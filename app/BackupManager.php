<?php

declare(strict_types=1);

final class BackupManager
{
    private const EXCLUDED_TABLES = ['sessions', 'login_attempts'];
    private const MAX_RESTORE_SQL_BYTES = 536_870_912;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
    ) {
    }

    public function create(
        string $triggerSource,
        int $userId,
        bool $includeFiles = true,
        string $label = '',
    ): array {
        $this->ensureTrackingTable();
        $triggerSource = $this->normalizeTrigger($triggerSource);
        $label = mb_substr(trim($label), 0, 190);

        $insert = $this->pdo->prepare(
            'INSERT INTO system_backups
                (label, trigger_source, backup_type, status, include_database, include_files, created_by)
             VALUES (:label, :trigger_source, :backup_type, \'running\', 1, :include_files, :created_by)'
        );
        $insert->execute([
            'label' => $label !== '' ? $label : $this->defaultLabel($triggerSource),
            'trigger_source' => $triggerSource,
            'backup_type' => $includeFiles ? 'full' : 'database',
            'include_files' => $includeFiles ? 1 : 0,
            'created_by' => $userId > 0 ? $userId : null,
        ]);
        $backupId = (int) $this->pdo->lastInsertId();

        $directoryName = gmdate('Ymd-His') . '-'
            . str_pad((string) $backupId, 6, '0', STR_PAD_LEFT)
            . '-' . substr(bin2hex(random_bytes(5)), 0, 8);
        $relativeDirectory = 'storage/backups/' . $directoryName;
        $directory = $this->root . '/' . $relativeDirectory;

        try {
            $this->ensureDirectory($directory);
            $this->pdo->prepare('UPDATE system_backups SET storage_path = :path WHERE id = :id')
                ->execute(['path' => $relativeDirectory, 'id' => $backupId]);

            $databaseFile = $directory . '/database.sql';
            $tableCount = $this->exportDatabase($databaseFile);

            $fileCount = 0;
            if ($includeFiles) {
                $fileCount = $this->copyProjectFiles($directory . '/files');
            } elseif (is_file($this->root . '/.env')) {
                $this->ensureDirectory($directory . '/config');
                if (!copy($this->root . '/.env', $directory . '/config/.env')) {
                    throw new RuntimeException('Die Konfigurationsdatei konnte nicht gesichert werden.');
                }
            }

            $manifest = [
                'product' => 'twitch-moddesk-backup',
                'format' => 1,
                'backup_id' => $backupId,
                'created_at' => gmdate(DATE_ATOM),
                'app_version' => $this->currentVersion(),
                'trigger_source' => $triggerSource,
                'backup_type' => $includeFiles ? 'full' : 'database',
                'database_tables' => $tableCount,
                'project_files' => $fileCount,
                'excluded_tables' => self::EXCLUDED_TABLES,
            ];
            $manifestFile = $directory . '/manifest.json';
            $encodedManifest = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            if (file_put_contents($manifestFile, $encodedManifest . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Das Backup-Manifest konnte nicht gespeichert werden.');
            }

            $archivePath = null;
            if (class_exists(ZipArchive::class)) {
                $archivePath = $this->createArchive($directory);
            }
            $size = $archivePath !== null
                ? (int) (filesize($archivePath) ?: 0)
                : $this->directorySize($directory);
            $checksumFile = $archivePath ?? $databaseFile;
            $checksum = hash_file('sha256', $checksumFile);
            if (!is_string($checksum)) {
                throw new RuntimeException('Die Backup-Prüfsumme konnte nicht berechnet werden.');
            }

            $finish = $this->pdo->prepare(
                'UPDATE system_backups SET status = \'completed\', size_bytes = :size_bytes,
                 checksum_sha256 = :checksum, metadata = :metadata, completed_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );
            $finish->execute([
                'size_bytes' => $size,
                'checksum' => $checksum,
                'metadata' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'id' => $backupId,
            ]);
        } catch (Throwable $exception) {
            try {
                $this->pdo->prepare(
                    'UPDATE system_backups SET status = \'failed\', error_message = :error,
                     completed_at = UTC_TIMESTAMP() WHERE id = :id'
                )->execute([
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                    'id' => $backupId,
                ]);
            } catch (Throwable) {
                // Der ursprüngliche Backupfehler bleibt maßgeblich.
            }
            throw $exception;
        }

        return $this->find($backupId)
            ?? throw new RuntimeException('Das erstellte Backup konnte nicht mehr geladen werden.');
    }

    public function restore(int $backupId, int $userId): array
    {
        $backup = $this->requireCompleted($backupId);
        $directory = $this->absoluteStoragePath((string) $backup['storage_path']);
        $databaseFile = $directory . '/database.sql';
        if (!is_file($databaseFile) || !is_readable($databaseFile)) {
            throw new RuntimeException('Die Datenbanksicherung dieses Backups fehlt.');
        }

        $filesDirectory = $directory . '/files';
        if ((int) $backup['include_files'] === 1) {
            if (!is_dir($filesDirectory)) {
                throw new RuntimeException('Die Dateisicherung dieses Backups fehlt.');
            }
            $this->restoreProjectFiles($filesDirectory);
        }

        $this->restoreDatabaseFile($databaseFile);

        try {
            $this->pdo->prepare(
                'UPDATE system_backups SET status = \'completed\', last_restored_at = UTC_TIMESTAMP(), last_restored_by = :user_id
                 WHERE id = :id'
            )->execute(['user_id' => $userId > 0 ? $userId : null, 'id' => $backupId]);
        } catch (Throwable) {
            // Ein älterer Datenbankstand kann die neuen Protokollfelder noch nicht enthalten.
        }

        return $backup;
    }

    public function restoreDatabaseOnly(int $backupId): void
    {
        $backup = $this->requireCompleted($backupId);
        $directory = $this->absoluteStoragePath((string) $backup['storage_path']);
        $this->restoreDatabaseFile($directory . '/database.sql');
    }

    public function delete(int $backupId): void
    {
        $backup = $this->find($backupId);
        if ($backup === null) {
            throw new RuntimeException('Das Backup wurde nicht gefunden.');
        }
        $directory = $this->absoluteStoragePath((string) $backup['storage_path']);
        $archive = $directory . '.zip';
        $this->removeTree($directory);
        if (is_file($archive) && !unlink($archive)) {
            throw new RuntimeException('Das Backup-Archiv konnte nicht gelöscht werden.');
        }
        $this->pdo->prepare('DELETE FROM system_backups WHERE id = :id')->execute(['id' => $backupId]);
    }

    public function downloadFile(int $backupId): array
    {
        $backup = $this->requireCompleted($backupId);
        $directory = $this->absoluteStoragePath((string) $backup['storage_path']);
        $archive = $directory . '.zip';
        if (!is_file($archive)) {
            if (!class_exists(ZipArchive::class)) {
                $databaseFile = $directory . '/database.sql';
                if (!is_file($databaseFile)) {
                    throw new RuntimeException('Für dieses Backup ist keine herunterladbare Datei vorhanden.');
                }
                return [
                    'path' => $databaseFile,
                    'name' => 'moddesk-backup-' . $backupId . '.sql',
                    'mime' => 'application/sql',
                ];
            }
            $archive = $this->createArchive($directory);
        }
        return [
            'path' => $archive,
            'name' => 'moddesk-backup-' . $backupId . '.zip',
            'mime' => 'application/zip',
        ];
    }

    public function find(int $backupId): ?array
    {
        $this->ensureTrackingTable();
        $statement = $this->pdo->prepare(
            'SELECT sb.*, u.display_name AS created_by_name, r.display_name AS restored_by_name
             FROM system_backups sb
             LEFT JOIN users u ON u.id = sb.created_by
             LEFT JOIN users r ON r.id = sb.last_restored_by
             WHERE sb.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $backupId]);
        return $statement->fetch() ?: null;
    }

    public function all(int $limit = 100): array
    {
        $this->ensureTrackingTable();
        $limit = max(1, min(250, $limit));
        return $this->pdo->query(
            'SELECT sb.*, u.display_name AS created_by_name, r.display_name AS restored_by_name
             FROM system_backups sb
             LEFT JOIN users u ON u.id = sb.created_by
             LEFT JOIN users r ON r.id = sb.last_restored_by
             ORDER BY sb.created_at DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function latestCompleted(): ?array
    {
        $this->ensureTrackingTable();
        $row = $this->pdo->query(
            'SELECT * FROM system_backups WHERE status = \'completed\' ORDER BY created_at DESC LIMIT 1'
        )->fetch();
        return $row ?: null;
    }

    public function registerExisting(array $backup): void
    {
        $this->ensureTrackingTable();
        $statement = $this->pdo->prepare(
            'INSERT INTO system_backups
                (id, label, trigger_source, backup_type, status, storage_path, include_database,
                 include_files, size_bytes, checksum_sha256, error_message, metadata, created_by,
                 created_at, completed_at)
             VALUES
                (:id, :label, :trigger_source, :backup_type, :status, :storage_path, :include_database,
                 :include_files, :size_bytes, :checksum_sha256, :error_message, :metadata, :created_by,
                 :created_at, :completed_at)
             ON DUPLICATE KEY UPDATE label = VALUES(label), trigger_source = VALUES(trigger_source),
                 backup_type = VALUES(backup_type), status = VALUES(status), storage_path = VALUES(storage_path),
                 include_database = VALUES(include_database), include_files = VALUES(include_files),
                 size_bytes = VALUES(size_bytes), checksum_sha256 = VALUES(checksum_sha256),
                 error_message = VALUES(error_message), metadata = VALUES(metadata),
                 completed_at = VALUES(completed_at)'
        );
        $statement->execute([
            'id' => (int) $backup['id'],
            'label' => (string) $backup['label'],
            'trigger_source' => (string) $backup['trigger_source'],
            'backup_type' => (string) $backup['backup_type'],
            'status' => (string) $backup['status'],
            'storage_path' => (string) $backup['storage_path'],
            'include_database' => (int) $backup['include_database'],
            'include_files' => (int) $backup['include_files'],
            'size_bytes' => $backup['size_bytes'] !== null ? (int) $backup['size_bytes'] : null,
            'checksum_sha256' => $backup['checksum_sha256'] ?: null,
            'error_message' => $backup['error_message'] ?: null,
            'metadata' => $backup['metadata'] ?: null,
            'created_by' => $backup['created_by'] !== null ? (int) $backup['created_by'] : null,
            'created_at' => (string) $backup['created_at'],
            'completed_at' => $backup['completed_at'] ?: null,
        ]);
    }

    public function isReady(): bool
    {
        try {
            $this->ensureTrackingTable();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function exportDatabase(string $targetFile): int
    {
        $handle = fopen($targetFile, 'wb');
        if (!is_resource($handle)) {
            throw new RuntimeException('Die Datenbanksicherung konnte nicht angelegt werden.');
        }

        try {
            $header = "-- Twitch ModDesk database backup\n"
                . '-- Created: ' . gmdate(DATE_ATOM) . "\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            $this->write($handle, $header);

            $tables = $this->pdo->query(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
                 ORDER BY table_name"
            )->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = 0;
            foreach ($tables as $tableName) {
                $table = (string) $tableName;
                if (in_array($table, self::EXCLUDED_TABLES, true)) {
                    continue;
                }
                $quotedTable = $this->quoteIdentifier($table);
                $createResult = $this->pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
                if (!is_array($createResult) || !isset($createResult[1])) {
                    throw new RuntimeException('Tabellenstruktur konnte nicht gesichert werden: ' . $table);
                }
                $this->write(
                    $handle,
                    'DROP TABLE IF EXISTS ' . $quotedTable . ";\n"
                    . (string) $createResult[1] . ";\n\n",
                );

                $rows = $this->pdo->query('SELECT * FROM ' . $quotedTable);
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map(
                        fn (string $column): string => $this->quoteIdentifier($column),
                        array_keys($row),
                    );
                    $values = array_map(fn (mixed $value): string => $this->sqlValue($value), array_values($row));
                    $this->write(
                        $handle,
                        'INSERT INTO ' . $quotedTable
                        . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n",
                    );
                }
                $this->write($handle, "\n");
                $tableCount++;
            }
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            return $tableCount;
        } finally {
            fclose($handle);
        }
    }

    private function restoreDatabaseFile(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Die Datenbanksicherung ist nicht lesbar.');
        }
        $size = (int) (filesize($file) ?: 0);
        if ($size < 1 || $size > self::MAX_RESTORE_SQL_BYTES) {
            throw new RuntimeException('Die Datenbanksicherung besitzt eine ungültige Größe.');
        }
        $header = file_get_contents($file, false, null, 0, 256);
        if (!is_string($header) || !str_contains($header, 'Twitch ModDesk database backup')) {
            throw new RuntimeException('Die Datei ist keine gültige ModDesk-Datenbanksicherung.');
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $tables = $this->pdo->query(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tableName) {
                $table = (string) $tableName;
                if (!in_array($table, self::EXCLUDED_TABLES, true)) {
                    $this->pdo->exec('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
                }
            }
            SchemaMigrator::executeFile($this->pdo, $file);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function copyProjectFiles(string $targetRoot): int
    {
        $this->ensureDirectory($targetRoot);
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($path, strlen(str_replace('\\', '/', $this->root))), '/');
            if ($relative === '' || $this->isExcludedProjectPath($relative)) {
                continue;
            }
            $target = $targetRoot . '/' . $relative;
            if ($item->isLink()) {
                continue;
            }
            if ($item->isDir()) {
                $this->ensureDirectory($target);
                continue;
            }
            $this->ensureDirectory(dirname($target));
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Projektdatei konnte nicht gesichert werden: ' . $relative);
            }
            $count++;
        }
        return $count;
    }

    private function restoreProjectFiles(string $sourceRoot): void
    {
        $this->removeFilesAbsentFromSnapshot($sourceRoot);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($sourceRoot))), '/');
            if ($relative === '' || $this->isExcludedProjectPath($relative)) {
                continue;
            }
            $target = $this->root . '/' . $relative;
            if ($item->isDir()) {
                $this->ensureDirectory($target);
                continue;
            }
            $this->ensureDirectory(dirname($target));
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Projektdatei konnte nicht wiederhergestellt werden: ' . $relative);
            }
        }
    }

    private function removeFilesAbsentFromSnapshot(string $sourceRoot): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = ltrim(
                str_replace('\\', '/', substr($item->getPathname(), strlen($this->root))),
                '/',
            );
            if ($relative === '' || $this->isExcludedProjectPath($relative)) {
                continue;
            }
            $snapshotPath = $sourceRoot . '/' . $relative;
            if ($item->isFile() && !is_file($snapshotPath)) {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException('Eine neuere Projektdatei konnte beim Rollback nicht entfernt werden: ' . $relative);
                }
            } elseif ($item->isDir() && !is_dir($snapshotPath)) {
                @rmdir($item->getPathname());
            }
        }
    }

    private function createArchive(string $directory): string
    {
        $archivePath = $directory . '.zip';
        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException('Das Backup-ZIP konnte nicht angelegt werden.');
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $item) {
                $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($directory))), '/');
                if ($relative === '') {
                    continue;
                }
                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } elseif (!$item->isLink() && !$zip->addFile($item->getPathname(), $relative)) {
                    throw new RuntimeException('Eine Datei konnte nicht ins Backup-ZIP übernommen werden.');
                }
            }
        } finally {
            $zip->close();
        }
        return $archivePath;
    }

    private function requireCompleted(int $backupId): array
    {
        $backup = $this->find($backupId);
        if ($backup === null || (string) $backup['status'] !== 'completed') {
            throw new RuntimeException('Dieses Backup ist nicht vollständig und kann nicht verwendet werden.');
        }
        return $backup;
    }

    private function absoluteStoragePath(string $relativePath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!preg_match('#^storage/backups/[A-Za-z0-9.-]+$#', $normalized)) {
            throw new RuntimeException('Der gespeicherte Backup-Pfad ist ungültig.');
        }
        return $this->root . '/' . $normalized;
    }

    private function isExcludedProjectPath(string $relativePath): bool
    {
        $path = ltrim(str_replace('\\', '/', $relativePath), '/');
        foreach ([
            '.git',
            'node_modules',
            'storage/backups',
            'storage/update-backups',
            'storage/update-work',
            'storage/update-locks',
            'storage/github-updates',
            'storage/module-work',
            'storage/module-backups',
            'storage/logs',
            'storage/cache',
            'storage/tmp',
        ] as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded . '/')) {
                return true;
            }
        }
        return false;
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '0x' . bin2hex((string) $value);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) {
            throw new RuntimeException('Ungültiger Datenbankbezeichner.');
        }
        return '`' . $identifier . '`';
    }

    private function ensureTrackingTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS system_backups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(190) NOT NULL,
                trigger_source VARCHAR(30) NOT NULL DEFAULT \'manual\',
                backup_type VARCHAR(20) NOT NULL DEFAULT \'full\',
                status VARCHAR(20) NOT NULL DEFAULT \'running\',
                storage_path VARCHAR(500) NULL,
                include_database TINYINT(1) NOT NULL DEFAULT 1,
                include_files TINYINT(1) NOT NULL DEFAULT 1,
                size_bytes BIGINT UNSIGNED NULL,
                checksum_sha256 CHAR(64) NULL,
                error_message VARCHAR(2000) NULL,
                metadata JSON NULL,
                created_by BIGINT UNSIGNED NULL,
                last_restored_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                last_restored_at DATETIME NULL,
                INDEX idx_system_backups_created (created_at),
                INDEX idx_system_backups_status (status, created_at),
                CONSTRAINT fk_system_backup_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_system_backup_restorer FOREIGN KEY (last_restored_by) REFERENCES users(id) ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function normalizeTrigger(string $trigger): string
    {
        return in_array($trigger, ['manual', 'update', 'migration', 'restore'], true) ? $trigger : 'manual';
    }

    private function defaultLabel(string $trigger): string
    {
        return match ($trigger) {
            'update' => 'Automatisch vor Update',
            'migration' => 'Automatisch vor Migration',
            'restore' => 'Automatisch vor Wiederherstellung',
            default => 'Manuelles Backup',
        };
    }

    private function currentVersion(): string
    {
        $version = trim((string) @file_get_contents($this->root . '/VERSION'));
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '0.0.0';
    }

    private function write($handle, string $content): void
    {
        if (fwrite($handle, $content) === false) {
            throw new RuntimeException('Die Datenbanksicherung konnte nicht vollständig geschrieben werden.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . basename($directory));
        }
    }

    private function directorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }
        return $size;
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory) || !str_starts_with(str_replace('\\', '/', $directory), str_replace('\\', '/', $this->root . '/storage/backups/'))) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new RuntimeException('Ein Backup-Ordner konnte nicht gelöscht werden.');
                }
            } elseif (!unlink($item->getPathname())) {
                throw new RuntimeException('Eine Backup-Datei konnte nicht gelöscht werden.');
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Der Backup-Ordner konnte nicht gelöscht werden.');
        }
    }
}

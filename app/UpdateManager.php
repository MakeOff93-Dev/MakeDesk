<?php

declare(strict_types=1);

final class UpdateManager
{
    private const MAX_ARCHIVE_BYTES = 52_428_800;
    private const MAX_UNCOMPRESSED_BYTES = 209_715_200;
    private const MAX_ENTRY_BYTES = 26_214_400;
    private const MAX_ENTRIES = 2500;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
    ) {
    }

    public function applyUploadedPackage(array $upload, int $userId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung ZIP fehlt. Aktiviere in XAMPP die Erweiterung extension=zip.');
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }

        $temporaryFile = (string) ($upload['tmp_name'] ?? '');
        $archiveSize = (int) ($upload['size'] ?? 0);
        $originalName = basename((string) ($upload['name'] ?? 'update.zip'));
        if (
            $temporaryFile === ''
            || !is_uploaded_file($temporaryFile)
            || $archiveSize < 1
            || $archiveSize > self::MAX_ARCHIVE_BYTES
            || strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            throw new RuntimeException('Bitte lade ein gültiges ModDesk-ZIP mit höchstens 50 MB hoch.');
        }

        return $this->applyPackage($temporaryFile, $originalName, $userId);
    }

    public function applyLocalPackage(string $packagePath, string $originalName, int $userId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung ZIP fehlt. Aktiviere in XAMPP die Erweiterung extension=zip.');
        }
        $originalName = basename($originalName);
        $size = is_file($packagePath) ? (int) (filesize($packagePath) ?: 0) : 0;
        if (
            $packagePath === ''
            || !is_file($packagePath)
            || !is_readable($packagePath)
            || $size < 1
            || $size > self::MAX_ARCHIVE_BYTES
            || strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            throw new RuntimeException('Das heruntergeladene ModDesk-ZIP ist ungültig oder zu groß.');
        }
        return $this->applyPackage($packagePath, $originalName, $userId);
    }

    private function applyPackage(string $temporaryFile, string $originalName, int $userId): array
    {

        $lockDirectory = $this->root . '/storage/update-locks';
        $this->ensureDirectory($lockDirectory);
        $lock = fopen($lockDirectory . '/update.lock', 'c+');
        if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Es läuft bereits ein ModDesk-Update.');
        }

        $workDirectory = $this->root . '/storage/update-work/' . bin2hex(random_bytes(12));
        $backupDirectory = '';
        $backupId = null;
        $updateId = null;
        $newFiles = [];
        $backedUpFiles = [];
        $databaseTouched = false;
        try {
            $this->ensureDirectory($workDirectory);
            $inspection = $this->inspectAndStage($temporaryFile, $workDirectory);
            $currentVersion = $this->currentVersion();
            $newVersion = $inspection['version'];
            if (version_compare($currentVersion, $inspection['minimum_version'], '<')) {
                throw new RuntimeException(
                    'Dieses Update benötigt mindestens ModDesk ' . $inspection['minimum_version']
                    . '. Installiert ist ' . $currentVersion . '.'
                );
            }
            if (!version_compare($newVersion, $currentVersion, '>')) {
                throw new RuntimeException('Das Update muss neuer als die installierte Version ' . $currentVersion . ' sein.');
            }

            $checksum = hash_file('sha256', $temporaryFile);
            if (!is_string($checksum)) {
                throw new RuntimeException('Die Prüfsumme des Updatepakets konnte nicht berechnet werden.');
            }
            $updateId = $this->startLog($currentVersion, $newVersion, $originalName, $checksum, '', $userId);
            $backup = (new BackupManager($this->pdo, $this->root))->create(
                'update',
                $userId,
                true,
                'Vor Update ' . $currentVersion . ' → ' . $newVersion,
            );
            $backupId = (int) $backup['id'];
            $backupDirectory = $this->root . '/' . ltrim((string) $backup['storage_path'], '/');
            $this->setBackupPath($updateId, (string) $backup['storage_path']);
            if (!copy($temporaryFile, $backupDirectory . '/package.zip')) {
                throw new RuntimeException('Das Updatepaket konnte nicht für die Sicherung kopiert werden.');
            }

            foreach ($inspection['files'] as $relativePath) {
                if ($this->isPreservedPath($relativePath)) {
                    continue;
                }
                $source = $workDirectory . '/' . $relativePath;
                $destination = $this->root . '/' . $relativePath;
                if (is_dir($source)) {
                    $this->ensureDirectory($destination);
                    continue;
                }

                $this->ensureDirectory(dirname($destination));
                if (is_file($destination)) {
                    $backupTarget = $backupDirectory . '/files/' . $relativePath;
                    $this->ensureDirectory(dirname($backupTarget));
                    if (!copy($destination, $backupTarget)) {
                        throw new RuntimeException('Sicherung fehlgeschlagen: ' . $relativePath);
                    }
                    $backedUpFiles[$relativePath] = $backupTarget;
                } else {
                    $newFiles[] = $relativePath;
                }

                if (!copy($source, $destination)) {
                    throw new RuntimeException('Update-Datei konnte nicht installiert werden: ' . $relativePath);
                }
            }

            require_once $this->root . '/app/SchemaMigrator.php';
            $databaseTouched = true;
            $migrations = SchemaMigrator::migrate(
                $this->pdo,
                $this->root . '/database/migrations',
                $userId,
                $backupId,
            );
            $this->finishLog($updateId, 'completed', null, [
                'migrations' => $migrations,
                'files' => count($inspection['files']),
                'backup_id' => $backupId,
            ]);

            return [
                'from_version' => $currentVersion,
                'to_version' => $newVersion,
                'files' => count($inspection['files']),
                'migrations' => $migrations,
                'backup_id' => $backupId,
            ];
        } catch (Throwable $exception) {
            $rollbackError = null;
            if ($backedUpFiles !== [] || $newFiles !== []) {
                try {
                    $this->rollbackFiles($backedUpFiles, $newFiles);
                } catch (Throwable $rollbackException) {
                    $rollbackError = $rollbackException->getMessage();
                }
            }
            if ($databaseTouched && $backupId !== null) {
                try {
                    (new BackupManager($this->pdo, $this->root))->restoreDatabaseOnly($backupId);
                    try {
                        $this->pdo->prepare(
                            'UPDATE system_backups SET status = \'completed\' WHERE id = :id'
                        )->execute(['id' => $backupId]);
                    } catch (Throwable) {
                        // Ein älterer Datenbankstand kann die Backup-Tabelle noch nicht kennen.
                    }
                } catch (Throwable $databaseRollbackException) {
                    $databaseMessage = 'Datenbank-Rollback fehlgeschlagen: ' . $databaseRollbackException->getMessage();
                    $rollbackError = $rollbackError !== null
                        ? $rollbackError . ' ' . $databaseMessage
                        : $databaseMessage;
                }
            }
            if ($updateId !== null) {
                $message = $exception->getMessage() . ($rollbackError !== null ? ' Rollback-Fehler: ' . $rollbackError : '');
                $this->finishLog($updateId, 'failed', $message, ['rollback_error' => $rollbackError]);
            }
            throw $exception;
        } finally {
            $this->removeTree($workDirectory);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function inspectAndStage(string $archivePath, string $workDirectory): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($result !== true) {
            throw new RuntimeException('Das Update-ZIP konnte nicht geöffnet werden.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('Das Update-ZIP enthält eine ungültige Anzahl Dateien.');
            }

            $names = [];
            $entrySizes = [];
            $totalSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('Ein ZIP-Eintrag konnte nicht geprüft werden.');
                }
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                $this->validateArchivePath($name);
                $size = (int) ($stat['size'] ?? 0);
                if ($size < 0 || $size > self::MAX_ENTRY_BYTES) {
                    throw new RuntimeException('Eine Update-Datei ist ungewöhnlich groß: ' . $name);
                }
                $totalSize += $size;
                if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('Das entpackte Update überschreitet 200 MB.');
                }

                $operatingSystem = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                    $fileType = ($attributes >> 16) & 0xF000;
                    if ($fileType === 0xA000) {
                        throw new RuntimeException('Symbolische Links sind in Updatepaketen nicht erlaubt.');
                    }
                }
                $names[] = $name;
                $entrySizes[] = $size;
            }

            $prefix = $this->detectPrefix($names);
            $mappedFiles = [];
            $seenPaths = [];
            foreach ($names as $index => $name) {
                if ($prefix !== '' && $name !== rtrim($prefix, '/') && !str_starts_with($name, $prefix)) {
                    throw new RuntimeException('Das Update-ZIP enthält Dateien außerhalb des Projektordners.');
                }
                $relativePath = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
                $relativePath = ltrim((string) $relativePath, '/');
                if ($relativePath === '') {
                    continue;
                }
                $this->validateArchivePath($relativePath);
                $pathKey = strtolower(rtrim($relativePath, '/'));
                if (isset($seenPaths[$pathKey])) {
                    throw new RuntimeException('Das Update-ZIP enthält einen Dateipfad mehrfach: ' . $relativePath);
                }
                $seenPaths[$pathKey] = true;
                $mappedFiles[] = $relativePath;
                if (str_ends_with($relativePath, '/')) {
                    $this->ensureDirectory($workDirectory . '/' . rtrim($relativePath, '/'));
                    continue;
                }

                $target = $workDirectory . '/' . $relativePath;
                $this->ensureDirectory(dirname($target));
                $source = $zip->getStream($name);
                $destination = fopen($target, 'wb');
                if (!is_resource($source) || !is_resource($destination)) {
                    if (is_resource($source)) {
                        fclose($source);
                    }
                    if (is_resource($destination)) {
                        fclose($destination);
                    }
                    throw new RuntimeException('Eine Update-Datei konnte nicht entpackt werden: ' . $relativePath);
                }
                $copiedBytes = stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);
                if ($copiedBytes === false || $copiedBytes !== $entrySizes[$index]) {
                    throw new RuntimeException('Eine Update-Datei wurde nicht vollständig entpackt: ' . $relativePath);
                }
            }
        } finally {
            $zip->close();
        }

        foreach (['VERSION', 'CHANGELOG.md', 'moddesk-update.json', 'index.php', 'install.php', 'app/bootstrap.php', 'public/index.php', 'database/schema.sql'] as $required) {
            if (!is_file($workDirectory . '/' . $required)) {
                throw new RuntimeException('Das ZIP ist kein vollständiges ModDesk-Update: ' . $required . ' fehlt.');
            }
        }

        $manifestRaw = file_get_contents($workDirectory . '/moddesk-update.json');
        $manifest = json_decode((string) $manifestRaw, true, 32, JSON_THROW_ON_ERROR);
        if (
            !is_array($manifest)
            || ($manifest['product'] ?? '') !== 'twitch-moddesk'
            || (int) ($manifest['package_format'] ?? 0) !== 1
            || ($manifest['entrypoint'] ?? '') !== 'index.php'
        ) {
            throw new RuntimeException('Das Update-Manifest gehört nicht zum Twitch ModDesk.');
        }
        $version = trim((string) file_get_contents($workDirectory . '/VERSION'));
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) || ($manifest['version'] ?? '') !== $version) {
            throw new RuntimeException('Die Versionsangaben im Updatepaket sind ungültig.');
        }
        $minimumPhp = (string) ($manifest['minimum_php'] ?? '8.2.0');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $minimumPhp) || version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new RuntimeException('Dieses Update benötigt mindestens PHP ' . $minimumPhp . '.');
        }
        $minimumVersion = trim((string) ($manifest['minimum_version'] ?? '0.0.0'));
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $minimumVersion)) {
            throw new RuntimeException('Die Mindestversion im Update-Manifest ist ungültig.');
        }

        return [
            'version' => $version,
            'minimum_version' => $minimumVersion,
            'files' => array_values(array_unique($mappedFiles)),
        ];
    }

    private function detectPrefix(array $names): string
    {
        if (in_array('VERSION', $names, true)) {
            return '';
        }
        $candidates = [];
        foreach ($names as $name) {
            if (preg_match('#^([^/]+)/VERSION$#', $name, $match)) {
                $candidates[] = $match[1] . '/';
            }
        }
        $candidates = array_values(array_unique($candidates));
        if (count($candidates) !== 1) {
            throw new RuntimeException('Der Projektordner im Update-ZIP konnte nicht eindeutig erkannt werden.');
        }
        return $candidates[0];
    }

    private function validateArchivePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new RuntimeException('Das Update-ZIP enthält einen unsicheren Dateipfad.');
        }
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new RuntimeException('Relative Pfade sind im Update-ZIP nicht erlaubt.');
            }
        }
    }

    private function isPreservedPath(string $relativePath): bool
    {
        $path = ltrim(str_replace('\\', '/', $relativePath), '/');
        return $path === '.env'
            || str_starts_with($path, '.env/')
            || str_starts_with($path, 'storage/')
            || $path === 'storage'
            || str_starts_with($path, 'modules/')
            || $path === 'modules'
            || str_starts_with($path, '.git/')
            || $path === '.git'
            || str_starts_with($path, 'vendor/')
            || str_starts_with($path, 'node_modules/');
    }

    private function currentVersion(): string
    {
        $version = trim((string) @file_get_contents($this->root . '/VERSION'));
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '0.0.0';
    }

    private function startLog(
        string $fromVersion,
        string $toVersion,
        string $filename,
        string $checksum,
        string $backupPath,
        int $userId,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO system_updates
                (from_version, to_version, package_name, checksum_sha256, status, backup_path, applied_by)
             VALUES (:from_version, :to_version, :package_name, :checksum, \'running\', :backup_path, :applied_by)'
        );
        $statement->execute([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'package_name' => mb_substr($filename, 0, 190),
            'checksum' => $checksum,
            'backup_path' => str_replace($this->root . '/', '', $backupPath),
            'applied_by' => $userId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function finishLog(int $id, string $status, ?string $error, array $details): void
    {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE system_updates SET status = :status, error_message = :error_message,
                 details = :details, completed_at = UTC_TIMESTAMP() WHERE id = :id'
            );
            $statement->execute([
                'status' => $status,
                'error_message' => $error !== null ? mb_substr($error, 0, 2000) : null,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'id' => $id,
            ]);
        } catch (Throwable) {
            // Der ursprüngliche Updatefehler bleibt maßgeblich.
        }
    }

    private function setBackupPath(int $id, string $backupPath): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE system_updates SET backup_path = :backup_path WHERE id = :id'
        );
        $statement->execute([
            'backup_path' => ltrim(str_replace('\\', '/', $backupPath), '/'),
            'id' => $id,
        ]);
    }

    private function rollbackFiles(array $backedUpFiles, array $newFiles): void
    {
        foreach ($backedUpFiles as $relativePath => $backupFile) {
            $destination = $this->root . '/' . $relativePath;
            $this->ensureDirectory(dirname($destination));
            if (!copy($backupFile, $destination)) {
                throw new RuntimeException('Datei konnte nicht wiederhergestellt werden: ' . $relativePath);
            }
        }
        foreach (array_reverse($newFiles) as $relativePath) {
            $target = $this->root . '/' . $relativePath;
            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . basename($directory));
        }
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory) || !str_contains($directory, '/storage/update-work/')) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Das Update-ZIP überschreitet das Upload-Limit des Servers.',
            UPLOAD_ERR_PARTIAL => 'Das Update-ZIP wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Bitte wähle ein Update-ZIP aus.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dem PHP-Server fehlt ein temporärer Upload-Ordner.',
            UPLOAD_ERR_CANT_WRITE => 'Der PHP-Server konnte die hochgeladene Datei nicht zwischenspeichern.',
            UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt.',
            default => 'Das Update-ZIP konnte nicht hochgeladen werden.',
        };
    }
}

<?php

declare(strict_types=1);

final class SchemaMigrator
{
    public static function executeFile(PDO $pdo, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException(basename($file) . ' konnte nicht gelesen werden.');
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*(?:\r?\n|$)/', trim($sql)) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function migrate(
        PDO $pdo,
        string $directory,
        ?int $userId = null,
        ?int $backupId = null,
        ?array $selectedNames = null,
    ): array
    {
        self::ensureTrackingTable($pdo);

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_NATURAL);
        $alreadyApplied = $pdo->query('SELECT migration_name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $known = array_fill_keys(array_map('strval', $alreadyApplied), true);
        $applied = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($known[$name])) {
                continue;
            }
            if ($selectedNames !== null && !in_array($name, $selectedNames, true)) {
                continue;
            }

            $runId = self::startRun($pdo, $name, $userId, $backupId);
            try {
                self::executeFile($pdo, $file);
                $statement = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
                $statement->execute(['name' => $name]);
                self::finishRun($pdo, $runId, 'completed', null);
                $applied[] = $name;
            } catch (Throwable $exception) {
                self::finishRun($pdo, $runId, 'failed', $exception->getMessage());
                throw new RuntimeException('Migration ' . $name . ' fehlgeschlagen: ' . $exception->getMessage(), 0, $exception);
            }
        }

        return $applied;
    }

    public static function migrateOne(
        PDO $pdo,
        string $directory,
        string $migrationName,
        ?int $userId = null,
        ?int $backupId = null,
    ): array {
        $migrationName = basename($migrationName);
        if (!preg_match('/^\d{3}_[A-Za-z0-9_.-]+\.sql$/', $migrationName)) {
            throw new InvalidArgumentException('Die ausgewählte Migration ist ungültig.');
        }
        $status = self::status($pdo, $directory);
        if (!in_array($migrationName, $status['available'], true)) {
            throw new RuntimeException('Die ausgewählte Migration wurde nicht gefunden.');
        }
        if (in_array($migrationName, $status['applied'], true)) {
            return [];
        }
        $firstPending = $status['pending'][0] ?? null;
        if ($firstPending !== $migrationName) {
            throw new RuntimeException(
                'Migrationen müssen in Reihenfolge ausgeführt werden. Als Nächstes ist '
                . ($firstPending ?? 'keine Migration') . ' vorgesehen.'
            );
        }
        return self::migrate($pdo, $directory, $userId, $backupId, [$migrationName]);
    }

    public static function status(PDO $pdo, string $directory): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_NATURAL);
        $available = array_map('basename', $files);
        $applied = [];
        try {
            $applied = array_map('strval', $pdo->query('SELECT migration_name FROM schema_migrations ORDER BY migration_name')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            $applied = [];
        }
        return [
            'available' => $available,
            'applied' => $applied,
            'pending' => array_values(array_diff($available, $applied)),
            'files' => array_map(
                static fn (string $file): array => [
                    'name' => basename($file),
                    'checksum' => (string) (hash_file('sha256', $file) ?: ''),
                    'size_bytes' => (int) (filesize($file) ?: 0),
                ],
                $files,
            ),
        ];
    }

    private static function ensureTrackingTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration_name VARCHAR(190) PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function startRun(
        PDO $pdo,
        string $name,
        ?int $userId,
        ?int $backupId,
    ): ?int {
        try {
            $exists = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'migration_runs'"
            );
            if ((int) $exists->fetchColumn() === 0) {
                return null;
            }
            $statement = $pdo->prepare(
                'INSERT INTO migration_runs (migration_name, status, backup_id, executed_by)
                 VALUES (:migration_name, \'running\', :backup_id, :executed_by)'
            );
            $statement->execute([
                'migration_name' => $name,
                'backup_id' => $backupId,
                'executed_by' => $userId,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (Throwable) {
            return null;
        }
    }

    private static function finishRun(PDO $pdo, ?int $runId, string $status, ?string $error): void
    {
        if ($runId === null) {
            return;
        }
        try {
            $statement = $pdo->prepare(
                'UPDATE migration_runs SET status = :status, error_message = :error,
                 completed_at = UTC_TIMESTAMP() WHERE id = :id'
            );
            $statement->execute([
                'status' => $status,
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
                'id' => $runId,
            ]);
        } catch (Throwable) {
            // Der Migrationsstatus darf den ursprünglichen Ausgang nicht verändern.
        }
    }
}

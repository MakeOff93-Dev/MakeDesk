<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/Env.php';
Env::load(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/SchemaMigrator.php';

try {
    $applied = SchemaMigrator::migrate(Database::connection(), dirname(__DIR__) . '/database/migrations');
    if ($applied === []) {
        fwrite(STDOUT, "✓ Die Datenbank ist bereits aktuell.\n");
        exit(0);
    }

    foreach ($applied as $migration) {
        fwrite(STDOUT, '✓ Migration ausgeführt: ' . $migration . PHP_EOL);
    }
    fwrite(STDOUT, "✓ Datenbank-Upgrade abgeschlossen.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fehler: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (twitch()->connection() === null) {
        fwrite(STDOUT, "Keine Twitch-Verbindung vorhanden.\n");
        exit(0);
    }
    $validation = twitch()->validateConnection();
    fwrite(STDOUT, 'Twitch-Token gültig; Restlaufzeit: ' . (int) ($validation['expires_in'] ?? 0) . " Sekunden.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Twitch-Validierung fehlgeschlagen: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}


<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo 'base64:' . base64_encode(random_bytes(48)) . PHP_EOL;


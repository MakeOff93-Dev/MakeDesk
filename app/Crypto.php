<?php

declare(strict_types=1);

final class Crypto
{
    private readonly string $key;

    public function __construct(string $appKey)
    {
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY muss mindestens 32 Zeichen lang sein.');
        }

        $this->key = hash('sha256', $appKey, true);
    }

    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipherText === false) {
            throw new RuntimeException('Verschlüsselung ist fehlgeschlagen.');
        }

        return 'v1:' . base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Unbekanntes Token-Format.');
        }

        $payload = base64_decode(substr($encrypted, 3), true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Beschädigtes Token.');
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipherText = substr($payload, 28);
        $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plainText === false) {
            throw new RuntimeException('Token konnte nicht entschlüsselt werden. Wurde APP_KEY geändert?');
        }

        return $plainText;
    }
}


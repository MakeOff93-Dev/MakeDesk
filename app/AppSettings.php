<?php

declare(strict_types=1);

final class AppSettings
{
    private array $cache = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Crypto $crypto,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->row($key);
        if ($row === null || $row['setting_value'] === null) {
            return $default;
        }

        $value = (string) $row['setting_value'];
        if ((int) $row['is_secret'] === 1 && $value !== '') {
            return $this->crypto->decrypt($value);
        }
        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function hasValue(string $key): bool
    {
        $row = $this->row($key);
        return $row !== null && trim((string) ($row['setting_value'] ?? '')) !== '';
    }

    public function set(string $key, string $value, bool $secret, ?int $updatedBy): void
    {
        if (!preg_match('/^[a-z0-9_.-]{2,100}$/', $key)) {
            throw new InvalidArgumentException('Ungültiger Einstellungsname.');
        }

        $storedValue = $secret && $value !== '' ? $this->crypto->encrypt($value) : $value;
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value, is_secret, updated_by)
             VALUES (:setting_key, :setting_value, :is_secret, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret),
                updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $storedValue,
            'is_secret' => $secret ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
        unset($this->cache[$key]);
    }

    public function setSecretWhenProvided(string $key, string $value, ?int $updatedBy): void
    {
        if (trim($value) !== '') {
            $this->set($key, trim($value), true, $updatedBy);
        }
    }

    private function row(string $key): ?array
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $statement = $this->pdo->prepare(
            'SELECT setting_value, is_secret FROM settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);
        $row = $statement->fetch();
        $this->cache[$key] = $row ?: null;
        return $this->cache[$key];
    }
}

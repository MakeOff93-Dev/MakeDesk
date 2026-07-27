<?php

declare(strict_types=1);

final class SecurityManager
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
    ) {
    }

    public function checks(): array
    {
        $storageWritable = is_dir($this->root . '/storage') && is_writable($this->root . '/storage');
        $envFile = $this->root . '/.env';
        $appKey = (string) Env::get('APP_KEY', '');
        $production = strtolower((string) Env::get('APP_ENV', 'production')) === 'production';
        $debugDisabled = !Env::bool('APP_DEBUG', false);
        $sessionSecure = Env::bool('SESSION_SECURE', false);
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        return [
            [
                'key' => 'production_mode',
                'label' => 'Produktionsmodus',
                'ok' => $production && $debugDisabled,
                'severity' => $debugDisabled ? 'warning' : 'critical',
                'message' => $production && $debugDisabled
                    ? 'Debug-Ausgaben sind deaktiviert.'
                    : 'Setze APP_ENV=production und APP_DEBUG=false.',
            ],
            [
                'key' => 'app_key',
                'label' => 'Verschlüsselungsschlüssel',
                'ok' => strlen($appKey) >= 32 && !str_contains($appKey, 'change-this'),
                'severity' => 'critical',
                'message' => strlen($appKey) >= 32 && !str_contains($appKey, 'change-this')
                    ? 'APP_KEY besitzt eine ausreichende Länge.'
                    : 'APP_KEY muss durch einen zufälligen Schlüssel mit mindestens 32 Zeichen ersetzt werden.',
            ],
            [
                'key' => 'environment_file',
                'label' => 'Konfigurationsschutz',
                'ok' => is_file($envFile) && is_file($this->root . '/.htaccess'),
                'severity' => 'critical',
                'message' => is_file($envFile) && is_file($this->root . '/.htaccess')
                    ? '.env und Apache-Schutzdatei sind vorhanden.'
                    : 'Die .env oder die Schutzdatei im Projektstamm fehlt.',
            ],
            [
                'key' => 'storage',
                'label' => 'Geschützter Speicher',
                'ok' => $storageWritable && is_file($this->root . '/storage/.htaccess'),
                'severity' => 'critical',
                'message' => $storageWritable && is_file($this->root . '/storage/.htaccess')
                    ? 'storage ist beschreibbar und gegen Webzugriff geschützt.'
                    : 'storage ist nicht beschreibbar oder seine Schutzdatei fehlt.',
            ],
            [
                'key' => 'https_session',
                'label' => 'HTTPS-Sitzungen',
                'ok' => !$https || $sessionSecure,
                'severity' => 'warning',
                'message' => !$https || $sessionSecure
                    ? ($https ? 'Secure-Cookies sind für HTTPS aktiv.' : 'Lokaler HTTP-Betrieb erkannt.')
                    : 'Bei HTTPS muss SESSION_SECURE=true gesetzt werden.',
            ],
            [
                'key' => 'openssl',
                'label' => 'OpenSSL',
                'ok' => extension_loaded('openssl'),
                'severity' => 'critical',
                'message' => extension_loaded('openssl')
                    ? 'Verschlüsselte Geheimnisse werden unterstützt.'
                    : 'Die PHP-Erweiterung OpenSSL fehlt.',
            ],
            [
                'key' => 'zip',
                'label' => 'ZIP-Unterstützung',
                'ok' => class_exists(ZipArchive::class),
                'severity' => 'warning',
                'message' => class_exists(ZipArchive::class)
                    ? 'Update-, Modul- und Backup-ZIPs sind verfügbar.'
                    : 'Aktiviere in XAMPP extension=zip für Updates und Module.',
            ],
        ];
    }

    public function warnings(): array
    {
        return array_values(array_filter($this->checks(), static fn (array $check): bool => !$check['ok']));
    }

    public function log(
        string $category,
        string $event,
        string $severity,
        string $message,
        array $context = [],
        ?int $userId = null,
        ?string $username = null,
    ): void {
        try {
            $this->ensureTables();
            $statement = $this->pdo->prepare(
                'INSERT INTO security_events
                    (category, event_key, severity, message, context, user_id, username, ip_address, user_agent)
                 VALUES (:category, :event_key, :severity, :message, :context, :user_id, :username, :ip, :agent)'
            );
            $statement->execute([
                'category' => mb_substr($category, 0, 50),
                'event_key' => mb_substr($event, 0, 100),
                'severity' => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
                'message' => mb_substr($message, 0, 1000),
                'context' => $context !== []
                    ? json_encode($this->mask($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : null,
                'user_id' => $userId,
                'username' => $username !== null ? mb_substr($username, 0, 100) : null,
                'ip' => $this->requestIp(),
                'agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (Throwable) {
            // Sicherheitsprotokollierung darf die eigentliche Schutzentscheidung nicht aufheben.
        }
    }

    public function events(int $limit = 200): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, $limit));
        return $this->pdo->query(
            'SELECT se.*, u.display_name AS user_name
             FROM security_events se LEFT JOIN users u ON u.id = se.user_id
             ORDER BY se.created_at DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function isIpBlocked(?string $ip = null): bool
    {
        try {
            $this->ensureTables();
            $ip ??= $this->requestIp();
            if ($ip === '') {
                return false;
            }
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM ip_blocks
                 WHERE ip_address = :ip AND active = 1
                 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())'
            );
            $statement->execute(['ip' => $ip]);
            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function blockIp(
        string $ip,
        string $reason,
        ?int $expiresInMinutes,
        ?int $createdBy,
        string $source = 'manual',
    ): void {
        $this->ensureTables();
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Die IP-Adresse ist ungültig.');
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if ($reason === '') {
            throw new InvalidArgumentException('Für die Sperre wird eine Begründung benötigt.');
        }
        $expiresAt = null;
        if ($expiresInMinutes !== null) {
            $minutes = max(1, min(525_600, $expiresInMinutes));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + ($minutes * 60));
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO ip_blocks (ip_address, reason, source, expires_at, active, created_by)
             VALUES (:ip, :reason, :source, :expires_at, 1, :created_by)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), source = VALUES(source),
                expires_at = VALUES(expires_at), active = 1, created_by = VALUES(created_by),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'ip' => $ip,
            'reason' => $reason,
            'source' => in_array($source, ['manual', 'automatic'], true) ? $source : 'manual',
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);
    }

    public function unblockIp(int $blockId): void
    {
        $this->ensureTables();
        $statement = $this->pdo->prepare(
            'UPDATE ip_blocks SET active = 0, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute(['id' => $blockId]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Die IP-Sperre wurde nicht gefunden oder ist bereits inaktiv.');
        }
    }

    public function blocks(): array
    {
        $this->ensureTables();
        $this->pdo->exec(
            'UPDATE ip_blocks SET active = 0, updated_at = UTC_TIMESTAMP()
             WHERE active = 1 AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()'
        );
        return $this->pdo->query(
            'SELECT ib.*, u.display_name AS created_by_name
             FROM ip_blocks ib LEFT JOIN users u ON u.id = ib.created_by
             ORDER BY ib.active DESC, ib.created_at DESC LIMIT 250'
        )->fetchAll();
    }

    public function requestIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private function ensureTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS security_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(50) NOT NULL,
                event_key VARCHAR(100) NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT \'info\',
                message VARCHAR(1000) NOT NULL,
                context JSON NULL,
                user_id BIGINT UNSIGNED NULL,
                username VARCHAR(100) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                resolved_at DATETIME NULL,
                resolved_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_security_events_created (created_at),
                INDEX idx_security_events_severity (severity, created_at),
                CONSTRAINT fk_security_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_security_event_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ip_blocks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL UNIQUE,
                reason VARCHAR(500) NOT NULL,
                source VARCHAR(20) NOT NULL DEFAULT \'manual\',
                expires_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ip_blocks_active (active, expires_at),
                CONSTRAINT fk_ip_block_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function mask(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            if (preg_match('/token|secret|password|authorization|cookie|app_key/', $normalized)) {
                $masked[$key] = '[MASKIERT]';
            } elseif (is_array($value)) {
                $masked[$key] = $this->mask($value);
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}

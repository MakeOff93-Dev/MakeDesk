<?php

declare(strict_types=1);

final class TwoFactorManager
{
    private const CHALLENGE_TTL = 600;
    private const SETUP_TTL = 900;
    private const MAX_CHALLENGE_ATTEMPTS = 5;
    private const RECOVERY_CODE_COUNT = 10;
    private const RECOVERY_CODE_LENGTH = 16;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private ?bool $readyCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Crypto $crypto,
        private readonly ?SecurityManager $security = null,
    ) {
    }

    public function ready(): bool
    {
        if ($this->readyCache !== null) {
            return $this->readyCache;
        }

        try {
            $statement = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name IN ('user_two_factor', 'user_two_factor_recovery_codes')"
            );
            return $this->readyCache = (int) $statement->fetchColumn() === 2;
        } catch (Throwable) {
            return $this->readyCache = false;
        }
    }

    public function isEnabled(int $userId): bool
    {
        if ($userId < 1 || !$this->ready()) {
            return false;
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM user_two_factor
                 WHERE user_id = :user_id AND enabled_at IS NOT NULL'
            );
            $statement->execute(['user_id' => $userId]);
            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function status(int $userId): array
    {
        $status = [
            'ready' => $this->ready(),
            'enabled' => false,
            'enabled_at' => null,
            'last_used_at' => null,
            'recovery_codes_remaining' => 0,
        ];
        if ($userId < 1 || !$status['ready']) {
            return $status;
        }

        $statement = $this->pdo->prepare(
            'SELECT enabled_at, last_used_at
             FROM user_two_factor WHERE user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();
        if (!is_array($row) || $row['enabled_at'] === null) {
            return $status;
        }

        $recovery = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_two_factor_recovery_codes
             WHERE user_id = :user_id AND used_at IS NULL'
        );
        $recovery->execute(['user_id' => $userId]);

        return [
            'ready' => true,
            'enabled' => true,
            'enabled_at' => $row['enabled_at'],
            'last_used_at' => $row['last_used_at'],
            'recovery_codes_remaining' => (int) $recovery->fetchColumn(),
        ];
    }

    public function startSetup(int $userId): void
    {
        $this->assertReady();
        if ($userId < 1) {
            throw new InvalidArgumentException('Das Benutzerkonto ist ungültig.');
        }
        if ($this->isEnabled($userId)) {
            throw new RuntimeException('Die Zwei-Faktor-Authentifizierung ist bereits aktiv.');
        }

        $secret = $this->base32Encode(random_bytes(20));
        $_SESSION['two_factor_setup'] = [
            'user_id' => $userId,
            'secret' => $this->crypto->encrypt($secret),
            'created_at' => time(),
        ];
    }

    public function pendingSetup(int $userId, string $issuer, string $account): ?array
    {
        $setup = $_SESSION['two_factor_setup'] ?? null;
        if (
            !is_array($setup)
            || (int) ($setup['user_id'] ?? 0) !== $userId
            || time() - (int) ($setup['created_at'] ?? 0) > self::SETUP_TTL
        ) {
            unset($_SESSION['two_factor_setup']);
            return null;
        }

        try {
            $secret = $this->crypto->decrypt((string) ($setup['secret'] ?? ''));
        } catch (Throwable) {
            unset($_SESSION['two_factor_setup']);
            return null;
        }
        if (!preg_match('/^[A-Z2-7]{32}$/', $secret)) {
            unset($_SESSION['two_factor_setup']);
            return null;
        }

        $issuer = trim(mb_substr($issuer, 0, 80)) ?: 'ModDesk';
        $account = trim(mb_substr($account, 0, 120)) ?: 'Benutzer';
        $label = rawurlencode($issuer . ':' . $account);
        $uri = 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';

        return [
            'secret' => $secret,
            'formatted_secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => $uri,
            'expires_at' => (int) $setup['created_at'] + self::SETUP_TTL,
        ];
    }

    public function cancelSetup(int $userId): void
    {
        $setup = $_SESSION['two_factor_setup'] ?? null;
        if (is_array($setup) && (int) ($setup['user_id'] ?? 0) === $userId) {
            unset($_SESSION['two_factor_setup']);
        }
    }

    public function confirmSetup(int $userId, string $code): array
    {
        $setup = $this->pendingSetup($userId, 'ModDesk', 'Benutzer');
        if ($setup === null) {
            throw new RuntimeException('Die Zwei-Faktor-Einrichtung ist abgelaufen. Bitte starte sie erneut.');
        }

        $counter = $this->matchingCounter($setup['secret'], $code, null);
        if ($counter === null) {
            throw new InvalidArgumentException('Der Authenticator-Code ist ungültig oder bereits abgelaufen.');
        }

        $codes = $this->generateRecoveryCodes();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO user_two_factor
                    (user_id, secret_encrypted, enabled_at, last_used_counter, last_used_at)
                 VALUES (:user_id, :secret, UTC_TIMESTAMP(), :counter, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE secret_encrypted = VALUES(secret_encrypted),
                    enabled_at = VALUES(enabled_at), last_used_counter = VALUES(last_used_counter),
                    last_used_at = VALUES(last_used_at), updated_at = UTC_TIMESTAMP()'
            );
            $statement->execute([
                'user_id' => $userId,
                'secret' => $this->crypto->encrypt($setup['secret']),
                'counter' => $counter,
            ]);
            $this->replaceRecoveryCodes($userId, $codes);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        unset($_SESSION['two_factor_setup']);
        $this->storeRecoveryCodesForDisplay($userId, $codes);
        $this->security?->log(
            'authentication',
            'auth.two_factor_enabled',
            'warning',
            'Die Zwei-Faktor-Authentifizierung wurde für ein Benutzerkonto aktiviert.',
            [],
            $userId,
        );
        return $codes;
    }

    public function disable(int $userId, string $code): void
    {
        $this->assertReady();
        if (!$this->isEnabled($userId)) {
            throw new RuntimeException('Die Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }
        if (!$this->verifyForUser($userId, $code)) {
            throw new InvalidArgumentException('Authenticator- oder Wiederherstellungscode ist ungültig.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM user_two_factor_recovery_codes WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
            $this->pdo->prepare('DELETE FROM user_two_factor WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        unset($_SESSION['two_factor_setup'], $_SESSION['two_factor_recovery_display']);
        $this->security?->log(
            'authentication',
            'auth.two_factor_disabled',
            'warning',
            'Die Zwei-Faktor-Authentifizierung wurde für ein Benutzerkonto deaktiviert.',
            [],
            $userId,
        );
    }

    public function regenerateRecoveryCodes(int $userId, string $code): array
    {
        $this->assertReady();
        if (!$this->isEnabled($userId)) {
            throw new RuntimeException('Die Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }
        if (!$this->verifyForUser($userId, $code)) {
            throw new InvalidArgumentException('Authenticator- oder Wiederherstellungscode ist ungültig.');
        }

        $codes = $this->generateRecoveryCodes();
        $this->pdo->beginTransaction();
        try {
            $this->replaceRecoveryCodes($userId, $codes);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        $this->storeRecoveryCodesForDisplay($userId, $codes);
        $this->security?->log(
            'authentication',
            'auth.two_factor_recovery_regenerated',
            'warning',
            'Neue Zwei-Faktor-Wiederherstellungscodes wurden erstellt.',
            [],
            $userId,
        );
        return $codes;
    }

    public function consumeRecoveryCodes(int $userId): array
    {
        $payload = $_SESSION['two_factor_recovery_display'] ?? null;
        unset($_SESSION['two_factor_recovery_display']);
        if (
            !is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || time() - (int) ($payload['created_at'] ?? 0) > self::SETUP_TTL
        ) {
            return [];
        }

        try {
            $json = $this->crypto->decrypt((string) ($payload['codes'] ?? ''));
            $codes = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
            return is_array($codes)
                ? array_values(array_filter(array_map('strval', $codes), static fn (string $code): bool => $code !== ''))
                : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function beginLoginChallenge(int $userId, string $source): void
    {
        if (!$this->isEnabled($userId)) {
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION['auth_user_id']);
        $_SESSION['two_factor_challenge'] = [
            'user_id' => $userId,
            'source' => mb_substr($source, 0, 40),
            'created_at' => time(),
            'attempts' => 0,
        ];
        $this->security?->log(
            'authentication',
            'auth.two_factor_required',
            'info',
            'Der erste Anmeldefaktor wurde bestätigt; der zweite Faktor steht noch aus.',
            ['source' => mb_substr($source, 0, 40)],
            $userId,
        );
    }

    public function pendingLoginChallenge(): ?array
    {
        $challenge = $_SESSION['two_factor_challenge'] ?? null;
        if (
            !is_array($challenge)
            || (int) ($challenge['user_id'] ?? 0) < 1
            || time() - (int) ($challenge['created_at'] ?? 0) > self::CHALLENGE_TTL
            || (int) ($challenge['attempts'] ?? 0) >= self::MAX_CHALLENGE_ATTEMPTS
        ) {
            unset($_SESSION['two_factor_challenge']);
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name FROM users
             WHERE id = :id AND active = 1 LIMIT 1'
        );
        $statement->execute(['id' => (int) $challenge['user_id']]);
        $user = $statement->fetch();
        if (!is_array($user) || !$this->isEnabled((int) $user['id'])) {
            unset($_SESSION['two_factor_challenge']);
            return null;
        }

        return [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
            'source' => (string) ($challenge['source'] ?? 'login'),
            'attempts_remaining' => self::MAX_CHALLENGE_ATTEMPTS - (int) ($challenge['attempts'] ?? 0),
            'expires_at' => (int) $challenge['created_at'] + self::CHALLENGE_TTL,
        ];
    }

    public function verifyPendingLogin(string $code): ?array
    {
        $pending = $this->pendingLoginChallenge();
        if ($pending === null) {
            return null;
        }

        if (!$this->verifyForUser((int) $pending['user_id'], $code)) {
            $_SESSION['two_factor_challenge']['attempts'] =
                (int) ($_SESSION['two_factor_challenge']['attempts'] ?? 0) + 1;
            $remaining = self::MAX_CHALLENGE_ATTEMPTS
                - (int) $_SESSION['two_factor_challenge']['attempts'];
            $this->security?->log(
                'authentication',
                'auth.two_factor_failed',
                $remaining <= 0 ? 'warning' : 'info',
                'Ein Zwei-Faktor-Code wurde abgewiesen.',
                ['attempts_remaining' => max(0, $remaining)],
                (int) $pending['user_id'],
                (string) $pending['username'],
            );
            if ($remaining <= 0) {
                unset($_SESSION['two_factor_challenge']);
            }
            return null;
        }

        unset($_SESSION['two_factor_challenge']);
        return $pending;
    }

    public function cancelLoginChallenge(): void
    {
        unset($_SESSION['two_factor_challenge']);
        session_regenerate_id(true);
    }

    private function verifyForUser(int $userId, string $code): bool
    {
        $normalizedRecovery = $this->normalizeRecoveryCode($code);
        if (strlen($normalizedRecovery) === self::RECOVERY_CODE_LENGTH) {
            $statement = $this->pdo->prepare(
                'SELECT id, code_hash FROM user_two_factor_recovery_codes
                 WHERE user_id = :user_id AND used_at IS NULL ORDER BY id'
            );
            $statement->execute(['user_id' => $userId]);
            foreach ($statement->fetchAll() as $recovery) {
                if (!password_verify($normalizedRecovery, (string) $recovery['code_hash'])) {
                    continue;
                }
                $consume = $this->pdo->prepare(
                    'UPDATE user_two_factor_recovery_codes
                     SET used_at = UTC_TIMESTAMP()
                     WHERE id = :id AND user_id = :user_id AND used_at IS NULL'
                );
                $consume->execute(['id' => $recovery['id'], 'user_id' => $userId]);
                if ($consume->rowCount() === 1) {
                    $this->pdo->prepare(
                        'UPDATE user_two_factor SET last_used_at = UTC_TIMESTAMP() WHERE user_id = :user_id'
                    )->execute(['user_id' => $userId]);
                    return true;
                }
            }
        }

        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($digits) !== 6) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT secret_encrypted, last_used_counter
             FROM user_two_factor
             WHERE user_id = :user_id AND enabled_at IS NOT NULL LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $factor = $statement->fetch();
        if (!is_array($factor)) {
            return false;
        }

        try {
            $secret = $this->crypto->decrypt((string) $factor['secret_encrypted']);
        } catch (Throwable) {
            return false;
        }
        $lastCounter = $factor['last_used_counter'] !== null ? (int) $factor['last_used_counter'] : null;
        $counter = $this->matchingCounter($secret, $digits, $lastCounter);
        if ($counter === null) {
            return false;
        }

        $update = $this->pdo->prepare(
            'UPDATE user_two_factor
             SET last_used_counter = :counter, last_used_at = UTC_TIMESTAMP()
             WHERE user_id = :user_id
             AND (last_used_counter IS NULL OR last_used_counter < :counter_check)'
        );
        $update->execute([
            'counter' => $counter,
            'user_id' => $userId,
            'counter_check' => $counter,
        ]);
        return $update->rowCount() === 1;
    }

    private function matchingCounter(string $secret, string $code, ?int $minimumExclusive): ?int
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($digits) !== 6) {
            return null;
        }

        $current = intdiv(time(), 30);
        foreach ([$current, $current - 1, $current + 1] as $counter) {
            if ($counter < 0 || ($minimumExclusive !== null && $counter <= $minimumExclusive)) {
                continue;
            }
            if (hash_equals($this->totp($secret, $counter), $digits)) {
                return $counter;
            }
        }
        return null;
    }

    private function totp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return '';
        }

        $high = intdiv($counter, 4_294_967_296);
        $low = $counter % 4_294_967_296;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );
        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
        if ($encoded === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($encoded) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }
        return $binary;
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        while (count($codes) < self::RECOVERY_CODE_COUNT) {
            $raw = '';
            for ($index = 0; $index < self::RECOVERY_CODE_LENGTH; $index++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $codes[$raw] = implode('-', str_split($raw, 4));
        }
        return array_values($codes);
    }

    private function replaceRecoveryCodes(int $userId, array $codes): void
    {
        $this->pdo->prepare('DELETE FROM user_two_factor_recovery_codes WHERE user_id = :user_id')
            ->execute(['user_id' => $userId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO user_two_factor_recovery_codes (user_id, code_hash)
             VALUES (:user_id, :code_hash)'
        );
        foreach ($codes as $code) {
            $hash = password_hash($this->normalizeRecoveryCode((string) $code), PASSWORD_DEFAULT);
            if (!is_string($hash) || $hash === '') {
                throw new RuntimeException('Wiederherstellungscodes konnten nicht sicher gespeichert werden.');
            }
            $insert->execute(['user_id' => $userId, 'code_hash' => $hash]);
        }
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    private function storeRecoveryCodesForDisplay(int $userId, array $codes): void
    {
        $_SESSION['two_factor_recovery_display'] = [
            'user_id' => $userId,
            'codes' => $this->crypto->encrypt(
                json_encode(array_values($codes), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            ),
            'created_at' => time(),
        ];
    }

    private function assertReady(): void
    {
        if (!$this->ready()) {
            throw new RuntimeException(
                'Bitte führe zuerst die aktuelle Zwei-Faktor- und Designmigration im Panel aus.'
            );
        }
    }
}

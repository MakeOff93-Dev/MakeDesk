<?php

declare(strict_types=1);

final class TwitchLogin
{
    private const AUTHORIZE_URL = 'https://id.twitch.tv/oauth2/authorize';
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';
    private const REVOKE_URL = 'https://id.twitch.tv/oauth2/revoke';
    private const USERS_URL = 'https://api.twitch.tv/helix/users';
    private ?bool $readyCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
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
                 WHERE table_schema = DATABASE() AND table_name = 'user_oauth_accounts'"
            );
            return $this->readyCache = (int) $statement->fetchColumn() === 1;
        } catch (Throwable) {
            return $this->readyCache = false;
        }
    }

    public function enabled(): bool
    {
        return $this->settings->bool('twitch_login_enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->enabled()
            && $this->ready()
            && $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->redirectUri() !== '';
    }

    public function redirectUri(): string
    {
        return trim((string) $this->settings->get('twitch_login_redirect_uri', ''));
    }

    public function authorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Der Twitch-Login ist noch nicht vollständig eingerichtet.');
        }
        if (!preg_match('/^[a-f0-9]{48,128}$/', $state)) {
            throw new InvalidArgumentException('Der OAuth-Status ist ungültig.');
        }

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'user:read:email',
            'state' => $state,
            'force_verify' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Der Twitch-Login ist noch nicht vollständig eingerichtet.');
        }
        $code = trim($code);
        if ($code === '' || strlen($code) > 512) {
            throw new InvalidArgumentException('Twitch hat keinen gültigen Autorisierungscode geliefert.');
        }

        [$tokenStatus, $tokenBody] = $this->request(
            'POST',
            self::TOKEN_URL,
            [],
            [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
            ],
        );
        $accessToken = trim((string) ($tokenBody['access_token'] ?? ''));
        if ($tokenStatus < 200 || $tokenStatus >= 300 || $accessToken === '') {
            throw new RuntimeException($this->apiError($tokenBody, 'Der Twitch-Login konnte nicht bestätigt werden.'));
        }

        try {
            [$profileStatus, $profileBody] = $this->request(
                'GET',
                self::USERS_URL,
                [
                    'Client-Id: ' . $this->clientId(),
                    'Authorization: Bearer ' . $accessToken,
                ],
            );
        } finally {
            $this->revoke($accessToken);
        }
        $profile = $profileBody['data'][0] ?? null;
        if ($profileStatus < 200 || $profileStatus >= 300 || !is_array($profile) || empty($profile['id'])) {
            throw new RuntimeException($this->apiError($profileBody, 'Twitch hat kein Benutzerprofil geliefert.'));
        }

        $avatarUrl = trim((string) ($profile['profile_image_url'] ?? ''));
        if ($avatarUrl !== '' && (!filter_var($avatarUrl, FILTER_VALIDATE_URL) || parse_url($avatarUrl, PHP_URL_SCHEME) !== 'https')) {
            $avatarUrl = '';
        }

        return [
            'id' => mb_substr((string) $profile['id'], 0, 80),
            'login' => mb_substr((string) ($profile['login'] ?? ''), 0, 100),
            'display_name' => mb_substr((string) ($profile['display_name'] ?? $profile['login'] ?? ''), 0, 190),
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            'email' => filter_var((string) ($profile['email'] ?? ''), FILTER_VALIDATE_EMAIL)
                ? mb_substr((string) $profile['email'], 0, 190)
                : null,
        ];
    }

    public function link(int $userId, array $profile): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die aktuelle Profil- und Login-Migration aus.');
        }
        $providerId = trim((string) ($profile['id'] ?? ''));
        if ($providerId === '') {
            throw new InvalidArgumentException('Das Twitch-Profil ist unvollständig.');
        }

        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare(
                "SELECT user_id FROM user_oauth_accounts
                 WHERE provider = 'twitch' AND provider_user_id = :provider_user_id
                 LIMIT 1 FOR UPDATE"
            );
            $existing->execute(['provider_user_id' => $providerId]);
            $linkedUserId = $existing->fetchColumn();
            if ($linkedUserId !== false && (int) $linkedUserId !== $userId) {
                throw new RuntimeException('Dieses Twitch-Konto ist bereits mit einem anderen ModDesk-Zugang verbunden.');
            }

            $statement = $this->pdo->prepare(
                "INSERT INTO user_oauth_accounts
                    (user_id, provider, provider_user_id, provider_username, provider_display_name,
                     provider_avatar_url, provider_email)
                 VALUES
                    (:user_id, 'twitch', :provider_user_id, :provider_username, :provider_display_name,
                     :provider_avatar_url, :provider_email)
                 ON DUPLICATE KEY UPDATE provider_user_id = VALUES(provider_user_id),
                    provider_username = VALUES(provider_username),
                    provider_display_name = VALUES(provider_display_name),
                    provider_avatar_url = VALUES(provider_avatar_url),
                    provider_email = VALUES(provider_email),
                    updated_at = UTC_TIMESTAMP()"
            );
            $statement->execute([
                'user_id' => $userId,
                'provider_user_id' => $providerId,
                'provider_username' => (string) ($profile['login'] ?? ''),
                'provider_display_name' => (string) ($profile['display_name'] ?? ''),
                'provider_avatar_url' => $profile['avatar_url'] ?? null,
                'provider_email' => $profile['email'] ?? null,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function unlink(int $userId): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die aktuelle Profil- und Login-Migration aus.');
        }
        $statement = $this->pdo->prepare(
            "DELETE FROM user_oauth_accounts WHERE user_id = :user_id AND provider = 'twitch'"
        );
        $statement->execute(['user_id' => $userId]);
    }

    public function accountForUser(int $userId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $statement = $this->pdo->prepare(
            "SELECT id, provider_user_id, provider_username, provider_display_name,
                    provider_avatar_url, provider_email, linked_at, last_login_at, updated_at
             FROM user_oauth_accounts
             WHERE user_id = :user_id AND provider = 'twitch' LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetch() ?: null;
    }

    public function linkedUser(string $providerUserId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $statement = $this->pdo->prepare(
            "SELECT u.id, u.username, u.display_name, u.email, u.role, u.active, u.last_login_at, u.created_at
             FROM user_oauth_accounts oa
             JOIN users u ON u.id = oa.user_id
             WHERE oa.provider = 'twitch' AND oa.provider_user_id = :provider_user_id
               AND u.active = 1
             LIMIT 1"
        );
        $statement->execute(['provider_user_id' => $providerUserId]);
        return $statement->fetch() ?: null;
    }

    public function recordLogin(int $userId, array $profile): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_oauth_accounts
             SET provider_username = :provider_username,
                 provider_display_name = :provider_display_name,
                 provider_avatar_url = :provider_avatar_url,
                 provider_email = :provider_email,
                 last_login_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE user_id = :user_id AND provider = 'twitch'"
        );
        $statement->execute([
            'provider_username' => (string) ($profile['login'] ?? ''),
            'provider_display_name' => (string) ($profile['display_name'] ?? ''),
            'provider_avatar_url' => $profile['avatar_url'] ?? null,
            'provider_email' => $profile['email'] ?? null,
            'user_id' => $userId,
        ]);
    }

    private function clientId(): string
    {
        return trim((string) $this->settings->get('twitch_client_id', Env::get('TWITCH_CLIENT_ID', '')));
    }

    private function clientSecret(): string
    {
        return trim((string) $this->settings->get('twitch_client_secret', Env::get('TWITCH_CLIENT_SECRET', '')));
    }

    private function revoke(string $accessToken): void
    {
        try {
            $this->request('POST', self::REVOKE_URL, [], [
                'client_id' => $this->clientId(),
                'token' => $accessToken,
            ]);
        } catch (Throwable) {
            // Der Profilabruf bleibt maßgeblich; der kurzlebige Token wird niemals gespeichert.
        }
    }

    private function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $form = null,
    ): array {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Die Twitch-Verbindung konnte nicht gestartet werden.');
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];
        if ($form !== null) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($raw)) {
            throw new RuntimeException('Twitch ist derzeit nicht erreichbar' . ($error !== '' ? ': ' . $error : '.'));
        }
        if ($raw === '') {
            return [$status, []];
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Twitch hat eine unlesbare Antwort gesendet.');
        }
        return [$status, is_array($decoded) ? $decoded : []];
    }

    private function apiError(array $body, string $fallback): string
    {
        $message = trim((string) ($body['message'] ?? $body['error_description'] ?? $body['error'] ?? ''));
        return $message !== '' ? mb_substr($message, 0, 500) : $fallback;
    }
}

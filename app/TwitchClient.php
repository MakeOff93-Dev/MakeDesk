<?php

declare(strict_types=1);

final class TwitchApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly array $response = [])
    {
        parent::__construct($message, $status);
    }
}

final class TwitchClient
{
    private const API_BASE = 'https://api.twitch.tv/helix';
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';
    private const VALIDATE_URL = 'https://id.twitch.tv/oauth2/validate';

    public function __construct(
        private readonly PDO $pdo,
        private readonly Crypto $crypto,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public static function requiredScopes(string $mode = 'owner'): array
    {
        $scopes = [
            'user:read:moderated_channels',
            'moderator:manage:banned_users',
            'moderator:manage:warnings',
            'moderator:manage:shield_mode',
            'moderator:manage:chat_messages',
            'moderator:manage:blocked_terms',
        ];
        if ($mode === 'owner') {
            array_unshift($scopes, 'channel:manage:moderators');
        }
        return $scopes;
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUri !== '';
    }

    public function oauthUrl(string $state, string $mode = 'owner'): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Twitch Client-ID, Client-Secret oder Redirect-URI fehlen in der .env-Datei.');
        }

        return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', self::requiredScopes($mode === 'moderator' ? 'moderator' : 'owner')),
            'state' => $state,
            'force_verify' => 'true',
        ]);
    }

    public function exchangeAuthorizationCode(string $code, int $connectedBy): array
    {
        [$status, $tokens] = $this->http('POST', self::TOKEN_URL, [], [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ], [], false);

        if ($status >= 400 || empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
            throw new TwitchApiException($this->errorMessage($tokens, 'OAuth-Code konnte nicht eingelöst werden.'), $status, $tokens);
        }

        $profileResponse = $this->apiWithToken('GET', '/users', (string) $tokens['access_token']);
        $profile = $profileResponse['data'][0] ?? null;
        if (!is_array($profile)) {
            throw new TwitchApiException('Twitch hat kein Profil für das verbundene Konto geliefert.');
        }

        $expiresAt = gmdate('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 0));
        $scopes = array_values(array_filter((array) ($tokens['scope'] ?? []), 'is_string'));
        $statement = $this->pdo->prepare(
            'INSERT INTO twitch_connections
                (twitch_user_id, login, display_name, profile_image_url, access_token, refresh_token, scopes, expires_at, last_validated_at, connected_by)
             VALUES
                (:twitch_user_id, :login, :display_name, :profile_image_url, :access_token, :refresh_token, :scopes, :expires_at, UTC_TIMESTAMP(), :connected_by)
             ON DUPLICATE KEY UPDATE login = VALUES(login), display_name = VALUES(display_name),
                profile_image_url = VALUES(profile_image_url), access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token), scopes = VALUES(scopes), expires_at = VALUES(expires_at),
                last_validated_at = UTC_TIMESTAMP(), connected_by = VALUES(connected_by), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'twitch_user_id' => (string) $profile['id'],
            'login' => (string) $profile['login'],
            'display_name' => (string) $profile['display_name'],
            'profile_image_url' => (string) ($profile['profile_image_url'] ?? ''),
            'access_token' => $this->crypto->encrypt((string) $tokens['access_token']),
            'refresh_token' => $this->crypto->encrypt((string) $tokens['refresh_token']),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'connected_by' => $connectedBy,
        ]);
        $removeOld = $this->pdo->prepare('DELETE FROM twitch_connections WHERE twitch_user_id <> :twitch_user_id');
        $removeOld->execute(['twitch_user_id' => (string) $profile['id']]);

        $this->cacheUser($profile);
        $this->setSettingIfMissing('twitch_channel_id', (string) $profile['id'], $connectedBy);
        $this->setSettingIfMissing('twitch_channel_login', (string) $profile['login'], $connectedBy);
        $this->setSettingIfMissing('twitch_channel_display_name', (string) $profile['display_name'], $connectedBy);

        return $this->connection() ?? [];
    }

    public function connection(): ?array
    {
        $statement = $this->pdo->query(
            'SELECT id, twitch_user_id, login, display_name, profile_image_url, scopes,
                    expires_at, last_validated_at, connected_by, created_at, updated_at
             FROM twitch_connections ORDER BY id DESC LIMIT 1'
        );
        $connection = $statement->fetch();
        if (!$connection) {
            return null;
        }
        $connection['scopes'] = json_decode((string) $connection['scopes'], true) ?: [];
        return $connection;
    }

    public function hasScope(string $scope): bool
    {
        $connection = $this->connection();
        return $connection !== null && in_array($scope, (array) $connection['scopes'], true);
    }

    private function secretConnection(): ?array
    {
        $statement = $this->pdo->query('SELECT * FROM twitch_connections ORDER BY id DESC LIMIT 1');
        return $statement->fetch() ?: null;
    }

    public function channel(): ?array
    {
        $statement = $this->pdo->query(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('twitch_channel_id', 'twitch_channel_login', 'twitch_channel_display_name')"
        );
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            $values[(string) $row['setting_key']] = $row['setting_value'];
        }

        if (empty($values['twitch_channel_id'])) {
            $connection = $this->connection();
            if ($connection === null) {
                return null;
            }
            return [
                'id' => $connection['twitch_user_id'],
                'login' => $connection['login'],
                'display_name' => $connection['display_name'],
            ];
        }

        return [
            'id' => (string) $values['twitch_channel_id'],
            'login' => (string) ($values['twitch_channel_login'] ?? ''),
            'display_name' => (string) ($values['twitch_channel_display_name'] ?? $values['twitch_channel_login'] ?? ''),
        ];
    }

    public function availableChannels(): array
    {
        $channels = [];
        $current = $this->channel();
        if ($current !== null && !empty($current['id'])) {
            $channels[(string) $current['id']] = $current;
        }
        $connection = $this->connection();
        if ($connection !== null) {
            $channels[(string) $connection['twitch_user_id']] = [
                'id' => (string) $connection['twitch_user_id'],
                'login' => (string) $connection['login'],
                'display_name' => (string) $connection['display_name'],
            ];
        }
        try {
            $statement = $this->pdo->query(
                'SELECT tu.twitch_user_id AS id, tu.login, tu.display_name
                 FROM ban_sync_channels bsc
                 JOIN twitch_users tu ON tu.twitch_user_id = bsc.twitch_user_id
                 WHERE bsc.enabled = 1 ORDER BY tu.display_name'
            );
            foreach ($statement->fetchAll() as $row) {
                $channels[(string) $row['id']] = [
                    'id' => (string) $row['id'],
                    'login' => (string) $row['login'],
                    'display_name' => (string) $row['display_name'],
                ];
            }
        } catch (Throwable) {
            // Ohne BanSync-Tabelle bleiben der aktuelle und der verbundene Kanal verfügbar.
        }
        uasort($channels, static fn (array $left, array $right): int => strcasecmp((string) $left['display_name'], (string) $right['display_name']));
        return array_values($channels);
    }

    public function selectAvailableChannel(string $channelId, int $updatedBy): array
    {
        foreach ($this->availableChannels() as $channel) {
            if (hash_equals((string) $channel['id'], trim($channelId))) {
                $this->setSetting('twitch_channel_id', (string) $channel['id'], $updatedBy);
                $this->setSetting('twitch_channel_login', (string) $channel['login'], $updatedBy);
                $this->setSetting('twitch_channel_display_name', (string) $channel['display_name'], $updatedBy);
                return $channel;
            }
        }
        throw new TwitchApiException('Dieser Kanal ist nicht als verbundener oder freigegebener ModDesk-Kanal verfügbar.');
    }

    public function setChannelByLogin(string $login, int $updatedBy): array
    {
        $user = $this->findUser($login);
        if ($user === null) {
            throw new TwitchApiException('Der Twitch-Kanal wurde nicht gefunden.');
        }

        $this->setSetting('twitch_channel_id', (string) $user['id'], $updatedBy);
        $this->setSetting('twitch_channel_login', (string) $user['login'], $updatedBy);
        $this->setSetting('twitch_channel_display_name', (string) $user['display_name'], $updatedBy);
        return $user;
    }

    public function validateConnection(): array
    {
        $connection = $this->secretConnection();
        if ($connection === null) {
            throw new TwitchApiException('Noch kein Twitch-Konto verbunden.');
        }

        $token = $this->crypto->decrypt((string) $connection['access_token']);
        [$status, $validation] = $this->http('GET', self::VALIDATE_URL, [], null, [
            'Authorization: Bearer ' . $token,
        ]);

        if ($status === 401) {
            $connection = $this->refreshConnection($connection);
            $token = $this->crypto->decrypt((string) $connection['access_token']);
            [$status, $validation] = $this->http('GET', self::VALIDATE_URL, [], null, [
                'Authorization: Bearer ' . $token,
            ]);
        }

        if ($status >= 400) {
            throw new TwitchApiException($this->errorMessage($validation, 'Twitch-Token ist ungültig.'), $status, $validation);
        }

        if (($validation['client_id'] ?? null) !== $this->clientId) {
            throw new TwitchApiException('Das Token gehört nicht zur eingetragenen Twitch Client-ID.');
        }

        $expiresAt = gmdate('Y-m-d H:i:s', time() + (int) ($validation['expires_in'] ?? 0));
        $statement = $this->pdo->prepare(
            'UPDATE twitch_connections SET scopes = :scopes, expires_at = :expires_at,
             last_validated_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute([
            'scopes' => json_encode(array_values((array) ($validation['scopes'] ?? [])), JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'id' => $connection['id'],
        ]);

        return $validation;
    }

    public function findUser(string $login): ?array
    {
        $login = strtolower(ltrim(trim($login), '@'));
        if (!preg_match('/^[a-z0-9_]{1,25}$/', $login)) {
            throw new InvalidArgumentException('Bitte gib einen gültigen Twitch-Login ein.');
        }

        $response = $this->request('GET', '/users', ['login' => $login]);
        $user = $response['data'][0] ?? null;
        if (!is_array($user)) {
            return null;
        }

        $this->cacheUser($user);
        return $user;
    }

    public function syncModerators(): int
    {
        $connection = $this->connection();
        $channel = $this->requireChannel();
        if ($connection === null || (string) $connection['twitch_user_id'] !== (string) $channel['id']) {
            throw new TwitchApiException('Die Moderatorliste kann Twitch nur mit dem verbundenen Konto des Kanalinhabers auslesen.');
        }

        $moderators = [];
        $after = null;
        do {
            $query = ['broadcaster_id' => $channel['id'], 'first' => 100];
            if ($after) {
                $query['after'] = $after;
            }
            $response = $this->request('GET', '/moderation/moderators', $query);
            $moderators = array_merge($moderators, (array) ($response['data'] ?? []));
            $after = $response['pagination']['cursor'] ?? null;
        } while ($after);

        $this->pdo->beginTransaction();
        try {
            $off = $this->pdo->prepare("UPDATE twitch_roles SET active = 0, synced_at = UTC_TIMESTAMP() WHERE channel_id = :channel_id AND role = 'moderator'");
            $off->execute(['channel_id' => $channel['id']]);
            foreach ($moderators as $moderator) {
                $this->cacheBasicUser((string) $moderator['user_id'], (string) $moderator['user_login'], (string) $moderator['user_name']);
                $role = $this->pdo->prepare(
                    "INSERT INTO twitch_roles (channel_id, twitch_user_id, role, active, synced_at)
                     VALUES (:channel_id, :user_id, 'moderator', 1, UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE active = 1, synced_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()"
                );
                $role->execute(['channel_id' => $channel['id'], 'user_id' => $moderator['user_id']]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return count($moderators);
    }

    public function syncBannedUsers(): int
    {
        $channel = $this->requireChannel();
        $bans = [];
        $after = null;
        do {
            $query = ['broadcaster_id' => $channel['id'], 'first' => 100];
            if ($after) {
                $query['after'] = $after;
            }
            $response = $this->request('GET', '/moderation/banned', $query);
            $bans = array_merge($bans, (array) ($response['data'] ?? []));
            $after = $response['pagination']['cursor'] ?? null;
        } while ($after);

        $this->pdo->beginTransaction();
        try {
            $off = $this->pdo->prepare("UPDATE twitch_roles SET active = 0, synced_at = UTC_TIMESTAMP() WHERE channel_id = :channel_id AND role = 'banned'");
            $off->execute(['channel_id' => $channel['id']]);
            foreach ($bans as $ban) {
                $this->cacheBasicUser((string) $ban['user_id'], (string) $ban['user_login'], (string) $ban['user_name']);
                $role = $this->pdo->prepare(
                    "INSERT INTO twitch_roles (channel_id, twitch_user_id, role, active, metadata, synced_at)
                     VALUES (:channel_id, :user_id, 'banned', 1, :metadata, UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE active = 1, metadata = VALUES(metadata), synced_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()"
                );
                $role->execute([
                    'channel_id' => $channel['id'],
                    'user_id' => $ban['user_id'],
                    'metadata' => json_encode($ban, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return count($bans);
    }

    public function cachedRoles(string $role): array
    {
        $channel = $this->channel();
        if ($channel === null) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT tu.*, tr.synced_at, tr.metadata FROM twitch_roles tr
             JOIN twitch_users tu ON tu.twitch_user_id = tr.twitch_user_id
             WHERE tr.channel_id = :channel_id AND tr.role = :role AND tr.active = 1
             ORDER BY tu.display_name'
        );
        $statement->execute(['channel_id' => $channel['id'], 'role' => $role]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['metadata'] = !empty($row['metadata']) ? (json_decode((string) $row['metadata'], true) ?: []) : [];
        }
        unset($row);
        return $rows;
    }

    public function moderatedChannels(): array
    {
        $connection = $this->requireConnection();
        if (!$this->hasScope('user:read:moderated_channels')) {
            throw new TwitchApiException(
                'Für die Rechteprüfung fehlt user:read:moderated_channels. Bitte verbinde das Twitch-Konto erneut.'
            );
        }

        $channels = [];
        $after = null;
        do {
            $query = [
                'user_id' => $connection['twitch_user_id'],
                'first' => 100,
            ];
            if ($after) {
                $query['after'] = $after;
            }
            $response = $this->request('GET', '/moderation/channels', $query);
            $channels = array_merge($channels, (array) ($response['data'] ?? []));
            $after = $response['pagination']['cursor'] ?? null;
        } while ($after);

        return $channels;
    }

    public function canModerateChannel(string $channelId): ?bool
    {
        $connection = $this->requireConnection();
        if ((string) $connection['twitch_user_id'] === $channelId) {
            return true;
        }
        if (!$this->hasScope('user:read:moderated_channels')) {
            return null;
        }

        foreach ($this->moderatedChannels() as $channel) {
            if ((string) ($channel['broadcaster_id'] ?? '') === $channelId) {
                return true;
            }
        }
        return false;
    }

    public function ban(string $userId, string $reason, ?int $duration = null): array
    {
        $channel = $this->requireChannel();
        return $this->banOnChannel((string) $channel['id'], $userId, $reason, $duration);
    }

    public function banOnChannel(string $channelId, string $userId, string $reason, ?int $duration = null): array
    {
        $connection = $this->requireConnection();
        $data = ['user_id' => $userId, 'reason' => mb_substr($reason, 0, 500)];
        if ($duration !== null) {
            $data['duration'] = max(1, min(1209600, $duration));
        }
        return $this->request('POST', '/moderation/bans', [
            'broadcaster_id' => $channelId,
            'moderator_id' => $connection['twitch_user_id'],
        ], ['data' => $data]);
    }

    public function unban(string $userId): array
    {
        $channel = $this->requireChannel();
        return $this->unbanOnChannel((string) $channel['id'], $userId);
    }

    public function unbanOnChannel(string $channelId, string $userId): array
    {
        $connection = $this->requireConnection();
        return $this->request('DELETE', '/moderation/bans', [
            'broadcaster_id' => $channelId,
            'moderator_id' => $connection['twitch_user_id'],
            'user_id' => $userId,
        ]);
    }

    public function warn(string $userId, string $reason): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        return $this->request('POST', '/moderation/warnings', [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
        ], ['data' => ['user_id' => $userId, 'reason' => mb_substr($reason, 0, 500)]]);
    }

    public function setShieldMode(bool $active): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        return $this->request('PUT', '/moderation/shield_mode', [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
        ], ['is_active' => $active]);
    }

    public function getShieldMode(): ?array
    {
        $connection = $this->connection();
        $channel = $this->channel();
        if ($connection === null || $channel === null) {
            return null;
        }
        $response = $this->request('GET', '/moderation/shield_mode', [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
        ]);
        return $response['data'][0] ?? null;
    }

    public function clearChat(?string $messageId = null): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        $query = [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
        ];
        if ($messageId !== null && trim($messageId) !== '') {
            $query['message_id'] = trim($messageId);
        }
        return $this->request('DELETE', '/moderation/chat', $query);
    }

    public function changeModerator(string $userId, bool $add): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        if ((string) $connection['twitch_user_id'] !== (string) $channel['id']) {
            throw new TwitchApiException('Moderatorrollen können nur mit dem verbundenen Konto des Kanalinhabers geändert werden.');
        }
        return $this->request($add ? 'POST' : 'DELETE', '/moderation/moderators', [
            'broadcaster_id' => $channel['id'],
            'user_id' => $userId,
        ]);
    }

    public function blockedTerms(): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        $terms = [];
        $after = null;
        do {
            $query = [
                'broadcaster_id' => $channel['id'],
                'moderator_id' => $connection['twitch_user_id'],
                'first' => 100,
            ];
            if ($after) {
                $query['after'] = $after;
            }
            $response = $this->request('GET', '/moderation/blocked_terms', $query);
            $terms = array_merge($terms, (array) ($response['data'] ?? []));
            $after = $response['pagination']['cursor'] ?? null;
        } while ($after);
        return $terms;
    }

    public function addBlockedTerm(string $text): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        return $this->request('POST', '/moderation/blocked_terms', [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
        ], ['text' => mb_substr(trim($text), 0, 500)]);
    }

    public function removeBlockedTerm(string $id): array
    {
        $connection = $this->requireConnection();
        $channel = $this->requireChannel();
        return $this->request('DELETE', '/moderation/blocked_terms', [
            'broadcaster_id' => $channel['id'],
            'moderator_id' => $connection['twitch_user_id'],
            'id' => $id,
        ]);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $connection = $this->secretConnection();
        if ($connection === null) {
            throw new TwitchApiException('Noch kein Twitch-Konto verbunden.');
        }

        if (strtotime((string) $connection['expires_at']) <= time() + 120) {
            $connection = $this->refreshConnection($connection);
        } elseif (empty($connection['last_validated_at']) || strtotime((string) $connection['last_validated_at']) < time() - 3300) {
            $this->validateConnection();
            $connection = $this->secretConnection() ?? $connection;
        }

        $token = $this->crypto->decrypt((string) $connection['access_token']);
        [$status, $response] = $this->http($method, self::API_BASE . $path, $query, $body, [
            'Authorization: Bearer ' . $token,
            'Client-Id: ' . $this->clientId,
        ], true);

        if ($status === 401) {
            $connection = $this->refreshConnection($connection);
            $token = $this->crypto->decrypt((string) $connection['access_token']);
            [$status, $response] = $this->http($method, self::API_BASE . $path, $query, $body, [
                'Authorization: Bearer ' . $token,
                'Client-Id: ' . $this->clientId,
            ], true);
        }

        if ($status >= 400) {
            throw new TwitchApiException($this->errorMessage($response, 'Twitch-API-Anfrage fehlgeschlagen.'), $status, $response);
        }

        return $response;
    }

    private function apiWithToken(string $method, string $path, string $token): array
    {
        [$status, $response] = $this->http($method, self::API_BASE . $path, [], null, [
            'Authorization: Bearer ' . $token,
            'Client-Id: ' . $this->clientId,
        ]);
        if ($status >= 400) {
            throw new TwitchApiException($this->errorMessage($response, 'Twitch-Profil konnte nicht geladen werden.'), $status, $response);
        }
        return $response;
    }

    private function refreshConnection(array $connection): array
    {
        $refreshToken = $this->crypto->decrypt((string) $connection['refresh_token']);
        [$status, $tokens] = $this->http('POST', self::TOKEN_URL, [], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ], [], false);

        if ($status >= 400 || empty($tokens['access_token'])) {
            throw new TwitchApiException($this->errorMessage($tokens, 'Twitch-Token konnte nicht erneuert werden.'), $status, $tokens);
        }

        $newRefresh = (string) ($tokens['refresh_token'] ?? $refreshToken);
        $storedScopes = json_decode((string) $connection['scopes'], true);
        $scopes = is_array($tokens['scope'] ?? null)
            ? array_values($tokens['scope'])
            : (is_array($storedScopes) ? array_values($storedScopes) : []);
        $statement = $this->pdo->prepare(
            'UPDATE twitch_connections SET access_token = :access_token, refresh_token = :refresh_token,
             scopes = :scopes, expires_at = :expires_at, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute([
            'access_token' => $this->crypto->encrypt((string) $tokens['access_token']),
            'refresh_token' => $this->crypto->encrypt($newRefresh),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 0)),
            'id' => $connection['id'],
        ]);
        return $this->secretConnection() ?? $connection;
    }

    private function http(
        string $method,
        string $url,
        array $query = [],
        ?array $body = null,
        array $headers = [],
        bool $jsonBody = true,
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-Erweiterung cURL fehlt.');
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('HTTP-Anfrage konnte nicht initialisiert werden.');
        }

        $method = strtoupper($method);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];
        if ($body !== null) {
            if ($jsonBody) {
                $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            } else {
                $options[CURLOPT_POSTFIELDS] = http_build_query($body);
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new TwitchApiException('Twitch ist nicht erreichbar: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($raw === '' || $status === 204) {
            return [$status, []];
        }
        $decoded = json_decode($raw, true);
        return [$status, is_array($decoded) ? $decoded : ['message' => 'Ungültige Antwort von Twitch.']];
    }

    private function cacheUser(array $user): void
    {
        $createdAt = !empty($user['created_at']) ? gmdate('Y-m-d H:i:s', strtotime((string) $user['created_at'])) : null;
        $statement = $this->pdo->prepare(
            'INSERT INTO twitch_users
                (twitch_user_id, login, display_name, profile_image_url, description, broadcaster_type, account_created_at, cached_at)
             VALUES (:id, :login, :display_name, :image, :description, :broadcaster_type, :account_created_at, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE login = VALUES(login), display_name = VALUES(display_name),
                profile_image_url = VALUES(profile_image_url), description = VALUES(description),
                broadcaster_type = VALUES(broadcaster_type), account_created_at = VALUES(account_created_at),
                cached_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'id' => (string) $user['id'],
            'login' => (string) $user['login'],
            'display_name' => (string) $user['display_name'],
            'image' => (string) ($user['profile_image_url'] ?? ''),
            'description' => (string) ($user['description'] ?? ''),
            'broadcaster_type' => (string) ($user['broadcaster_type'] ?? ''),
            'account_created_at' => $createdAt,
        ]);
    }

    private function cacheBasicUser(string $id, string $login, string $displayName): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO twitch_users (twitch_user_id, login, display_name, cached_at)
             VALUES (:id, :login, :display_name, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE login = VALUES(login), display_name = VALUES(display_name),
                cached_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute(['id' => $id, 'login' => $login, 'display_name' => $displayName]);
    }

    private function setSettingIfMissing(string $key, string $value, int $updatedBy): void
    {
        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :updated_by)'
        );
        $statement->execute(['key' => $key, 'value' => $value, 'updated_by' => $updatedBy]);
    }

    private function setSetting(string $key, string $value, int $updatedBy): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute(['key' => $key, 'value' => $value, 'updated_by' => $updatedBy]);
    }

    private function requireConnection(): array
    {
        $connection = $this->connection();
        if ($connection === null) {
            throw new TwitchApiException('Noch kein Twitch-Konto verbunden.');
        }
        return $connection;
    }

    private function requireChannel(): array
    {
        $channel = $this->channel();
        if ($channel === null) {
            throw new TwitchApiException('Noch kein Zielkanal ausgewählt.');
        }
        return $channel;
    }

    private function errorMessage(array $response, string $fallback): string
    {
        $message = trim((string) ($response['message'] ?? ''));
        return $message !== '' ? 'Twitch: ' . $message : $fallback;
    }
}

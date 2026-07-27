<?php

declare(strict_types=1);

final class DiscordApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly array $response = [])
    {
        parent::__construct($message, $status);
    }
}

final class DiscordClient
{
    private const API_BASE = 'https://discord.com/api/v10';

    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->settings->hasValue('discord_bot_token');
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool('discord_enabled', false);
    }

    public function sendEvent(string $eventKey, array $message): ?array
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return null;
        }

        $routes = [];
        if ($this->tableExists('discord_channel_routes') && $this->tableExists('discord_channels')) {
            $statement = $this->pdo->prepare(
                'SELECT ds.guild_id, dc.channel_id
                 FROM discord_channel_routes dcr
                 JOIN discord_channels dc ON dc.id = dcr.channel_id
                 JOIN discord_servers ds ON ds.id = dc.server_id
                 WHERE dcr.event_key = :event_key AND dcr.enabled = 1 AND dc.enabled = 1 AND ds.enabled = 1
                 ORDER BY ds.name, dc.name'
            );
            $statement->execute(['event_key' => $eventKey]);
            $routes = $statement->fetchAll();
        } else {
            $statement = $this->pdo->prepare(
                'SELECT guild_id, channel_id FROM discord_notification_routes
                 WHERE event_key = :event_key AND enabled = 1 LIMIT 1'
            );
            $statement->execute(['event_key' => $eventKey]);
            $route = $statement->fetch();
            if ($route) $routes[] = $route;
        }
        if ($routes === []) return null;

        $deliveries = [];
        $errors = [];
        foreach ($routes as $route) {
            if (empty($route['channel_id'])) continue;
            try {
                $deliveries[] = $this->deliverEmbed(
                    $eventKey,
                    (string) $route['channel_id'],
                    $message,
                    trim((string) ($route['guild_id'] ?? '')),
                );
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($deliveries === [] && $errors !== []) {
            throw new DiscordApiException(implode(' | ', array_unique($errors)));
        }
        return ['deliveries' => $deliveries, 'errors' => $errors];
    }

    public function sendTest(string $channelId): array
    {
        return $this->deliverEmbed('test', $channelId, [
            'title' => 'ModDesk-Verbindung erfolgreich',
            'description' => 'Dieser Discord-Channel kann Benachrichtigungen vom Twitch ModDesk empfangen.',
            'color' => 9525247,
            'fields' => [
                ['name' => 'Zeitpunkt', 'value' => gmdate('d.m.Y H:i') . ' UTC', 'inline' => true],
                ['name' => 'Status', 'value' => 'Bot ist einsatzbereit', 'inline' => true],
            ],
        ]);
    }

    public function sendCustomMessage(string $channelId, array $message, string $guildId = ''): array
    {
        $content = mb_substr(trim((string) ($message['content'] ?? '')), 0, 2000);
        $embedInput = $message['embed'] ?? null;
        $payload = ['allowed_mentions' => ['parse' => []]];
        if ($content !== '') {
            $payload['content'] = $content;
        }
        if (is_array($embedInput)) {
            $payload['embeds'] = [$this->normalizeEmbed($embedInput, false)];
        }
        if (!isset($payload['content']) && !isset($payload['embeds'])) {
            throw new DiscordApiException('Die Discord-Nachricht ist leer.');
        }
        return $this->deliverPayload('manual_message', $channelId, $payload, $guildId);
    }

    public function guildChannels(string $guildId): array
    {
        if (!preg_match('/^[0-9]{15,22}$/', $guildId)) {
            throw new DiscordApiException('Die Discord Server-ID ist ungültig.');
        }
        $guild = $this->botGet('/guilds/' . rawurlencode($guildId));
        $channels = $this->botGet('/guilds/' . rawurlencode($guildId) . '/channels');
        $sendable = [];
        foreach ($channels as $channel) {
            if (!is_array($channel) || !in_array((int) ($channel['type'] ?? -1), [0, 5, 10, 11, 12], true)) continue;
            $id = (string) ($channel['id'] ?? '');
            $name = mb_substr(trim((string) ($channel['name'] ?? '')), 0, 120);
            if (!preg_match('/^[0-9]{15,22}$/', $id) || $name === '') continue;
            $sendable[] = ['channel_id' => $id, 'name' => $name, 'type' => (int) $channel['type'], 'position' => (int) ($channel['position'] ?? 0)];
        }
        usort($sendable, static fn (array $left, array $right): int => [$left['position'], $left['name']] <=> [$right['position'], $right['name']]);
        return ['server_name' => mb_substr(trim((string) ($guild['name'] ?? 'Discord-Server')), 0, 120), 'channels' => $sendable];
    }

    private function deliverEmbed(string $eventKey, string $channelId, array $message, string $guildId = ''): array
    {
        $payload = [
            'embeds' => [$this->normalizeEmbed($message, true)],
            'allowed_mentions' => ['parse' => []],
        ];
        return $this->deliverPayload($eventKey, $channelId, $payload, $guildId);
    }

    private function deliverPayload(string $eventKey, string $channelId, array $payload, string $guildId = ''): array
    {
        if (!$this->isConfigured()) {
            throw new DiscordApiException('Noch kein Discord-Bot-Token gespeichert.');
        }
        if (!preg_match('/^[0-9]{15,22}$/', $channelId)) {
            throw new DiscordApiException('Die Discord-Channel-ID ist ungültig.');
        }

        $destination = ($guildId !== '' ? $guildId . '/' : '') . $channelId;

        try {
            [$status, $response] = $this->request($channelId, $payload);
            $this->logDelivery($eventKey, $destination, true, $status, null, $payload);
            return $response;
        } catch (DiscordApiException $exception) {
            $this->logDelivery(
                $eventKey,
                $destination,
                false,
                $exception->status > 0 ? $exception->status : null,
                $exception->getMessage(),
                $payload,
            );
            throw $exception;
        }
    }

    private function request(string $channelId, array $payload, bool $mayRetry = true): array
    {
        if (!function_exists('curl_init')) {
            throw new DiscordApiException('Die PHP-Erweiterung cURL fehlt.');
        }

        $token = trim((string) $this->settings->get('discord_bot_token', ''));
        $curl = curl_init(self::API_BASE . '/channels/' . rawurlencode($channelId) . '/messages');
        if ($curl === false) {
            throw new DiscordApiException('Discord-Anfrage konnte nicht initialisiert werden.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bot ' . $token,
                'Content-Type: application/json',
                'User-Agent: TwitchModDesk/1.4 (+https://localhost)',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new DiscordApiException('Discord ist nicht erreichbar: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $response = json_decode((string) $raw, true);
        $response = is_array($response) ? $response : [];

        if ($status === 429 && $mayRetry) {
            $retryAfter = (float) ($response['retry_after'] ?? 0);
            if ($retryAfter > 0 && $retryAfter <= 2) {
                usleep((int) ceil($retryAfter * 1_000_000));
                return $this->request($channelId, $payload, false);
            }
        }

        if ($status < 200 || $status >= 300) {
            $message = trim((string) ($response['message'] ?? ''));
            throw new DiscordApiException(
                $message !== '' ? 'Discord: ' . $message : 'Discord-Nachricht konnte nicht gesendet werden.',
                $status,
                $response,
            );
        }
        return [$status, $response];
    }

    private function botGet(string $path): array
    {
        if (!$this->isConfigured()) throw new DiscordApiException('Noch kein Discord-Bot-Token gespeichert.');
        if (!function_exists('curl_init')) throw new DiscordApiException('Die PHP-Erweiterung cURL fehlt.');
        $token = trim((string) $this->settings->get('discord_bot_token', ''));
        $curl = curl_init(self::API_BASE . $path);
        if ($curl === false) throw new DiscordApiException('Discord-Anfrage konnte nicht initialisiert werden.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bot ' . $token,
                'User-Agent: TwitchModDesk/1.4 (+https://localhost)',
            ],
        ]);
        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new DiscordApiException('Discord ist nicht erreichbar: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $response = json_decode((string) $raw, true);
        $response = is_array($response) ? $response : [];
        if ($status < 200 || $status >= 300) {
            $message = trim((string) ($response['message'] ?? ''));
            throw new DiscordApiException($message !== '' ? 'Discord: ' . $message : 'Discord-Daten konnten nicht gelesen werden.', $status, $response);
        }
        return $response;
    }

    private function normalizeEmbed(array $message, bool $withDefaults): array
    {
        $embed = ['color' => max(0, min(16777215, (int) ($message['color'] ?? 9525247)))];
        $title = mb_substr(trim((string) ($message['title'] ?? ($withDefaults ? 'ModDesk' : ''))), 0, 256);
        $description = mb_substr(trim((string) ($message['description'] ?? '')), 0, 4096);
        if ($title !== '') {
            $embed['title'] = $title;
        }
        if ($description !== '') {
            $embed['description'] = $description;
        }

        $embedUrl = $this->httpUrl($message['url'] ?? '');
        if ($embedUrl !== '') {
            $embed['url'] = $embedUrl;
        }

        $authorName = mb_substr(trim((string) ($message['author_name'] ?? '')), 0, 256);
        if ($authorName !== '') {
            $embed['author'] = ['name' => $authorName];
            $authorUrl = $this->httpUrl($message['author_url'] ?? '');
            $authorIconUrl = $this->httpUrl($message['author_icon_url'] ?? '');
            if ($authorUrl !== '') {
                $embed['author']['url'] = $authorUrl;
            }
            if ($authorIconUrl !== '') {
                $embed['author']['icon_url'] = $authorIconUrl;
            }
        }

        $thumbnailUrl = $this->httpUrl($message['thumbnail_url'] ?? '');
        if ($thumbnailUrl !== '') {
            $embed['thumbnail'] = ['url' => $thumbnailUrl];
        }
        $imageUrl = $this->httpUrl($message['image_url'] ?? '');
        if ($imageUrl !== '') {
            $embed['image'] = ['url' => $imageUrl];
        }

        $footerText = mb_substr(trim((string) ($message['footer_text'] ?? ($withDefaults ? 'Twitch ModDesk' : ''))), 0, 2048);
        if ($footerText !== '') {
            $embed['footer'] = ['text' => $footerText];
            $footerIconUrl = $this->httpUrl($message['footer_icon_url'] ?? '');
            if ($footerIconUrl !== '') {
                $embed['footer']['icon_url'] = $footerIconUrl;
            }
        }
        if ($withDefaults || !empty($message['timestamp'])) {
            $embed['timestamp'] = gmdate('c');
        }

        $fields = [];
        foreach (array_slice((array) ($message['fields'] ?? []), 0, 25) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = mb_substr(trim((string) ($field['name'] ?? '')), 0, 256);
            $value = mb_substr(trim((string) ($field['value'] ?? '')), 0, 1024);
            if ($name !== '' && $value !== '') {
                $fields[] = ['name' => $name, 'value' => $value, 'inline' => !empty($field['inline'])];
            }
        }
        if ($fields !== []) {
            $embed['fields'] = $fields;
        }
        return $embed;
    }

    private function httpUrl(mixed $value): string
    {
        $url = trim((string) $value);
        if ($url === '' || strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    }

    private function logDelivery(
        string $eventKey,
        string $destination,
        bool $success,
        ?int $status,
        ?string $error,
        array $payload,
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO integration_deliveries
                    (provider, event_key, destination, success, response_status, error_message, payload)
                 VALUES (\'discord\', :event_key, :destination, :success, :response_status, :error_message, :payload)'
            );
            $statement->execute([
                'event_key' => mb_substr($eventKey, 0, 80),
                'destination' => mb_substr($destination, 0, 190),
                'success' => $success ? 1 : 0,
                'response_status' => $status,
                'error_message' => $error !== null ? mb_substr($error, 0, 1000) : null,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable) {
            // Eine fehlgeschlagene Protokollierung darf die eigentliche Moderationsaktion nicht beeinflussen.
        }
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-z0-9_]{2,64}$/', $table)) return false;
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :table_name'
            );
            $statement->execute(['table_name' => $table]);
            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}

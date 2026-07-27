<?php

declare(strict_types=1);

final class GitHubReleaseClient
{
    private const API_BASE = 'https://api.github.com';
    private const API_VERSION = '2022-11-28';
    private const MAX_PACKAGE_BYTES = 52_428_800;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
        private readonly string $root,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->settings->bool('github_updates_enabled', false)
            && $this->repository() !== '';
    }

    public function repository(): string
    {
        return strtolower(trim((string) $this->settings->get('github_repository', '')));
    }

    public function assetName(): string
    {
        $name = basename(trim((string) $this->settings->get('github_asset_name', 'twitch-moddesk.zip')));
        return $name !== '' ? mb_substr($name, 0, 190) : 'twitch-moddesk.zip';
    }

    public function cachedStatus(): ?array
    {
        try {
            $statement = $this->pdo->query('SELECT * FROM github_release_status WHERE id = 1 LIMIT 1');
            return $statement->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function updateAvailable(?array $status = null): bool
    {
        $status ??= $this->cachedStatus();
        $version = trim((string) ($status['version'] ?? ''));
        return $version !== '' && version_compare($version, app_version(), '>') && !empty($status['asset_id']);
    }

    public function checkLatest(bool $force = true): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Die GitHub-Updateverbindung ist noch nicht aktiviert oder vollständig eingerichtet.');
        }
        if (!$force && !$this->checkIsDue()) {
            return $this->cachedStatus() ?? [];
        }
        $repository = $this->repository();
        if (!preg_match('#^[a-z0-9_.-]{1,100}/[a-z0-9_.-]{1,100}$#i', $repository)) {
            throw new InvalidArgumentException('Das GitHub-Repository muss als owner/repository eingetragen werden.');
        }

        try {
            $release = $this->requestJson('/repos/' . str_replace('%2F', '/', rawurlencode($repository)) . '/releases/latest');
            $tag = mb_substr(trim((string) ($release['tag_name'] ?? '')), 0, 120);
            $version = $this->normalizeVersion($tag);
            if ($version === null) {
                throw new RuntimeException('Der neueste GitHub-Release-Tag muss wie v1.4.1 oder 1.4.1 aufgebaut sein.');
            }

            $asset = null;
            foreach ((array) ($release['assets'] ?? []) as $candidate) {
                if (is_array($candidate) && ($candidate['name'] ?? '') === $this->assetName() && ($candidate['state'] ?? 'uploaded') === 'uploaded') {
                    $asset = $candidate;
                    break;
                }
            }
            if (!is_array($asset)) {
                throw new RuntimeException('Im neuesten Release fehlt das Update-Asset „' . $this->assetName() . '“.');
            }
            $assetSize = (int) ($asset['size'] ?? 0);
            if ($assetSize < 1 || $assetSize > self::MAX_PACKAGE_BYTES) {
                throw new RuntimeException('Das GitHub-Updatepaket besitzt eine ungültige Größe.');
            }
            $assetApiUrl = trim((string) ($asset['url'] ?? ''));
            if (!$this->isGitHubAssetApiUrl($assetApiUrl)) {
                throw new RuntimeException('GitHub hat keine gültige Asset-API-Adresse geliefert.');
            }

            $publishedAt = $this->githubDate((string) ($release['published_at'] ?? ''));
            $statement = $this->pdo->prepare(
                'INSERT INTO github_release_status
                    (id, repository, release_id, tag_name, version, release_name, release_body, html_url,
                     asset_id, asset_name, asset_api_url, browser_download_url, asset_size, asset_digest,
                     published_at, checked_at, error_message)
                 VALUES
                    (1, :repository, :release_id, :tag_name, :version, :release_name, :release_body, :html_url,
                     :asset_id, :asset_name, :asset_api_url, :browser_download_url, :asset_size, :asset_digest,
                     :published_at, UTC_TIMESTAMP(), NULL)
                 ON DUPLICATE KEY UPDATE repository = VALUES(repository), release_id = VALUES(release_id),
                    tag_name = VALUES(tag_name), version = VALUES(version), release_name = VALUES(release_name),
                    release_body = VALUES(release_body), html_url = VALUES(html_url), asset_id = VALUES(asset_id),
                    asset_name = VALUES(asset_name), asset_api_url = VALUES(asset_api_url),
                    browser_download_url = VALUES(browser_download_url), asset_size = VALUES(asset_size),
                    asset_digest = VALUES(asset_digest), published_at = VALUES(published_at),
                    checked_at = UTC_TIMESTAMP(), error_message = NULL'
            );
            $statement->execute([
                'repository' => $repository,
                'release_id' => (int) ($release['id'] ?? 0) ?: null,
                'tag_name' => $tag,
                'version' => $version,
                'release_name' => mb_substr(trim((string) ($release['name'] ?? $tag)), 0, 190),
                'release_body' => mb_substr((string) ($release['body'] ?? ''), 0, 100000),
                'html_url' => mb_substr((string) ($release['html_url'] ?? ''), 0, 1000),
                'asset_id' => (int) ($asset['id'] ?? 0),
                'asset_name' => mb_substr((string) $asset['name'], 0, 190),
                'asset_api_url' => mb_substr($assetApiUrl, 0, 1000),
                'browser_download_url' => mb_substr((string) ($asset['browser_download_url'] ?? ''), 0, 1000),
                'asset_size' => $assetSize,
                'asset_digest' => mb_substr((string) ($asset['digest'] ?? ''), 0, 120) ?: null,
                'published_at' => $publishedAt,
            ]);
            return $this->cachedStatus() ?? [];
        } catch (Throwable $exception) {
            $this->saveError($repository, $exception->getMessage());
            throw $exception;
        }
    }

    public function downloadCachedPackage(): array
    {
        $status = $this->cachedStatus();
        if (!$this->updateAvailable($status)) {
            throw new RuntimeException('Es ist kein neuerer GitHub-Release mit Updatepaket verfügbar.');
        }
        $url = (string) ($status['asset_api_url'] ?? '');
        if (!$this->isGitHubAssetApiUrl($url)) {
            throw new RuntimeException('Die gespeicherte GitHub-Asset-Adresse ist ungültig.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Für GitHub-Updates wird die PHP-Erweiterung cURL benötigt.');
        }

        $directory = $this->root . '/storage/github-downloads';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Der temporäre GitHub-Downloadordner konnte nicht angelegt werden.');
        }
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '.zip';
        $file = fopen($path, 'wb');
        if (!is_resource($file)) {
            throw new RuntimeException('Das GitHub-Updatepaket konnte nicht lokal angelegt werden.');
        }

        $downloaded = 0;
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($file);
            throw new RuntimeException('Der GitHub-Download konnte nicht initialisiert werden.');
        }
        $headers = $this->headers('application/octet-stream');
        curl_setopt_array($curl, [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($resource, float $downloadSize, float $downloadedNow) use (&$downloaded): int {
                $downloaded = (int) $downloadedNow;
                return $downloadedNow > self::MAX_PACKAGE_BYTES ? 1 : 0;
            },
        ]);
        $success = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($file);

        if ($success === false || $statusCode < 200 || $statusCode >= 300 || !$this->isAllowedDownloadUrl($effectiveUrl)) {
            @unlink($path);
            throw new RuntimeException('GitHub-Update konnte nicht heruntergeladen werden' . ($error !== '' ? ': ' . $error : '.'));
        }
        $actualSize = (int) (filesize($path) ?: 0);
        if ($actualSize < 1 || $actualSize > self::MAX_PACKAGE_BYTES || $downloaded > self::MAX_PACKAGE_BYTES) {
            @unlink($path);
            throw new RuntimeException('Das heruntergeladene GitHub-Paket besitzt eine ungültige Größe.');
        }
        $expectedSize = (int) ($status['asset_size'] ?? 0);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            @unlink($path);
            throw new RuntimeException('Der GitHub-Download ist unvollständig.');
        }

        $digest = strtolower(trim((string) ($status['asset_digest'] ?? '')));
        if (preg_match('/^sha256:([0-9a-f]{64})$/', $digest, $match)) {
            $actualDigest = hash_file('sha256', $path);
            if (!is_string($actualDigest) || !hash_equals($match[1], strtolower($actualDigest))) {
                @unlink($path);
                throw new RuntimeException('Die SHA-256-Prüfsumme des GitHub-Assets stimmt nicht.');
            }
        }

        return ['path' => $path, 'name' => (string) $status['asset_name'], 'version' => (string) $status['version']];
    }

    private function requestJson(string $path): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Für GitHub wird die PHP-Erweiterung cURL benötigt.');
        }
        $curl = curl_init(self::API_BASE . $path);
        if ($curl === false) throw new RuntimeException('GitHub-Anfrage konnte nicht initialisiert werden.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $this->headers('application/vnd.github+json'),
        ]);
        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('GitHub ist nicht erreichbar: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $data = json_decode((string) $raw, true);
        $data = is_array($data) ? $data : [];
        if ($status < 200 || $status >= 300) {
            $message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 500);
            throw new RuntimeException($message !== '' ? 'GitHub: ' . $message : 'GitHub-Release konnte nicht gelesen werden (HTTP ' . $status . ').');
        }
        return $data;
    }

    private function headers(string $accept): array
    {
        $headers = [
            'Accept: ' . $accept,
            'X-GitHub-Api-Version: ' . self::API_VERSION,
            'User-Agent: TwitchModDesk/' . app_version(),
        ];
        $token = trim((string) $this->settings->get('github_token', ''));
        if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
        return $headers;
    }

    private function checkIsDue(): bool
    {
        $status = $this->cachedStatus();
        if ($status === null || empty($status['checked_at'])) return true;
        $hours = max(1, min(168, (int) $this->settings->get('github_check_interval_hours', '6')));
        try {
            return new DateTimeImmutable((string) $status['checked_at'], new DateTimeZone('UTC')) <= new DateTimeImmutable('-' . $hours . ' hours', new DateTimeZone('UTC'));
        } catch (Throwable) {
            return true;
        }
    }

    private function normalizeVersion(string $tag): ?string
    {
        $version = ltrim(trim($tag), "vV");
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : null;
    }

    private function githubDate(string $value): ?string
    {
        if ($value === '') return null;
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function isGitHubAssetApiUrl(string $url): bool
    {
        return (bool) preg_match('#^https://api\.github\.com/repos/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/releases/assets/[0-9]+$#', $url);
    }

    private function isAllowedDownloadUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return in_array($host, ['api.github.com', 'github.com'], true)
            || str_ends_with($host, '.githubusercontent.com')
            || str_ends_with($host, '.github.com');
    }

    private function saveError(string $repository, string $message): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO github_release_status (id, repository, checked_at, error_message)
                 VALUES (1, :repository, UTC_TIMESTAMP(), :error_message)
                 ON DUPLICATE KEY UPDATE repository = VALUES(repository), checked_at = UTC_TIMESTAMP(),
                    error_message = VALUES(error_message)'
            );
            $statement->execute(['repository' => $repository, 'error_message' => mb_substr($message, 0, 1000)]);
        } catch (Throwable) {
            // Der ursprüngliche Verbindungsfehler bleibt maßgeblich.
        }
    }
}

<?php

declare(strict_types=1);

final class ProfileManager
{
    private const MAX_AVATAR_BYTES = 3_145_728;
    private ?bool $readyCache = null;
    private array $metadataCache = [];
    private array $avatarCache = [];

    public function __construct(private readonly PDO $pdo)
    {
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
                 AND table_name IN ('user_avatars', 'user_oauth_accounts')"
            );
            return $this->readyCache = (int) $statement->fetchColumn() === 2;
        } catch (Throwable) {
            return $this->readyCache = false;
        }
    }

    public function storeAvatar(array $upload, int $userId): string
    {
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die aktuelle Profil- und Login-Migration aus.');
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }

        $temporaryFile = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($temporaryFile === '' || !is_uploaded_file($temporaryFile) || $size < 1 || $size > self::MAX_AVATAR_BYTES) {
            throw new RuntimeException('Das Profilbild muss eine gültige Bilddatei mit höchstens 3 MB sein.');
        }

        $image = @getimagesize($temporaryFile);
        if (!is_array($image)) {
            throw new RuntimeException('Die hochgeladene Datei ist kein lesbares Bild.');
        }

        $mime = strtolower((string) ($image['mime'] ?? ''));
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new RuntimeException('Als Profilbild sind ausschließlich PNG, JPG und WebP erlaubt.');
        }

        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        if ($width < 64 || $height < 64 || $width > 4096 || $height > 4096) {
            throw new RuntimeException('Das Profilbild muss zwischen 64×64 und 4096×4096 Pixel groß sein.');
        }

        $hash = hash_file('sha256', $temporaryFile);
        $binary = file_get_contents($temporaryFile);
        if (!is_string($hash) || $hash === '' || !is_string($binary) || $binary === '') {
            throw new RuntimeException('Das Profilbild konnte nicht sicher gelesen werden.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user_avatars
                (user_id, mime_type, file_data, checksum_sha256, width, height)
             VALUES (:user_id, :mime_type, :file_data, :checksum, :width, :height)
             ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), file_data = VALUES(file_data),
                checksum_sha256 = VALUES(checksum_sha256), width = VALUES(width), height = VALUES(height),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':mime_type', $mime);
        $statement->bindValue(':file_data', $binary, PDO::PARAM_LOB);
        $statement->bindValue(':checksum', $hash);
        $statement->bindValue(':width', $width, PDO::PARAM_INT);
        $statement->bindValue(':height', $height, PDO::PARAM_INT);
        $statement->execute();

        unset($this->metadataCache[$userId], $this->avatarCache[$userId]);
        return $hash;
    }

    public function deleteAvatar(int $userId): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die aktuelle Profil- und Login-Migration aus.');
        }
        $this->pdo->prepare('DELETE FROM user_avatars WHERE user_id = :user_id')
            ->execute(['user_id' => $userId]);
        $this->metadataCache[$userId] = null;
        $this->avatarCache[$userId] = null;
    }

    public function avatarMetadata(int $userId): ?array
    {
        if (array_key_exists($userId, $this->metadataCache)) {
            return $this->metadataCache[$userId];
        }
        if (!$this->ready()) {
            return $this->metadataCache[$userId] = null;
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT mime_type, checksum_sha256, width, height, updated_at
                 FROM user_avatars WHERE user_id = :user_id LIMIT 1'
            );
            $statement->execute(['user_id' => $userId]);
            $row = $statement->fetch();
            if (!is_array($row) || !in_array($row['mime_type'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return $this->metadataCache[$userId] = null;
            }
            return $this->metadataCache[$userId] = $row;
        } catch (Throwable) {
            return $this->metadataCache[$userId] = null;
        }
    }

    public function avatar(int $userId): ?array
    {
        if (array_key_exists($userId, $this->avatarCache)) {
            return $this->avatarCache[$userId];
        }
        $metadata = $this->avatarMetadata($userId);
        if ($metadata === null) {
            return $this->avatarCache[$userId] = null;
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT file_data FROM user_avatars WHERE user_id = :user_id LIMIT 1'
            );
            $statement->execute(['user_id' => $userId]);
            $data = $statement->fetchColumn();
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if (!is_string($data) || $data === '') {
                return $this->avatarCache[$userId] = null;
            }
            return $this->avatarCache[$userId] = $metadata + ['file_data' => $data];
        } catch (Throwable) {
            return $this->avatarCache[$userId] = null;
        }
    }

    public function linkedAvatarUrl(int $userId): ?string
    {
        if (!$this->ready()) {
            return null;
        }
        try {
            $statement = $this->pdo->prepare(
                "SELECT provider_avatar_url FROM user_oauth_accounts
                 WHERE user_id = :user_id AND provider = 'twitch' LIMIT 1"
            );
            $statement->execute(['user_id' => $userId]);
            $url = trim((string) ($statement->fetchColumn() ?: ''));
            return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
                ? $url
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Das Profilbild überschreitet die erlaubte Dateigröße.',
            UPLOAD_ERR_PARTIAL => 'Das Profilbild wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Bitte wähle zuerst ein Profilbild aus.',
            default => 'Das Profilbild konnte nicht hochgeladen werden.',
        };
    }
}

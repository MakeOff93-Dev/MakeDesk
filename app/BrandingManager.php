<?php

declare(strict_types=1);

final class BrandingManager
{
    private const MAX_LOGO_BYTES = 2_097_152;
    private bool $metadataResolved = false;
    private ?array $metadataCache = null;
    private bool $logoResolved = false;
    private ?array $logoCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
    ) {
    }

    public function storeLogo(array $upload, int $updatedBy): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }

        $temporaryFile = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($temporaryFile === '' || !is_uploaded_file($temporaryFile) || $size < 1 || $size > self::MAX_LOGO_BYTES) {
            throw new RuntimeException('Das Logo muss eine gültige Bilddatei mit höchstens 2 MB sein.');
        }

        $image = @getimagesize($temporaryFile);
        if (!is_array($image)) {
            throw new RuntimeException('Die hochgeladene Datei ist kein lesbares Bild.');
        }

        $mime = strtolower((string) ($image['mime'] ?? ''));
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Als Logo sind ausschließlich PNG, JPG und WebP erlaubt.');
        }

        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        if ($width < 16 || $height < 16 || $width > 4096 || $height > 4096) {
            throw new RuntimeException('Das Logo muss zwischen 16×16 und 4096×4096 Pixel groß sein.');
        }

        $hash = hash_file('sha256', $temporaryFile);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Das Logo konnte nicht geprüft werden.');
        }
        $binary = file_get_contents($temporaryFile);
        if (!is_string($binary) || $binary === '') {
            throw new RuntimeException('Das Logo konnte nicht gelesen werden.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO branding_assets
                (asset_key, mime_type, file_data, checksum_sha256, width, height, updated_by)
             VALUES (\'logo\', :mime_type, :file_data, :checksum, :width, :height, :updated_by)
             ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), file_data = VALUES(file_data),
                checksum_sha256 = VALUES(checksum_sha256), width = VALUES(width), height = VALUES(height),
                updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
        );
        $statement->bindValue(':mime_type', $mime);
        $statement->bindValue(':file_data', $binary, PDO::PARAM_LOB);
        $statement->bindValue(':checksum', $hash);
        $statement->bindValue(':width', $width, PDO::PARAM_INT);
        $statement->bindValue(':height', $height, PDO::PARAM_INT);
        $statement->bindValue(':updated_by', $updatedBy, PDO::PARAM_INT);
        $statement->execute();
        $this->settings->set('brand_logo_enabled', 'true', false, $updatedBy);
        $this->metadataResolved = false;
        $this->metadataCache = null;
        $this->logoResolved = false;
        $this->logoCache = null;
        return $hash;
    }

    public function clearLogo(int $updatedBy): void
    {
        $this->pdo->prepare('DELETE FROM branding_assets WHERE asset_key = \'logo\'')->execute();
        $this->settings->set('brand_logo_enabled', 'false', false, $updatedBy);
        $this->metadataResolved = true;
        $this->metadataCache = null;
        $this->logoResolved = true;
        $this->logoCache = null;
    }

    public function logoMetadata(): ?array
    {
        if ($this->metadataResolved) {
            return $this->metadataCache;
        }
        $this->metadataResolved = true;
        if (!$this->settings->bool('brand_logo_enabled', false)) {
            return null;
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT mime_type, checksum_sha256, width, height, updated_at
                 FROM branding_assets WHERE asset_key = \'logo\' LIMIT 1'
            );
            $statement->execute();
            $row = $statement->fetch();
            if (!is_array($row) || !in_array($row['mime_type'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return null;
            }
            $this->metadataCache = $row;
            return $this->metadataCache;
        } catch (Throwable) {
            return null;
        }
    }

    public function logo(): ?array
    {
        if ($this->logoResolved) {
            return $this->logoCache;
        }
        $this->logoResolved = true;
        $metadata = $this->logoMetadata();
        if ($metadata === null) {
            return null;
        }
        try {
            $statement = $this->pdo->prepare('SELECT file_data FROM branding_assets WHERE asset_key = \'logo\' LIMIT 1');
            $statement->execute();
            $data = $statement->fetchColumn();
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if (!is_string($data) || $data === '') {
                return null;
            }
            $this->logoCache = $metadata + ['file_data' => $data];
            return $this->logoCache;
        } catch (Throwable) {
            return null;
        }
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Das Logo überschreitet die erlaubte Dateigröße.',
            UPLOAD_ERR_PARTIAL => 'Das Logo wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Bitte wähle zuerst eine Logo-Datei aus.',
            default => 'Das Logo konnte nicht hochgeladen werden.',
        };
    }
}

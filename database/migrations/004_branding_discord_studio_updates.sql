CREATE TABLE IF NOT EXISTS branding_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_key VARCHAR(80) NOT NULL UNIQUE,
    mime_type VARCHAR(80) NOT NULL,
    file_data MEDIUMBLOB NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    width SMALLINT UNSIGNED NULL,
    height SMALLINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_branding_asset_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_message_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    message_content VARCHAR(2000) NULL,
    embed_title VARCHAR(256) NULL,
    embed_description TEXT NULL,
    embed_url VARCHAR(1000) NULL,
    embed_color INT UNSIGNED NOT NULL DEFAULT 9525247,
    author_name VARCHAR(256) NULL,
    author_url VARCHAR(1000) NULL,
    author_icon_url VARCHAR(1000) NULL,
    thumbnail_url VARCHAR(1000) NULL,
    image_url VARCHAR(1000) NULL,
    footer_text VARCHAR(2048) NULL,
    footer_icon_url VARCHAR(1000) NULL,
    include_timestamp TINYINT(1) NOT NULL DEFAULT 1,
    fields_json JSON NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discord_templates_updated (updated_at),
    CONSTRAINT fk_discord_template_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_discord_template_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_version VARCHAR(40) NOT NULL,
    to_version VARCHAR(40) NOT NULL,
    package_name VARCHAR(190) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    backup_path VARCHAR(500) NULL,
    error_message VARCHAR(2000) NULL,
    details JSON NULL,
    applied_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_system_updates_created (created_at),
    INDEX idx_system_updates_status (status, created_at),
    CONSTRAINT fk_system_update_user FOREIGN KEY (applied_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

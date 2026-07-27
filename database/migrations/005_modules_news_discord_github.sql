CREATE TABLE IF NOT EXISTS modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    version VARCHAR(40) NOT NULL DEFAULT '1.0.0',
    source VARCHAR(20) NOT NULL DEFAULT 'builtin',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    protected TINYINT(1) NOT NULL DEFAULT 0,
    directory_name VARCHAR(100) NULL,
    manifest JSON NULL,
    installed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_modules_enabled (enabled, source),
    CONSTRAINT fk_modules_installer FOREIGN KEY (installed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO modules (module_key, name, description, version, source, enabled, protected) VALUES
    ('news', 'News & Ankündigungen', 'Interne oder veröffentlichte Neuigkeiten für das ModDesk-Team.', '1.0.0', 'builtin', 1, 0),
    ('ideas', 'Ideen', 'Ideen-Board und Planung.', '1.0.0', 'builtin', 1, 0),
    ('notes', 'Notizen', 'Teamnotizen und Wissensablage.', '1.0.0', 'builtin', 1, 0),
    ('links', 'Links', 'Geteilte Links und Ressourcen.', '1.0.0', 'builtin', 1, 0),
    ('twitch', 'Twitch', 'Twitch OAuth, Nutzer und Moderationswerkzeuge.', '1.0.0', 'builtin', 1, 0),
    ('ban-sync', 'BanSync', 'Kanalübergreifende Bans und Banlog.', '1.0.0', 'builtin', 1, 0),
    ('cases', 'Moderationsfälle', 'Interne Moderationsfälle und Aktionsverlauf.', '1.0.0', 'builtin', 1, 0),
    ('discord', 'Discord', 'Discord Studio, Bot und Benachrichtigungen.', '1.0.0', 'builtin', 1, 0),
    ('team', 'Team & Rechte', 'Lokale Zugänge und Rollenverwaltung.', '1.0.0', 'builtin', 1, 0),
    ('design', 'Design-Editor', 'Branding, Navigation und Seiteninhalte.', '1.0.0', 'builtin', 1, 0),
    ('audit', 'Audit-Protokoll', 'Nachvollziehbares System- und Aktionsprotokoll.', '1.0.0', 'builtin', 1, 0);

CREATE TABLE IF NOT EXISTS module_migrations (
    module_key VARCHAR(80) NOT NULL,
    migration_name VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (module_key, migration_name),
    CONSTRAINT fk_module_migrations_module FOREIGN KEY (module_key) REFERENCES modules(module_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    publish_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_news_status_publish (status, publish_at),
    INDEX idx_news_pinned_updated (pinned, updated_at),
    CONSTRAINT fk_news_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_news_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_servers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discord_servers_enabled (enabled, name),
    CONSTRAINT fk_discord_server_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_discord_server_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT UNSIGNED NOT NULL,
    channel_id VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discord_channels_server (server_id, enabled, name),
    CONSTRAINT fk_discord_channel_server FOREIGN KEY (server_id) REFERENCES discord_servers(id) ON DELETE CASCADE,
    CONSTRAINT fk_discord_channel_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_discord_channel_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_channel_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(80) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_discord_channel_event (channel_id, event_key),
    INDEX idx_discord_channel_routes_event (event_key, enabled),
    CONSTRAINT fk_discord_route_channel FOREIGN KEY (channel_id) REFERENCES discord_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO discord_servers (guild_id, name, enabled, created_by, updated_by)
SELECT DISTINCT guild_id, CONCAT('Discord-Server ', guild_id), 1, updated_by, updated_by
FROM discord_notification_routes
WHERE guild_id IS NOT NULL AND guild_id <> '';

INSERT IGNORE INTO discord_channels (server_id, channel_id, name, enabled, created_by, updated_by)
SELECT ds.id, dnr.channel_id, CONCAT('Channel ', dnr.channel_id), dnr.enabled, dnr.updated_by, dnr.updated_by
FROM discord_notification_routes dnr
JOIN discord_servers ds ON ds.guild_id = dnr.guild_id
WHERE dnr.channel_id IS NOT NULL AND dnr.channel_id <> '';

INSERT IGNORE INTO discord_channel_routes (channel_id, event_key, enabled)
SELECT dc.id, dnr.event_key, dnr.enabled
FROM discord_notification_routes dnr
JOIN discord_channels dc ON dc.channel_id = dnr.channel_id
WHERE dnr.channel_id IS NOT NULL AND dnr.channel_id <> '';

INSERT IGNORE INTO discord_notification_routes (event_key) VALUES ('news_published'), ('changelog');

CREATE TABLE IF NOT EXISTS github_release_status (
    id TINYINT UNSIGNED PRIMARY KEY,
    repository VARCHAR(190) NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    tag_name VARCHAR(120) NULL,
    version VARCHAR(40) NULL,
    release_name VARCHAR(190) NULL,
    release_body MEDIUMTEXT NULL,
    html_url VARCHAR(1000) NULL,
    asset_id BIGINT UNSIGNED NULL,
    asset_name VARCHAR(190) NULL,
    asset_api_url VARCHAR(1000) NULL,
    browser_download_url VARCHAR(1000) NULL,
    asset_size BIGINT UNSIGNED NULL,
    asset_digest VARCHAR(120) NULL,
    published_at DATETIME NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    error_message VARCHAR(1000) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

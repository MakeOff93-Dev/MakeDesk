CREATE TABLE IF NOT EXISTS discord_notification_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(80) NOT NULL UNIQUE,
    guild_id VARCHAR(40) NULL,
    channel_id VARCHAR(40) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discord_routes_enabled (enabled, event_key),
    CONSTRAINT fk_discord_route_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO discord_notification_routes (event_key) VALUES
    ('ban_sync'),
    ('moderation_action'),
    ('idea_created'),
    ('case_created');

CREATE TABLE IF NOT EXISTS integration_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(20) NOT NULL,
    event_key VARCHAR(80) NOT NULL,
    destination VARCHAR(190) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    response_status SMALLINT UNSIGNED NULL,
    error_message VARCHAR(1000) NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_integration_deliveries_created (created_at),
    INDEX idx_integration_deliveries_provider (provider, success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

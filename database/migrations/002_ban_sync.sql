CREATE TABLE IF NOT EXISTS ban_sync_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    twitch_user_id VARCHAR(40) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    validation_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    validated_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ban_sync_channels_enabled (enabled, validation_status),
    CONSTRAINT fk_ban_sync_channel_twitch_user FOREIGN KEY (twitch_user_id) REFERENCES twitch_users(twitch_user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ban_sync_channel_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ban_sync_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_twitch_user_id VARCHAR(40) NOT NULL,
    action VARCHAR(10) NOT NULL,
    reason VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    channel_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    success_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    requested_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_ban_sync_jobs_created (created_at),
    INDEX idx_ban_sync_jobs_target (target_twitch_user_id, created_at),
    CONSTRAINT fk_ban_sync_job_target FOREIGN KEY (target_twitch_user_id) REFERENCES twitch_users(twitch_user_id),
    CONSTRAINT fk_ban_sync_job_requester FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ban_sync_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    channel_twitch_user_id VARCHAR(40) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    http_status SMALLINT UNSIGNED NULL,
    error_message VARCHAR(1000) NULL,
    api_response JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ban_sync_result_channel (job_id, channel_twitch_user_id),
    INDEX idx_ban_sync_results_success (success, created_at),
    CONSTRAINT fk_ban_sync_result_job FOREIGN KEY (job_id) REFERENCES ban_sync_jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ban_sync_result_channel FOREIGN KEY (channel_twitch_user_id) REFERENCES twitch_users(twitch_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

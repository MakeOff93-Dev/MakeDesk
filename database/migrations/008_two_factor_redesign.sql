CREATE TABLE IF NOT EXISTS user_two_factor (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    secret_encrypted TEXT NOT NULL,
    enabled_at DATETIME NOT NULL,
    last_used_counter BIGINT UNSIGNED NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_two_factor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_two_factor_recovery_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_two_factor_recovery_user (user_id, used_at),
    CONSTRAINT fk_two_factor_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE settings SET setting_value = '#0B0B0D'
WHERE setting_key = 'theme_background' AND UPPER(TRIM(setting_value)) = '#09080E';

UPDATE settings SET setting_value = '#131316'
WHERE setting_key = 'theme_surface' AND UPPER(TRIM(setting_value)) = '#111019';

UPDATE settings SET setting_value = '#1A1A1F'
WHERE setting_key = 'theme_surface_alt' AND UPPER(TRIM(setting_value)) = '#171520';

UPDATE settings SET setting_value = '#F5F5F6'
WHERE setting_key = 'theme_text' AND UPPER(TRIM(setting_value)) = '#F6F4FB';

UPDATE settings SET setting_value = '#9B9BA5'
WHERE setting_key = 'theme_muted' AND UPPER(TRIM(setting_value)) = '#9691A5';

UPDATE settings SET setting_value = '#C7364A'
WHERE setting_key = 'theme_primary' AND UPPER(TRIM(setting_value)) = '#9147FF';

UPDATE settings SET setting_value = '#EC6574'
WHERE setting_key = 'theme_secondary' AND UPPER(TRIM(setting_value)) = '#B77BFF';

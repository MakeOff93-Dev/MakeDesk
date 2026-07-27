ALTER TABLE users MODIFY COLUMN role VARCHAR(80) NOT NULL DEFAULT 'viewer';

CREATE TABLE IF NOT EXISTS roles (
    role_key VARCHAR(80) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    protected TINYINT(1) NOT NULL DEFAULT 0,
    is_owner TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_roles_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    permission_key VARCHAR(100) PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    `sensitive` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_key VARCHAR(80) NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_key, permission_key),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_key) REFERENCES roles(role_key) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_key) REFERENCES permissions(permission_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (role_key, name, description, protected, is_owner) VALUES
    ('owner', 'Owner', 'Geschützter Hauptadministrator mit Vollzugriff.', 1, 1),
    ('admin', 'Administrator', 'Verwaltet Inhalte, Team, Integrationen und Design.', 1, 0),
    ('moderator', 'Moderator', 'Bearbeitet Inhalte und nutzt freigegebene Moderationswerkzeuge.', 1, 0),
    ('viewer', 'Nur Lesen', 'Kann freigegebene Panel-Inhalte nur ansehen.', 1, 0);

INSERT IGNORE INTO permissions (permission_key, name, category, description, `sensitive`) VALUES
    ('content.write', 'Inhalte bearbeiten', 'Inhalte', 'Ideen, Notizen, Links und News anlegen oder bearbeiten.', 0),
    ('content.archive', 'Inhalte archivieren', 'Inhalte', 'Inhalte archivieren oder ausblenden.', 0),
    ('twitch.use', 'Twitch-Modtools nutzen', 'Twitch', 'Moderationsaktionen über Twitch ausführen.', 1),
    ('twitch.configure', 'Twitch konfigurieren', 'Twitch', 'Konten, Zielkanäle und OAuth-Verbindungen verwalten.', 1),
    ('discord.studio', 'Discord Studio nutzen', 'Discord', 'Gestaltete Nachrichten an Discord senden.', 1),
    ('discord.configure', 'Discord konfigurieren', 'Discord', 'Bot, Server, Channels und Routen verwalten.', 1),
    ('team.manage', 'Benutzer verwalten', 'Benutzer', 'Panel-Zugänge anlegen, bearbeiten und sperren.', 1),
    ('roles.manage', 'Rollen und Rechte verwalten', 'Benutzer', 'Rollen und detaillierte Berechtigungen bearbeiten.', 1),
    ('settings.manage', 'Einstellungen verwalten', 'System', 'Allgemeine Integrations- und Systemeinstellungen ändern.', 1),
    ('design.manage', 'Design verwalten', 'System', 'Branding, Navigation und Seiteninhalte bearbeiten.', 0),
    ('modules.manage', 'Module verwalten', 'System', 'Module aktivieren, installieren, aktualisieren oder entfernen.', 1),
    ('updates.manage', 'Updates verwalten', 'System', 'ZIP- und GitHub-Updates installieren oder zurückrollen.', 1),
    ('migrations.manage', 'Migrationen verwalten', 'System', 'Datenbankmigrationen prüfen und ausführen.', 1),
    ('backups.manage', 'Backups verwalten', 'System', 'Backups erstellen, herunterladen und bereinigen.', 1),
    ('backups.restore', 'Backups wiederherstellen', 'System', 'Datenbank und Dateien aus einem Backup wiederherstellen.', 1),
    ('security.view', 'Sicherheitsstatus ansehen', 'Sicherheit', 'Sicherheitsprüfungen und Ereignisse einsehen.', 1),
    ('security.manage', 'Sicherheit verwalten', 'Sicherheit', 'IP-Sperren und Sitzungen verwalten.', 1),
    ('audit.view', 'Audit-Log ansehen', 'Protokolle', 'Administrative Änderungen und Aktionen einsehen.', 1),
    ('audit.export', 'Audit-Log exportieren', 'Protokolle', 'Protokolle als Datei exportieren.', 1);

INSERT IGNORE INTO role_permissions (role_key, permission_key) VALUES
    ('admin', 'content.write'),
    ('admin', 'content.archive'),
    ('admin', 'twitch.use'),
    ('admin', 'twitch.configure'),
    ('admin', 'discord.studio'),
    ('admin', 'discord.configure'),
    ('admin', 'team.manage'),
    ('admin', 'settings.manage'),
    ('admin', 'design.manage'),
    ('admin', 'audit.view'),
    ('moderator', 'content.write'),
    ('moderator', 'twitch.use');

CREATE TABLE IF NOT EXISTS system_backups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(190) NOT NULL,
    trigger_source VARCHAR(30) NOT NULL DEFAULT 'manual',
    backup_type VARCHAR(20) NOT NULL DEFAULT 'full',
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    storage_path VARCHAR(500) NULL,
    include_database TINYINT(1) NOT NULL DEFAULT 1,
    include_files TINYINT(1) NOT NULL DEFAULT 1,
    size_bytes BIGINT UNSIGNED NULL,
    checksum_sha256 CHAR(64) NULL,
    error_message VARCHAR(2000) NULL,
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    last_restored_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    last_restored_at DATETIME NULL,
    INDEX idx_system_backups_created (created_at),
    INDEX idx_system_backups_status (status, created_at),
    CONSTRAINT fk_system_backup_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_system_backup_restorer FOREIGN KEY (last_restored_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    error_message VARCHAR(2000) NULL,
    backup_id BIGINT UNSIGNED NULL,
    executed_by BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_migration_runs_created (started_at),
    CONSTRAINT fk_migration_run_backup FOREIGN KEY (backup_id) REFERENCES system_backups(id) ON DELETE SET NULL,
    CONSTRAINT fk_migration_run_user FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    message VARCHAR(1000) NOT NULL,
    context JSON NULL,
    user_id BIGINT UNSIGNED NULL,
    username VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    resolved_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_events_created (created_at),
    INDEX idx_security_events_severity (severity, created_at),
    CONSTRAINT fk_security_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_security_event_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(500) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'manual',
    expires_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_blocks_active (active, expires_at),
    CONSTRAINT fk_ip_block_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO modules (module_key, name, description, version, source, enabled, protected) VALUES
    ('backups', 'Backup & Wiederherstellung', 'Datenbank- und Dateisicherungen mit Wiederherstellung.', '1.0.0', 'builtin', 1, 1),
    ('security', 'Sicherheitsbereich', 'Systemprüfungen, Sicherheitsereignisse, IP-Sperren und Sitzungen.', '1.0.0', 'builtin', 1, 1);

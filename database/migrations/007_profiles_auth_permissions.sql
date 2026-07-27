CREATE TABLE IF NOT EXISTS user_avatars (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    mime_type VARCHAR(80) NOT NULL,
    file_data MEDIUMBLOB NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    width SMALLINT UNSIGNED NOT NULL,
    height SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_avatar_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_oauth_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(100) NOT NULL,
    provider_username VARCHAR(190) NULL,
    provider_display_name VARCHAR(190) NULL,
    provider_avatar_url VARCHAR(1000) NULL,
    provider_email VARCHAR(190) NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_oauth_provider_identity (provider, provider_user_id),
    UNIQUE KEY uq_user_oauth_user_provider (user_id, provider),
    INDEX idx_user_oauth_user (user_id),
    CONSTRAINT fk_user_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, name, category, description, `sensitive`) VALUES
    ('dashboard.view', 'Dashboard ansehen', 'Dashboard', 'Dashboard, Systemstatus und Schnellzugriffe öffnen.', 0),
    ('news.view', 'News ansehen', 'News', 'News und Ankündigungen lesen.', 0),
    ('news.create', 'News erstellen', 'News', 'Neue News und Entwürfe anlegen.', 0),
    ('news.edit', 'News bearbeiten/veröffentlichen', 'News', 'News bearbeiten, planen und veröffentlichen.', 0),
    ('news.archive', 'News archivieren', 'News', 'News ausblenden und archivieren.', 0),
    ('ideas.view', 'Ideen ansehen', 'Ideen', 'Das Ideen-Board öffnen.', 0),
    ('ideas.create', 'Ideen erstellen', 'Ideen', 'Neue Ideen anlegen.', 0),
    ('ideas.edit', 'Ideen bearbeiten', 'Ideen', 'Vorhandene Ideen ändern.', 0),
    ('ideas.archive', 'Ideen archivieren', 'Ideen', 'Ideen archivieren.', 0),
    ('notes.view', 'Notizen ansehen', 'Notizen', 'Freigegebene Teamnotizen lesen.', 0),
    ('notes.private.view', 'Private Notizen ansehen', 'Notizen', 'Auch intern markierte Notizen lesen.', 1),
    ('notes.create', 'Notizen erstellen', 'Notizen', 'Neue Notizen anlegen.', 0),
    ('notes.edit', 'Notizen bearbeiten', 'Notizen', 'Vorhandene Notizen ändern.', 0),
    ('notes.archive', 'Notizen archivieren', 'Notizen', 'Notizen archivieren.', 0),
    ('links.view', 'Links ansehen', 'Links', 'Geteilte Links öffnen.', 0),
    ('links.create', 'Links erstellen', 'Links', 'Neue Links teilen.', 0),
    ('links.edit', 'Links bearbeiten', 'Links', 'Vorhandene Links ändern.', 0),
    ('links.archive', 'Links archivieren', 'Links', 'Links archivieren.', 0),
    ('twitch.view', 'Twitch-Zentrale ansehen', 'Twitch', 'Status und Daten der Twitch-Zentrale lesen.', 0),
    ('twitch.connect', 'Twitch-Modkonto verbinden', 'Twitch', 'Das für Moderationsaktionen genutzte Twitch-Konto per OAuth verbinden.', 1),
    ('twitch.channels.select', 'Aktiven Twitch-Kanal wählen', 'Twitch', 'Den aktiven Zielkanal auswählen oder ändern.', 1),
    ('twitch.users.lookup', 'Twitch-Nutzer suchen', 'Twitch', 'Twitch-Profile laden und zwischenspeichern.', 0),
    ('twitch.moderate', 'Twitch moderieren', 'Twitch', 'Bans, Timeouts, Modstatus, Shield Mode, Chat und Begriffe verwalten.', 1),
    ('twitch.sync', 'Twitch-Daten synchronisieren', 'Twitch', 'Moderatoren und Bans von Twitch abrufen.', 1),
    ('twitch_users.view', 'Twitch-Nutzer ansehen', 'Twitch', 'Geladene Twitch-Profile und ihren Verlauf ansehen.', 0),
    ('bansync.view', 'BanSync ansehen', 'BanSync', 'Kanäle, Aufträge und Ergebnisse lesen.', 1),
    ('bansync.configure', 'BanSync konfigurieren', 'BanSync', 'Zielkanäle hinzufügen, schalten und prüfen.', 1),
    ('bansync.execute', 'BanSync ausführen', 'BanSync', 'Kanalübergreifende Bans und Unbans starten.', 1),
    ('cases.view', 'Moderationsfälle ansehen', 'Moderation', 'Moderationsfälle und Maßnahmen lesen.', 1),
    ('cases.create', 'Moderationsfälle erstellen', 'Moderation', 'Neue Moderationsfälle eröffnen.', 1),
    ('cases.edit', 'Moderationsfälle bearbeiten', 'Moderation', 'Status, Zuordnung und Inhalt bestehender Fälle ändern.', 1),
    ('discord.view', 'Discord Studio ansehen', 'Discord', 'Discord Studio, Vorlagen und Zustellungen öffnen.', 0),
    ('discord.send', 'Discord-Nachrichten senden', 'Discord', 'Live-Nachrichten und Changelogs an Discord senden.', 1),
    ('discord.templates.manage', 'Discord-Vorlagen verwalten', 'Discord', 'Embed-Vorlagen erstellen, bearbeiten und löschen.', 0),
    ('users.view', 'Benutzer ansehen', 'Benutzer', 'Panel-Benutzer und ihren Status anzeigen.', 1),
    ('users.create', 'Benutzer erstellen', 'Benutzer', 'Neue lokale Panel-Zugänge anlegen.', 1),
    ('users.edit', 'Benutzer bearbeiten', 'Benutzer', 'Namen, E-Mail und Kontostatus anderer Benutzer ändern.', 1),
    ('users.disable', 'Benutzer sperren', 'Benutzer', 'Bestehende Panel-Zugänge deaktivieren.', 1),
    ('users.password.reset', 'Benutzerpasswörter ändern', 'Benutzer', 'Passwörter anderer Panel-Benutzer zurücksetzen.', 1),
    ('roles.view', 'Rollen und Rechte ansehen', 'Rollen', 'Rollen sowie ihre Berechtigungen anzeigen.', 1),
    ('roles.create', 'Rollen erstellen', 'Rollen', 'Neue benutzerdefinierte Rollen anlegen.', 1),
    ('roles.edit', 'Rollen bearbeiten', 'Rollen', 'Namen und Einzelrechte bestehender Rollen ändern.', 1),
    ('roles.delete', 'Rollen löschen', 'Rollen', 'Nicht geschützte und unbenutzte Rollen löschen.', 1),
    ('roles.assign', 'Rollen zuweisen', 'Rollen', 'Benutzern eine andere Rolle zuweisen.', 1),
    ('design.view', 'Design-Editor ansehen', 'Design', 'Branding- und Seiteneinstellungen anzeigen.', 0),
    ('settings.view', 'Einstellungen ansehen', 'Einstellungen', 'Die zentrale Einstellungsseite öffnen.', 1),
    ('settings.general.manage', 'Allgemeine Einstellungen ändern', 'Einstellungen', 'App-Name, App-URL und Routing ändern.', 1),
    ('authentication.configure', 'Login-Schutz konfigurieren', 'Authentifizierung', 'reCAPTCHA und externe Loginanbieter konfigurieren.', 1),
    ('smtp.configure', 'SMTP konfigurieren', 'E-Mail', 'SMTP-Verbindung ändern und testen.', 1),
    ('github.configure', 'GitHub-Verbindung konfigurieren', 'GitHub', 'Repository, Token und Release-Prüfung verwalten.', 1),
    ('modules.view', 'Module ansehen', 'Module', 'Installierte Module und ihren Status anzeigen.', 1),
    ('modules.configure', 'Module konfigurieren', 'Module', 'Module aktivieren, deaktivieren und konfigurieren.', 1),
    ('modules.install', 'Module installieren', 'Module', 'Neue oder aktualisierte Modulpakete hochladen.', 1),
    ('modules.remove', 'Module entfernen', 'Module', 'Nicht geschützte Zusatzmodule entfernen.', 1),
    ('updates.view', 'Updates ansehen', 'Updates', 'Update-Status und Versionsverlauf anzeigen.', 1),
    ('updates.install', 'Updates installieren', 'Updates', 'ZIP- oder GitHub-Updates installieren.', 1),
    ('updates.rollback', 'Updates zurückrollen', 'Updates', 'Eine Installation auf das vorherige Backup zurücksetzen.', 1),
    ('migrations.view', 'Migrationen ansehen', 'Migrationen', 'Offene und ausgeführte Migrationen anzeigen.', 1),
    ('migrations.run', 'Migrationen ausführen', 'Migrationen', 'Einzelne oder alle offenen Migrationen starten.', 1),
    ('backups.view', 'Backups ansehen', 'Backups', 'Backup-Verlauf und Status anzeigen.', 1),
    ('backups.create', 'Backups erstellen', 'Backups', 'Manuelle Datenbank- und Dateibackups erzeugen.', 1),
    ('backups.download', 'Backups herunterladen', 'Backups', 'Gesicherte Backup-Pakete herunterladen.', 1),
    ('backups.delete', 'Backups löschen', 'Backups', 'Nicht mehr benötigte Sicherungen entfernen.', 1),
    ('security.ip.manage', 'IP-Sperren verwalten', 'Sicherheit', 'IP-Adressen sperren und Sperren aufheben.', 1),
    ('security.sessions.manage', 'Sitzungen verwalten', 'Sicherheit', 'Aktive Benutzersitzungen beenden.', 1);

INSERT IGNORE INTO permissions (permission_key, name, category, description, `sensitive`)
SELECT
    CONCAT('module.', module_key, '.view'),
    CONCAT(name, ' ansehen'),
    'Zusatzmodule',
    CONCAT('Das Zusatzmodul „', name, '“ im Panel öffnen.'),
    0
FROM modules
WHERE source = 'custom' AND module_key REGEXP '^[a-z][a-z0-9-]{2,49}$';

INSERT IGNORE INTO permissions (permission_key, name, category, description, `sensitive`)
SELECT
    CONCAT('module.', module_key, '.use'),
    CONCAT(name, ' verwenden'),
    'Zusatzmodule',
    CONCAT('Aktionen des Zusatzmoduls „', name, '“ ausführen.'),
    1
FROM modules
WHERE source = 'custom' AND module_key REGEXP '^[a-z][a-z0-9-]{2,49}$';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'dashboard.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'news.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'ideas.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'notes.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'links.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'bansync.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch_users.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'cases.view' FROM roles WHERE is_owner = 0;
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'notes.private.view' FROM roles WHERE role_key = 'admin';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT roles.role_key, CONCAT('module.', modules.module_key, '.view')
FROM roles CROSS JOIN modules
WHERE roles.is_owner = 0
  AND modules.source = 'custom'
  AND modules.module_key REGEXP '^[a-z][a-z0-9-]{2,49}$';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT roles.role_key, CONCAT('module.', modules.module_key, '.use')
FROM roles CROSS JOIN modules
WHERE roles.is_owner = 0
  AND modules.source = 'custom'
  AND modules.module_key REGEXP '^[a-z][a-z0-9-]{2,49}$';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'news.create' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'news.edit' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'ideas.create' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'ideas.edit' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'notes.create' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'notes.edit' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'links.create' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'links.edit' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'cases.create' FROM role_permissions WHERE permission_key = 'content.write';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'cases.edit' FROM role_permissions WHERE permission_key = 'content.write';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'news.archive' FROM role_permissions WHERE permission_key = 'content.archive';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'ideas.archive' FROM role_permissions WHERE permission_key = 'content.archive';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'notes.archive' FROM role_permissions WHERE permission_key = 'content.archive';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'links.archive' FROM role_permissions WHERE permission_key = 'content.archive';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.users.lookup' FROM role_permissions WHERE permission_key = 'twitch.use';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.moderate' FROM role_permissions WHERE permission_key = 'twitch.use';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.sync' FROM role_permissions WHERE permission_key = 'twitch.use';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'bansync.execute' FROM role_permissions WHERE permission_key = 'twitch.use';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.connect' FROM role_permissions WHERE permission_key = 'twitch.configure';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'twitch.channels.select' FROM role_permissions WHERE permission_key = 'twitch.configure';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'bansync.configure' FROM role_permissions WHERE permission_key = 'twitch.configure';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'discord.view' FROM role_permissions WHERE permission_key IN ('discord.studio', 'discord.configure');
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'discord.send' FROM role_permissions WHERE permission_key = 'discord.studio';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'discord.templates.manage' FROM role_permissions WHERE permission_key = 'discord.studio';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'users.view' FROM role_permissions WHERE permission_key = 'team.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'users.create' FROM role_permissions WHERE permission_key = 'team.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'users.edit' FROM role_permissions WHERE permission_key = 'team.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'users.disable' FROM role_permissions WHERE permission_key = 'team.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'users.password.reset' FROM role_permissions WHERE permission_key = 'team.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'roles.view' FROM role_permissions WHERE permission_key = 'roles.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'roles.create' FROM role_permissions WHERE permission_key = 'roles.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'roles.edit' FROM role_permissions WHERE permission_key = 'roles.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'roles.delete' FROM role_permissions WHERE permission_key = 'roles.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'roles.assign' FROM role_permissions WHERE permission_key = 'roles.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'settings.view' FROM role_permissions
WHERE permission_key IN ('settings.manage', 'updates.manage', 'migrations.manage');
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'settings.general.manage' FROM role_permissions WHERE permission_key = 'settings.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'authentication.configure' FROM role_permissions WHERE permission_key = 'settings.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'smtp.configure' FROM role_permissions WHERE permission_key = 'settings.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'design.view' FROM role_permissions WHERE permission_key = 'design.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'modules.view' FROM role_permissions WHERE permission_key = 'modules.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'modules.configure' FROM role_permissions WHERE permission_key = 'modules.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'modules.install' FROM role_permissions WHERE permission_key = 'modules.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'modules.remove' FROM role_permissions WHERE permission_key = 'modules.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'updates.view' FROM role_permissions WHERE permission_key = 'updates.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'updates.install' FROM role_permissions WHERE permission_key = 'updates.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'updates.rollback' FROM role_permissions WHERE permission_key = 'updates.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'github.configure' FROM role_permissions WHERE permission_key = 'updates.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'migrations.view' FROM role_permissions WHERE permission_key = 'migrations.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'migrations.run' FROM role_permissions WHERE permission_key = 'migrations.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'backups.view' FROM role_permissions WHERE permission_key IN ('backups.manage', 'backups.restore');
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'backups.create' FROM role_permissions WHERE permission_key = 'backups.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'backups.download' FROM role_permissions WHERE permission_key = 'backups.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'backups.delete' FROM role_permissions WHERE permission_key = 'backups.manage';

INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'security.ip.manage' FROM role_permissions WHERE permission_key = 'security.manage';
INSERT IGNORE INTO role_permissions (role_key, permission_key)
SELECT role_key, 'security.sessions.manage' FROM role_permissions WHERE permission_key = 'security.manage';

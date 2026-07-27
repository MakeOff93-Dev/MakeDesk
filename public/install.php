<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

$root = dirname(__DIR__);
$assetPrefix = defined('MODDESK_ASSET_PREFIX') ? (string) constant('MODDESK_ASSET_PREFIX') : '';
require_once $root . '/app/Env.php';
Env::load($root . '/.env');
require_once $root . '/app/SchemaMigrator.php';
require_once $root . '/app/Crypto.php';
require_once $root . '/app/AppSettings.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name('moddesk_installer');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function installer_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function installer_default_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $directory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php')));
    $directory = $directory === '/' ? '' : rtrim($directory, '/');
    return $scheme . '://' . $host . $directory;
}

function installer_env_value(string $value): string
{
    if (preg_match('/[\r\n]/', $value)) {
        throw new InvalidArgumentException('Konfigurationswerte dürfen keine Zeilenumbrüche enthalten.');
    }
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function installer_write_env(string $path, array $values): void
{
    $lines = [
        '# Erstellt vom Twitch ModDesk Web-Installer am ' . gmdate('Y-m-d H:i:s') . ' UTC',
    ];
    foreach ($values as $key => $value) {
        if (!preg_match('/^[A-Z0-9_]+$/', (string) $key)) {
            continue;
        }
        $lines[] = $key . '=' . installer_env_value((string) $value);
    }
    $content = implode(PHP_EOL, $lines) . PHP_EOL;
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('.env konnte nicht geschrieben werden. Prüfe die Schreibrechte des Projektordners.');
    }
}

function installer_existing_installation(): bool
{
    if (Env::bool('APP_INSTALLED', false)) {
        return true;
    }
    $installedFlag = Env::get('APP_INSTALLED');
    $existingKey = (string) Env::get('APP_KEY', '');
    if (
        $installedFlag === null
        && is_file(dirname(__DIR__) . '/.env')
        && strlen($existingKey) >= 32
        && !str_contains(strtolower($existingKey), 'change-this')
    ) {
        return true;
    }
    $database = trim((string) Env::get('DB_DATABASE', ''));
    if ($database === '') {
        return false;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) Env::get('DB_HOST', '127.0.0.1'),
            (int) Env::get('DB_PORT', 3306),
            $database,
        );
        $pdo = new PDO($dsn, (string) Env::get('DB_USERNAME', ''), (string) Env::get('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

$checks = [
    ['label' => 'PHP 8.2 oder neuer', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'optional' => false],
    ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'optional' => false],
    ['label' => 'cURL', 'ok' => extension_loaded('curl'), 'optional' => false],
    ['label' => 'mbstring', 'ok' => extension_loaded('mbstring'), 'optional' => false],
    ['label' => 'OpenSSL', 'ok' => extension_loaded('openssl'), 'optional' => false],
    ['label' => 'Projektordner beschreibbar', 'ok' => is_writable($root), 'optional' => false],
    ['label' => 'ZIP-Updater (optional)', 'ok' => class_exists(ZipArchive::class), 'optional' => true],
];
$systemReady = true;
foreach ($checks as $check) {
    if (!$check['optional'] && !$check['ok']) {
        $systemReady = false;
    }
}
$locked = installer_existing_installation();
$error = null;
$installed = false;
$appUrlDefault = (string) Env::get('APP_URL', installer_default_url());
$csrf = (string) ($_SESSION['installer_csrf'] ?? '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['installer_csrf'] = $csrf;
}

$defaults = [
    'app_name' => (string) Env::get('APP_NAME', 'Twitch ModDesk'),
    'app_url' => $appUrlDefault,
    'timezone' => (string) Env::get('APP_TIMEZONE', 'Europe/Berlin'),
    'db_host' => (string) Env::get('DB_HOST', '127.0.0.1'),
    'db_port' => (string) Env::get('DB_PORT', '3306'),
    'db_database' => (string) Env::get('DB_DATABASE', 'twitch_moddesk'),
    'db_username' => (string) Env::get('DB_USERNAME', 'root'),
    'admin_username' => 'admin',
    'admin_display_name' => 'Administrator',
    'twitch_redirect_uri' => rtrim($appUrlDefault, '/') . '/?page=twitch-callback',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_auth' => 'login',
    'smtp_from_name' => 'Twitch ModDesk',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$locked) {
    foreach ($defaults as $key => $value) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $defaults[$key] = trim($_POST[$key]);
        }
    }

    try {
        if (!$systemReady) {
            throw new RuntimeException('Die Systemvoraussetzungen sind noch nicht erfüllt.');
        }
        $providedCsrf = (string) ($_POST['_csrf'] ?? '');
        if ($providedCsrf === '' || !hash_equals($csrf, $providedCsrf)) {
            throw new RuntimeException('Das Installer-Formular ist abgelaufen. Bitte lade die Seite neu.');
        }

        $appName = mb_substr(trim((string) ($_POST['app_name'] ?? 'Twitch ModDesk')), 0, 100);
        $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $timezone = trim((string) ($_POST['timezone'] ?? 'Europe/Berlin'));
        $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $dbPort = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim((string) ($_POST['db_database'] ?? 'twitch_moddesk'));
        $dbUser = trim((string) ($_POST['db_username'] ?? 'root'));
        $dbPassword = (string) ($_POST['db_password'] ?? '');
        $createDatabase = isset($_POST['create_database']);
        $adminUsername = strtolower(trim((string) ($_POST['admin_username'] ?? 'admin')));
        $adminDisplayName = mb_substr(trim((string) ($_POST['admin_display_name'] ?? 'Administrator')), 0, 100);
        $adminEmail = trim((string) ($_POST['admin_email'] ?? '')) ?: null;
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminPasswordRepeat = (string) ($_POST['admin_password_repeat'] ?? '');

        if ($appName === '' || !filter_var($appUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('App-Name oder App-URL ist ungültig.');
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Die Zeitzone ist ungültig.');
        }
        if ($dbHost === '' || $dbPort < 1 || $dbPort > 65535 || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $dbName) || $dbUser === '') {
            throw new InvalidArgumentException('Die MySQL-Verbindungsdaten sind ungültig.');
        }
        if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $adminUsername) || $adminDisplayName === '') {
            throw new InvalidArgumentException('Der Owner-Benutzername oder Anzeigename ist ungültig.');
        }
        if ($adminEmail !== null && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Die Owner-E-Mail-Adresse ist ungültig.');
        }
        if (strlen($adminPassword) < 12 || !hash_equals($adminPassword, $adminPasswordRepeat)) {
            throw new InvalidArgumentException('Das Owner-Passwort muss mindestens 12 Zeichen haben und zweimal identisch sein.');
        }

        $twitchClientId = trim((string) ($_POST['twitch_client_id'] ?? ''));
        $twitchClientSecret = trim((string) ($_POST['twitch_client_secret'] ?? ''));
        $twitchRedirectUri = trim((string) ($_POST['twitch_redirect_uri'] ?? ''));
        if (($twitchClientId !== '' || $twitchClientSecret !== '') && ($twitchClientId === '' || $twitchClientSecret === '')) {
            throw new InvalidArgumentException('Für Twitch werden Client-ID und Client-Secret gemeinsam benötigt.');
        }
        if ($twitchClientId !== '' && !preg_match('/^[a-zA-Z0-9_-]{5,255}$/', $twitchClientId)) {
            throw new InvalidArgumentException('Die Twitch Client-ID ist ungültig.');
        }
        if ($twitchRedirectUri !== '' && (!filter_var($twitchRedirectUri, FILTER_VALIDATE_URL) || !in_array(parse_url($twitchRedirectUri, PHP_URL_SCHEME), ['http', 'https'], true))) {
            throw new InvalidArgumentException('Die Twitch Redirect-URI ist ungültig.');
        }

        $discordToken = trim((string) ($_POST['discord_bot_token'] ?? ''));
        $discordApplicationId = trim((string) ($_POST['discord_application_id'] ?? ''));
        $discordGuildId = trim((string) ($_POST['discord_guild_id'] ?? ''));
        $discordChannelId = trim((string) ($_POST['discord_channel_id'] ?? ''));
        foreach ([$discordApplicationId, $discordGuildId, $discordChannelId] as $snowflake) {
            if ($snowflake !== '' && !preg_match('/^[0-9]{15,22}$/', $snowflake)) {
                throw new InvalidArgumentException('Eine Discord-ID ist ungültig. Bitte kopiere die numerische ID im Entwicklermodus.');
            }
        }
        if (($discordGuildId === '') !== ($discordChannelId === '')) {
            throw new InvalidArgumentException('Discord Server-ID und Channel-ID müssen gemeinsam eingetragen werden.');
        }

        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587)));
        $smtpEncryption = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls';
        $smtpAuth = in_array($_POST['smtp_auth'] ?? '', ['login', 'plain', 'none'], true) ? (string) $_POST['smtp_auth'] : 'login';
        $smtpUsername = trim((string) ($_POST['smtp_username'] ?? ''));
        $smtpPassword = (string) ($_POST['smtp_password'] ?? '');
        $smtpFromEmail = trim((string) ($_POST['smtp_from_email'] ?? ''));
        $smtpFromName = mb_substr(trim((string) ($_POST['smtp_from_name'] ?? 'Twitch ModDesk')), 0, 100);
        if ($smtpHost !== '' && (!filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL) || str_contains($smtpHost, '://') || preg_match('/[\s\r\n]/', $smtpHost))) {
            throw new InvalidArgumentException('SMTP-Hostname oder Absenderadresse ist ungültig.');
        }
        if ($smtpHost !== '' && $smtpAuth !== 'none' && ($smtpUsername === '' || $smtpPassword === '')) {
            throw new InvalidArgumentException('Für die SMTP-Anmeldung fehlen Benutzername oder Passwort.');
        }

        $appKey = base64_encode(random_bytes(48));
        $envValues = [
            'APP_NAME' => $appName,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_INSTALLED' => 'false',
            'APP_URL' => $appUrl,
            'APP_KEY' => $appKey,
            'APP_TIMEZONE' => $timezone,
            'SESSION_SECURE' => parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 'true' : 'false',
            'DB_HOST' => $dbHost,
            'DB_PORT' => (string) $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPassword,
            'TWITCH_CLIENT_ID' => '',
            'TWITCH_CLIENT_SECRET' => '',
            'TWITCH_REDIRECT_URI' => '',
        ];
        installer_write_env($root . '/.env', $envValues);

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
        $serverPdo = new PDO($serverDsn, $dbUser, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($createDatabase) {
            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        $pdo = new PDO($serverDsn . ';dbname=' . $dbName, $dbUser, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        SchemaMigrator::executeFile($pdo, $root . '/database/schema.sql');
        SchemaMigrator::migrate($pdo, $root . '/database/migrations');
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            throw new RuntimeException('In dieser Datenbank existiert bereits ein ModDesk-Owner. Der Installer wurde aus Sicherheitsgründen gestoppt.');
        }

        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            "INSERT INTO users (username, display_name, email, password_hash, role, active)
             VALUES (:username, :display_name, :email, :password_hash, 'owner', 1)"
        );
        $statement->execute([
            'username' => $adminUsername,
            'display_name' => $adminDisplayName,
            'email' => $adminEmail,
            'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);
        $ownerId = (int) $pdo->lastInsertId();
        $appSettings = new AppSettings($pdo, new Crypto($appKey));

        $appSettings->set('app_name', $appName, false, $ownerId);
        $appSettings->set('app_url', $appUrl, false, $ownerId);
        $appSettings->set('twitch_client_id', $twitchClientId, false, $ownerId);
        $appSettings->setSecretWhenProvided('twitch_client_secret', $twitchClientSecret, $ownerId);
        $appSettings->set('twitch_redirect_uri', $twitchRedirectUri, false, $ownerId);
        $appSettings->set('discord_enabled', $discordToken !== '' ? 'true' : 'false', false, $ownerId);
        $appSettings->set('discord_application_id', $discordApplicationId, false, $ownerId);
        $appSettings->setSecretWhenProvided('discord_bot_token', $discordToken, $ownerId);
        if ($discordGuildId !== '' && $discordChannelId !== '') {
            $statement = $pdo->prepare(
                "UPDATE discord_notification_routes SET guild_id = :guild_id, channel_id = :channel_id,
                 enabled = 1, updated_by = :updated_by WHERE event_key = 'ban_sync'"
            );
            $statement->execute(['guild_id' => $discordGuildId, 'channel_id' => $discordChannelId, 'updated_by' => $ownerId]);
            $statement = $pdo->prepare(
                'INSERT INTO discord_servers (guild_id, name, enabled, created_by, updated_by)
                 VALUES (:guild_id, :name, 1, :created_by, :updated_by)
                 ON DUPLICATE KEY UPDATE enabled = 1, updated_by = VALUES(updated_by)'
            );
            $statement->execute([
                'guild_id' => $discordGuildId,
                'name' => 'Discord-Server ' . $discordGuildId,
                'created_by' => $ownerId,
                'updated_by' => $ownerId,
            ]);
            $serverIdStatement = $pdo->prepare('SELECT id FROM discord_servers WHERE guild_id = :guild_id');
            $serverIdStatement->execute(['guild_id' => $discordGuildId]);
            $discordServerId = (int) $serverIdStatement->fetchColumn();
            $statement = $pdo->prepare(
                'INSERT INTO discord_channels (server_id, channel_id, name, enabled, created_by, updated_by)
                 VALUES (:server_id, :channel_id, :name, 1, :created_by, :updated_by)
                 ON DUPLICATE KEY UPDATE server_id = VALUES(server_id), enabled = 1, updated_by = VALUES(updated_by)'
            );
            $statement->execute([
                'server_id' => $discordServerId,
                'channel_id' => $discordChannelId,
                'name' => 'Channel ' . $discordChannelId,
                'created_by' => $ownerId,
                'updated_by' => $ownerId,
            ]);
            $channelIdStatement = $pdo->prepare('SELECT id FROM discord_channels WHERE channel_id = :channel_id');
            $channelIdStatement->execute(['channel_id' => $discordChannelId]);
            $discordChannelRecordId = (int) $channelIdStatement->fetchColumn();
            $pdo->prepare(
                "INSERT IGNORE INTO discord_channel_routes (channel_id, event_key, enabled)
                 VALUES (:channel_id, 'ban_sync', 1)"
            )->execute(['channel_id' => $discordChannelRecordId]);
        }
        $appSettings->set('smtp_enabled', $smtpHost !== '' ? 'true' : 'false', false, $ownerId);
        $appSettings->set('smtp_host', $smtpHost, false, $ownerId);
        $appSettings->set('smtp_port', (string) $smtpPort, false, $ownerId);
        $appSettings->set('smtp_encryption', $smtpEncryption, false, $ownerId);
        $appSettings->set('smtp_auth', $smtpAuth, false, $ownerId);
        $appSettings->set('smtp_username', $smtpUsername, false, $ownerId);
        $appSettings->setSecretWhenProvided('smtp_password', $smtpPassword, $ownerId);
        $appSettings->set('smtp_from_email', $smtpFromEmail, false, $ownerId);
        $appSettings->set('smtp_from_name', $smtpFromName, false, $ownerId);
        $pdo->commit();

        $envValues['APP_INSTALLED'] = 'true';
        installer_write_env($root . '/.env', $envValues);
        unset($_SESSION['installer_csrf']);
        $installed = true;
        $locked = true;
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof PDOException
            ? 'MySQL-Fehler: ' . $exception->getMessage()
            : $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0b14">
    <title>ModDesk installieren</title>
    <link rel="stylesheet" href="<?= installer_e($assetPrefix) ?>assets/app.css?v=1.4">
</head>
<body class="installer-shell">
<main class="installer-main">
    <header class="installer-head">
        <a class="brand" href="install.php"><span class="brand-mark">M</span><span><strong>MOD</strong><small>// INSTALLER</small></span></a>
        <span class="badge">Version 1.4.0</span>
    </header>

    <?php if ($installed): ?>
        <section class="card installer-success">
            <span class="empty-icon">✓</span>
            <p class="eyebrow">INSTALLATION ABGESCHLOSSEN</p>
            <h1>Dein ModDesk ist bereit.</h1>
            <p>Datenbank, Owner-Zugang und die eingetragenen Integrationen wurden eingerichtet. Der Web-Installer ist jetzt automatisch gesperrt.</p>
            <a class="button button-primary button-wide" href="./?page=login">Zum ModDesk-Login →</a>
        </section>
    <?php elseif ($locked): ?>
        <section class="card installer-success">
            <span class="empty-icon">⌁</span>
            <p class="eyebrow">INSTALLER GESPERRT</p>
            <h1>ModDesk ist bereits installiert.</h1>
            <p>Zum Schutz deiner Daten kann der Installer nach dem Anlegen eines Owner-Zugangs nicht erneut ausgeführt werden.</p>
            <a class="button button-primary button-wide" href="./?page=login">Zum Login →</a>
        </section>
    <?php else: ?>
        <section class="installer-title">
            <p class="eyebrow">SCHRITT 1 VON 1</p>
            <h1>ModDesk auf diesem Laptop einrichten</h1>
            <p>Der Installer legt MySQL-Tabellen und Owner-Zugang an und speichert optionale Twitch-, Discord- und SMTP-Daten verschlüsselt.</p>
        </section>

        <?php if ($error): ?><div class="alert alert-danger"><span>!</span><p><?= installer_e($error) ?></p></div><?php endif; ?>

        <section class="card installer-checks">
            <div class="section-head"><div><p class="eyebrow">SYSTEMCHECK</p><h3>Voraussetzungen</h3></div><span class="job-status <?= $systemReady ? 'status-completed' : 'status-failed' ?>"><?= $systemReady ? 'Bereit' : 'Fehlt etwas' ?></span></div>
            <div class="check-grid">
                <?php foreach ($checks as $check): ?><div class="check-item <?= $check['ok'] ? 'ok' : ($check['optional'] ? 'optional' : 'failed') ?>"><span><?= $check['ok'] ? '✓' : ($check['optional'] ? '~' : '!') ?></span><?= installer_e($check['label']) ?></div><?php endforeach; ?>
            </div>
        </section>

        <form method="post" class="installer-form">
            <input type="hidden" name="_csrf" value="<?= installer_e($csrf) ?>">

            <section class="card installer-section">
                <div class="section-head"><div><p class="eyebrow">ANWENDUNG</p><h2>Adresse und Datenbank</h2></div><span class="step-number">01</span></div>
                <div class="form-grid">
                    <label><span>App-Name *</span><input type="text" name="app_name" required maxlength="100" value="<?= installer_e($defaults['app_name']) ?>"></label>
                    <label><span>App-URL *</span><input type="url" name="app_url" required value="<?= installer_e($defaults['app_url']) ?>"></label>
                    <label><span>Zeitzone *</span><input type="text" name="timezone" required value="<?= installer_e($defaults['timezone']) ?>"></label>
                    <label><span>MySQL-Host *</span><input type="text" name="db_host" required value="<?= installer_e($defaults['db_host']) ?>"></label>
                    <label><span>MySQL-Port *</span><input type="number" name="db_port" min="1" max="65535" required value="<?= installer_e($defaults['db_port']) ?>"></label>
                    <label><span>Datenbankname *</span><input type="text" name="db_database" required value="<?= installer_e($defaults['db_database']) ?>"></label>
                    <label><span>MySQL-Benutzer *</span><input type="text" name="db_username" required value="<?= installer_e($defaults['db_username']) ?>"></label>
                    <label><span>MySQL-Passwort</span><input type="password" name="db_password" autocomplete="new-password"></label>
                    <label class="check-label span-2"><input type="checkbox" name="create_database" value="1" checked><span>Datenbank automatisch anlegen, falls sie noch nicht existiert</span></label>
                </div>
            </section>

            <section class="card installer-section">
                <div class="section-head"><div><p class="eyebrow">OWNER-ZUGANG</p><h2>Erstes Administratorkonto</h2></div><span class="step-number">02</span></div>
                <div class="form-grid">
                    <label><span>Benutzername *</span><input type="text" name="admin_username" required minlength="3" maxlength="50" value="<?= installer_e($defaults['admin_username']) ?>"></label>
                    <label><span>Anzeigename *</span><input type="text" name="admin_display_name" required maxlength="100" value="<?= installer_e($defaults['admin_display_name']) ?>"></label>
                    <label class="span-2"><span>E-Mail (optional)</span><input type="email" name="admin_email" autocomplete="email"></label>
                    <label><span>Passwort *</span><input type="password" name="admin_password" required minlength="12" autocomplete="new-password"></label>
                    <label><span>Passwort wiederholen *</span><input type="password" name="admin_password_repeat" required minlength="12" autocomplete="new-password"></label>
                </div>
            </section>

            <section class="card installer-section">
                <div class="section-head"><div><p class="eyebrow">TWITCH</p><h2>API-Zugang (optional)</h2></div><span class="step-number">03</span></div>
                <p class="section-copy">Kann auch später unter „Einstellungen“ hinterlegt werden.</p>
                <div class="form-grid">
                    <label><span>Client-ID</span><input type="text" name="twitch_client_id" autocomplete="off"></label>
                    <label><span>Client-Secret</span><input type="password" name="twitch_client_secret" autocomplete="new-password"></label>
                    <label class="span-2"><span>OAuth Redirect-URI</span><input type="url" name="twitch_redirect_uri" value="<?= installer_e($defaults['twitch_redirect_uri']) ?>"></label>
                </div>
            </section>

            <section class="card installer-section">
                <div class="section-head"><div><p class="eyebrow">DISCORD</p><h2>Bot und erster Channel (optional)</h2></div><span class="step-number">04</span></div>
                <p class="section-copy">Der erste Channel wird für BanSync aktiviert. Weitere Ereignisse und Channels stellst du später im Panel ein.</p>
                <div class="form-grid">
                    <label><span>Application-ID</span><input type="text" name="discord_application_id" inputmode="numeric" autocomplete="off"></label>
                    <label><span>Bot-Token</span><input type="password" name="discord_bot_token" autocomplete="new-password"></label>
                    <label><span>Discord Server-ID</span><input type="text" name="discord_guild_id" inputmode="numeric" autocomplete="off"></label>
                    <label><span>Discord Channel-ID</span><input type="text" name="discord_channel_id" inputmode="numeric" autocomplete="off"></label>
                </div>
            </section>

            <section class="card installer-section">
                <div class="section-head"><div><p class="eyebrow">E-MAIL</p><h2>SMTP-Server (optional)</h2></div><span class="step-number">05</span></div>
                <div class="form-grid">
                    <label><span>SMTP-Host</span><input type="text" name="smtp_host" placeholder="smtp.example.de"></label>
                    <label><span>Port</span><input type="number" name="smtp_port" min="1" max="65535" value="<?= installer_e($defaults['smtp_port']) ?>"></label>
                    <label><span>Verschlüsselung</span><select name="smtp_encryption"><option value="tls" selected>STARTTLS</option><option value="ssl">SSL/TLS direkt</option><option value="none">Keine</option></select></label>
                    <label><span>Anmeldung</span><select name="smtp_auth"><option value="login" selected>AUTH LOGIN</option><option value="plain">AUTH PLAIN</option><option value="none">Keine Anmeldung</option></select></label>
                    <label><span>SMTP-Benutzer</span><input type="text" name="smtp_username" autocomplete="off"></label>
                    <label><span>SMTP-Passwort</span><input type="password" name="smtp_password" autocomplete="new-password"></label>
                    <label><span>Absenderadresse</span><input type="email" name="smtp_from_email" placeholder="moddesk@example.de"></label>
                    <label><span>Absendername</span><input type="text" name="smtp_from_name" maxlength="100" value="<?= installer_e($defaults['smtp_from_name']) ?>"></label>
                </div>
            </section>

            <section class="card installer-submit">
                <div><strong>Bereit für die Installation?</strong><p>Geheime Werte werden nicht im Quellcode gespeichert.</p></div>
                <button class="button button-primary" type="submit" <?= !$systemReady ? 'disabled' : '' ?> data-confirm="ModDesk jetzt installieren und die Datenbank einrichten?">ModDesk installieren →</button>
            </section>
        </form>
    <?php endif; ?>
</main>
<script src="<?= installer_e($assetPrefix) ?>assets/app.js?v=1.4" defer></script>
</body>
</html>

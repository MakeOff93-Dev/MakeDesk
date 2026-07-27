<?php

declare(strict_types=1);

require_once __DIR__ . '/Env.php';
Env::load(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabaseSessionHandler.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/AppSettings.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/TwoFactorManager.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ProfileManager.php';
require_once __DIR__ . '/RecaptchaVerifier.php';
require_once __DIR__ . '/TwitchLogin.php';
require_once __DIR__ . '/TwitchClient.php';
require_once __DIR__ . '/DiscordClient.php';
require_once __DIR__ . '/SmtpMailer.php';
require_once __DIR__ . '/BrandingManager.php';
require_once __DIR__ . '/UpdateManager.php';
require_once __DIR__ . '/BackupManager.php';
require_once __DIR__ . '/SchemaMigrator.php';
require_once __DIR__ . '/ModuleManager.php';
require_once __DIR__ . '/GitHubReleaseClient.php';
require_once __DIR__ . '/Support.php';

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Europe/Berlin'));

$pdo = Database::connection();
$crypto = new Crypto((string) Env::get('APP_KEY', ''));
$settings = new AppSettings($pdo, $crypto);

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('twitch_moddesk_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => Env::bool('SESSION_SECURE', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_set_save_handler(new DatabaseSessionHandler($pdo), true);
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    $recaptchaAssetsAllowed = $settings->bool('recaptcha_enabled', false)
        && $settings->hasValue('recaptcha_site_key')
        && $settings->hasValue('recaptcha_secret_key');
    $scriptSources = "'self'";
    $frameSources = "'none'";
    $connectSources = "'self'";
    if ($recaptchaAssetsAllowed) {
        $scriptSources .= ' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/';
        $frameSources = 'https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/';
        $connectSources .= ' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/';
    }
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; "
        . "style-src 'self' 'unsafe-inline'; script-src " . $scriptSources . '; '
        . 'connect-src ' . $connectSources . '; frame-src ' . $frameSources . '; '
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'"
    );
    header('Cache-Control: no-store, private');
    if (Env::bool('SESSION_SECURE', false)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$security = new SecurityManager($pdo, dirname(__DIR__));
$twoFactor = new TwoFactorManager($pdo, $crypto, $security);
$auth = new Auth($pdo, $security, $twoFactor);
$profiles = new ProfileManager($pdo);
$recaptcha = new RecaptchaVerifier($settings);
$twitchLogin = new TwitchLogin($pdo, $settings);
$twitch = new TwitchClient(
    $pdo,
    $crypto,
    (string) $settings->get('twitch_client_id', Env::get('TWITCH_CLIENT_ID', '')),
    (string) $settings->get('twitch_client_secret', Env::get('TWITCH_CLIENT_SECRET', '')),
    (string) $settings->get('twitch_redirect_uri', Env::get('TWITCH_REDIRECT_URI', '')),
);
$discord = new DiscordClient($pdo, $settings);
$smtpMailer = new SmtpMailer($pdo, $settings);
$branding = new BrandingManager($pdo, $settings);
$modules = new ModuleManager($pdo, $settings, dirname(__DIR__));
$githubReleases = new GitHubReleaseClient($pdo, $settings, dirname(__DIR__));
$backups = new BackupManager($pdo, dirname(__DIR__));

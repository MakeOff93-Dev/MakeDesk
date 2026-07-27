<?php
$appName = (string) settings()->get('app_name', env('APP_NAME', 'Twitch ModDesk'));
$currentPage = request_page('dashboard');
$flashes = consume_flashes();
$loggedIn = auth()->check();
$nav = $loggedIn ? navigation_items() : [];
$logo = branding()->logoMetadata();
$logoUrl = $logo !== null ? url('brand-logo', ['v' => substr((string) $logo['checksum_sha256'], 0, 16)]) : null;
$theme = theme_values();
$headerEyebrow = (string) settings()->get('header_eyebrow', 'CONTROL CENTER');
$footerText = trim((string) settings()->get('footer_text', ''));
$versionText = display_version_text();
$twitchModuleEnabled = modules()->isEnabled('twitch');
$activeTwitchChannel = $loggedIn && $twitchModuleEnabled ? twitch()->channel() : null;
$availableTwitchChannels = $loggedIn && $twitchModuleEnabled ? twitch()->availableChannels() : [];
$githubStatus = $loggedIn ? github_update_status() : null;
$currentChangelog = $loggedIn ? changelog_for_version() : '';
$changelogChannels = $loggedIn && modules()->isEnabled('discord') && auth()->can('discord.send') ? discord_managed_channels(true) : [];
$currentUserAvatarUrl = $loggedIn ? profile_avatar_url((int) auth()->user()['id']) : null;
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="<?= e($theme['background']) ?>">
    <title><?= e($title ?? 'Panel') ?> · <?= e($appName) ?></title>
    <?php if ($logoUrl): ?><link rel="icon" href="<?= e($logoUrl) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>?v=<?= e(app_version()) ?>">
    <link rel="stylesheet" href="<?= e(url('theme-css', ['v' => app_version()])) ?>">
    <script src="<?= e(asset_url('assets/app.js')) ?>?v=<?= e(app_version()) ?>" defer></script>
</head>
<body class="<?= $loggedIn ? 'app-shell' : 'guest-shell' ?>">
<?php if ($loggedIn): ?>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('dashboard')) ?>" aria-label="Dashboard">
            <?php if ($logoUrl): ?><img class="brand-logo" src="<?= e($logoUrl) ?>" alt="<?= e($appName) ?>"><?php else: ?><span class="brand-mark">M</span><?php endif; ?>
            <span><strong><?= e($appName) ?></strong><small>// DESK</small></span>
        </a>
        <nav class="nav" aria-label="Hauptnavigation">
            <?php foreach ($nav as $navPage => $item): ?>
                <a href="<?= e(url($navPage)) ?>" class="<?= $currentPage === $navPage ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <a class="sidebar-avatar-link" href="<?= e(url('profile')) ?>" aria-label="Benutzerkonto öffnen">
                <?php if ($currentUserAvatarUrl): ?><img class="avatar avatar-image" src="<?= e($currentUserAvatarUrl) ?>" alt=""><?php else: ?><span class="avatar avatar-text"><?= e(mb_strtoupper(mb_substr((string) auth()->user()['display_name'], 0, 1))) ?></span><?php endif; ?>
            </a>
            <a class="sidebar-user-copy" href="<?= e(url('profile')) ?>">
                <strong><?= e(auth()->user()['display_name']) ?></strong>
                <small><?= e(role_label((string) auth()->user()['role'])) ?></small>
            </a>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="icon-button" type="submit" title="Abmelden" aria-label="Abmelden">↪</button>
            </form>
        </div>
    </aside>
    <div class="page-wrap">
        <header class="topbar">
            <button class="menu-button" type="button" data-menu aria-controls="sidebar" aria-label="Navigation öffnen">☰</button>
            <div class="topbar-copy">
                <p class="eyebrow"><?= e($headerEyebrow) ?></p>
                <h1><?= e($title ?? '') ?></h1>
                <?php if (!empty($pageTopText)): ?><small><?= e($pageTopText) ?></small><?php endif; ?>
            </div>
            <div class="topbar-actions">
                <button class="version-pill version-button <?= !empty($githubStatus['update_available']) ? 'has-update' : '' ?>" type="button" data-version-open><?= e($versionText) ?><?= !empty($githubStatus['update_available']) ? ' · Update' : '' ?></button>
                <?php if ($twitchModuleEnabled && auth()->can('twitch.channels.select') && $availableTwitchChannels !== []): ?>
                    <form method="post" class="channel-switcher">
                        <?= csrf_field() ?><input type="hidden" name="action" value="channel-switch"><input type="hidden" name="return_page" value="<?= e($currentPage) ?>">
                        <span class="status-dot <?= twitch()->connection() ? 'online' : '' ?>"></span>
                        <select name="twitch_channel_id" aria-label="Aktiven Twitch-Kanal auswählen" data-channel-switch>
                            <?php foreach ($availableTwitchChannels as $availableChannel): ?><option value="<?= e($availableChannel['id']) ?>" <?= (string) ($activeTwitchChannel['id'] ?? '') === (string) $availableChannel['id'] ? 'selected' : '' ?>><?= e($availableChannel['display_name']) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                <?php else: ?>
                    <div class="channel-pill <?= $twitchModuleEnabled && twitch()->connection() ? 'online' : '' ?>">
                        <span class="status-dot"></span>
                        <?= e($twitchModuleEnabled ? ($activeTwitchChannel['display_name'] ?? 'Twitch nicht verbunden') : 'Twitch-Modul aus') ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="status">
                    <span><?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'danger' ? '!' : 'i') ?></span>
                    <p><?= e($flash['message']) ?></p>
                    <button type="button" data-dismiss aria-label="Hinweis schließen">×</button>
                </div>
            <?php endforeach; ?>
            <?php if (auth()->can('updates.view') && !empty($githubStatus['update_available'])): ?>
                <div class="alert alert-update" role="status"><span>↑</span><p><strong>ModDesk <?= e($githubStatus['version']) ?> verfügbar.</strong> <?= e($githubStatus['release_name'] ?: 'Ein neuer GitHub-Release kann installiert werden.') ?></p><a class="button button-small button-primary" href="<?= e(url('settings', ['section' => 'github'])) ?>">Update öffnen</a></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
        <footer class="app-footer"><span><?= e($footerText !== '' ? $footerText : $appName) ?></span><small><?= e($versionText) ?></small></footer>
    </div>
    <div class="sidebar-backdrop" data-menu-close></div>

    <dialog class="version-dialog" data-version-dialog>
        <div class="version-dialog-shell">
            <header><div><p class="eyebrow">MODDESK VERSION</p><h2><?= e($versionText) ?></h2></div><button class="icon-button" type="button" data-version-close aria-label="Dialog schließen">×</button></header>
            <section><h3>Änderungen in <?= e(app_version()) ?></h3><pre class="changelog-copy"><?= e($currentChangelog) ?></pre></section>
            <?php if (!empty($githubStatus['update_available'])): ?>
                <section class="available-changelog"><div class="section-head"><div><p class="eyebrow">UPDATE VERFÜGBAR</p><h3><?= e($githubStatus['release_name'] ?: 'Version ' . $githubStatus['version']) ?></h3></div><span class="version-pill static">v<?= e($githubStatus['version']) ?></span></div><pre class="changelog-copy"><?= e($githubStatus['release_body'] ?: 'Für diesen Release wurden keine Notizen hinterlegt.') ?></pre><?php if (auth()->can('updates.view')): ?><a class="button button-primary" href="<?= e(url('settings', ['section' => 'github'])) ?>">Update verwalten</a><?php endif; ?></section>
            <?php endif; ?>
            <?php if ($changelogChannels !== []): ?>
                <section><h3>Changelog an Discord senden</h3><form method="post" class="form-grid compact-form"><?= csrf_field() ?><input type="hidden" name="action" value="discord-changelog-post"><input type="hidden" name="return_page" value="<?= e($currentPage) ?>"><label><span>Version</span><select name="changelog_source"><option value="current">Installiert · <?= e(app_version()) ?></option><?php if (!empty($githubStatus['update_available'])): ?><option value="latest">Verfügbar · <?= e($githubStatus['version']) ?></option><?php endif; ?></select></label><label><span>Discord-Channel</span><select name="discord_channel_id" required><?php foreach ($changelogChannels as $channel): ?><option value="<?= e($channel['channel_id']) ?>"><?= e($channel['server_name']) ?> · #<?= e($channel['channel_name']) ?></option><?php endforeach; ?></select></label><div class="span-2 form-actions"><button class="button button-secondary" type="submit" data-confirm="Diesen Changelog jetzt in Discord veröffentlichen?">In Discord posten</button></div></form></section>
            <?php endif; ?>
        </div>
    </dialog>
<?php else: ?>
    <main class="guest-main">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="status"><span>!</span><p><?= e($flash['message']) ?></p></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
<?php endif; ?>
</body>
</html>

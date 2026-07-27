<?php
$twitchConfigured = $integrationSettings['twitch_client_id'] !== ''
    && $integrationSettings['twitch_secret_set']
    && $integrationSettings['twitch_redirect_uri'] !== '';
$discordConfigured = $integrationSettings['discord_token_set'];
$smtpConfigured = $integrationSettings['smtp_host'] !== ''
    && $integrationSettings['smtp_from_email'] !== '';
$recaptchaConfigured = $integrationSettings['recaptcha_site_key'] !== ''
    && $integrationSettings['recaptcha_secret_set'];
$twitchLoginConfigured = $integrationSettings['twitch_client_id'] !== ''
    && $integrationSettings['twitch_secret_set']
    && $integrationSettings['twitch_login_redirect_uri'] !== '';
$discordInviteUrl = $integrationSettings['discord_application_id'] !== ''
    ? 'https://discord.com/oauth2/authorize?' . http_build_query([
        'client_id' => $integrationSettings['discord_application_id'],
        'scope' => 'bot',
        'permissions' => '274877926400',
    ])
    : null;
$providerLabels = ['discord' => 'Discord', 'smtp' => 'E-Mail'];
$updateStatusLabels = ['running' => 'Läuft', 'completed' => 'Erfolgreich', 'failed' => 'Fehler', 'rolled_back' => 'Zurückgesetzt'];
$eventLabels = array_map(static fn (array $event): string => $event['label'], $discordEvents);
$eventLabels['test'] = 'Verbindungstest';
$eventLabels['manual_message'] = 'Live-Nachricht';
?>

<div class="page-intro">
    <div><p>Zentrale Konfiguration für Installation, Twitch OAuth, Discord-Bot und SMTP-Mailversand.</p></div>
    <span class="badge">ModDesk <?= e($version) ?></span>
</div>

<nav class="settings-tabs" aria-label="Einstellungskategorien">
    <?php if (auth()->can('settings.general.manage')): ?><a href="#general">Allgemein</a><?php endif; ?>
    <?php if (auth()->can('authentication.configure')): ?><a href="#authentication">Authentifizierung</a><?php endif; ?>
    <?php if (auth()->can('twitch.configure')): ?><a href="#twitch">Twitch</a><?php endif; ?>
    <?php if (auth()->can('discord.configure')): ?><a href="#discord">Discord</a><?php endif; ?>
    <?php if (auth()->can('smtp.configure')): ?><a href="#smtp">SMTP</a><?php endif; ?>
    <?php if (auth()->can('github.configure')): ?><a href="#github">GitHub</a><?php endif; ?>
    <?php if (auth()->can('migrations.view')): ?><a href="#system">System</a><?php endif; ?>
    <?php if (auth()->can('updates.view')): ?><a href="#updates">Updates</a><?php endif; ?>
    <?php if (auth()->can('modules.view')): ?><a href="<?= e(url('modules')) ?>">Module ↗</a><?php endif; ?>
    <?php if (auth()->can('backups.view')): ?><a href="<?= e(url('backups')) ?>">Backups ↗</a><?php endif; ?>
    <?php if (auth()->can('security.view')): ?><a href="<?= e(url('security')) ?>">Sicherheit ↗</a><?php endif; ?>
</nav>

<section class="stat-grid integration-stats">
    <article class="stat-card purple"><span>◉</span><strong><?= $twitchConfigured ? 'OK' : '–' ?></strong><small>TWITCH API</small></article>
    <article class="stat-card cyan"><span>◌</span><strong><?= $discordConfigured && $integrationSettings['discord_enabled'] ? 'ON' : '–' ?></strong><small>DISCORD BOT</small></article>
    <article class="stat-card green"><span>✉</span><strong><?= $smtpConfigured && $integrationSettings['smtp_enabled'] ? 'ON' : '–' ?></strong><small>SMTP</small></article>
    <article class="stat-card orange"><span>⌁</span><strong><?= count($integrationDeliveries) ?></strong><small>LETZTE ZUSTELLUNGEN</small></article>
</section>

<?php if (auth()->can('settings.general.manage')): ?>
<section class="card settings-section" id="general">
    <div class="section-head">
        <div><p class="eyebrow">ALLGEMEIN</p><h2>Installation</h2></div>
        <span class="verification-badge status-verified">APP_KEY aktiv</span>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings-general-save">
        <input type="hidden" name="return_page" value="settings">
        <label><span>App-Name *</span><input type="text" name="app_name" required maxlength="100" value="<?= e($integrationSettings['app_name']) ?>"></label>
        <label><span>Öffentliche App-URL *</span><input type="url" name="app_url" required value="<?= e($integrationSettings['app_url']) ?>" placeholder="https://moddesk.example.de"></label>
        <label class="check-label span-2"><input type="checkbox" name="url_rewrite_enabled" value="1" <?= $integrationSettings['url_rewrite_enabled'] ? 'checked' : '' ?>><span>Saubere URL-Rewrites aktivieren, z. B. <code>/news</code> statt <code>?page=news</code></span></label>
        <div class="span-2 settings-note">Für Rewrites muss Apache <code>mod_rewrite</code> sowie <code>AllowOverride All</code> erlauben. Die mitgelieferten <code>.htaccess</code>-Dateien unterstützen sowohl den Projektstamm als auch einen Document Root auf <code>public/</code>.</div>
        <div class="span-2 settings-note">Die Datenbankverbindung und der APP_KEY bleiben bewusst in der nicht öffentlich erreichbaren <code>.env</code>. Integrationsgeheimnisse werden verschlüsselt in MySQL abgelegt.</div>
        <div class="span-2 form-actions"><button class="button button-secondary" type="submit">Allgemeines speichern</button></div>
    </form>
</section>
<?php endif; ?>

<?php if (auth()->can('authentication.configure')): ?>
<section class="card settings-section" id="authentication">
    <div class="section-head">
        <div><p class="eyebrow">LOGIN & BOT-SCHUTZ</p><h2>Authentifizierung</h2></div>
        <span class="job-status <?= ($integrationSettings['recaptcha_enabled'] || $integrationSettings['twitch_login_enabled']) ? 'status-completed' : 'status-running' ?>"><?= ($integrationSettings['recaptcha_enabled'] || $integrationSettings['twitch_login_enabled']) ? 'Aktiv' : 'Optional' ?></span>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings-auth-save">
        <input type="hidden" name="return_page" value="settings">
        <div class="span-2 settings-subsection">
            <div class="section-head"><div><p class="eyebrow">GOOGLE RECAPTCHA V2</p><h3>Klassischen Login schützen</h3></div><span class="job-status <?= $recaptchaConfigured ? 'status-completed' : 'status-running' ?>"><?= $recaptchaConfigured ? 'Konfiguriert' : 'Offen' ?></span></div>
            <div class="form-grid">
                <label class="check-label span-2"><input type="checkbox" name="recaptcha_enabled" value="1" <?= $integrationSettings['recaptcha_enabled'] ? 'checked' : '' ?>><span>„Ich bin kein Roboter“-Prüfung beim Passwort-Login aktivieren</span></label>
                <label><span>Site-Key</span><input type="text" name="recaptcha_site_key" autocomplete="off" value="<?= e($integrationSettings['recaptcha_site_key']) ?>"></label>
                <label><span>Secret-Key</span><input type="password" name="recaptcha_secret_key" autocomplete="new-password" placeholder="<?= $integrationSettings['recaptcha_secret_set'] ? 'Verschlüsselt gespeichert – leer lassen zum Behalten' : 'Noch nicht gespeichert' ?>"></label>
                <div class="span-2 settings-note">Verwende in der Google-Konsole einen reCAPTCHA-v2-Schlüssel vom Typ „Ich bin kein Roboter“-Checkbox und trage den Hostnamen deiner ModDesk-Installation ein. Der Secret-Key wird verschlüsselt gespeichert.</div>
            </div>
        </div>
        <div class="span-2 settings-subsection">
            <div class="section-head"><div><p class="eyebrow">TWITCH LOGIN</p><h3>Mit Twitch anmelden</h3></div><span class="job-status <?= $twitchLoginConfigured ? 'status-completed' : 'status-running' ?>"><?= $twitchLoginConfigured ? 'Konfiguriert' : 'Offen' ?></span></div>
            <div class="form-grid">
                <label class="check-label span-2"><input type="checkbox" name="twitch_login_enabled" value="1" <?= $integrationSettings['twitch_login_enabled'] ? 'checked' : '' ?>><span>Twitch als zusätzliche Loginmöglichkeit aktivieren</span></label>
                <label class="span-2"><span>Login Redirect-URI *</span><input type="url" name="twitch_login_redirect_uri" value="<?= e($integrationSettings['twitch_login_redirect_uri']) ?>" placeholder="<?= e(app_base_url() . '/?page=twitch-login-callback') ?>"></label>
                <div class="span-2 settings-note">Diese URI zusätzlich und exakt in der Twitch Developer Console eintragen. Sie muss zur eingestellten ModDesk-App gehören und auf <code>twitch-login-callback</code> zeigen. Sie ist bewusst getrennt von der Redirect-URI des Twitch-Modkontos. Benutzer verknüpfen ihr Twitch-Konto danach unter „Mein Benutzerkonto“.</div>
            </div>
        </div>
        <label class="span-2 critical-confirm">
            <span>Aktuelles Passwort zur Bestätigung *</span>
            <input type="password" name="confirm_password" required autocomplete="current-password">
            <small>Änderungen am Login-Schutz und an externen Anmeldungen werden sicherheitskritisch protokolliert.</small>
        </label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit">Authentifizierung speichern</button></div>
    </form>
</section>
<?php endif; ?>

<?php if (auth()->can('twitch.configure')): ?>
<section class="card settings-section" id="twitch">
    <div class="section-head">
        <div><p class="eyebrow">TWITCH OAUTH</p><h2>Twitch-API</h2></div>
        <span class="job-status <?= $twitchConfigured ? 'status-completed' : 'status-running' ?>"><?= $twitchConfigured ? 'Konfiguriert' : 'Offen' ?></span>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings-twitch-save">
        <input type="hidden" name="return_page" value="settings">
        <label><span>Client-ID</span><input type="text" name="twitch_client_id" autocomplete="off" value="<?= e($integrationSettings['twitch_client_id']) ?>"></label>
        <label><span>Client-Secret</span><input type="password" name="twitch_client_secret" autocomplete="new-password" placeholder="<?= $integrationSettings['twitch_secret_set'] ? 'Gespeichert – leer lassen zum Behalten' : 'Noch nicht gespeichert' ?>"></label>
        <label class="span-2"><span>OAuth Redirect-URI</span><input type="url" name="twitch_redirect_uri" value="<?= e($integrationSettings['twitch_redirect_uri']) ?>" placeholder="https://example.de/?page=twitch-callback"></label>
        <div class="span-2 settings-note">Die Redirect-URI muss in der Twitch Developer Console exakt gleich eingetragen sein. Nach einer Scope-Änderung das Twitch-Konto in der Twitch-Zentrale neu verbinden.</div>
        <div class="span-2 form-actions"><button class="button button-secondary" type="submit">Twitch speichern</button></div>
    </form>
</section>
<?php endif; ?>

<?php if (auth()->can('discord.configure')): ?>
<section class="card settings-section" id="discord">
    <div class="section-head">
        <div><p class="eyebrow">DISCORD-MODUL</p><h2>Bot, Server und Channels</h2></div>
        <span class="job-status <?= $discordConfigured && $integrationSettings['discord_enabled'] ? 'status-completed' : 'status-running' ?>"><?= $discordConfigured && $integrationSettings['discord_enabled'] ? 'Aktiv' : 'Inaktiv' ?></span>
    </div>
    <form method="post" class="stack-form settings-integration-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return_page" value="settings">
        <div class="form-grid">
            <label class="check-label field-bottom"><input type="checkbox" name="discord_enabled" value="1" <?= $integrationSettings['discord_enabled'] ? 'checked' : '' ?>><span>Discord-Benachrichtigungen aktivieren</span></label>
            <label><span>Application-ID</span><input type="text" name="discord_application_id" inputmode="numeric" autocomplete="off" value="<?= e($integrationSettings['discord_application_id']) ?>"></label>
            <label class="span-2"><span>Bot-Token</span><input type="password" name="discord_bot_token" autocomplete="new-password" placeholder="<?= $integrationSettings['discord_token_set'] ? 'Verschlüsselt gespeichert – leer lassen zum Behalten' : 'Bot-Token eintragen' ?>"></label>
        </div>

        <?php if ($discordInviteUrl): ?>
            <div class="settings-note discord-invite"><span>Bot noch nicht auf dem Server?</span><a class="button button-small button-secondary" href="<?= e($discordInviteUrl) ?>" target="_blank" rel="noopener noreferrer">Bot mit Minimalrechten einladen ↗</a></div>
        <?php else: ?>
            <div class="settings-note">Nach dem Speichern der Application-ID wird hier ein Einladungslink mit „Kanal ansehen“, „Nachrichten senden“, „Links einbetten“ und „Nachrichten in Threads senden“ erzeugt.</div>
        <?php endif; ?>

        <?php if (!$discordManagedReady): ?>
            <div class="alert alert-warning"><span>!</span><p>Für mehrere Server und Channels bitte zuerst unter „System“ die ausstehende Migration ausführen. Bis dahin bleibt das bisherige Routing aktiv.</p></div>
            <div class="route-grid">
                <?php foreach ($discordEvents as $eventKey => $event): ?>
                    <?php $route = $discordRoutes[$eventKey] ?? ['guild_id' => '', 'channel_id' => '', 'enabled' => 0]; ?>
                    <article class="route-card">
                        <div class="route-head"><div><strong><?= e($event['label']) ?></strong><small><?= e($event['description']) ?></small></div><label class="switch-label"><input type="checkbox" name="discord_routes[<?= e($eventKey) ?>][enabled]" value="1" <?= (int) $route['enabled'] === 1 ? 'checked' : '' ?>><span>Aktiv</span></label></div>
                        <label><span>Discord Server-ID</span><input type="text" inputmode="numeric" name="discord_routes[<?= e($eventKey) ?>][guild_id]" value="<?= e($route['guild_id'] ?? '') ?>"></label>
                        <label><span>Discord Channel-ID</span><input type="text" inputmode="numeric" name="discord_routes[<?= e($eventKey) ?>][channel_id]" value="<?= e($route['channel_id'] ?? '') ?>"></label>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label><span>Test-Channel</span><select name="discord_test_channel_id"><option value="">Erster aktiver Channel</option><?php foreach (discord_managed_channels(true) as $channel): ?><option value="<?= e($channel['channel_id']) ?>"><?= e($channel['server_name']) ?> · #<?= e($channel['channel_name']) ?></option><?php endforeach; ?></select></label>
        <div class="button-row settings-actions">
            <button class="button button-secondary" type="submit" name="action" value="settings-discord-save">Discord speichern</button>
            <button class="button button-primary" type="submit" name="action" value="settings-discord-test" data-confirm="Einstellungen speichern und jetzt eine Discord-Testnachricht senden?">Speichern & testen</button>
        </div>
    </form>

    <?php if ($discordManagedReady): ?>
        <div class="settings-subsection">
            <div class="section-head"><div><p class="eyebrow">SERVER</p><h3>Discord-Server verwalten</h3></div><span class="count-chip"><?= count($discordServers) ?></span></div>
            <form method="post" class="form-grid discord-add-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="discord-server-save"><input type="hidden" name="return_page" value="settings">
                <label><span>Servername *</span><input type="text" name="server_name" maxlength="120" required placeholder="Community-Server"></label>
                <label><span>Server-ID *</span><input type="text" name="guild_id" inputmode="numeric" required></label>
                <label class="check-label"><input type="checkbox" name="server_enabled" value="1" checked><span>Server aktiv</span></label>
                <div class="form-actions"><button class="button button-primary" type="submit">Server hinzufügen</button></div>
            </form>
            <div class="discord-resource-grid top-gap">
                <?php foreach ($discordServers as $server): ?>
                    <article class="route-card managed-resource-card">
                        <form method="post" class="stack-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="discord-server-save"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="server_id" value="<?= e($server['id']) ?>">
                            <label><span>Name</span><input type="text" name="server_name" maxlength="120" required value="<?= e($server['name']) ?>"></label>
                            <label><span>Server-ID</span><input type="text" name="guild_id" inputmode="numeric" required value="<?= e($server['guild_id']) ?>"></label>
                            <label class="check-label"><input type="checkbox" name="server_enabled" value="1" <?= (int) $server['enabled'] === 1 ? 'checked' : '' ?>><span>Aktiv</span></label>
                            <button class="button button-small button-secondary" type="submit">Speichern</button>
                        </form>
                        <form method="post" class="top-gap"><?= csrf_field() ?><input type="hidden" name="action" value="discord-server-sync"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="server_id" value="<?= e($server['id']) ?>"><button class="button button-small button-primary" type="submit">Channels von Discord abrufen</button></form>
                        <form method="post" class="top-gap"><?= csrf_field() ?><input type="hidden" name="action" value="discord-server-delete"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="server_id" value="<?= e($server['id']) ?>"><button class="button button-small button-danger-outline" type="submit" data-confirm="Server und alle zugeordneten Channels aus ModDesk entfernen?">Entfernen</button></form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="settings-subsection">
            <div class="section-head"><div><p class="eyebrow">CHANNELS</p><h3>Channels und Ereignisse</h3></div><span class="count-chip"><?= count($discordChannels) ?></span></div>
            <?php if ($discordServers === []): ?><div class="settings-note">Lege zuerst einen Discord-Server an.</div><?php else: ?>
            <form method="post" class="form-grid discord-add-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="discord-channel-save"><input type="hidden" name="return_page" value="settings">
                <label><span>Server *</span><select name="server_id"><?php foreach ($discordServers as $server): ?><option value="<?= e($server['id']) ?>"><?= e($server['name']) ?></option><?php endforeach; ?></select></label>
                <label><span>Channelname *</span><input type="text" name="channel_name" maxlength="120" required placeholder="moddesk-news"></label>
                <label><span>Channel-ID *</span><input type="text" name="channel_id" inputmode="numeric" required></label>
                <label class="check-label"><input type="checkbox" name="channel_enabled" value="1" checked><span>Channel aktiv</span></label>
                <fieldset class="span-2 event-check-grid"><legend>Ereignisse für diesen Channel</legend><?php foreach ($discordEvents as $eventKey => $event): ?><label class="check-label"><input type="checkbox" name="channel_events[]" value="<?= e($eventKey) ?>"><span><?= e($event['label']) ?></span></label><?php endforeach; ?></fieldset>
                <div class="span-2 form-actions"><button class="button button-primary" type="submit">Channel hinzufügen</button></div>
            </form>
            <?php endif; ?>

            <div class="discord-channel-list top-gap">
                <?php foreach ($discordChannels as $channel): ?>
                    <?php $assignedEvents = $discordChannelRoutes[(int) $channel['id']] ?? []; ?>
                    <article class="route-card managed-channel-card">
                        <form method="post" class="form-grid">
                            <?= csrf_field() ?><input type="hidden" name="action" value="discord-channel-save"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="channel_record_id" value="<?= e($channel['id']) ?>">
                            <label><span>Server</span><select name="server_id"><?php foreach ($discordServers as $server): ?><option value="<?= e($server['id']) ?>" <?= (int) $channel['server_id'] === (int) $server['id'] ? 'selected' : '' ?>><?= e($server['name']) ?></option><?php endforeach; ?></select></label>
                            <label><span>Channelname</span><input type="text" name="channel_name" maxlength="120" required value="<?= e($channel['name']) ?>"></label>
                            <label><span>Channel-ID</span><input type="text" name="channel_id" inputmode="numeric" required value="<?= e($channel['channel_id']) ?>"></label>
                            <label class="check-label"><input type="checkbox" name="channel_enabled" value="1" <?= (int) $channel['enabled'] === 1 ? 'checked' : '' ?>><span>Aktiv</span></label>
                            <fieldset class="span-2 event-check-grid"><legend>Benachrichtigungen</legend><?php foreach ($discordEvents as $eventKey => $event): ?><label class="check-label"><input type="checkbox" name="channel_events[]" value="<?= e($eventKey) ?>" <?= in_array($eventKey, $assignedEvents, true) ? 'checked' : '' ?>><span><?= e($event['label']) ?></span></label><?php endforeach; ?></fieldset>
                            <div class="span-2 button-row"><button class="button button-small button-secondary" type="submit">Channel speichern</button></div>
                        </form>
                        <form method="post" class="top-gap"><?= csrf_field() ?><input type="hidden" name="action" value="discord-channel-delete"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="channel_record_id" value="<?= e($channel['id']) ?>"><button class="button button-small button-danger-outline" type="submit" data-confirm="Diesen Channel und seine Ereigniszuordnungen entfernen?">Channel entfernen</button></form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (auth()->can('smtp.configure')): ?>
<section class="card settings-section" id="smtp">
    <div class="section-head">
        <div><p class="eyebrow">E-MAIL</p><h2>SMTP-Server</h2></div>
        <span class="job-status <?= $smtpConfigured && $integrationSettings['smtp_enabled'] ? 'status-completed' : 'status-running' ?>"><?= $smtpConfigured && $integrationSettings['smtp_enabled'] ? 'Aktiv' : 'Inaktiv' ?></span>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="return_page" value="settings">
        <label class="check-label field-bottom"><input type="checkbox" name="smtp_enabled" value="1" <?= $integrationSettings['smtp_enabled'] ? 'checked' : '' ?>><span>SMTP-Mailversand aktivieren</span></label>
        <label><span>SMTP-Host</span><input type="text" name="smtp_host" value="<?= e($integrationSettings['smtp_host']) ?>" placeholder="smtp.example.de"></label>
        <label><span>Port</span><input type="number" name="smtp_port" min="1" max="65535" value="<?= e($integrationSettings['smtp_port']) ?>"></label>
        <label><span>Verschlüsselung</span><select name="smtp_encryption"><option value="tls" <?= $integrationSettings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS</option><option value="ssl" <?= $integrationSettings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS direkt</option><option value="none" <?= $integrationSettings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Keine</option></select></label>
        <label><span>Anmeldung</span><select name="smtp_auth"><option value="login" <?= $integrationSettings['smtp_auth'] === 'login' ? 'selected' : '' ?>>AUTH LOGIN</option><option value="plain" <?= $integrationSettings['smtp_auth'] === 'plain' ? 'selected' : '' ?>>AUTH PLAIN</option><option value="none" <?= $integrationSettings['smtp_auth'] === 'none' ? 'selected' : '' ?>>Keine Anmeldung</option></select></label>
        <label><span>SMTP-Benutzer</span><input type="text" name="smtp_username" autocomplete="off" value="<?= e($integrationSettings['smtp_username']) ?>"></label>
        <label><span>SMTP-Passwort</span><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $integrationSettings['smtp_password_set'] ? 'Verschlüsselt gespeichert – leer lassen zum Behalten' : 'Noch nicht gespeichert' ?>"></label>
        <label><span>Absenderadresse</span><input type="email" name="smtp_from_email" value="<?= e($integrationSettings['smtp_from_email']) ?>" placeholder="moddesk@example.de"></label>
        <label><span>Absendername</span><input type="text" name="smtp_from_name" maxlength="100" value="<?= e($integrationSettings['smtp_from_name']) ?>"></label>
        <label class="span-2"><span>Testmail senden an</span><input type="email" name="smtp_test_recipient" placeholder="deine-adresse@example.de"></label>
        <div class="span-2 settings-note">Viele Mailanbieter verlangen statt des normalen Kontopassworts ein eigenes App-Passwort. SMTP-Konten, die ausschließlich OAuth2 zulassen, benötigen einen separaten Provider-Adapter.</div>
        <div class="span-2 button-row settings-actions">
            <button class="button button-secondary" type="submit" name="action" value="settings-smtp-save">SMTP speichern</button>
            <button class="button button-primary" type="submit" name="action" value="settings-smtp-test" data-confirm="Einstellungen speichern und jetzt eine SMTP-Testmail senden?">Speichern & testen</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if (auth()->can('github.configure')): ?>
<section class="card settings-section" id="github">
    <div class="section-head">
        <div><p class="eyebrow">GITHUB RELEASES</p><h2>Repository und Ein-Klick-Updates</h2></div>
        <span class="job-status <?= $githubReady && $githubSettings['enabled'] ? 'status-completed' : 'status-running' ?>"><?= !$githubReady ? 'Migration fehlt' : ($githubSettings['enabled'] ? 'Verbunden' : 'Inaktiv') ?></span>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="github-settings-save"><input type="hidden" name="return_page" value="settings">
        <label class="check-label span-2"><input type="checkbox" name="github_updates_enabled" value="1" <?= $githubSettings['enabled'] ? 'checked' : '' ?>><span>Automatisch nach neuen GitHub-Releases suchen</span></label>
        <label><span>Repository *</span><input type="text" name="github_repository" value="<?= e($githubSettings['repository']) ?>" placeholder="owner/twitch-moddesk"></label>
        <label><span>Release-Assetname *</span><input type="text" name="github_asset_name" value="<?= e($githubSettings['asset_name']) ?>" placeholder="twitch-moddesk.zip"></label>
        <label><span>Prüfintervall</span><select name="github_check_interval_hours"><?php foreach ([1 => 'stündlich', 6 => 'alle 6 Stunden', 12 => 'alle 12 Stunden', 24 => 'täglich', 168 => 'wöchentlich'] as $hours => $label): ?><option value="<?= e($hours) ?>" <?= (int) $githubSettings['interval'] === $hours ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Fine-grained Token (optional)</span><input type="password" name="github_token" autocomplete="new-password" placeholder="<?= $githubSettings['token_set'] ? 'Verschlüsselt gespeichert – leer lassen zum Behalten' : 'Nur für private Repositories nötig' ?>"></label>
        <div class="span-2 settings-note">ModDesk verwendet den neuesten veröffentlichten GitHub-Release und sucht darin exakt nach dem eingetragenen ZIP-Asset. Für private Repositories genügt ein Fine-grained Token mit lesendem „Contents“-Zugriff. Das Token wird verschlüsselt in MySQL gespeichert.</div>
        <div class="span-2 form-actions"><button class="button button-secondary" type="submit" <?= !$githubReady ? 'disabled' : '' ?>>GitHub-Verbindung speichern</button></div>
    </form>

    <?php if ($githubStatus): ?>
        <article class="github-release-card <?= !empty($githubStatus['update_available']) ? 'update-available' : '' ?>">
            <div><p class="eyebrow">LETZTER RELEASE</p><h3><?= e($githubStatus['release_name'] ?: $githubStatus['tag_name'] ?: 'Noch kein Release') ?></h3><small>Geprüft: <?= e(utc_to_local($githubStatus['checked_at'] ?? null)) ?><?php if ($githubStatus['published_at']): ?> · veröffentlicht <?= e(utc_to_local($githubStatus['published_at'])) ?><?php endif; ?></small></div>
            <span class="version-pill static">v<?= e($githubStatus['version'] ?: '–') ?></span>
            <?php if ($githubStatus['error_message']): ?><div class="alert alert-warning"><span>!</span><p><?= e($githubStatus['error_message']) ?></p></div><?php endif; ?>
            <?php if ($githubStatus['release_body']): ?><details><summary>GitHub-Changelog anzeigen</summary><pre class="changelog-copy"><?= e($githubStatus['release_body']) ?></pre></details><?php endif; ?>
        </article>
    <?php endif; ?>
    <div class="button-row top-gap">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="github-release-check"><input type="hidden" name="return_page" value="settings"><button class="button button-secondary" type="submit" <?= !$githubReady || !$githubSettings['enabled'] ? 'disabled' : '' ?>>Jetzt auf Releases prüfen</button></form>
        <?php if (!empty($githubStatus['update_available']) && auth()->can('updates.install')): ?><form method="post" class="inline-form critical-inline"><?= csrf_field() ?><input type="hidden" name="action" value="github-update-install"><input type="hidden" name="return_page" value="settings"><input type="password" name="confirm_password" required autocomplete="current-password" placeholder="Aktuelles Passwort"><button class="button button-primary" type="submit" data-confirm="Release-ZIP herunterladen, Vollbackup erstellen, Migrationen ausführen und ModDesk jetzt aktualisieren?">Version <?= e($githubStatus['version']) ?> installieren</button></form><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (auth()->can('migrations.view')): ?>
<section class="card settings-section" id="system">
    <div class="section-head">
        <div><p class="eyebrow">DATENBANK</p><h2>Panel-Migrator</h2></div>
        <span class="job-status <?= $migrationStatus['pending'] === [] ? 'status-completed' : 'status-running' ?>"><?= $migrationStatus['pending'] === [] ? 'Aktuell' : count($migrationStatus['pending']) . ' ausstehend' ?></span>
    </div>
    <div class="migration-summary">
        <div><strong><?= count($migrationStatus['available']) ?></strong><small>verfügbare Migrationen</small></div>
        <div><strong><?= count($migrationStatus['applied']) ?></strong><small>bereits ausgeführt</small></div>
        <div><strong><?= count($migrationStatus['pending']) ?></strong><small>noch offen</small></div>
    </div>
    <div class="migration-list top-gap">
        <?php foreach ($migrationStatus['files'] as $index => $migrationFile): ?>
            <?php
            $migrationName = (string) $migrationFile['name'];
            $isApplied = in_array($migrationName, $migrationStatus['applied'], true);
            $isNext = !$isApplied && ($migrationStatus['pending'][0] ?? null) === $migrationName;
            ?>
            <article>
                <span class="timeline-icon <?= $isApplied ? 'success' : ($isNext ? 'pending' : 'muted') ?>"><?= $isApplied ? '✓' : ($isNext ? '→' : '·') ?></span>
                <span><strong><?= e($migrationName) ?></strong><small><?= e(format_bytes((int) $migrationFile['size_bytes'])) ?> · SHA-256 <?= e(substr((string) $migrationFile['checksum'], 0, 12)) ?>…</small></span>
                <span class="job-status <?= $isApplied ? 'status-completed' : 'status-running' ?>"><?= $isApplied ? 'Ausgeführt' : 'Offen' ?></span>
                <?php if ($isNext && auth()->can('migrations.run')): ?>
                    <form method="post" class="inline-form migration-run-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="system-migrations-run"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="migration_name" value="<?= e($migrationName) ?>">
                        <input type="password" name="confirm_password" required autocomplete="current-password" placeholder="Aktuelles Passwort">
                        <button class="button button-small button-secondary" type="submit" data-confirm="Vorher ein Vollbackup erstellen und diese Migration jetzt ausführen?">Einzeln starten</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($migrationStatus['pending'] !== [] && auth()->can('migrations.run')): ?>
        <form method="post" class="form-grid top-gap migration-all-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="system-migrations-run"><input type="hidden" name="return_page" value="settings">
            <label class="critical-confirm"><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
            <div class="form-actions"><button class="button button-primary" type="submit" data-confirm="Vollbackup erstellen und alle ausstehenden Datenbankmigrationen jetzt in Reihenfolge ausführen?">Alle offenen Migrationen ausführen</button></div>
        </form>
    <?php else: ?>
        <div class="settings-note top-gap">Alle vorhandenen Migrationen sind ausgeführt.</div>
    <?php endif; ?>
    <?php if ($migrationRuns !== []): ?>
        <details class="top-gap"><summary>Letzte Migrationsläufe anzeigen</summary><div class="responsive-table top-gap"><table><thead><tr><th>Migration</th><th>Status</th><th>Ausgeführt von</th><th>Zeit</th><th>Fehler</th></tr></thead><tbody><?php foreach ($migrationRuns as $run): ?><tr><td><code><?= e($run['migration_name']) ?></code></td><td data-label="Status"><span class="job-status status-<?= e($run['status']) ?>"><?= e($run['status']) ?></span></td><td data-label="Person"><?= e($run['executed_by_name'] ?? 'System') ?></td><td data-label="Zeit"><?= e(utc_to_local($run['started_at'])) ?></td><td data-label="Fehler"><small><?= e($run['error_message'] ?: '–') ?></small></td></tr><?php endforeach; ?></tbody></table></div></details>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (auth()->can('updates.view')): ?>
<section class="card settings-section" id="updates">
    <div class="section-head">
        <div><p class="eyebrow">SYSTEM</p><h2>Update-Importer</h2></div>
        <span class="job-status <?= $zipAvailable && $updatesReady ? 'status-completed' : 'status-failed' ?>"><?= !$updatesReady ? 'Migration fehlt' : ($zipAvailable ? 'Bereit' : 'ZIP fehlt') ?></span>
    </div>
    <div class="update-preflight">
        <?php foreach ($updatePreflight as $check): ?><article class="<?= $check['ok'] ? 'passed' : 'failed' ?>"><span><?= $check['ok'] ? '✓' : '!' ?></span><div><strong><?= e($check['label']) ?></strong><small><?= e($check['message']) ?></small></div></article><?php endforeach; ?>
    </div>
    <?php if (auth()->can('updates.install')): ?>
    <form method="post" enctype="multipart/form-data" class="form-grid top-gap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="system-update-import">
        <input type="hidden" name="return_page" value="settings">
        <label class="span-2 upload-field"><span>Neues ModDesk-Update-ZIP</span><input type="file" name="update_package" accept="application/zip,.zip" required data-file-input><small data-file-status>Noch keine Datei ausgewählt.</small></label>
        <label class="span-2 critical-confirm"><span>Aktuelles Passwort zur Installation *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
        <div class="span-2 settings-note">Die Dateiauswahl bleibt immer bedienbar. Vor der Installation prüft ModDesk Uploadstatus, ZIP-Struktur, Manifest, Version, Pfade und Größen und erstellt anschließend ein vollständiges Datenbank- und Dateibackup. <code>.env</code>, MySQL-Daten, Branding und Zusatzmodule bleiben beim Update erhalten.</div>
        <?php if (array_filter($updatePreflight, static fn (array $check): bool => !$check['ok'])): ?><div class="span-2 alert alert-warning"><span>!</span><p>Du kannst das ZIP bereits auswählen. Nicht bestandene Prüfungen müssen vor der Installation behoben werden; der Server nennt beim Absenden den konkreten Grund.</p></div><?php endif; ?>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit" data-confirm="Update prüfen, Vollbackup erstellen und die neue Version jetzt installieren?">Update prüfen & installieren</button></div>
    </form>
    <?php else: ?>
        <div class="settings-note top-gap">Du kannst den Updateverlauf ansehen, besitzt aber kein Recht zum Installieren neuer Versionen.</div>
    <?php endif; ?>

    <?php if ($systemUpdates !== []): ?>
        <div class="responsive-table update-history"><table><thead><tr><th>Version</th><th>Paket</th><th>Status</th><th>Ausgeführt</th><th>Rollback</th></tr></thead><tbody>
        <?php foreach ($systemUpdates as $update): ?><tr><td><strong><?= e($update['from_version']) ?> → <?= e($update['to_version']) ?></strong><small><?= e(substr($update['checksum_sha256'], 0, 12)) ?>…</small></td><td data-label="Paket"><?= e($update['package_name']) ?></td><td data-label="Status"><span class="job-status status-<?= e($update['status']) ?>"><?= e($updateStatusLabels[$update['status']] ?? $update['status']) ?></span><?php if ($update['error_message']): ?><small><?= e($update['error_message']) ?></small><?php endif; ?></td><td data-label="Ausgeführt"><strong><?= e($update['applied_by_name']) ?></strong><small><?= e(utc_to_local($update['created_at'])) ?></small></td><td data-label="Rollback"><?php if (auth()->can('updates.rollback') && $update['status'] === 'completed' && str_starts_with((string) $update['backup_path'], 'storage/backups/')): ?><form method="post" class="inline-form rollback-form"><?= csrf_field() ?><input type="hidden" name="action" value="system-update-rollback"><input type="hidden" name="return_page" value="settings"><input type="hidden" name="update_id" value="<?= e($update['id']) ?>"><input class="mini-password" type="password" name="confirm_password" required placeholder="Passwort" autocomplete="current-password"><button class="button button-small button-danger-outline" type="submit" data-confirm="Den vollständigen Stand vor diesem Update wiederherstellen? Der aktuelle Stand wird vorher erneut gesichert.">Rollback</button></form><?php else: ?>–<?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card table-card delivery-card">
    <div class="section-head"><div><p class="eyebrow">PROTOKOLL</p><h3>Integrations-Zustellungen</h3></div><span class="count-chip"><?= count($integrationDeliveries) ?></span></div>
    <?php if ($integrationDeliveries === []): ?>
        <div class="empty-state"><span class="empty-icon">⌁</span><h3>Noch keine Zustellung</h3><p>Tests und automatische Discord-Nachrichten erscheinen hier.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead><tr><th>Dienst</th><th>Ereignis</th><th>Ziel</th><th>Status</th><th>Fehler</th><th>Zeit</th></tr></thead>
                <tbody>
                <?php foreach ($integrationDeliveries as $delivery): ?>
                    <tr>
                        <td><strong><?= e($providerLabels[$delivery['provider']] ?? $delivery['provider']) ?></strong></td>
                        <td data-label="Ereignis"><?= e($eventLabels[$delivery['event_key']] ?? $delivery['event_key']) ?></td>
                        <td data-label="Ziel"><small class="mono-copy"><?= e($delivery['destination'] ?: '–') ?></small></td>
                        <td data-label="Status"><span class="job-status <?= (int) $delivery['success'] === 1 ? 'status-completed' : 'status-failed' ?>"><?= (int) $delivery['success'] === 1 ? 'Erfolgreich' : 'Fehler' ?></span><?php if ($delivery['response_status']): ?><small>Code <?= e($delivery['response_status']) ?></small><?php endif; ?></td>
                        <td data-label="Fehler"><small><?= e($delivery['error_message'] ?: '–') ?></small></td>
                        <td data-label="Zeit"><?= e(utc_to_local($delivery['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="login-card">
    <div class="login-brand">
        <?php $loginLogo = branding()->logoMetadata(); ?>
        <?php if ($loginLogo): ?><img class="brand-logo large" src="<?= e(url('brand-logo', ['v' => substr((string) $loginLogo['checksum_sha256'], 0, 16)])) ?>" alt=""><?php else: ?><span class="brand-mark large">M</span><?php endif; ?>
        <div><strong><?= e((string) settings()->get('app_name', env('APP_NAME', 'Twitch ModDesk'))) ?></strong><small>// DESK</small></div>
    </div>
    <p class="eyebrow">TWITCH TEAM CONTROL</p>
    <h1>Willkommen zurück.</h1>
    <p class="muted">Melde dich an, um Ideen, Notizen und Moderationswerkzeuge zu öffnen.</p>

    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>
            <span>Benutzername</span>
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>
        <label>
            <span>Passwort</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <?php if (recaptcha()->enabled() && recaptcha()->configured()): ?>
            <div class="recaptcha-shell">
                <div class="g-recaptcha" data-sitekey="<?= e(recaptcha()->siteKey()) ?>" data-theme="dark"></div>
            </div>
        <?php endif; ?>
        <button class="button button-primary button-wide" type="submit">Panel öffnen <span>→</span></button>
    </form>
    <?php if (twitch_login()->isConfigured()): ?>
        <div class="login-divider"><span>oder</span></div>
        <a class="button button-twitch button-wide" href="<?= e(url('twitch-login-start', ['mode' => 'login'])) ?>">Mit Twitch anmelden <span>→</span></a>
    <?php endif; ?>
    <?php $loginFooter = trim((string) settings()->get('footer_text', '')); ?>
    <p class="login-foot"><?= e($loginFooter !== '' ? $loginFooter : 'Geschützter Team-Bereich') ?> · <?= e(display_version_text()) ?></p>
</section>
<?php if (recaptcha()->enabled() && recaptcha()->configured()): ?>
<script src="https://www.google.com/recaptcha/api.js?hl=de" async defer></script>
<?php endif; ?>

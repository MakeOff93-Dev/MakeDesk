<section class="login-card two-factor-login-card">
    <div class="login-brand">
        <?php $loginLogo = branding()->logoMetadata(); ?>
        <?php if ($loginLogo): ?><img class="brand-logo large" src="<?= e(url('brand-logo', ['v' => substr((string) $loginLogo['checksum_sha256'], 0, 16)])) ?>" alt=""><?php else: ?><span class="brand-mark large">M</span><?php endif; ?>
        <div><strong><?= e((string) settings()->get('app_name', env('APP_NAME', 'Twitch ModDesk'))) ?></strong><small>// SECURE</small></div>
    </div>

    <div class="security-symbol" aria-hidden="true">⌁</div>
    <p class="eyebrow">ZWEITER SICHERHEITSFAKTOR</p>
    <h1>Code bestätigen.</h1>
    <p class="muted">
        Hallo <?= e($twoFactorChallenge['display_name']) ?>. Gib den sechsstelligen Code aus deiner
        Authenticator-App oder einen unbenutzten Wiederherstellungscode ein.
    </p>

    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="two-factor-verify">
        <label>
            <span>Authenticator- oder Wiederherstellungscode</span>
            <input class="otp-input" type="text" name="two_factor_code"
                   autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                   maxlength="24" placeholder="000 000" required autofocus>
        </label>
        <button class="button button-primary button-wide" type="submit">Anmeldung abschließen <span>→</span></button>
    </form>

    <form method="post" class="two-factor-cancel-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="two-factor-cancel">
        <button class="button button-secondary button-wide" type="submit">Abbrechen und zurück</button>
    </form>

    <p class="login-foot">
        Noch <?= e($twoFactorChallenge['attempts_remaining']) ?> Versuche · Code läuft automatisch ab
    </p>
</section>

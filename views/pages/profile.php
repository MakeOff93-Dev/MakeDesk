<?php
$initial = mb_strtoupper(mb_substr((string) $profileUser['display_name'], 0, 1));
$twitchLoginAvailable = twitch_login()->isConfigured();
?>

<div class="page-intro">
    <div><p>Profil, Profilbild, Passwort, Zwei-Faktor-Schutz und verknüpfte Anmeldungen deines ModDesk-Kontos verwalten.</p></div>
    <span class="badge"><?= e(role_label((string) $profileUser['role'])) ?></span>
</div>

<section class="profile-grid">
    <article class="card profile-identity-card">
        <div class="profile-avatar-shell" data-avatar-preview-shell>
            <?php if ($profileAvatarUrl): ?>
                <img src="<?= e($profileAvatarUrl) ?>" alt="Profilbild von <?= e($profileUser['display_name']) ?>" data-avatar-preview>
            <?php else: ?>
                <span class="avatar avatar-profile avatar-text" data-avatar-fallback><?= e($initial) ?></span>
                <img src="" alt="" data-avatar-preview hidden>
            <?php endif; ?>
        </div>
        <div>
            <p class="eyebrow">DEIN KONTO</p>
            <h2><?= e($profileUser['display_name']) ?></h2>
            <p class="muted">@<?= e($profileUser['username']) ?><?= $profileUser['email'] ? ' · ' . e($profileUser['email']) : '' ?></p>
            <div class="profile-meta">
                <span><small>Rolle</small><strong><?= e(role_label((string) $profileUser['role'])) ?></strong></span>
                <span><small>Erstellt</small><strong><?= e(utc_to_local($profileUser['created_at'])) ?></strong></span>
                <span><small>Letzter Login</small><strong><?= e(utc_to_local($profileUser['last_login_at'])) ?></strong></span>
            </div>
        </div>
    </article>

    <article class="card form-card">
        <div class="section-head"><div><p class="eyebrow">PROFILBILD</p><h3>Avatar bearbeiten</h3></div></div>
        <form method="post" enctype="multipart/form-data" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile-avatar-save">
            <input type="hidden" name="return_page" value="profile">
            <label class="upload-field">
                <span>Neues Profilbild</span>
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp"
                       required data-file-input data-avatar-input>
                <small data-file-status>Noch keine Datei ausgewählt.</small>
            </label>
            <div class="settings-note">PNG, JPG oder WebP · höchstens 3 MB · mindestens 64×64 Pixel. Das Bild wird quadratisch zugeschnitten dargestellt, die Originaldatei bleibt erhalten.</div>
            <button class="button button-primary" type="submit">Profilbild speichern</button>
        </form>
        <?php if ($profileAvatarMetadata): ?>
            <form method="post" class="top-gap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile-avatar-delete">
                <input type="hidden" name="return_page" value="profile">
                <button class="button button-small button-danger-outline" type="submit" data-confirm="Das hochgeladene Profilbild entfernen?">Profilbild entfernen</button>
            </form>
        <?php endif; ?>
    </article>
</section>

<section class="card form-card">
    <div class="section-head"><div><p class="eyebrow">PROFILDATEN</p><h2>Name und Kontaktdaten</h2></div></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile-save">
        <input type="hidden" name="return_page" value="profile">
        <label><span>Benutzername *</span><input type="text" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.-]+" value="<?= e($profileUser['username']) ?>" autocomplete="username"></label>
        <label><span>Anzeigename *</span><input type="text" name="display_name" required maxlength="100" value="<?= e($profileUser['display_name']) ?>"></label>
        <label class="span-2"><span>E-Mail</span><input type="email" name="email" maxlength="190" value="<?= e($profileUser['email'] ?? '') ?>" autocomplete="email"></label>
        <label class="span-2 critical-confirm">
            <span>Aktuelles Passwort zur Bestätigung *</span>
            <input type="password" name="confirm_password" required autocomplete="current-password">
            <small>Änderungen an Benutzername und E-Mail werden im Sicherheitsprotokoll erfasst.</small>
        </label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit">Profildaten speichern</button></div>
    </form>
</section>

<section class="account-security-grid">
    <article class="card form-card">
        <div class="section-head"><div><p class="eyebrow">PASSWORT</p><h2>Passwort ändern</h2></div></div>
        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile-password-change">
            <input type="hidden" name="return_page" value="profile">
            <label><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
            <label><span>Neues Passwort *</span><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label>
            <label><span>Neues Passwort wiederholen *</span><input type="password" name="new_password_confirmation" required minlength="12" autocomplete="new-password"></label>
            <div class="settings-note">Mindestens 12 Zeichen und mindestens drei Arten aus Großbuchstaben, Kleinbuchstaben, Zahlen und Sonderzeichen.</div>
            <button class="button button-primary" type="submit" data-confirm="Passwort ändern und alle anderen Sitzungen beenden?">Passwort ändern</button>
        </form>
    </article>

    <article class="card twitch-account-card">
        <div class="section-head"><div><p class="eyebrow">EXTERNER LOGIN</p><h2>Twitch-Anmeldung</h2></div><span class="job-status <?= $linkedTwitchAccount ? 'status-completed' : 'status-running' ?>"><?= $linkedTwitchAccount ? 'Verbunden' : 'Nicht verbunden' ?></span></div>
        <?php if ($linkedTwitchAccount): ?>
            <div class="linked-account">
                <?php if ($linkedTwitchAccount['provider_avatar_url']): ?><img src="<?= e($linkedTwitchAccount['provider_avatar_url']) ?>" alt=""><?php else: ?><span class="avatar avatar-text">T</span><?php endif; ?>
                <span><strong><?= e($linkedTwitchAccount['provider_display_name'] ?: $linkedTwitchAccount['provider_username']) ?></strong><small>@<?= e($linkedTwitchAccount['provider_username']) ?> · Twitch-ID <?= e($linkedTwitchAccount['provider_user_id']) ?></small></span>
            </div>
            <p class="muted">Du kannst dich auf der Login-Seite mit diesem Twitch-Konto anmelden. Das ModDesk speichert dafür kein Twitch-Zugriffstoken.</p>
            <form method="post" class="stack-form top-gap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile-twitch-unlink">
                <input type="hidden" name="return_page" value="profile">
                <label><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                <button class="button button-small button-danger-outline" type="submit" data-confirm="Twitch wirklich als Loginmöglichkeit entfernen?">Twitch-Verknüpfung entfernen</button>
            </form>
        <?php elseif ($twitchLoginAvailable): ?>
            <p class="muted">Verbinde dein Twitch-Konto einmalig. Danach erscheint „Mit Twitch anmelden“ auf der Login-Seite.</p>
            <form method="post" class="stack-form top-gap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile-twitch-link-start">
                <input type="hidden" name="return_page" value="profile">
                <label><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                <button class="button button-twitch" type="submit">Mit Twitch verbinden →</button>
            </form>
        <?php else: ?>
            <div class="settings-note">Der Twitch-Login muss zuerst unter Einstellungen → Authentifizierung aktiviert und vollständig konfiguriert werden.</div>
        <?php endif; ?>
    </article>
</section>

<section class="card form-card two-factor-card">
    <div class="section-head">
        <div><p class="eyebrow">ZWEI-FAKTOR-SCHUTZ</p><h2>Authenticator-Anmeldung</h2></div>
        <span class="job-status <?= !empty($twoFactorStatus['enabled']) ? 'status-completed' : 'status-running' ?>">
            <?= !empty($twoFactorStatus['enabled']) ? 'Aktiv' : 'Nicht aktiv' ?>
        </span>
    </div>

    <?php if (empty($twoFactorStatus['ready'])): ?>
        <div class="alert alert-warning"><span>!</span><p>Führe zuerst unter Einstellungen → System die aktuelle Zwei-Faktor- und Designmigration aus.</p></div>
    <?php else: ?>
        <?php if ($twoFactorRecoveryCodes !== []): ?>
            <div class="recovery-code-panel">
                <div>
                    <p class="eyebrow">NUR JETZT SICHTBAR</p>
                    <h3>Wiederherstellungscodes sicher speichern</h3>
                    <p>Jeder Code kann genau einmal verwendet werden. Speichere sie getrennt von deinem Passwort.</p>
                </div>
                <div class="recovery-code-grid" data-recovery-codes>
                    <?php foreach ($twoFactorRecoveryCodes as $recoveryCode): ?>
                        <code><?= e($recoveryCode) ?></code>
                    <?php endforeach; ?>
                </div>
                <div class="button-row">
                    <button class="button button-secondary" type="button" data-copy-recovery>Alle Codes kopieren</button>
                    <button class="button button-secondary" type="button" data-print-recovery>Codes drucken</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($twoFactorStatus['enabled'])): ?>
            <div class="two-factor-status-grid">
                <span><small>Status</small><strong>Geschützt</strong></span>
                <span><small>Aktiv seit</small><strong><?= e(utc_to_local($twoFactorStatus['enabled_at'])) ?></strong></span>
                <span><small>Reserve-Codes</small><strong><?= e($twoFactorStatus['recovery_codes_remaining']) ?> übrig</strong></span>
                <span><small>Letzte Nutzung</small><strong><?= e(utc_to_local($twoFactorStatus['last_used_at'])) ?></strong></span>
            </div>
            <div class="two-factor-actions">
                <form method="post" class="stack-form security-action-card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile-two-factor-recovery-regenerate">
                    <input type="hidden" name="return_page" value="profile">
                    <div><p class="eyebrow">RESERVE</p><h3>Neue Wiederherstellungscodes</h3><p class="muted">Alle bisherigen Codes werden sofort ungültig.</p></div>
                    <label><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                    <label><span>Aktueller Authenticator- oder Wiederherstellungscode *</span><input type="text" name="two_factor_code" maxlength="24" required autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"></label>
                    <button class="button button-secondary" type="submit" data-confirm="Alle bisherigen Wiederherstellungscodes ersetzen?">Neue Codes erstellen</button>
                </form>
                <form method="post" class="stack-form security-action-card danger-zone">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile-two-factor-disable">
                    <input type="hidden" name="return_page" value="profile">
                    <div><p class="eyebrow">DEAKTIVIEREN</p><h3>Zweiten Faktor entfernen</h3><p class="muted">Andere Sitzungen werden dabei beendet.</p></div>
                    <label><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                    <label><span>Aktueller Authenticator- oder Wiederherstellungscode *</span><input type="text" name="two_factor_code" maxlength="24" required autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"></label>
                    <button class="button button-danger-outline" type="submit" data-confirm="Zwei-Faktor-Authentifizierung wirklich deaktivieren?">Zwei-Faktor deaktivieren</button>
                </form>
            </div>
        <?php elseif ($twoFactorSetup): ?>
            <div class="two-factor-setup">
                <ol class="setup-steps">
                    <li><span>1</span><div><strong>Authenticator-App öffnen</strong><small>Füge ein neues zeitbasiertes Konto (TOTP) hinzu.</small></div></li>
                    <li><span>2</span><div><strong>Einrichtungsschlüssel eingeben</strong><small>Der Schlüssel bleibt lokal in deinem ModDesk und wird verschlüsselt gespeichert.</small></div></li>
                    <li><span>3</span><div><strong>Sechsstelligen Code bestätigen</strong><small>Danach erhältst du zehn einmalige Wiederherstellungscodes.</small></div></li>
                </ol>
                <div class="setup-secret-card">
                    <span>Einrichtungsschlüssel</span>
                    <code data-copy-source="two-factor-secret"><?= e($twoFactorSetup['formatted_secret']) ?></code>
                    <button class="button button-small button-secondary" type="button" data-copy-target="two-factor-secret">Schlüssel kopieren</button>
                </div>
                <details class="advanced-setup">
                    <summary>Erweiterte Einrichtung anzeigen</summary>
                    <label><span>Authenticator-URI</span><textarea readonly rows="3" data-copy-source="two-factor-uri"><?= e($twoFactorSetup['uri']) ?></textarea></label>
                    <button class="button button-small button-secondary" type="button" data-copy-target="two-factor-uri">URI kopieren</button>
                </details>
                <form method="post" class="form-grid two-factor-confirm-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile-two-factor-enable">
                    <input type="hidden" name="return_page" value="profile">
                    <label><span>Sechsstelliger Code *</span><input class="otp-input" type="text" name="two_factor_code" inputmode="numeric" pattern="[0-9 ]{6,8}" maxlength="8" required autocomplete="one-time-code" placeholder="000 000" data-otp-only></label>
                    <div class="form-actions align-end"><button class="button button-primary" type="submit">Aktivieren und Codes erzeugen</button></div>
                </form>
                <form method="post" class="top-gap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile-two-factor-cancel">
                    <input type="hidden" name="return_page" value="profile">
                    <button class="button button-small button-secondary" type="submit">Einrichtung abbrechen</button>
                </form>
            </div>
        <?php else: ?>
            <div class="two-factor-intro">
                <div class="security-symbol" aria-hidden="true">⌁</div>
                <div>
                    <h3>Schütze dein Konto mit einem zweiten Faktor.</h3>
                    <p class="muted">Nach Passwort oder Twitch-Login wird zusätzlich ein zeitbasierter Code aus deiner Authenticator-App verlangt.</p>
                </div>
            </div>
            <form method="post" class="form-grid top-gap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile-two-factor-begin">
                <input type="hidden" name="return_page" value="profile">
                <label><span>Aktuelles Passwort zur Einrichtung *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                <div class="form-actions align-end"><button class="button button-primary" type="submit">Zwei-Faktor einrichten</button></div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">SITZUNGEN</p><h3>Deine angemeldeten Geräte</h3></div><span class="count-chip"><?= count($profileSessions) ?></span></div>
    <?php if ($profileSessions === []): ?>
        <div class="empty-state"><p>Es wurden noch keine Sitzungsdaten gespeichert.</p></div>
    <?php else: ?>
        <div class="responsive-table"><table><thead><tr><th>Gerät</th><th>IP-Adresse</th><th>Letzte Aktivität</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($profileSessions as $session): ?>
            <tr>
                <td><small><?= e($session['user_agent'] ?: 'Unbekanntes Gerät') ?></small></td>
                <td data-label="IP"><?= e($session['ip_address'] ?: '–') ?></td>
                <td data-label="Aktivität"><?= e(date('d.m.Y H:i', (int) $session['last_activity'])) ?></td>
                <td data-label="Status"><span class="job-status <?= hash_equals((string) $session['id'], session_id()) ? 'status-completed' : 'status-running' ?>"><?= hash_equals((string) $session['id'], session_id()) ? 'Diese Sitzung' : 'Weitere Sitzung' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

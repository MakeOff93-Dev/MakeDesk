<?php
$activeBanSyncChannels = array_values(array_filter(
    $banSyncChannels,
    static fn (array $channel): bool => (int) $channel['enabled'] === 1,
));
$hasBanScope = $connection !== null && in_array('moderator:manage:banned_users', (array) $connection['scopes'], true);
$hasChannelCheckScope = $connection !== null && in_array('user:read:moderated_channels', (array) $connection['scopes'], true);
$validationLabels = [
    'verified' => 'Mod-Recht bestätigt',
    'denied' => 'Kein Mod-Recht erkannt',
    'unknown' => 'Noch ungeprüft',
];
$jobLabels = [
    'running' => 'Läuft',
    'completed' => 'Erfolgreich',
    'partial' => 'Teilweise',
    'failed' => 'Fehlgeschlagen',
];
?>

<div class="page-intro">
    <div>
        <p>Ein Twitch-User, mehrere Kanäle: Ban oder Unban einmal auslösen und pro Kanal nachvollziehen.</p>
    </div>
    <?php if ($connection && auth()->can('bansync.configure')): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ban-sync-verify">
            <input type="hidden" name="return_page" value="ban-sync">
            <button class="button button-secondary" type="submit">Mod-Rechte prüfen</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$connection): ?>
    <section class="connect-panel card">
        <div class="twitch-logo">⛔</div>
        <div>
            <p class="eyebrow">BANSYNC SETUP</p>
            <h2>Dein Modkonto verbinden</h2>
            <p>Ein einziges OAuth-Konto reicht. Es muss auf jedem Zielkanal Broadcaster oder Moderator sein; Passwörter oder Tokens der anderen Kanäle werden nicht benötigt.</p>
            <?php if (auth()->can('twitch.connect')): ?>
                <a class="button button-twitch <?= !twitch()->isConfigured() ? 'disabled' : '' ?>" href="<?= twitch()->isConfigured() ? e(url('twitch-connect', ['mode' => 'moderator'])) : '#' ?>">Twitch-Modkonto verbinden →</a>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="ban-sync-hero card">
        <div class="connection-profile">
            <?php if ($connection['profile_image_url']): ?>
                <img class="avatar avatar-large" src="<?= e($connection['profile_image_url']) ?>" alt="">
            <?php else: ?>
                <span class="avatar avatar-large avatar-text"><?= e(mb_strtoupper(mb_substr((string) $connection['display_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div>
                <span class="online-label"><i></i> AUSFÜHRENDES MODKONTO</span>
                <h3><?= e($connection['display_name']) ?></h3>
                <p>@<?= e($connection['login']) ?> bannt im eigenen Namen auf allen ausgewählten Kanälen.</p>
            </div>
        </div>
        <?php if (auth()->can('twitch.connect')): ?>
            <a class="button button-small button-secondary" href="<?= e(url('twitch-connect', ['mode' => 'moderator'])) ?>">Neu verbinden</a>
        <?php endif; ?>
    </section>

    <?php if (!$hasBanScope): ?>
        <div class="alert alert-danger"><span>!</span><p>Der verbundene Token darf keine Bans verwalten. Verbinde das Modkonto erneut und bestätige die angeforderten Rechte.</p></div>
    <?php elseif (!$hasChannelCheckScope): ?>
        <div class="alert alert-warning"><span>i</span><p>Ban und Unban können funktionieren, aber die automatische Mod-Rechteprüfung fehlt. Verbinde das Konto erneut, um <code>user:read:moderated_channels</code> zu erlauben.</p></div>
    <?php endif; ?>

    <section class="stat-grid ban-sync-stats">
        <article class="stat-card purple"><span>◉</span><strong><?= e($banSyncStats['channels']) ?></strong><small>AKTIVE KANÄLE</small></article>
        <article class="stat-card cyan"><span>↻</span><strong><?= e($banSyncStats['jobs']) ?></strong><small>SYNC-VORGÄNGE</small></article>
        <article class="stat-card green"><span>✓</span><strong><?= e($banSyncStats['successes']) ?></strong><small>ERFOLGREICHE KANALAKTIONEN</small></article>
        <article class="stat-card orange"><span>!</span><strong><?= e($banSyncStats['partial']) ?></strong><small>TEILERFOLGE</small></article>
    </section>

    <section class="ban-sync-layout">
        <article class="card ban-sync-console">
            <div class="section-head">
                <div><p class="eyebrow">LIVE-AKTION</p><h2>Ban auf Kanäle synchronisieren</h2></div>
                <span class="danger-chip">SOFORT BEI TWITCH</span>
            </div>

            <?php if (!auth()->can('bansync.execute')): ?>
                <p class="empty-copy">Deine ModDesk-Rolle darf keine Twitch-Aktionen ausführen.</p>
            <?php elseif ($activeBanSyncChannels === []): ?>
                <div class="inline-warning">Lege rechts zuerst mindestens einen aktiven Zielkanal an.</div>
            <?php else: ?>
                <form method="post" class="stack-form ban-sync-form" data-ban-sync-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ban-sync-execute">
                    <input type="hidden" name="return_page" value="ban-sync">

                    <div class="sync-action-options" role="radiogroup" aria-label="Aktion">
                        <label class="sync-action-option danger-option">
                            <input type="radio" name="sync_action" value="ban" checked>
                            <span><strong>Dauerhaft bannen</strong><small>User auf allen gewählten Kanälen sperren</small></span>
                        </label>
                        <label class="sync-action-option">
                            <input type="radio" name="sync_action" value="unban">
                            <span><strong>Ban aufheben</strong><small>Sperre auf allen gewählten Kanälen entfernen</small></span>
                        </label>
                    </div>

                    <label><span>Twitch-Login des Users *</span><input type="text" name="twitch_login" required autocomplete="off" placeholder="ohne @"></label>
                    <label><span>Begründung beim Ban *</span><textarea name="reason" rows="3" maxlength="500" data-sync-reason placeholder="Wird an Twitch übermittelt und im Banlog gespeichert."></textarea></label>

                    <fieldset class="sync-channel-picker">
                        <legend>Zielkanäle *</legend>
                        <?php foreach ($activeBanSyncChannels as $channel): ?>
                            <?php $validationStatus = isset($validationLabels[$channel['validation_status']]) ? $channel['validation_status'] : 'unknown'; ?>
                            <label class="sync-channel-option status-<?= e($validationStatus) ?>">
                                <input type="checkbox" name="channel_ids[]" value="<?= e($channel['id']) ?>" checked>
                                <?php if ($channel['profile_image_url']): ?>
                                    <img class="avatar avatar-small" src="<?= e($channel['profile_image_url']) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar avatar-small avatar-text"><?= e(mb_strtoupper(mb_substr((string) $channel['display_name'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <span class="sync-channel-copy"><strong><?= e($channel['display_name']) ?></strong><small>@<?= e($channel['login']) ?></small></span>
                                <span class="verification-label"><?= e($validationLabels[$validationStatus]) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <button class="button button-danger sync-submit" type="submit" <?= !$hasBanScope ? 'disabled' : '' ?> data-sync-submit data-confirm="Diesen User jetzt auf allen ausgewählten Twitch-Kanälen dauerhaft bannen?">Auf ausgewählten Kanälen ausführen</button>
                </form>
            <?php endif; ?>
        </article>

        <aside class="card ban-sync-channels">
            <div class="section-head">
                <div><p class="eyebrow">ZIELGRUPPE</p><h3>Verbundene Kanäle</h3></div>
                <span class="count-chip"><?= count($banSyncChannels) ?></span>
            </div>
            <p class="muted">Füge deinen Kanal und beispielsweise Dragoras07 hinzu. Dein verbundenes Konto braucht dort jeweils Mod-Recht.</p>

            <?php if (auth()->can('bansync.configure')): ?>
                <form method="post" class="inline-form wrap ban-sync-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ban-sync-channel-add">
                    <input type="hidden" name="return_page" value="ban-sync">
                    <input type="text" name="channel_login" required autocomplete="off" placeholder="z. B. dragoras07">
                    <button class="button button-secondary" type="submit">Kanal hinzufügen</button>
                </form>
            <?php endif; ?>

            <div class="configured-channel-list">
                <?php if ($banSyncChannels === []): ?><p class="empty-copy">Noch keine Kanäle eingerichtet.</p><?php endif; ?>
                <?php foreach ($banSyncChannels as $channel): ?>
                    <?php $validationStatus = isset($validationLabels[$channel['validation_status']]) ? $channel['validation_status'] : 'unknown'; ?>
                    <div class="configured-channel <?= (int) $channel['enabled'] === 1 ? '' : 'is-paused' ?>">
                        <div class="configured-channel-main">
                            <?php if ($channel['profile_image_url']): ?>
                                <img class="avatar avatar-small" src="<?= e($channel['profile_image_url']) ?>" alt="">
                            <?php else: ?>
                                <span class="avatar avatar-small avatar-text"><?= e(mb_strtoupper(mb_substr((string) $channel['display_name'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <span><strong><?= e($channel['display_name']) ?></strong><small>@<?= e($channel['login']) ?></small></span>
                        </div>
                        <span class="verification-badge status-<?= e($validationStatus) ?>"><?= e($validationLabels[$validationStatus]) ?></span>
                        <?php if (auth()->can('bansync.configure')): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="ban-sync-channel-toggle">
                                <input type="hidden" name="return_page" value="ban-sync">
                                <input type="hidden" name="channel_id" value="<?= e($channel['id']) ?>">
                                <input type="hidden" name="enabled" value="<?= (int) $channel['enabled'] === 1 ? '0' : '1' ?>">
                                <button class="button button-tiny button-secondary" type="submit"><?= (int) $channel['enabled'] === 1 ? 'Pausieren' : 'Aktivieren' ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (auth()->can('bansync.configure') && $banSyncChannels !== []): ?>
                <form method="post" class="top-gap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ban-sync-verify">
                    <input type="hidden" name="return_page" value="ban-sync">
                    <button class="button button-small button-secondary full-width" type="submit">Alle Mod-Rechte neu prüfen</button>
                </form>
            <?php endif; ?>
        </aside>
    </section>

    <?php if ($selectedBanSyncJob): ?>
        <?php $selectedStatus = isset($jobLabels[$selectedBanSyncJob['status']]) ? $selectedBanSyncJob['status'] : 'running'; ?>
        <section class="card sync-result-panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">KANALPROTOKOLL #<?= e($selectedBanSyncJob['id']) ?></p>
                    <h3><?= $selectedBanSyncJob['action'] === 'ban' ? 'Ban' : 'Unban' ?> für <?= e($selectedBanSyncJob['target_name']) ?> <small>@<?= e($selectedBanSyncJob['target_login']) ?></small></h3>
                </div>
                <span class="job-status status-<?= e($selectedStatus) ?>"><?= e($jobLabels[$selectedStatus]) ?></span>
            </div>
            <?php if ($selectedBanSyncJob['reason']): ?><p class="sync-reason"><strong>Begründung:</strong> <?= e($selectedBanSyncJob['reason']) ?></p><?php endif; ?>
            <div class="sync-result-list">
                <?php foreach ($selectedBanSyncResults as $result): ?>
                    <div class="sync-result-row <?= (int) $result['success'] === 1 ? 'is-success' : 'is-failure' ?>">
                        <span class="result-icon"><?= (int) $result['success'] === 1 ? '✓' : '!' ?></span>
                        <span><strong><?= e($result['channel_name']) ?></strong><small>@<?= e($result['channel_login']) ?></small></span>
                        <span class="result-message">
                            <?php if ((int) $result['success'] === 1): ?>Bei Twitch erfolgreich ausgeführt<?php else: ?><?= e($result['error_message'] ?: 'Twitch-Aktion fehlgeschlagen.') ?><?php endif; ?>
                            <?php if ($result['http_status']): ?><small>HTTP <?= e($result['http_status']) ?></small><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card table-card banlog-card">
        <div class="section-head"><div><p class="eyebrow">HISTORIE</p><h3>BanSync-Log</h3></div><span class="count-chip"><?= count($banSyncJobs) ?></span></div>
        <?php if ($banSyncJobs === []): ?>
            <div class="empty-state"><span class="empty-icon">⛔</span><h3>Noch kein BanSync</h3><p>Der erste Sammel-Ban oder -Unban erscheint anschließend hier.</p></div>
        <?php else: ?>
            <div class="responsive-table">
                <table>
                    <thead><tr><th>Ziel</th><th>Aktion</th><th>Ergebnis</th><th>Ausgeführt von</th><th>Zeit</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($banSyncJobs as $job): ?>
                        <?php $jobStatus = isset($jobLabels[$job['status']]) ? $job['status'] : 'running'; ?>
                        <tr>
                            <td><strong><?= e($job['target_name']) ?></strong><small>@<?= e($job['target_login']) ?><?= $job['reason'] ? ' · ' . e($job['reason']) : '' ?></small></td>
                            <td data-label="Aktion"><span class="badge <?= $job['action'] === 'ban' ? 'badge-danger' : 'badge-planned' ?>"><?= $job['action'] === 'ban' ? 'Ban' : 'Unban' ?></span></td>
                            <td data-label="Ergebnis"><span class="job-status status-<?= e($jobStatus) ?>"><?= e($jobLabels[$jobStatus]) ?></span><small><?= e($job['success_count']) ?> ✓ / <?= e($job['failure_count']) ?> Fehler</small></td>
                            <td data-label="Ausgeführt von"><?= e($job['requester_name']) ?></td>
                            <td data-label="Zeit"><?= e(utc_to_local($job['created_at'])) ?></td>
                            <td data-label="Details"><a class="button button-tiny button-secondary" href="<?= e(url('ban-sync', ['job' => $job['id']])) ?>">Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

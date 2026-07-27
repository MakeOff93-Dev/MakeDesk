<?php
$checkCount = count($securityChecks);
$passedChecks = count(array_filter($securityChecks, static fn (array $check): bool => $check['ok']));
?>

<div class="page-intro">
    <div><p>Systemschutz, fehlgeschlagene Zugriffe, IP-Sperren und aktive Panel-Sitzungen an einer Stelle prüfen.</p></div>
    <span class="badge"><?= $passedChecks ?>/<?= $checkCount ?> PRÜFUNGEN OK</span>
</div>

<section class="security-check-grid">
    <?php foreach ($securityChecks as $check): ?>
        <article class="card security-check <?= $check['ok'] ? 'passed' : e($check['severity']) ?>">
            <span class="security-check-icon"><?= $check['ok'] ? '✓' : '!' ?></span>
            <div><h3><?= e($check['label']) ?></h3><p><?= e($check['message']) ?></p></div>
        </article>
    <?php endforeach; ?>
</section>

<?php if (auth()->can('security.ip.manage')): ?>
<section class="card settings-section">
    <div class="section-head"><div><p class="eyebrow">ZUGRIFFSSCHUTZ</p><h2>IP-Adresse sperren</h2></div></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="security-ip-block"><input type="hidden" name="return_page" value="security">
        <label><span>IP-Adresse *</span><input type="text" name="ip_address" required maxlength="45" placeholder="203.0.113.10"></label>
        <label><span>Dauer</span><select name="block_minutes"><option value="15">15 Minuten</option><option value="60">1 Stunde</option><option value="1440">24 Stunden</option><option value="10080">7 Tage</option><option value="0">Dauerhaft</option></select></label>
        <label class="span-2"><span>Begründung *</span><input type="text" name="block_reason" required maxlength="500"></label>
        <label class="span-2 critical-confirm"><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
        <div class="span-2 form-actions"><button class="button button-danger-outline" type="submit">IP sperren</button></div>
    </form>
</section>
<?php endif; ?>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">SPERREN</p><h3>IP-Sperrliste</h3></div><span class="count-chip"><?= count($ipBlocks) ?></span></div>
    <?php if ($ipBlocks === []): ?>
        <div class="empty-state"><span class="empty-icon">⬡</span><h3>Keine IP-Sperren</h3></div>
    <?php else: ?>
        <div class="responsive-table"><table><thead><tr><th>IP</th><th>Grund</th><th>Quelle</th><th>Ablauf</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($ipBlocks as $block): ?><tr>
            <td><code><?= e($block['ip_address']) ?></code></td>
            <td data-label="Grund"><?= e($block['reason']) ?></td>
            <td data-label="Quelle"><?= e($block['source'] === 'automatic' ? 'Automatisch' : ($block['created_by_name'] ?? 'Manuell')) ?></td>
            <td data-label="Ablauf"><?= e($block['expires_at'] ? utc_to_local($block['expires_at']) : 'Dauerhaft') ?></td>
            <td data-label="Status"><span class="status-label <?= (int) $block['active'] === 1 ? 'inactive' : 'active' ?>"><i></i><?= (int) $block['active'] === 1 ? 'Gesperrt' : 'Aufgehoben' ?></span></td>
            <td data-label="Aktion"><?php if ((int) $block['active'] === 1 && auth()->can('security.ip.manage')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="security-ip-unblock"><input type="hidden" name="return_page" value="security"><input type="hidden" name="block_id" value="<?= e($block['id']) ?>"><input class="mini-password" type="password" name="confirm_password" required placeholder="Passwort" autocomplete="current-password"><button class="button button-small button-secondary" type="submit">Aufheben</button></form><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">SITZUNGEN</p><h3>Aktive Panel-Sitzungen</h3></div><span class="count-chip"><?= count($activeSessions) ?></span></div>
    <div class="responsive-table"><table><thead><tr><th>Benutzer</th><th>IP</th><th>Gerät</th><th>Zuletzt aktiv</th><th></th></tr></thead><tbody>
    <?php foreach ($activeSessions as $session): ?><tr>
        <td><strong><?= e($session['display_name'] ?? 'Nicht angemeldet') ?></strong><?= hash_equals((string) $session['id'], session_id()) ? '<small>Diese Sitzung</small>' : '' ?></td>
        <td data-label="IP"><code><?= e($session['ip_address'] ?: '–') ?></code></td>
        <td data-label="Gerät"><small><?= e($session['user_agent'] ?: '–') ?></small></td>
        <td data-label="Aktiv"><?= e(date('d.m.Y H:i', (int) $session['last_activity'])) ?></td>
        <td data-label="Aktion"><?php if (!hash_equals((string) $session['id'], session_id()) && auth()->can('security.sessions.manage')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="security-session-end"><input type="hidden" name="return_page" value="security"><input type="hidden" name="session_id" value="<?= e($session['id']) ?>"><input class="mini-password" type="password" name="confirm_password" required placeholder="Passwort" autocomplete="current-password"><button class="button button-small button-danger-outline" type="submit">Beenden</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">SICHERHEITSPROTOKOLL</p><h3>Letzte Ereignisse</h3></div><span class="count-chip"><?= count($securityEvents) ?></span></div>
    <?php if ($securityEvents === []): ?>
        <div class="empty-state"><span class="empty-icon">⌁</span><h3>Noch keine Ereignisse</h3></div>
    <?php else: ?>
        <div class="responsive-table"><table><thead><tr><th>Zeit</th><th>Stufe</th><th>Ereignis</th><th>Person</th><th>IP</th><th>Meldung</th></tr></thead><tbody>
        <?php foreach ($securityEvents as $event): ?><tr>
            <td><?= e(utc_to_local($event['created_at'])) ?></td>
            <td data-label="Stufe"><span class="job-status security-<?= e($event['severity']) ?>"><?= e(strtoupper($event['severity'])) ?></span></td>
            <td data-label="Ereignis"><code><?= e($event['event_key']) ?></code></td>
            <td data-label="Person"><?= e($event['user_name'] ?? $event['username'] ?? 'System') ?></td>
            <td data-label="IP"><small><?= e($event['ip_address'] ?: '–') ?></small></td>
            <td data-label="Meldung"><?= e($event['message']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

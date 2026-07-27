<?php
$statusLabels = ['running' => 'Läuft', 'completed' => 'Erfolgreich', 'failed' => 'Fehler'];
$sourceLabels = ['manual' => 'Manuell', 'update' => 'Vor Update', 'migration' => 'Vor Migration', 'restore' => 'Vor Wiederherstellung'];
?>

<div class="page-intro">
    <div><p>MySQL-Daten, Konfiguration und Projektdateien sichern, herunterladen oder kontrolliert wiederherstellen.</p></div>
    <span class="badge"><?= count($backupRows) ?> BACKUPS</span>
</div>

<?php if (auth()->can('backups.create')): ?>
<section class="card settings-section">
    <div class="section-head"><div><p class="eyebrow">NEUE SICHERUNG</p><h2>Backup erstellen</h2></div><span class="job-status status-completed">Bereit</span></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="backup-create"><input type="hidden" name="return_page" value="backups">
        <label><span>Bezeichnung</span><input type="text" name="backup_label" maxlength="190" placeholder="Vor größerer Änderung"></label>
        <label class="check-label field-bottom"><input type="checkbox" name="include_files" value="1" checked><span>Projektdateien und .env mitsichern</span></label>
        <div class="span-2 settings-note">Temporäre Dateien, frühere Backups, Git-Daten und laufende Sitzungen werden nicht in die Sicherung aufgenommen. Integrationsdaten, Branding, Rollen und Audit-Logs liegen in MySQL und sind enthalten.</div>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit">Backup jetzt erstellen</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($backupRows === []): ?>
    <section class="card empty-state"><span class="empty-icon">◒</span><h3>Noch kein Backup</h3><p>Vor Updates und Migrationen legt ModDesk künftig automatisch eine vollständige Sicherung an.</p></section>
<?php else: ?>
    <section class="backup-grid">
        <?php foreach ($backupRows as $backup): ?>
            <article class="card backup-card status-card-<?= e($backup['status']) ?>">
                <header>
                    <div><p class="eyebrow"><?= e($sourceLabels[$backup['trigger_source']] ?? $backup['trigger_source']) ?></p><h3><?= e($backup['label']) ?></h3></div>
                    <span class="job-status status-<?= e($backup['status']) ?>"><?= e($statusLabels[$backup['status']] ?? $backup['status']) ?></span>
                </header>
                <dl class="meta-list">
                    <div><dt>Erstellt</dt><dd><?= e(utc_to_local($backup['created_at'])) ?></dd></div>
                    <div><dt>Umfang</dt><dd><?= (int) $backup['include_files'] === 1 ? 'Datenbank + Dateien' : 'Datenbank' ?></dd></div>
                    <div><dt>Größe</dt><dd><?= e(format_bytes((int) ($backup['size_bytes'] ?? 0))) ?></dd></div>
                    <div><dt>Von</dt><dd><?= e($backup['created_by_name'] ?? 'System') ?></dd></div>
                </dl>
                <?php if ($backup['checksum_sha256']): ?><small class="mono-copy">SHA-256 <?= e(substr((string) $backup['checksum_sha256'], 0, 20)) ?>…</small><?php endif; ?>
                <?php if ($backup['error_message']): ?><div class="alert alert-danger top-gap"><span>!</span><p><?= e($backup['error_message']) ?></p></div><?php endif; ?>
                <?php if ($backup['last_restored_at']): ?><div class="settings-note top-gap">Zuletzt wiederhergestellt: <?= e(utc_to_local($backup['last_restored_at'])) ?><?= $backup['restored_by_name'] ? ' von ' . e($backup['restored_by_name']) : '' ?></div><?php endif; ?>

                <?php if ($backup['status'] === 'completed'): ?>
                    <div class="button-row top-gap">
                        <?php if (auth()->can('backups.download')): ?><a class="button button-small button-secondary" href="<?= e(url('backup-download', ['id' => $backup['id']])) ?>">Herunterladen</a><?php endif; ?>
                    </div>
                    <?php if (auth()->can('backups.restore')): ?>
                        <form method="post" class="form-grid compact-form top-gap">
                            <?= csrf_field() ?><input type="hidden" name="action" value="backup-restore"><input type="hidden" name="return_page" value="backups"><input type="hidden" name="backup_id" value="<?= e($backup['id']) ?>">
                            <label><span>Passwort für Wiederherstellung</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                            <button class="button button-small button-danger-outline" type="submit" data-confirm="Aktuellen Stand vorsichtshalber sichern und dieses Backup anschließend vollständig wiederherstellen?">Wiederherstellen</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (auth()->can('backups.delete')): ?><form method="post" class="form-grid compact-form top-gap">
                    <?= csrf_field() ?><input type="hidden" name="action" value="backup-delete"><input type="hidden" name="return_page" value="backups"><input type="hidden" name="backup_id" value="<?= e($backup['id']) ?>">
                    <label><span>Passwort zum Löschen</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                    <button class="button button-small button-danger-outline" type="submit" data-confirm="Dieses Backup dauerhaft löschen? Danach kann es nicht wiederhergestellt werden.">Backup löschen</button>
                </form><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

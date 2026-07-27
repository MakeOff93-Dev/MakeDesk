<?php
$moduleEditLinks = [
    'twitch' => url('settings', ['section' => 'twitch']),
    'discord' => url('settings', ['section' => 'discord']),
    'design' => url('design'),
    'news' => url('news'),
    'ideas' => url('ideas'),
    'notes' => url('notes'),
    'links' => url('links'),
    'ban-sync' => url('ban-sync'),
    'cases' => url('cases'),
    'team' => url('team'),
    'backups' => url('backups'),
    'security' => url('security'),
    'audit' => url('audit'),
];
$canConfigureModules = auth()->can('modules.configure');
$canInstallModules = auth()->can('modules.install');
$canRemoveModules = auth()->can('modules.remove');
?>

<div class="page-intro">
    <div><p>Panel-Funktionen aktivieren, deaktivieren, konfigurieren und vertrauenswürdige Zusatzmodule als ZIP installieren.</p></div>
    <span class="badge">MODULE</span>
</div>

<?php if (!$moduleSystemReady): ?>
    <div class="alert alert-warning"><span>!</span><p>Die Modulverwaltung benötigt noch die aktuelle Datenbankmigration. Öffne „Einstellungen → System“ und führe den Migrator aus.</p></div>
<?php endif; ?>

<section class="module-grid">
    <?php foreach ($moduleRows as $module): ?>
        <article class="card module-card <?= (int) $module['enabled'] === 1 ? 'enabled' : 'disabled' ?>">
            <header><span class="module-source"><?= ($module['source'] ?? '') === 'custom' ? 'ZIP' : 'CORE' ?></span><span class="status-dot <?= (int) $module['enabled'] === 1 ? 'online' : '' ?>"></span></header>
            <h3><?= e($module['name']) ?></h3>
            <p><?= e($module['description'] ?: 'Keine Beschreibung hinterlegt.') ?></p>
            <small><code><?= e($module['module_key']) ?></code> · v<?= e($module['version']) ?></small>
            <div class="module-actions">
                <?php if ($canConfigureModules): ?>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="module-toggle"><input type="hidden" name="return_page" value="modules"><input type="hidden" name="module_key" value="<?= e($module['module_key']) ?>"><input type="hidden" name="enabled" value="<?= (int) $module['enabled'] === 1 ? '0' : '1' ?>">
                    <button class="button button-small <?= (int) $module['enabled'] === 1 ? 'button-danger-outline' : 'button-primary' ?>" type="submit" <?= !$moduleSystemReady || ((int) ($module['protected'] ?? 0) === 1 && (int) $module['enabled'] === 1) ? 'disabled' : '' ?>><?= (int) $module['enabled'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                </form>
                <?php endif; ?>
                <?php if ($canConfigureModules || ($canRemoveModules && ($module['source'] ?? '') === 'custom')): ?>
                <a class="button button-small button-secondary" href="<?= e(url('modules', ['edit' => $module['module_key']])) ?>">Bearbeiten</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($selectedModule && ($canConfigureModules || ($canRemoveModules && ($selectedModule['source'] ?? '') === 'custom'))): ?>
<section class="card settings-section module-editor" id="module-editor">
    <div class="section-head"><div><p class="eyebrow">MODUL BEARBEITEN</p><h2><?= e($selectedModule['name']) ?></h2></div><a class="button button-small button-secondary" href="<?= e(url('modules')) ?>">Schließen</a></div>
    <p class="muted"><?= e($selectedModule['description']) ?></p>
    <?php $configurationFields = modules()->configurationFields($selectedModule); ?>
    <?php if ($configurationFields !== [] && $canConfigureModules): ?>
        <form method="post" class="form-grid top-gap">
            <?= csrf_field() ?><input type="hidden" name="action" value="module-config-save"><input type="hidden" name="return_page" value="modules"><input type="hidden" name="module_key" value="<?= e($selectedModule['module_key']) ?>">
            <?php foreach ($configurationFields as $field): ?>
                <?php $fieldValue = $moduleConfiguration[$field['key']] ?? ['value' => '', 'secret_set' => false]; ?>
                <?php if ($field['type'] === 'boolean'): ?>
                    <label class="check-label"><input type="checkbox" name="module_settings[<?= e($field['key']) ?>]" value="1" <?= filter_var($fieldValue['value'], FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>><span><?= e($field['label']) ?></span></label>
                <?php elseif ($field['type'] === 'select'): ?>
                    <label><span><?= e($field['label']) ?></span><select name="module_settings[<?= e($field['key']) ?>]"><?php foreach ($field['options'] as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string) $fieldValue['value'] === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select></label>
                <?php else: ?>
                    <label><span><?= e($field['label']) ?></span><input type="<?= $field['type'] === 'password' ? 'password' : ($field['type'] === 'number' ? 'number' : ($field['type'] === 'url' ? 'url' : 'text')) ?>" name="module_settings[<?= e($field['key']) ?>]" value="<?= $field['type'] === 'password' ? '' : e($fieldValue['value']) ?>" placeholder="<?= $field['type'] === 'password' && $fieldValue['secret_set'] ? 'Gespeichert – leer lassen zum Behalten' : '' ?>"></label>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="span-2 form-actions"><button class="button button-primary" type="submit">Moduleinstellungen speichern</button></div>
        </form>
    <?php elseif (isset($moduleEditLinks[$selectedModule['module_key']]) && $canConfigureModules): ?>
        <div class="settings-note top-gap">Dieses eingebaute Modul wird in seinem normalen Panel-Bereich bearbeitet.</div>
        <a class="button button-primary top-gap" href="<?= e($moduleEditLinks[$selectedModule['module_key']]) ?>">Moduleinstellungen öffnen</a>
    <?php elseif ($canConfigureModules): ?>
        <div class="settings-note top-gap">Dieses Modul besitzt keine eigenen Einstellungsfelder. Aktivierung und Navigation können hier beziehungsweise im Design-Editor geändert werden.</div>
    <?php endif; ?>

    <?php if (($selectedModule['source'] ?? '') === 'custom' && $canRemoveModules): ?>
        <form method="post" class="top-gap">
            <?= csrf_field() ?><input type="hidden" name="action" value="module-remove"><input type="hidden" name="return_page" value="modules"><input type="hidden" name="module_key" value="<?= e($selectedModule['module_key']) ?>">
            <label class="critical-confirm top-gap"><span>Aktuelles Passwort *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
            <button class="button button-danger-outline" type="submit" data-confirm="Dieses Zusatzmodul entfernen? Sein Ordner wird vorher unter storage/module-backups gesichert.">Zusatzmodul entfernen</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($canInstallModules): ?>
<section class="card settings-section">
    <div class="section-head"><div><p class="eyebrow">ERWEITERN</p><h2>Neues Modul hochladen</h2></div><span class="job-status <?= $zipAvailable && $moduleSystemReady ? 'status-completed' : 'status-failed' ?>"><?= !$zipAvailable ? 'ZIP fehlt' : ($moduleSystemReady ? 'Bereit' : 'Migration fehlt') ?></span></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="module-upload"><input type="hidden" name="return_page" value="modules">
        <label class="span-2 upload-field"><span>Modul-ZIP mit module.json</span><input type="file" name="module_package" accept="application/zip,.zip" required data-file-input><small data-file-status>Noch keine Datei ausgewählt.</small></label>
        <label class="span-2 critical-confirm"><span>Aktuelles Passwort zur Installation *</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
        <div class="span-2 settings-note">Ein PHP-Modul läuft mit denselben Serverrechten wie ModDesk und kann auf Datenbank und Geheimnisse zugreifen. Installiere deshalb ausschließlich selbst erstellte oder vollständig vertrauenswürdige Pakete. Pfade, Größen, Dateitypen, Manifest und symbolische Links werden vor der Installation geprüft.</div>
        <?php if (!$zipAvailable || !$moduleSystemReady): ?><div class="span-2 alert alert-warning"><span>!</span><p>Die Datei kann ausgewählt werden. Die Installation startet erst, wenn <?= !$zipAvailable ? 'die PHP-ZIP-Erweiterung aktiviert' : 'die offene Datenbankmigration ausgeführt' ?> wurde.</p></div><?php endif; ?>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit" data-confirm="Dieses Modul enthält ausführbaren PHP-Code. Vertrauenswürdiges Paket jetzt installieren und aktivieren?">Modul prüfen & installieren</button></div>
    </form>
</section>
<?php endif; ?>

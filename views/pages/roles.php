<?php
$permissionsByCategory = [];
foreach ($permissions as $permission) {
    $permissionsByCategory[(string) $permission['category']][] = $permission;
}
$editingKey = (string) ($editRole['role_key'] ?? '');
$assigned = $editingKey !== '' ? ($permissionAssignments[$editingKey] ?? []) : [];
$visiblePermissionKeys = array_values(array_map(
    static fn (array $permission): string => (string) $permission['permission_key'],
    $permissions,
));
$canEditForm = $editRole ? auth()->can('roles.edit') : auth()->can('roles.create');
?>

<div class="page-intro">
    <div><p>Detaillierte Panel-Rechte pro Rolle verwalten. Die geschützte Owner-Rolle besitzt immer Vollzugriff.</p></div>
    <?php if (auth()->can('roles.create')): ?><a class="button button-primary" href="<?= e(url('roles') . '#role-form') ?>">+ Rolle erstellen</a><?php endif; ?>
</div>

<?php if ($canEditForm): ?>
<section class="card form-card" id="role-form">
    <div class="section-head">
        <div><p class="eyebrow"><?= $editRole ? 'ROLLE BEARBEITEN' : 'NEUE ROLLE' ?></p><h3><?= e($editRole['name'] ?? 'Eigene Rolle erstellen') ?></h3></div>
        <?php if ($editRole): ?><a href="<?= e(url('roles')) ?>">Abbrechen ×</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="role-save">
        <input type="hidden" name="return_page" value="roles">
        <input type="hidden" name="original_role_key" value="<?= e($editingKey) ?>">
        <label>
            <span>Technischer Schlüssel *</span>
            <input type="text" name="role_key" required minlength="2" maxlength="80" pattern="[a-z][a-z0-9_.-]+"
                   value="<?= e($editingKey) ?>" <?= $editRole ? 'readonly' : '' ?> placeholder="support-team">
        </label>
        <label><span>Anzeigename *</span><input type="text" name="role_name" required maxlength="120" value="<?= e($editRole['name'] ?? '') ?>"></label>
        <label class="span-2"><span>Beschreibung</span><textarea name="role_description" maxlength="500" rows="3"><?= e($editRole['description'] ?? '') ?></textarea></label>

        <?php if (!empty($editRole['is_owner'])): ?>
            <div class="span-2 alert alert-warning"><span>!</span><p>Die Owner-Rolle besitzt aus Sicherheitsgründen immer alle Rechte. Einzelne Häkchen sind für diese Rolle nicht abschaltbar.</p></div>
        <?php else: ?>
            <div class="span-2 permission-editor">
                <div class="permission-editor-toolbar">
                    <strong>Einzelrechte</strong>
                    <div class="button-row">
                        <button class="button button-small button-secondary" type="button" data-permission-set="all">Alle einschalten</button>
                        <button class="button button-small button-secondary" type="button" data-permission-set="none">Alle ausschalten</button>
                    </div>
                </div>
                <?php foreach ($permissionsByCategory as $category => $categoryPermissions): ?>
                    <?php $categoryKey = 'permission-category-' . substr(hash('sha256', (string) $category), 0, 12); ?>
                    <fieldset data-permission-category="<?= e($categoryKey) ?>">
                        <legend>
                            <span><?= e($category) ?></span>
                            <span class="permission-category-actions">
                                <button type="button" data-permission-set="all" data-permission-scope="<?= e($categoryKey) ?>">Alle an</button>
                                <button type="button" data-permission-set="none" data-permission-scope="<?= e($categoryKey) ?>">Alle aus</button>
                            </span>
                        </legend>
                        <?php foreach ($categoryPermissions as $permission): ?>
                            <label class="permission-option">
                                <input type="checkbox" name="permissions[]" value="<?= e($permission['permission_key']) ?>"
                                    <?= in_array((string) $permission['permission_key'], $assigned, true) ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($permission['name']) ?><?= (int) $permission['sensitive'] === 1 ? ' · geschützt' : '' ?></strong>
                                    <small><?= e($permission['description']) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label class="span-2 critical-confirm">
            <span>Aktuelles Passwort zur Bestätigung *</span>
            <input type="password" name="confirm_password" required autocomplete="current-password">
            <small>Rollenänderungen werden vollständig protokolliert.</small>
        </label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit">Rolle und Rechte speichern</button></div>
    </form>
</section>
<?php elseif ($editRole): ?>
    <div class="alert alert-warning"><span>!</span><p>Du darfst Rollen ansehen, aber diese Rolle nicht bearbeiten.</p></div>
<?php endif; ?>

<section class="role-admin-grid">
    <?php foreach ($roles as $role): ?>
        <article class="card role-admin-card <?= (int) $role['is_owner'] === 1 ? 'owner-role' : '' ?>">
            <header>
                <div><span class="role-dot <?= e($role['role_key']) ?>"></span><h3><?= e($role['name']) ?></h3></div>
                <span class="badge"><?= (int) $role['protected'] === 1 ? 'GESCHÜTZT' : 'EIGEN' ?></span>
            </header>
            <p><?= e($role['description'] ?: 'Keine Beschreibung hinterlegt.') ?></p>
            <?php $visibleAssigned = array_intersect($permissionAssignments[$role['role_key']] ?? [], $visiblePermissionKeys); ?>
            <small><code><?= e($role['role_key']) ?></code> · <?= (int) ($role['user_count'] ?? 0) ?> Benutzer · <?= (int) $role['is_owner'] === 1 ? 'Vollzugriff' : count($visibleAssigned) . ' Einzelrechte' ?></small>
            <div class="button-row top-gap">
                <?php if (auth()->can('roles.edit')): ?><a class="button button-small button-secondary" href="<?= e(url('roles', ['edit' => $role['role_key']]) . '#role-form') ?>">Bearbeiten</a><?php endif; ?>
            </div>
            <?php if ((int) $role['protected'] !== 1 && auth()->can('roles.delete')): ?>
                <form method="post" class="form-grid compact-form top-gap">
                    <?= csrf_field() ?><input type="hidden" name="action" value="role-delete"><input type="hidden" name="return_page" value="roles"><input type="hidden" name="role_key" value="<?= e($role['role_key']) ?>">
                    <label><span>Passwort</span><input type="password" name="confirm_password" required autocomplete="current-password"></label>
                    <button class="button button-small button-danger-outline" type="submit" data-confirm="Diese Rolle endgültig löschen? Das geht nur, wenn kein Benutzer sie verwendet.">Rolle löschen</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

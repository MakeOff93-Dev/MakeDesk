<?php
$roleLabels = [];
foreach ($roles as $roleDefinition) {
    $roleLabels[(string) $roleDefinition['role_key']] = (string) $roleDefinition['name'];
}
$canEditForm = $editMember ? auth()->can('users.edit') : auth()->can('users.create');
?>
<div class="page-intro"><div><p>Lokale Panel-Zugänge verwalten. Rollen und Detailrechte sind unabhängig von Twitch-Rollen.</p></div><div class="button-row"><?php if (auth()->can('roles.view')): ?><a class="button button-secondary" href="<?= e(url('roles')) ?>">Rollen & Rechte</a><?php endif; ?><?php if (auth()->can('users.create')): ?><a class="button button-primary" href="#team-form">+ Zugang anlegen</a><?php endif; ?></div></div>

<?php if ($canEditForm): ?>
<section class="card form-card" id="team-form">
    <div class="section-head"><div><p class="eyebrow"><?= $editMember ? 'BEARBEITEN' : 'NEUER ZUGANG' ?></p><h3><?= $editMember ? e($editMember['display_name']) : 'Teammitglied hinzufügen' ?></h3></div><?php if ($editMember): ?><a href="<?= e(url('team')) ?>">Abbrechen ×</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="team-save"><input type="hidden" name="return_page" value="team"><input type="hidden" name="id" value="<?= e($editMember['id'] ?? 0) ?>">
        <label><span>Benutzername *</span><input type="text" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.-]+" value="<?= e($editMember['username'] ?? '') ?>"></label>
        <label><span>Anzeigename *</span><input type="text" name="display_name" required maxlength="100" value="<?= e($editMember['display_name'] ?? '') ?>"></label>
        <label><span>E-Mail</span><input type="email" name="email" maxlength="190" value="<?= e($editMember['email'] ?? '') ?>"></label>
        <label><span>Rolle</span><select name="role"><?php foreach ($roles as $roleDefinition): ?><?php if ((int) $roleDefinition['is_owner'] === 1 && !auth()->roleIsOwner()) continue; ?><?php if (!auth()->can('roles.assign') && $editMember && ($editMember['role'] ?? '') !== $roleDefinition['role_key']) continue; ?><?php if (!auth()->can('roles.assign') && !$editMember && $roleDefinition['role_key'] !== 'viewer') continue; ?><option value="<?= e($roleDefinition['role_key']) ?>" <?= ($editMember['role'] ?? 'viewer') === $roleDefinition['role_key'] ? 'selected' : '' ?>><?= e($roleDefinition['name']) ?></option><?php endforeach; ?></select></label>
        <?php if (!$editMember || auth()->can('users.password.reset')): ?><label><span>Passwort <?= $editMember ? '(leer = unverändert)' : '*' ?></span><input type="password" name="password" <?= $editMember ? '' : 'required' ?> minlength="12" autocomplete="new-password"></label><?php else: ?><input type="hidden" name="password" value=""><?php endif; ?>
        <?php if (!$editMember || auth()->can('users.disable')): ?><label class="check-label field-bottom"><input type="checkbox" name="active" value="1" <?= !isset($editMember['active']) || $editMember['active'] ? 'checked' : '' ?>><span>Zugang aktiv</span></label><?php else: ?><input type="hidden" name="active" value="<?= !empty($editMember['active']) ? '1' : '0' ?>"><?php endif; ?>
        <label class="span-2 critical-confirm"><span>Dein aktuelles Passwort zur Bestätigung *</span><input type="password" name="confirm_password" required autocomplete="current-password"><small>Benutzer-, Rollen- und Passwortänderungen sind sicherheitskritisch und werden protokolliert.</small></label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editMember ? 'Zugang aktualisieren' : 'Zugang erstellen' ?></button></div>
    </form>
</section>
<?php endif; ?>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">PANEL-ZUGÄNGE</p><h3><?= count($members) ?> Teammitglieder</h3></div></div>
    <div class="responsive-table"><table><thead><tr><th>Teammitglied</th><th>Rolle</th><th>Status</th><th>Letzter Login</th><th>Erstellt</th><th></th></tr></thead><tbody><?php foreach ($members as $member): ?><tr>
        <?php $memberAvatar = profile_avatar_url((int) $member['id']); ?>
        <td data-label="Teammitglied"><div class="table-user"><?php if ($memberAvatar): ?><img class="avatar avatar-small avatar-image" src="<?= e($memberAvatar) ?>" alt=""><?php else: ?><span class="avatar avatar-small avatar-text"><?= e(mb_strtoupper(mb_substr((string) $member['display_name'], 0, 1))) ?></span><?php endif; ?><span><strong><?= e($member['display_name']) ?></strong><small>@<?= e($member['username']) ?><?= $member['email'] ? ' · ' . e($member['email']) : '' ?></small></span></div></td>
        <td data-label="Rolle"><span class="badge role-<?= e($member['role']) ?>"><?= e($roleLabels[$member['role']] ?? $member['role']) ?></span></td>
        <td data-label="Status"><span class="status-label <?= $member['active'] ? 'active' : 'inactive' ?>"><i></i><?= $member['active'] ? 'Aktiv' : 'Deaktiviert' ?></span></td>
        <td data-label="Letzter Login"><?= e(utc_to_local($member['last_login_at'])) ?></td><td data-label="Erstellt"><?= e(utc_to_local($member['created_at'])) ?></td><td data-label="Aktion"><?php if (auth()->can('users.edit')): ?><a class="icon-button" href="<?= e(url('team', ['edit' => $member['id']]) . '#team-form') ?>">✎</a><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
</section>

<section class="permission-grid">
    <?php foreach ($roles as $roleDefinition): ?><article class="card"><span class="role-dot <?= e($roleDefinition['role_key']) ?>"></span><h3><?= e($roleDefinition['name']) ?></h3><p><?= e($roleDefinition['description'] ?: 'Keine Beschreibung hinterlegt.') ?></p></article><?php endforeach; ?>
</section>

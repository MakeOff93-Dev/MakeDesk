<div class="page-intro"><div><p>Von der API geladene Twitch-Profile mit Notizen, Fällen und Aktionshistorie.</p></div><?php if (auth()->can('twitch.users.lookup')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-lookup"><input type="hidden" name="return_page" value="twitch-users"><input type="text" name="twitch_login" required placeholder="Twitch-Login suchen"><button class="button button-primary" type="submit">Suchen</button></form><?php endif; ?></div>

<?php if ($selectedUser): ?>
<section class="profile-panel card">
    <div class="profile-main">
        <?php if ($selectedUser['profile_image_url']): ?><img class="avatar profile-avatar" src="<?= e($selectedUser['profile_image_url']) ?>" alt=""><?php else: ?><span class="avatar profile-avatar avatar-text"><?= e(mb_strtoupper(mb_substr((string) $selectedUser['display_name'], 0, 1))) ?></span><?php endif; ?>
        <div><p class="eyebrow">TWITCH-PROFIL</p><h2><?= e($selectedUser['display_name']) ?></h2><p>@<?= e($selectedUser['login']) ?> · ID <?= e($selectedUser['twitch_user_id']) ?></p><?php if ($selectedUser['description']): ?><p class="profile-bio"><?= e($selectedUser['description']) ?></p><?php endif; ?></div>
    </div>
    <div class="profile-meta"><span><small>Konto erstellt</small><strong><?= e(utc_to_local($selectedUser['account_created_at'])) ?></strong></span><span><small>Typ</small><strong><?= e($selectedUser['broadcaster_type'] ?: 'normal') ?></strong></span><span><small>Zuletzt geladen</small><strong><?= e(utc_to_local($selectedUser['cached_at'])) ?></strong></span></div>
    <div class="button-row"><a class="button button-secondary" href="https://twitch.tv/<?= e($selectedUser['login']) ?>" target="_blank" rel="noopener noreferrer">Auf Twitch öffnen ↗</a><?php if (auth()->can('notes.create')): ?><a class="button button-primary" href="<?= e(url('notes', ['twitch_user_id' => $selectedUser['twitch_user_id']]) . '#note-form') ?>">Notiz anlegen</a><?php endif; ?></div>
</section>

<section class="dashboard-grid">
    <article class="card"><div class="section-head"><div><p class="eyebrow">NOTIZEN</p><h3><?= count($selectedNotes) ?> Einträge</h3></div></div><?php if (!$selectedNotes): ?><p class="empty-copy">Noch keine Notiz zu diesem Nutzer.</p><?php else: ?><div class="compact-list static"><?php foreach ($selectedNotes as $note): ?><div><span class="tool-icon purple">▤</span><span><strong><?= e($note['title']) ?></strong><small><?= e($note['creator_name']) ?> · <?= e(utc_to_local($note['created_at'])) ?></small></span></div><?php endforeach; ?></div><?php endif; ?></article>
    <article class="card"><div class="section-head"><div><p class="eyebrow">AKTIONSHISTORIE</p><h3><?= count($selectedActions) ?> Aktionen</h3></div></div><?php if (!$selectedActions): ?><p class="empty-copy">Noch keine Moderationsaktion dokumentiert.</p><?php else: ?><div class="timeline-list"><?php foreach ($selectedActions as $action): ?><div><span class="timeline-icon <?= $action['success'] ? 'success' : 'failed' ?>"><?= $action['success'] ? '✓' : '!' ?></span><span><strong><?= e(strtoupper((string) $action['action'])) ?></strong><small><?= e($action['performer_name']) ?> · <?= e(utc_to_local($action['created_at'])) ?></small></span></div><?php endforeach; ?></div><?php endif; ?></article>
</section>
<?php endif; ?>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">LOKALER API-CACHE</p><h3><?= count($users) ?> Twitch-Profile</h3></div></div>
    <?php if (!$users): ?><div class="empty-state"><span class="empty-icon">♙</span><h3>Noch keine Profile</h3><p>Suche oben nach einem Twitch-Namen.</p></div><?php else: ?><div class="responsive-table"><table><thead><tr><th>User</th><th>Typ</th><th>Notizen</th><th>Aktionen</th><th>Aktualisiert</th><th></th></tr></thead><tbody><?php foreach ($users as $user): ?><tr>
        <td data-label="User"><div class="table-user"><?php if ($user['profile_image_url']): ?><img class="avatar avatar-small" src="<?= e($user['profile_image_url']) ?>" alt=""><?php else: ?><span class="avatar avatar-small avatar-text"><?= e(mb_strtoupper(mb_substr((string) $user['display_name'], 0, 1))) ?></span><?php endif; ?><span><strong><?= e($user['display_name']) ?></strong><small>@<?= e($user['login']) ?></small></span></div></td>
        <td data-label="Typ"><span class="badge"><?= e($user['broadcaster_type'] ?: 'User') ?></span></td><td data-label="Notizen"><?= e($user['notes_count']) ?></td><td data-label="Aktionen"><?= e($user['actions_count']) ?></td><td data-label="Aktualisiert"><?= e(utc_to_local($user['cached_at'])) ?></td><td data-label="Öffnen"><a class="icon-button" href="<?= e(url('twitch-users', ['id' => $user['twitch_user_id']])) ?>">→</a></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

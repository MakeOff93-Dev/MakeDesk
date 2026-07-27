<?php $isEditing = !empty($editNote['id']); ?>
<div class="page-intro">
    <div><p>Teamwissen, Absprachen und Beobachtungen dauerhaft und durchsuchbar sichern.</p></div>
    <?php if (auth()->can('notes.create')): ?><a class="button button-primary" href="#note-form">+ Neue Notiz</a><?php endif; ?>
</div>

<?php if (auth()->can($isEditing ? 'notes.edit' : 'notes.create')): ?>
<section class="card form-card" id="note-form">
    <div class="section-head"><div><p class="eyebrow"><?= $isEditing ? 'BEARBEITEN' : 'TEAMWISSEN' ?></p><h3><?= $isEditing ? e($editNote['title']) : 'Notiz anlegen' ?></h3></div><?php if ($isEditing): ?><a href="<?= e(url('notes')) ?>">Abbrechen ×</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="note-save"><input type="hidden" name="id" value="<?= e($editNote['id'] ?? 0) ?>">
        <label class="span-2"><span>Titel *</span><input type="text" name="title" maxlength="180" required value="<?= e($editNote['title'] ?? '') ?>" placeholder="Kurzer, eindeutiger Titel"></label>
        <label class="span-2"><span>Notiz *</span><textarea name="body" rows="6" required placeholder="Hier stehen alle Details …"><?= e($editNote['body'] ?? '') ?></textarea></label>
        <label><span>Twitch-Nutzer (optional)</span><select name="twitch_user_id"><option value="">Keiner</option><?php foreach ($twitchUsers as $user): ?><option value="<?= e($user['twitch_user_id']) ?>" <?= ($editNote['twitch_user_id'] ?? '') === $user['twitch_user_id'] ? 'selected' : '' ?>><?= e($user['display_name']) ?> (@<?= e($user['login']) ?>)</option><?php endforeach; ?></select></label>
        <label><span>Idee (optional)</span><select name="idea_id"><option value="">Keine</option><?php foreach ($ideasForNotes as $idea): ?><option value="<?= e($idea['id']) ?>" <?= (int) ($editNote['idea_id'] ?? 0) === (int) $idea['id'] ? 'selected' : '' ?>><?= e($idea['title']) ?></option><?php endforeach; ?></select></label>
        <label><span>Tags</span><input type="text" name="tags" maxlength="500" value="<?= e($editNote['tags'] ?? '') ?>" placeholder="technik, sound, wichtig"></label>
        <?php if (auth()->can('notes.private.view')): ?><label><span>Sichtbarkeit</span><select name="visibility"><option value="team" <?= ($editNote['visibility'] ?? 'team') === 'team' ? 'selected' : '' ?>>Gesamtes Team</option><option value="admin" <?= ($editNote['visibility'] ?? '') === 'admin' ? 'selected' : '' ?>>Intern berechtigt</option></select></label><?php else: ?><input type="hidden" name="visibility" value="team"><?php endif; ?>
        <label class="check-label span-2"><input type="checkbox" name="pinned" value="1" <?= !empty($editNote['pinned']) ? 'checked' : '' ?>><span>Oben anheften</span></label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $isEditing ? 'Änderungen speichern' : 'Notiz speichern' ?></button></div>
    </form>
</section>
<?php endif; ?>

<section class="note-grid">
    <?php if (!$notes): ?><div class="empty-state card"><span class="empty-icon">▤</span><h3>Noch keine Notizen</h3><p>Wichtiges muss nicht im Chat verloren gehen.</p></div><?php endif; ?>
    <?php foreach ($notes as $note): ?><article class="note-card card <?= $note['pinned'] ? 'pinned' : '' ?>">
        <div class="note-top"><div><?php if ($note['pinned']): ?><span class="pin">◆ ANGEHEFTET</span><?php endif; ?><h3><?= e($note['title']) ?></h3></div><span class="badge"><?= e($note['visibility'] === 'admin' ? 'Admins' : 'Team') ?></span></div>
        <p><?= nl2br(e($note['body'])) ?></p>
        <?php if ($note['twitch_name'] || $note['idea_title']): ?><div class="relations"><?php if ($note['twitch_name']): ?><span>♙ <?= e($note['twitch_name']) ?></span><?php endif; ?><?php if ($note['idea_title']): ?><span>✦ <?= e($note['idea_title']) ?></span><?php endif; ?></div><?php endif; ?>
        <?php if ($note['tags']): ?><div class="tag-list"><?php foreach (array_filter(array_map('trim', explode(',', (string) $note['tags']))) as $tag): ?><span>#<?= e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
        <footer><small><?= e($note['creator_name']) ?> · <?= e(utc_to_local($note['updated_at'])) ?></small><div class="table-actions"><?php if (auth()->can('notes.edit')): ?><a class="icon-button" href="<?= e(url('notes', ['edit' => $note['id']])) ?>">✎</a><?php endif; ?><?php if (auth()->can('notes.archive')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="note-archive"><input type="hidden" name="id" value="<?= e($note['id']) ?>"><button class="icon-button danger" type="submit" data-confirm="Notiz archivieren?">⌫</button></form><?php endif; ?></div></footer>
    </article><?php endforeach; ?>
</section>

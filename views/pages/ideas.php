<?php
$statusLabels = ['new' => 'Neu', 'planned' => 'Geplant', 'in_progress' => 'In Arbeit', 'done' => 'Erledigt', 'rejected' => 'Verworfen'];
$priorityLabels = ['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch', 'urgent' => 'Dringend'];
?>
<div class="page-intro">
    <div><p>Gemeinsames Ideen-Board für Streams, Technik, Community und neue Formate.</p></div>
    <?php if (auth()->can('ideas.create')): ?><a class="button button-primary" href="#idea-form">+ Neue Idee</a><?php endif; ?>
</div>

<?php if (auth()->can($editIdea ? 'ideas.edit' : 'ideas.create')): ?>
<section class="card form-card" id="idea-form">
    <div class="section-head"><div><p class="eyebrow"><?= $editIdea ? 'BEARBEITEN' : 'NEUER EINTRAG' ?></p><h3><?= $editIdea ? e($editIdea['title']) : 'Idee festhalten' ?></h3></div><?php if ($editIdea): ?><a href="<?= e(url('ideas')) ?>">Abbrechen ×</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="idea-save">
        <input type="hidden" name="id" value="<?= e($editIdea['id'] ?? 0) ?>">
        <label class="span-2"><span>Titel *</span><input type="text" name="title" maxlength="180" required value="<?= e($editIdea['title'] ?? '') ?>" placeholder="z. B. Neuer Soundeffekt für den Stream"></label>
        <label class="span-2"><span>Beschreibung *</span><textarea name="description" rows="4" required placeholder="Was ist die Idee und was wird dafür gebraucht?"><?= e($editIdea['description'] ?? '') ?></textarea></label>
        <label><span>Status</span><select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editIdea['status'] ?? 'new') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Priorität</span><select name="priority"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editIdea['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Zuständig</span><select name="assigned_to"><option value="">Noch offen</option><?php foreach ($team as $member): ?><option value="<?= e($member['id']) ?>" <?= (int) ($editIdea['assigned_to'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>><?= e($member['display_name']) ?></option><?php endforeach; ?></select></label>
        <label><span>Wunschtermin</span><input type="date" name="due_date" value="<?= e($editIdea['due_date'] ?? '') ?>"></label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editIdea ? 'Änderungen speichern' : 'Idee speichern' ?></button></div>
    </form>
</section>
<?php endif; ?>

<section class="card table-card">
    <div class="section-head"><div><p class="eyebrow">IDEEN-BOARD</p><h3><?= count($ideas) ?> Einträge</h3></div></div>
    <?php if (!$ideas): ?><div class="empty-state"><span class="empty-icon">✦</span><h3>Noch ganz leer</h3><p>Die erste gute Idee wartet schon.</p></div><?php else: ?>
    <div class="responsive-table"><table>
        <thead><tr><th>Idee</th><th>Status</th><th>Priorität</th><th>Zuständig</th><th>Aktualisiert</th><th></th></tr></thead>
        <tbody><?php foreach ($ideas as $idea): ?><tr>
            <td data-label="Idee"><strong><?= e($idea['title']) ?></strong><small><?= e(mb_strimwidth((string) $idea['description'], 0, 115, '…')) ?></small></td>
            <td data-label="Status"><span class="badge badge-<?= e($idea['status']) ?>"><?= e($statusLabels[$idea['status']] ?? $idea['status']) ?></span></td>
            <td data-label="Priorität"><span class="priority-label"><i class="priority-dot priority-<?= e($idea['priority']) ?>"></i><?= e($priorityLabels[$idea['priority']] ?? $idea['priority']) ?></span></td>
            <td data-label="Zuständig"><?= e($idea['assignee_name'] ?? 'Noch offen') ?></td>
            <td data-label="Aktualisiert"><?= e(utc_to_local($idea['updated_at'])) ?><small>von <?= e($idea['creator_name']) ?></small></td>
            <td class="table-actions" data-label="Aktionen">
                <?php if (auth()->can('ideas.edit')): ?><a class="icon-button" href="<?= e(url('ideas', ['edit' => $idea['id']])) ?>" title="Bearbeiten">✎</a><?php endif; ?>
                <?php if (auth()->can('ideas.archive')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="idea-archive"><input type="hidden" name="id" value="<?= e($idea['id']) ?>"><button class="icon-button danger" type="submit" data-confirm="Idee wirklich archivieren?" title="Archivieren">⌫</button></form><?php endif; ?>
            </td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>

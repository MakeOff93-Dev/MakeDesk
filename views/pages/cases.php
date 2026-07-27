<?php
$statusLabels = ['open' => 'Offen', 'monitoring' => 'Beobachtung', 'closed' => 'Geschlossen'];
$severityLabels = ['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch', 'critical' => 'Kritisch'];
?>
<div class="page-intro"><div><p>Wiederkehrende Vorfälle nachvollziehbar dokumentieren und Maßnahmen einem Fall zuordnen.</p></div><?php if (auth()->can('cases.create')): ?><a class="button button-primary" href="#case-form">+ Neuer Fall</a><?php endif; ?></div>

<?php if (auth()->can($editCase ? 'cases.edit' : 'cases.create')): ?>
<section class="card form-card" id="case-form">
    <div class="section-head"><div><p class="eyebrow"><?= $editCase ? 'BEARBEITEN' : 'NEUER MOD-FALL' ?></p><h3><?= $editCase ? e($editCase['title']) : 'Vorfall dokumentieren' ?></h3></div><?php if ($editCase): ?><a href="<?= e(url('cases')) ?>">Abbrechen ×</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="case-save"><input type="hidden" name="return_page" value="cases"><input type="hidden" name="id" value="<?= e($editCase['id'] ?? 0) ?>">
        <label><span>Twitch-Login *</span><input type="text" name="twitch_login" required value="<?= e($editCase['login'] ?? '') ?>" placeholder="ohne @"></label>
        <label><span>Titel *</span><input type="text" name="title" maxlength="180" required value="<?= e($editCase['title'] ?? '') ?>" placeholder="z. B. Wiederholter Spam"></label>
        <label class="span-2"><span>Zusammenfassung</span><textarea name="summary" rows="4" placeholder="Was ist passiert? Welche Absprachen gibt es?"><?= e($editCase['summary'] ?? '') ?></textarea></label>
        <label><span>Status</span><select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editCase['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Schweregrad</span><select name="severity"><?php foreach ($severityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editCase['severity'] ?? 'normal') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Zuständig</span><select name="assigned_to"><option value="">Noch offen</option><?php foreach ($team as $member): ?><option value="<?= e($member['id']) ?>" <?= (int) ($editCase['assigned_to'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>><?= e($member['display_name']) ?></option><?php endforeach; ?></select></label>
        <div class="form-actions align-end"><button class="button button-primary" type="submit"><?= $editCase ? 'Fall aktualisieren' : 'Fall anlegen' ?></button></div>
    </form>
</section>
<?php endif; ?>

<section class="case-grid">
    <?php if (!$cases): ?><div class="empty-state card"><span class="empty-icon">⚑</span><h3>Keine Moderationsfälle</h3><p>Das ist die beste Art von leerer Liste.</p></div><?php endif; ?>
    <?php foreach ($cases as $case): ?><article class="case-card card severity-<?= e($case['severity']) ?>">
        <div class="case-head"><span class="case-number">#<?= e($case['id']) ?></span><span class="badge badge-<?= e($case['status']) ?>"><?= e($statusLabels[$case['status']] ?? $case['status']) ?></span></div>
        <a class="case-user" href="<?= e(url('twitch-users', ['id' => $case['twitch_user_id']])) ?>">♙ <?= e($case['twitch_name']) ?> <small>@<?= e($case['login']) ?></small></a>
        <h3><?= e($case['title']) ?></h3><?php if ($case['summary']): ?><p><?= e(mb_strimwidth((string) $case['summary'], 0, 240, '…')) ?></p><?php endif; ?>
        <div class="case-meta"><span><small>Schweregrad</small><strong><?= e($severityLabels[$case['severity']] ?? $case['severity']) ?></strong></span><span><small>Aktionen</small><strong><?= e($case['actions_count']) ?></strong></span><span><small>Zuständig</small><strong><?= e($case['assignee_name'] ?? 'Offen') ?></strong></span></div>
        <footer><small>Aktualisiert <?= e(utc_to_local($case['updated_at'])) ?></small><?php if (auth()->can('cases.edit')): ?><a class="button button-small button-secondary" href="<?= e(url('cases', ['edit' => $case['id']]) . '#case-form') ?>">Bearbeiten</a><?php endif; ?></footer>
    </article><?php endforeach; ?>
</section>

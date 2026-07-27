<?php
$newsStatusLabels = ['draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'archived' => 'Archiviert'];
$editPublishAt = '';
if (!empty($editNews['publish_at'])) {
    try {
        $editPublishAt = (new DateTimeImmutable((string) $editNews['publish_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) env('APP_TIMEZONE', 'Europe/Berlin')))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        $editPublishAt = '';
    }
}
?>

<div class="page-intro">
    <div><p>Ankündigungen vorbereiten, anheften, veröffentlichen und auf Wunsch automatisch über Discord verteilen.</p></div>
    <span class="badge">NEWS-MODUL</span>
</div>

<?php if (auth()->can($editNews ? 'news.edit' : 'news.create')): ?>
<section class="card settings-section">
    <div class="section-head"><div><p class="eyebrow"><?= $editNews ? 'BEARBEITEN' : 'NEUER EINTRAG' ?></p><h2><?= $editNews ? e($editNews['title']) : 'News verfassen' ?></h2></div><?php if ($editNews): ?><a class="button button-small button-secondary" href="<?= e(url('news')) ?>">Abbrechen</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="news-save">
        <input type="hidden" name="return_page" value="news">
        <input type="hidden" name="news_id" value="<?= e($editNews['id'] ?? 0) ?>">
        <label class="span-2"><span>Titel *</span><input type="text" name="title" maxlength="180" required value="<?= e($editNews['title'] ?? '') ?>"></label>
        <label class="span-2"><span>Inhalt *</span><textarea name="body" rows="8" maxlength="50000" required placeholder="Neuigkeiten, Änderungen oder Hinweise für das Team …"><?= e($editNews['body'] ?? '') ?></textarea></label>
        <label><span>Status</span><select name="status"><?php foreach ($newsStatusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($editNews['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Veröffentlichungszeitpunkt</span><input type="datetime-local" name="publish_at" value="<?= e($editPublishAt) ?>"></label>
        <label class="check-label span-2"><input type="checkbox" name="pinned" value="1" <?= !empty($editNews['pinned']) ? 'checked' : '' ?>><span>Oben anheften</span></label>
        <div class="span-2 settings-note">Beim ersten Speichern als „Veröffentlicht“ kann das Ereignis automatisch an alle dafür ausgewählten Discord-Channels gesendet werden.</div>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit">News speichern</button></div>
    </form>
</section>
<?php endif; ?>

<section class="news-grid">
    <?php if ($newsPosts === []): ?>
        <article class="card empty-state"><span class="empty-icon">◫</span><h3>Noch keine News</h3><p>Der erste Eintrag kann direkt oben angelegt werden.</p></article>
    <?php endif; ?>
    <?php foreach ($newsPosts as $post): ?>
        <article class="card news-card <?= !empty($post['pinned']) ? 'pinned' : '' ?>">
            <header>
                <div><p class="eyebrow"><?= !empty($post['pinned']) ? 'ANGEHEFTET · ' : '' ?><?= e($newsStatusLabels[$post['status']] ?? $post['status']) ?></p><h2><?= e($post['title']) ?></h2></div>
                <span class="job-status status-<?= e($post['status'] === 'published' ? 'completed' : ($post['status'] === 'archived' ? 'failed' : 'running')) ?>"><?= e($newsStatusLabels[$post['status']] ?? $post['status']) ?></span>
            </header>
            <div class="news-body"><?= nl2br(e($post['body'])) ?></div>
            <footer><span><?= e($post['updater_name'] ?: $post['creator_name']) ?> · <?= e(utc_to_local($post['publish_at'] ?: $post['updated_at'])) ?></span><div class="button-row">
                <?php if (auth()->can('news.edit')): ?><a class="button button-small button-secondary" href="<?= e(url('news', ['edit' => $post['id']])) ?>">Bearbeiten</a><?php endif; ?>
                <?php if (auth()->can('news.archive') && $post['status'] !== 'archived'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="news-archive"><input type="hidden" name="return_page" value="news"><input type="hidden" name="news_id" value="<?= e($post['id']) ?>"><button class="button button-small button-danger-outline" type="submit" data-confirm="Diese News archivieren?">Archivieren</button></form><?php endif; ?>
            </div></footer>
        </article>
    <?php endforeach; ?>
</section>

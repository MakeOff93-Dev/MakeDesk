<div class="page-intro"><div><p>Wichtige Tools, Dokumente, Clips und Ressourcen zentral mit dem Team teilen.</p></div><?php if (auth()->can('links.create')): ?><a class="button button-primary" href="#link-form">+ Link teilen</a><?php endif; ?></div>

<?php if (auth()->can($editLink ? 'links.edit' : 'links.create')): ?>
<section class="card form-card" id="link-form">
    <div class="section-head"><div><p class="eyebrow"><?= $editLink ? 'BEARBEITEN' : 'NEUER LINK' ?></p><h3><?= $editLink ? e($editLink['title']) : 'Ressource teilen' ?></h3></div><?php if ($editLink): ?><a href="<?= e(url('links')) ?>">Abbrechen ×</a><?php endif; ?></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="link-save"><input type="hidden" name="id" value="<?= e($editLink['id'] ?? 0) ?>">
        <label><span>Titel *</span><input type="text" name="title" maxlength="180" required value="<?= e($editLink['title'] ?? '') ?>"></label>
        <label><span>Kategorie</span><input type="text" name="category" maxlength="80" value="<?= e($editLink['category'] ?? '') ?>" placeholder="Technik, Clips, Grafik …"></label>
        <label class="span-2"><span>URL *</span><input type="url" name="url" maxlength="2048" required value="<?= e($editLink['url'] ?? '') ?>" placeholder="https://"></label>
        <label class="span-2"><span>Beschreibung</span><textarea name="description" rows="3" placeholder="Wofür ist der Link gedacht?"><?= e($editLink['description'] ?? '') ?></textarea></label>
        <div class="span-2 form-actions"><button class="button button-primary" type="submit"><?= $editLink ? 'Änderungen speichern' : 'Link teilen' ?></button></div>
    </form>
</section>
<?php endif; ?>

<section class="link-grid">
    <?php if (!$links): ?><div class="empty-state card"><span class="empty-icon">↗</span><h3>Noch keine Links</h3><p>Teile die erste nützliche Ressource.</p></div><?php endif; ?>
    <?php foreach ($links as $link): ?><article class="link-card card">
        <div class="link-icon">↗</div>
        <div class="link-copy"><?php if ($link['category']): ?><span class="eyebrow"><?= e(mb_strtoupper((string) $link['category'])) ?></span><?php endif; ?><h3><?= e($link['title']) ?></h3><?php if ($link['description']): ?><p><?= e($link['description']) ?></p><?php endif; ?><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(parse_url((string) $link['url'], PHP_URL_HOST) ?: $link['url']) ?> <span>→</span></a></div>
        <footer><small>von <?= e($link['creator_name']) ?> · <?= e(utc_to_local($link['updated_at'])) ?></small><div class="table-actions"><?php if (auth()->can('links.edit')): ?><a class="icon-button" href="<?= e(url('links', ['edit' => $link['id']])) ?>">✎</a><?php endif; ?><?php if (auth()->can('links.archive')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="link-archive"><input type="hidden" name="id" value="<?= e($link['id']) ?>"><button class="icon-button danger" type="submit" data-confirm="Link archivieren?">⌫</button></form><?php endif; ?></div></footer>
    </article><?php endforeach; ?>
</section>

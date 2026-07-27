<section class="hero-panel">
    <div>
        <p class="eyebrow">GUTEN TAG, <?= e(mb_strtoupper((string) auth()->user()['display_name'])) ?></p>
        <h2>Alles im Blick.<br><span>Bereit für den Stream.</span></h2>
        <p>Ideen koordinieren, Teamwissen sichern und bei Bedarf direkt moderieren.</p>
    </div>
    <div class="hero-orbit" aria-hidden="true">
        <span class="orbit orbit-one"></span><span class="orbit orbit-two"></span><strong>LIVE<br>CTRL</strong>
    </div>
</section>

<section class="stat-grid">
    <?php if (modules()->isEnabled('ideas')): ?><a class="stat-card purple" href="<?= e(url('ideas')) ?>"><span>✦</span><strong><?= e($stats['ideas']) ?></strong><small>offene Ideen</small></a><?php endif; ?>
    <?php if (modules()->isEnabled('notes')): ?><a class="stat-card cyan" href="<?= e(url('notes')) ?>"><span>▤</span><strong><?= e($stats['notes']) ?></strong><small>aktive Notizen</small></a><?php endif; ?>
    <?php if (modules()->isEnabled('links')): ?><a class="stat-card green" href="<?= e(url('links')) ?>"><span>↗</span><strong><?= e($stats['links']) ?></strong><small>geteilte Links</small></a><?php endif; ?>
    <?php if (modules()->isEnabled('cases')): ?><a class="stat-card orange" href="<?= e(url('cases')) ?>"><span>⚑</span><strong><?= e($stats['cases']) ?></strong><small>offene Mod-Fälle</small></a><?php endif; ?>
</section>

<?php if ($systemWarnings !== [] || $pendingMigrationCount > 0 || $latestBackup !== null): ?>
<section class="card system-overview">
    <div class="section-head"><div><p class="eyebrow">SYSTEMSTATUS</p><h3>Installation und Sicherheit</h3></div><?php if (auth()->can('security.view')): ?><a href="<?= e(url('security')) ?>">Details →</a><?php endif; ?></div>
    <div class="system-status-grid">
        <article class="<?= $systemWarnings === [] ? 'passed' : 'warning' ?>"><span><?= $systemWarnings === [] ? '✓' : '!' ?></span><div><strong><?= count($systemWarnings) ?> Sicherheitswarnungen</strong><small><?= $systemWarnings === [] ? 'Alle Basisprüfungen bestanden.' : e($systemWarnings[0]['message']) ?></small></div></article>
        <article class="<?= $pendingMigrationCount === 0 ? 'passed' : 'warning' ?>"><span><?= $pendingMigrationCount === 0 ? '✓' : '→' ?></span><div><strong><?= e($pendingMigrationCount) ?> offene Migrationen</strong><small><?= $pendingMigrationCount === 0 ? 'Datenbankschema ist aktuell.' : 'Unter Einstellungen → System ausführen.' ?></small></div></article>
        <article class="<?= $latestBackup !== null ? 'passed' : 'warning' ?>"><span><?= $latestBackup !== null ? '✓' : '!' ?></span><div><strong><?= $latestBackup !== null ? 'Backup vorhanden' : 'Noch kein Backup' ?></strong><small><?= $latestBackup !== null ? e(utc_to_local($latestBackup['created_at'])) . ' · ' . e(format_bytes((int) $latestBackup['size_bytes'])) : 'Vor der nächsten größeren Änderung sichern.' ?></small></div></article>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($dashboardNews)): ?>
<section class="card dashboard-news">
    <div class="section-head"><div><p class="eyebrow">ANKÜNDIGUNGEN</p><h3>Aktuelle News</h3></div><a href="<?= e(url('news')) ?>">Alle News →</a></div>
    <div class="dashboard-news-grid">
        <?php foreach ($dashboardNews as $post): ?><article><span class="badge"><?= !empty($post['pinned']) ? 'ANGEHEFTET' : 'NEWS' ?></span><h3><?= e($post['title']) ?></h3><p><?= e(mb_substr($post['body'], 0, 240)) ?><?= mb_strlen($post['body']) > 240 ? '…' : '' ?></p><small><?= e($post['creator_name']) ?> · <?= e(utc_to_local($post['publish_at'] ?: $post['updated_at'])) ?></small></article><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="dashboard-grid">
    <?php if (modules()->isEnabled('ideas')): ?>
    <article class="card">
        <div class="section-head"><div><p class="eyebrow">PLANUNG</p><h3>Letzte Ideen</h3></div><a href="<?= e(url('ideas')) ?>">Alle ansehen →</a></div>
        <?php if (!$recentIdeas): ?>
            <p class="empty-copy">Noch keine Ideen eingetragen.</p>
        <?php else: ?>
            <div class="compact-list">
                <?php foreach ($recentIdeas as $idea): ?>
                    <a href="<?= e(url('ideas', ['edit' => $idea['id']])) ?>">
                        <span class="priority-dot priority-<?= e($idea['priority']) ?>"></span>
                        <span><strong><?= e($idea['title']) ?></strong><small><?= e($idea['creator_name']) ?> · <?= e(utc_to_local($idea['updated_at'])) ?></small></span>
                        <span class="badge badge-<?= e($idea['status']) ?>"><?= e(str_replace('_', ' ', $idea['status'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php endif; ?>

    <?php if (modules()->isEnabled('twitch') || modules()->isEnabled('ban-sync') || modules()->isEnabled('audit')): ?>
    <article class="card">
        <?php
        $moderationLogUrl = auth()->can('audit.view') && modules()->isEnabled('audit')
            ? url('audit')
            : (modules()->isEnabled('twitch') ? url('twitch-users') : url('ban-sync'));
        ?>
        <div class="section-head"><div><p class="eyebrow">MODERATION</p><h3>Letzte Aktionen</h3></div><a href="<?= e($moderationLogUrl) ?>">Protokoll →</a></div>
        <?php if (!$recentActions): ?>
            <p class="empty-copy">Noch keine Moderationsaktion ausgeführt.</p>
        <?php else: ?>
            <div class="timeline-list">
                <?php foreach ($recentActions as $action): ?>
                    <div>
                        <span class="timeline-icon <?= $action['success'] ? 'success' : 'failed' ?>"><?= $action['success'] ? '✓' : '!' ?></span>
                        <span><strong><?= e(strtoupper((string) $action['action'])) ?><?= $action['target_name'] ? ' · ' . e($action['target_name']) : '' ?></strong><small><?= e($action['performer_name']) ?> · <?= e(utc_to_local($action['created_at'])) ?></small></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php endif; ?>
</section>

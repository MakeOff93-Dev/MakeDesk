<section class="empty-state card">
    <span class="empty-icon">!</span>
    <h2><?= e($title ?? 'Fehler') ?></h2>
    <p><?= e($message ?? 'Ein unbekannter Fehler ist aufgetreten.') ?></p>
    <?php if (auth()->check()): ?>
        <a class="button button-primary" href="<?= e(url('dashboard')) ?>">Zum Dashboard</a>
    <?php endif; ?>
</section>


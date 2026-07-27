<?php
$designLogo = branding()->logoMetadata();
$canManageDesign = auth()->can('design.manage');
?>
<div class="page-intro">
    <div><p>Logo, Farben, Header, Footer, Navigation und Seitentitel ohne Änderungen am Quellcode gestalten.</p></div>
    <span class="badge">LIVE DESIGN</span>
</div>

<form method="post" enctype="multipart/form-data" class="stack-form design-editor" data-design-editor>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="design-save">
    <input type="hidden" name="return_page" value="design">
    <?php if (!$canManageDesign): ?><div class="alert alert-warning"><span>!</span><p>Du besitzt Leserechte für den Design-Editor. Änderungen sind für deine Rolle deaktiviert.</p></div><?php endif; ?>
    <fieldset class="design-editor-fieldset" <?= $canManageDesign ? '' : 'disabled' ?>>

    <section class="card settings-section">
        <div class="section-head"><div><p class="eyebrow">BRANDING</p><h2>Logo, Header und Footer</h2></div><span class="job-status status-running">Vorschau rechts</span></div>
        <div class="design-brand-grid">
            <div class="form-grid">
                <label><span>App-Name *</span><input type="text" name="app_name" maxlength="100" required value="<?= e($designSettings['app_name']) ?>" data-design-name></label>
                <label><span>Headerzeile *</span><input type="text" name="header_eyebrow" maxlength="60" required value="<?= e($designSettings['header_eyebrow']) ?>" data-design-eyebrow></label>
                <label><span>Versionsanzeige</span><input type="text" name="header_version_text" maxlength="80" value="<?= e($designSettings['header_version_text']) ?>" placeholder="Version {version}"></label>
                <label class="span-2"><span>Footer-Text</span><textarea name="footer_text" maxlength="300" rows="3" data-design-footer><?= e($designSettings['footer_text']) ?></textarea></label>
                <label class="span-2"><span>Eigenes Logo (PNG, JPG oder WebP, maximal 2 MB)</span><input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp" data-logo-input></label>
                <?php if ($designSettings['logo_set']): ?>
                    <label class="check-label span-2"><input type="checkbox" name="remove_logo" value="1"><span>Gespeichertes Logo entfernen</span></label>
                <?php endif; ?>
                <div class="span-2 settings-note">Mit <code>{version}</code> wird automatisch die installierte ModDesk-Version eingesetzt. Inhalte werden als sicherer Text ausgegeben; HTML und JavaScript sind bewusst nicht erlaubt.</div>
            </div>
            <div class="design-shell-preview" data-design-preview>
                <div class="design-preview-sidebar">
                    <div class="brand preview-brand">
                        <?php if ($designLogo): ?><img src="<?= e(url('brand-logo', ['v' => substr((string) $designLogo['checksum_sha256'], 0, 16)])) ?>" alt="" data-logo-preview><?php else: ?><span class="brand-mark" data-logo-fallback>M</span><img src="" alt="" data-logo-preview hidden><?php endif; ?>
                        <span><strong data-preview-name><?= e($designSettings['app_name']) ?></strong><small>// DESK</small></span>
                    </div>
                    <div class="preview-nav-line active"></div><div class="preview-nav-line"></div><div class="preview-nav-line short"></div>
                </div>
                <div class="design-preview-main"><small data-preview-eyebrow><?= e($designSettings['header_eyebrow']) ?></small><strong>Seitenüberschrift</strong><div class="preview-card"></div><footer data-preview-footer><?= e($designSettings['footer_text'] ?: 'Eigener Footer-Text') ?></footer></div>
            </div>
        </div>
    </section>

    <section class="card settings-section">
        <div class="section-head"><div><p class="eyebrow">FARBEN</p><h2>Oberfläche</h2></div><span class="count-chip">7</span></div>
        <div class="color-editor-grid">
            <?php foreach ([
                'background' => 'Hintergrund',
                'surface' => 'Karten',
                'surface_alt' => 'Flächen',
                'text' => 'Text',
                'muted' => 'Sekundärtext',
                'primary' => 'Primärfarbe',
                'secondary' => 'Akzentfarbe',
            ] as $key => $label): ?>
                <label class="color-field"><span><?= e($label) ?></span><span><input type="color" name="theme_<?= e($key) ?>" value="<?= e($designSettings['theme'][$key]) ?>" data-theme-color="<?= e($key) ?>"><code><?= e($designSettings['theme'][$key]) ?></code></span></label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card settings-section">
        <div class="section-head"><div><p class="eyebrow">NAVIGATION</p><h2>Menü bearbeiten</h2></div><span class="count-chip"><?= count($menuEditor) ?></span></div>
        <div class="menu-editor-list">
            <?php foreach ($menuEditor as $menuKey => $item): ?>
                <article class="menu-editor-row">
                    <label class="switch-label"><input type="checkbox" name="menu[<?= e($menuKey) ?>][enabled]" value="1" <?= $item['enabled'] ? 'checked' : '' ?>><span>Sichtbar</span></label>
                    <label><span>Symbol</span><input type="text" name="menu[<?= e($menuKey) ?>][icon]" maxlength="4" value="<?= e($item['icon']) ?>"></label>
                    <label><span>Beschriftung</span><input type="text" name="menu[<?= e($menuKey) ?>][label]" maxlength="45" required value="<?= e($item['label']) ?>"></label>
                    <label><span>Position</span><input type="number" name="menu[<?= e($menuKey) ?>][order]" min="0" max="999" value="<?= e($item['order']) ?>"></label>
                    <code><?= e($menuKey) ?></code>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="settings-note top-gap">Ausblenden entfernt nur den Link aus dem Menü. Rollen und Zugriffsrechte der Seite bleiben davon unabhängig geschützt.</div>
    </section>

    <section class="card settings-section">
        <div class="section-head"><div><p class="eyebrow">CONTENT</p><h2>Seitentitel und Text oben</h2></div><span class="count-chip"><?= count($pageEditor) ?></span></div>
        <div class="page-editor-list">
            <?php foreach ($pageEditor as $pageKey => $item): ?>
                <article class="page-editor-row">
                    <div><strong><?= e($item['default_title']) ?></strong><code><?= e($pageKey) ?></code></div>
                    <label><span>Eigener Seitentitel</span><input type="text" name="pages[<?= e($pageKey) ?>][title]" maxlength="100" value="<?= e($item['title']) ?>" placeholder="<?= e($item['default_title']) ?>"></label>
                    <label><span>Zusatztext im Header</span><textarea name="pages[<?= e($pageKey) ?>][top_text]" maxlength="240" rows="2" placeholder="Optionaler Text oben auf dieser Seite"><?= e($item['top_text']) ?></textarea></label>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-editor-actions"><span>Änderungen werden in MySQL gespeichert.</span><button class="button button-primary" type="submit">Design und Inhalte speichern</button></div>
    </fieldset>
</form>

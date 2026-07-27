<?php
$studioFields = is_array($selectedTemplate['fields'] ?? null) ? $selectedTemplate['fields'] : [];
if ($studioFields === []) {
    $studioFields = [['name' => '', 'value' => '', 'inline' => false]];
}
?>

<div class="page-intro">
    <div><p>Discord-Nachrichten visuell gestalten, als Vorlage speichern und sofort in einen Bot-Channel senden.</p></div>
    <span class="job-status <?= $discordConfigured ? 'status-completed' : 'status-failed' ?>"><?= $discordConfigured ? 'Bot bereit' : 'Bot fehlt' ?></span>
</div>

<?php if (!$discordConfigured): ?>
    <div class="alert alert-warning"><span>!</span><p>Trage zuerst unter „Einstellungen → Discord“ einen Bot-Token ein.</p></div>
<?php endif; ?>

<div class="discord-studio-layout">
    <aside class="card template-sidebar">
        <div class="section-head"><div><p class="eyebrow">VORLAGEN</p><h3>Nachrichten</h3></div><?php if (auth()->can('discord.templates.manage')): ?><a class="button button-tiny button-secondary" href="<?= e(url('discord-studio')) ?>">+ Neu</a><?php endif; ?></div>
        <div class="template-list">
            <?php if ($templates === []): ?><p class="muted">Noch keine Vorlage gespeichert.</p><?php endif; ?>
            <?php foreach ($templates as $template): ?>
                <a href="<?= e(url('discord-studio', ['template' => $template['id']])) ?>" class="<?= (int) $selectedTemplate['id'] === (int) $template['id'] ? 'active' : '' ?>">
                    <span>◆</span><span><strong><?= e($template['name']) ?></strong><small><?= e(utc_to_local($template['updated_at'])) ?><?= $template['updated_by_name'] ? ' · ' . e($template['updated_by_name']) : '' ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ((int) $selectedTemplate['id'] > 0 && auth()->can('discord.templates.manage')): ?>
            <form method="post" class="template-delete-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="discord-template-delete"><input type="hidden" name="return_page" value="discord-studio"><input type="hidden" name="template_id" value="<?= e($selectedTemplate['id']) ?>">
                <button class="button button-danger-outline button-wide" type="submit" data-confirm="Diese Discord-Vorlage löschen?">Vorlage löschen</button>
            </form>
        <?php endif; ?>
    </aside>

    <form method="post" class="discord-editor-form" data-discord-editor>
        <?= csrf_field() ?>
        <input type="hidden" name="return_page" value="discord-studio">
        <input type="hidden" name="template_id" value="<?= e($selectedTemplate['id']) ?>">

        <section class="card settings-section discord-target-card">
            <div class="section-head"><div><p class="eyebrow">LIVE-ZIEL</p><h2>Server und Channel</h2></div><span class="status-dot <?= $discordConfigured ? 'online' : '' ?>"></span></div>
            <?php if ($studioRoutes !== []): ?>
                <div class="route-shortcuts">
                    <?php foreach ($studioRoutes as $route): ?>
                        <button type="button" class="button button-small button-secondary" data-discord-route data-guild-id="<?= e($route['guild_id']) ?>" data-channel-id="<?= e($route['channel_id']) ?>"><?= e($studioEvents[$route['event_key']]['label'] ?? $route['event_key']) ?> · #<?= e($route['channel_name']) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-grid">
                <label><span>Discord Server-ID (optional)</span><input type="text" name="discord_guild_id" inputmode="numeric" data-guild-input></label>
                <label><span>Discord Channel-ID *</span><input type="text" name="discord_channel_id" inputmode="numeric" required data-channel-input></label>
            </div>
        </section>

        <div class="discord-editor-grid">
            <div class="stack-form">
                <section class="card settings-section">
                    <div class="section-head"><div><p class="eyebrow">NACHRICHT</p><h2>Text und Titel</h2></div><button type="button" class="button button-tiny button-secondary" data-emoji-toggle>☺ Emojis</button></div>
                    <div class="emoji-picker" data-emoji-picker hidden>
                        <?php foreach (['✅','⚠️','🚨','🎉','💜','🔥','📌','📣','🛡️','⛔','ℹ️','⭐','🎮','👋','💬','🔗'] as $emoji): ?><button type="button" data-emoji="<?= e($emoji) ?>"><?= e($emoji) ?></button><?php endforeach; ?>
                    </div>
                    <div class="form-grid">
                        <label class="span-2"><span>Vorlagenname</span><input type="text" name="template_name" maxlength="120" value="<?= e($selectedTemplate['name']) ?>" placeholder="z. B. BanSync-Ergebnis" data-editor-input="template"></label>
                        <label class="span-2"><span>Normaler Nachrichtentext</span><textarea name="message_content" maxlength="2000" rows="3" placeholder="Text oberhalb der Einbettung" data-editor-input="content"><?= e($selectedTemplate['message_content']) ?></textarea></label>
                        <label><span>Embed-Titel</span><input type="text" name="embed_title" maxlength="256" value="<?= e($selectedTemplate['embed_title']) ?>" data-editor-input="title"></label>
                        <label><span>Titel-Link (HTTPS)</span><input type="url" name="embed_url" value="<?= e($selectedTemplate['embed_url']) ?>" placeholder="https://…"></label>
                        <label class="span-2"><span>Beschreibung</span><textarea name="embed_description" maxlength="4096" rows="6" data-editor-input="description"><?= e($selectedTemplate['embed_description']) ?></textarea></label>
                        <label><span>Embed-Farbe</span><span class="color-input-pair"><input type="color" name="embed_color" value="<?= e($selectedTemplate['embed_color_hex']) ?>" data-editor-input="color"><code data-color-code><?= e($selectedTemplate['embed_color_hex']) ?></code></span></label>
                        <label class="check-label field-bottom"><input type="checkbox" name="include_timestamp" value="1" <?= (int) $selectedTemplate['include_timestamp'] === 1 ? 'checked' : '' ?> data-editor-input="timestamp"><span>Zeitstempel anzeigen</span></label>
                    </div>
                </section>

                <section class="card settings-section">
                    <div class="section-head"><div><p class="eyebrow">MEDIEN</p><h2>Autor, Icons und Bilder</h2></div></div>
                    <div class="form-grid">
                        <label><span>Autorname</span><input type="text" name="author_name" maxlength="256" value="<?= e($selectedTemplate['author_name']) ?>" data-editor-input="author"></label>
                        <label><span>Autor-Link (HTTPS)</span><input type="url" name="author_url" value="<?= e($selectedTemplate['author_url']) ?>"></label>
                        <label class="span-2"><span>Autor-Icon-URL (HTTPS)</span><input type="url" name="author_icon_url" value="<?= e($selectedTemplate['author_icon_url']) ?>" placeholder="https://…/icon.png" data-editor-input="author-icon"></label>
                        <label><span>Thumbnail-URL (HTTPS)</span><input type="url" name="thumbnail_url" value="<?= e($selectedTemplate['thumbnail_url']) ?>" placeholder="Kleines Bild rechts" data-editor-input="thumbnail"></label>
                        <label><span>Große Bild-URL (HTTPS)</span><input type="url" name="image_url" value="<?= e($selectedTemplate['image_url']) ?>" placeholder="Bild unter dem Embed" data-editor-input="image"></label>
                        <label><span>Footer-Text</span><input type="text" name="footer_text" maxlength="2048" value="<?= e($selectedTemplate['footer_text']) ?>" data-editor-input="footer"></label>
                        <label><span>Footer-Icon-URL (HTTPS)</span><input type="url" name="footer_icon_url" value="<?= e($selectedTemplate['footer_icon_url']) ?>" data-editor-input="footer-icon"></label>
                    </div>
                </section>

                <section class="card settings-section">
                    <div class="section-head"><div><p class="eyebrow">FELDER</p><h2>Embed-Blöcke</h2></div><button type="button" class="button button-small button-secondary" data-add-embed-field>+ Feld</button></div>
                    <div class="embed-field-editor" data-embed-fields>
                        <?php foreach ($studioFields as $index => $field): ?>
                            <article class="embed-field-row" data-embed-field>
                                <label><span>Feldname</span><input type="text" name="embed_fields[<?= e($index) ?>][name]" maxlength="256" value="<?= e($field['name'] ?? '') ?>" data-field-name></label>
                                <label><span>Inhalt</span><textarea name="embed_fields[<?= e($index) ?>][value]" maxlength="1024" rows="2" data-field-value><?= e($field['value'] ?? '') ?></textarea></label>
                                <label class="check-label"><input type="checkbox" name="embed_fields[<?= e($index) ?>][inline]" value="1" <?= !empty($field['inline']) ? 'checked' : '' ?> data-field-inline><span>Nebeneinander</span></label>
                                <button type="button" class="icon-button danger" data-remove-embed-field aria-label="Feld entfernen">×</button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="discord-preview-column">
                <section class="card discord-preview-card">
                    <div class="section-head"><div><p class="eyebrow">LIVE-VORSCHAU</p><h3>Discord</h3></div><span class="status-dot online"></span></div>
                    <div class="discord-message-preview">
                        <div class="discord-avatar">M</div>
                        <div class="discord-message-body">
                            <div class="discord-message-meta"><strong>ModDesk Bot</strong><span>APP</span><small>Heute</small></div>
                            <div class="discord-content-preview" data-preview-content></div>
                            <article class="discord-embed-preview" data-preview-embed>
                                <div class="discord-embed-author" data-preview-author-wrap><img src="" alt="" data-preview-author-icon hidden><span data-preview-author></span></div>
                                <strong class="discord-embed-title" data-preview-title></strong>
                                <div class="discord-embed-description" data-preview-description></div>
                                <img class="discord-embed-thumbnail" src="" alt="" data-preview-thumbnail hidden>
                                <div class="discord-preview-fields" data-preview-fields></div>
                                <img class="discord-embed-image" src="" alt="" data-preview-image hidden>
                                <footer><img src="" alt="" data-preview-footer-icon hidden><span data-preview-footer></span><small data-preview-timestamp> · jetzt</small></footer>
                            </article>
                        </div>
                    </div>
                </section>
                <div class="sticky-studio-actions">
                    <?php if (auth()->can('discord.templates.manage')): ?><button class="button button-secondary" type="submit" name="action" value="discord-template-save" formnovalidate>Vorlage speichern</button><?php endif; ?>
                    <?php if (auth()->can('discord.send')): ?><button class="button button-primary" type="submit" name="action" value="discord-message-send" <?= !$discordConfigured ? 'disabled' : '' ?> data-confirm="Diese Nachricht jetzt live an Discord senden?">Jetzt live senden</button><?php endif; ?>
                </div>
            </aside>
        </div>
    </form>
</div>

<template id="embed-field-template"><article class="embed-field-row" data-embed-field><label><span>Feldname</span><input type="text" data-field-name maxlength="256"></label><label><span>Inhalt</span><textarea data-field-value maxlength="1024" rows="2"></textarea></label><label class="check-label"><input type="checkbox" value="1" data-field-inline><span>Nebeneinander</span></label><button type="button" class="icon-button danger" data-remove-embed-field aria-label="Feld entfernen">×</button></article></template>

<?php if ($manualDeliveries !== []): ?>
    <section class="card table-card delivery-card">
        <div class="section-head"><div><p class="eyebrow">LIVE-PROTOKOLL</p><h3>Zuletzt gesendet</h3></div><span class="count-chip"><?= count($manualDeliveries) ?></span></div>
        <div class="responsive-table"><table><thead><tr><th>Ziel</th><th>Status</th><th>Antwort</th><th>Zeit</th></tr></thead><tbody>
        <?php foreach ($manualDeliveries as $delivery): ?><tr><td><strong><?= e($delivery['destination']) ?></strong></td><td data-label="Status"><span class="job-status <?= (int) $delivery['success'] === 1 ? 'status-completed' : 'status-failed' ?>"><?= (int) $delivery['success'] === 1 ? 'Gesendet' : 'Fehler' ?></span></td><td data-label="Antwort"><small><?= e($delivery['error_message'] ?: 'HTTP ' . ($delivery['response_status'] ?: '–')) ?></small></td><td data-label="Zeit"><?= e(utc_to_local($delivery['created_at'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
<?php endif; ?>

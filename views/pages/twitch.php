<div class="page-intro">
    <div><p>Live-Werkzeuge, API-Synchronisierung und dokumentierte Moderationsaktionen an einem Ort.</p></div>
    <?php if ($connection && auth()->can('twitch.connect')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-validate"><input type="hidden" name="return_page" value="twitch"><button class="button button-secondary" type="submit">Verbindung prüfen</button></form><?php endif; ?>
</div>

<?php if (!$connection): ?>
    <section class="connect-panel card">
        <div class="twitch-logo">◉</div>
        <div><p class="eyebrow">TWITCH API</p><h2>Konto sicher verbinden</h2><p>Verbinde das Konto des Streamers oder dein Moderator-Konto. Die Zugangstoken werden verschlüsselt in MySQL gespeichert und automatisch erneuert.</p>
            <?php if (!twitch()->isConfigured()): ?><div class="inline-warning">In der <code>.env</code> fehlen noch TWITCH_CLIENT_ID, TWITCH_CLIENT_SECRET und TWITCH_REDIRECT_URI.</div><?php endif; ?>
            <?php if (auth()->can('twitch.connect')): ?><div class="button-row"><a class="button button-twitch <?= !twitch()->isConfigured() ? 'disabled' : '' ?>" href="<?= twitch()->isConfigured() ? e(url('twitch-connect', ['mode' => 'owner'])) : '#' ?>">Als Kanalinhaber verbinden →</a><a class="button button-secondary <?= !twitch()->isConfigured() ? 'disabled' : '' ?>" href="<?= twitch()->isConfigured() ? e(url('twitch-connect', ['mode' => 'moderator'])) : '#' ?>">Als Moderator verbinden</a></div><?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="connection-grid">
        <article class="card connection-card">
            <div class="connection-profile">
                <?php if ($connection['profile_image_url']): ?><img class="avatar avatar-large" src="<?= e($connection['profile_image_url']) ?>" alt=""><?php else: ?><span class="avatar avatar-large avatar-text"><?= e(mb_strtoupper(mb_substr((string) $connection['display_name'], 0, 1))) ?></span><?php endif; ?>
                <div><span class="online-label"><i></i> VERBUNDENES KONTO</span><h3><?= e($connection['display_name']) ?></h3><p>@<?= e($connection['login']) ?></p></div>
            </div>
            <dl class="meta-list"><div><dt>Token gültig bis</dt><dd><?= e(utc_to_local($connection['expires_at'])) ?></dd></div><div><dt>Zuletzt geprüft</dt><dd><?= e(utc_to_local($connection['last_validated_at'])) ?></dd></div><div><dt>Berechtigungen</dt><dd><?= count($connection['scopes']) ?> Scopes</dd></div></dl>
            <?php if (auth()->can('twitch.connect')): ?><div class="button-row top-gap"><a class="button button-small button-secondary" href="<?= e(url('twitch-connect', ['mode' => 'owner'])) ?>">Owner neu verbinden</a><a class="button button-small button-secondary" href="<?= e(url('twitch-connect', ['mode' => 'moderator'])) ?>">Moderator neu verbinden</a></div><?php endif; ?>
        </article>
        <article class="card target-card">
            <p class="eyebrow">BETREUTER KANAL</p>
            <h3><?= e($channel['display_name'] ?? 'Nicht gewählt') ?></h3>
            <p><?= $channel ? '@' . e($channel['login']) . ' · ID ' . e($channel['id']) : 'Lege fest, für welchen Kanal die Mod-Tools gelten.' ?></p>
            <?php if ($channel && (string) $channel['id'] === (string) $connection['twitch_user_id']): ?><span class="owner-chip">Kanalinhaber verbunden</span><?php else: ?><span class="moderator-chip">Moderator-Zugriff</span><?php endif; ?>
            <?php if (auth()->can('twitch.channels.select')): ?><form method="post" class="inline-form top-gap"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-set-channel"><input type="hidden" name="return_page" value="twitch"><input type="text" name="channel_login" required placeholder="Twitch-Kanalname" value="<?= e($channel['login'] ?? '') ?>"><button class="button button-secondary" type="submit">Kanal setzen</button></form><?php endif; ?>
        </article>
    </section>

    <?php if ($liveError): ?><div class="alert alert-warning"><span>i</span><p>Live-Status nicht verfügbar: <?= e($liveError) ?></p></div><?php endif; ?>

    <section class="live-control card">
        <div><p class="eyebrow">PANIKSCHUTZ</p><h3>Shield Mode</h3><p>Aktiviert sofort die bei Twitch eingerichteten Schutzregeln gegen Chat-Angriffe.</p></div>
        <div class="shield-status <?= !empty($shield['is_active']) ? 'active' : '' ?>"><span class="shield-icon">⬢</span><strong><?= !empty($shield['is_active']) ? 'AKTIV' : 'BEREIT' ?></strong></div>
        <?php if (auth()->can('twitch.moderate')): ?><div class="button-row">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-shield"><input type="hidden" name="return_page" value="twitch"><input type="hidden" name="active" value="1"><button class="button button-danger" type="submit" data-confirm="Shield Mode wirklich aktivieren?">Shield aktivieren</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-shield"><input type="hidden" name="return_page" value="twitch"><input type="hidden" name="active" value="0"><button class="button button-secondary" type="submit">Deaktivieren</button></form>
        </div><?php endif; ?>
    </section>

    <?php if (auth()->can('twitch.moderate') || auth()->can('twitch.users.lookup')): ?>
    <section class="tool-grid">
        <?php if (auth()->can('twitch.moderate')): ?>
        <article class="card tool-card span-wide">
            <div class="tool-heading"><span class="tool-icon purple">⚑</span><div><p class="eyebrow">MODERATION</p><h3>User verwarnen, timeouten oder bannen</h3></div></div>
            <form method="post" class="form-grid" data-mod-form>
                <?= csrf_field() ?><input type="hidden" name="action" value="twitch-mod-action"><input type="hidden" name="return_page" value="twitch">
                <label><span>Twitch-Login *</span><input type="text" name="twitch_login" required placeholder="ohne @"></label>
                <label><span>Aktion *</span><select name="mod_action" required data-mod-action><option value="warn">Verwarnen</option><option value="timeout">Timeout</option><option value="ban">Dauerhaft bannen</option><option value="unban">Ban / Timeout aufheben</option></select></label>
                <label data-duration hidden><span>Timeout in Minuten</span><input type="number" name="duration_minutes" min="1" max="20160" value="10"></label>
                <label><span>Mit Mod-Fall verknüpfen</span><select name="case_id"><option value="">Kein Fall</option><?php foreach ($openCases as $case): ?><option value="<?= e($case['id']) ?>">#<?= e($case['id']) ?> · <?= e($case['title']) ?> (<?= e($case['display_name']) ?>)</option><?php endforeach; ?></select></label>
                <label class="span-2"><span>Begründung</span><textarea name="reason" rows="3" maxlength="500" placeholder="Wird im internen Protokoll und – je nach Aktion – bei Twitch gespeichert."></textarea></label>
                <div class="span-2 form-actions"><button class="button button-primary" type="submit" data-confirm="Diese Moderationsaktion jetzt über Twitch ausführen?">Aktion ausführen</button></div>
            </form>
        </article>
        <?php endif; ?>

        <?php if (auth()->can('twitch.users.lookup')): ?>
        <article class="card tool-card">
            <div class="tool-heading"><span class="tool-icon cyan">♙</span><div><p class="eyebrow">USER-SUCHE</p><h3>Profil laden</h3></div></div>
            <p class="muted">Twitch-Profil aufrufen und lokal für Notizen oder Fälle speichern.</p>
            <form method="post" class="stack-form compact"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-lookup"><input type="hidden" name="return_page" value="twitch"><label><span>Twitch-Login</span><input type="text" name="twitch_login" required placeholder="z. B. dragoras07"></label><button class="button button-secondary" type="submit">Profil suchen →</button></form>
        </article>
        <?php endif; ?>

        <?php if (auth()->can('twitch.moderate')): ?>
        <article class="card tool-card">
            <div class="tool-heading"><span class="tool-icon orange">⌫</span><div><p class="eyebrow">CHAT</p><h3>Nachrichten löschen</h3></div></div>
            <p class="muted">Ohne Message-ID wird der gesamte sichtbare Chat geleert.</p>
            <form method="post" class="stack-form compact"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-clear-chat"><input type="hidden" name="return_page" value="twitch"><label><span>Message-ID (optional)</span><input type="text" name="message_id" placeholder="Leer = gesamten Chat leeren"></label><button class="button button-danger-outline" type="submit" data-confirm="Wirklich löschen? Diese Aktion ist sofort bei Twitch sichtbar.">Jetzt löschen</button></form>
        </article>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="dashboard-grid sync-grid">
        <article class="card">
            <div class="section-head"><div><p class="eyebrow">TEAM-SYNC</p><h3>Moderatoren <span class="count-chip"><?= count($moderators) ?></span></h3></div><?php if (auth()->can('twitch.sync')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-sync-moderators"><input type="hidden" name="return_page" value="twitch"><button class="button button-small button-secondary" type="submit">↻ Sync</button></form><?php endif; ?></div>
            <?php if (!$moderators): ?><p class="empty-copy">Noch keine Moderatorliste synchronisiert. Twitch erlaubt den Abruf nur mit dem Konto des Kanalinhabers.</p><?php else: ?><div class="user-chip-list"><?php foreach (array_slice($moderators, 0, 20) as $moderator): ?><a href="<?= e(url('twitch-users', ['id' => $moderator['twitch_user_id']])) ?>"><span class="avatar avatar-small avatar-text"><?= e(mb_strtoupper(mb_substr((string) $moderator['display_name'], 0, 1))) ?></span><span><strong><?= e($moderator['display_name']) ?></strong><small>@<?= e($moderator['login']) ?></small></span></a><?php endforeach; ?></div><?php endif; ?>
        </article>
        <article class="card">
            <div class="section-head"><div><p class="eyebrow">SICHERHEIT</p><h3>Bans & Timeouts <span class="count-chip"><?= count($bannedUsers) ?></span></h3></div><?php if (auth()->can('twitch.sync')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-sync-bans"><input type="hidden" name="return_page" value="twitch"><button class="button button-small button-secondary" type="submit">↻ Sync</button></form><?php endif; ?></div>
            <?php if (!$bannedUsers): ?><p class="empty-copy">Noch keine Ban-Liste synchronisiert.</p><?php else: ?><div class="user-chip-list"><?php foreach (array_slice($bannedUsers, 0, 20) as $banned): ?><a href="<?= e(url('twitch-users', ['id' => $banned['twitch_user_id']])) ?>"><span class="avatar avatar-small avatar-text danger-bg">!</span><span><strong><?= e($banned['display_name']) ?></strong><small>@<?= e($banned['login']) ?><?= !empty($banned['metadata']['reason']) ? ' · ' . e($banned['metadata']['reason']) : '' ?></small></span></a><?php endforeach; ?></div><?php endif; ?>
        </article>
    </section>

    <?php if (auth()->can('twitch.moderate')): ?>
    <section class="card admin-tools">
        <div class="section-head"><div><p class="eyebrow">KANALINHABER-WERKZEUG</p><h3>Moderatorrolle ändern</h3></div><span class="badge">Owner-Token nötig</span></div>
        <form method="post" class="inline-form wrap"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-moderator-change"><input type="hidden" name="return_page" value="twitch"><input type="text" name="twitch_login" required placeholder="Twitch-Login"><select name="mode"><option value="add">Als Moderator hinzufügen</option><option value="remove">Moderator entfernen</option></select><button class="button button-secondary" type="submit" data-confirm="Moderatorrolle wirklich ändern?">Ausführen</button></form>
    </section>
    <?php endif; ?>

    <section class="card admin-tools">
        <div class="section-head"><div><p class="eyebrow">CHAT-FILTER</p><h3>Blockierte Begriffe</h3></div><?php if (auth()->can('twitch.moderate')): ?><a href="<?= e(url('twitch', ['show' => 'terms'])) ?>">Liste von Twitch laden ↻</a><?php endif; ?></div>
        <?php if (auth()->can('twitch.moderate')): ?><form method="post" class="inline-form wrap"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-term-add"><input type="hidden" name="return_page" value="twitch"><input type="text" name="term" maxlength="500" required placeholder="Neuer blockierter Begriff"><button class="button button-secondary" type="submit">Hinzufügen</button></form><?php endif; ?>
        <?php if (auth()->can('twitch.moderate') && ($_GET['show'] ?? '') === 'terms'): ?><div class="term-list"><?php if (!$terms): ?><p class="empty-copy">Keine Begriffe gefunden oder Liste nicht verfügbar.</p><?php endif; ?><?php foreach ($terms as $term): ?><div><span><?= e($term['text']) ?></span><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="twitch-term-remove"><input type="hidden" name="return_page" value="twitch"><input type="hidden" name="term_id" value="<?= e($term['id']) ?>"><button class="icon-button danger" type="submit" data-confirm="Begriff entfernen?">×</button></form></div><?php endforeach; ?></div><?php endif; ?>
    </section>
<?php endif; ?>

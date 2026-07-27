<div class="page-intro"><div><p>Nachvollziehbares Sicherheitsprotokoll für Logins, Änderungen und Twitch-Aktionen.</p></div><span class="badge">letzte 250 Ereignisse</span></div>
<section class="card table-card audit-table">
    <div class="responsive-table"><table><thead><tr><th>Zeit</th><th>Person</th><th>Aktion</th><th>Ziel</th><th>Details</th><th>IP</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr>
        <td data-label="Zeit"><?= e(utc_to_local($log['created_at'])) ?></td><td data-label="Person"><?= e($log['user_name'] ?? 'System') ?></td><td data-label="Aktion"><code><?= e($log['action']) ?></code></td><td data-label="Ziel"><?= e($log['entity_type'] ?: '–') ?><?= $log['entity_id'] ? ' #' . e($log['entity_id']) : '' ?></td><td data-label="Details"><small class="json-copy"><?= e($log['details'] ?: '–') ?></small></td><td data-label="IP"><small><?= e($log['ip_address'] ?: '–') ?></small></td>
    </tr><?php endforeach; ?></tbody></table></div>
</section>


<?php
/**
 * Übersicht (rechte Spalte des Plugin-Layouts).
 *
 * @var array<string,int> $stats
 * @var array<string,string> $settings
 * @var list<array<string,mixed>> $placements
 * @var list<array<string,mixed>> $recent
 * @var bool $tablesExist
 * @var array{type:string,message:string}|null $notice
 * @var CMS_Promos_Repository $repository
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$schemaVersion = CMS_Promos_Installer::installed_schema_version();
$webhookOn = ($settings['click_export_enabled'] ?? '0') === '1';
$webhookUrl = $repository->get_webhook_url();
$retentionDays = max(0, (int) ($settings['retention_days'] ?? 0));
$ctr = $stats['impressions'] > 0 ? round($stats['clicks'] / $stats['impressions'] * 100, 1) : 0.0;

$checks = [
    [
        'label' => 'Datenbanktabellen',
        'status' => $tablesExist ? 'ok' : 'error',
        'value' => $tablesExist ? 'vorhanden' : 'fehlen',
        'hint' => $tablesExist ? '' : 'Plugin deaktivieren und erneut aktivieren, um die Tabellen anzulegen.',
    ],
    [
        'label' => 'Schema-Version',
        'status' => $schemaVersion === CMS_PROMOS_DB_VERSION ? 'ok' : 'warning',
        'value' => $schemaVersion !== '' ? $schemaVersion : 'unbekannt',
        'hint' => $schemaVersion === CMS_PROMOS_DB_VERSION ? '' : 'Erwartet: ' . CMS_PROMOS_DB_VERSION,
    ],
    [
        'label' => 'Automatische Ausspielung',
        'status' => $stats['active_hook_placements'] > 0 ? 'ok' : 'info',
        'value' => $stats['active_hook_placements'] . ' Hook-Platzierung(en) aktiv',
        'url' => CMS_Promos_Admin_Pages::url('promos-placements'),
    ],
    [
        'label' => 'Abgelaufen, aber noch aktiv',
        'status' => $stats['expired_active'] > 0 ? 'warning' : 'ok',
        'value' => (string) $stats['expired_active'],
        'hint' => $stats['expired_active'] > 0 ? 'Laufzeit überschritten – archivieren oder Ende verlängern.' : '',
        'url' => $stats['expired_active'] > 0 ? CMS_Promos_Admin_Pages::url('promos-items', ['status' => 'expired']) : '',
    ],
    [
        'label' => 'Aktive Promos ohne Ziel-URL',
        'status' => $stats['active_without_target'] > 0 ? 'warning' : 'ok',
        'value' => (string) $stats['active_without_target'],
        'hint' => $stats['active_without_target'] > 0 ? 'Ohne Ziel-URL verweist der Button nur auf die Übersicht.' : '',
    ],
    [
        'label' => 'Klick-Export (Webhook)',
        'status' => !$webhookOn ? 'muted' : ($webhookUrl !== '' ? 'ok' : 'warning'),
        'value' => !$webhookOn ? 'aus' : ($webhookUrl !== '' ? (string) parse_url($webhookUrl, PHP_URL_HOST) : 'URL fehlt'),
        'url' => CMS_Promos_Admin_Pages::url('promos-settings'),
    ],
    [
        'label' => 'Löschfrist',
        'status' => $retentionDays > 0 ? 'ok' : 'muted',
        'value' => $retentionDays > 0 ? $retentionDays . ' Tage' : 'nie',
        'url' => CMS_Promos_Admin_Pages::url('promos-settings'),
    ],
];

$activity = [];
foreach ($recent as $item) {
    $status = (string) ($item['status'] ?? 'draft');
    $live = (int) ($item['is_live'] ?? 0) === 1;
    $activity[] = [
        'title' => (string) ($item['title'] ?? ''),
        'url' => CMS_Promos_Admin_Pages::url('promos-items', ['edit' => (int) $item['id']]),
        'meta' => trim(((string) ($item['placement_name'] ?? '') !== '' ? (string) $item['placement_name'] . ' · ' : '')
            . CMS_Promos_Admin_Pages::format_datetime((string) ($item['updated_at'] ?? ''))
            . ' · ' . number_format((int) ($item['impression_count'] ?? 0), 0, ',', '.') . ' Impr. / '
            . number_format((int) ($item['click_count'] ?? 0), 0, ',', '.') . ' Klicks'),
        'badge' => $status === 'active' && !$live ? 'Aktiv (außerhalb Laufzeit)' : (CMS_Promos_Repository::STATUS_LABELS[$status] ?? $status),
        'tone' => $status === 'active' ? ($live ? 'success' : 'warning') : ($status === 'paused' ? 'warning' : 'muted'),
    ];
}

if ($notice !== null) {
    echo '<div class="alert alert-' . ($notice['type'] === 'success' ? 'success' : 'danger') . '" role="status">' . $esc($notice['message']) . '</div>';
}

echo cms_plugin_dashboard([
    'intro' => sprintf(
        '%s Promos, davon %s gerade sichtbar. Klickrate gesamt: %s %%.',
        number_format($stats['promos'], 0, ',', '.'),
        number_format($stats['live_promos'], 0, ',', '.'),
        number_format($ctr, 1, ',', '.')
    ),
    'actions' => [
        ['label' => 'Neue Promo', 'url' => CMS_Promos_Admin_Pages::url('promos-items', ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Teaser, Ziel-URL, Laufzeit und Platzierung festlegen.'],
        ['label' => 'Neue Platzierung', 'url' => CMS_Promos_Admin_Pages::url('promos-placements', ['action' => 'new']), 'icon' => 'ti ti-layout-grid-add', 'description' => 'Einbauort anlegen und an einen Theme-Hook koppeln.'],
        ['label' => 'Laufende Promos', 'url' => CMS_Promos_Admin_Pages::url('promos-items', ['status' => 'live']), 'icon' => 'ti ti-player-play', 'description' => 'Alle aktuell ausgespielten Kampagnen.'],
        ['label' => 'Öffentliche Übersicht', 'url' => '/promos', 'icon' => 'ti ti-world', 'external' => true, 'description' => 'Archivseite /promos im Frontend.'],
    ],
    'checks' => $checks,
    'activity' => [
        'heading' => 'Zuletzt bearbeitete Promos',
        'items' => $activity,
        'empty' => 'Noch keine Promos angelegt.',
        'more_url' => CMS_Promos_Admin_Pages::url('promos-items', ['sort' => 'updated']),
    ],
]);
?>

<section class="admin-card pr-section">
    <div class="pr-section__head">
        <div>
            <h3>Platzierungen</h3>
            <p>Einbauorte mit Theme-Hook, Kapazität und aktuell laufenden Promos.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements')); ?>"><i class="ti ti-settings" aria-hidden="true"></i> Verwalten</a>
    </div>
    <?php if ($placements === []): ?>
        <div class="empty-state"><p><strong>Keine Platzierungen vorhanden</strong></p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Platzierung</th><th scope="col">Theme-Hook</th><th scope="col">Max.</th><th scope="col">Laufend / gesamt</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($placements as $placement): ?>
                    <?php $live = (int) ($placement['live_count'] ?? 0); $max = (int) ($placement['max_items'] ?? 0); ?>
                    <tr>
                        <td><a href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements', ['edit' => (int) $placement['id']])); ?>"><?php echo $esc($placement['name'] ?? ''); ?></a></td>
                        <td><?php echo $esc($repository->get_theme_hook_label((string) ($placement['theme_hook'] ?? 'manual'))); ?></td>
                        <td><?php echo $max; ?></td>
                        <td><?php echo $live; ?> / <?php echo (int) ($placement['promo_count'] ?? 0); ?><?php if ($live > $max): ?> <span class="pr-meta">(nur <?php echo $max; ?> sichtbar)</span><?php endif; ?></td>
                        <td><span class="badge bg-<?php echo ($placement['status'] ?? '') === 'active' ? 'green' : 'secondary'; ?>-lt"><?php echo ($placement['status'] ?? '') === 'active' ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

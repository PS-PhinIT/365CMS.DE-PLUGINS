<?php
/**
 * CMS Beratung – Landingpage-Liste mit Filter, Sammelaktionen und Pagination.
 *
 * @var array{status:string,search:string,sort:string} $filters
 * @var array{rows:array<int,array<string,mixed>>,total:int} $result
 * @var int $pageNumber
 * @var int $perPage
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$statuses = CMS_Beratung_Settings::statuses();
$templates = CMS_Beratung_Settings::templates();
$queryParams = array_filter(['status' => $filters['status'], 'q' => $filters['search'], 'sort' => $filters['sort'] !== 'updated' ? $filters['sort'] : ''], static fn(string $value): bool => $value !== '');
$listUrl = $admin::admin_url('cms-beratung-landingpages', $queryParams + ($pageNumber > 1 ? ['p' => $pageNumber] : []));

$admin::page_header('Landingpages', 'Alle Beratungs-Landingpages mit Status, Vorschau und Export.', $admin::button_link('Neue Landingpage', $admin::admin_url('cms-beratung-new'), 'ti ti-plus', 'btn btn-primary'));
$admin::render_alerts();

echo '<form method="get" action="' . $admin::esc($admin::admin_url('cms-beratung-landingpages')) . '" class="beratung-toolbar toolbar" role="search">'
    . '<label class="beratung-field"><span>Suche</span><input type="search" name="q" value="' . $admin::esc($filters['search']) . '" placeholder="Titel oder Slug"></label>'
    . '<label class="beratung-field"><span>Status</span><select name="status"><option value="">Alle Status</option>';
foreach ($statuses as $key => $label) {
    echo '<option value="' . $admin::esc($key) . '"' . ($filters['status'] === $key ? ' selected' : '') . '>' . $admin::esc($label) . '</option>';
}
echo '</select></label><label class="beratung-field"><span>Sortierung</span><select name="sort">';
foreach (['updated' => 'Zuletzt geändert', 'title' => 'Titel A–Z', 'status' => 'Status'] as $key => $label) {
    echo '<option value="' . $admin::esc($key) . '"' . ($filters['sort'] === $key ? ' selected' : '') . '>' . $admin::esc($label) . '</option>';
}
echo '</select></label><div class="beratung-toolbar__actions"><button type="submit" class="btn btn-outline-primary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>'
    . ($queryParams !== [] ? $admin::button_link('Zurücksetzen', $admin::admin_url('cms-beratung-landingpages'), 'ti ti-x') : '') . '</div></form>';

if ($result['rows'] === []) {
    echo '<div class="admin-card empty-state"><h3>' . ($queryParams !== [] ? 'Keine Landingpages für diesen Filter.' : 'Noch keine Landingpages vorhanden.') . '</h3>'
        . '<p>Starte mit einer Vorlage und speichere die Seite anschließend als Entwurf.</p>'
        . $admin::button_link('Erste Landingpage anlegen', $admin::admin_url('cms-beratung-new'), 'ti ti-plus', 'btn btn-primary') . '</div>';
    return;
}

echo '<form method="post" action="' . $admin::esc($listUrl) . '" class="admin-card beratung-list-form">'
    . $admin::hidden_token('beratung_landingpage_action')
    . '<input type="hidden" name="beratung_admin_action" value="landingpage_action">'
    . '<div class="beratung-bulkbar"><label class="beratung-field"><span>Sammelaktion</span><select name="bulk_action"><option value="">Bitte wählen</option><option value="publish">Veröffentlichen</option><option value="draft">Als Entwurf</option><option value="private">Privat</option><option value="archived">Archivieren</option><option value="delete">Löschen</option></select></label>'
    . '<button type="submit" class="btn btn-outline-secondary" data-cms-confirm="Sammelaktion für die ausgewählten Landingpages ausführen? Gelöschte Seiten lassen sich nicht wiederherstellen.">Anwenden</button></div>'
    . '<div class="users-table-container"><table class="users-table beratung-table"><thead><tr><th class="beratung-col-check"><span class="visually-hidden">Auswahl</span></th><th>Landingpage</th><th>Status</th><th>Template</th><th>Geändert</th><th class="beratung-col-actions">Aktionen</th></tr></thead><tbody>';

foreach ($result['rows'] as $row) {
    $id = (int) ($row['id'] ?? 0);
    $status = (string) ($row['status'] ?? 'draft');
    $hero = json_decode((string) ($row['hero_json'] ?? '{}'), true);
    $hero = is_array($hero) ? $hero : [];
    $publicUrl = $status === 'published' ? $admin::public_landingpage_url((string) ($row['slug'] ?? '')) : '';
    $standaloneUrl = $status === 'published' && !empty($hero['standalone_header_enabled']) && !empty($hero['standalone_header_slug']) ? $admin::public_landingpage_url((string) $hero['standalone_header_slug']) : '';
    $title = (string) (($row['internal_title'] ?? '') ?: 'Ohne internen Titel');

    echo '<tr><td class="beratung-col-check"><input type="checkbox" name="ids[]" value="' . $id . '" aria-label="' . $admin::esc($title) . ' auswählen"></td>'
        . '<td><a class="beratung-strong" href="' . $admin::esc($admin::admin_url('cms-beratung-new', ['id' => $id])) . '">' . $admin::esc($title) . '</a>'
        . '<div class="beratung-muted">' . $admin::esc((string) ($row['public_title'] ?? '')) . ' · <code>/beratung/' . $admin::esc((string) ($row['slug'] ?? '')) . '</code>' . (!empty($row['noindex']) ? ' · noindex' : '') . '</div></td>'
        . '<td>' . $admin::status_badge($status, $statuses[$status] ?? $status) . '</td>'
        . '<td>' . $admin::esc($templates[(string) ($row['template'] ?? '')] ?? (string) ($row['template'] ?? '')) . '</td>'
        . '<td>' . $admin::esc((string) ($row['updated_at'] ?? '')) . '</td>'
        . '<td class="beratung-col-actions"><div class="beratung-row-actions">'
        . '<a class="btn btn-sm btn-outline-primary" href="' . $admin::esc($admin::admin_url('cms-beratung-new', ['id' => $id])) . '" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . $admin::esc($admin::preview_url($id)) . '" target="_blank" rel="noopener noreferrer" title="Vorschau"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Vorschau</span></a>'
        . ($publicUrl !== '' ? '<a class="btn btn-sm btn-outline-secondary" href="' . $admin::esc($publicUrl) . '" target="_blank" rel="noopener noreferrer" title="Öffentliche Seite"><i class="ti ti-world" aria-hidden="true"></i><span class="visually-hidden">Öffentliche Seite</span></a>' : '')
        . ($standaloneUrl !== '' ? '<a class="btn btn-sm btn-outline-secondary" href="' . $admin::esc($standaloneUrl) . '" target="_blank" rel="noopener noreferrer" title="Standalone-Variante"><i class="ti ti-layout-navbar-collapse" aria-hidden="true"></i><span class="visually-hidden">Standalone-Variante</span></a>' : '')
        . '<button type="submit" class="btn btn-sm btn-outline-secondary" name="row_action" value="export:' . $id . '" title="JSON exportieren"><i class="ti ti-download" aria-hidden="true"></i><span class="visually-hidden">JSON exportieren</span></button>'
        . '<button type="submit" class="btn btn-sm btn-outline-secondary" name="row_action" value="duplicate:' . $id . '" title="Duplizieren"><i class="ti ti-copy" aria-hidden="true"></i><span class="visually-hidden">Duplizieren</span></button>'
        . ($status === 'published'
            ? '<button type="submit" class="btn btn-sm btn-outline-secondary" name="row_action" value="draft:' . $id . '" title="Veröffentlichung aufheben"><i class="ti ti-player-pause" aria-hidden="true"></i><span class="visually-hidden">Veröffentlichung aufheben</span></button>'
            : '<button type="submit" class="btn btn-sm btn-outline-success" name="row_action" value="publish:' . $id . '" title="Veröffentlichen"><i class="ti ti-player-play" aria-hidden="true"></i><span class="visually-hidden">Veröffentlichen</span></button>')
        . '<button type="submit" class="btn btn-sm btn-outline-danger" name="row_action" value="delete:' . $id . '" title="Löschen" data-cms-confirm="Landingpage „' . $admin::esc($title) . '“ endgültig löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>'
        . '</div></td></tr>';
}
echo '</tbody></table></div></form>';
echo $admin::pagination('cms-beratung-landingpages', $queryParams, (int) $result['total'], $pageNumber, $perPage);

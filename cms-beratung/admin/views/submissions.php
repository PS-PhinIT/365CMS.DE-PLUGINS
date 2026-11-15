<?php
/**
 * CMS Beratung – Anfragen: Filter, Sammelaktionen, CSV-Export, Pagination, DSGVO-Auskunftsdateien.
 *
 * @var array{status:string,landingpage_id:int,search:string} $filters
 * @var array{rows:array<int,array<string,mixed>>,total:int} $result
 * @var int $pageNumber
 * @var int $perPage
 * @var array<int,array<string,mixed>> $landingpages
 * @var list<array{name:string,size:int,created_at:string}> $exports
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$statuses = CMS_Beratung_Storage::submission_statuses();
$queryParams = array_filter(['status' => $filters['status'], 'landingpage' => $filters['landingpage_id'] > 0 ? (string) $filters['landingpage_id'] : '', 'q' => $filters['search']], static fn(string $value): bool => $value !== '');
$listUrl = $admin::admin_url('cms-beratung-submissions', $queryParams + ($pageNumber > 1 ? ['p' => $pageNumber] : []));

$exportForm = '<form method="post" action="' . $admin::esc($listUrl) . '" class="beratung-inline-form">' . $admin::hidden_token('beratung_submissions')
    . '<input type="hidden" name="beratung_admin_action" value="export_csv">'
    . '<input type="hidden" name="status" value="' . $admin::esc($filters['status']) . '"><input type="hidden" name="landingpage" value="' . (int) $filters['landingpage_id'] . '"><input type="hidden" name="q" value="' . $admin::esc($filters['search']) . '">'
    . '<button type="submit" class="btn btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV-Export</button></form>';
$admin::page_header('Anfragen', 'Beratungsanfragen aus den Landingpage-Formularen.', $exportForm);
$admin::render_alerts();

echo '<form method="get" action="' . $admin::esc($admin::admin_url('cms-beratung-submissions')) . '" class="beratung-toolbar toolbar" role="search">'
    . '<label class="beratung-field"><span>Suche</span><input type="search" name="q" value="' . $admin::esc($filters['search']) . '" placeholder="Name, E-Mail, Firma, Text"></label>'
    . '<label class="beratung-field"><span>Status</span><select name="status"><option value="">Alle Status</option>';
foreach ($statuses as $key => $label) {
    echo '<option value="' . $admin::esc($key) . '"' . ($filters['status'] === $key ? ' selected' : '') . '>' . $admin::esc($label) . '</option>';
}
echo '</select></label><label class="beratung-field"><span>Landingpage</span><select name="landingpage"><option value="">Alle Landingpages</option>';
foreach ($landingpages as $landingpage) {
    $lpId = (int) ($landingpage['id'] ?? 0);
    echo '<option value="' . $lpId . '"' . ($filters['landingpage_id'] === $lpId ? ' selected' : '') . '>' . $admin::esc((string) (($landingpage['internal_title'] ?? '') ?: ($landingpage['public_title'] ?? ''))) . '</option>';
}
echo '</select></label><div class="beratung-toolbar__actions"><button type="submit" class="btn btn-outline-primary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>'
    . ($queryParams !== [] ? $admin::button_link('Zurücksetzen', $admin::admin_url('cms-beratung-submissions'), 'ti ti-x') : '') . '</div></form>';

if ($result['rows'] === []) {
    echo '<div class="admin-card empty-state"><h3>' . ($queryParams !== [] ? 'Keine Anfragen für diesen Filter.' : 'Noch keine Anfragen vorhanden.') . '</h3><p>Anfragen erscheinen hier, sobald jemand das Formular einer veröffentlichten Landingpage absendet.</p></div>';
} else {
    echo '<form method="post" action="' . $admin::esc($listUrl) . '" class="admin-card beratung-list-form">' . $admin::hidden_token('beratung_submissions')
        . '<input type="hidden" name="beratung_admin_action" value="bulk_submissions">'
        . '<div class="beratung-bulkbar"><label class="beratung-field"><span>Sammelaktion</span><select name="bulk_action"><option value="">Bitte wählen</option>';
    foreach ($statuses as $key => $label) {
        echo '<option value="' . $admin::esc($key) . '">Status: ' . $admin::esc($label) . '</option>';
    }
    echo '<option value="delete">Löschen</option></select></label><button type="submit" class="btn btn-outline-secondary" data-cms-confirm="Sammelaktion für die ausgewählten Anfragen ausführen? Gelöschte Anfragen lassen sich nicht wiederherstellen.">Anwenden</button></div>'
        . '<div class="users-table-container"><table class="users-table beratung-table"><thead><tr><th class="beratung-col-check"><span class="visually-hidden">Auswahl</span></th><th>Datum</th><th>Name / E-Mail</th><th>Landingpage</th><th>Thema</th><th>Status</th></tr></thead><tbody>';
    foreach ($result['rows'] as $row) {
        $id = (int) $row['id'];
        $status = (string) ($row['status'] ?? 'new');
        $name = (string) (($row['sender_name'] ?? '') ?: 'Ohne Namen');
        echo '<tr' . ($status === 'new' ? ' class="is-new"' : '') . '><td class="beratung-col-check"><input type="checkbox" name="ids[]" value="' . $id . '" aria-label="Anfrage von ' . $admin::esc($name) . ' auswählen"></td>'
            . '<td>' . $admin::esc((string) ($row['created_at'] ?? '')) . '</td>'
            . '<td><a class="beratung-strong" href="' . $admin::esc($admin::admin_url('cms-beratung-submissions', ['id' => $id])) . '">' . $admin::esc($name) . '</a><div class="beratung-muted">' . $admin::esc((string) ($row['sender_email'] ?? '')) . ((string) ($row['company'] ?? '') !== '' ? ' · ' . $admin::esc((string) $row['company']) : '') . '</div></td>'
            . '<td>' . $admin::esc((string) (($row['landingpage_title'] ?? '') ?: '–')) . '</td>'
            . '<td>' . $admin::esc((string) (($row['topic'] ?? '') ?: ($row['desired_service'] ?? ''))) . '</td>'
            . '<td>' . $admin::status_badge($status, $statuses[$status] ?? $status) . '</td></tr>';
    }
    echo '</tbody></table></div></form>';
    echo $admin::pagination('cms-beratung-submissions', $queryParams, (int) $result['total'], $pageNumber, $perPage);
}

echo '<section class="admin-card"><h3>DSGVO-Auskunftsdateien</h3><p class="beratung-muted">Entstehen beim Abschließen einer DSGVO-Auskunft (Admin → DSGVO) für Personen mit Anfragen; Abgleich über die E-Mail-Adresse. Dateien werden nach '
    . CMS_Beratung_Integrations::EXPORT_KEEP_DAYS . ' Tagen automatisch entfernt.</p>';
if ($exports === []) {
    echo '<p class="beratung-muted">Keine Auskunftsdateien vorhanden.</p>';
} else {
    echo '<div class="users-table-container"><table class="users-table"><thead><tr><th>Datei</th><th>Erstellt</th><th>Größe</th><th class="beratung-col-actions">Aktionen</th></tr></thead><tbody>';
    foreach ($exports as $file) {
        echo '<tr><td><code>' . $admin::esc($file['name']) . '</code></td><td>' . $admin::esc($file['created_at']) . '</td><td>' . number_format($file['size'] / 1024, 1, ',', '.') . ' KB</td><td class="beratung-col-actions">'
            . '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-submissions')) . '" class="beratung-row-actions">' . $admin::hidden_token('beratung_submissions')
            . '<input type="hidden" name="beratung_admin_action" value="privacy_export"><input type="hidden" name="file" value="' . $admin::esc($file['name']) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-secondary" title="Herunterladen"><i class="ti ti-download" aria-hidden="true"></i><span class="visually-hidden">Herunterladen</span></button>'
            . '<button type="submit" class="btn btn-sm btn-outline-danger" name="delete" value="1" title="Löschen" data-cms-confirm="Auskunftsdatei löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';

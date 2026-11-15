<?php
/**
 * CMS Beratung – Detailansicht einer Anfrage (Status, interne Notiz, Löschen).
 *
 * @var array<string,mixed>|null $submission
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$back = $admin::button_link('Zur Liste', $admin::admin_url('cms-beratung-submissions'), 'ti ti-arrow-left');

if ($submission === null) {
    $admin::page_header('Anfrage', 'Die Anfrage wurde nicht gefunden.', $back);
    echo '<div class="alert alert-danger">Die Anfrage existiert nicht (mehr).</div>';
    return;
}

$id = (int) $submission['id'];
$statuses = CMS_Beratung_Storage::submission_statuses();
$email = (string) ($submission['sender_email'] ?? '');
$actions = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $admin::button_link('Antworten', 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode('Ihre Beratungsanfrage'), 'ti ti-mail-forward', 'btn btn-outline-primary') : '') . $back;
$admin::page_header('Anfrage von ' . ((string) ($submission['sender_name'] ?? '') ?: 'Unbekannt'), 'Eingegangen am ' . (string) ($submission['created_at'] ?? ''), $actions);
$admin::render_alerts();

$rows = [
    'Name' => (string) ($submission['sender_name'] ?? ''),
    'E-Mail' => $email,
    'Telefon' => (string) ($submission['phone'] ?? ''),
    'Unternehmen' => (string) ($submission['company'] ?? ''),
    'Thema' => (string) ($submission['topic'] ?? ''),
    'Wunschleistung' => (string) ($submission['desired_service'] ?? ''),
    'Landingpage' => (string) (($submission['landingpage_title'] ?? '') ?: '–'),
    'Einwilligung' => !empty($submission['consent']) ? 'erteilt' : 'nicht erteilt',
    'Kopie an Absender' => !empty($submission['copy_to_sender']) ? 'angefordert' : 'nein',
];
if ((string) ($submission['ip_address'] ?? '') !== '') {
    $rows['IP-Adresse'] = (string) $submission['ip_address'];
}
if ((string) ($submission['user_agent'] ?? '') !== '') {
    $rows['Browser'] = (string) $submission['user_agent'];
}

echo '<div class="beratung-grid-2"><section class="admin-card"><h3>Angaben</h3><dl class="beratung-dl">';
foreach ($rows as $label => $value) {
    echo '<dt>' . $admin::esc($label) . '</dt><dd>' . ($value !== '' ? $admin::esc($value) : '<span class="beratung-muted">–</span>') . '</dd>';
}
echo '</dl><h3>Nachricht</h3><div class="beratung-message">' . nl2br($admin::esc((string) ($submission['message'] ?? ''))) . '</div></section>';

echo '<section class="admin-card"><h3>Bearbeitung</h3><form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-submissions', ['id' => $id])) . '">'
    . $admin::hidden_token('beratung_submissions') . '<input type="hidden" name="beratung_admin_action" value="update_submission"><input type="hidden" name="id" value="' . $id . '">';
$admin::select('Status', 'status', (string) ($submission['status'] ?? 'new'), $statuses);
$admin::textarea('Interne Notiz', 'internal_note', (string) ($submission['internal_note'] ?? ''), 6);
echo '<div class="beratung-form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button></div></form>'
    . '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-submissions', ['id' => $id])) . '" class="beratung-danger-zone">'
    . $admin::hidden_token('beratung_submissions') . '<input type="hidden" name="beratung_admin_action" value="delete_submission"><input type="hidden" name="id" value="' . $id . '">'
    . '<button type="submit" class="btn btn-outline-danger" data-cms-confirm="Diese Anfrage endgültig löschen?"><i class="ti ti-trash" aria-hidden="true"></i> Anfrage löschen</button></form></section></div>';

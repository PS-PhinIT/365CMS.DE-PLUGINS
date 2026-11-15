<?php
/**
 * CMS Beratung – Übersicht (Dashboard).
 *
 * @var array<int,array<string,mixed>> $recentPages
 * @var array<int,array<string,mixed>> $recentSubmissions
 * @var array<string,mixed> $stats
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$settings = CMS_Beratung_Settings::all();
$statuses = CMS_Beratung_Settings::statuses();
$submissionStatuses = CMS_Beratung_Storage::submission_statuses();
$missing = CMS_Beratung_Installer::missing_tables();
$storedVersion = CMS_Beratung_Installer::stored_version();
$recipient = (string) ($settings['contact_recipient_email'] ?? '');
$notify = ($settings['notification_enabled'] ?? '1') === '1';
$retention = CMS_Beratung_Settings::retention_days();
$newCount = (int) ($stats['submissions']['new'] ?? 0);
$showSubmissions = ($settings['show_submissions_backend'] ?? '1') === '1';

$actions = [
    ['label' => 'Neue Landingpage', 'url' => $admin::admin_url('cms-beratung-new'), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Vorlage wählen und als Entwurf speichern'],
    ['label' => 'Landingpages verwalten', 'url' => $admin::admin_url('cms-beratung-landingpages'), 'icon' => 'ti ti-file-text', 'description' => (int) ($stats['pages']['draft'] ?? 0) . ' Entwürfe, ' . (int) ($stats['pages']['published'] ?? 0) . ' veröffentlicht'],
];
if ($showSubmissions) {
    $actions[] = ['label' => 'Anfragen bearbeiten', 'url' => $admin::admin_url('cms-beratung-submissions', $newCount > 0 ? ['status' => 'new'] : []), 'icon' => 'ti ti-inbox', 'description' => $newCount . ' neue Anfrage(n)'];
}
$actions[] = ['label' => 'Kontaktformular einrichten', 'url' => $admin::admin_url('cms-beratung-settings', ['tab' => 'contact']), 'icon' => 'ti ti-mail'];
$actions[] = ['label' => 'Import und Export', 'url' => $admin::admin_url('cms-beratung-import-export'), 'icon' => 'ti ti-transfer'];

$checks = [
    ['label' => 'Datenbanktabellen', 'status' => $missing === [] ? 'ok' : 'error', 'value' => $missing === [] ? 'vorhanden' : 'fehlen: ' . implode(', ', $missing)],
    ['label' => 'Schema-Version', 'status' => $storedVersion === CMS_BERATUNG_DB_VERSION ? 'ok' : 'warning', 'value' => $storedVersion . ' / ' . CMS_BERATUNG_DB_VERSION],
    ['label' => 'Empfänger für Anfragen', 'status' => !$notify ? 'muted' : (filter_var($recipient, FILTER_VALIDATE_EMAIL) ? 'ok' : 'warning'), 'value' => !$notify ? 'Benachrichtigung aus' : (filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : 'nicht hinterlegt'), 'url' => $admin::admin_url('cms-beratung-settings', ['tab' => 'contact'])],
    ['label' => 'Anfragen speichern', 'status' => ($settings['form_storage_enabled'] ?? '1') === '1' ? 'ok' : 'info', 'value' => ($settings['form_storage_enabled'] ?? '1') === '1' ? 'aktiv' : 'nur E-Mail'],
    ['label' => 'Spamschutz', 'status' => (($settings['honeypot_enabled'] ?? '1') === '1' || ($settings['captcha_prepared'] ?? '0') === '1') ? 'ok' : 'warning', 'value' => trim((($settings['honeypot_enabled'] ?? '1') === '1' ? 'Honeypot ' : '') . (($settings['captcha_prepared'] ?? '0') === '1' ? 'Sicherheitsfrage ' : '') . '+ Ratenlimit')],
    ['label' => 'Löschfrist Anfragen', 'status' => $retention > 0 ? 'ok' : 'info', 'value' => $retention > 0 ? $retention . ' Tage' : 'nie (manuell)', 'url' => $admin::admin_url('cms-beratung-settings', ['tab' => 'privacy'])],
    ['label' => 'SEO-Service des Cores', 'status' => class_exists('CMS\\Services\\SEOService') ? 'ok' : 'info', 'value' => class_exists('CMS\\Services\\SEOService') ? 'Meta über setRequestMeta()' : 'Plugin-eigene Meta-Tags'],
];

$items = [];
foreach ($recentPages as $row) {
    $status = (string) ($row['status'] ?? 'draft');
    $items[] = [
        'title' => (string) (($row['internal_title'] ?? '') ?: ($row['public_title'] ?? 'Landingpage')),
        'url' => $admin::admin_url('cms-beratung-new', ['id' => (int) $row['id']]),
        'meta' => '/beratung/' . (string) ($row['slug'] ?? '') . ' · ' . (string) ($row['updated_at'] ?? ''),
        'badge' => $statuses[$status] ?? $status,
        'tone' => $status === 'published' ? 'success' : ($status === 'draft' ? 'warning' : 'muted'),
    ];
}

echo cms_plugin_dashboard([
    'intro' => 'Landingpages anlegen, Inhalte im Builder pflegen und Beratungsanfragen bearbeiten.',
    'actions' => $actions,
    'checks' => $checks,
    'activity' => [
        'heading' => 'Zuletzt geänderte Landingpages',
        'items' => $items,
        'empty' => 'Noch keine Landingpages vorhanden.',
        'more_url' => $admin::admin_url('cms-beratung-landingpages'),
    ],
]);

if ($showSubmissions) {
    echo '<section class="admin-card beratung-dashboard-submissions"><h3>Neueste Anfragen</h3>';
    if ($recentSubmissions === []) {
        echo '<p class="beratung-muted">Noch keine Anfragen eingegangen.</p>';
    } else {
        echo '<div class="users-table-container"><table class="users-table"><thead><tr><th>Datum</th><th>Name</th><th>Landingpage</th><th>Status</th></tr></thead><tbody>';
        foreach ($recentSubmissions as $row) {
            $status = (string) ($row['status'] ?? 'new');
            echo '<tr><td>' . $admin::esc((string) ($row['created_at'] ?? '')) . '</td>'
                . '<td><a href="' . $admin::esc($admin::admin_url('cms-beratung-submissions', ['id' => (int) $row['id']])) . '">' . $admin::esc((string) (($row['sender_name'] ?? '') ?: 'Ohne Namen')) . '</a></td>'
                . '<td>' . $admin::esc((string) (($row['landingpage_title'] ?? '') ?: '–')) . '</td>'
                . '<td>' . $admin::status_badge($status, $submissionStatuses[$status] ?? $status) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

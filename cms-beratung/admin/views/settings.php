<?php
/**
 * CMS Beratung – Einstellungen eines Bereichs (Design, Kontaktformular, Datenschutz, SEO, Tracking).
 *
 * @var string $tab
 * @var array{label:string,icon:string,description:string,keys:list<string>} $definition
 * @var array<string,string> $settings
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$labels = CMS_Beratung_Settings::labels();
$numberKeys = ['border_radius', 'spacing', 'content_width', 'font_size_base', 'font_size_hero', 'font_size_section_title', 'font_size_card_title', 'header_spacing', 'footer_spacing', 'card_spacing', 'section_content_spacing', 'retention_days'];

echo '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-settings', ['tab' => $tab])) . '" class="admin-card beratung-settings-form">'
    . $admin::hidden_token('beratung_settings')
    . '<input type="hidden" name="beratung_admin_action" value="save_settings"><div class="beratung-form-grid">';
foreach ($definition['keys'] as $key) {
    $label = (string) ($labels[$key]['label'] ?? $key);
    $hint = (string) ($labels[$key]['hint'] ?? '');
    $value = (string) ($settings[$key] ?? '');
    if (CMS_Beratung_Settings::is_checkbox($key)) {
        echo '<div class="beratung-form-grid__wide">';
        $admin::checkbox($label, $key, $value === '1', $hint);
        echo '</div>';
    } elseif (str_contains($key, 'color')) {
        $admin::field($label, $key, $value, 'color', $hint);
    } elseif (str_contains($key, 'message') || str_contains($key, 'privacy')) {
        echo '<div class="beratung-form-grid__wide">';
        $admin::textarea($label, $key, $value, 3);
        echo '</div>';
    } else {
        $admin::field($label, $key, $value, in_array($key, $numberKeys, true) ? 'number' : (str_contains($key, 'email') ? 'email' : 'text'), $hint);
    }
}
echo '</div><div class="beratung-form-actions"><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button></div></form>';

if ($tab === 'privacy') {
    $days = CMS_Beratung_Settings::retention_days();
    echo '<div class="alert alert-info">Die Löschfrist ist als Regel „Beratungsanfragen (CMS Beratung)“ in der zentralen Datenaufbewahrung des CMS registriert und läuft mit dem täglichen Cron. '
        . ($days > 0 ? 'Aktuell fällig: ' . CMS_Beratung_Storage::instance()->count_submissions_older_than($days) . ' Anfrage(n) älter als ' . $days . ' Tage.' : 'Derzeit werden Anfragen nicht automatisch gelöscht.')
        . ' DSGVO-Auskünfte und Löschungen (Admin → DSGVO) berücksichtigen die Anfragen über die E-Mail-Adresse.</div>';
}

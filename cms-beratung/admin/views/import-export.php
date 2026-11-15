<?php
/**
 * CMS Beratung – JSON-Import/-Export von Landingpages und Konfiguration.
 *
 * @var array<int,array<string,mixed>> $landingpages
 * @var array<string,mixed>|null $previewPage
 * @var string $previewJson
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$action = $admin::esc($admin::admin_url('cms-beratung-import-export'));
$options = '';
foreach ($landingpages as $landingpage) {
    $options .= '<option value="' . (int) ($landingpage['id'] ?? 0) . '"' . ($previewPage !== null && (int) $previewPage['id'] === (int) ($landingpage['id'] ?? 0) ? ' selected' : '') . '>'
        . $admin::esc((string) (($landingpage['internal_title'] ?? '') ?: ($landingpage['public_title'] ?? 'Landingpage'))) . ' (/beratung/' . $admin::esc((string) ($landingpage['slug'] ?? '')) . ')</option>';
}

echo '<div class="beratung-grid-2">';

echo '<form method="post" enctype="multipart/form-data" action="' . $action . '" class="admin-card"><h3>Landingpage importieren</h3>'
    . '<p class="beratung-muted">JSON-Datei (.json, höchstens 2 MB) hochladen oder JSON einfügen. Alle Felder werden geprüft und bereinigt; importierte Seiten sind immer Entwürfe.</p>'
    . $admin::hidden_token('beratung_import_export') . '<input type="hidden" name="beratung_admin_action" value="import_json">'
    . '<label class="beratung-field"><span>JSON-Datei</span><input type="file" name="import_file" accept="application/json,.json"></label>'
    . '<label class="beratung-field"><span>Oder JSON einfügen</span><textarea name="import_json" rows="8" class="beratung-code"></textarea></label>'
    . '<label class="beratung-field"><span>Import-Modus</span><select name="import_mode"><option value="new">Als neue Landingpage importieren</option><option value="overwrite">Bestehende Landingpage überschreiben</option></select></label>'
    . '<label class="beratung-field"><span>Zu überschreibende Landingpage</span><select name="overwrite_id"><option value="0">Bitte wählen</option>' . $options . '</select></label>'
    . '<input type="hidden" name="skip_images" value="0"><label class="beratung-check"><input type="checkbox" name="skip_images" value="1"> <span>Bildpfade beim Import leeren</span></label>'
    . '<div class="beratung-form-actions"><button class="btn btn-primary" type="submit" data-cms-confirm="Import starten? Beim Überschreiben wird der bisherige Inhalt ersetzt."><i class="ti ti-upload" aria-hidden="true"></i> Importieren</button></div></form>';

echo '<div class="beratung-stack"><form method="post" action="' . $action . '" class="admin-card"><h3>Landingpage exportieren</h3>'
    . '<p class="beratung-muted">Lädt die Landingpage als JSON-Datei (Format cms-beratung-landingpage/v1) herunter.</p>'
    . $admin::hidden_token('beratung_import_export') . '<input type="hidden" name="beratung_admin_action" value="export_landingpage">'
    . '<label class="beratung-field"><span>Landingpage</span><select name="export_id" required><option value="">Bitte wählen</option>' . $options . '</select></label>'
    . '<div class="beratung-form-actions"><button class="btn btn-outline-primary" type="submit"><i class="ti ti-download" aria-hidden="true"></i> JSON herunterladen</button></div></form>';

echo '<form method="post" action="' . $action . '" class="admin-card"><h3>Konfiguration exportieren</h3>'
    . '<p class="beratung-muted">Globale Einstellungen, M365-FAQs und eigene Design-Presets als JSON.</p>'
    . $admin::hidden_token('beratung_import_export') . '<input type="hidden" name="beratung_admin_action" value="export_config">'
    . '<div class="beratung-form-actions"><button class="btn btn-outline-primary" type="submit"><i class="ti ti-download" aria-hidden="true"></i> Konfiguration herunterladen</button></div></form>';

echo '<form method="post" enctype="multipart/form-data" action="' . $action . '" class="admin-card"><h3>Konfiguration importieren</h3>'
    . '<p class="beratung-muted">Überschreibt die enthaltenen Einstellungen und FAQs; Presets werden ergänzt.</p>'
    . $admin::hidden_token('beratung_import_export') . '<input type="hidden" name="beratung_admin_action" value="import_config">'
    . '<label class="beratung-field"><span>Konfigurationsdatei (.json)</span><input type="file" name="config_file" accept="application/json,.json" required></label>'
    . '<div class="beratung-form-actions"><button class="btn btn-outline-primary" type="submit" data-cms-confirm="Konfiguration importieren und bestehende Einstellungen überschreiben?"><i class="ti ti-upload" aria-hidden="true"></i> Konfiguration importieren</button></div></form></div>';

echo '</div>';

if ($previewPage !== null) {
    echo '<section class="admin-card"><h3>JSON-Vorschau: ' . $admin::esc((string) ($previewPage['internal_title'] ?? '')) . '</h3><textarea readonly rows="16" class="beratung-code">' . $admin::esc($previewJson) . '</textarea></section>';
}

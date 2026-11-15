<?php
/**
 * CMS Beratung – Design-Presets (System und eigene) samt Anlegen/Löschen.
 *
 * @var array<int,array<string,mixed>> $presets
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
$colorKeys = CMS_Beratung_Storage::preset_color_keys();

echo '<ul class="beratung-preset-grid">';
foreach ($presets as $preset) {
    $design = json_decode((string) ($preset['design_json'] ?? '{}'), true);
    $design = is_array($design) ? $design : [];
    echo '<li class="admin-card beratung-preset"><div class="beratung-preset__head"><h3>' . $admin::esc((string) ($preset['name'] ?? 'Preset')) . '</h3>'
        . $admin::status_badge(!empty($preset['is_system']) ? 'system' : 'published', !empty($preset['is_system']) ? 'System' : 'Eigenes') . '</div>'
        . '<p class="beratung-muted">' . $admin::esc((string) ($preset['description'] ?? '')) . '</p><ul class="beratung-swatches" aria-label="Farben">';
    foreach ($colorKeys as $key) {
        $color = CMS_Beratung_Settings::color((string) ($design[$key] ?? ''), '');
        if ($color === '') {
            continue;
        }
        // Deaktiviertes Farbfeld als Farbmuster – kein Inline-Style nötig.
        echo '<li title="' . $admin::esc((string) ($labels[$key]['label'] ?? $key) . ': ' . $color) . '"><input type="color" value="' . $admin::esc($color) . '" disabled tabindex="-1" aria-label="' . $admin::esc((string) ($labels[$key]['label'] ?? $key) . ' ' . $color) . '"></li>';
    }
    echo '</ul>';
    if (empty($preset['is_system'])) {
        echo '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-presets')) . '">' . $admin::hidden_token('beratung_presets')
            . '<input type="hidden" name="beratung_admin_action" value="delete_preset"><input type="hidden" name="preset_id" value="' . (int) $preset['id'] . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger" data-cms-confirm="Preset „' . $admin::esc((string) $preset['name']) . '“ löschen?"><i class="ti ti-trash" aria-hidden="true"></i> Löschen</button></form>';
    }
    echo '</li>';
}
echo '</ul>';

echo '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-presets')) . '" class="admin-card"><h3>Eigenes Preset anlegen</h3>'
    . '<p class="beratung-muted">Vorbelegt mit den globalen Design-Einstellungen.</p>'
    . $admin::hidden_token('beratung_presets') . '<input type="hidden" name="beratung_admin_action" value="create_preset"><div class="beratung-form-grid">';
$admin::field('Name', 'preset_name', '');
$admin::field('Beschreibung', 'preset_description', '');
foreach ($colorKeys as $key) {
    $admin::field((string) ($labels[$key]['label'] ?? $key), $key, (string) ($settings[$key] ?? '#ffffff'), 'color');
}
echo '</div><div class="beratung-form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-plus" aria-hidden="true"></i> Preset anlegen</button></div></form>';

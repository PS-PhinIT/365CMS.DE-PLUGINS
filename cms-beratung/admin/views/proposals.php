<?php
/**
 * CMS Beratung – Vorlagenauswahl für neue Landingpages.
 *
 * @var string $active
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$proposals = CMS_Beratung_Admin_Defaults::landingpage_proposals();

echo '<section class="admin-card beratung-template-library"><h3>Vorlage wählen</h3><p class="beratung-muted">Die Vorlage wird mit Platzhaltern in den Editor geladen und erst beim Speichern als Entwurf angelegt.</p><ul class="beratung-template-grid">';
foreach ($proposals as $slug => $proposal) {
    $isActive = $slug === $active;
    echo '<li><a class="beratung-template-card' . ($isActive ? ' is-active' : '') . '" href="' . $admin::esc($admin::admin_url('cms-beratung-new', ['proposal' => $slug])) . '"' . ($isActive ? ' aria-current="true"' : '') . '>'
        . '<i class="' . $admin::esc((string) ($proposal['icon'] ?? 'ti ti-file')) . '" aria-hidden="true"></i>'
        . '<strong>' . $admin::esc((string) ($proposal['title'] ?? $slug)) . '</strong>'
        . '<small>' . $admin::esc((string) ($proposal['badge'] ?? 'Vorlage')) . '</small>'
        . '<span>' . $admin::esc((string) ($proposal['description'] ?? '')) . '</span>'
        . '<em>' . ($isActive ? 'Aktuell geladen' : 'Diese Vorlage laden') . '</em></a></li>';
}
echo '</ul></section>';

<?php
/**
 * Unterseite „Public Hub“: öffentliche Verzeichnisse, Hauptmenü-Link und Suchmaschinen-Anbindung.
 *
 * @var array<string, string> $settings
 * @var array{total:int,active:int,inactive:int} $experts
 * @var array{total:int,active:int,inactive:int} $companies
 * @var string $settingsUrl
 * @var string $siteUrl
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$navOn = ($settings['show_nav_link'] ?? '0') === '1';
$routes = [
    [
        'path' => '/experts-companie',
        'title' => (string) ($settings['hub_archive_title'] ?? 'Experts & Companie'),
        'text' => 'Gemeinsamer Hub mit Filter nach Typ, Ort und Suchbegriff. Aliase: /experts-and-companie, /experts-companies.',
        'count' => ($experts['active'] + $companies['active']) . ' sichtbare Einträge',
        'icon' => 'ti ti-world',
    ],
    [
        'path' => '/experts',
        'title' => (string) ($settings['experts_archive_title'] ?? 'Experts'),
        'text' => 'Verzeichnis der Expert:innen mit Detailseiten unter /experts/<id>.',
        'count' => $experts['active'] . ' von ' . $experts['total'] . ' sichtbar',
        'icon' => 'ti ti-user-star',
    ],
    [
        'path' => '/companies',
        'title' => (string) ($settings['companies_archive_title'] ?? 'Companies'),
        'text' => 'Verzeichnis der Unternehmen mit Detailseiten unter /companies/<id>.',
        'count' => $companies['active'] . ' von ' . $companies['total'] . ' sichtbar',
        'icon' => 'ti ti-building',
    ],
];
?>
<div class="admin-page-header">
    <div>
        <h2>Public Hub</h2>
        <p>Öffentliche Verzeichnisse, Hauptmenü-Link und Anbindung an Sitemap und Seitensuche.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?= $e($siteUrl . '/experts-companie') ?>" target="_blank" rel="noopener"><i class="ti ti-external-link" aria-hidden="true"></i> Hub öffnen</a>
        <a class="btn btn-outline-secondary" href="<?= $e($settingsUrl) ?>"><i class="ti ti-settings" aria-hidden="true"></i> Texte &amp; Layout</a>
    </div>
</div>

<div class="cms-excomp-route-grid">
    <?php foreach ($routes as $route): ?>
        <section class="admin-card cms-excomp-route">
            <h3><i class="<?= $e($route['icon']) ?>" aria-hidden="true"></i> <?= $e($route['title']) ?></h3>
            <p class="cms-excomp-route__path"><code><?= $e($route['path']) ?></code></p>
            <p class="cms-excomp-muted"><?= $e($route['text']) ?></p>
            <p class="cms-excomp-route__count"><?= $e($route['count']) ?></p>
            <a class="btn btn-sm btn-outline-primary" href="<?= $e($siteUrl . $route['path']) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link" aria-hidden="true"></i> Öffnen</a>
        </section>
    <?php endforeach; ?>
</div>

<section class="admin-card cms-excomp-card">
    <div class="cms-excomp-card__head">
        <h3><i class="ti ti-plug-connected" aria-hidden="true"></i> Anbindung</h3>
    </div>
    <div class="users-table-container">
        <table class="users-table">
            <thead>
            <tr><th scope="col">Bereich</th><th scope="col">Stand</th><th scope="col">Hinweis</th></tr>
            </thead>
            <tbody>
            <tr>
                <td>Link im Hauptmenü</td>
                <td><?= $navOn ? '<span class="status-badge active">Ein</span>' : '<span class="status-badge inactive">Aus</span>' ?></td>
                <td><?= $navOn ? 'Beschriftung „' . $e((string) ($settings['nav_label'] ?? '')) . '“, Ziel /experts.' : 'In den Einstellungen unter „Navigation“ einschalten.' ?></td>
            </tr>
            <tr>
                <td>Sitemap (plugins.xml)</td>
                <td><span class="status-badge active">Aktiv</span></td>
                <td>Die drei Verzeichnisse und <?= (int) ($experts['active'] + $companies['active']) ?> Detailseiten mit Status „aktiv“ werden gemeldet.</td>
            </tr>
            <tr>
                <td>Seitensuche (/search)</td>
                <td><span class="status-badge active">Aktiv</span></td>
                <td>Treffer aus Experts (Name, Firma, Position, Skills, Biografie) und Companies (Name, Branche, Beschreibung, Ort).</td>
            </tr>
            <tr>
                <td>Meta-Tags</td>
                <td><span class="status-badge active">Aktiv</span></td>
                <td>Titel, Beschreibung und Canonical der Verzeichnisse und Detailseiten; Filter- und Suchansichten sind auf „noindex“ gesetzt.</td>
            </tr>
            <tr>
                <td>Template-Overrides</td>
                <td><span class="status-badge active">Möglich</span></td>
                <td>Eigene Templates im Theme unter <code>cms-expertsandcompanie/</code> (gleiche Dateinamen wie <code>templates/</code>).</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<?php
/**
 * CMS Beratung – Hilfe.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$topics = [
    ['ti ti-route', 'Routing', 'Veröffentlichte Landingpages sind unter /beratung/{slug} (auch /de/… und /en/…) erreichbar. Eine optionale Standalone-Variante ohne Theme-Header/-Footer nutzt einen eigenen Slug und ist auf noindex gesetzt.'],
    ['ti ti-layout-grid', 'Bereiche', 'Jeder Bereich nach dem Content Header kann aktiviert, dupliziert, gelöscht und per Hoch/Runter oder Drag and Drop sortiert werden. Cards unterstützen 1 bis 4 Desktop-Spalten (Tablet 2, Mobil 1).'],
    ['ti ti-eye', 'Vorschau', 'Die Entwurfs-Vorschau (/api/cms-beratung/preview) ist nur für Administratoren erreichbar und nicht indexierbar.'],
    ['ti ti-world-search', 'SEO', 'Titel, Beschreibung, Canonical, Robots und Social Cards setzt das Plugin über den SEO-Service des Cores. Zusätzlich gibt es JSON-LD für Service, FAQPage, Breadcrumb und optional Organisation aus. Indexierbare Seiten stehen in der Sitemap (plugins.xml) und in der Seitensuche.'],
    ['ti ti-mail', 'Anfrageformular', 'Schutz durch CSRF-Token, Honeypot, optionale Sicherheitsfrage, Ratenbegrenzung (5 Anfragen je 10 Minuten) und den Antispam-Dienst des Cores. Mails werden als reiner Text versendet.'],
    ['ti ti-shield-lock', 'Datenschutz', 'Anfragen sind in DSGVO-Auskunft und -Löschung sowie in der Selbstauskunft des Mitgliederbereichs enthalten (Abgleich über die E-Mail-Adresse). Eine automatische Löschfrist lässt sich unter Einstellungen → Datenschutz festlegen.'],
    ['ti ti-chart-dots', 'Tracking', 'Aktivierte Ereignisse werden nur an die im CMS eingerichtete Webanalyse (Matomo, GA4, GTM) gemeldet, sobald deren Einwilligung vorliegt.'],
    ['ti ti-transfer', 'Import und Export', 'Landingpages und die Konfiguration lassen sich als JSON übertragen; Anfragen als CSV (UTF-8, Semikolon, mit Schutz vor Formel-Injection).'],
];

echo '<div class="beratung-help-grid">';
foreach ($topics as [$icon, $title, $text]) {
    echo '<section class="admin-card"><h3><i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3><p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p></section>';
}
echo '</div>';

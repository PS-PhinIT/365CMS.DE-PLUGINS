# CMS Promos

Promo- und Kampagnen-Plugin für 365CMS mit Platzierungen, CTA-Elementen, Ziel-URLs und einfacher Performance-Basis über Impressions und Klicks.

## Features

- Promo-Elemente mit Titel, Teaser, Content, Bild-URL und CTA verwalten
- Platzierungen wie Hero, Sidebar oder CTA-Zonen definieren
- Automatische Ausspielung über definierte Theme-Hooks wie `after_header`, `home_content` und `before_footer`
- Klick-Weiterleitung über `/promo/click/:slug`
- Öffentliche Übersicht über `/promos`
- Admin-Dashboard mit Statistik- und Verwaltungsseiten

## Anforderungen

- 365CMS 4.0.0 oder neuer
- PHP 8.4.1 oder neuer
- MySQL/MariaDB mit InnoDB, utf8mb4

## Admin

Menü **365CMS | Promos** unter `/admin/plugins/promos-dashboard`:

- `promos-dashboard` – Übersicht mit Schnellaktionen, System-Check und letzten Änderungen
- `promos-items` – Promos (Suche, Filter, Sammelaktionen, CSV-Export)
- `promos-placements` – Platzierungen und Theme-Hooks
- `promos-settings` – Archiv, Standardtexte, Klick-Export, Löschfrist, JSON-Export/-Import

## Integrationen

- Sitemap (`/promos`), Seitensuche (`_type` = `promos`), Datenaufbewahrung (`cms_promos.expired_promos`), Systemhinweise
- Hook `cms_promos_click_export` mit Klick-Payload (ohne personenbezogene Daten), optional Webhook

## Sicherheitsstatus (2026-04-04)

- Snyk-Code-Audit für `cms-promos` abgeschlossen, aktuell ohne offene Findings.
- Admin-Redirects verweisen nach schreibenden Aktionen jetzt ausschließlich auf feste interne Dashboard-Routen.
- Das reduziert Redirect-Manipulationen und beseitigt den vorherigen Open-Redirect-Befund.

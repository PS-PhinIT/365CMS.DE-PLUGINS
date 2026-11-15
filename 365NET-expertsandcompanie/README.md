# 365CMS | Experts & Companies

Version: 2.0.0

Kombi-Plugin für Experts und Companies mit gemeinsamer Public-Übersicht, eigenen Verzeichnissen und Detailseiten.

## Anforderungen

- 365CMS 4.0.0 oder neuer
- PHP 8.4.1 oder neuer
- Optional: Plugin „Events & Speaker“ (Verknüpfung von Experts/Companies mit Speakern)

## Öffentliche Seiten

- Gemeinsamer Hub: `/experts-companie` (Aliase `/experts-and-companie`, `/experts-companies`)
- Verzeichnisse: `/experts`, `/companies`; Detailseiten `/experts/<id>`, `/companies/<id>`
- Sitemap (`plugins.xml`), Seitensuche und SEO-Meta sind angebunden.

## Admin

`/admin/plugins/experts-companie` – Menütitel **365CMS | Experts & Companies** im Layout-Vertrag von 365CMS 4.0.0:

- Übersicht: Schnellaktionen, Status-/System-Check, zuletzt bearbeitet, Seed-Daten
- Public Hub: Routen, Hauptmenü-Link, Sitemap/Suche
- Experts / Companies: Suche, Status-Filter, Sortierung, Seiten, Sammelaktionen, Bearbeiten, Löschen
- Import & Export: CSV-Export/-Import (Format von `defaults/*.csv`), Vorlage, DSGVO-Auskunftsdateien
- Einstellungen: Navigation, Texte, Layout, Rundungen, Farben

## Technische Struktur

- `cms-365NETexpertsandcompanie.php` – Bootstrap, Frontend-Styles, Design-Variablen, Lifecycle-Funktionen
- `includes/class-database.php` – Schema (Version in `365net_excomp_settings`), Seed, CRUD, Batch-Abfragen
- `includes/class-admin.php` – Admin-Menü, Seiten-Controller; Views in `admin/views/`
- `includes/class-post-type.php` – öffentliche Routen, Bild-Upload, Weiterleitungen alter Admin-Adressen
- `includes/class-transfer.php` – CSV-Import/-Export
- `includes/class-integrations.php` – Sitemap, Suche, DSGVO, Systemhinweise
- `includes/class-template-loader.php` – Templates inkl. Theme-Override (`<theme>/cms-expertsandcompanie/`)
- `includes/phinit-admin-nav.php`, `includes/plugin-workspace.php`, `assets/css/phinit-admin-nav.css` – synchronisierte Kopien (nicht direkt bearbeiten)

## Hinweise

- Eigene Tabellen: `365net_excomp_experts`, `365net_excomp_companies`, `365net_excomp_settings` (werden auch vom Plugin „Events & Speaker“ gelesen).
- Seed-Daten: `defaults/experts.csv`, `defaults/companies.csv`.

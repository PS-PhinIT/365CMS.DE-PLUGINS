# 365CMS | Projekte – Admin & Integration (ab 4.0.0)

> **Plugin:** `cms-projects` · **Version:** 4.0.0 · **Stand:** 2026-11-14
> **Voraussetzungen:** 365CMS 4.0.0 (Plugin-Layout-Vertrag), PHP 8.4.1

## 1. Admin-Oberfläche

Alle Seiten nutzen den Core-Layout-Vertrag (`cms_plugin_workspace_open()` / `cms_plugin_dashboard()` aus `CMS/admin/partials/plugin-workspace.php`, Gestaltung `CMS/assets/css/admin-plugins.css`).

### Kopfzeile

| Element | Inhalt |
|---|---|
| Titel | `365CMS \| Projekte`, Versions-Badge, Status-Badge „n aktiv“ |
| KPI 1 | Projekte gesamt (Link zur Liste) |
| KPI 2 | Aktive Projekte (Link mit Filter `status=active`) |
| KPI 3 | Tickets gesamt |
| KPI 4 | Überfällige Tickets (Ton „warning“, wenn > 0) |

### Plugin-Menü (links)

| Menüpunkt | URL | Inhalt |
|---|---|---|
| Übersicht | `/admin/plugins/cms-projects/cms-projects` | Schnellaktionen, System-Check, zuletzt geänderte Tickets |
| Projekte | `…/cms-projects-projects` | Liste mit Filter (Suche, Status, Sichtbarkeit), Neu, Löschen |
| ↳ Projekt: *Name* | `…/cms-projects-projects?project_id=<id>&tab=<reiter>` | Reiter **Stammdaten**, **Boards**, **Widgets**, **Tickets** (nur bei geöffnetem Projekt, mit Zähler-Badges) |
| Audit-Log | `…/cms-projects-audit` | letzte 200 Einträge, Filter Projekt/Bereich/Ergebnis, CSV-Export |
| Einstellungen | `…/cms-projects-settings` | Löschfrist Audit-Protokoll, Bereinigen, Tabellen prüfen |

`$_GET['page']` wird vom AdminRouter aus dem Pfad gesetzt; ältere Links `?page=cms-projects-projects` funktionieren weiter.

### Funktionen je Reiter

- **Stammdaten:** Name, Slug, Owner, Status, Sichtbarkeit (Öffentlich/Member/Privat), Akzentfarbe, Headlines, Zusammenfassung, Beschreibung; Projekt löschen (transaktional inkl. Boards, Widgets, Tickets).
- **Boards:** Vorschau aller Boards, Anlegen/Bearbeiten/Löschen (inkl. Tickets), Struktur als JSON (`columns`/`lanes`/`clusters`/`stages`/`milestones`, optional `wip_limit`).
- **Widgets:** Typen `text`, `checklist`, `links`, `stats`, `timeline`; Anzeige Member/Public/beide; Bearbeiten/Löschen.
- **Tickets:** Board-Ansicht mit Drag & Drop (`assets/js/admin-board.js`), Ticketliste mit Fälligkeit/Überfällig-Markierung, Formular.
- **Kopfaktionen:** Member-/Public-Vorschau, JSON-Export des gesamten Projekts.

## 2. Öffentliche und Member-Ausgabe

| Bereich | Route / Einbindung | Regeln |
|---|---|---|
| Archiv | `/projects`, `/en/projects` | nur `status=active` und `visibility=public` |
| Detail | `/projects/<slug>` | wie Archiv; sonst 404 |
| Member | `/member/plugin/projects` | angemeldete Mitglieder; nur aktive/pausierte Projekte mit Sichtbarkeit Member oder Öffentlich |
| Shortcode | `[cms_projects project="slug" scope="public\|member"]` | `member` nur für angemeldete Besucher |
| Shortcode | `[cms_projects_widget project="slug" scope="…" type="stats"]` | prüft Sichtbarkeit und Status |

Member-Registrierung über `PluginDashboardRegistry::register()` mit `capability => 'member'` (4.0.0 verweigert unbekannte Capability-Namen).

## 3. Core-Integrationen

| Integration | Umsetzung |
|---|---|
| Sitemap | Filter `cms_sitemap_entries`: `/projects` und `/projects/<slug>` aller öffentlichen Projekte |
| Seitensuche | Filter `search_results`: öffentliche Projekte, die alle Suchwörter in Name/Zusammenfassung/Beschreibung enthalten; `_type = projects` |
| Datenaufbewahrung | `DataRetentionService::registerRule('cms_projects_audit_logs')` – Frist aus Einstellungen (Standard 365 Tage), täglicher Cron löscht in Blöcken |
| Systemhinweise | `AdminNoticeService::registerCheck('cms_projects_overdue')` – Hinweis bei überfälligen Tickets |
| Papierkorb / ContentTypeRegistry | nicht angebunden (Projekte sind keine redaktionellen Inhalte; Löschen erfolgt bewusst mit Bestätigung und JSON-Export) |

## 4. Lebenszyklus und Datenbank

| Callback | Verhalten |
|---|---|
| `cms_projects_activate()` | Tabellen anlegen/migrieren (`boot(true)`), Option `cms_projects_db_version` setzen |
| `cms_projects_deactivate()` | keine Datenänderung |
| `cms_projects_uninstall()` | alle Tabellen löschen, Optionen `cms_projects_db_version` und `cms_projects_audit_retention_days` entfernen |

Tabellen (Präfix aus der Runtime): `projects_projects`, `projects_boards`, `projects_widgets`, `projects_tasks`, `projects_audit_logs` – InnoDB, `utf8mb4_unicode_ci`, Indizes auf Status/Sichtbarkeit, Projekt/Position, Board/Spalte, Audit-Zeitpunkt. Die Schema-Prüfung läuft nur, wenn `cms_projects_db_version` (Autoload-Option, über `OptionStore` gelesen) von `CMS_Projects_Service::DB_VERSION` abweicht.

## 5. Sicherheit

- Alle Admin-Seiten: `current_user_can('manage_options')`; jeder POST mit CSRF-Token `cms_projects_admin` (einmalig), Exporte mit persistentem Token `cms_projects_export`.
- Redirects ausschließlich über `Security::safeLocalPath()`.
- SQL ausschließlich über Prepared Statements des Core-Wrappers; Tabellennamen aus bereinigten Bezeichnern, `LIMIT` als Integer.
- Ausgaben mit `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`; JSON-Export mit `JSON_HEX_TAG`; CSV gegen Formel-Injektion geschützt.
- Keine Inline-Skripte oder Event-Handler; Bestätigungen über `data-cms-confirm`; Projektfarben über nonce-geschützte `<style>`-Regeln (`CMS_Projects_Service::accentSwatch()`), keine `style`-Attribute.
- Widget-Links nur `http`/`https` ohne Zugangsdaten oder private Hosts.

## 6. Assets

| Datei | Geladen |
|---|---|
| `assets/css/style.css` | `/projects*`, Member-Bereich „Projekte“, einmalig mit Shortcode-Ausgabe, Admin-Projektseiten |
| `assets/css/admin.css` | nur Admin-Projektseiten (Core-Tokens, Light/Dark) |
| `assets/js/admin-board.js` | nur Admin-Reiter „Tickets“, `defer` |

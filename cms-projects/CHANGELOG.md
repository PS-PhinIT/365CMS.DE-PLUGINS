# Changelog – CMS Projects

## 4.0.0 – 2026-11-14

### Layout

- Alle Admin-Seiten nutzen den Plugin-Layout-Vertrag 4.0.0 (`cms_plugin_workspace_open()`/`_close()`): Kopfzeile „365CMS | Projekte“ mit Version, Status-Badge und vier Live-KPI (Projekte, aktive Projekte, offene Tickets, überfällige Tickets), linkes Plugin-Menü mit Übersicht, Projekte, Boards, Tickets, Widgets (Gruppe „Projektinhalte“), Audit-Log und Einstellungen (Gruppe „System“).
- Übersicht über `cms_plugin_dashboard()`: Schnellaktionen, System-Check (Tabellen, Schema-Version, öffentliche Projekte, überfällige Tickets, Sitemap/Suche, Audit-Aufbewahrung), letzte Änderungen aus dem Audit-Log; darunter die Tabelle „Fällige Tickets“.
- Die bisherige Sammelseite `admin/page.php` (Projektliste, drei Formulare, Audit-Trail und Board-Vorschau untereinander) ist in eigene Unterseiten mit Header → Toolbar/Filter → Inhalt aufgeteilt (`admin/views/*.php`); doppelte KPI-Karten und der zweite Seitentitel entfallen.
- Admin-CSS in `assets/css/admin.css` getrennt (nur Tokens, Kantenrundung 2px, Selektoren unter `.cms-plugin-main`); `assets/css/style.css` neu auf Tokens aufgebaut, feste Weiß-/Dunkelflächen und 10–16px-Rundungen entfernt. Rückfall-Layout `includes/plugin-workspace.php` + `assets/css/cms-plugin-workspace.css`.

### Kompatibilität 4.0.0

- Plugin-Header mit `Requires CMS: 4.0.0`, `Requires at least: 4.0.0`, `Requires PHP: 8.4.1`; neue `plugin.json`, `update.json` auf 4.0.0.
- Lifecycle-Funktionen `cms_projects_activate()`, `cms_projects_deactivate()`, `cms_projects_uninstall()` (Tabellen/Optionen/DSGVO-Exportdateien entfernen); der Hook-Handler auf `plugin_activated` entfällt.
- Schema-Migration nur noch bei geänderter Schema-Version (Option `cms_projects_schema_version`) statt fünf `CREATE TABLE` bei jedem Request.
- Admin-Callbacks geben nur Inhalt aus; Abhängigkeiten auf nicht existierende Hilfen (`cms_plugin_admin_layout_start`, `cms_plugin_admin_dispatch_page`, `../shared/…`) entfernt. Alle Links, Formularziele und Redirects nutzen `/admin/plugins/cms-projects/<unterseite>`; `?page=` wird weiter akzeptiert. Menüslugs `cms-projects` und `cms-projects-projects` bleiben erhalten, Icon `ti ti-briefcase`.
- Mitgliederbereich mit Capability `member` und `csrf => core`; Projekt-Auswahl per Slug prüft Sichtbarkeit und Status.
- Neu angebunden: `cms_sitemap_entries`, `search_results`, `cms_member_export_data`, `dsgvo_export_data`, `dsgvo_delete_data`, `DataRetentionService::registerRule()` (Regel `cms_projects.audit_logs`), `AdminNoticeService::registerCheck()`; SEO-Metadaten (Titel, Beschreibung, Canonical, `noindex` für 404) der öffentlichen Seiten über `SEOService::setRequestMeta()`.

### Fertiggestellt

- Projekte: Bearbeiten in eigener Maske, Löschen (inkl. Boards, Widgets, Tickets), Suche/Filter nach Status und Sichtbarkeit, Sammelaktionen (Status setzen, löschen), Pagination.
- Boards: Bearbeiten (bisher nur Anlegen möglich), Sammelaktionen aktivieren/deaktivieren/öffentlich/intern/löschen; Tickets aus entfernten Spalten wandern beim Speichern in die erste Spalte; Board ohne Spalten wird abgelehnt.
- Widgets: Bearbeiten (bisher nur Anlegen), Sammelaktionen, Vorschau.
- Tickets: Filter (Projekt oder alle, Board, Priorität, Status, überfällig, Suche), Sammelaktionen, Pagination, Spaltenauswahl im Formular auf das gewählte Board eingeschränkt, Löschen aus dem Bearbeitungsformular.
- CSV-Export (UTF-8 mit BOM, `;`, Formel-Injection-Schutz) für Tickets und Audit-Log; JSON-Export eines Projekts und JSON-Import (max. 1 MB, Endung + MIME-Prüfung, Slug-Konflikt → Suffix, Import als Entwurf, Ergebniszusammenfassung).
- Audit-Log als eigene Seite mit Filter, Pagination und lesbaren Aktionsbezeichnungen; Einstellungsseite (Aufbewahrungsfrist, Sitemap, Suche).
- Öffentliche 404-Seite im Theme-Layout statt nacktem `<h1>`; Status-Labels statt interner Schlüssel.

### Sicherheit

- Post/Redirect/Get mit Flash-Meldung; jede Schreibaktion prüft Admin-Recht und einmaliges CSRF-Token, Downloads ein persistentes Token. Redirects nur über `Security::safeLocalPath()`.
- Shortcode `scope="member"` gab Mitgliederinhalte bisher an anonyme Besucher aus – jetzt nur für angemeldete Personen.
- Öffentliche Projektkarten verraten keine Zählwerte interner Boards/Tickets mehr.
- Projekt-Akzentfarben über nonce-geschützten Style-Block mit Klassen statt `style`-Attributen; JavaScript ohne `innerHTML`, Projektauswahl ohne Inline-Handler (`data-cp-autosubmit`).
- Sammel-Updates nur über Spalten-Whitelist und generierte `IN (…)`-Platzhalter; `LIMIT/OFFSET` gebunden; Audit-Log speichert keine E-Mail-Adresse mehr als Akteursname.

### Performance

- Kein `SELECT *` mehr (11 Stellen); Listen laden keine LONGTEXT-Spalten.
- Entitätszähler in einer `UNION ALL`-Abfrage statt drei, Ticket-Neusortierung in einer `UPDATE … CASE`-Abfrage statt einer pro Ticket, Board-Suche per ID statt Laden aller Boards, Request-Cache für Projekte.
- Neue Indizes (idempotent per `information_schema`): `projects(visibility,status,updated_at)`, `projects(updated_at)`, `tasks(project_id,board_id,sort_order)`, `tasks(is_active,due_date)`, `audit_logs(actor_id)`.
- Board-/Widget-Renderer viermal dupliziert → gemeinsame Klasse `CMS_Projects_View`; CSS/JS nur auf eigenen Seiten (Admin-Callback, eigene öffentliche Routen, Member-Bereich, Seiten mit Shortcode).

## 3.0.4 – 2026-09-30

### Geändert

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „365CMS | Projekte“ (bisher „Projekte“), damit die öffentlichen 365CMS-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.

## 3.0.3 – 2026-09-27

### Behoben

- Fatal im Member-Bereich behoben (member_plugin_section_head übergibt ein Array) – betraf alle Member-Plugin-Seiten.
- Admin-CSS lädt wieder; Admin-Markup nutzt die einheitlichen Bausteine (Seitenkopf, Kennzahlen, Karten).
- Board-Drag-and-Drop in `assets/js/admin-board.js` ausgelagert, Ticket-Löschbestätigung per `data-cms-confirm` (CSP-konform).
- Shortcodes werden über den Core-Filter `content_render` verarbeitet; `[cms_projects]` greift nicht mehr fälschlich `[cms_projects_widget]` ab.
- `admin/page.php` hat einen `ABSPATH`-Guard.

### Geändert

- Admin-Oberfläche an das einheitliche, schlichte 365CMS-Plugin-Admin-Design angeglichen: keine Emoji-/Kürzel-Icons mehr, Icon-Buttons mit Tabler-Icons, Menüname ohne `365CMS | `/`365NET | `-Präfix (der Core sortiert Plugins im Abschnitt „Plugin-Erweiterungen“).
- Kompatibilität mit 365CMS 3.4.00 geprüft (Produktiv-CSP mit Nonces und Trusted Types, PHP 8.4); `requires_cms` auf `3.4.00` angehoben.

## 3.0.2 – 2026-05-31

- Admin-Menüeintrag wird in der Core-Sidebar mit `365CMS | ` vorangestellt, damit 365CMS-Plugins gemeinsam sortiert werden.

## 3.0.1 – 2026-05-18

- PHP-Anforderung auf 8.4 angehoben und Plugin-Version auf `3.0.1` aktualisiert.
- Admin-Renderer prüft jetzt serverseitig `CMS\Auth::instance()->isAdmin()` und redirectet unberechtigte Zugriffe mit HTTP 303.
- Public-/Member-Slugs werden vor Lookups normalisiert und längenbegrenzt.
- Board-/Widget-JSON-Payloads sind auf 64 KB begrenzt, um teure Admin-Requests zu vermeiden.
- Widget-Link-URLs blockieren jetzt Kontrollzeichen, Credentials, lokale Hosts sowie private/reservierte IPs.
- Public-/Member-Templates nutzen semantischere Container und Statusrollen für Empty States.
- CSS optisch beruhigt: weniger Glassmorphism/Gradient, klarere Token-Anbindung und Reduced-Motion-Regel.

## 3.0.0 – 2026-05-17

- Kompatibilität auf 365CMS 3.0.0 angehoben.
- Public- und Member-Templates um direkte `ABSPATH`-Guards ergänzt.
- Projekt-Akzentfarben vor Ausgabe strikt als Hex-Farben validiert.
- Dynamische Akzentfarbe auf CSS-Custom-Property `--cp-project-accent` umgestellt, damit die visuelle Darstellung zentral über `style.css` läuft.
- `update.json` für den 365CMS-3.x-Updatefluss ergänzt.

## 1.0.0

- Erste Version mit Projekt-Dashboards, Boards, Widgets, Member- und Public-Ausgabe.

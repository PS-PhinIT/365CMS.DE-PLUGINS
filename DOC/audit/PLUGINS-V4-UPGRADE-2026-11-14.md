# Plugin-Upgrade auf 365CMS 4.0.0 – Audit- & Fortschrittsprotokoll

> **Datum:** 2026-11-14 · **Bezug:** 365CMS Core 4.0.0 (Schema v25, `min_php` 8.4.1) · **Repository:** `365CMS.DE-PLUGINS`
> **Layout-Vertrag:** `CMS/assets/css/admin-plugins.css` + `CMS/admin/partials/plugin-workspace.php` (Core 4.0.0, `CMS_PLUGIN_WORKSPACE_VERSION` 1.0.0)

## 0. Vorbemerkungen

- In diesem Repository liegen die Plugins direkt im Wurzelverzeichnis (`<slug>/`); installiert werden sie nach `CMS/plugins/<slug>/`. Die im Core integrierten Plugins (`cms-contact`, `cms-downloads`, `cms-feed`, `cms-newsletter`, `cms-knowledgebase`) sind hier bereits entfernt und gehören nicht zum Umfang; `index.json` verwies noch auf sie und auf das entfernte `cms-forum` – bereinigt, enthält jetzt nur die vier vorhandenen Plugins.
- **Core-Dateien:** Der Core 4.0.0 liefert den einheitlichen Plugin-Layout-Vertrag bereits vollständig aus: `admin-plugins.css` (Split-Header, KPI-Karten, `240px minmax(0, 1fr)`-Workspace, Umbruch unter `992px`, `--cms-*`/`--tblr-*`-Tokens, max. 2px Radius, Dark-Mode) und `admin/partials/plugin-workspace.php` (`cms_plugin_workspace_open()`, `cms_plugin_workspace_close()`, `cms_plugin_dashboard()`). Der `AdminRouter` lädt beides automatisch vor jedem Plugin-Callback. **Eine Core-Änderung war daher nicht nötig**; alle Plugins nutzen ausschließlich diese Core-Helfer.
- Prüfgrundlage: `DOC/plugins/PLUGIN-DEVELOPMENT.md` (Core, Stand 2026-11-14), `AdminRouter`, `Security` (CSP `script-src 'self' 'nonce-…'`, `style-src` ohne `unsafe-inline`), `PluginDashboardRegistry::checkCapability()`, `Http\Client`, `DataRetentionService`, `AdminNoticeService`.
- Syntaxprüfung: `php -l` auf allen PHP-Dateien (lokal PHP 8.3 CLI; die Plugins nutzen keine 8.4-only-Syntax).

## 1. Inventar

| Plugin | Ordner | Familie / Menütitel | Version alt → neu | PHP-Zeilen | JS/CSS-Zeilen | Eigene Tabellen | Status |
|---|---|---|---|---:|---:|---|---|
| CMS Beratung | `cms-beratung/` | `365CMS \| Beratung` | 2.9.794 → 3.0.0 | 5.030 | 1.771 / 4.852 | `beratung_landingpages`, `beratung_design_presets`, `beratung_settings`, `beratung_form_submissions` | [x] |
| CMS Booking | `cms-booking/` | `365CMS \| Buchungen` | 3.0.8 → 4.0.0 | 6.484 | 430 / 773 | `bookings`, `booking_providers`, `booking_services`, `booking_availability`, `booking_meta`, `booking_settings` | [x] |
| CMS Projects | `cms-projects/` | `365CMS \| Projekte` | 3.0.4 → 4.0.0 → 4.0.1 | 3.788 | 171 / 626 | `projects_projects`, `projects_boards`, `projects_widgets`, `projects_tasks`, `projects_audit_logs` | [x] |
| CMS Promos | `cms-promos/` | `365CMS \| Promos` | 3.0.4 → 4.0.0 → 4.0.1 | 2.456 | 40 / 531 | `promos`, `promo_placements`, `promo_settings` | [x] |

Je Plugin liegt die aktuelle technische Dokumentation unter `<plugin>/DOC/`: `README.md` (Admin-Menü, KPIs, Routen, Core-Integrationen, Lebenszyklus, Sicherheit, Assets), `DATABASE.md`, `HOOKS.md`, `API.md`; die Versionshistorie bleibt in `<plugin>/CHANGELOG.md`.

Abgehakt (`[x]`) wird ein Plugin erst, wenn alle fünf Phasen umgesetzt und alle PHP-Dateien mit `php -l` fehlerfrei geprüft sind.

## 2. Automatischer Erstbefund (alle Plugins)

| Prüfpunkt | Ergebnis |
|---|---|
| Entfernte 3.5.x-APIs (`buildSidebarPluginIcon()`, `cmsNormalizeSidebarActivePage()`, `isGroupActive()`, `isSlugActive()`, `sidebarChildIcon()`, `sidebarNavTitleHtml()`, `sidebarTopLevelIcon()`, `Database::invalidateFeedCache()`) | keine Treffer |
| Veraltete PHP-Funktionen | `curl_close()` + direktes cURL/`file_get_contents()` in `cms-promos` (Webhook) |
| Inline-Event-Handler (`onclick` …) | keine Treffer |
| Inline-`<script>` ohne Nonce | nur `type="application/json"`/`ld+json`-Datenblöcke (`cms-beratung`) – JSON ohne `JSON_HEX_TAG` |
| `style="…"`-Attribute (von der CSP blockiert, nur per Core-Rewriter gerettet) | `cms-booking` Admin-Views (51), `cms-beratung` Template (14), `cms-projects` Templates (4) |
| `innerHTML`/`insertAdjacentHTML` | 17 Stellen in `cms-beratung/assets/js/admin-builder.js` |
| `SELECT *` | 30 Stellen (alle Plugins) |
| Eigenes Admin-Layout statt Layout-Vertrag | alle vier Plugins (eigene Sidebar/Tabs, Stat-Karten im Inhalt) |
| Lifecycle-Funktionen `<slug>_activate/_deactivate/_uninstall` | fehlen in allen vier Plugins |
| `Requires CMS`/`Requires PHP`-Header | fehlen in allen vier Plugins |

## 3. Befunde & Umsetzung je Plugin

Je Plugin: Befund vor der Umsetzung, umgesetzte Änderungen je Phase und Prüfung. Fehler, die erst der Integrationstest (Abschnitt 5) gefunden hat, stehen dort.

### 3.1 CMS Promos (`cms-promos`, 3.0.4 → 4.0.0) – [x] abgeschlossen

**Befunde**

| # | Bereich | Befund | Schwere |
|---|---|---|---|
| P-1 | Design | Eigenes `.pr-admin-layout` mit Sidebar-Box und Stat-Karten mit Kürzeln („ALL“, „ACT“) im Inhalt; kein Split-Header, keine KPI-Karten, kein `aria-current` | mittel |
| P-2 | 4.0.0 | `renderAdminLayoutStart()`/`cms_plugin_admin_layout_start()` im Callback (seit 4.0.0 rendert der Router die Shell), Suche nach nicht mehr existierendem `shared/admin/plugin-admin-contract.php`, Menü-Icon als Text „PR“ | mittel |
| P-3 | 4.0.0 | Keine Lebenszyklus-Funktionen; `plugin_uninstalled`-Handler leer; keine `Requires CMS/PHP`-Header | mittel |
| P-4 | Performance | `maybe_install()` führte bei **jedem Request** `CREATE TABLE` ×3, 13 `INFORMATION_SCHEMA`-Abfragen und Seed-Abfragen aus | hoch |
| P-5 | Performance | `session_start()` bei jedem `cms_init` für alle Besucher (verhindert Page-Caching, erzeugt Sessions für Bots) | hoch |
| P-6 | Performance | `SELECT *` (4×), korrelierte `COUNT(*)`-Unterabfragen, N+1-Abfragen beim Speichern der Einstellungen, fehlende Indizes auf `status/start_at/end_at`, `placement_id/status`, Hook-Spalten | mittel |
| P-7 | Funktion | Öffentliches CSS nur auf `/promos` – Promos in Theme-Hooks (Startseite, Footer) blieben unformatiert | mittel |
| P-8 | Sicherheit | Webhook per rohem cURL/`file_get_contents()` (DNS-Rebinding/SSRF nur per Hostname-Check), `curl_close()` veraltet | hoch |
| P-9 | Sicherheit | Klick-Route leitete auch Entwürfe/abgelaufene Promos weiter; keine `noindex`/`no-store`-Header | niedrig |
| P-10 | Sicherheit/UX | Löschen ohne Bestätigung; Statuswerte unübersetzt („draft“) | niedrig |
| P-11 | Unfertig | Kein Export, kein Zurücksetzen von Statistik/Einstellungen, kein Duplizieren, keine Filter/Suche; UTM-Builder überschrieb die gespeicherte Ziel-URL (UTM wurde beim Klick doppelt angehängt) | mittel |
| P-12 | Integration | Keine Sitemap-Einträge, keine SEO-Metadaten für `/promos`, kein Systemhinweis | niedrig |
| P-13 | Code | Hartkodierte Farben in `promos-admin.css` (38 Hex/RGBA-Werte) | niedrig |

**Umsetzung (Phasen 1–5)**

1. **Design:** `cms_plugin_workspace_open()` mit Titel `365CMS | Promos`, Versions-/Live-Badge, 4 KPI-Karten (Promos gesamt, aktuell live, Impressions, Klickrate); linkes Menü Übersicht/Promos/Platzierungen/Einstellungen mit Zähler-Badges (Warnton bei abgelaufenen Promos); Übersicht über `cms_plugin_dashboard()` (Schnellaktionen, System-Check: Schema, Hook-Platzierungen, abgelaufene Promos, Webhook, Gesamtleistung; zuletzt bearbeitet). Unterseiten: Header → Filter-Toolbar → Tabelle bzw. Formular. `promos-admin.css` neu nur mit Tokens.
2. **4.0.0:** Router-Shell statt eigener Layout-Aufrufe, Tabler-Icon `ti-speakerphone`, `current_user_can('manage_options')`, Redirects über `Security::safeLocalPath()`, `$_GET['page']`-Dispatcher kompatibel zu `/admin/plugins/promos-dashboard/<unterseite>`; Lebenszyklus-Callbacks; `cms_sitemap_entries`, `SEOService::setRequestMeta()`, `AdminNoticeService::registerCheck('cms_promos_expired')`. `ContentTypeRegistry`/`search_results`/`DataRetentionService`: nicht sinnvoll (Promos haben keine Detailseiten, speichern keine personenbezogenen Daten – nur Zähler).
3. **Ausbau:** Filter (Suche, Status inkl. live/abgelaufen, Platzierung), Duplizieren, Statistik zurücksetzen (je Promo/alle), Einstellungen zurücksetzen, CSV/JSON-Export, UTM-Vorschau mit Kopieren, Validierung Webhook-Pflichtfeld, Wertebereiche für Priorität/Kapazität.
4. **Sicherheit:** Webhook über `CMS\Http\Client` (SSRF-Schutz, Timeout, Größenlimit); CSRF auf allen POSTs, Export mit persistentem Token; CSV-Formel-Injektion entschärft; `data-cms-confirm` statt ungesicherter Löschbuttons; Klick-Route nur für live Promos; keine Inline-Skripte/Styles, JS-Ausgabe nur per `textContent`.
5. **Speed:** Migration nur bei geänderter `cms_promos_db_version` (Autoload-Option über `OptionStore`); neue Indizes; Upsert für Einstellungen; explizite Spaltenlisten; Aggregat-Join statt korrelierter Unterabfrage; öffentliche Session entfernt; CSS nur bei Bedarf, Admin-JS nur auf der Promo-Seite mit `defer`.

**Dokumentation:** `cms-promos/DOC/README.md`, `DATABASE.md`, `HOOKS.md`, `API.md` (neu, Aufbau gemäß `.github/copilot-instructions.md` §11), `README.md`, `CHANGELOG.md`, `update.json`.

**Prüfung:** `php -l` für alle 10 PHP-Dateien fehlerfrei; Render-Smoke-Test aller 7 Admin-Ansichten mit Core-Partial `plugin-workspace.php` ohne Notices, ohne `style=`/`on*=`-Attribute.

### 3.2 CMS Projects (`cms-projects`, 3.0.4 → 4.0.0) – [x] abgeschlossen

**Befunde**

| # | Bereich | Befund | Schwere |
|---|---|---|---|
| PJ-1 | Design | Eine einzige Sammelseite `admin/page.php` (583 Zeilen) für „Übersicht“ **und** „Projekte“ – identischer Inhalt unter zwei Menüpunkten; Stat-Karten im Inhalt, keine KPI-Kopfzeile, kein Plugin-Menü | hoch |
| PJ-2 | 4.0.0 | Menü-Capability `admin` (kein gültiger Capability-Name), Menü-Icon „PRJ“, `cms_plugin_admin_layout_start()`/`shared/admin/plugin-admin-contract.php` (existieren im Core 4.0.0 nicht), CSS über `admin_head` mit Pfadraten | mittel |
| PJ-3 | 4.0.0 | Member-Registrierung mit `capability => null`, Label „PROJECTS“, Icon „PRJ“; Detailauswahl ließ archivierte Projekte zu | mittel |
| PJ-4 | Unfertig | Boards und Widgets konnten nur **angelegt**, nicht bearbeitet oder gelöscht werden; Projekte nicht löschbar; Audit-Log nur als ungefilterte Tabelle ohne Export; kein Export | hoch |
| PJ-5 | Unfertig | POST ohne Redirect (Doppel-Absendung bei Reload), Formularwerte nach Fehlern verloren | mittel |
| PJ-6 | Performance | `ensureTables()` (5× `CREATE TABLE IF NOT EXISTS`) bei **jedem** Request | hoch |
| PJ-7 | Performance | `SELECT *` in 11 Abfragen | niedrig |
| PJ-8 | Datenschutz | Audit-Protokoll mit Benutzernamen wächst unbegrenzt, keine Löschfrist | mittel |
| PJ-9 | CSP | Projekt-Akzentfarbe als `style="--cp-project-accent:…"` in Templates und Shortcode (von `style-src` blockiert) | mittel |
| PJ-10 | Sicherheit | Shortcode `scope="member"` gab Member-Inhalte auch an anonyme Besucher aus; Widget-Shortcode ignorierte den Projektstatus | mittel |
| PJ-11 | Integration | Keine Sitemap-/Suchintegration für öffentliche Projekte, kein Systemhinweis, keine Lebenszyklus-Callbacks | niedrig |
| PJ-12 | Code | 38 hartkodierte Farbwerte in `style.css`, Admin-Overrides mit 4px-Radius | niedrig |

**Umsetzung (Phasen 1–5)**

1. **Design:** `admin/page.php` aufgeteilt in `admin/views/` (Liste, Projektkopf, Reiter Stammdaten/Boards/Widgets/Tickets, Audit, Einstellungen, Helfer). Kopfzeile `365CMS | Projekte` mit 4 KPIs (Projekte, aktiv, Tickets, überfällig); linkes Menü Übersicht · Projekte · Audit-Log · Einstellungen, bei geöffnetem Projekt die Reiter als Menügruppe „Projekt: *Name*“ mit Zähler-Badges; Übersicht über `cms_plugin_dashboard()`. Neues `admin.css` mappt die `--cp-*`-Variablen auf Core-Tokens (Light/Dark, 2px).
2. **4.0.0:** `manage_options`, Tabler-Icons, Router-Shell, `current_user_can()`, `safeLocalPath()`; Member-Capability `member`; Lebenszyklus-Callbacks; `cms_sitemap_entries`, `search_results`, `DataRetentionService::registerRule('cms_projects_audit_logs')`, `AdminNoticeService::registerCheck('cms_projects_overdue')`.
3. **Ausbau:** Projekte löschen (transaktional mit Boards/Widgets/Tickets), Boards/Widgets bearbeiten & löschen (mit Projektzugehörigkeitsprüfung und Audit-Einträgen), Projektliste mit Filtern, Projekt-JSON-Export, Audit-Log mit Filtern und CSV-Export, Einstellungen (Löschfrist, Bereinigen, Tabellenreparatur), PRG-Redirects mit Flash-Meldungen.
4. **Sicherheit:** CSRF auf allen POSTs, persistentes Token für Exporte, CSV-Formel-Schutz, `JSON_HEX_TAG`; Akzentfarben über nonce-geschützte `<style>`-Regeln (`accentSwatch()`); Member-Shortcodes nur für angemeldete Besucher; Statusprüfung im Widget-Shortcode und Member-Detail.
5. **Speed:** Schema-Prüfung nur bei geänderter `cms_projects_db_version`; explizite Spaltenlisten; Audit-Bereinigung in Blöcken über den Core-Cron; CSS nur auf Projektseiten bzw. einmalig mit Shortcode-Ausgabe; Board-JS nur im Ticket-Reiter.

**Dokumentation:** `cms-projects/DOC/README.md`, `DATABASE.md`, `HOOKS.md`, `API.md` (neu, Aufbau gemäß `.github/copilot-instructions.md` §11), `README.md`, `CHANGELOG.md`, `update.json`.

**Prüfung:** `php -l` für alle PHP-Dateien fehlerfrei; Render-Smoke-Test aller 9 Admin-Ansichten (inkl. aller Projekt-Reiter) mit Core-Partial ohne Notices, ohne `style=`-Attribute, genau ein `aria-current` je Seite.

### 3.3 CMS Booking (`cms-booking`, 3.0.8 → 4.0.0) – [x] abgeschlossen

**Befunde**

| # | Bereich | Befund | Schwere |
|---|---|---|---|
| B-1 | Funktion | `CMS_Booking_Notifications` wurde **nie instanziiert** – keine einzige Buchungs-, Status- oder Admin-Mail wurde versendet | hoch |
| B-2 | Funktion | Nach erfolgreicher Buchung Aufruf der nicht existierenden Methode `send_booking_created()` → Exception → Kunde sieht „Speichern fehlgeschlagen“, Buchung ist trotzdem angelegt | hoch |
| B-3 | Unfertig | Keine Admin-Oberfläche für Verfügbarkeiten (Wochenplan/Abwesenheiten); Anbieter nur über Quell-Plugins anlegbar → Plugin ohne weitere Plugins nicht nutzbar | hoch |
| B-4 | Unfertig | Einstellung „Erinnerungen versenden“ und Spalte `reminder_sent` ohne Versandlogik; Einstellung „Primärfarbe“ ohne Wirkung; kein Export, kein Buchungsdetail, keine interne Notiz | mittel |
| B-5 | Design | Eigene Shell mit Fallback auf Core-Header/Sidebar, Traits mit Inline-POST ohne Redirect, 51 `style`-Attribute, hartkodierte Farben, Service-Formular als JS-Modal | mittel |
| B-6 | 4.0.0 | `shared/admin/plugin-admin-contract.php`, `cms_plugin_admin_layout_start()`, `plugin_activated`/`plugin_uninstalled`-Listener statt Lebenszyklus-Funktionen; Member-Bereich mit Emoji-Icon und ohne Capability | mittel |
| B-7 | 4.0.0 | Versand per `mail()` statt Core-`MailService` (kein SMTP/Graph, kein Mail-Log) | mittel |
| B-8 | Sicherheit | Token-Secret `CMS_SECRET_KEY` existiert in 4.0.0 nicht → Fallback aus `ABSPATH`/`SITE_URL` (Unvorhersagbarkeit hing allein an der iCal-UID) | mittel |
| B-9 | Sicherheit | `LIKE`-Suche ohne Maskierung von `%`/`_`; `SELECT *` auf Quell-Plugin-Tabellen | niedrig |
| B-10 | Datenschutz | Kundendaten (Name, E-Mail, Telefon, Notizen) ohne Löschfrist | mittel |
| B-11 | Bug | Member-Liste zeigte die Leistung nie (`service_name` statt `service_title`); Status unübersetzt | niedrig |
| B-12 | Performance | 13 `SELECT *`, Einstellungen mehrfach je Request gelesen, kein Index für Erinnerungs-/Kalenderabfragen | niedrig |

**Umsetzung (Phasen 1–5)**

1. **Design:** Neue `CMS_Booking_Admin_Pages` auf dem Layout-Vertrag: Kopfzeile `365CMS | Buchungen` mit 4 KPIs (offene Anfragen, heute, 7 Tage, aktive Anbieter); Menü Übersicht · Buchungen · Anbieter · **Verfügbarkeit** · Leistungen · Einstellungen mit Badge für offene Anfragen; Übersicht mit Schnellaktionen, 7 System-Checks und anstehenden Terminen; alle Unterseiten Header → Toolbar → Inhalt. Traits, `dashboard.php` und `booking-admin.js` entfernt; `booking-admin.css` neu nur mit Tokens.
2. **4.0.0:** `manage_options`, Tabler-Icons, Router-Shell (Slugs unverändert, AdminRouter-Fallback `render_*()` bleibt), `safeLocalPath()`; Lebenszyklus-Callbacks; Member-Capability `member`; `MailService`; `cms_cron_hourly`; `DataRetentionService::registerRule('cms_booking_customers')` (anonymisieren); `AdminNoticeService::registerCheck('cms_booking_pending')`; `cms_sitemap_entries`.
3. **Ausbau:** Anbieter manuell anlegen/bearbeiten, Wochenplan mit zwei Zeitfenstern je Tag, Abwesenheiten über Zeiträume, Buchungsdetail mit Meta und interner Notiz, kontextabhängige Statusaktionen, CSV-Export, Erinnerungs-Cron inkl. Mailvorlage, Primärfarbe, Einstellungen zurücksetzen, Wartungsaktionen; B-1, B-2 und B-11 behoben.
4. **Sicherheit:** CSRF auf allen POSTs, persistentes Export-Token, CSV-Formel-Schutz, Aktions-Whitelist, Zeit-/Datumsvalidierung, `https`-Pflicht für Meeting-Links; Zugangs-Token mit `SECURE_AUTH_KEY`/`AUTH_KEY` (alte Links bleiben gültig); `LIKE`-Maskierung; Quelltabellen nur über validierte Spalten.
5. **Speed:** Explizite Spaltenlisten, Einstellungen einmal je Request (`read_settings()`), `booking_db_version` über `OptionStore`, neue Indizes `idx_reminder` und `idx_provider_date`, Anonymisierung in Blöcken.

**Dokumentation:** `cms-booking/DOC/README.md`, `DATABASE.md`, `HOOKS.md`, `API.md` (neu), `README.md`, `CHANGELOG.md`, `update.json`.

**Prüfung:** `php -l` für alle PHP-Dateien fehlerfrei; Render-Smoke-Test aller 11 Admin-Ansichten (inkl. Detail-, Formular- und Verfügbarkeitsansicht) mit Core-Partial ohne Notices, ohne `style=`-/`on*=`-Attribute, genau ein `aria-current`.

### 3.4 CMS Beratung (`cms-beratung`, 2.9.794 → 3.0.0) – [x] abgeschlossen

**Befunde**

| # | Bereich | Befund | Schwere |
|---|---|---|---|
| BR-1 | Sicherheit | **XSS im Builder:** Bereichstitel (`internal_name`/`title`) wurden mit `text()` statt `esc()` in ein `innerHTML`-Template eingesetzt – ausnutzbar z. B. über importiertes Landingpage-JSON | hoch |
| BR-2 | CSP | 17 `innerHTML`-Zuweisungen in `admin-builder.js`, Inline-`style` mit hartkodiertem Verlauf in der CTA-Vorschau | mittel |
| BR-3 | Performance | `register_routes()` lud bei **jedem** Request alle Landingpages per `SELECT *` (inkl. aller JSON-Spalten), um je Seite drei GET- und drei POST-Routen zu registrieren | hoch |
| BR-4 | 4.0.0 | Admin-Vorschau gab ein vollständiges `<!doctype html>`-Dokument im Plugin-Callback aus → AdminRouter bettete es in die Admin-Shell ein (verschachteltes HTML, Admin-CSS auf der Vorschau) | mittel |
| BR-5 | Unfertig | Formular-Anfragen nur als Tabelle: kein Status, keine Notiz, kein Löschen, kein Export, obwohl `update_submission()`/`delete_submissions()` existierten | hoch |
| BR-6 | Unfertig/Datenschutz | Einstellungen `auto_delete_submissions_enabled`/`retention_days` vorhanden, `purge_old_submissions()` wurde aber nie aufgerufen; Datenschutz-Schalter (IP, User-Agent, Löschfrist, Benachrichtigung) im Formular nicht bearbeitbar | hoch |
| BR-7 | Unfertig | Design-Presets: „Eigene Presets sind im Datenmodell vorbereitet“ – keine Anlage/Bearbeitung; Export nur als Textarea ohne Download | mittel |
| BR-8 | Design | Eigene Layout-Shell, keine KPI-Kopfzeile, kein Dashboard, Stat-Karten im Inhalt; `admin.css` mit über 100 hartkodierten Farben und 10–24px-Radien (kein Dark-Mode) | mittel |
| BR-9 | 4.0.0 | `shared/admin/plugin-admin-contract.php`, `cms_plugin_admin_*`-Funktionen, `plugin_activated` statt Lebenszyklus-Funktionen, Menü-Icon leer, `head`-Hook für Admin-Assets | mittel |
| BR-10 | Sicherheit | Eingebettetes JSON ohne `JSON_HEX_TAG` (nur `</script`-Ersetzung); `@mail()`-Fallback | niedrig |
| BR-11 | Integration | Keine Sitemap-/Suchintegration für veröffentlichte Landingpages, kein Systemhinweis für neue Anfragen | niedrig |

**Umsetzung (Phasen 1–5)**

1. **Design:** `render_with_layout()` auf `cms_plugin_workspace_open()` mit Titel `365CMS | Beratung`, 4 KPIs (Landingpages, veröffentlicht, neue/offene Anfragen); linkes Menü Übersicht · Landingpages · Formular-Anfragen · M365 FAQs · Design-Presets · Globale Einstellungen · Import & Export · Hilfe mit Badges; neues Dashboard über `cms_plugin_dashboard()`. Admin-CSS (3 Dateien) mechanisch auf Core-Tokens umgestellt, neue Bausteine ergänzt.
2. **4.0.0:** Shared-Contract-Abhängigkeiten entfernt, Router-Shell, Tabler-Icon `ti-presentation`, `safeLocalPath()`, `OptionStore`; Lebenszyklus-Callbacks; `cms_sitemap_entries`, `search_results`, `DataRetentionService::registerRule('cms_beratung_submissions')`, `AdminNoticeService::registerCheck('cms_beratung_new_submissions')`; Vorschau-Route `/beratung-vorschau/{id}` über die Theme-Pipeline.
3. **Ausbau:** Formular-Anfragen (Filter, Detail, Status, Notiz, Löschen, CSV, Bereinigen), Design-Presets (CRUD, Übernehmen), Datenschutz-Einstellungen, JSON-Download, Landingpage-Filter, Flash-Meldungen/PRG für alle Aktionen.
4. **Sicherheit:** BR-1 behoben; eine HTML-Senke `setHtml()` (DOMPurify `RETURN_TRUSTED_TYPE`, Fallback Core-Default-Policy), `replaceChildren()` statt `innerHTML = ''`; `JSON_HEX_TAG`; Export-Token und CSV-Formel-Schutz; Mail nur über `cms_mail()`.
5. **Speed:** Parametrisierte Routen + `resolve_public_slug()` (eine Abfrage, `JSON_EXTRACT` für Standalone-Slugs); explizite Spaltenlisten; Aggregatabfragen für Kennzahlen; Builder-JS nur im Editor.

**Bewusst nicht geändert:** Das öffentliche Template `templates/landingpage.php` gibt Per-Page-Designwerte als `style="--var:…"` aus (14 Stellen). Diese werden vom Core-`InlineStyleRewriter` CSP-konform in nonce-geschützte Klassen überführt; die Werte sind farb-/zahlvalidiert. Eine Umstellung auf Klassen-Generierung im Plugin ist als Folgeaufgabe sinnvoll, war aber für die 4.0.0-Kompatibilität nicht erforderlich.

**Dokumentation:** `cms-beratung/DOC/README.md`, `DATABASE.md`, `HOOKS.md`, `API.md` (neu), `DOC/GLOBAL-DESIGN-MODULE.md` (aktualisiert: `sectionContentSpacing`, Presets, CSP-Hinweis), `README.md` und `CHANGELOG.md` (neu), `update.json`.

**Prüfung:** `php -l` für alle PHP-Dateien fehlerfrei, `node --check` für `admin-builder.js`; Render-Smoke-Test aller 14 Admin-Ansichten (inkl. Editor, Preset-Bearbeitung, Anfrage-Detail, Export-Anzeige) mit Core-Partial ohne Notices, ohne `style=`-Attribute, genau ein `aria-current`, Builder-Skripte nur im Editor.

## 4. Abschluss

| Plugin | Phasen 1–5 | `php -l` | Smoke-Test Admin | Integrationstest (Abschn. 5) | DOC |
|---|---|---|---|---|---|
| cms-promos 4.0.1 | [x] | [x] | 7 Ansichten | [x] | [x] |
| cms-projects 4.0.1 | [x] | [x] | 9 Ansichten | [x] | [x] |
| cms-booking 4.0.0 | [x] | [x] | 11 Ansichten | [x] | [x] |
| cms-beratung 3.0.0 | [x] | [x] | 14 Ansichten | [x] | [x] |

Den Lauf gegen eine echte Installation, der bei der ersten Fassung dieses Protokolls noch fehlte, dokumentiert Abschnitt 5.

## 5. Integrationstest gegen eine echte 365CMS-4.0.0-Installation (2026-11-15)

### 5.1 Umgebung

- Core `365CMS.DE` 4.0.0 (unverändert bis auf testbedingte Anpassungen, siehe unten), frische Installation über den Installer, **MariaDB 10.11**, PHP-Built-in-Server mit Core-Router, Admin-Login mit Produktiv-CSP (`script-src 'self' 'nonce-…'`, `style-src` ohne `unsafe-inline`, Trusted Types).
- Alle vier Plugins aus diesem Repository installiert und über die Plugin-Verwaltung aktiviert (Tabellen, Indizes und Versionsoptionen über die Lifecycle-Callbacks).
- **Einschränkungen der Testumgebung:** In der Sandbox stand nur **PHP 8.3** zur Verfügung; für den Test wurden in einer *Kopie* des Cores `Version::MIN_PHP`, die PHP-Prüfung in `config.php` und `validateBundledPhpPlatform()` gelockert. Die Plugins nutzen keine 8.4-only-Syntax (`php -l`). Es gab keinen Mailserver: `mail()` schlug erwartungsgemäß fehl; geprüft wurde, dass die Plugins Versand anstoßen, Fehler protokollieren und den Ablauf nicht abbrechen.

### 5.2 Abgedeckte Abläufe (HTTP-Client mit Session, echte Formulare inkl. CSRF-Token)

| Plugin | Geprüft |
|---|---|
| Alle | Crawl aller 32 Plugin-Admin-Seiten (Status 200, kein PHP-Notice, genau ein `aria-current`, kein Fallback „Plugin-Adminseite nicht gefunden“); Formular-POST mit falschem CSRF-Token wird abgewiesen |
| cms-promos | Platzierung/Promo anlegen, Validierung (Titel, URL, Zeitraum), Duplizieren, Filter, öffentliches Archiv, Theme-Hook-Fläche auf der Startseite, Klick-Redirect mit UTM, Entwurf → 404, CSV/JSON-Export, Export mit falschem Token, Statistik zurücksetzen, Einstellungen speichern/validieren/zurücksetzen, Löschen |
| cms-projects | Projekt-CRUD, Boards/Widgets/Tickets, Ticket verschieben, Überfällig-KPI, JSON/CSV-Export, öffentliche Projektseiten mit Akzentfarbe, Suche, Einstellungen, Tabellenreparatur, kaskadierendes Löschen |
| cms-booking | Anbieter anlegen/bearbeiten, Wochenplan inkl. Validierung, Abwesenheiten, Leistung (https-Pflicht für Meeting-Links), Slot-API (gesperrter Tag leer, Überschneidungen konsistent mit dem Absenden), **öffentliche Buchung**, Bestätigungsseite mit Token (falsches Token → 403), iCal, Admin-Bestätigung, Notiz, Erinnerungen, CSV, Primärfarbe, Abschließen, Anonymisieren, Member-Seite, Löschen |
| cms-beratung | Landingpage aus Vorlage speichern, Entwurf → 404 / Vorschau für Admins, Veröffentlichen, `/en/…`, Filter, **Kontaktformular** (Erfolg, Validierung, falsches Token → 403), Anfragen: Detail, Status/Notiz, Filter, CSV (Formel-Schutz `'=`), Export mit falschem Token, Löschfrist; Presets anlegen/bearbeiten/übernehmen/löschen (System-Preset geschützt), Einstellungen, FAQs, JSON-Export/-Import (inkl. defektem JSON), Sitemap und Suche, Duplizieren/Deaktivieren/Löschen |

### 5.3 Browser-Prüfung (Chromium/Playwright)

- 35 Seiten (alle Plugin-Admin-Seiten, öffentliche Archive, Landingpage) mit vollständig geladenen Core-Assets: **0 Konsolenfehler, 0 JavaScript-Fehler, 0 CSP-Verletzungen** (`securitypolicyviolation`).
- Beratung-Builder: 13 Bereiche und 29 Hero-Felder werden aus dem JSON aufgebaut; Speichern über die JS-Oberfläche serialisiert Bereiche, Hero, Kontakt und Design korrekt. Kontaktformular im Browser abgeschickt.
- Mobil (390 px): kein horizontales Scrollen in den vier Plugin-Admins; der Workspace stapelt unter 992 px.

### 5.4 Gefundene und behobene Fehler

| Plugin | Fehler | Ursache | Behebung |
|---|---|---|---|
| cms-beratung | Plugin lud weder Routen noch Admin-Seiten | `if (class_exists('CMS_Beratung', false)) return;` vor einer unbedingten Klassendeklaration – PHP registriert die Klasse beim Kompilieren, der Guard war immer wahr | Klasse in `if (!class_exists(…)) { … }` |
| cms-booking | Öffentliches Buchungsformular → 403 | Core-Router verlangt für öffentliche POSTs `form_guard`; das Formular sendet sein eigenes Token | Filter `cms_public_form_persistent_token` für `/booking/<anbieter>/<leistung>` |
| cms-booking | Buchung eingeloggter Mitglieder schlug fehl | `CMS\Auth::getUser()` existiert in 4.0.0 nicht | `Auth::isLoggedIn()` + `Auth::instance()->currentUser()`; Fehler werden mit Datei/Zeile protokolliert |
| cms-promos, cms-beratung | Leerer Slug ergab immer `promo` bzw. `beratung` (+ Zähler) | `??` greift bei leerem String nicht bzw. Sanitizer setzte den Fallback vor der Titel-Ableitung | Slug aus Titel/Name; Umlaute transliteriert (auch cms-projects) |
| alle vier | Neue/geänderte öffentliche Inhalte fehlten in `plugins.xml` bis zum Tages-Cron | Core speichert das Sitemap-Bundle als Datei und verwirft es nur bei Seiten-/Beitragsänderungen | Nach relevanten Admin-Änderungen `SEOService::getInstance()->invalidateSavedSitemaps()` |
| cms-beratung | Rechtes Inhaltsverzeichnis bei 1100–1800 px fast vollständig abgeschnitten (vorbestehend) | ältere `.is-toc-expanded`-Regel überstimmte die 280-px-Spalte | Spezifität angeglichen, Spalte inkl. Randabstand |
| cms-beratung | Import ohne Slug meldete „Slug-Konflikt“; Editorfelder vertikal gestreckt | Vergleich mit leerem Slug; Grid-Stretch | Vergleich nur bei vorhandenem Slug; `align-content:start` |
| cms-booking | Validierungsfehler im PHP-Fehlerprotokoll | Logging aller Exceptions | `InvalidArgumentException` wird nicht mehr geloggt |

Ergebnis: Nach den Korrekturen laufen alle Abläufe aus 5.2 und 5.3 ohne Fehler; das PHP-Fehlerprotokoll enthält nur die erwarteten Mail-Fehler der Sandbox und die Warnungen der absichtlich falschen CSRF-Token.

### 5.5 Weiterhin offen (nicht in der Sandbox prüfbar)

- Lauf unter PHP 8.4.1 ohne Test-Anpassungen am Core.
- Upgrade einer bestehenden 3.x-Datenbank (getestet wurde eine frische Installation; die Migrationen sind idempotent und nur bei geänderter Schema-Version aktiv).
- Echter Mailversand (Buchungsbestätigung, Erinnerungen, Beratung-Benachrichtigung) und Ausführung von `cms_cron_hourly` über den System-Cron (die Erinnerungslogik wurde über die manuelle Admin-Aktion ausgelöst).

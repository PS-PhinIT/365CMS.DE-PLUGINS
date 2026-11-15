# 365CMS-Plugins – Upgrade auf 365CMS v4.0.0 (Inventar & Audit)

Stand: 14./15.11.2026 · Referenz: 365CMS-Core 4.0.0 (`CMS/admin/partials/plugin-workspace.php`, `CMS/core/Routing/AdminRouter.php`, `DOC/plugins/PLUGIN-DEVELOPMENT.md`) · Anforderungen aller Plugins: **365CMS ≥ 4.0.0, PHP ≥ 8.4.1**

Ein Plugin wird erst abgehakt (`[x]`), wenn alle fünf Katalogpunkte umgesetzt sind und jede PHP-Datei des Plugins per `php -l` fehlerfrei geprüft wurde.

## 1. Inventar aller Plugin-Ordner

| Ordner | Slug | Menütitel | Version alt → neu | Batch / PR | Zweck | Status |
|---|---|---|---|---|---|---|
| `cms-promos` | `cms-promos` | 365CMS \| Promos | 3.0.4 → **4.0.0** | Batch 1 | Banner, Teaserflächen, Platzierungen, Klick-Tracking | [x] abgeschlossen |
| `cms-projects` | `cms-projects` | 365CMS \| Projekte | 3.0.4 → **4.0.0** | Batch 1 | Projekte, Boards, Tickets, Widgets (Admin/Member/Public) | [x] abgeschlossen |
| `cms-booking` | `cms-booking` | 365CMS \| Buchungen | 3.0.8 → **4.0.0** | Batch 1 | Termin- und Buchungssystem mit Anbietern, Leistungen, Verfügbarkeiten | [x] abgeschlossen |
| `cms-beratung` | `cms-beratung` | 365CMS \| Beratung | 2.9.794 → **3.0.0** | Batch 2 | Landingpage-Builder für Beratungsleistungen mit Anfrageformular | [ ] offen |
| `365NET-eventsandspeaker` | `cms-365neteventsandspeaker` | 365CMS \| Events & Speaker | 3.2.0 → **4.0.0** | Batch 2 | Event- und Speaker-Verzeichnis | [ ] offen |
| `365NET-expertsandcompanie` | `cms-365netexpertsandcompanie` | 365CMS \| Experts & Companies | 1.2.0 → **2.0.0** | Batch 2 | Experten- und Firmenverzeichnis | [ ] offen |

## 2. In den Core übernommene Plugins (`integrated_in_core`)

Seit 365CMS 4.0.0 sind `cms-feed`, `cms-downloads`, `cms-newsletter`, `cms-knowledgebase` und `cms-contact` Teil des Cores. Keiner dieser Ordner liegt noch in diesem Repository (entfernt mit den Commits „delete old Plugins“). Im Katalog `index.json` sind sie ohne Versionsnummer mit `"integrated_in_core": "4.0.0"` und `"install_supported": false` geführt, damit 3.x-Updater kein Update anbieten. Core-Logik wird nicht nachgebaut.

| Plugin | Core-Route (Migrationshinweis) | Im Repo | Katalog |
|---|---|---|---|
| `cms-contact` | `/admin/forms, /admin/form-submissions` | nein | [x] `integrated_in_core: 4.0.0`, `install_supported: false` |
| `cms-downloads` | `/admin/downloads` | nein | [x] `integrated_in_core: 4.0.0`, `install_supported: false` |
| `cms-feed` | `/admin/settings?tab=feeds` | nein | [x] `integrated_in_core: 4.0.0`, `install_supported: false` |
| `cms-knowledgebase` | `/admin/knowledgebase` | nein | [x] `integrated_in_core: 4.0.0`, `install_supported: false` |
| `cms-newsletter` | `/admin/newsletter` | nein | [x] `integrated_in_core: 4.0.0`, `install_supported: false` |

Migration: `IntegratedPluginService` deaktiviert aktive Exemplare, `PluginMigration\PluginMigrator` übernimmt die Daten idempotent (Admin-Aufrufe und täglicher Cron), alte Adminseiten `/admin/plugins/<plugin>/<seite>` leiten per 301 auf die Core-Route um. Die alten Plugin-Ordner können nach der Übernahme gelöscht werden.

Weitere Katalogbereinigung: `cms-forum` stand noch im Katalog, der Ordner wurde jedoch entfernt; der Eintrag ist mit `install_supported: false` markiert. Die beiden 365NET-Plugins wurden mit ihren Installations-Slugs in den Katalog aufgenommen.

## 3. Prüfkatalog (für jedes eigenständige Plugin)

1. **Einheitliches Admin-Layout** – `.cms-plugin-layout` innerhalb von `.cms-plugin-admin-content`: Split-Header (`365CMS | <Kurzname>`, Versions-/Status-Badge, Einzeiler, 3–4 KPI mit Live-Zahlen), darunter `240px minmax(0, 1fr)` (einspaltig < 992px) mit linkem Plugin-Menü (erster Punkt Übersicht, `aria-current`, Tabler-Icons, Badges) und rechtem Inhalt (Übersicht: Schnellaktionen, System-Check, Aktivität; Unterseiten: Header → Toolbar/Filter → Inhalt). Markup über `cms_plugin_workspace_open()`/`cms_plugin_dashboard()` aus dem Core, mitgelieferte Kopie + Rückfall-CSS nur für Cores ohne Partial. Nur `--cms-*`/`--tblr-*`-Tokens, Radius ≤ 2px, Light/Dark.
2. **365CMS-4.0.0-Kompatibilität** – keine entfernten 3.5.x-APIs, Callbacks liefern nur Inhalt (Router liefert die Shell), Pfad-Routen `/admin/plugins/<plugin>/<unterseite>` + `?page=`, gültige Member-Capabilities, Anbindung an `cms_sitemap_entries`, `search_results`, `cms_member_export_data`/DSGVO, `DataRetentionService`, `AdminNoticeService`, `ContentTypeRegistry` wo fachlich passend, PHP-8.4-Deprecations beseitigt.
3. **Unfertiges fertiggestellt** – Formulare, Listen, Filter, Sammelaktionen, Export/Import, Lifecycle `<slug>_activate/_deactivate/_uninstall`, idempotente Tabellen und versionierte Migrationen.
4. **Sicherheit & CSP** – Prepared Statements, Whitelist für ORDER BY/LIMIT/IN, Escaping, CSRF + Capability bei jedem POST/AJAX, keine Inline-Handler/-Scripts ohne Nonce, kein unsicheres `innerHTML`, `CMS\Http\Client`, `Security::safeLocalPath()`, `CryptoService` für Secrets.
5. **Speed & Best Practices** – keine N+1-Queries, kein `SELECT *`, Indizes, Migrationen nicht pro Request, Assets nur auf eigenen Seiten mit `defer`, Metadaten (`Requires CMS/at least: 4.0.0`, `Requires PHP: 8.4.1`, `update.json`, `plugin.json`, `CHANGELOG.md`).

## 4. Checkliste

| Plugin | 1 Layout | 2 Kompatibilität | 3 Fertigstellung | 4 Sicherheit | 5 Performance | `php -l` | Gesamt |
|---|---|---|---|---|---|---|---|
| `cms-promos` 4.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 14 Dateien, 0 Fehler | [x] |
| `cms-projects` 4.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 21 Dateien, 0 Fehler | [x] |
| `cms-booking` 4.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 33 Dateien, 0 Fehler | [x] |
| `cms-beratung` 3.0.0 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| `365NET-eventsandspeaker` 4.0.0 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| `365NET-expertsandcompanie` 2.0.0 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |

Prüfungen je Plugin (alle ohne Befund): `php -l` aller PHP-Dateien; grep auf Inline-Handler (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`, `onload=`, `javascript:`), entfernte/veraltete APIs (`curl_close`, `finfo_close`, `imagedestroy`, `MYSQL_ATTR_`, `renderAdminLayoutStart`, `buildSidebarPluginIcon`, `isSlugActive`, `invalidateFeedCache`, Hook `plugin_uninstalled`), `innerHTML`/`outerHTML`/`insertAdjacentHTML` in Assets, `SELECT *`, `<style>`/`<script>` ohne Nonce bzw. ohne `src`; JSON-Validierung von `update.json`/`plugin.json`; `includes/plugin-workspace.php` byte-identisch mit dem Core-Partial.

Grenzen der Prüfung: Im Prüfcontainer lief weder ein 365CMS noch MySQL. SQL-Migrationen, Mailversand, Cron, Router-Einbettung und CSP im Browser wurden per Code-Lesen und Smoke-Tests mit nachgebauten Core-Klassen geprüft, nicht gegen eine laufende Installation. Vor dem Rollout: Aktivierung auf einer bestehenden Installation des Vorgängerstands, ein Durchlauf aller Admin-Seiten und Formulare sowie die öffentlichen Routen mit Produktiv-CSP testen. Offene Punkte stehen je Plugin im Abschnitt „Offene Punkte / Risiken“.

## 5. Befunde und Umsetzung je Plugin

### cms-promos – Upgrade 3.0.4 → 4.0.0 (365CMS 4.0.0)

Plugin-Ordner: `cms-promos` · Schema 1.1.0 → 1.2.0 · Stand 2026-11-15

#### 1. Inventar

**Dateien**

| Datei | Zweck |
|---|---|
| `cms-promos.php` | Bootstrap, Header, Konstanten, Hooks, Lifecycle `cms_promos_activate/deactivate/uninstall` (Dateiende, `function_exists`-geschützt) |
| `includes/class-sanitizer.php` | **neu** – zentrale Bereinigung (Text, URL, lokaler Pfad, Whitelist-HTML, UTM, Slug, Datum); ersetzt drei Kopien |
| `includes/class-installer.php` | Tabellen, versionierte Migration (Spalten + Indizes), Schema-Option, `tables_exist()`, `uninstall()` |
| `includes/class-repository.php` | Datenzugriff: Einstellungen, Platzierungen, Promos, Listen/Filter/Pagination, Sammelaktionen, Tracking, Ausspielung, Suche, Export/Import, Löschfrist |
| `includes/class-public-controller.php` | Routen, Archiv, Klick-Redirect, Theme-Hook-Ausspielung, Frequency-Cap, Impressions, Webhook |
| `includes/class-integrations.php` | **neu** – Sitemap, Seitensuche, Datenaufbewahrung, Systemhinweise |
| `includes/plugin-workspace.php`, `assets/css/cms-plugin-workspace.css` | **neu** – kanonische Rückfall-Kopien (identisch, per `cmp` geprüft) |
| `admin/class-admin-menu.php`, `admin/class-admin-pages.php` | Menü, Dispatcher, Layout, POST-Handler, Downloads |
| `admin/views/page-dashboard.php`, `page-promos.php`, `page-placements.php`, `page-settings.php` | Views (nur Inhalt der rechten Spalte) |
| `assets/js/promos-admin.js` | UTM-Builder, „Alle markieren“, Auswahlprüfung für Sammelaktionen |
| `assets/css/promos-admin.css`, `assets/css/promos-public.css` | Plugin-spezifische Admin-Bausteine / öffentliche Ausgabe |
| `templates/archive-promos.php` | Öffentliches Archiv |
| `update.json`, **neu** `plugin.json`, `CHANGELOG.md`, `README.md`, `FeatureList.md` | Metadaten/Doku |

**Tabellen** (`{prefix}`): `promos`, `promo_placements`, `promo_settings`. Core-Option: `cms_promos_db_version`.

**Admin** (Menü `365CMS | Promos`, Icon `ti ti-speakerphone`, Capability `manage_options`, Root `promos-dashboard`):

| Slug | URL | Inhalt |
|---|---|---|
| `promos-dashboard` | `/admin/plugins/promos-dashboard/promos-dashboard` (auch `/admin/plugins/promos-dashboard`) | Übersicht |
| `promos-items` | `…/promos-items` (`?action=new`, `?edit=ID`, Filter `q,status,placement,sort,p`) | Promos |
| `promos-placements` | `…/promos-placements` (`?action=new`, `?edit=ID`, Filter `q,status,hook`) | Platzierungen |
| `promos-settings` | `…/promos-settings` | Einstellungen, Export/Import, Löschfrist |

**Öffentlich:** `GET /promos`, `/en/promos`, `/promos/placement/:slug`, `/en/promos/placement/:slug`, `/promo/click/:slug`, `/en/promo/click/:slug`; Theme-Hooks `body_start`, `after_header`, `home_content`, `before_footer`. **Kein Member-Bereich.**

#### 2. Gefundene Mängel (Stand 3.0.4, Datei:Zeile vor der Änderung)

##### Punkt 1 – Layout
- `admin/class-admin-pages.php:109-135` `render_with_layout()` ruft `cms_plugin_admin_layout_start()`/`renderAdminLayoutStart()` und rendert eine eigene Sidebar `pr-admin-layout` (`:123-128`, `render_sidebar_menu()` `:266-287`).
- `:180-202` lädt `../shared/admin/plugin-admin-contract.php` (außerhalb des Plugins) bzw. `ABSPATH . 'admin/partials/admin-menu.php'`; `:39-42` hängt an `cms_plugin_admin_dispatch_page()` aus diesem Shared-Ordner.
- Views `admin/views/page-*.php:3` eigene Shell `pr-admin-shell`; `page-dashboard.php:19-26` sechs `stat-card`s mit Text-Icons („ALL“, „ACT“ …), `:28-37` Info-/Feature-Karten mit Kürzel-Icons; Liste und Formular nebeneinander (`page-promos.php:15`, `page-placements.php:15`).
- `promos-admin.css`: Hex-Farben, `border-radius` 8–12px/999px, weiße Flächen ohne Dark-Mode, Sidebar-/Hero-/Feature-Klassen; `promos-public.css:1` Variablen global auf `:root`.
- `page-promos.php:114` `<script>` mitten im View.

##### Punkt 2 – Kompatibilität 4.0.0
- `cms-promos.php:86` Hook `plugin_uninstalled` (existiert nicht), `on_uninstall()` `:113-118` leer; keine `cms_promos_*`-Lifecycle-Funktionen; Header ohne `Requires CMS/at least/PHP` (`:2-11`).
- `cms-promos.php:63-73` `load_shared_public_i18n()` lädt `../shared/public/plugin-public-i18n.php`.
- `cms-promos.php:84,95-102` `bootstrap_session()` startet bei **jedem** Request eine Session (bricht die Lazy Sessions / das öffentliche Caching des Core).
- `update.json`: Feld `"requires": "3.0.0"`, `tested 3.4.00`, `min_php 8.4`.
- Keine Sitemap-/Such-/Retention-/Systemhinweis-Anbindung; keine SEO-Meta fürs Archiv; unbekannte Platzierung lieferte 200 statt 404.

##### Punkt 3 – Unfertiges
- Listen ohne Suche/Filter/Pagination/Sammelaktionen (`page-promos.php:17-53`, `page-placements.php:17-52`); kein Export/Import.
- Löschen ohne Bestätigung (`page-promos.php:39-44`, `page-placements.php:38-43`).
- Hinweis „Unbekannte Admin-Unterseite“ (`class-admin-pages.php:324-338`) wurde bei jeder Path-Route geschrieben, aber nie sinnvoll angezeigt.
- Validierungsfehler verwarfen die Formulareingaben (sofortiger Redirect, `:162-163`).
- Hook-Zonen außerhalb von `/promos` ohne CSS (`cms-promos.php:115-131` lud CSS nur auf `/promos`).
- Bild-URL wurde im Archiv nie angezeigt (`templates/archive-promos.php:115-128`).

##### Punkt 4 – Sicherheit & CSP
- `includes/class-public-controller.php:538-553` `curl_init/curl_exec/curl_close`, `:556-565` `@file_get_contents()`-Fallback für den Webhook (kein SSRF-Schutz bei DNS, 1,2 s im Redirect-Pfad).
- `:622-633` (und Kopien in `class-repository.php:554-566`, `templates/archive-promos.php:74-83`) Attribut-Entfernung per Regex; Bug: `$matches[2] ?? $matches[3]` greift nie, weil leere Gruppen `''` statt `null` sind → `href='…'` und unquotierte hrefs gingen verloren.
- `:484-491` `ensure_public_session()` startet Sessions für anonyme Besucher.
- Webhook-URL ungeprüft gespeichert, wenn ungültig (stilles Leeren); Klick-Export ohne URL erlaubt.
- Klicks auf Entwürfe/pausierte Promos wurden weitergeleitet **und gezählt**; Bots wurden gezählt.

##### Punkt 5 – Performance
- `includes/class-installer.php:113-116` `maybe_install()` = `install()` bei **jedem** Request: 3× `CREATE TABLE IF NOT EXISTS`, 13 `information_schema`-Abfragen, 9 Seed-Abfragen + 3 Hook-Updates.
- `SELECT *` in `class-repository.php:212,220,387,395`, `pr.*`/`p.*` in `:186,283,304,337,360`; korrelierte Unterabfragen `:186,337`.
- N+1 in der Ausspielung: `class-public-controller.php:126-201` je Hook eine Platzierungsabfrage, je Platzierung eine Promo-Abfrage und ein UPDATE für Impressions (`:143,148`).
- `save_settings()` `:159-169` und `seed_defaults()` `:63-72` je Schlüssel 2 Abfragen; Zähler-Updates änderten `updated_at` (`:491,502`) und leerten alle Caches.
- Fehlende Indizes für `status + start_at/end_at`, `placement_id + status`, `updated_at`, Hook-Platzierungen.

#### 3. Umsetzung

##### Punkt 1 – Layout
- `CMS_Promos_Admin_Pages::open()/close()` mit `load_workspace()` (Core-Partial, sonst Plugin-Kopie + Rückfall-CSS) und `cms_plugin_workspace_open()`: Titel `365CMS | Promos`, Version, Status (`Aktiv` bzw. Warnung „Datenbank prüfen“ bei Schema-Abweichung), Einzeiler, 4 Live-KPIs (Promos gesamt → Liste, „Laufen gerade“ → `?status=live`, Impressions, Klicks mit CTR-Hinweis), Nav Übersicht/Promos (Warn-Badge = abgelaufene, aber aktive Promos)/Platzierungen (Anzahl)/Einstellungen, `nav_explicit`.
- Übersicht: `cms_plugin_dashboard()` mit Intro, 4 Schnellaktionen, 7 Checks (Tabellen, Schema-Version, Hook-Platzierungen, abgelaufen-aber-aktiv, aktiv ohne Ziel-URL, Webhook, Löschfrist), Aktivität „Zuletzt bearbeitete Promos“; danach Platzierungstabelle.
- Unterseiten: `admin-page-header` → Alert → GET-Toolbar → `admin-card` mit `users-table-container`; Formulare als eigene Ansicht.
- Alte Sidebar, Shell, Info-/Feature-Karten, `renderAdminLayout*`, Shared-Contract entfernt. Admin-CSS komplett neu (nur `pr-*`-Bausteine unter `.cms-plugin-admin-content`, Tokens, Rundung ≤ 2px). CSS/JS werden nur im Callback ausgegeben (`<link>` wird vom Router in den Head verschoben, `<script src defer>`).

##### Punkt 2 – Kompatibilität
- Lifecycle-Funktionen am Dateiende; Uninstall löscht nur die drei eigenen Tabellen + Option `cms_promos_db_version`; Deactivate leert den Systemhinweis-Cache.
- `maybe_install()` vergleicht die Schema-Option (OptionStore-Autoload, keine Zusatzabfrage) und migriert nur bei Abweichung; Migration ergänzt Spalten und Indizes idempotent über `information_schema`.
- Header `Requires CMS: 4.0.0`, `Requires at least: 4.0.0`, `Requires PHP: 8.4.1`; `update.json` ohne `requires`; neue `plugin.json`.
- Kein Shared-Ordner mehr; Core-Helfer `cms_plugin_public_*` mit eingebautem Rückfall.
- Admin-Links/Formulare/Redirects auf `/admin/plugins/promos-dashboard/<sub>`; Dispatcher liest `$_GET['page']`, Rückfall auf den Pfad.
- Erweiterungspunkte siehe Abschnitt 4; Archiv mit `SEOService::setRequestMeta()` (Titel, Beschreibung, Canonical, Robots); Platzierungsseiten `noindex,follow`; unbekannte/inaktive Platzierung → 404; Klick-URLs `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store`.
- `declare(strict_types=1)` + `ABSPATH`-Guard in allen 14 PHP-Dateien.

##### Punkt 3 – Fertiggestellt
- Promo-Liste: Suche (Titel, Slug, Teaser, Kampagne; alle Wörter), Status-Filter inkl. „läuft gerade“/„abgelaufen“, Platzierungs-Filter inkl. „ohne“, Sortierung (Whitelist), Pagination 25/Seite, Sammelaktionen aktivieren/pausieren/archivieren/Entwurf/Zähler zurücksetzen/löschen; Filter bleiben nach Aktionen erhalten (`return_query`, Schlüssel-Whitelist).
- Platzierungs-Liste: Suche, Status-/Hook-Filter, Sammelaktionen aktivieren/deaktivieren/löschen (Promos werden entkoppelt, nicht gelöscht).
- CSV-Export (UTF-8-BOM, `;`, Formel-Injection-Schutz `= + - @ \t \r`), JSON-Export der Konfiguration (ohne Zähler und Webhook-URL), JSON-Import (≤ 2 MB, Endung `.json`, `finfo`-MIME `application/json|text/plain|text/json`, Format-Kennung, Mengenlimits, Upsert über Slug, Zusammenfassung „neu/aktualisiert/übersprungen“).
- Validierungsfehler zeigen das Formular mit den eingegebenen Werten (inkl. Checkboxen); Löschungen/Sammelaktionen/Import mit `data-cms-confirm`; JS verhindert Sammelaktionen ohne Auswahl.
- Neue Einstellungen „Archiv indexieren“ und „Löschfrist (Tage)“ inkl. Trockenlauf-Anzeige und manueller Bereinigung.
- Öffentliches CSS auch für Hook-Zonen auf allen Seiten (wenn aktive Hook-Platzierungen existieren); Bild im Archiv; Karten-Anker `#promo-<slug>`; „Alle Promos“ in der Platzierungsnavigation, nur aktive Platzierungen.

##### Punkt 4 – Sicherheit & CSP
- Webhook über `\CMS\Http\Client::getInstance()->post()` (2 s Timeout, 1 s Connect, 64 KB, eigener User-Agent), Fehler nur protokolliert; kein cURL, kein `file_get_contents('http…')`.
- `CMS_Promos_Sanitizer::html()`: Whitelist-Tags, alle Attribute verworfen außer geprüftem `href` (http(s) öffentlich oder lokaler Pfad via `Security::safeLocalPath`), externe Links mit `rel="noopener noreferrer"`; beim Speichern und bei der Ausgabe angewendet (Smoke-Test: `onclick`, `style`, `javascript:`, `<script>`, `<img onerror>` entfernt).
- Ziel-, Bild- und Webhook-URLs werden beim Speichern validiert (Fehlermeldung statt stilles Leeren); Webhook nicht im Export.
- **Nachtrag:** Webhook-URL als Secret: `CryptoService::encrypt($url, CONTEXT_SETTINGS, 'cms_promos.webhook_url')`, wenn `isAvailable()`; Lesen über `CMS_Promos_Repository::get_webhook_url()` mit `decrypt`, wenn `isEncrypted()` (Klartext-Altwerte lesbar, beim nächsten Speichern verschlüsselt; nicht entschlüsselbar → '' + Log + Systemhinweis). Formular gibt den Wert nie zurück (auch nicht nach Validierungsfehlern): Platzhalter „gespeichert – leer lassen zum Beibehalten“, leeres Feld = behalten, Checkbox „Webhook-URL entfernen“. Import überschreibt die gespeicherte URL nie. Logik mit Stub-CryptoService getestet (Altwert lesen → verschlüsseln, behalten, ersetzen, entfernen, Export-ohne-URL abgelehnt).
- Jede POST-Aktion: `Auth::isAdmin()` + `current_user_can('manage_options')` + CSRF (`verifyToken('promos_admin')`, Downloads `verifyPersistentToken('promos_export')`); Redirects 303 über `Security::safeLocalPath()` auf feste Unterseiten; Downloads leeren den Router-Puffer und enden mit `exit`.
- Keine Session-Starts mehr; Frequency-Cap nutzt eine bereits aktive Session, sonst das Cookie (`HttpOnly`, `SameSite=Lax`, 14 Tage, ≤ 8 KB gelesen).
- Klick auf Entwurf → 404; Klicks/Impressions nur für laufende Promos und nicht für Bots (UA-Muster, leerer UA).
- Alle Ausgaben escaped; keine Inline-Skripte/-Styles/-Handler, kein `innerHTML`.

##### Punkt 5 – Performance
- Pro öffentlichem Request: 1 Abfrage für alle aktiven Hook-Platzierungen (auch für die CSS-Entscheidung wiederverwendet), 1 Abfrage für deren laufende Promos (`IN (…)`), 1 UPDATE für alle Impressions per `register_shutdown_function` (vorher bis zu 4 + 2×n Abfragen).
- Kein Schema-Check mehr pro Request (vorher > 25 Abfragen pro Request).
- Explizite Spaltenlisten (Formular/Export, Liste, Öffentlich, Platzierung); Promo-Zähler je Platzierung per Join; Settings per `INSERT … ON DUPLICATE KEY UPDATE`, Seeds per `INSERT IGNORE` (je 1 Anweisung); eindeutiger Slug mit einer Abfrage; Zähler-Updates mit `updated_at = updated_at`; `LIMIT/OFFSET` als gecastete ints.
- Indizes (Schema 1.2.0, idempotent): `promos.idx_status_window(status,start_at,end_at)`, `promos.idx_placement_status(placement_id,status)`, `promos.idx_updated(updated_at)`, `promo_placements.idx_hook(status,theme_hook,hook_priority)`.
- Löschfrist in Blöcken (`DELETE … LIMIT 500`, max. 20 Runden).

#### 4. Erweiterungspunkte

| Hook/Service | Status | Begründung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | `promos` und `en/promos` (lastmod = jüngste Änderung laufender Promos), nur wenn Promos laufen und „Archiv indexieren“ aktiv. Platzierungsseiten (Filteransichten) und Klick-URLs (Redirects) bewusst nicht. |
| `search_results` | umgesetzt | Laufende Promos, die **alle** Wörter enthalten (Titel/Teaser/Text DE+EN, Prepared Statements, LIMIT ≤ 20), Link `/promos#promo-<slug>` (bzw. `/en/…`), `_type`=`promos`, `date`=`updated_at`; `try/catch`, `$results` unverändert bei Fehlern/fremdem `type`. |
| `cms_member_export_data`, `dsgvo_export_data`, `dsgvo_delete_data` | bewusst nicht | Das Plugin speichert keine personenbezogenen Daten (nur aggregierte Zähler; kein Log mit IP/User). Das Frequency-Cookie liegt beim Besucher; der Webhook-Payload enthält nur Promo-Daten und den Pfad. |
| `DataRetentionService::registerRule()` | umgesetzt | `cms_promos.expired_promos`: löscht Promos, deren `end_at` bzw. Archivierung länger als N Tage zurückliegt; Frist = Einstellung `retention_days` (Standard 0 = nie). Tracking-Daten brauchen keine eigene Frist (nur Zähler, keine Einzelereignisse). |
| `AdminNoticeService::registerCheck()` | umgesetzt | Warnung bei abweichender Schema-Version (OptionStore, ohne Query) bzw. Klick-Export ohne Webhook-URL (eine kleine Settings-Abfrage, gecacht vom Core). |
| `ContentTypeRegistry` | bewusst nicht | Promos haben keinen Papierkorb; Export/Import ist plugin-intern vollständig (CSV/JSON). Eine `ExchangeProvider`-Implementierung (Batches, Preview, ImportContext) wäre ohne laufendes CMS nicht verlässlich testbar und brächte gegenüber dem eigenen JSON-Abgleich keinen Mehrwert. |
| `SEOService::setRequestMeta()` | umgesetzt | Archiv-Meta inkl. Canonical/Robots. |
| `CMS\Http\Client` | umgesetzt | Webhook. |
| `CryptoService` | umgesetzt (Nachtrag) | Webhook-URL verschlüsselt (Bindung `cms_promos.webhook_url`), Altwerte lesbar, Formular ohne Rückgabe. |
| `OptionStore` | umgesetzt | Schema-Version; Plugin-Einstellungen liegen in eigener Tabelle und werden mit einer Abfrage geladen. |

#### 5. Prüfungen

- `php -l`: 14 PHP-Dateien, 0 Fehler (PHP 8.3 im Container).
- `onclick=|onchange=|onsubmit=|oninput=|onload=|javascript:` → leer.
- `curl_close|finfo_close|imagedestroy|MYSQL_ATTR_|renderAdminLayoutStart|buildSidebarPluginIcon|isSlugActive|invalidateFeedCache|plugin_uninstalled` → leer in PHP/JS/CSS (nur im CHANGELOG erwähnt); zusätzlich `session_start`, `curl_init`, `/shared/`, `admin-menu.php`-Require → leer.
- `innerHTML|insertAdjacentHTML|outerHTML` in `assets` → leer.
- `<style` / `<script>` in PHP → leer; `style="` in PHP → leer; `SELECT *` → leer.
- `update.json`, `plugin.json` → gültiges JSON.
- `includes/plugin-workspace.php` und `assets/css/cms-plugin-workspace.css` byte-identisch mit `SCRATCH/shared`.
- Smoke-Tests (Harness mit Stubs, danach gelöscht): Sanitizer (XSS-Vektoren, URLs, Slug mit Umlauten, Datum); alle vier Admin-Views rendern ohne Notices/Warnings (13 Formulare, alle geschlossen, alle Links auf `/admin/plugins/promos-dashboard/…`); öffentliche Hook-Zone, Frequency-Cap (zweiter Aufruf blendet gecappte Promo aus), Archiv, 404-Platzierung, gebündelte Impressions.
- Formular-Durchlauf (Code-Lesen): save_promo/save_placement/save_settings → POST auf eigene Unterseite → Admin + `manage_options` + CSRF → Erfolg: 303 auf Unterseite (`?edit=ID`) mit Session-Meldung; Fehler: kein Redirect, Formular mit Eingaben + Alert. bulk_* / single_delete → 303 zurück zur Liste mit Filtern. export_csv/json → Persistent-Token → Download + `exit`. import_json → Upload-Prüfungen → Zusammenfassung. run_retention → nur bei Frist > 0.

#### 6. Offene Punkte / Risiken

- Kein laufendes 365CMS im Container: SQL (MySQL-Syntax: `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE`, `DELETE … LIMIT`, `SUM(bool)`), Router-Einbettung, Sitemap/Suche/Retention-UI und CSV-Download wurden nur per Code-Lesen bzw. Stub-Harness geprüft, nicht gegen eine echte DB.
- PHP 8.4.1-spezifische Deprecations nur per Lesen geprüft (Container hat 8.3); `fputcsv()` mit explizitem `escape=''`.
- Frequency-Cap kann für Besucher ohne laufende Session nur gespeichert werden, solange noch keine Header gesendet sind (bei Hook-Zonen im Theme abhängig vom Output-Buffering; für anonyme Erstbesucher puffert der Core). Gleiches Verhalten wie 3.x, aber ohne Session-Start.
- Impressions zählen weiterhin pro Seitenaufruf (Zähler, keine Deduplizierung); Bot-Erkennung ist ein einfaches UA-Muster.
- Auf `/promos` zählt eine Promo, die zugleich in einer Hook-Zone erscheint, nur einmal pro Request, belegt aber den Frequency-Cap ggf. vor der Hook-Zone.
- Ist `CryptoService` nicht verfügbar (`isAvailable()` false), wird die Webhook-URL weiterhin im Klartext gespeichert; nach einem Schlüsselwechsel ohne `CMS_ENCRYPTION_KEYS_PREVIOUS` ist sie nicht mehr lesbar (Systemhinweis, neu eingeben). Eine HMAC-Signatur der Webhook-Aufrufe gibt es nicht.
- Datenaufbewahrung löscht auch abgelaufene, nicht archivierte Promos – nur wirksam, wenn der Admin eine Frist > 0 setzt (Standard: nie).
- `FeatureList.md` (englische Feature-Ideen) unverändert belassen.

### Bericht: cms-projects 3.0.4 → 4.0.0

Plugin-Ordner: `cms-projects`.

#### 1. Inventar (nach dem Umbau)

**Dateien**
| Datei | Zweck |
|---|---|
| `cms-projects.php` | Bootstrap, Header (Requires CMS/at least 4.0.0, PHP 8.4.1), Hooks, Lifecycle-Funktionen |
| `includes/class-projects-settings.php` (neu) | Einstellungen (`cms_projects_settings`) und Schema-Version (`cms_projects_schema_version`) über OptionStore/`update_option` |
| `includes/class-projects-repository.php` | SQL (nur Prepared Statements), Schema, Index-Migration |
| `includes/class-projects-service.php` | Validierung, Sichtbarkeit, Board-Zusammenbau, Sammelaktionen, Export/Import, Audit, Suche, Sitemap, Aufbewahrung |
| `includes/class-projects-view.php` (neu) | gemeinsame Board-, Widget- und Ticket-Renderer, Akzentfarben-Styleblock mit Nonce, Stylesheet einmal je Request |
| `includes/class-projects-admin.php` | Admin-Controller (Dispatcher, PRG, Downloads, Layout-Vertrag) |
| `includes/class-projects-integrations.php` (neu) | Sitemap, Suche, DSGVO, Selbstauskunft, Löschfrist-Regel, Systemhinweis |
| `includes/class-projects-public.php`, `…-member-dashboard.php`, `…-shortcode.php` | öffentliche Routen, Member-Bereich, Content-Tags |
| `includes/plugin-workspace.php`, `assets/css/cms-plugin-workspace.css` | kanonische Kopien, identisch mit `SCRATCH/shared` (mit `cmp` geprüft) |
| `admin/views/{overview-due,projects-list,project-form,boards,tasks,widgets,audit,settings}.php` (neu) | Admin-Views |
| `templates/public-projects.php`, `templates/member-projects.php` | Frontend-Views |
| `assets/css/admin.css` (neu), `assets/css/style.css`, `assets/js/admin-board.js` | Assets |
| `plugin.json` (neu), `update.json`, `CHANGELOG.md`, `README.md`, `FeatureList.md` | Metadaten |
| entfernt: `admin/page.php` | durch `admin/views/*` ersetzt |

**Tabellen** (Präfix + Name): `projects_projects`, `projects_boards`, `projects_widgets`, `projects_tasks`, `projects_audit_logs`

**Admin-Unterseiten** (Hauptmenü `cms-projects`, Titel „365CMS | Projekte“, Icon `ti ti-briefcase`)
| Slug | URL | Inhalt |
|---|---|---|
| `cms-projects` (bestand) | `/admin/plugins/cms-projects/cms-projects` | Übersicht/Dashboard |
| `cms-projects-projects` (bestand) | `/admin/plugins/cms-projects/cms-projects-projects` (`?project_id=`, `?action=new`) | Projektliste und Projektformular |
| `cms-projects-boards` (neu) | `…/cms-projects-boards?project_id=` | Boards und Drag-and-drop-Ansicht |
| `cms-projects-tasks` (neu) | `…/cms-projects-tasks?project_id=` | Tickets |
| `cms-projects-widgets` (neu) | `…/cms-projects-widgets?project_id=` | Widgets |
| `cms-projects-audit` (neu) | `…/cms-projects-audit` | Audit-Log |
| `cms-projects-settings` (neu) | `…/cms-projects-settings` | Einstellungen |

**Routen**
- Öffentlich: `/projects`, `/projects/:slug`, `/en/projects`, `/en/projects/:slug`
- Member: `/member/plugin/projects?project=<slug>`
- Content-Tags: `[cms_projects …]`, `[cms_projects_widget …]`

#### 2. Gefundene Mängel (Stand 3.0.4, Datei:Zeile vor der Änderung)

##### Punkt 1 – Layout
- `admin/page.php:163–583`: eine Sammelseite für alles: Projektliste, Projekt-, Board-, Widget- und Ticketformular, Audit-Trail, Board-, Widget- und Ticketlisten untereinander. Kein Workspace-Layout und kein Plugin-Menü.
- `admin/page.php:177–183`: eigene KPI-Karten `dashboard-grid` im Inhalt; zusätzlich ein zweiter Seitentitel (`:164–169`).
- `includes/class-projects-admin.php:30–37`: Menü-Icon `'PRJ'`, keine Tabler-Klasse.
- `includes/class-projects-admin.php:94–106`: Admin-CSS über den globalen Hook `admin_head`.
- `assets/css/style.css`: feste Flächen (`#fff`, `#0f172a`, `#f8fafc`) und `border-radius` von 10 bis 16px, auch im Admin. Alte Layoutklassen (`cp-admin-shell`, `cp-admin-grid`, `cp-panel`, `cp-header-card`, `cp-summary-*`, `cp-alert-*`).

##### Punkt 2 – 4.0.0-Kompatibilität
- `cms-projects.php:2–9`: Header ohne `Requires CMS`, `Requires at least` und `Requires PHP`.
- `cms-projects.php:72`: Aktivierung über den Hook `plugin_activated` statt `cms_projects_activate()`. Es gab weder `_deactivate()` noch `_uninstall()`, Tabellen wurden also nie entfernt.
- `includes/class-projects-admin.php:73`, `:160–166`, `:363–382`: Aufrufe von `cms_plugin_admin_dispatch_page()` und `cms_plugin_admin_layout_start()`, dazu ein Include von `../shared/admin/plugin-admin-contract.php`. Keines davon existiert im Core oder im Repo.
- `includes/class-projects-public.php:112–134`: Include von `../shared/public/plugin-public-i18n.php`, existiert nicht. Der Core lädt die i18n-Helfer selbst.
- `includes/class-projects-admin.php:153–156`: Links als `?page=…` statt als Pfad-Route.
- `includes/class-projects-member-dashboard.php:76–80`: Projektauswahl per Slug prüfte nur `private`, nicht Status oder Sichtbarkeit. Die Capability war `null`, das Label `PROJECTS`.
- Erweiterungspunkte (Sitemap, Suche, DSGVO, Löschfrist, Systemhinweis) fehlten vollständig.

##### Punkt 3 – Unfertiges
- `includes/class-projects-service.php:450–561`: `saveBoard()` und `saveWidget()` legten immer neu an. Boards und Widgets ließen sich weder bearbeiten noch löschen, Projekte nicht löschen.
- Keine Filter, Suche, Sammelaktionen oder Pagination. Die Audit-Liste lud fest 120 Einträge (`class-projects-admin.php:151`).
- Kein Export oder Import.
- `README.md:47–59`: Abschnitte „MVP-Stand“ und „Nächste Ausbaustufen“ waren veraltet. Tickets und Drag-and-drop gab es längst.
- `admin/page.php:415–424`: Die Spaltenauswahl zeigte die Spalten aller Boards. Eine falsche Kombination lieferte einen unklaren Fehler.
- `includes/class-projects-public.php:52–54`: Die 404-Seite war ein nacktes `<h1>` ohne Theme.

##### Punkt 4 – Sicherheit und CSP
- `includes/class-projects-shortcode.php:58–60` und `:110–112`: `[cms_projects … scope="member"]` gab Mitgliederinhalte (interne Boards, Tickets, Widgets) an **anonyme Besucher** aus.
- `includes/class-projects-admin.php:124–133`: POST ohne PRG. Ein Neuladen sendete erneut und lief in „Sicherheitscheck fehlgeschlagen“.
- `style="--cp-project-accent:…"`-Attribute in `class-projects-shortcode.php:68`, `templates/member-projects.php:154` und `templates/public-projects.php:201/224`.
- `templates/public-projects.php:217–221`: Öffentliche Karten zeigten Board-, Widget- und Ticketzahlen inklusive interner Einträge.
- `includes/class-projects-repository.php:486` (`getAuditLogs`): `LIMIT {$limit}` interpoliert (gecastet, aber nicht gebunden).
- `includes/class-projects-service.php:1158`: Das Audit-Log nutzte die E-Mail-Adresse als Akteursname, falls kein Name vorhanden war.
- JS (`assets/js/admin-board.js`) ohne `innerHTML`, das war unkritisch. AJAX-Endpunkte gab es nicht: Verschieben läuft über ein verstecktes POST-Formular, das serverseitig Admin-Recht und CSRF geprüft hat.

##### Punkt 5 – Performance und Best Practices
- 11× `SELECT *` in `class-projects-repository.php` (Zeilen 137, 143, 153, 162, 168, 198, 247, 284, 301, 317, 486).
- `class-projects-service.php:22–25` mit `cms-projects.php:79`: fünf `CREATE TABLE IF NOT EXISTS` bei **jedem** Request.
- `class-projects-service.php:845–863`: ein UPDATE je Ticket beim Verschieben (N+1).
- `class-projects-service.php:865–879`: `findBoardForProject()` lud alle Boards, um eines zu finden.
- `class-projects-repository.php:375–431`: drei Zählabfragen je Projektliste.
- Board- und Widget-Renderer viermal dupliziert (Admin-Template, Shortcode, Member- und Public-Template). Die WIP-Texte waren uneinheitlich („ueberschritten“, englisch im Shortcode).
- Es fehlten Indizes für Sichtbarkeit/Status/Sortierung, Board-Sortierung, Fälligkeit und `actor_id`.

#### 3. Umsetzung

##### Punkt 1
- Jede Unterseite läuft über `cms_plugin_workspace_open()` und `cms_plugin_workspace_close()`. Die Kopfzeile zeigt vier Live-KPI aus einer einzigen Abfrage (`getSummaryCounts`): Projekte, Aktive Projekte, Offene Tickets, Überfällig (Ton `warning` bei mehr als 0). Status-Badge „Aktiv“, bei veraltetem Schema „Schema veraltet“.
- Linkes Menü mit `nav_explicit`: Übersicht und Projekte (Badge mit Anzahl), Gruppe „Projektinhalte“ mit Boards, Tickets (Warn-Badge bei überfälligen) und Widgets, Gruppe „System“ mit Audit-Log und Einstellungen. Die projektbezogenen Menüpunkte behalten `project_id`.
- Die Übersicht nutzt `cms_plugin_dashboard()`:
  - 4 Schnellaktionen
  - 6 Checks: Tabellen über `information_schema`, Schema-Version, öffentliche Projekte, überfällige Tickets, Sitemap/Suche, Audit-Aufbewahrung
  - Aktivität: die letzten 8 Audit-Einträge mit lesbaren Texten
  - danach eine Tabelle „Fällige Tickets“
- Unterseiten folgen immer `admin-page-header` → Alert → Toolbar (`form.cp-toolbar.toolbar`) → `admin-card` mit `users-table-container`/`users-table`. Keine Emojis, Icons als `ti ti-…`.
- `assets/css/admin.css` enthält nur Plugin-Bausteine (Toolbar, Sammelleiste, Formularraster, Pagination, Badges, Board-Vorschau). Es nutzt nur Tokens, Radius 2px und die Scopes `.cms-plugin-main`.
- `style.css` ist neu auf Tokens aufgebaut, alte Layoutklassen sind entfernt. Board-Spalten scrollen horizontal im eigenen Container, Grid-Kinder haben `min-width: 0`.
- Assets werden nur im Callback geladen (`<link>` und `<script defer>`); `style.css` im Admin nur auf Boards und Widgets. Der globale `admin_head`-Hook ist entfernt.
- `load_workspace` (Methode `loadWorkspace()`) folgt dem Brief: Core-Partial, sonst Rückfall-Partial mit Rückfall-CSS.

##### Punkt 2
- Header, `update.json` (alle Pflichtfelder, kein `requires`) und die neue `plugin.json` stehen auf 4.0.0.
- Lifecycle-Funktionen am Dateiende, mit `function_exists` geschützt:
  - `cms_projects_activate()`: Tabellen und Index-Migration
  - `cms_projects_deactivate()`: leert nur den Cache der Systemhinweise
  - `cms_projects_uninstall()`: entfernt die 5 Tabellen, die 2 Optionen und die eigenen DSGVO-Exportdateien
- `boot()` migriert nur bei abweichender Schema-Version (`CMS_Projects_Settings::SCHEMA_VERSION = '4.0.0'`).
- Nicht existierende Shared- und Legacy-Helfer sind entfernt. Pfad-Routen werden überall genutzt, der Dispatcher akzeptiert zusätzlich `$_GET['page']` oder den Pfad. Die Slugs `cms-projects` und `cms-projects-projects` bleiben erhalten.
- Member: `capability => 'member'`, `csrf => 'core'`. Der Bereich ist nur lesend, deshalb gibt es kein `post_callback`. Die Slug-Auswahl prüft über `findAccessibleProjectBySlug(…, 'member')` Sichtbarkeit und Status; die Zählkachel nutzt eine COUNT-Abfrage.
- `declare(strict_types=1)` und `ABSPATH`-Guard stehen in allen 21 PHP-Dateien.

##### Punkt 3
- **Projekte:** Bearbeitungsmaske mit Kennzahlen, JSON-Export, Löschen (kaskadiert auf Boards, Widgets und Tickets) und `data-cms-confirm`. Liste mit Suche (Name, Slug, Owner), Status- und Sichtbarkeitsfilter, Sammelaktionen (Status setzen, löschen) und 25 Einträgen pro Seite.
- **Boards:** Bearbeiten (`board_id`) und Sammelaktionen (aktivieren, deaktivieren, öffentlich, intern, löschen inklusive Tickets). Tickets aus entfernten Spalten wandern in die erste Spalte (`reassignOrphanTasks`). Ein Board ohne Spalten wird abgelehnt. Doppelte Spaltenschlüssel werden eindeutig gemacht.
- **Widgets:** Bearbeiten, Sammelaktionen, Vorschau. Der Textinhalt erscheint jetzt bei allen Widget-Typen; das Formular kündigte das schon an.
- **Tickets:** Filter nach Projekt oder „Alle Projekte“, Board, Priorität, Status, „nur überfällige“ und Suche. Sammelaktionen nur bei einem einzelnen Projekt. 50 Einträge pro Seite, Löschen im Formular. Die Spaltenauswahl filtert per JS auf das gewählte Board.
- **Export:** CSV für Tickets und Audit-Log: UTF-8 mit BOM, `;`-getrennt, Präfix `'` gegen Formel-Injection, Admin-Recht plus persistentes CSRF-Token, alle Puffer verworfen, danach `exit`. JSON-Export eines Projekts.
- **Import:** JSON bis 1 MB, Prüfung von Endung, `finfo`-MIME und `is_uploaded_file`, Formatkennung `cms-projects/project`. Bei Slug-Konflikt wird ein Suffix angehängt, Projekte werden immer als Entwurf angelegt. Die Rückmeldung fasst zusammen, wie viele Boards, Widgets und Tickets übernommen und wie viele Einträge übersprungen wurden.
- **Audit-Log:** eigene Seite mit Filtern und Pagination.
- **Einstellungen:** Aufbewahrungsfrist (Standard 0 = nie), Sitemap ein/aus, Suche ein/aus. Alle drei Werte werden auch gelesen.
- **Öffentliche 404:** im Theme-Layout, mit `noindex`; Status-Labels übersetzt.
- README und CHANGELOG sind aktualisiert.

##### Punkt 4
- **PRG:** Jede POST-Aktion läuft durch `handlePost()`. Ablauf: Admin-Prüfung im Dispatcher, dann `verifyToken` (einmalig) bzw. `verifyPersistentToken` für Downloads, Flash-Meldung in der Session, `303` über `Security::safeLocalPath()`. Der Anker wird gegen eine Whitelist geprüft.
- **Content-Tags:** `scope="member"` nur, wenn `Auth::isLoggedIn()`. Der Zugriff wird zentral über `findAccessibleProjectBySlug()` geprüft.
- **Akzentfarben:** `<style nonce>` mit `.cp-accent-<id>`, Werte strikt `#rrggbb`. Es gibt keine `style`-Attribute mehr.
- **JS:** nur DOM-APIs. Projektauswahl über `data-cp-autosubmit`, keine Inline-Handler. Das Verschiebe-Formular bleibt POST mit CSRF und Admin-Prüfung.
- **SQL:** `LIMIT`/`OFFSET` gebunden, `IN (…)` mit generierten Platzhaltern, Flag-Spalten per Whitelist (`updateFlag`), LIKE-Wildcards escaped. Alle Schreibzugriffe für Kindobjekte sind zusätzlich auf `project_id` beschränkt.
- **Öffentliche Seiten:** keine Zahlen interner Boards oder Tickets mehr. Der Akteursname im Audit-Log enthält keine E-Mail-Adresse mehr.

##### Punkt 5
- Kein `SELECT *` mehr; eigene schlanke Spaltenlisten für Listen ohne LONGTEXT-Felder.
- Zähler in einer `UNION ALL`-Abfrage; die Neusortierung beim Verschieben ist ein `UPDATE … CASE` statt N Abfragen.
- Boards werden per ID geladen. Request-Cache für Projekte, Summary einmal je Request. In der Ticketliste lädt `getProjectBoards(…, [])` keine Tickets.
- Neue Indizes, idempotent über `information_schema.STATISTICS`: `idx_visibility_status_updated`, `idx_updated`, `idx_project_board_sort`, `idx_active_due`, `idx_actor`.
- `CMS_Projects_View` ersetzt die vier duplizierten Renderer. Logik und View sind getrennt (`admin/views`). Die tote Methode `getTasksByBoard` und `admin/page.php` sind entfernt.

#### 4. Erweiterungspunkte

| Hook / Service | Status | Begründung / Umsetzung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | `projects` und `projects/<slug>` nur für aktive, öffentliche Projekte, mit `lastmod = updated_at`; abschaltbar |
| `search_results` | umgesetzt | Alle Wörter (2 bis 6) müssen in Name, Kurzbeschreibung oder Beschreibung vorkommen (Prepared LIKE), `LIMIT` gebunden, `_type = projects`; ein anderer `?type=` wird übersprungen; `try/catch`; abschaltbar |
| `cms_member_export_data` | umgesetzt | Schlüssel `cms_projects_audit_log`: Audit-Einträge, die die Person als Admin ausgelöst hat. Projektinhalte sind redaktionelle Admin-Inhalte ohne `user_id` |
| `dsgvo_export_data` | umgesetzt | JSON-Datei im privaten Speicher (`cms-projects/privacy-exports`), nur wenn Einträge existieren |
| `dsgvo_delete_data` | umgesetzt | anonymisiert `actor_id` und `actor_name` im Audit-Log (das Protokoll bleibt nachvollziehbar) |
| `DataRetentionService::registerRule` | umgesetzt | `cms_projects.audit_logs`, Frist aus den Einstellungen (Standard 0 = nie), Löschen in Blöcken zu 1.000 |
| `AdminNoticeService::registerCheck` | umgesetzt | Warnung „Datenbank nicht aktuell“, wenn die Schema-Version abweicht (eine Option lesen, sehr billig) |
| `SEOService::setRequestMeta` | umgesetzt | Titel, Beschreibung, Canonical, `noindex` bei 404 und leerem Archiv |
| `ContentTypeRegistry` | bewusst nicht | Kein Papierkorb (kein `deleted_at`) und Board, Widget und Ticket hängen hierarchisch am Projekt. Ein sauberer Trash- oder Exchange-Provider bräuchte eine Schemaänderung samt Wiederherstellungslogik über vier Tabellen. Export und Import sind im Plugin als JSON gelöst, die Suche läuft über `search_results` |
| `CMS\Http\Client`, `CryptoService` | entfällt | keine externen Abrufe, keine Secrets |
| Uploads | nur Import | Die JSON-Datei wird nur gelesen, nicht gespeichert |

#### 5. Prüfungen

- `php -l`: 21 von 21 PHP-Dateien „No syntax errors“ (PHP 8.3).
- `grep onclick=|onchange=|onsubmit=|oninput=|onload=|javascript:`: leer.
- `grep curl_close|finfo_close|imagedestroy|MYSQL_ATTR_|renderAdminLayoutStart|buildSidebarPluginIcon|isSlugActive|invalidateFeedCache|plugin_uninstalled`: leer. Zusätzlich gibt es kein `SELECT *` und keine Aufrufe von `cms_plugin_admin_layout*` mehr.
- `grep innerHTML|insertAdjacentHTML|outerHTML` in `assets`: leer.
- `grep "<style\|<script>"` in `*.php`: ein Treffer, `includes/class-projects-view.php:95`, nonce-geschützt (`nonceAttr()`). Keine `style="`-Attribute.
- JSON (`update.json`, `plugin.json`): `json_decode` mit `JSON_THROW_ON_ERROR` ok.
- Shared-Dateien: mit `cmp` identisch zu `SCRATCH/shared`.
- **Smoke-Test** mit gestubbten Core-Klassen (`SCRATCH/harness/run.php`, `post.php`):
  - Platzhalterzahl gegen Parameterzahl bei jeder Abfrage geprüft.
  - Alle 13 GET-Varianten der 7 Admin-Seiten rendern ohne Fehler oder Notice (Fehler wurden als Exception geworfen).
  - Ebenso öffentliches Archiv und Einzelseite, Member-Bereich, beide Content-Tags, Suche, Sitemap, Selbstauskunft.
  - Alle POST-Aktionen liefern die erwartete Flash-Meldung: Projekt speichern, Sammelaktion, Löschen, Board, Verschieben, Ticket mit ungültigem Datum, Ticket-Sammelaktion, Widget, Einstellungen, unbekannte Aktion.
  - CSV-Export von Tickets und Audit-Log mit BOM und `;`.
  - Ein falsches CSRF-Token wird abgelehnt.

#### 6. Offene Punkte und Risiken

- **Kein laufendes CMS und kein MySQL getestet.** Die Smoke-Tests nutzen Stubs; MySQL-spezifisches SQL ist nur gelesen, nicht ausgeführt: `information_schema`, `DELETE … ORDER BY … LIMIT ?`, `UPDATE … CASE`, `UNION ALL` mit `GROUP BY`. Bitte einmal real prüfen: Aktivierung auf leerer und auf bestehender 3.0.4-Installation (Index-Migration), Drag-and-drop, Import/Export.
- Der Router puffert die Ausgabe des Callbacks; Downloads und Redirects verwerfen deshalb alle Ausgabepuffer (`ob_end_clean` bis Level 0). Das setzt voraus, dass der Core davor nichts Wichtiges puffert, z. B. Kompression.
- Flash-Meldungen brauchen eine aktive Session (im Admin wegen CSRF ohnehin gegeben); ohne Session erscheint nach dem Redirect keine Meldung.
- Das Member-Menü-Icon bleibt der Text `PRJ`, weil das Member-Layout Icons als Text ausgibt.
- Suche und Sitemap liefern deutsche Pfade; `en/projects/…` nur bei `locale = en` im Suchkontext.
- `FeatureList.md` (Ideenliste) ist unverändert.

### Bericht cms-booking – Upgrade 3.0.8 → 4.0.0 (365CMS 4.0.0)

Plugin-Ordner: `cms-booking` · Version 4.0.0 · Schema-Version 2 → 3 · Lifecycle-Präfix `cms_booking_` · Menütitel `365CMS | Buchungen`

#### 1. Inventar

**Dateien (33 PHP + Assets/Meta)**

| Bereich | Dateien |
|---|---|
| Bootstrap | `cms-booking.php` (Header, Konstanten, Loader, Hooks, Member-Bereich, `cms_booking_activate/deactivate/uninstall`) |
| includes | `class-settings.php` (neu), `class-installer.php`, `class-providers.php`, `class-services.php`, `class-availability.php`, `class-bookings.php`, `class-notifications.php`, `class-calendar-export.php`, `class-integration.php`, `class-privacy.php` (neu), `class-public-index.php` (neu), `class-frontend.php`, `plugin-workspace.php` (kanonische Kopie) |
| admin | `class-admin-menu.php`, `class-admin-pages.php`, `modules/trait-page-{dashboard,bookings,providers,services,settings}.php`, `views/{dashboard,bookings,booking-detail,providers,provider-edit,provider-availability,services,service-edit,settings}.php` (4 Views neu) |
| templates | `page-provider.php`, `page-booking.php`, `page-confirmation.php` |
| assets | `css/booking-admin.css` (neu geschrieben), `css/booking-public.css`, `css/cms-plugin-workspace.css` (kanonische Kopie), `js/booking-admin.js` (neu geschrieben), `js/booking-public.js` |
| Meta | `update.json`, `plugin.json` (neu), `CHANGELOG.md`, `README.md`, `FeatureList.md` (unverändert) |

**Tabellen** (Präfix + Name): `booking_providers`, `booking_services`, `booking_availability`, `bookings`, `booking_meta`, `booking_settings`; Option `booking_db_version` in Core-`settings`.

**Admin-Unterseiten** (Top-Level-Slug `booking`, alle Slugs unverändert):

| Seite | Slug | URL | Callback |
|---|---|---|---|
| Übersicht | `booking` | `/admin/plugins/booking` | `CMS_Booking_Admin_Pages::render_dashboard` |
| Buchungen | `bookings` | `/admin/plugins/booking/bookings` (`?view=detail&id=`) | `render_bookings` |
| Anbieter | `providers` | `/admin/plugins/booking/providers` (`?view=edit[&id=]`, `?view=availability&id=`) | `render_providers` |
| Leistungen | `services` | `/admin/plugins/booking/services` (`?view=edit[&id=]`) | `render_services` |
| Einstellungen | `settings` | `/admin/plugins/booking/settings` | `render_settings` |

Core-Fallback-Slugs `booking-dashboard`, `booking-bookings`, `booking-providers`, `booking-services`, `booking-settings` werden zusätzlich vom Dispatcher akzeptiert; `dispatch_*`-Methoden (3.x-Menü-Callbacks) bleiben als Aliase.

**Public-Routen:** `GET /booking/{provider}`, `GET|POST /booking/{provider}/{service}`, `GET /booking/confirm/{id}?token=`, `GET /booking/ical/{id}?token=`, **neu** `POST /booking/cancel/{id}?token=`, `GET /api/booking/slots/{providerId}/{date}` – jeweils auch unter `/en/…`.
**Member:** `/member/plugin/booking` (Liste eigener Buchungen + Stornieren, Kachel mit Zähler).

#### 2. Gefundene Mängel (Stand 3.0.8, Datei:Zeile vor der Änderung)

##### 2.1 Layout
- `admin/class-admin-pages.php:255-293` (`render_with_layout`): eigene Shell über `cms_plugin_admin_layout_start()` / `renderAdminLayoutStart()` / eigener Header/Sidebar-Fallback (`:381-409`), zusätzlicher Wrapper `booking-admin-shell`; kein Layout-Vertrag 4.0.0.
- `cms-booking.php:52-62` und `class-admin-pages.php:357-372`: Laden von `../shared/admin/plugin-admin-contract.php` (existiert weder im Repo noch im Core).
- `admin/views/dashboard.php:12-43`: sechs eigene Stat-Karten statt KPI-Header, `:49-54,66,89,95` Inline-Styles mit Hex-Farben, Emoji-Icons der Integrationen.
- `admin/views/providers.php:21-31,45,68,83,91-109,129,133` / `services.php:178,202,226,228,243,247,265-303,347-378` / `settings.php:33-183`: zahlreiche `style="…"`-Attribute (Grids, Farben, `display:none`-Modal), Emoji/Unicode-Zeichen als Button-Icons (`▶`, `✔`, `↩`).
- `assets/css/booking-admin.css`: Hex-Farben, `border-radius` 6–20px, ungenutzte Klassen (`booking-avail-grid`, `booking-status-filters`, `booking-integration-*`, `booking-override-*`).

##### 2.2 Kompatibilität 4.0.0
- `cms-booking.php:101`: `on_uninstall` an nicht existierenden Hook `plugin_uninstalled` → Deinstallation entfernte nie Tabellen; keine `cms_booking_activate/deactivate/uninstall`-Funktionen.
- `cms-booking.php:2-10`: Header ohne `Requires CMS`/`Requires at least`/`Requires PHP`; `update.json` mit `requires_cms 3.4.00`; keine `plugin.json`.
- **Router-Bruch:** Der Core-Router (`core/Router.php:302-345`) verlangt für öffentliche POSTs ein `form_guard`-Token im Feld `csrf_token`. Das Buchungsformular (`templates/page-booking.php:109`) sendete ein `booking_submit`-Token → jede öffentliche Buchung endete in 4.0.0 mit 403.
- `cms-booking.php:202-215`: Member-Registrierung mit unbekannten Top-Level-Schlüsseln (`color`, `admin_url`), keine Capability; `register_member_section()` typisiert ohne `instanceof`-Prüfung.
- `cms-booking.php:291-305`: DSGVO-Hooks werten nur `$userId` aus, Anfragen ohne Konto (nur E-Mail) wurden ignoriert; Export feuerte nur den eigenen Hook `dsgvo_export_data_booking` (keine Datei, keine Auskunft).
- `includes/class-frontend.php:514-521`: `SEOService::instance()->setTitle/Description` ohne Canonical/Robots; Bestätigungsseite indexierbar.
- `class-frontend.php:541`: `$auth->getUser()` existiert in `CMS\Auth` nicht → Buchung angemeldeter Nutzer warf Error (im `try` gefangen → „Fehler beim Speichern“).
- Kein Sitemap-, Such-, Retention-, Notice-, `cms_member_export_data`-Anschluss.

##### 2.3 Unfertige Funktionen
- **Mails nie versendet:** `CMS_Booking_Notifications` wurde nirgends instanziiert (Hooks im Konstruktor nie registriert); `class-frontend.php:333` rief die nicht existierende Methode `send_booking_created()` auf → Error, Nutzer sah „Fehler beim Speichern“, obwohl die Buchung angelegt war.
- **Keine Verfügbarkeits-Oberfläche:** Wochenplan/Ausnahmen (`class-availability.php`) nur per API; ohne Integration konnte kein Anbieter Slots haben.
- **Keine manuellen Anbieter**, kein Bearbeiten; Löschen-Handler (`trait-page-providers.php:47`) ohne Button.
- `trait-page-providers.php` / `class-providers.php:74-85`: Sync per `upsert()` überschrieb Bio, Telefon, Zeitzone, `settings_json` mit NULL/Defaults.
- Einstellungen gespeichert, aber nie gelesen: `send_reminders`, `reminder_hours`, `cancellation_hours`, `default_duration`, `default_buffer`, `default_currency`, `primary_color`.
- Buchungsliste: keine Sammelaktionen, keine Detailansicht/Notizen, kein Datumsfilter, kein Export; `no_show`/`delete` hatten Handler, aber keine Buttons; `count()` (`class-bookings.php:221-246`) filterte anders als `get_all()` (Anbietername nicht durchsucht) → falsche Seitenzahl.
- `max_bookings` wird gelesen, aber hat kein Formularfeld und keine Wirkung in der Slot-Berechnung (siehe offene Punkte).
- Sofortbuchung: `create()` + `confirm()` → zwei Mails mit widersprüchlichem Text.

##### 2.4 Sicherheit & CSP
- `class-frontend.php:553-557`: Token-Secret `CMS_SECRET_KEY` existiert im Core nicht → Fallback `sha256(ABSPATH|SITE_URL)` (schwach; durch zufällige `ical_uid` im HMAC-Input nicht direkt ausnutzbar).
- `class-frontend.php:204,217-223`: `$old = $_POST` ungefiltert an Template; `$e(?string)` mit Array-Werten → TypeError unter strict_types; keine Längenbegrenzung (VARCHAR 255/50 → SQL-Fehler im Strict-Mode); kein Rate-Limit.
- `class-calendar-export.php:30-78`: keine Zeilenfaltung (RFC 5545 §3.1), CN-Parameter mit TEXT-Escaping statt quoted-string (§3.2), `METHOD:REQUEST` für einen Download, `STATUS:CONFIRMED` auch für offene/stornierte Buchungen, Zeiten via `strtotime()` in Server-Zeitzone statt Anbieter-Zeitzone, keine Cache-Header.
- `class-notifications.php:151-170,330-332`: `{{time}}`/`{{date}}` unescaped, Absendername nicht quoted, `mail()` direkt (ohne Core-Mailer/Log), Emojis in Templates.
- `assets/js/booking-admin.js:80-121`: eigener Bestätigungsdialog für jedes Formular mit `value="delete"`-Input (fehleranfällig), statt Core `data-cms-confirm`.
- `class-integration.php:314`: `SELECT *` auf fremde Quell-Tabelle.
- Admin-POSTs ohne PRG (Doppel-Submit bei Reload).

##### 2.5 Performance
- `class-availability.php:273-289` + `class-frontend.php:937-953`: Kalender über 90 Tage = je Tag eine Override-Abfrage, eine Buchungs-Abfrage und bis zu drei Limit-Zählungen → bis zu ~450 Abfragen pro Seitenaufruf (und nochmals nach Formularfehler).
- `class-installer.php:400-412`: Schema-Version bei jedem Request per eigener Abfrage; Settings in `class-frontend.php:972` und `class-notifications.php:340` je separat geladen.
- 11× `SELECT *` (`class-availability.php:125,147`, `class-integration.php:314`, `class-services.php:116,126,139`, `class-providers.php:103,117,131,145,166`).
- Fehlende zusammengesetzte Indizes für `provider_id + booking_date + status`, `status + booking_date`, Erinnerungen, Verfügbarkeit je Datum/Wochentag.
- Assets mit `filemtime()` ohne Versionspräfix; public-Routen-Regex enthielt Dubletten.

#### 3. Umsetzung

##### 3.1 Layout
- `class-admin-pages.php` neu: `render_page()` → POST-Verarbeitung vor Ausgabe → `load_workspace()` (Core-Partial, sonst Kopie + Rückfall-CSS) → `cms_plugin_workspace_open()` mit Titel `365CMS | Buchungen`, Icon `ti ti-calendar-event`, Version, Status (Aktiv / Mail-Konfiguration unvollständig / Tabellen fehlen), 4 Live-KPIs (offene Anfragen, heute, 7 Tage, aktive Anbieter – mit Links), Navigation mit Badge „offene Anfragen“, `nav_explicit` → Inhalt → `cms_plugin_workspace_close()`.
- Übersicht: `cms_plugin_dashboard()` mit Schnellaktionen, 8 System-Checks, „Zuletzt eingegangen“; danach Tabelle „Nächste Termine“ (`views/dashboard.php`).
- Alle Unterseiten: `admin-page-header` (h2 + Einzeiler + `header-actions`) → Alerts → `<form method="get" class="… booking-admin-toolbar">` → `admin-card` mit `users-table-container`. Modal und alle Inline-Styles/Emojis entfernt.
- `booking-admin.css` neu: nur `.cms-plugin-admin-content .booking-admin-*`, Tokens `--cms-*`/`--tblr-*` mit Fallback, Rundung 2px, `min-width: 0` in Grids. `booking-admin.js` neu (Autosubmit, Farbvorschau, Alle-auswählen/Zähler/Button-Sperre).

##### 3.2 Kompatibilität
- Lifecycle-Funktionen am Dateiende (`function_exists`-geschützt): Aktivieren = idempotente Installation, Deaktivieren = Cache leeren, Deinstallieren = alle 6 Tabellen (mit `FOREIGN_KEY_CHECKS=0`), Option `booking_db_version`, DSGVO-Exportdateien.
- Header + `update.json` + `plugin.json` auf 4.0.0 / PHP 8.4.1; Menü über `add_menu_page`/`add_submenu_page`, Slugs unverändert.
- Öffentliche Formulare: Feld `csrf_token` = `form_guard`-Token (Router), Feld `booking_token` = Plugin-Token; Filter `cms_public_form_persistent_token` meldet `booking_submit`/`booking_cancel` für die eigenen Pfade, Plugin prüft danach einmalig.
- Member-Bereich: `capability => 'member'`, `csrf => 'core'` (Token `member_plugin_booking` über `csrfField('booking')`), `post_callback` für Stornierung **nur eigener** Buchungen (`user_id`-Abgleich), Kachel mit `stats_callback`.
  - Hinweis: Der Brief nennt `member_plugin_cms-booking`. Der Core bildet die Aktion aus dem **Bereichs-Slug** (`'member_plugin_' . $section['slug']`); der Slug `booking` wurde beibehalten, damit `/member/plugin/booking` gültig bleibt. Das Token kommt aus der Registry, daher stimmt die Prüfung.
- `Auth::getCurrentUser()` statt `getUser()`, SEO über `setRequestMeta()` (Canonical, `robots_index=false` für Bestätigung), Mails über `cms_mail()`.
- `declare(strict_types=1)` + ABSPATH-Guard in allen 33 PHP-Dateien.

##### 3.3 Fertiggestellt
- Benachrichtigungen: Instanz in `cms_init`, hook-basiert; Eingangsmail abhängig vom Status (Sofortbuchung wird direkt `confirmed` angelegt → eine Mail), Kunden-Link zur Bestätigungsseite, Admin-Link zur Detailansicht; Erinnerungen über `cms_cron_hourly` mit `reminder_sent`.
- Anbieter: anlegen/bearbeiten (`create_manual()` mit `source_plugin = cms-booking`), Sync überschreibt nur übergebene Felder, Löschen inkl. Leistungen/Verfügbarkeit/Buchungen/Meta, Sammelaktionen, Filter, Paginierung.
- Verfügbarkeit: Wochenplan (2 Fenster/Tag, Validierung Ende > Beginn), Ausnahmen (gesperrt/zusätzlich, ganzer Tag ohne Uhrzeit) mit Löschen (Provider-Prüfung).
- Leistungen: eigene Bearbeitungsansicht (Defaults aus Einstellungen), Reihenfolge, Tageslimit, Sammelaktionen, Filter Anbieter/Status; Löschen setzt `bookings.service_id = NULL`.
- Buchungen: Filter (Status, Anbieter, Von/Bis, Suche inkl. Telefon/Nr.), Sammelaktionen (bestätigen, abschließen, nicht erschienen, stornieren, auf offen, löschen), Zeilenaktionen, Detailansicht mit allen Daten, Meta, interner Notiz, Link zur Kundenansicht; CSV-Export der gefilterten Liste (≤ 5000 Zeilen).
- Selbst-Stornierung (Bestätigungsseite + Member) mit Einstellung „erlauben“ und „Stornierungsfrist“.
- Kalender berücksichtigt Vorlauf min./max. Tage, Mindest-Vorlauf in Stunden und bereits vergangene Slots (auch in der Slot-API).
- Einstellungen erweitert: Rate-Limit, Selbst-Stornierung, eigene Primärfarbe (nonce-geschützte CSS-Variable), Löschfrist; Validierung mit erneuter Anzeige der Eingaben.

##### 3.4 Sicherheit
- Admin: `guard_post()` = `isAdmin()` + `current_user_can('manage_options')` + `verifyToken()` (Export: `verifyPersistentToken`), PRG mit `Security::safeLocalPath()`; Statuswechsel/Löschen nur per POST; `data-cms-confirm` für alle destruktiven Aktionen (Core `cms-inline-actions.js`; im Frontend eigener Rückfall in `booking-public.js`).
- Public: Rate-Limit `Security::checkDbRateLimit()` + `recordDbRateLimitAttempt()` (Standard 10/h/IP), Honeypot, skalare POST-Werte mit Längenlimits, Telefon-Zeichen-Whitelist, 422 bei Fehler.
- Token: HMAC mit `AUTH_KEY` (Fallback-Kette), 3.x-Links weiter gültig; `no-store`/`noindex` auf Bestätigung & ICS; 403-Seite statt Fragment.
- ICS RFC 5545 (Escaping, quoted CN, Faltung 75 Oktette UTF-8-sicher, UTC aus Anbieter-Zeitzone, `METHOD:PUBLISH`, Status-Mapping, URL nur http/https); Google-Link per `http_build_query` mit UTC.
- Mails: Steuerzeichen entfernt, Absender quoted, alle Werte escaped, URLs Schema-Whitelist.
- CSV: BOM, `;`, Formel-Präfix `'` für `= + - @ \t \r`, `Content-Disposition`, Puffer geleert, `exit`.
- Integration: nur validierte, gequotete Spalten der Quell-Tabelle.

##### 3.5 Performance
- `CMS_Booking_Availability::preload_range()` + `CMS_Booking_Bookings::get_booked_ranges_between()` / `count_active_between()`: Kalender mit 3–5 Abfragen statt bis ~450.
- Schema-Version 3 (`migrate_indexes()`, idempotent über `SHOW INDEX`): `bookings.idx_provider_date_status`, `idx_status_date`, `idx_user_date`, `idx_reminder`; `booking_availability.idx_provider_date`, `idx_provider_dow`; `booking_services.idx_provider_status_sort`; `booking_providers.idx_status_name`. Migration nur bei abweichender Version (gelesen über `OptionStore`).
- `CMS_Booking_Settings`: eine Abfrage pro Request; `get_stats()` statisch gecacht; `get_options()` für Auswahllisten.
- Alle `SELECT *` durch Spaltenlisten ersetzt; LIMIT/OFFSET als gecastete ints; `LIKE` mit escaptem `%_`.
- Assets nur auf eigenen Seiten, Cache-Buster `?v=4.0.0-<mtime>`, Scripts `defer`.

#### 4. Erweiterungspunkte

| Hook/Service | Status | Begründung/Umsetzung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | `/booking/<anbieter>` und `/booking/<anbieter>/<leistung>` aktiver Anbieter mit aktiver Leistung; Bestätigung/iCal/API privat |
| `search_results` | umgesetzt | aktive Leistungen; alle Suchwörter müssen in Titel/Beschreibung/Anbietername vorkommen; `_type = booking`; try/catch |
| `dsgvo_export_data` | umgesetzt | JSON-Datei im PrivateStorage (`cms-booking/privacy-exports`), Konto **oder** E-Mail, Audit-Log; Aufräumen nach 30 Tagen (`cms_cron_daily`) |
| `dsgvo_delete_data` | umgesetzt | Anonymisierung (Name, E-Mail, Telefon, Notizen, interne Notiz, user_id) + Meta-Löschung; Buchungen bleiben für Statistik |
| `cms_member_export_data` | umgesetzt | Schlüssel `bookings` |
| `DataRetentionService::registerRule()` | umgesetzt | Regel `cms-booking.bookings`, Einstellung „Löschfrist“ (Standard 0 = nie), Löschung in 500er-Blöcken inkl. Meta |
| `AdminNoticeService::registerCheck()` | umgesetzt | „offene Buchungsanfragen“ (info), „Mail-Konfiguration unvollständig“ (warning); je eine COUNT-Abfrage bzw. gecachte Settings |
| `cms_public_form_persistent_token` | umgesetzt | Router-Freigabe des Plugin-Tokens für Buchung/Stornierung |
| `cms_cron_hourly` / `cms_cron_daily` | umgesetzt | Erinnerungen / Export-Aufräumen |
| `member_dashboard_init` | umgesetzt | Bereich `booking`, Capability `member`, Core-CSRF |
| `ContentTypeRegistry` | bewusst nicht | Buchungen sind Vorgangsdaten mit Personenbezug, kein Inhalt für Papierkorb/Exchange; Anbieter stammen meist aus Quell-Plugins (deren Datenhoheit), Leistungen hängen hart an Anbieter + Verfügbarkeit – ein Export/Import ohne Anbieterzuordnung wäre inkonsistent |
| `CMS\Http\Client` | nicht nötig | keine externen Abrufe |
| `CryptoService` | nicht nötig | keine Secrets in den Einstellungen |

#### 5. Prüfungen

- `php -l`: 33 PHP-Dateien, 0 Fehler (PHP 8.3).
- `grep onclick=|onchange=|onsubmit=|oninput=|onload=|javascript:` → leer.
- `grep curl_close|finfo_close|imagedestroy|MYSQL_ATTR_|renderAdminLayoutStart|buildSidebarPluginIcon|isSlugActive|invalidateFeedCache|plugin_uninstalled` → nur CHANGELOG/update.json (Beschreibung).
- `grep innerHTML|insertAdjacentHTML|outerHTML assets` → leer.
- `grep "<style\|<script>" --include=*.php` → nur `cms-booking.php` (Primärfarbe, mit `nonceAttr()`).
- `SELECT *` → nur in Kommentaren. `style="` → nur in HTML-Mail-Templates (für Mail-Clients erforderlich, nicht CSP-relevant).
- `update.json`, `plugin.json` → valides JSON (`JSON_THROW_ON_ERROR`).
- Kanonische Dateien `includes/plugin-workspace.php` und `assets/css/cms-plugin-workspace.css` byte-identisch zu `SCRATCH/shared`.
- Zusätzlich Smoke-Test mit Core-Stubs (Datenbank/Security/Auth/Hooks gestubbt, im Scratchpad, danach gelöscht): alle 5 Admin-Seiten + 6 Unteransichten rendern ohne Warnung; POST-Szenarien (Sammelaktion, Zeilenlöschung, Notiz, CSV-Export, Anbieter anlegen, Wochenplan inkl. Fehlerfall, Statuswechsel, Leistung mit `javascript:`-URL abgewiesen, Einstellungen inkl. ungültiger E-Mail) liefern erwartete Redirect-Meldung bzw. Fehlermeldung; ICS-Ausgabe, CSV (BOM, Formelschutz), Sitemap, Suche, Router-Token-Filter und öffentliches Formular/POST/Bestätigung geprüft. Dabei gefunden und behoben: ungebremste Slug-Schleife (jetzt max. 500 Versuche, dann Zufallssuffix).

#### 6. Offene Punkte / Risiken

- Kein laufendes 365CMS/MySQL getestet: Migration (`ALTER TABLE … ADD INDEX`, `SHOW INDEX`), `DELETE m FROM … INNER JOIN` (MySQL-Syntax), Mailversand, Cron und Router-Filter `cms_public_form_persistent_token` nur per Code-Lesen bzw. Stub geprüft.
- `max_bookings` (parallele Buchungen) bleibt ohne Wirkung; die Slot-Berechnung behandelt jeden Termin als exklusiv. Eine Kapazitätslogik würde das bisherige Slot-Raster ändern und wurde bewusst nicht eingebaut.
- Pufferzeit wird nur zwischen generierten Slots eingehalten, nicht nach bereits gebuchten Terminen (Verhalten aus 3.x unverändert).
- Bei sehr großen Datenmengen ist der CSV-Export auf 5000 Zeilen begrenzt (Filter verwenden).
- Das neue Token-Secret (`AUTH_KEY`) ersetzt das alte; 3.x-Links werden zusätzlich gegen das alte Secret geprüft. War in 3.x ein eigenes `CMS_SECRET_KEY` definiert (Core kennt es nicht), wären solche Alt-Links ungültig.
- Member-CSRF-Aktion heißt `member_plugin_booking` (Bereichs-Slug), nicht `member_plugin_cms-booking` – siehe 3.2.
- Integrierte Quell-Plugins (`cms-experts`, `cms-speakers`, `cms-events`, `cms-companies`) sind nicht im Repo; Sync nur gegen die dokumentierte Spaltenkonfiguration geprüft.

### `cms-beratung`

Wird mit Batch 2 umgesetzt; Befunde und Umsetzung folgen mit dem zugehörigen Pull Request.

### `365NET-eventsandspeaker`

Wird mit Batch 2 umgesetzt; Befunde und Umsetzung folgen mit dem zugehörigen Pull Request.

### `365NET-expertsandcompanie`

Wird mit Batch 2 umgesetzt; Befunde und Umsetzung folgen mit dem zugehörigen Pull Request.


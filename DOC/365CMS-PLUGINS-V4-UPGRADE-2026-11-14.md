# 365CMS-Plugins – Upgrade auf 365CMS v4.0.0 (Inventar & Audit)

> **Hinweis (15.11.2026, Merges mit `main`):** Für `cms-promos`, `cms-projects`, `cms-booking` und – seit dem Merge in PR #12 – auch `cms-beratung` gilt der Code aus PS-PhinIT/365CMS.DE-PLUGINS#9 und #10, der gegen eine echte 365CMS-4.0.0-Installation getestet ist. Die Beschreibungen dieser drei Plugins weiter unten beziehen sich auf die damit ersetzte Fassung aus PR #11. Maßgeblich ist `DOC/audit/PLUGINS-V4-UPGRADE-2026-11-14.md`. Aus PR #11 übernommen wurden der Katalog (`index.json`), die `plugin.json`-Dateien und die verschlüsselte Ablage der Promos-Webhook-URL über `CryptoService`. Aus PR #12 übernommen wurden die beiden `365NET-*`-Plugins unverändert sowie für `cms-beratung` die Ratenbegrenzung und Core-Antispam-Prüfung des Kontaktformulars und die DSGVO-Anbindung (Auskunft, Löschung, Selbstauskunft). Der Bericht zu `cms-beratung` weiter unten beschreibt die ersetzte Fassung aus PR #12.

Stand: 14./15.11.2026 · Referenz: 365CMS-Core 4.0.0 (`CMS/admin/partials/plugin-workspace.php`, `CMS/core/Routing/AdminRouter.php`, `DOC/plugins/PLUGIN-DEVELOPMENT.md`) · Anforderungen aller Plugins: **365CMS ≥ 4.0.0, PHP ≥ 8.4.1**

Ein Plugin wird erst abgehakt (`[x]`), wenn alle fünf Katalogpunkte umgesetzt sind und jede PHP-Datei des Plugins per `php -l` fehlerfrei geprüft wurde.

## 1. Inventar aller Plugin-Ordner

| Ordner | Slug | Menütitel | Version alt → neu | Batch / PR | Zweck | Status |
|---|---|---|---|---|---|---|
| `cms-promos` | `cms-promos` | 365CMS \| Promos | 3.0.4 → **4.0.0** | Batch 1 | Banner, Teaserflächen, Platzierungen, Klick-Tracking | [x] abgeschlossen |
| `cms-projects` | `cms-projects` | 365CMS \| Projekte | 3.0.4 → **4.0.0** | Batch 1 | Projekte, Boards, Tickets, Widgets (Admin/Member/Public) | [x] abgeschlossen |
| `cms-booking` | `cms-booking` | 365CMS \| Buchungen | 3.0.8 → **4.0.0** | Batch 1 | Termin- und Buchungssystem mit Anbietern, Leistungen, Verfügbarkeiten | [x] abgeschlossen |
| `cms-beratung` | `cms-beratung` | 365CMS \| Beratung | 2.9.794 → **3.0.0** | Batch 2 | Landingpage-Builder für Beratungsleistungen mit Anfrageformular | [x] abgeschlossen |
| `365NET-eventsandspeaker` | `cms-365neteventsandspeaker` | 365CMS \| Events & Speaker | 3.2.0 → **4.0.0** | Batch 2 | Event- und Speaker-Verzeichnis | [x] abgeschlossen |
| `365NET-expertsandcompanie` | `cms-365netexpertsandcompanie` | 365CMS \| Experts & Companies | 1.2.0 → **2.0.0** | Batch 2 | Experten- und Firmenverzeichnis | [x] abgeschlossen |

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
| `cms-beratung` 3.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 26 Dateien, 0 Fehler | [x] |
| `365NET-eventsandspeaker` 4.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 21 Dateien, 0 Fehler | [x] |
| `365NET-expertsandcompanie` 2.0.0 | [x] | [x] | [x] | [x] | [x] | [x] 24 Dateien, 0 Fehler | [x] |

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

### Bericht: cms-beratung 2.9.794 → 3.0.0 (365CMS 4.0.0)

Plugin-Ordner: `cms-beratung` · DB-Schema 3 → 4 · Lifecycle-Präfix `cms_beratung_`

#### 1. Inventar

**Dateien (nach dem Umbau)**
- Bootstrap: `cms-beratung.php` (Header, Konstanten, Hooks, `cms_beratung_activate/deactivate/uninstall` am Dateiende)
- Admin: `admin/class-admin-pages.php` (Dispatch, Layout, Aktionen, Downloads, Upload), `admin/class-admin-menu.php`, `admin/class-admin-defaults.php` (neu: Vorlagen/Standardinhalt, aus der Admin-Klasse ausgelagert), `admin/views/*.php` (neu: dashboard, landingpages, proposals, editor, submissions, submission-detail, faqs, presets, settings, import-export, help)
- Logik: `includes/class-installer.php`, `-settings.php`, `-storage.php`, `-forms.php`, `-seo.php`, `-renderer.php`, `-import-export.php`, `-frontend.php`, `-integrations.php` (neu: Sitemap/Suche/DSGVO/Löschfrist/Hinweise), `includes/plugin-workspace.php` (kanonische Kopie)
- Template: `templates/landingpage.php`
- Assets: `assets/css/admin.css` (neu geschrieben; `admin-faq.css` und `admin-media.css` gelöscht und zusammengeführt), `assets/css/cms-plugin-workspace.css` (kanonische Kopie), `assets/css/frontend*.css` (5 Dateien, unverändert bis auf eine Regel in `frontend-extra.css`), `assets/js/admin-builder.js`, `assets/js/frontend.js`, `assets/img/consultant-portrait.svg`
- Metadaten: `update.json`, `plugin.json` (neu), `CHANGELOG.md` (neu); `DOC/GLOBAL-DESIGN-MODULE.md`, `config/global-design.json`, `src/theme/*` unverändert

**Tabellen** (Präfix + …): `beratung_landingpages`, `beratung_settings`, `beratung_design_presets`, `beratung_form_submissions`; Option `beratung_db_version` in `{prefix}settings`.

**Admin-Unterseiten** (Hauptmenü `cms-beratung`, Titel „365CMS | Beratung“, Icon `ti ti-briefcase`; alle Slugs beibehalten)

| Slug | URL | Inhalt |
|---|---|---|
| `cms-beratung` | `/admin/plugins/cms-beratung` | Übersicht (Dashboard) – **vorher Landingpage-Liste** |
| `cms-beratung-landingpages` | `/admin/plugins/cms-beratung/cms-beratung-landingpages` | Liste; jetzt eigener Menüeintrag |
| `cms-beratung-new` (Ansicht) | `…/cms-beratung-landingpages?view=cms-beratung-new[&id=]` | Editor (auch alter Weg `…/cms-beratung?view=cms-beratung-new` funktioniert) |
| `cms-beratung-preview` (Ansicht) | leitet 303 auf `/api/cms-beratung/preview?id=` | Vorschau |
| `cms-beratung-submissions` | `…/cms-beratung-submissions[?id=]` | Anfragen, Detail |
| `cms-beratung-faqs` | `…/cms-beratung-faqs` | M365 FAQs |
| `cms-beratung-presets` | `…/cms-beratung-presets` | Design Presets |
| `cms-beratung-settings` | `…/cms-beratung-settings?tab=design\|contact\|privacy\|seo\|tracking` | Einstellungen (Reiter = Menüpunkte, Gruppe „Einstellungen“) |
| `cms-beratung-import-export` | `…/cms-beratung-import-export[?export=ID]` | Import/Export |
| `cms-beratung-help` | `…/cms-beratung-help` | Hilfe |

**Öffentliche Routen**: GET/POST `/beratung/{slug}`, `/de/beratung/{slug}`, `/en/beratung/{slug}` (nur veröffentlichte Seiten) und dieselben für einen optionalen Standalone-Slug; GET `/api/cms-beratung/preview?id=` (nur Admin); POST `/api/cms-beratung/media-upload` (Admin + persistenter Token `editorjs_media`). Kein Member-Bereich.

#### 2. Gefundene Mängel (Stand 2.9.794)

##### Punkt 1 – Layout
- `admin/class-admin-pages.php:1220–1241` `render_with_layout()` rief `renderAdminLayoutStart/End()` bzw. `cms_plugin_admin_layout_start()` auf; kein `cms_plugin_workspace_open()`, kein Plugin-Menü, kein Dashboard.
- Dashboard fehlte; Hauptseite war die Landingpage-Liste mit eigenen `stat-card`s im Inhalt (`:223`).
- Eigene Karten/Hero-Gestaltung (`beratung-card` mit `<h1>` in jeder Unterseite, z. B. `:318`, `:353`, `:378`, `:468`), Emoji-Icons in Vorlagenkarten (`:629–634`).
- `admin.css` (32 KB): Radien 10–24px und 999px, Verläufe, feste Weißflächen, ~34 Hex-Werte ohne Tokens; `admin-media.css` 29 Hex-Werte.
- Admin-CSS zusätzlich global im Hook `head` (`cms-beratung.php:75`, `enqueue_admin_assets_for_request()` per URI-Substring).
- Vorschau gab ein vollständiges HTML-Dokument im Router-Callback aus (`:438–463`) → landete verschachtelt in der Admin-Shell.

##### Punkt 2 – Kompatibilität 4.0.0
- Header ohne `Requires CMS/at least/PHP` (`cms-beratung.php:2–10`); `update.json` mit `"requires": "365CMS"`, `requires_cms: 3.4.00`.
- Lifecycle nur über Hook `plugin_activated` (`cms-beratung.php:73`); keine `_activate/_deactivate/_uninstall`, keine Deinstallation.
- Verweise auf nicht existierende `shared/admin/plugin-admin-contract.php` (`cms-beratung.php:41`, `admin/class-admin-pages.php:1543–1552`, Aufrufe `cms_plugin_admin_*`).
- SEO: eigene `<title>`/Meta/Canonical/OG-Tags im Hook `head` (`includes/class-seo.php:16–66`) → Dopplung mit dem Core-SEO-Head; kein `setRequestMeta()`.
- Keine Sitemap-, Such-, DSGVO-, Löschfrist- oder Systemhinweis-Anbindung, obwohl personenbezogene Anfragen gespeichert werden.

##### Punkt 3 – Unfertiges
- Anfragen: nur Liste der letzten 100 (`:374–388`), keine Detailansicht, Status/Notiz/Löschen trotz vorhandener Storage-Methoden `update_submission`, `delete_submissions`, `purge_old_submissions` (nie aufgerufen).
- Einstellungen gespeichert, aber nie gelesen: `error_message`, `captcha_prepared` (+ `contact.captcha_enabled` im Builder), `show_submissions_backend`, `use_default_font`, `allow_custom_page_css_class`, alle `tracking_*`; `auto_delete_submissions_enabled`/`retention_days`/`notification_enabled`/`store_*`/`tracking_faq|download|bookings_enabled` nicht einmal im Formular.
- Presets: „Eigene Presets sind im Datenmodell vorbereitet“ (`:318`) ohne Funktion.
- Fehler: Einstellungs-Checkboxen ließen sich nie abwählen (verstecktes Feld „0“ + `$raw !== ''` in `class-settings.php:sanitize_from_post`).
- Fehler: E-Mail-/Telefon-Buttonziele gingen beim zweiten Speichern verloren (`class-import-export.php:button_target`, gespeichertes `mailto:` wurde verworfen).
- Landingpage-Liste ohne Filter/Suche/Pagination/Sammelaktionen; Export nur als Textarea.
- `frontend.js:17` rief nicht existierendes `window.cmsTracking` auf.

##### Punkt 4 – Sicherheit & CSP
- `assets/js/admin-builder.js`: 17× `innerHTML` (u. a. `:221, 250, 286, 289, 299, 325, 386, 540, 588, 632, 715, 733, 1216, 1219, 1254, 1256, 1453`); `sectionTemplate()` setzte `section.internal_name` **unescaped** ein (`:1363`, DOM-XSS z. B. über importiertes JSON); Inline-`style` in der CTA-Vorschau (`:1348`). Unter Trusted Types liefen alle Zuweisungen durch die DOMPurify-Default-Policy.
- JSON-LD ohne `JSON_HEX_TAG` bei `JSON_UNESCAPED_SLASHES` → `</script>` aus Seitentexten konnte den Block beenden (`class-seo.php:92/134/149/161`).
- Mails: `cms_mail()` behandelt den Body als HTML, wenn kein `text/plain` gesetzt ist → Nutzereingaben als HTML in Benachrichtigungen (`class-forms.php:send_mail`).
- Formular ohne Ratenbegrenzung, ohne Längenlimits, ohne Core-Antispam.
- Upload (`class-admin-pages.php:130–196`): Endung + „ist irgendein Bild“ statt MIME je Endung, Größe erst nach Inhaltsprüfung, vorhersagbare Dateinamen (`name-1.ext`), SVG-Prüfung ohne DOCTYPE/ENTITY/`style`/externe Referenzen.
- JSON-Import ohne Größenlimit, ohne Endungs-/MIME-Prüfung (`:1470–1478`).
- `check_access()` leitete per 302 um; Redirects ohne `safeLocalPath`.

##### Punkt 5 – Performance
- `register_routes` bei **jedem Request** `SELECT *` über alle Landingpages inkl. aller JSON-Spalten (`class-frontend.php:107`), Standalone-Auflösung mit Schleife über alle Seiten (`:242`).
- `CMS_Beratung_Installer::ensure_for_admin_save()` (CREATE TABLE + Spaltenchecks + Seeds) bei jedem Speichern, beim Öffnen von Editor und Presets.
- FAQ-Konfiguration: 10 Einzelabfragen pro Aufruf (`class-storage.php:m365_faq_config`), mehrfach je Seitenaufbau (Renderer + SEO).
- `Settings::save()` mit einer SELECT-Abfrage je Schlüssel; `SELECT *` in allen Lesefunktionen; fehlende Indizes für E-Mail-Suche und Status/Datum.

#### 3. Umsetzung

##### Punkt 1 – Layout
- `open_layout()/close_layout()` in der Admin-Klasse: `load_workspace()` (Core-Partial, sonst Plugin-Kopie + Rückfall-CSS), `cms_plugin_workspace_open()` mit Titel, Icon, Version, Status (danger bei fehlenden Tabellen, warning bei fehlendem Empfänger), 4 Live-KPIs und explizitem Menü (`nav_explicit`); Einstellungsreiter als Gruppe „Einstellungen“.
- Dashboard (`views/dashboard.php`) mit `cms_plugin_dashboard()` (Aktionen, 7 Checks, Aktivität „Zuletzt geänderte Landingpages“) und Zusatztabelle „Neueste Anfragen“.
- Alle Unterseiten: `admin-page-header` → `alert` → Toolbar (`form method=get`) → `admin-card`/`users-table-container`. Emoji-Vorlagenicons → Tabler.
- `admin.css` neu (nur Builder/Formular/Toolbar/Medien-Bausteine, alles unter `.cms-plugin-admin-content .beratung-admin` bzw. `.beratung-media-modal`, Tokens mit Hex nur als Fallback, Radius 2px). Assets nur im Callback; Script mit Nonce + `defer`.
- Vorschau als eigene Route `/api/cms-beratung/preview` (vollständiges Dokument, noindex, Admin-Prüfung); alte Admin-Vorschau-URL leitet um.

##### Punkt 2 – Kompatibilität
- Header/Version/`update.json`/`plugin.json`/`CHANGELOG.md` gemäß Brief. `requires`-Feld entfernt.
- `cms_beratung_activate()` (Installation), `_deactivate()` (Hinweis-Cache leeren), `_uninstall()` (4 Tabellen, Option, DSGVO-Exporte). Hook `plugin_activated` entfernt.
- Shared-Contract-Verweise und `renderAdminLayoutStart` entfernt; Dispatcher wertet Callback-Slug, `?view=` und `$_GET['page']` aus; alle `dispatch_*`-Methoden bleiben erhalten.
- `CMS_Beratung_SEO::apply_request_meta()` vor dem Theme-Header (Titel, Beschreibung, Canonical, robots_index/follow – Standalone-Variante immer noindex –, OG, Twitter); Plugin-Fallback-Meta nur ohne SEOService; JSON-LD bleibt plugin-eigen.
- `includes/class-integrations.php`: Sitemap, Suche, DSGVO, Selbstauskunft, Löschfrist, Systemhinweise (alle mit `class_exists` und `try/catch`).

##### Punkt 3 – Fertiggestellt
- Anfragen: Filter/Suche/Pagination, Detail (Status, Notiz, Löschen, „Antworten“-Mailto), Sammelaktionen (Status/Löschen), CSV-Export (BOM, `;`, Formel-Schutz), Auto-„Gelesen“, DSGVO-Auskunftsdateien (Download/Löschen, Auto-Bereinigung nach 30 Tagen via `cms_cron_daily`).
- Landingpages: Suche, Status, Sortierung, Pagination, Sammelaktionen, Zeilenaktionen (Bearbeiten, Vorschau, Public, Standalone, JSON-Download, Duplizieren, Veröffentlichen/Zurückziehen, Löschen mit `data-cms-confirm`).
- Eigene Presets anlegen/löschen; Konfigurations-Export/-Import (Einstellungen, FAQs, Presets).
- Einstellungen in 5 Bereiche mit deutschen Beschriftungen; nur Schlüssel des Bereichs werden gespeichert; Checkbox-Fehler behoben.
- Verdrahtet: `error_message`, Sicherheitsfrage (Session-Rechenaufgabe, global oder je Seite), `show_submissions_backend` (Menüpunkt + Seite), `allow_custom_page_css_class`, `use_default_font` (CSS-Klasse), Löschfrist, Tracking (`data-beratung-tracking` → `_paq`/`gtag`/`dataLayer` nur wenn vom Core-Loader nach Einwilligung geladen; optional DOM-Ereignis), `tracking_enabled` je Seite. `mailto:`/`tel:`-Fehler behoben.

##### Punkt 4 – Sicherheit
- Builder: strikter DOM-Builder `fragmentFrom()`/`setMarkup()` (Tokenizer → `createElement`/`setAttribute`/`createTextNode`, verwirft script/style/iframe/svg u. a., `on*`, `style`, `srcdoc`, `javascript:/vbscript:/data:`-URLs); Medienraster/Leerzustände direkt mit `createElement`/`textContent`; `esc()` escaped auch `'`; Bereichsname escaped; CTA-Vorschau per Klasse. Mit Node-Testskript geprüft (Handler/Script/JS-URL werden entfernt, Entities korrekt).
- JSON-LD/Datenblöcke mit HEX-Flags (+ Nonce bei JSON-LD).
- Formular: Rate-Limit (Session + `checkDbRateLimit`/`recordDbRateLimitAttempt`), `AntispamService::evaluate()`, Feldlängen, Plain-Text-Mails, Betreff/Header ohne Steuerzeichen, Reply-To.
- Upload: Größe zuerst, `finfo`-MIME je Endung, `getimagesize` für Raster, zufällige Namen (`name-<12 hex>.ext`), schärfere SVG-Prüfung, kein `finfo_close()`; JSON-Antwort verwirft vorher alle Ausgabepuffer.
- Import: 2 MB, `.json`, MIME, Tiefe 64, Schema-/Inhaltsprüfung, interne IDs entfernt; HTML-Bereich über `PurifierService` (`hub`).
- Alle POSTs: `verify_nonce()` = Admin-Recht **und** `verifyToken()`; Redirects 303 über `safeLocalPath()`; Downloads per POST.

##### Punkt 5 – Performance
- `Storage::published_routes()` (eine schlanke Abfrage, Request-Cache) für Routen, Sitemap, Standalone-Auflösung; Regex-Vorprüfung vor jeder Frontend-DB-Abfrage.
- Explizite Spaltenlisten (`LIST_COLUMNS`, `PAGE_COLUMNS`, `SUBMISSION_COLUMNS`); kein `SELECT *` mehr.
- FAQ/Settings aus einem gebündelten Cache (`Settings::raw()`); Settings speichern mit einer Vorababfrage.
- Migrationen nur bei Versionswechsel; Schema-Version über `OptionStore`; Rekursionsschutz in `install()`.
- Schema 4: `idx_sender_email`, `idx_status_created`, `idx_status_updated` (idempotent über `information_schema.STATISTICS`).
- Blockweises Löschen alter Anfragen; Global-Design-JSON statisch gecacht.

#### 4. Erweiterungspunkte

| Hook/Service | Status | Begründung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | veröffentlichte Seiten ohne noindex, `beratung/<slug>`, lastmod = updated_at; Standalone-Varianten ausgeschlossen (Duplikat) |
| `search_results` | umgesetzt | Typ `beratung`, alle Wörter in Titel/Beschreibung/Keyword/Hero/Bereichen, Prepared Statements, LIMIT gebunden, try/catch |
| `SEOService::setRequestMeta()` | umgesetzt | Titel, Beschreibung, Canonical, Robots, OG, Twitter; JSON-LD bleibt im Plugin |
| `dsgvo_export_data` / `dsgvo_delete_data` | umgesetzt | Anfragen sind personenbezogen; Abgleich über E-Mail, Export als JSON in `PrivateStorageService` (`cms-beratung/privacy-exports`) |
| `cms_member_export_data` | umgesetzt | Schlüssel `beratung_anfragen`, Abgleich über die E-Mail des Mitglieds (Anfragen sind nicht an user_id gebunden) |
| `DataRetentionService::registerRule()` | umgesetzt | Regel `cms_beratung.submissions`, Frist aus Einstellungen → Datenschutz, Standard 0 = nie |
| `AdminNoticeService::registerCheck()` | umgesetzt | fehlende Tabellen (danger), fehlender Empfänger (warning), neue Anfragen (info) |
| `ContentTypeRegistry` | bewusst nicht | Trash bräuchte Soft-Delete-Spalte und geänderte Löschsemantik, Exchange einen zweiten Import-/Exportpfad neben dem vorhandenen, validierten JSON-Format; nicht sauber ohne größere Schemaänderung |
| Member-Dashboard | nicht relevant | Plugin hat keinen Member-Bereich |
| `CMS\Http\Client`, `CryptoService` | nicht relevant | keine externen Abrufe, keine Secrets |

#### 5. Prüfungen

- `php -l`: 26 PHP-Dateien, 0 Fehler. `node --check` für beide JS-Dateien ohne Fehler.
- Inline-Handler/`javascript:`: leer (ein JS-Kommentar wurde umformuliert, der tote Regex-Fallback im Renderer gelöscht).
- Deprecated/entfernte APIs: nur Changelog-Texte (`update.json` Eintrag 2.9.794, `CHANGELOG.md` Zeilen 18 und 52), laut Brief ausgenommen.
- `innerHTML|insertAdjacentHTML|outerHTML` in `assets`: leer.
- `<style`/`<script` in PHP: zwei externe Scripts mit Nonce + `defer`, JSON-LD mit Nonce, zwei `type="application/json"`-Datenblöcke (nicht ausführbar, HEX-escaped); keine `<style>`-Blöcke.
- JSON: `update.json`, `plugin.json`, `config/global-design.json`, `src/theme/global-design.schema.json` gültig.
- Admin-CSS: alle Hex-Werte nur als `var()`-Fallback, alle Radien `var(--beratung-a-radius)` = 2px.
- Smoke-Test (Harness mit gestubbten `CMS\Hooks/Security/Auth`, ohne DB): alle 13 Admin-Ansichten (inkl. 5 Einstellungsbereiche), Frontend-Render der Standardvorlage mit Sicherheitsfrage, SEO-Head und JSON-Export→Import-Roundtrip (12/12 Bereiche) ohne PHP-Warnungen.
- Statische Prüfung: alle Klassen-/Methodenaufrufe existieren und sind sichtbar (Python-Skript über alle PHP-Dateien).
- Formularpfade per Code-Lesen durchgespielt: Editor (`…landingpages?view=cms-beratung-new&id=` → `render_new_page`, Token `beratung_save_landingpage`, 303 mit `notice`), Liste (Token `beratung_landingpage_action`, Zeilen-/Sammelaktionen), Anfragen (Token `beratung_submissions`: Update, Löschen, Bulk, CSV, DSGVO-Datei), FAQs, Presets, Einstellungen je Bereich, Import/Export (Token `beratung_import_export`), Upload (`editorjs_media`), Frontend-Formular (`form_guard` + `beratung_form_<id>`).

#### 6. Offene Punkte / Risiken

- Kein laufendes 365CMS/MySQL im Container: SQL (JSON-Spalten, `information_schema`, neue Indizes, `LIMIT ?` mit `PARAM_INT`) und die Core-Anbindungen (Sitemap, Suche, DSGVO-Flows, Retention, Notices, `setRequestMeta`) sind nur per Code-Lesen gegen den Core 4.0.0 geprüft.
- Verhaltensänderung: `/admin/plugins/cms-beratung` zeigt jetzt die Übersicht statt der Liste (Slug unverändert, Liste unter `cms-beratung-landingpages`).
- Frontend-Template nutzt weiterhin `style="--…"`-Attribute für seitenindividuelle Farb-/Größenwerte; die Core-CSP überführt sie per `InlineStyleRewriter` in nonce-geschützte Klassen. Frontend-CSS (viele Hex-Werte, Theme-unabhängiges Landingpage-Design) bewusst nicht angefasst.
- `src/theme/*.ts` + Schema werden zur Laufzeit nicht verwendet (keine Build-Pipeline; reine Typ-/Dokumentationsreferenz zu `config/global-design.json`, das der Renderer liest) – unverändert gelassen.
- DOM-Builder im Admin unterstützt bewusst nur das plugin-eigene Markup (kein SVG/MathML); visuell im Browser nicht getestet.
- Sicherheitsfrage braucht eine aktive Sitzung (ohne Sitzung wird sie übersprungen, das Formular funktioniert dann aber ohnehin nicht wegen der CSRF-Tokens).
- Tracking meldet nur an Anbieter, deren Globals (`_paq`, `gtag`, `dataLayer`) der Core-Loader nach Einwilligung angelegt hat; Ereignisnamen (`button_click`, `form_submit`, …) sind neu und ggf. im Analysewerkzeug einzurichten.
- Hochgeladene Bilder unter `/uploads/beratung` bleiben bei der Deinstallation erhalten (können in Inhalten weiterverwendet werden).

### 365NET-eventsandspeaker → 4.0.0

Plugin-Ordner: `365NET-eventsandspeaker` (Installationsordner/Slug `cms-365neteventsandspeaker`), Version 3.2.0 → **4.0.0**.

#### 1. Inventar

**Dateien (nach dem Umbau)**
- `cms-365neteventsandspeaker.php` – Bootstrap, Header, öffentliche Assets, Logger, Lifecycle-Funktionen
- `includes/class-database.php` – Schema/Migrationen/Seeds, CRUD, Listen, Sammelaktionen, Export, Sitemap/Suche, Google-Backfill
- `includes/class-admin.php` – Menü, Seiten-Callbacks (AdminRouter), POST-Handler, CSV-Export, KPI/Dashboard/Badges, Formular-Bausteine
- `includes/class-post-type.php` – öffentliche Routen, SEO-Meta, Umleitungen der alten Admin-Adressen
- `includes/class-integrations.php` (neu) – Sitemap, Suche, Selbstauskunft, Systemhinweis
- `includes/class-template-loader.php` – unverändert
- `admin/views/` (neu) – `dashboard.php`, `events-list.php`, `event-form.php`, `speakers-list.php`, `speaker-form.php`, `taxonomies.php`, `settings.php`
- `includes/phinit-admin-nav.php`, `includes/plugin-workspace.php`, `assets/css/phinit-admin-nav.css` – synchronisierte Kopien, **nicht verändert**
- `assets/css/admin.css` (neu geschrieben), `assets/css/public.css`, `assets/js/admin.js`
- `defaults/seed-data.php`, `defaults/event-descriptions.php` (umbenannt aus `_generated_event_descriptions_map.php.txt`)
- `templates/*.php` (4 öffentliche Templates, unverändert), `update.json` (neu), `plugin.json` (neu), `CHANGELOG.md`, `README.md`, `.env.example`, `.htaccess`
- Entfernt: `assets/css/admin-enhancements.css`, `assets/css/admin-excomp-forms.css` (in `admin.css` aufgegangen), `assets/js/public.js` (leer), `logs/.gitkeep` (Log jetzt über Core-Logger)

**Tabellen**: `{prefix}365net_events`, `{prefix}365net_event_speakers`, `{prefix}365net_event_speaker_rel` (FK CASCADE), `{prefix}365net_event_settings`. Schema-Version 3.0.0 → 4.0.0.

**Admin-Unterseiten** (Top-Level-Slug `365netevents`, Slugs unverändert):
| Slug | URL (Helfer) | Callback |
|---|---|---|
| `365netevents` | `/admin/plugins/365netevents` | `pageDashboard` |
| `365netevents-events` | `…/365netevents?view=365netevents-events` (+ `action=new\|edit\|export`) | `pageEvents` |
| `365netevents-speakers` | `…?view=365netevents-speakers` | `pageSpeakers` |
| `365netevents-taxonomies` | `…?view=365netevents-taxonomies` | `pageTaxonomies` |
| `365netevents-settings` | `…?view=365netevents-settings` | `pageSettings` |

`/admin/plugins/365netevents/<unterseite>` funktioniert ebenfalls (Helfer leitet um). Alte Adressen `/admin/365netevents[/…]` → 302/307 auf die neuen URLs.

**Öffentliche Routen**: `/events`, `/events/:slug`, `/speakers`, `/speakers/:slug`, `/event-speakers`, `/event-speakers/:slug` (Alias). Kein Member-Bereich.

#### 2. Gefundene Mängel (Stand 3.2.0, Datei:Zeile vor der Änderung)

**Punkt 1 – Layout**
- `includes/class-admin.php:77` Menütitel „PHINIT | Events & Speaker“, Icon-Emoji „📅“; `:39` Titel im `adopt()` ebenso.
- `class-admin.php:88-111` Menü-Callbacks waren nur Redirects auf eigene Routen `/admin/365netevents/…`; die Seiten zeichneten die Shell selbst (`startShell()` `:706-722` mit `renderAdminLayoutStart()`, Fallback auf `admin/partials/header.php`/`sidebar.php`), `:65` Require von `admin/partials/admin-menu.php`, `:60` Require einer nicht existierenden `../shared/admin/plugin-admin-contract.php`.
- `:54/:113-126` Filter `admin_menu_items` (existiert im Core 4.0.0 nicht) mit Emoji-Icon.
- Seitenköpfe mit Emojis (`:330`, `:377`, `:483`, `:500`, `:592`, `:621`), Buttons nur als Emoji (`:354-358`, `:488`), eigene Header-Klassen `cms365-admin-header`, Sortier-Buttonleiste als zweite Reiterleiste (`:486`), Filter in separater Karte.
- CSS: `admin.css` mit Radien 14–24px, festen Weißflächen/Verläufen, Schatten (kein Dark-Mode); `admin-excomp-forms.css` mit `!important`-Kaskaden, Radien 10–14px; `admin-enhancements.css` mit `#fff`-Flächen.
- Assets global über `head`/`body_end` per Pfadprüfung `/admin/365netevents…` (`cms-365neteventsandspeaker.php:99-100, 217-233, 333-346`) – auf den AdminRouter-Seiten griff die Prüfung nur über `?page=365netevents…`.
- `assets/js/admin.js:38, 54, 74, 173` `innerHTML`; `:416-423` `window.confirm()` über `data-confirm`.
- Medien-Modal auf jeder Seite ausgegeben (`endShell()` `:756`).

**Punkt 2 – Kompatibilität**
- `cms-365neteventsandspeaker.php:97-98` Lifecycle über Hooks `plugin_activated/deactivated` statt `<slug>_activate()`…; kein `_uninstall()` – Tabellen blieben beim Löschen liegen.
- Header ohne `Requires CMS/at least/PHP`; keine `update.json`/`plugin.json`.
- Keine Anbindung an Sitemap, Suche, SEO-Meta, Systemhinweise. Gespeicherte Felder `seo_title`, `seo_description`, `og_image_url` wurden nie ausgewertet (Templates/Routen setzen keine Meta-Daten).
- Experts-&-Companies-Tabellen wurden gelesen und beschrieben, sobald sie existierten – unabhängig davon, ob das Plugin aktiv ist (`class-database.php` `resolvePreferredSourceTable`, `syncExcompLinksFromSpeaker`).
- Log-Datei `logs/cms-365neteventsandspeaker.log` im Web-Root des Plugins (nur Dotfiles per `.htaccess` gesperrt).

**Punkt 3 – Unfertig**
- Route `POST /admin/365netevents/settings/backfill-speakers` (`class-post-type.php:57`, Handler `:500`) ohne Button im Formular – Funktion nicht erreichbar.
- Google API-Key nur per ENV/.env; keine Konfiguration im Admin.
- Listen ohne Status-Filter, ohne Pagination (feste `limit 300`, `class-post-type.php:336, 573`), ohne Sammelaktionen, ohne Export.
- Taxonomie-Formular speicherte Preisklassen, die `getTaxonomyOptions()` (`class-database.php:2314-2328`) fest überschreibt (gespeichert, nie gelesen).
- Platzhalter „Speaker, Thema oder Tag suchen“ – Suche prüfte keine Tags.

**Punkt 4 – Sicherheit/CSP**
- `class-admin.php:805` Fehlermeldung gibt beliebigen `$_GET['error']`-Text aus (escaped, aber frei steuerbar).
- `admin.js` `innerHTML` (s. o.), `confirm()`.
- Google API-Key nur im Klartext (ENV/.env, auch `.env` im Web-Root des Plugins zugelassen).
- LIKE-Platzhalter `%`/`_` im Suchbegriff nicht maskiert.

**Punkt 5 – Performance/Best Practices**
- **Bug**: `saveSettings()` (`class-database.php:2265`) iterierte über `array_keys(getSettings())` – darin auch `schema_version` aus der Tabelle; das Speichern der Einstellungen schrieb `schema_version = ''`, beim nächsten Request lief `ensureSchema()` komplett (Seeds, Purge, Auto-Link mit Hunderten UPDATEs).
- `SELECT *`: `getEvent` `:806`, `getEventBySlug` `:816`, `getSpeaker` `:1035`, `getSpeakerBySlug` `:1045`, Backfill `:2597`; zusätzlich `e.*`/`s.*` in `getEvents`, `getSpeakers`, `getSpeakersForEvent`, `getEventsForSpeaker`.
- `/events` (`class-post-type.php:103-122`): je Event `getLinkedCompany()` + ggf. `detectLinkedCompanyBySignals()`; dabei je Aufruf `SHOW COLUMNS` (nicht gecacht) und `COUNT(*)` auf die Firmentabelle (`resolvePreferredSourceTable`) – bei 15 Events pro Seite ~60–90 Zusatzabfragen.
- `getSettings()` mehrfach je Request (Head-Style, Menüpunkt, Archiv) ohne Cache.
- `ensureSchemaForSave()` lief bei jedem Speichern (`CREATE TABLE IF NOT EXISTS` ×4 + `SHOW COLUMNS`).
- Speaker-Backfill: bis zu 500 Google-Anfragen à 12 s in einer Anfrage, auch für bereits vollständige Profile.
- Leere `public.js` wurde auf allen öffentlichen Plugin-Seiten geladen.
- `defaults/_generated_event_descriptions_map.php.txt`: **nicht verwaist** (wird von `seed-data.php:340` per `require` geladen), aber PHP-Code ohne ABSPATH-Guard mit `.txt`-Endung (per Web als Text abrufbar).

#### 3. Umsetzung

**Punkt 1 – Layout**
- Menü: `add_menu_page('365CMS | Events & Speaker', '365CMS | Events & Speaker', 'manage_options', '365netevents', …, 'ti ti-calendar-event', 45)`; `adopt()` mit `menu_title`/`title` = „365CMS | Events & Speaker“, `status`-Callback (Aktiv / Aktualisierung ausstehend / Datenbankfehler), `stats` (4 echte KPI: kommende veröffentlichte Events, Events gesamt, öffentliche Speaker, Entwürfe – je mit Link), `dashboard` (intro, 4 Schnellaktionen, 8 Checks: Tabellen, Schema-Version, Eventseite, Entwürfe, Speaker-Verzeichnis, Experts & Companies, Google-Suche, Fehlerfall; Aktivität „Zuletzt bearbeitete Events“ mit more_url), `badges` (Entwürfe an „Events“).
- Alle Callbacks geben nur Inhalt aus; der Helfer setzt Kopfzeile und Plugin-Menü. Ohne Helfer-Datei öffnet `renderPage()` das Layout selbst über `cms_plugin_workspace_open()` (Core-Partial bzw. mitgelieferte Kopie).
- Jede Unterseite: `admin-page-header` (h2 + Einzeiler + `header-actions` mit Tabler-Icons) → Meldungen (`alert alert-success|warning|danger`) → Toolbar (`form method="get" class="cms365-toolbar"`) → `admin-card` mit `users-table-container`/`users-table` bzw. Formular-Sektionen. Keine Emojis, keine zweite Reiterleiste (Sortierung jetzt als Select in der Toolbar), kein doppelter Pluginname.
- Overflow: Tabellen in `users-table-container`, Grids mit `minmax(...)`/`min-width: 0`, Toolbar/Media-Zeilen umbrechend.
- `assets/css/admin.css` neu: nur `var(--cms-…, var(--tblr-…, fallback))`, `border-radius` ≤ 2px, keine festen Weiß-/Schwarzflächen, alles unter `.cms-plugin-admin-content` bzw. `.cms365-media-*`. Nur auf eigenen Seiten (`printAssets()` im Callback, Script `defer` + CSP-Nonce, Cache-Buster Version+filemtime).
- `admin.js`: `innerHTML` → `replaceChildren()`/`textContent` (Helfer `textSpan()`); `data-confirm`/`confirm()` entfernt → `data-cms-confirm` (Core); neu „Alle markieren“.
- Medien-Modal nur noch auf Event-/Speaker-Formularen.

**Punkt 2 – Kompatibilität**
- Bootstrap-Header: `Plugin Name: 365CMS | Events & Speaker`, `Version: 4.0.0`, `Requires CMS: 4.0.0`, `Requires at least: 4.0.0`, `Requires PHP: 8.4.1`.
- Lifecycle-Funktionen am Dateiende (mit `function_exists`): `cms_365neteventsandspeaker_activate()` (ensureSchema + Hook `cms_365net_events_activated`), `_deactivate()` (Hook, Daten bleiben), `_uninstall()` (DROP der 4 eigenen Tabellen, alte Logdatei löschen). Hook-Registrierungen auf `plugin_activated/deactivated` entfernt.
- `renderAdminLayoutStart/End`, `admin-menu.php`-Require, `shared/`-Require, Filter `admin_menu_items` entfernt.
- Formulare posten auf die eigene Unterseite (verstecktes Feld `cms365_action`), Weiterleitung 303 über `Security::safeLocalPath()`. Alte Admin-Routen in `class-post-type.php` als Umleitungen (GET 302, POST 307 mit `do=<aktion>`); die CSRF-Aktionsnamen der Formulare sind gleich geblieben, damit auch offene alte Formulare durchlaufen.
- Integration Experts & Companies nur bei aktivem Plugin (`CMS_365NET_EXCOMP_VERSION` oder `PluginManager::instance()->isPluginActive('cms-365netexpertsandcompanie')`); im Formular Hinweis, wenn inaktiv. Nachbar-Plugin nicht verändert; dessen gelesene Spalten (`id, display_name, slug, topic, linked_expert_id, linked_company_id, status`) unverändert.
- Logging über `CMS\Logger` (Kanal `plugins`), Fallback `error_log`.
- Alle Dateien mit `declare(strict_types=1)` + ABSPATH-Guard; `event-descriptions.php` mit Guard.

**Punkt 3 – Fertiggestellt**
- Listen Events/Speaker: Suche, Status-Filter, Zeitraum (Events: anstehend/vergangen/ohne Datum/alle) bzw. Sortierung (Speaker), Sammelaktionen Veröffentlichen/Entwurf/Löschen (`bulkSetStatus`, `bulkDelete`, IN-Platzhalter), Zeilen-Löschen per Button im Sammelformular (`row_delete`, `data-cms-confirm`), Pagination 25/Seite (`p`), Löschen auch im Bearbeitungsformular.
- CSV-Export Events/Speaker (GET mit persistentem Token `365net_export`, aktive Filter, UTF-8-BOM, `;`, Formel-Schutz `csvCell()`, Puffer geleert, `exit`).
- Speaker-Backfill-Button in den Einstellungen (CSRF `365net_settings_speaker_backfill`), deaktiviert mit Begründung, solange nicht konfiguriert; Batches à 25 Speaker ohne Website/LinkedIn mit Cursor (`speaker_backfill_cursor`), Meldung „weitere stehen an“.
- Google-Zugangsdaten im Formular (Key-Feld leer mit Platzhalter „gespeichert“, „Key entfernen“-Checkbox, CSE-ID); ENV hat Vorrang.
- SEO-Felder werden ausgewertet (s. Erweiterungspunkte). Preisklassen-Felder entfernt (fest vorgegeben, `saveTaxonomyOptions` überspringt sie). Speaker-Suche prüft jetzt auch Tags. Eventformular: Feld „Stadt“; Galerie-JSON wird als URL-Liste angezeigt (vorher rohes JSON im Textfeld).
- Import: bewusst nicht gebaut (Seeds sind die vorgesehene Datenquelle; ein CSV-Import für Events mit Speaker-Relationen wäre ein eigenes Feature).

**Punkt 4 – Sicherheit**
- Admin-Recht (`Auth::isAdmin()` in `guard()`) + CSRF (`verifyToken`, einmalig) bei jeder Schreibaktion; GET verändert nichts (Export nur lesend).
- Fehler-/Hinweistexte nur aus Whitelist.
- API-Key: `CryptoService::encrypt(…, CONTEXT_SETTINGS, 'cms365net_events.google_api_key')`, Format-Prüfung, ohne verfügbare Verschlüsselung wird kein Klartext gespeichert (Warnung); Klartext-Altwerte lesbar und beim nächsten Speichern verschlüsselt; nie ins Formular zurückgegeben; `getSettings()` liefert interne Schlüssel (`schema_version`, `google_*`, Cursor) nicht mehr aus.
- `escapeLike()` für alle LIKE-Suchen; Spalten-/ORDER-BY-Werte nur aus Whitelists bzw. Konstanten; LIMIT/OFFSET gecastet.
- `.env` im Plugin-Ordner wird weiterhin gelesen (Abwärtskompatibilität), README/Formular empfehlen ENV bzw. verschlüsselte Einstellung.

**Punkt 5 – Performance**
- `saveSettings()` nur über bekannte Standard-Schlüssel → `schema_version` bleibt erhalten (Bug behoben).
- Kein `SELECT *`/`alias.*` mehr: Listenspalten-Konstanten ohne große JSON-Felder, Detail/Formular über `allEventColumns()`/`allSpeakerColumns()`, jeweils geschnitten mit den vorhandenen Spalten (`selectColumns()`, `SHOW COLUMNS` 1× je Tabelle und Request).
- `/events`: verknüpfte Firmen-IDs sammeln, Top-Partner-Kennzeichen in **einer** Abfrage (`getTopPartnerFlags`); Quelltabelle, Fremdspalten und Signal-Treffer je Request gecacht.
- Einstellungen: eine Abfrage je Request (`loadSettingRows`/`settingsCache`, Invalidierung beim Schreiben).
- `ensureSchemaForSave()` nur bei abweichender Schema-Version.
- Neue Indizes per Migration mit `SHOW INDEX`-Prüfung: Events `idx_status_updated (status, updated_at)`, `idx_start_date`; Speaker `idx_status_updated`, `idx_email`, `idx_display_name`.
- `public.js` entfernt; Admin-Assets nicht mehr im globalen `head`.
- Views nach `admin/views/` ausgelagert; tote Dateien/Funktionen entfernt.

#### 4. Erweiterungspunkte

| Hook/Service | Status | Begründung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | `/events`, `/speakers` + alle veröffentlichten Event-/Speaker-Detailseiten mit `lastmod` (max. 5000 je Typ); `/event-speakers` als Alias nicht gemeldet |
| `search_results` | umgesetzt | Scopes `events`/`speakers`, nur veröffentlicht, alle Suchwörter (≥ 2 Zeichen, max. 6) per Prepared Statement, LIMIT int, `try/catch`, `$results` sonst unverändert |
| SEO-Meta (`SEOService::setRequestMeta`) | umgesetzt | Detailseiten (Titel/Beschreibung aus SEO-Feldern mit Fallback, Canonical, og_image absolut, schema_type Event/Person), Übersichten (noindex bei Suche/Folgeseiten); Speaker-Alias kanonisch auf `/speakers/…` |
| `cms_member_export_data` | umgesetzt | Speaker-Profile, deren E-Mail der Mitglieds-E-Mail entspricht (Schlüssel `event_speaker_profiles`) |
| `dsgvo_export_data` / `dsgvo_delete_data` | bewusst nicht | Speaker/Event-Kontakte sind redaktionelle Datensätze und **nicht an CMS-Konten gebunden** (kein `user_id`). Automatisches Löschen bei Kontolöschung wäre fachlich falsch; Löschung erfolgt im Admin (Einzeln/Sammelaktion). Selbstauskunft per E-Mail-Abgleich ist über den Filter abgedeckt |
| `DataRetentionService::registerRule` | bewusst nicht | Keine Logs/Anfragen mit Löschfrist; vergangene Events sind gewolltes Archiv |
| `AdminNoticeService::registerCheck` | umgesetzt | „Tabellen fehlen“ (danger) bzw. „Schema-Aktualisierung ausstehend“ (warning); billige Prüfung (tableExists + 1 Settings-Abfrage) |
| `ContentTypeRegistry` | bewusst nicht | Eigenes Datenmodell mit Seed-Abgleich und Relationstabelle, kein Papierkorb; die Provider-Schnittstelle lässt sich nicht ohne Doppelpflege sauber abbilden |
| `CMS\Http\Client` | war bereits umgesetzt | Google-Abruf (`fetchGoogleSearchItems`) nutzt den Client (timeout 12 s, maxBytes, Content-Type-Whitelist) |
| `CryptoService` | umgesetzt | Google API-Key |
| `OptionStore` | nicht genutzt | Plugin speichert in eigener Tabelle; stattdessen eine gebündelte Abfrage je Request mit Cache |
| `PluginManager::isPluginActive` | umgesetzt | Gate für die Experts-&-Companies-Integration |

#### 5. Prüfungen

- `php -l`: 21 PHP-Dateien, 0 Fehler.
- `grep onclick=|onchange=|onsubmit=|oninput=|onload=|javascript:` → leer.
- `grep curl_close|finfo_close|imagedestroy|MYSQL_ATTR_|renderAdminLayoutStart|renderAdminLayoutEnd|buildSidebarPluginIcon|isSlugActive|invalidateFeedCache|plugin_uninstalled|admin-menu.php` → leer.
- `grep innerHTML|insertAdjacentHTML|outerHTML assets` → leer (die synchronisierte `phinit-admin-nav.css` enthält ohnehin kein JS).
- `grep "<style\|<script>" --include=*.php` → nur `cms-365neteventsandspeaker.php:276` (öffentliches Einstellungs-CSS mit `nonceAttr()`).
- Alle PHP-Dateien mit `declare(strict_types=1)` und ABSPATH-Guard.
- `update.json`, `plugin.json`: `json_decode(..., JSON_THROW_ON_ERROR)` ok.
- Render-Test mit Stub-Harness (Scratchpad `evtest/harness.php`, Stubs für `CMS\Hooks`, `CMS\Security`, `CMS\Auth`): alle 7 Views (inkl. neues/bearbeitetes Event, Speaker, Einstellungen, Taxonomien) rendern ohne Notice/Warning unter `error_reporting=-1`; Links/Formularziele auf `?view=…`, GET-Toolbar mit verstecktem `view`, Escaping von Titeln geprüft; `csvCell('=SUM(1)')` → `'=SUM(1)`.
- Formulare/Pfade (Code-Lesen): Event speichern (`cms365_action=save`, CSRF `365net_event_form`, 303 auf Bearbeiten + `saved`), Event löschen (Formular/Zeile, `365net_admin`), Sammelaktion, Verknüpfungs-Sync, Speaker speichern/löschen/Sammelaktion, Taxonomien, Einstellungen (+ Google-Daten), Backfill Beschreibungen/Speaker, CSV-Export; alte POST-Routen → 307 → gleiche Handler.

#### 6. Offene Punkte / Risiken

- Kein laufendes CMS/keine Datenbank im Container: Migrationen (neue Indizes, Schema 4.0.0), SQL der neuen Abfragen (Bulk, Export, Suche, Sitemap, Top-Partner-Batch), Redirect-Verhalten im AdminRouter + Helfer und das Zusammenspiel mit `cms-inline-actions.js` (`data-cms-confirm`) sind nur durch Code-Lesen geprüft.
- Beim Update auf 4.0.0 läuft `ensureSchema()` einmal (neue Schema-Version): dabei wie bisher Seeds (nur bei leerer Tabelle), `purgeNonPersonSpeakers()` (löscht Nicht-Personen-Speaker, setzt `company = NULL`) und Auto-Link – das war schon in 3.x das Verhalten bei Versionswechsel.
- `WHERE`-Bedingungen nutzen Meta-Spalten (`tags`, `specializations`, `excerpt`) direkt; sie existieren nach der Migration. Schlägt ein `ALTER` fehl, liefert die Suche einen abgefangenen Fehler (Sitemap/Suche brechen nicht), die Admin-Suche würde einen Fehler werfen.
- Öffentliche Templates enthalten weiterhin `style="--cms-events-…-bg:…"` (je Datensatz gesetzte CSS-Variable) und Emojis in Buttons; bei strenger `style-src` ohne `unsafe-inline` würden diese Variablen ignoriert (Fallback-Farbe greift). Nicht angefasst, da öffentliches Design.
- `.env` im Plugin-Ordner wird aus Kompatibilitätsgründen noch gelesen (bei nginx selbst absichern, siehe `.env.example`).
- `index.json` im Repo-Root (außerhalb des Plugin-Ordners) führt noch den Namen „365 | Events & Speaker“ – nicht geändert (Ordner-Regel).

### Bericht: 365NET-expertsandcompanie → 2.0.0 (365CMS 4.0.0)

Plugin-Ordner: `365NET-expertsandcompanie` · Bootstrap `cms-365NETexpertsandcompanie.php` · Version 1.2.0 → **2.0.0**

#### 1. Inventar

**Dateien (nach Umbau)**
- `cms-365NETexpertsandcompanie.php` – Bootstrap, Frontend-Styles inkl. Design-Variablen-Link, `designVarsCss()`, Schema-Gate, Lifecycle-Funktionen
- `includes/class-database.php` – Schema/Migration (Schema-Version 2.0.0), Seed, CRUD, Batch-Abfragen, Löschen/Status, Export, Suche, Sitemap, DSGVO
- `includes/class-admin.php` – Admin-Menü, `adopt()`-Optionen (Stats/Status/Dashboard), Seiten-Controller, POST-Handler, Formular-Bausteine
- `includes/class-post-type.php` – öffentliche Routen, Design-Variablen-Stylesheet, Bild-Upload-API, Umleitungen alter Admin-Adressen, SEO-Meta
- `includes/class-transfer.php` (neu) – CSV-Export/-Import/-Vorlage, gemeinsamer CSV-Parser (auch für Seeds)
- `includes/class-integrations.php` (neu) – Sitemap, Suche, DSGVO, Systemhinweis, Cron-Aufräumen
- `includes/class-template-loader.php` – unverändert
- `admin/views/` (neu) – `_notice`, `overview`, `public-hub`, `experts-list`, `companies-list`, `expert-form`, `company-form`, `settings`, `transfer`
- `templates/*.php` (5) – Inline-Styles entfernt
- `assets/css/admin.css` (neu geschrieben), `assets/js/admin-media.js` (neu geschrieben), `assets/js/admin.js` (neu), `assets/css/style.css` (unverändert)
- Synchronisierte Kopien **unverändert**: `includes/phinit-admin-nav.php`, `includes/plugin-workspace.php`, `assets/css/phinit-admin-nav.css` (per `git diff 9d925c7` geprüft)
- `defaults/seed-data.php` (nutzt jetzt den Transfer-Parser), `defaults/experts.csv`, `defaults/companies.csv`
- Metadaten: `update.json`, `plugin.json` (neu), `CHANGELOG.md` (neu), `README.md`, `FeatureList.md`

**Tabellen** (unverändert benannt, Schnittstelle zum Events-Plugin): `{prefix}365net_excomp_experts` (+ neue Spalte `email`), `{prefix}365net_excomp_companies`, `{prefix}365net_excomp_settings`; neue Indizes `idx_updated`, `idx_email`, `idx_status_updated` (beide Entitäten).

**Admin** (Top-Level-Slug `experts-companie`, Capability `manage_options`, Unterseiten-Slugs beibehalten; die PhinIT-Navigation stellt sie als `…/experts-companie/experts-companie?view=<slug>` dar, Pfad-Routen leitet sie weiter):

| Seite | Slug | URL |
|---|---|---|
| Übersicht | `experts-companie` | `/admin/plugins/experts-companie` |
| Public Hub | `experts-companie-public` | `/admin/plugins/experts-companie/experts-companie-public` |
| Experts (Liste, `?action=edit&id=`) | `experts-companie-experts` | `/admin/plugins/experts-companie/experts-companie-experts` |
| Companies | `experts-companie-companies` | `…/experts-companie-companies` |
| Import & Export (neu) | `experts-companie-transfer` | `…/experts-companie-transfer` |
| Einstellungen | `experts-companie-settings` | `…/experts-companie-settings` |
| Expert anlegen (versteckt, Elternpunkt Experts) | `experts-companie-expert-new` | `…/experts-companie-expert-new` |
| Company anlegen (versteckt, Elternpunkt Companies) | `experts-companie-company-new` | `…/experts-companie-company-new` |

Alte Routen `/admin/experts-companie[/…]` (GET + POST) leiten weiter (GET 301, POST 307).

**Public-Routen:** `/experts-companie`, `/experts-and-companie`, `/experts-companies`, `/experts`, `/experts/:id`, `/companies`, `/companies/:id`, neu `/experts-companie/design-vars` (CSS). API: `POST /api/experts-companie/media-upload`. Kein Member-Bereich.

**Schnittstellen zum Nachbarn 365NET-eventsandspeaker** (per `grep -rn` geprüft, nicht gebrochen): Tabellennamen `365net_excomp_experts/companies` und Spalten (`id, name, website, logo_url, location_city, industry, linked_expert_id, linked_speaker_id, status, first_name, last_name, email, position, company, photo_url, linked_company_id, is_top_partner`), Konstante `CMS_365NET_EXCOMP_VERSION`, öffentliche URLs `/experts/<id>`, `/companies/<id>`. Die neue Expert-Spalte `email` wird vom Events-Plugin bereits optional gelesen (Speaker-Abgleich). Im Nachbarordner wurde nichts geändert.

#### 2. Gefundene Mängel (vorher, Stand `9d925c7`)

**Punkt 1 – Layout**
- `includes/class-admin.php:73-80` Menütitel „PHINIT | Experts & Companies“, Icon als Emoji `🤝`; `:36-37` adopt-Titel „PHINIT | …“.
- Eigene Admin-Routen außerhalb des AdminRouters (`class-post-type.php:44-58`); Menü-Callbacks waren nur Redirect-Brücken (`class-admin.php:93-126`).
- Eigene Shell: `class-admin.php:1053-1086` (`cms_plugin_admin_layout_start`, `renderAdminLayoutStart`, `header.php`/`sidebar.php`), `:59-67` Require `admin/partials/admin-menu.php` und `shared/admin/plugin-admin-contract.php`.
- Unterseiten-Header mit Emojis und eigenen Klassen (`cms-excomp-admin-header`, z. B. `:413`, `:539`, `:629`, `:723`, `:841`), Sortier-Buttons als zweite Leiste statt Toolbar, Meldungen als `admin-card` statt `alert`.
- `admin.css` (8 KB): `border-radius` 10–24px, feste Farben (`#fff`, `#64748b` …), Schatten/Verläufe, unscoped Klassen, tote Klassen (`cms-excomp-admin-card--experts`, `-grid`); Laden global per Hook `head` (`cms-365NET…php:218-228`) und zusätzlich als Inline-`<style>` (`class-admin.php:1063/1073/1082`).
- 6× `<style>`: `printInlineStyle()` (`cms-365NET…php:249-275`) + Design-Variablen in 5 Templates (`archive-experts.php:123`, `archive-companies.php:123`, `archive-experts-companie.php:109`, `single-expert.php:72`, `single-company.php:72`); zusätzlich `style.css` komplett inline in jedem Template (`:15`).
- Inline-Script-Block `class-admin.php:1103` (`<script type="application/json">`).
- 7× `innerHTML` in `assets/js/admin-media.js:64, 93, 128, 131, 141, 167, 229`.
- „Public Hub“ war kein Inhalt, sondern ein Redirect auf `/experts` (`class-admin.php:98-101`).

**Punkt 2 – Kompatibilität 4.0.0**
- Keine Lifecycle-Funktionen; Aktivierung über Hook `plugin_activated` (`cms-365NET…php:141`).
- Header ohne `Requires CMS/at least/PHP`; `update.json` `min_cms_version 3.0.0`, `min_php 8.1`.
- Toter Filter `admin_menu_items` (`class-admin.php:51`, `:139-151`; vom Core nicht mehr ausgewertet).
- Keine Sitemap-, Such-, DSGVO-, Systemhinweis-Anbindung; keine SEO-Meta; unbekannte Detailseiten mit HTTP 200.

**Punkt 3 – Unfertiges**
- Keine Lösch-Funktion für Experts/Companies (kein Handler), keine Sammelaktionen, kein Status-Filter, Liste hart auf 400 Einträge (`class-post-type.php:405`, `:512`) ohne Seiten.
- Kein CSV-Import/-Export (`defaults/seed-data.php:6` „Keine Upload- oder Import-Funktion“).
- Status als Freitextfeld (`class-admin.php:787`, `:862`).
- **Bug:** `saveSettings()` (`class-database.php:174-204`) iterierte über alle geladenen Schlüssel inkl. `schema_version` und leerte nicht gesendete Felder (`schema_version`, `layout_grid_gap`) bei jedem Speichern.
- Fehlermeldung bei `?error=save` wurde nirgends angezeigt; `?saved=1` in Listen ohne Ausgabe.

**Punkt 4 – Sicherheit**
- Design-Variablen nur `htmlspecialchars` in einem `<style>`-Block → CSS-Injektion über Einstellungen möglich (`archive-experts.php:107-124` u. a.); Header-Button-URLs erlaubten `//host`.
- Upload (`class-post-type.php:633-690`): Endungen inkl. `bmp`, `ico`; MIME nicht an die Endung gebunden (`uploadedFileIsImage` akzeptierte jedes `image/*` bzw. `exif`/`getimagesize`-Treffer); SVG-Prüfung ohne Entity-/externe-URL-Checks; vorhersagbare Dateinamen; Kommentar mit `finfo_close()` (`:701`).
- Redirects über `CMS\Router::redirect()` ohne `safeLocalPath`.

**Punkt 5 – Performance/Best Practice**
- `ensureSchema()` (CREATE TABLE IF NOT EXISTS, SHOW COLUMNS, Seed-COUNT, Speaker-Sync mit bis zu 4.000 UPDATEs) bei **jedem** Request über `cms_init` (`cms-365NET…php:171-181`).
- N+1 in den Archiven: je Expert bis zu 3, je Company bis zu 5 Abfragen (`class-post-type.php:299-339`), bei 30 Karten > 200 Abfragen im Hub.
- 2× `SELECT *` (`class-database.php:247`, `:262`); `getSettings()` ohne Cache (mehrfach je Request: Hauptmenü, Archive, Dashboard).
- Views und Logik vermischt in `class-admin.php` (66 KB).

#### 3. Umsetzung

**Punkt 1 – Layout**
- Menütitel/Header `365CMS | Experts & Companies`, Icon `ti ti-heart-handshake`; Callbacks rendern nur Inhalt, die (unveränderte) PhinIT-Navigation setzt Kopfzeile/KPI/Menü über den Core-Partial.
- KPI (`adminStats()`, 2 COUNT-Abfragen): Experts, Companies, Öffentlich sichtbar, Ausgeblendet – mit Links. Status-Badge (`adminStatus()`): „Aktiv“ bzw. „Aktualisierung ausstehend“/„Datenbank fehlt“.
- Dashboard (`adminDashboard()`): 5 Schnellaktionen, Checks (Schema-Version, öffentliche Experts/Companies x von y, Hauptmenü-Link, Upload-Ordner beschreibbar, Sitemap & Suche, Events & Speaker), Aktivität „Zuletzt bearbeitet“ mit `more_url`; darunter nur die Seed-Karte (keine KPI-Wiederholung).
- Jede Unterseite: `admin-page-header` (h2, Einzeiler, `header-actions`) → `alert` → `form.toolbar` (Suche/Status/Sortierung, GET mit `view`) → Sammelaktions-Formular + `users-table-container`/`users-table` → Pagination. Formulare in `admin-card`-Abschnitten, Löschen mit `data-cms-confirm`. Public Hub ist jetzt eine echte Seite (Routen, Zähler, Anbindungs-Tabelle).
- Alle `<style>`-Blöcke entfernt: `style.css` + Design-Variablen als `<link>` im Hook `head` (nur Frontend-Routen); Design-Variablen über Route `/experts-companie/design-vars?v=<hash>` (text/css, validierte Werte, `immutable`-Cache). `printInlineStyle()` bleibt als No-op für alte Theme-Overrides.
- Medien-Konfiguration als `data-config`-Attribut (JSON mit HEX-Flags) statt Script-Block; `admin-media.js` vollständig auf `createElement`/`textContent`/`replaceChildren` umgestellt (0× innerHTML), `admin.js` für „Alle auswählen“.
- `admin.css` neu: nur Tokens mit Fallback, Radius ≤ 2px, Dark-Mode über Tokens, Scope `.cms-plugin-admin-content`/`.cms-excomp-`; `min-width: 0` an Grid-/Flex-Kindern, `overflow-x:auto` für Tabellen. Laden nur im Callback (`printAssets()`), JS mit `defer`, Cache-Buster `?v=2.0.0`.

**Punkt 2 – Kompatibilität**
- Eigene Shell und Requires entfernt; Pfad-Routen `/admin/plugins/experts-companie/<sub>`; Formulare posten auf die eigene Unterseite; alte Adressen per `register_routes` umgeleitet (GET 301, POST 307 inkl. `excomp_action`).
- Lifecycle `cms_365NETexpertsandcompanie_activate/_deactivate/_uninstall` am Dateiende (mit `function_exists`); `plugin_activated`-Listener entfernt. Activate: Schema + Seed; Deactivate: Systemhinweis-Cache leeren; Uninstall: eigene 3 Tabellen + eigene DSGVO-Dateien (Bilder unter `/uploads/experts-companie` bleiben bewusst).
- Header `Requires CMS: 4.0.0`, `Requires at least: 4.0.0`, `Requires PHP: 8.4.1`; Version 2.0.0 in Header, Konstante, `update.json`, `plugin.json`. Slug in `update.json`/`plugin.json`: `cms-365netexpertsandcompanie` (= kanonischer, kleingeschriebener Slug des PluginManagers/UpdateService; `index.json` außerhalb des Ordners nutzt die Mixed-Case-Schreibweise des Ordners – case-insensitiv identisch).
- Erweiterungspunkte siehe Abschnitt 4; alle Core-Klassen per `class_exists`/`method_exists`, Hooks in `try/catch`.
- `declare(strict_types=1)` + ABSPATH-Guard in allen 24 PHP-Dateien; `fgetcsv/fputcsv` mit explizitem `escape ''` (PHP 8.4).

**Punkt 3 – Fertiggestellt**
- Löschen (Zeile, Bearbeiten-Seite, Sammelaktion) mit Lösen der Verweise (Companies/Experts/Speaker); Sammelaktionen aktivieren/deaktivieren/löschen; Status-Filter; Sortierung; 25 je Seite.
- CSV: Export (Liste mit aktuellem Filter oder Import-&-Export-Seite; BOM, `;`, Formel-Schutz), leere Vorlage, Import (Format der Seed-Dateien + ID/EMAIL/PHOTO_URL/LOGO_URL/Verknüpfungen; `;`/`,`/Tab, BOM, Windows-1252, Zeilenumbrüche in Quotes; Aktualisieren per ID mit Übernahme fehlender Spalten; Ergebnis angelegt/aktualisiert/übersprungen + Zeilenfehler). Seeds nutzen denselben Parser.
- Expert-Feld E-Mail; Status-Select; Pflichtfelder (`required`) und Fehlermeldungen; Meldungen nur über feste Codes (keine reflektierten Texte).
- Settings-Bug behoben: nur Formularschlüssel, `schema_version` intern, nicht gesendete Felder bleiben, ungültige CSS-/URL-Werte werden abgelehnt und im Formular markiert.

**Punkt 4 – Sicherheit**
- Jede POST-Aktion: `Auth::isAdmin()` + `current_user_can('manage_options')` + CSRF (`verifyToken`; Downloads `verifyPersistentToken`). Redirects via `Security::safeLocalPath` + 303. GET verändert nichts.
- Design-Variablen: Whitelist-Regex für Längen/Farben beim Speichern und bei der Ausgabe.
- Upload: Endungen jpg/png/gif/webp/avif/svg, finfo-MIME je Endung, `getimagesize` für Raster, SVG-Prüfung (Scripts, Handler, `<!ENTITY`/interne DTD, `xml-stylesheet`, `javascript:`, externe href/src/url(), `@import`) – getestet mit 8 Fällen; Dateiname mit `random_bytes`; Admin + Capability + CSRF; `finfo_close()` entfällt (Objekt-API).
- CSV-Import: Endung, finfo-MIME, 2 MB, 5.000 Zeilen; Werte laufen durch `saveExpert/saveCompany` (Sanitizing, E-Mail/URL-Validierung).
- Alle Ausgaben escaped (Smoke-Test: `ACME <b>` erscheint nur escaped); SQL ausschließlich prepared, Spaltennamen aus Whitelists, LIMIT/OFFSET als gecastete ints, `IN (…)` über generierte Platzhalter, LIKE-Muster in Suche/Export escaped.

**Punkt 5 – Performance**
- Migration nur bei abweichender Schema-Version (`needsSchemaUpgrade()` = eine Einstellungsabfrage, ohnehin für Menü/Archive benötigt) bzw. bei Aktivierung/„Seed neu einspielen“.
- Archive: 3 bzw. 5 Batch-Abfragen statt N+1 (`getCompaniesByIds`, `getExpertsByIds`, `getSpeakersByIds`, `getSpeakersByLinkedExpertIds`, `getExpertsByLinkedCompanyIds`, `getSpeakersByLinkedCompanyIds`) – gleiche Semantik wie zuvor (inkl. manuell verknüpfter Einträge vorn).
- `SELECT *` → Spaltenlisten (`EXPERT_COLUMNS`/`COMPANY_COLUMNS`), `buildSelect`-Fallback `id`; Einstellungs-Cache je Request; Indizes per idempotenter Migration (information_schema-Prüfung statt `SHOW INDEX … WHERE ?`).
- Views in `admin/views/`, tote Funktionen entfernt (`normalizePluginSlug`, `isAdminRoute`, `addMenuItem` (Admin), Bridge-Callbacks, alte Admin-Handler).

#### 4. Erweiterungspunkte

| Hook/Service | Status | Begründung |
|---|---|---|
| `cms_sitemap_entries` | umgesetzt | 3 Verzeichnisse (0.6, weekly) + alle aktiven Detailseiten (0.5, monthly, lastmod = updated_at); keine Filter-/Suchvarianten |
| `search_results` | umgesetzt | Experts/Companies mit Status aktiv, alle Wörter (max. 6, ≥ 2 Zeichen) müssen vorkommen, `_type` `experts`/`companies`, `$context['type']` fremder Scopes → unverändert; try/catch |
| SEO-Meta (`SEOService::setRequestMeta`) | umgesetzt | Titel/Beschreibung/Canonical; Filter-/Seitenansichten `noindex`; 404 mit `noindex` |
| `dsgvo_export_data` | umgesetzt | Experts (neue Spalte `email`) und Companies (Kontakt-E-Mail) per E-Mail; JSON im PrivateStorage `experts-companie/privacy-exports`, Download/Löschen unter Import & Export, Audit-Log |
| `dsgvo_delete_data` | umgesetzt | Expert-Profile der Adresse löschen (Verweise lösen), bei Companies nur E-Mail/Telefon entfernen |
| `cms_member_export_data` | bewusst nicht | Einträge sind nicht an CMS-Konten gebunden (keine `user_id`), kein Member-Bereich; Auskunft läuft über die Admin-DSGVO-Anfrage |
| `DataRetentionService::registerRule` | bewusst nicht | Verzeichnisdaten ohne fachliche Löschfrist; Auskunftsdateien werden per `cms_cron_daily` nach 30 Tagen entfernt |
| `AdminNoticeService::registerCheck` | umgesetzt | „Tabellen fehlen“ (danger) / „Datenbank-Update ausstehend“ (warning); nur Einstellungs-Cache, keine schweren Queries |
| `ContentTypeRegistry` | bewusst nicht | kein Papierkorb-Modell (kein `deleted_at`), eigener CSV-Austausch vorhanden; Provider-Schnittstelle (Trash/Exchange) ließe sich nur mit Schemaänderung sauber implementieren |
| `CMS\Http\Client` | n. z. | keine externen Abrufe |
| `CryptoService` | n. z. | keine Secrets |
| `OptionStore` | n. z. | Einstellungen in eigener Tabelle, jetzt eine Abfrage je Request |
| Member-Dashboard | n. z. | kein Member-Bereich |

#### 5. Prüfungen

- `php -l`: 24 PHP-Dateien, 0 Fehler (PHP 8.3).
- `grep onclick=|onchange=|onsubmit=|oninput=|onload=|javascript:` → leer (die drei synchronisierten Helfer-Dateien ausgenommen – dort ebenfalls keine Treffer relevant).
- `grep curl_close|finfo_close|imagedestroy|MYSQL_ATTR_|renderAdminLayoutStart|…|plugin_uninstalled` → nur Changelog-Texte (`CHANGELOG.md`, `update.json` 1.0.18).
- `grep innerHTML|insertAdjacentHTML|outerHTML` in `assets` → leer.
- `grep "<style\|<script>"` in PHP → leer. `SELECT *` → nur Changelog.
- JSON: `update.json`, `plugin.json` gültig (`JSON_THROW_ON_ERROR`). JS: `node --check` für beide Dateien ok.
- Smoke-Tests (Stubs für `CMS\Hooks`/`CMS\Security`, `error_reporting=-1`): alle 9 Views rendern ohne Notice/Warning, 19/19 Formulare und 193/193 `<div>` ausgeglichen, Escaping geprüft; CSV-Parser (BOM, Komma, mehrzeilige Felder, Windows-1252, Formel-Maskierung) und SVG-Prüfung (8 Fälle) getestet; Seed-Parser liefert 163 Experts / 104 Companies wie zuvor.
- Admin-Pfade gedanklich durchgespielt: Seed (POST Übersicht → `excomp_admin` → 303 `?notice=seeded`), Settings (`excomp_settings_form`), Expert/Company speichern (`excomp_expert_form`/`excomp_company_form` → Bearbeiten-Seite mit Meldung), Löschen/Sammelaktion (`excomp_list`), Export/Vorlage/Auskunft-Download (`excomp_export`, persistent, Download mit `exit` ohne Layout), Import/Auskunft löschen (`excomp_transfer`), Upload-API (`editorjs_media`), alte POST-Adressen (307 → Router → PhinIT-Weiterleitung 307 → Handler).

#### 6. Offene Punkte / Risiken

- Kein laufendes 365CMS/MySQL im Container: SQL (Batch-Abfragen, Migration mit `ALTER TABLE … AFTER last_name`, information_schema-Indexprüfung, DSGVO-Abfragen), AdminRouter-/PhinIT-Navigation-Zusammenspiel, CSP im Browser und Downloads wurden nur per Code-Lesen bzw. Stub-Tests geprüft.
- Design-Variablen-Route `/experts-companie/design-vars` muss vom Webserver an `index.php` durchgereicht werden (bewusst ohne `.css`-Endung gewählt); bei restriktiver Server-Konfiguration greifen die Standardwerte aus `style.css`.
- Neues Expert-Feld E-Mail wird (wie im bestehenden Template vorgesehen) öffentlich als Kontakt-Button gezeigt – im Formular so beschriftet.
- Vorher ungültige Design-Werte in bestehenden Installationen werden bei der Ausgabe durch Standardwerte ersetzt (bisher ungeprüft ausgegeben) – kann die Optik ändern, wenn z. B. `calc()` genutzt wurde.
- Biografien und Beschreibungen unter 250 Wörtern werden (Altverhalten) auch beim CSV-Import automatisch verlängert.
- Uninstall löscht Tabellen; das Events-Plugin verliert dann seine Expert/Company-Verknüpfungsquelle (es prüft `tableExists`, bricht also nicht).
- `index.json` (außerhalb des Ordners) trägt noch den alten Namen „365 | Experts & Companies“ und `cms-365NETexpertsandcompanie` als Slug – ggf. zentral angleichen.


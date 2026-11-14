# Plugin-Upgrade auf 365CMS 4.0.0 – Audit- & Fortschrittsprotokoll

> **Datum:** 2026-11-14 · **Bezug:** 365CMS Core 4.0.0 (Schema v25, `min_php` 8.4.1) · **Repository:** `365CMS.DE-PLUGINS`
> **Layout-Vertrag:** `CMS/assets/css/admin-plugins.css` + `CMS/admin/partials/plugin-workspace.php` (Core 4.0.0, `CMS_PLUGIN_WORKSPACE_VERSION` 1.0.0)

## 0. Vorbemerkungen

- In diesem Repository liegen die Plugins direkt im Wurzelverzeichnis (`<slug>/`); installiert werden sie nach `CMS/plugins/<slug>/`. Die im Core integrierten Plugins (`cms-contact`, `cms-downloads`, `cms-feed`, `cms-newsletter`, `cms-knowledgebase`) sind hier bereits entfernt und gehören nicht zum Umfang; `index.json` verweist aber noch auf sie (→ bereinigt).
- **Core-Dateien:** Der Core 4.0.0 liefert den einheitlichen Plugin-Layout-Vertrag bereits vollständig aus: `admin-plugins.css` (Split-Header, KPI-Karten, `240px minmax(0, 1fr)`-Workspace, Umbruch unter `992px`, `--cms-*`/`--tblr-*`-Tokens, max. 2px Radius, Dark-Mode) und `admin/partials/plugin-workspace.php` (`cms_plugin_workspace_open()`, `cms_plugin_workspace_close()`, `cms_plugin_dashboard()`). Der `AdminRouter` lädt beides automatisch vor jedem Plugin-Callback. **Eine Core-Änderung war daher nicht nötig**; alle Plugins nutzen ausschließlich diese Core-Helfer.
- Prüfgrundlage: `DOC/plugins/PLUGIN-DEVELOPMENT.md` (Core, Stand 2026-11-14), `AdminRouter`, `Security` (CSP `script-src 'self' 'nonce-…'`, `style-src` ohne `unsafe-inline`), `PluginDashboardRegistry::checkCapability()`, `Http\Client`, `DataRetentionService`, `AdminNoticeService`.
- Syntaxprüfung: `php -l` auf allen PHP-Dateien (lokal PHP 8.3 CLI; die Plugins nutzen keine 8.4-only-Syntax).

## 1. Inventar

| Plugin | Ordner | Familie / Menütitel | Version alt → neu | PHP-Zeilen | JS/CSS-Zeilen | Eigene Tabellen | Status |
|---|---|---|---|---:|---:|---|---|
| CMS Beratung | `cms-beratung/` | `365CMS \| Beratung` | 2.9.794 → 3.0.0 | 5.030 | 1.771 / 4.852 | `beratung_landingpages`, `beratung_presets`, `beratung_settings`, `beratung_form_submissions` | [ ] |
| CMS Booking | `cms-booking/` | `365CMS \| Booking` | 3.0.8 → 4.0.0 | 6.484 | 430 / 773 | `bookings`, `booking_providers`, `booking_services`, `booking_availability`, `booking_meta`, `booking_settings` | [ ] |
| CMS Projects | `cms-projects/` | `365CMS \| Projekte` | 3.0.4 → 4.0.0 | 3.788 | 171 / 626 | `projects`, `project_boards`, `project_tasks`, `project_widgets`, `project_audit_log` | [ ] |
| CMS Promos | `cms-promos/` | `365CMS \| Promos` | 3.0.4 → 4.0.0 | 2.456 | 40 / 531 | `promos`, `promo_placements`, `promo_settings` | [x] |

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

_(wird je Plugin während der Umsetzung ergänzt)_

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

**Prüfung:** `php -l` für alle 10 PHP-Dateien fehlerfrei; Render-Smoke-Test aller 7 Admin-Ansichten mit Core-Partial `plugin-workspace.php` ohne Notices, ohne `style=`/`on*=`-Attribute.

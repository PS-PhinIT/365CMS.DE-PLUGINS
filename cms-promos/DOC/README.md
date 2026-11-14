# 365CMS | Promos – Admin & Integration (ab 4.0.0)

> Weitere Dokumente: [DATABASE.md](DATABASE.md) · [HOOKS.md](HOOKS.md) · [API.md](API.md) · [Changelog](../CHANGELOG.md)

> **Plugin:** `cms-promos` · **Version:** 4.0.0 · **Stand:** 2026-11-14
> **Voraussetzungen:** 365CMS 4.0.0 (Plugin-Layout-Vertrag), PHP 8.4.1

## 1. Admin-Oberfläche

Alle Seiten nutzen den Core-Layout-Vertrag (`cms_plugin_workspace_open()` / `cms_plugin_dashboard()`).

### Kopfzeile

| Element | Inhalt |
|---|---|
| Titel | `365CMS \| Promos`, Versions-Badge, Status-Badge „n live“ bzw. „Keine Promo live“ |
| KPI 1 | Promos gesamt |
| KPI 2 | Aktuell live (aktiv und innerhalb der Laufzeit) |
| KPI 3 | Impressions |
| KPI 4 | Klickrate in % (Hinweis mit Klickzahl) |

### Plugin-Menü (links)

| Menüpunkt | URL | Inhalt |
|---|---|---|
| Übersicht | `/admin/plugins/promos-dashboard/promos-dashboard` | Schnellaktionen (Neue Promo, Neue Platzierung, CSV-Export, Archiv), System-Check, zuletzt bearbeitete Promos |
| Promos | `…/promos-items` | Liste mit Filter (Suche, Status inkl. „live“/„abgelaufen“, Platzierung); `?action=new` bzw. `?edit=<id>` öffnet das Formular. Badge: Anzahl bzw. Warnung bei abgelaufenen aktiven Promos |
| Platzierungen | `…/promos-placements` | Liste, Anlegen/Bearbeiten/Löschen, Theme-Hook und Priorität |
| Einstellungen | `…/promos-settings` | Archivtexte (DE/EN), Standard-Button, Zielverhalten, Klick-Webhook, Zurücksetzen |

### Aktionen

- Promo: Speichern, Duplizieren (als Entwurf), Löschen, Statistik zurücksetzen.
- Gesamt: Statistik aller Promos zurücksetzen, Einstellungen auf Standard zurücksetzen.
- Export: CSV (Excel-tauglich, `;`, UTF-8-BOM) und JSON mit Impressions, Klicks und Klickrate.
- UTM-Vorschau: zeigt die finale Ziel-URL inkl. UTM-Parametern (Kopieren-Button); die gespeicherte Ziel-URL bleibt unverändert, UTM wird erst beim Klick angehängt.

## 2. Öffentliche Ausgabe

| Route | Inhalt |
|---|---|
| `/promos`, `/en/promos` | Archiv aktiver Promos |
| `/promos/placement/<slug>` | Promos einer Platzierung (`noindex`) |
| `/promo/click/<slug>` | zählt den Klick und leitet weiter – nur für aktive Promos innerhalb der Laufzeit, `no-store`, `noindex` |

Platzierungen mit Theme-Hook (`body_start`, `after_header`, `home_content`, `before_footer`) spielen bis zu `max_items` aktive Promos automatisch aus. Frequency-Cap je Besucher über ein HttpOnly-Cookie (`cms_promos_freq`), ohne PHP-Session.

## 3. Core-Integrationen

| Integration | Umsetzung |
|---|---|
| Sitemap | `cms_sitemap_entries`: `/promos` und `/en/promos`, sobald eine Promo live ist |
| SEO | `SEOService::setRequestMeta()` für Titel, Beschreibung, Canonical |
| Systemhinweise | `AdminNoticeService::registerCheck('cms_promos_expired')` |
| Klick-Export | Action `cms_promos_click_export` (Payload: Promo, Platzierung, Ziel, UTM, Sprache); optional Webhook per `CMS\Http\Client::post()` |
| Suche / Datenaufbewahrung / Papierkorb | nicht angebunden: keine Detailseiten, keine personenbezogenen Daten (nur Zähler) |

## 4. Lebenszyklus und Datenbank

| Callback | Verhalten |
|---|---|
| `cms_promos_activate()` | Tabellen anlegen/migrieren, Standardwerte, Option `cms_promos_db_version` |
| `cms_promos_deactivate()` | Daten bleiben erhalten |
| `cms_promos_uninstall()` | Tabellen `promos`, `promo_placements`, `promo_settings` und die Option entfernen |

Indizes: `uniq_slug`, `idx_placement_status (placement_id, status)`, `idx_status_window (status, start_at, end_at)`, `idx_priority (priority, updated_at)`, `idx_hook (status, theme_hook, hook_priority)`. Migration nur bei geänderter `CMS_PROMOS_DB_VERSION`.

## 5. Sicherheit

- `current_user_can('manage_options')` für alle Admin-Seiten; CSRF-Token `promos_admin` für jeden POST, `promos_export` (persistent) für Exporte.
- Webhook ausschließlich über `CMS\Http\Client` (SSRF-Schutz, 2 s Timeout, 4 KB Antwortlimit).
- Ziel-, Bild- und Webhook-URLs nur `http(s)` ohne Zugangsdaten, private oder lokale Hosts; Zusatztext über Tag-Whitelist ohne Event-/Style-Attribute.
- Keine Inline-Skripte, Event-Handler oder `style`-Attribute; Bestätigungen über `data-cms-confirm`; JS-Ausgabe nur per `textContent`.

## 6. Assets

| Datei | Geladen |
|---|---|
| `assets/css/promos-public.css` | `/promos*` oder wenn eine Hook-Platzierung tatsächlich Promos ausspielt |
| `assets/css/promos-admin.css` | nur Promos-Adminseiten (Core-Tokens) |
| `assets/js/promos-admin.js` | nur Seite „Promos“, `defer` |

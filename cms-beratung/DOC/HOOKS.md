# 365CMS | Beratung – Hooks

## Vom Plugin genutzte Core-Hooks

| Hook | Typ | Zweck |
|---|---|---|
| `cms_init` | Action | Schema-Migration (nur bei neuer Version), Frontend-Hooks, Aufbewahrungsregel, Systemhinweis |
| `cms_admin_menu` | Action | Menü `365CMS \| Beratung` mit Unterseiten |
| `register_routes` (9) | Action | `POST /api/cms-beratung/media-upload` |
| `register_routes` (10) | Action | `/beratung/:slug` (+ `/de`, `/en`), `/beratung-vorschau/:id` |
| `head` (18/19) | Action | Frontend-CSS bzw. SEO-Head auf Landingpages |
| `body_end` | Action | `frontend.js` auf Landingpages |
| `body_class` | Filter | Klasse `cms-beratung-page` |
| `cms_sitemap_entries` | Filter | veröffentlichte, indexierbare Landingpages |
| `search_results` | Filter | Treffer vom Typ `beratung` |

Registrierungen: `DataRetentionService::registerRule('cms_beratung_submissions')`, `AdminNoticeService::registerCheck('cms_beratung_new_submissions')`.

## Standalone-Dokument

Die Standalone-Variante gibt ein eigenes HTML-Dokument aus und löst darin die Theme-Hooks `head`, `body_start`, `before_footer` und `body_end` aus, damit Core-Assets, CSP-Laufzeit und Consent-Banner wie auf normalen Seiten geladen werden.

## Lebenszyklus-Funktionen

`cms_beratung_activate()`, `cms_beratung_deactivate()`, `cms_beratung_uninstall()` – vom `PluginManager` aufgerufen.

Das Plugin stellt selbst keine eigenen Actions oder Filter bereit.

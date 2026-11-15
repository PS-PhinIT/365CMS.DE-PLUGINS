# 365CMS | Promos – PHP-API

## `CMS_Promos` (Bootstrap, Singleton)

| Methode | Beschreibung |
|---|---|
| `instance()` | Singleton, registriert alle Hooks |
| `init_plugin()` | `cms_init`: Migration + Systemhinweis |
| `sitemap_entries(mixed $entries): array` | Filter `cms_sitemap_entries` |
| `enqueue_styles()` | Action `head` |

## `CMS_Promos_Installer` (statisch)

| Methode | Beschreibung |
|---|---|
| `maybe_install()` | Migration nur bei abweichender `cms_promos_db_version` |
| `install()` | Tabellen/Spalten/Indizes anlegen, Standardwerte, Version setzen |
| `uninstall()` | Tabellen und Option entfernen |
| `installed_version(): string` | gespeicherte Schema-Version |

## `CMS_Promos_Repository` (Singleton)

Alle Schreibmethoden liefern `['success' => bool, 'message' => string]` bzw. `['success' => false, 'error' => string]`.

| Methode | Beschreibung |
|---|---|
| `get_promos()`, `get_promo(int)`, `get_promo_by_slug(string)` | Lesen (explizite Spaltenlisten) |
| `find_promos(string $status, string $search, int $placementId)` | Filter; `$status` auch `live` oder `expired` |
| `get_active_promos(?string $placementSlug)` | aktive Promos innerhalb der Laufzeit |
| `get_active_promos_for_placement(int $id, int $limit)` | Ausspielung einer Hook-Fläche |
| `save_promo(array $post)`, `delete_promo(int)`, `duplicate_promo(int)` | CRUD; Duplikat als Entwurf |
| `reset_stats(int $id = 0)` | Zähler einer bzw. aller Promos auf 0 |
| `increment_click(int)`, `increment_impressions(array $ids)` | Zähler |
| `get_placements()`, `get_placement(int)`, `get_placement_by_slug(string)` | Platzierungen inkl. `promo_count`/`active_count` |
| `save_placement(array)`, `delete_placement(int)` | CRUD |
| `get_hook_placements(string $hook)` | aktive Platzierungen eines Theme-Hooks |
| `get_settings()`, `save_settings(array)`, `reset_settings()` | Einstellungen |
| `get_dashboard_stats()` | Promos, aktiv, featured, Platzierungen, Impressions, Klicks |
| `count_live_promos()`, `count_expired_active()`, `has_live_hook_placements()` | Kennzahlen/Checks |
| `get_recent_promos(int)`, `get_export_rows()`, `get_last_modified()` | Dashboard, Export, Sitemap |
| `status_label(string)` | deutsche Statusbezeichnung |

## `CMS_Promos_Public_Controller` (Singleton)

| Methode | Beschreibung |
|---|---|
| `register_routes($router)` | öffentliche Routen |
| `archive_page(string $slug = '')` | Archiv bzw. Platzierungsansicht, setzt SEO-Metadaten |
| `redirect_promo(string $slug)` | Klick zählen, `cms_promos_click_export`, Weiterleitung (nur live Promos) |
| `render_*_promos()` | Ausgabe je Theme-Hook |

## `CMS_Promos_Admin_Pages` (statisch)

| Methode | Beschreibung |
|---|---|
| `render_page_dispatcher()` | Menü-Callback; wählt die Unterseite über `$_GET['page']` |
| `page_url(string $slug, array $query = [])` | interne Admin-URL |
| `export_url(string $format)` | Export-Link (`csv`/`json`) mit persistentem Token |
| `status_tone(string)`, `format_date(string)` | Darstellungshelfer der Views |

Konstanten: `ROOT_SLUG = 'promos-dashboard'`, `CAPABILITY = 'manage_options'`.

## Changelog

Siehe [../CHANGELOG.md](../CHANGELOG.md).

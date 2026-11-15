# 365CMS | Beratung – PHP-API

## `CMS_Beratung` (Bootstrap)

`instance()`, `init_plugin()`, `sitemap_entries(mixed): array`, `search_results(mixed $results, mixed $query, mixed $limit, mixed $context): array`.

## `CMS_Beratung_Storage` (Singleton)

| Bereich | Methoden |
|---|---|
| Landingpages | `all_landingpages()`, `get_landingpage(int)`, `get_landingpage_by_slug(string, bool $publicOnly)`, `save_landingpage(array): int`, `update_status(int, string)`, `duplicate_landingpage(int)`, `delete_landingpage(int)`, `suggest_unique_slug()`, `landingpage_counts()` |
| Routing/Index | `resolve_public_slug(string): ?array{slug,standalone}`, `published_index(int)`, `search_published(array $words, int)` |
| Presets | `all_presets()`, `preset(int)`, `save_preset(int $id, string $name, string $description, array $design)`, `delete_preset(int)`, `create_preset_if_missing(array)` |
| FAQs | `m365_faq_config()`, `save_m365_faq_config(array)`, `seed_m365_faqs_if_missing()`, `static default_m365_faq_config()`, `static sanitize_m365_faq_items()` |
| Anfragen | `submissions(int)`, `find_submissions(string $status, int $landingpageId, string $search, int $limit, bool $includeSpam)`, `submission(int)`, `create_submission(array)`, `update_submission(int, string $status, string $note)`, `delete_submissions(array)`, `purge_old_submissions(int $days)`, `count_submissions_older_than(int)`, `submission_counts()`, `static submission_statuses()` |

## `CMS_Beratung_Settings` (statisch)

`defaults()`, `all()`, `save(array)`, `save_missing(array)`, `sanitize_from_post(array)`, Bereinigung: `color()`, `text()`, `slug()`, `public_url()`, `image_url()`; Kataloge: `statuses()`, `templates()`.

## Weitere Klassen

| Klasse | Methoden |
|---|---|
| `CMS_Beratung_Installer` | `install()`, `maybe_install()`, `ensure_for_admin_save()`, `uninstall()`, `stored_version()` |
| `CMS_Beratung_Import_Export` | `export_page()`, `export_json()`, `decode_import_json()`, `sanitize_landingpage_payload()` und `sanitize_*()` je Bereich |
| `CMS_Beratung_Renderer` | `render(array $page, array $formResult)`, `render_standalone_header()`, `safe_html()`, `esc()` |
| `CMS_Beratung_Forms` | `handle_submission(array $page)`, `desired_services()` |
| `CMS_Beratung_SEO` | `output_head(array $page)` |
| `CMS_Beratung_Frontend` | `instance(?Router)` – Routen, Assets, SEO-Head, Vorschau |
| `CMS_Beratung_Admin_Pages` | `dispatch_overview()`, `dispatch_*()` je Unterseite, `render_*()`, `register_admin_routes()`, `handle_media_upload()`, `preview_url(int)`; Konstanten `MENU_SLUG`, `DEFAULT_PAGE_SLUG`, `CAPABILITY` |

# 365CMS | Promos – Datenbank

> Stand 4.0.0 (Schema `CMS_PROMOS_DB_VERSION` = `1.2.0`) · Präfix aus `Database::getPrefix()` · InnoDB · `utf8mb4_unicode_ci`

Die Migration läuft über `CMS_Promos_Installer::maybe_install()` nur, wenn die Option `cms_promos_db_version` (Core-Tabelle `settings`, Autoload) von `CMS_PROMOS_DB_VERSION` abweicht. Fehlende Spalten und Indizes werden über `INFORMATION_SCHEMA` ermittelt und idempotent ergänzt.

## `{prefix}promos`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `placement_id` | INT UNSIGNED NULL | → `promo_placements.id`; beim Löschen der Platzierung auf `NULL` gesetzt |
| `title`, `title_en` | VARCHAR(180) | |
| `slug` | VARCHAR(120), UNIQUE | Klick-Route `/promo/click/<slug>` |
| `teaser`, `teaser_en` | VARCHAR(255) | |
| `content_html`, `content_html_en` | LONGTEXT NULL | Tag-Whitelist, keine Event-/Style-Attribute |
| `target_url`, `image_url` | VARCHAR(500) | nur öffentliche `http(s)`-URLs |
| `button_label`, `button_label_en` | VARCHAR(80) | |
| `status` | VARCHAR(20) | `draft`, `active`, `paused`, `archived` |
| `start_at`, `end_at` | DATETIME NULL | Laufzeit; `NULL` = offen |
| `priority` | INT UNSIGNED | 0–9999, höher zuerst |
| `is_featured` | TINYINT(1) | wird vor anderen ausgespielt |
| `frequency_cap`, `frequency_window_hours` | SMALLINT UNSIGNED | 0 = unbegrenzt; Fenster 1–336 h |
| `utm_source`, `utm_medium` | VARCHAR(80) | |
| `utm_campaign`, `utm_term`, `utm_content` | VARCHAR(120) | |
| `impression_count`, `click_count` | INT UNSIGNED | Zähler, zurücksetzbar |
| `created_at`, `updated_at` | TIMESTAMP | |

Indizes: `uniq_slug (slug)`, `idx_placement_status (placement_id, status)`, `idx_status_window (status, start_at, end_at)`, `idx_priority (priority, updated_at)`.

## `{prefix}promo_placements`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `name` | VARCHAR(180) | |
| `slug` | VARCHAR(120), UNIQUE | `/promos/placement/<slug>` |
| `description` | TEXT NULL | |
| `status` | VARCHAR(20) | `active`, `inactive` |
| `theme_hook` | VARCHAR(50) | `manual`, `body_start`, `after_header`, `home_content`, `before_footer` |
| `hook_priority` | INT | −100 … 1000, kleiner zuerst |
| `max_items` | INT UNSIGNED | 1–24 |
| `created_at`, `updated_at` | TIMESTAMP | |

Indizes: `uniq_slug (slug)`, `idx_hook (status, theme_hook, hook_priority)`.

Standard-Platzierungen beim ersten Anlegen: *Homepage Hero* (`after_header`), *Sidebar CTA* (`before_footer`), *Home Content Highlights* (`home_content`).

## `{prefix}promo_settings`

Schlüssel/Wert-Tabelle (`setting_key` UNIQUE, `setting_value` LONGTEXT), geschrieben per `INSERT … ON DUPLICATE KEY UPDATE`.

| Schlüssel | Standard |
|---|---|
| `archive_title`, `archive_title_en` | „Promotions & Highlights“ |
| `archive_description`, `archive_description_en` | Kurzbeschreibung DE/EN |
| `default_button_label`, `default_button_label_en` | „Mehr erfahren“ / „Learn more“ |
| `default_target_behavior` | `same_tab` (`new_tab`) |
| `click_export_enabled` | `0` |
| `click_export_webhook_url` | leer |

## Optionen (Core-Tabelle `settings`)

| Option | Bedeutung |
|---|---|
| `cms_promos_db_version` | installierte Schema-Version |

## Deinstallation

`cms_promos_uninstall()` → `DROP TABLE IF EXISTS` für alle drei Tabellen und Löschen der Option.

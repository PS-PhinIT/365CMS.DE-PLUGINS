# 365CMS | Beratung – Datenbank

> Stand 3.0.0 (Schema `CMS_BERATUNG_DB_VERSION` = `3`) · Präfix aus `Database::getPrefix()` · InnoDB · `utf8mb4_unicode_ci`

`CMS_Beratung_Installer::maybe_install()` läuft bei `cms_init` nur, wenn die Option `beratung_db_version` (über `OptionStore`) abweicht. Spalten-Migrationen laufen nach dem Anlegen aller Tabellen. Alle Tabellen besitzen `tenant_id` (vorbereitet für Mandantenfilter).

## `{prefix}beratung_landingpages`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK | |
| `internal_title`, `public_title` | VARCHAR(255) | |
| `slug` | VARCHAR(160) | UNIQUE (`tenant_id`, `slug`); `/beratung/<slug>` |
| `meta_title`, `meta_description`, `focus_keyword`, `canonical_url` | VARCHAR | SEO |
| `status` | VARCHAR(24) | `draft`, `published`, `private`, `archived` |
| `template` | VARCHAR(64) | Layout-Vorlage |
| `max_content_width` | INT UNSIGNED | 720–1160 |
| `custom_design_enabled`, `use_global_settings`, `use_global_design` | TINYINT(1) | Design-Quelle (siehe [GLOBAL-DESIGN-MODULE.md](GLOBAL-DESIGN-MODULE.md)) |
| `show_header`, `show_footer`, `show_breadcrumb`, `show_toc`, `show_anchor_nav` | TINYINT(1) | Darstellung |
| `noindex`, `nofollow` | TINYINT(1) | Robots; `noindex` = nicht in Sitemap/Suche |
| `custom_css_class` | VARCHAR(120) | |
| `hero_json`, `contact_json`, `seo_json`, `design_json`, `sections_json` | JSON | Builder-Inhalte (beim Speichern/Import serverseitig bereinigt) |
| `tracking_enabled` | TINYINT(1) | |
| `created_by`, `updated_by`, `created_at`, `updated_at` | | |

Indizes: `idx_slug_tenant` (UNIQUE), `idx_slug`, `idx_status`, `idx_template`, `idx_updated_at`.

## `{prefix}beratung_settings`

Schlüssel/Wert je Mandant (`UNIQUE (tenant_id, setting_key)`). Schlüssel und Standardwerte: `CMS_Beratung_Settings::defaults()` – Design (Farben, Radius, Abstände, Schriftgrößen), Kontakt (`contact_recipient_email`, Texte, `form_storage_enabled`, `notification_enabled`, `sender_copy_enabled`, `honeypot_enabled`), **Datenschutz** (`store_ip_enabled`, `store_user_agent_enabled`, `auto_delete_submissions_enabled`, `retention_days` = 180), SEO- und Tracking-Schalter, zentrale M365-FAQ-Konfiguration.

## `{prefix}beratung_design_presets`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK | |
| `name`, `slug`, `description` | | UNIQUE (`tenant_id`, `slug`) |
| `design_json` | JSON | Farbwerte (`primary_color` … `card_border_color`) |
| `is_system` | TINYINT(1) | 1 = mitgeliefert, schreibgeschützt |

## `{prefix}beratung_form_submissions`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id`, `landingpage_id`, `tenant_id` | BIGINT UNSIGNED | |
| `sender_name`, `sender_email`, `phone`, `company` | | **personenbezogen** → Löschfrist |
| `topic`, `desired_service`, `message` | | |
| `internal_note` | TEXT | Admin-Notiz |
| `consent`, `copy_to_sender` | TINYINT(1) | |
| `payload_json` | JSON | vollständige Formularwerte |
| `ip_address`, `user_agent` | | nur bei aktivierter Option |
| `status` | VARCHAR(24) | `new`, `read`, `in_progress`, `answered`, `done`, `archived` |
| `is_spam` | TINYINT(1) | Honeypot-Treffer (in Listen ausgeblendet) |
| `created_at` | TIMESTAMP | |

Indizes: `idx_landingpage`, `idx_tenant`, `idx_status`, `idx_created_at`.

## Optionen (Core-Tabelle `settings`)

| Option | Bedeutung |
|---|---|
| `beratung_db_version` | installierte Schema-Version |

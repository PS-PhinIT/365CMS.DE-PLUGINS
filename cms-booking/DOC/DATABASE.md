# 365CMS | Buchungen – Datenbank

> Stand 4.0.0 (Schema `CMS_BOOKING_DB_VERSION` = `3`) · Präfix aus `Database::getPrefix()` · InnoDB · `utf8mb4_unicode_ci`

`CMS_Booking_Installer::maybe_install()` läuft bei `cms_init`, arbeitet aber nur, wenn die Option `booking_db_version` (über `OptionStore` gelesen) abweicht. Fremdschlüssel und Indizes werden idempotent ergänzt; Kollisionen werden protokolliert und blockieren die Installation nicht.

## `{prefix}booking_providers`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK | |
| `user_id` | INT UNSIGNED NULL | verknüpftes Benutzerkonto |
| `source_plugin`, `source_id` | VARCHAR(50), INT UNSIGNED | UNIQUE; manuell angelegte Anbieter: `cms-booking` + fortlaufende Nummer |
| `display_name`, `slug` | VARCHAR(255), VARCHAR(150) UNIQUE | `/booking/<slug>` |
| `email`, `phone`, `avatar_url`, `bio` | | Kontakt/Profil |
| `contact_form_id` | INT UNSIGNED NULL | optionales Kontaktformular |
| `timezone`, `currency` | VARCHAR | Standard `Europe/Berlin`, `EUR` |
| `status` | VARCHAR(20) | `active`, `inactive`, `pending` |
| `settings_json` | JSON NULL | |
| `created_at`, `updated_at` | TIMESTAMP | |

## `{prefix}booking_services`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id`, `provider_id` | INT UNSIGNED | FK → Anbieter (`ON DELETE CASCADE`) |
| `title`, `slug` | | UNIQUE (`provider_id`, `slug`) |
| `description` | TEXT | Tag-Whitelist |
| `duration_min`, `buffer_min`, `max_bookings` | INT UNSIGNED | Dauer, Puffer danach, parallele Buchungen |
| `price_cents` | INT UNSIGNED | 0 = kostenlos |
| `location_type` | VARCHAR(30) | `online`, `onsite`, `hybrid` |
| `meeting_url` | VARCHAR(500) | nur `https` |
| `booking_type` | VARCHAR(30) | `confirmation`, `instant`, `request` |
| `contact_template`, `status`, `sort_order` | | |
| `settings_json` | JSON | u. a. `daily_booking_limit` |

## `{prefix}booking_availability`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id`, `provider_id` | INT UNSIGNED | FK → Anbieter (`CASCADE`) |
| `day_of_week` | TINYINT NULL | 0 = Montag … 6 = Sonntag (Wochenplan) |
| `specific_date` | DATE NULL | Einzeltag (Abwesenheit/Sondertermin) |
| `start_time`, `end_time` | TIME | |
| `is_blocked` | TINYINT(1) | 1 = nicht buchbar |

Indizes: `idx_provider`, `idx_day`, `idx_date`, **neu** `idx_provider_date (provider_id, specific_date)`.

## `{prefix}bookings`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id`, `provider_id`, `service_id`, `user_id`, `contact_submission_id` | INT UNSIGNED | FK Anbieter `CASCADE`, Leistung `SET NULL` |
| `customer_name`, `customer_email`, `customer_phone` | | **personenbezogen** → Anonymisierung (`Anonymisiert`, `anonymisiert@invalid`) |
| `booking_date`, `start_time`, `end_time`, `duration_min` | | |
| `location_type`, `meeting_url`, `price_cents`, `currency` | | Kopie aus der Leistung zum Buchungszeitpunkt |
| `status` | VARCHAR(30) | `pending`, `confirmed`, `cancelled`, `completed`, `no_show` |
| `notes`, `internal_notes` | TEXT | Kundennachricht bzw. interne Notiz |
| `ical_uid` | VARCHAR(255) | 128-Bit-Zufallswert, Grundlage des Zugangs-Tokens |
| `reminder_sent` | TINYINT(1) | vom Erinnerungs-Cron gesetzt |
| `cancelled_at`, `confirmed_at`, `completed_at`, `created_at`, `updated_at` | TIMESTAMP | |

Indizes: `idx_provider`, `idx_service`, `idx_user`, `idx_date`, `idx_status`, `idx_email`, `idx_contact_sub`, **neu** `idx_reminder (status, reminder_sent, booking_date)`, `idx_provider_date (provider_id, booking_date, status)`.

## `{prefix}booking_meta`

`booking_id` (FK `CASCADE`), `meta_key`, `meta_value`; UNIQUE (`booking_id`, `meta_key`). Wird bei der Anonymisierung gelöscht.

## `{prefix}booking_settings`

Schlüssel/Wert (`setting_key` UNIQUE). Schlüssel: `admin_email`, `from_name`, `from_email`, `default_duration`, `default_buffer`, `default_timezone`, `default_currency`, `booking_advance_min`, `booking_advance_max`, `booking_min_notice_hours`, `booking_daily_limit`, `cancellation_hours`, `auto_confirm`, `send_reminders`, `reminder_hours`, `primary_color`, **neu** `retention_days` (Standard 730).

## Optionen (Core-Tabelle `settings`)

| Option | Bedeutung |
|---|---|
| `booking_db_version` | installierte Schema-Version |

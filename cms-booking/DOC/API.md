# 365CMS | Buchungen – PHP-API

## Integration für andere Plugins (`CMS_Booking_Integration`)

```php
CMS\Hooks::addAction('booking_register_providers', static function (): void {
    CMS_Booking_Integration::register_provider_type('mein-plugin', [
        'label'            => 'Mein Typ',
        'source_table'     => 'meine_tabelle',   // ohne Präfix, nur [a-z0-9_]
        'name_column'      => 'name',
        'email_column'     => 'email',
        'user_id_column'   => 'user_id',
        'single_route'     => '/mein-bereich/{slug}',
        'contact_template' => 'booking-simple',
    ]);
});

$providerId = CMS_Booking_Integration::sync_provider('mein-plugin', $sourceId);
echo CMS_Booking_Integration::render_booking_button('mein-plugin', $sourceId);
```

| Methode | Beschreibung |
|---|---|
| `register_provider_type(string $plugin, array $config)` | Anbieter-Typ registrieren (Bezeichner werden validiert) |
| `get_provider_types()`, `get_provider_type()`, `is_registered()` | Abfrage |
| `sync_provider(string $plugin, int $sourceId, array $overrides = [])` | Anbieter aus der Quelltabelle anlegen/aktualisieren (liest nur Name, E-Mail, Benutzer-ID) |
| `render_booking_button()`, `render_booking_widget()`, `get_or_create_button()` | Ausgabe-Helfer |

## `CMS_Booking_Bookings`

| Methode | Beschreibung |
|---|---|
| `create(array)`, `update(int, array)`, `get(int)`, `delete(int)` | CRUD (`create` löst `booking_created` aus) |
| `get_all($offset, $limit, $status, $search, $providerId, $from, $to)`, `count($status, $search, $providerId)` | Listen/Filter |
| `confirm()`, `cancel()`, `complete()`, `no_show()` | Statuswechsel mit `booking_status_*` |
| `get_upcoming(int)`, `get_stats()`, `count_stale_pending()` | Dashboard |
| `get_due_reminders(int $hours)`, `mark_reminder_sent(int)` | Erinnerungs-Cron |
| `count_anonymizable(int $days)`, `anonymize_older_than(int $days)` | Aufbewahrungsregel |
| `export_rows(...)` | CSV-Export |
| `get_by_user()`, `get_by_provider_user()` | Member-Bereich |
| `get_meta()`, `set_meta()`, `get_all_meta()` | Meta-Daten |
| `static status_labels()`, `static status_badge_class()` | Darstellung |

## `CMS_Booking_Providers`

`upsert(array)` (Quell-Plugins), `save_manual(array, int $id = 0)` (Admin), `get()`, `get_by_slug()`, `get_by_source()`, `get_by_plugin()`, `get_all()`, `count()`, `count_by_status()`, `set_status()`, `delete()`; Konstante `MANUAL_SOURCE = 'cms-booking'`.

## `CMS_Booking_Services`, `CMS_Booking_Availability`

| Klasse | Methoden |
|---|---|
| Services | `create()`, `update()`, `get()`, `get_by_slug()`, `get_by_provider()`, `get_all()`, `count()`, `delete()`, `static format_price()`, `static format_duration()` |
| Availability | `replace_weekly(int $providerId, array $slots)`, `get_weekly()`, `block_date()`, `get_date_overrides()`, `delete()`, `get_available_slots()`, `is_slot_available()`, `get_available_dates()`, `static day_labels()` |

## `CMS_Booking_Notifications`

Registriert sich auf `booking_created` und `booking_status_*`; `send_due_reminders(): int` für den Cron. Versand über `CMS\Services\MailService`.

## `CMS_Booking_Installer`

`install()`, `maybe_install()`, `uninstall()`, `default_settings()`, `read_settings(bool $refresh = false)`, `write_settings(array)`, `stored_version()`.

## `CMS_Booking_Admin_Pages`

`dispatch()` und `dispatch_*()` (Menü-Callbacks), `render_*()` (AdminRouter-Fallback), `url(string $slug, array $query)`, `export_url(array)`, `format_date()`, `status_tone()`, `settings()`; Konstanten `CAPABILITY`, `PAGES`.

# 365CMS | Buchungen – Hooks

## Vom Plugin genutzte Core-Hooks

| Hook | Typ | Zweck |
|---|---|---|
| `cms_init` (10) | Action | Schema-Migration, Benachrichtigungen, Aufbewahrungsregel, Systemhinweis |
| `cms_init` (50) | Action | `CMS_Booking_Integration::init()` – löst `booking_register_providers` aus |
| `cms_admin_menu` | Action | Menü `365CMS \| Buchungen` mit sechs Unterseiten |
| `register_routes` | Action | öffentliche Routen und Slot-API |
| `member_dashboard_init` | Action | Member-Bereich `booking` (Capability `member`) |
| `head`, `body_end` | Action | CSS/Primärfarbe bzw. JS auf `/booking*` |
| `cms_cron_hourly` | Action | Terminerinnerungen |
| `cms_sitemap_entries` | Filter | `/booking/<anbieter>`; nach Änderungen an Anbietern/Leistungen wird das gespeicherte Sitemap-Bundle verworfen (`SEOService::invalidateSavedSitemaps()`) |
| `cms_public_form_persistent_token` | Filter | meldet für `POST /booking/<anbieter>/<leistung>` das eigene Token (`booking_submit`) an, statt des Core-Einmaltokens `form_guard` |
| `dsgvo_export_data`, `dsgvo_delete_data` | Action | DSGVO-Auskunft und -Löschung |

Registrierungen: `DataRetentionService::registerRule('cms_booking_customers')`, `AdminNoticeService::registerCheck('cms_booking_pending')`.

## Vom Plugin ausgelöste Hooks

| Hook | Typ | Parameter | Zeitpunkt |
|---|---|---|---|
| `booking_register_providers` | Action | – | Andere Plugins registrieren Anbieter-Typen (siehe API.md) |
| `booking_created` | Action | `int $bookingId, array $fields` | nach dem Anlegen einer Buchung – versendet Kunden-, Anbieter- und Admin-Mail |
| `booking_status_confirmed` | Action | `int $bookingId` | nach Bestätigung (Kunden-Mail) |
| `booking_status_cancelled` | Action | `int $bookingId` | nach Stornierung (Kunden- und Anbieter-Mail) |
| `booking_status_completed`, `booking_status_no_show` | Action | `int $bookingId` | nach Abschluss bzw. „nicht erschienen“ |
| `dsgvo_export_data_booking` | Action | `int $userId, array $export` | beim DSGVO-Export |

```php
\CMS\Hooks::addAction('booking_status_confirmed', static function (int $bookingId): void {
    $booking = CMS_Booking_Bookings::instance()->get($bookingId);
    // z. B. Termin in ein externes System übertragen
});
```

## Lebenszyklus-Funktionen

`cms_booking_activate()`, `cms_booking_deactivate()`, `cms_booking_uninstall()` – vom `PluginManager` aufgerufen.

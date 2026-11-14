# 365CMS | Promos – Hooks

## Vom Plugin genutzte Core-Hooks

| Hook | Typ | Zweck |
|---|---|---|
| `cms_init` | Action | Schema-Migration (nur bei neuer Version), Systemhinweis registrieren |
| `cms_admin_menu` | Action | Menü `365CMS \| Promos` und Unterseiten |
| `register_routes` | Action | `/promos`, `/promos/placement/:slug`, `/promo/click/:slug` (+ `/en/…`) |
| `head` | Action | öffentliches CSS (nur Promo-Routen oder aktive Hook-Platzierungen) |
| `body_start`, `after_header`, `home_content`, `before_footer` | Action | automatische Ausspielung der Platzierungen |
| `cms_sitemap_entries` | Filter | `/promos`, `/en/promos` |

Außerdem: `AdminNoticeService::registerCheck('cms_promos_expired', …)`.

## Vom Plugin ausgelöste Hooks

### `cms_promos_click_export` (Action)

Wird bei jedem gültigen Klick über `/promo/click/<slug>` ausgelöst – unabhängig vom Webhook.

```php
\CMS\Hooks::addAction('cms_promos_click_export', static function (array $payload): void {
    // $payload = [
    //   'event' => 'promo_click', 'timestamp' => '2026-11-14T10:00:00+00:00', 'lang' => 'de',
    //   'promo_id' => 3, 'promo_slug' => 'sommer', 'promo_title' => 'Sommeraktion',
    //   'placement_id' => 1, 'target_url' => 'https://…?utm_source=…',
    //   'request_path' => '/promo/click/sommer',
    //   'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '', 'utm_term' => '', 'utm_content' => '',
    // ]
});
```

Das Payload enthält keine personenbezogenen Daten (keine IP, keine Benutzer-ID).

## Lebenszyklus-Funktionen

| Funktion | Aufruf durch |
|---|---|
| `cms_promos_activate()` | `PluginManager` beim Aktivieren |
| `cms_promos_deactivate()` | beim Deaktivieren |
| `cms_promos_uninstall()` | beim Löschen |

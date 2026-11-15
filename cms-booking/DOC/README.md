# 365CMS | Buchungen – Admin & Integration (ab 4.0.0)

> Weitere Dokumente: [DATABASE.md](DATABASE.md) · [HOOKS.md](HOOKS.md) · [API.md](API.md) · [Changelog](../CHANGELOG.md)

> **Plugin:** `cms-booking` · **Version:** 4.0.2 · **Stand:** 2026-11-15
> **Voraussetzungen:** 365CMS 4.0.0 (Plugin-Layout-Vertrag), PHP 8.4.1

## 1. Admin-Oberfläche

Alle Seiten nutzen den Core-Layout-Vertrag (`cms_plugin_workspace_open()` / `cms_plugin_dashboard()`); Callbacks rendern nur Inhalt, die Shell liefert der AdminRouter.

### Kopfzeile

| Element | Inhalt |
|---|---|
| Titel | `365CMS \| Buchungen`, Versions-Badge, Status „n offen“ bzw. „Keine offenen Anfragen“ |
| KPI 1 | Offene Anfragen (Link mit Filter `status=pending`, Ton „warning“) |
| KPI 2 | Termine heute |
| KPI 3 | Termine der nächsten 7 Tage |
| KPI 4 | Aktive Anbieter |

### Plugin-Menü (links)

| Menüpunkt | URL | Inhalt |
|---|---|---|
| Übersicht | `/admin/plugins/booking/booking` | Schnellaktionen, System-Check (Schema, buchbares Angebot, unbearbeitete Anfragen, E-Mail, Erinnerungen, Anonymisierung, Integrationen), anstehende Termine |
| Buchungen | `…/bookings` | Filter (Suche, Status, Anbieter), Statusaktionen, Sammelaktion (bestätigen, stornieren, abschließen, nicht erschienen, löschen), Pagination, CSV-Export, DSGVO-Auskunftsdateien (Download/Löschen); `?view=<id>` Detail mit interner Notiz. Badge: offene Anfragen |
| Anbieter | `…/providers` | Liste, Aktivieren/Deaktivieren, Synchronisieren, Löschen; `?action=new` / `?edit=<id>` Formular |
| Verfügbarkeit | `…/availability?provider_id=<id>` | Wochenplan (Mo–So, bis zu zwei Zeitfenster), Abwesenheiten (Zeitraum, optional Uhrzeit) |
| Leistungen | `…/services` | Suche, Liste; Formular mit Dauer, Puffer, Kapazität, Tageslimit, Preis, Ort, Buchungsart, Meeting-Link |
| Einstellungen | `…/settings` | E-Mail, Standards, Buchungsregeln, Erinnerungen, Primärfarbe, Anonymisierungsfrist; Wartung (Erinnerungen senden, anonymisieren, zurücksetzen) |

Die Slugs sind seit 3.x unverändert; der Core-AdminRouter enthält für `booking` einen Fallback auf `CMS_Booking_Admin_Pages::render_*()`.

### Statusmodell

`pending` (Offen) → `confirmed` (Bestätigt) → `completed` (Abgeschlossen) bzw. `no_show` (Nicht erschienen); `pending`/`confirmed` → `cancelled` (Storniert). Jeder Wechsel löst `booking_status_<status>` aus; Bestätigung und Stornierung senden eine Kunden-Mail.

## 2. Öffentliche Ausgabe

| Route | Methode | Beschreibung |
|---|---|---|
| `/booking/<anbieter>` | GET | Anbieterseite mit Leistungen (Sitemap) |
| `/booking/<anbieter>/<leistung>` | GET/POST | Buchungsformular mit Kalender, CSRF `booking_submit`, Honeypot |
| `/booking/confirm/<id>?token=…` | GET | Bestätigungsseite (HMAC-Token) |
| `/booking/ical/<id>?token=…` | GET | iCal-Download |
| `/api/booking/slots/<anbieter-id>/<datum>` | GET | freie Zeitfenster (JSON) |

Alle Routen auch unter `/en/…`. CSS/JS nur auf diesen Routen; die Primärfarbe der Einstellungen wird per nonce-geschütztem `<style>` als `--booking-primary` gesetzt.

## 3. Core-Integrationen

| Integration | Umsetzung |
|---|---|
| Cron | `cms_cron_hourly` → `CMS_Booking_Notifications::send_due_reminders()` |
| E-Mail | `CMS\Services\MailService::sendDetailed()` (Header `X-365CMS-Test-Source: cms-booking`, Reply-To aus den Einstellungen) |
| Datenaufbewahrung | Regel `cms_booking_customers` (Aktion *anonymisieren*): Name, E-Mail, Telefon, Notizen und Meta abgeschlossener/stornierter/nicht wahrgenommener Termine nach `retention_days` |
| DSGVO | `dsgvo_export_data` schreibt die Buchungen der Person als JSON in die private Ablage (`PrivateStorageService`, `cms-booking/privacy-exports`; Download/Löschen unter Buchungen, Entfernung nach 30 Tagen); `dsgvo_delete_data` anonymisiert sie; `cms_member_export_data` ergänzt die Selbstauskunft (`buchungen`). Zuordnung über das Konto **und** die vom Core gelieferte (bestätigte) E-Mail-Adresse – auch Gastbuchungen |
| Systemhinweise | `cms_booking_pending` – Anfragen, deren Termin unbearbeitet verstrichen ist |
| Sitemap | `cms_sitemap_entries`: aktive Anbieter mit mindestens einer aktiven Leistung |
| Member-Bereich | `PluginDashboardRegistry` Slug `booking`, Capability `member`, eigene Buchungen des Mitglieds |
| Suche / Papierkorb | nicht angebunden (Buchungen sind private Daten; Anbieterseiten sind über die Sitemap auffindbar) |

## 4. Lebenszyklus

| Callback | Verhalten |
|---|---|
| `cms_booking_activate()` | Tabellen, Fremdschlüssel, Indizes und Standardwerte anlegen; Option `booking_db_version` |
| `cms_booking_deactivate()` | keine Datenänderung |
| `cms_booking_uninstall()` | alle sechs Tabellen und die Option entfernen |

## 5. Sicherheit

- Admin: `current_user_can('manage_options')`, CSRF `booking_admin` für jeden POST, persistentes Token `booking_export` für den CSV-Export (Formel-Injektion entschärft).
- Statuswechsel nur über eine Aktions-Whitelist; Zeit- und Datumswerte werden serverseitig validiert.
- Öffentliche Formulare: CSRF, Honeypot, Verfügbarkeits- und Limitprüfung; Zugangs-Token `HMAC-SHA256(id:ical_uid, SECURE_AUTH_KEY|AUTH_KEY)` mit `hash_equals()`.
- Quell-Plugin-Tabellen werden nur über validierte Bezeichner und die konfigurierten Spalten gelesen.
- Keine Inline-Skripte, Event-Handler oder `style`-Attribute in Admin und Frontend; Bestätigungen über `data-cms-confirm`.

## 6. Assets

| Datei | Geladen |
|---|---|
| `assets/css/booking-public.css`, `assets/js/booking-public.js` | nur `/booking*` und die Slot-API, JS mit `defer` |
| `assets/css/booking-admin.css` | nur Booking-Adminseiten (Core-Tokens, Light/Dark) |

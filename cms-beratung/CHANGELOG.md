# Changelog – CMS Beratung

Ältere Versionen (bis 2.9.794) sind in `update.json` dokumentiert.

## 3.0.0 – 2026-11-15

### Kompatibilität

- Voraussetzung 365CMS 4.0.0 und PHP 8.4.1 (`Requires CMS`, `Requires PHP`, `update.json`).
- Lebenszyklus-Callbacks `cms_beratung_activate()`, `cms_beratung_deactivate()`, `cms_beratung_uninstall()` (Deinstallation entfernt alle vier Tabellen und die Versionsoption).
- Entfernt: Abhängigkeit auf `shared/admin/plugin-admin-contract.php`, `cms_plugin_admin_layout_start()`/`renderAdminLayoutStart()` im Callback, `plugin_activated`-Listener, `head`-Hook für Admin-Assets.
- Integrationen: `cms_sitemap_entries` (veröffentlichte, indexierbare Landingpages), `search_results` (Typ `beratung`), `DataRetentionService::registerRule('cms_beratung_submissions')`, `AdminNoticeService::registerCheck('cms_beratung_new_submissions')`.

### Admin

- Einheitliches Plugin-Layout: Kopfzeile `365CMS | Beratung` mit KPIs (Landingpages, veröffentlicht, neue und offene Anfragen); linkes Menü **Übersicht · Landingpages · Formular-Anfragen · M365 FAQs · Design-Presets · Globale Einstellungen · Import & Export · Hilfe** mit Zähler-Badges.
- **Neu: Übersicht** mit Schnellaktionen, System-Check (Schema, veröffentlichte Seiten, Empfänger, Löschfrist, FAQs, Honeypot) und neuesten Anfragen/Landingpages.
- Landingpages: Filter nach Status und Suche; Löschen mit Bestätigung; Aktionen mit Flash-Meldungen.
- **Formular-Anfragen ausgebaut** (bisher nur eine Tabelle): Filter (Status, Landingpage, Suche), Detailansicht mit Status und interner Notiz, Löschen, CSV-Export, alte Anfragen löschen; neue Anfragen werden beim Öffnen als „Gelesen“ markiert.
- **Design-Presets ausgebaut** („Eigene Presets sind im Datenmodell vorbereitet“): eigene Presets anlegen, bearbeiten, löschen und in die globalen Design-Einstellungen übernehmen; Farbfelder ohne `style`-Attribute.
- Globale Einstellungen: neue Gruppe **Datenschutz** (IP/User-Agent speichern, automatische Löschung, Frist) und „Benachrichtigung“ – die Werte existierten, waren aber nicht bearbeitbar; Speichern nach dem PRG-Muster.
- Import & Export: Download als JSON-Datei je Landingpage.
- Vorschau läuft über die öffentliche Route `/beratung-vorschau/{id}` (nur Administratoren, `noindex`, `no-store`) statt eines vollständigen HTML-Dokuments innerhalb der Admin-Shell.

### Sicherheit

- XSS im Builder behoben: Bereichstitel wurden ungeescaped per `innerHTML` eingesetzt (z. B. aus importiertem JSON).
- Alle HTML-Zuweisungen des Builders laufen über eine einzige Senke `setHtml()` mit DOMPurify/Trusted Types; Leeren per `replaceChildren()`; CTA-Vorschau ohne Inline-Style.
- Eingebettetes JSON (Media-/Experten-Konfiguration, JSON-LD) mit `JSON_HEX_TAG`.
- CSV-Export mit persistentem Token und Formel-Schutz; Redirects über `Security::safeLocalPath()`.
- Mailversand ausschließlich über `cms_mail()` (Core-MailService), kein `@mail()`-Fallback.

### Performance

- Öffentliche Routen parametrisiert (`/beratung/:slug`); bisher wurden bei **jedem** Request alle Landingpages inkl. JSON-Spalten geladen, um Routen zu registrieren. Standalone-Slugs werden per `JSON_EXTRACT` aufgelöst.
- `beratung_db_version` über `OptionStore`; keine `SELECT *`; Kennzahlen per Aggregatabfrage; Builder-JS nur im Editor.
- Admin-CSS (`admin.css`, `admin-media.css`, `admin-faq.css`) auf Core-Tokens umgestellt (Light/Dark, 2px Radius).

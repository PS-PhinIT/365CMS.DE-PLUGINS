# Changelog

## 4.1.0 - 2026-11-16

Plugin-Audit gegen 365CMS 4.1.8 (getestet auf 4.1.8, weiterhin lauffähig ab 4.0.0).

- Medien-Verwendungsnachweis (365CMS 4.1) für Promo-Bilder und -Inhalte.
- Fix: Lokale Bildpfade (/uploads/…) werden gespeichert statt still verworfen; ungültige Bild-/Ziel-URLs werden gemeldet.
- Fix: Promos deaktivierter Platzierungen erscheinen nicht mehr; unbekannte Platzierungen liefern 404.
- Sicherheit: HTML-Filter entfernt Event-Handler und href ohne vorangehendes Leerzeichen.
- Speed: Zähler leeren keine Lese-Caches mehr; Hook-Platzierungen einmal je Anfrage.

## 4.0.3 - 2026-11-15

Funktions-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft auf der 365CMS-Testinstallation (Core-`main`).

### Neu

- **FUN-PLG-03 – Sammelaktion in der Promo-Liste:** markierte Promos aktivieren, pausieren, archivieren oder löschen (höchstens 500 je Aufruf, Bestätigung über `data-cms-confirm`); Checkbox „Alle auswählen“ in `promos-admin.js` (CSP-konform). Danach wird das Sitemap-Bundle verworfen.

## 4.0.2 - 2026-11-15

### Sicherheit

- Die Webhook-URL für den Klick-Export (kann ein Token enthalten) wird mit `CryptoService` verschlüsselt gespeichert (Kontext `settings`, Bindung `cms_promos.webhook_url`) und erst beim Versand entschlüsselt. Übernommen aus PR #11.
- Das Einstellungsformular zeigt die gespeicherte URL nicht mehr an. Ein leeres Feld behält den Wert, die Checkbox „Gespeicherte Webhook-URL entfernen“ löscht ihn. Eine ungültige neue URL wird mit einer Fehlermeldung abgelehnt, statt still verworfen zu werden.
- Klartext-Werte aus älteren Versionen bleiben lesbar und werden beim nächsten Speichern mit neuer URL verschlüsselt.

## 4.0.1 - 2026-11-15

Fixes aus dem Integrationstest gegen eine echte 365CMS-4.0.0-Installation (MariaDB, Router, CSP).

### Behoben

- Leerer Slug: Promos und Platzierungen erhielten immer den Slug `promo` (mit Zählersuffix), weil ein leeres Formularfeld den Titel-Fallback übersprang. Der Slug wird jetzt aus Titel bzw. Name abgeleitet.
- Umlaute im Slug werden transliteriert (`ä` → `ae`, `ß` → `ss` …) statt durch Bindestriche ersetzt.
- `plugins.xml`: Der Core speichert das Sitemap-Bundle als Datei und verwirft es nur bei Seiten-/Beitragsänderungen. Nach Speichern/Löschen von Promos und Platzierungen ruft das Plugin jetzt `SEOService::invalidateSavedSitemaps()` auf.

## 4.0.0 - 2026-11-14

### Kompatibilität

- Voraussetzung jetzt 365CMS 4.0.0 und PHP 8.4.1 (`Requires CMS`, `Requires PHP`, `update.json`).
- Lebenszyklus-Callbacks `cms_promos_activate()`, `cms_promos_deactivate()`, `cms_promos_uninstall()` (Deinstallation entfernt Tabellen und Option).
- Öffentliche Archivseiten werden über `cms_sitemap_entries` gemeldet; Archiv setzt Titel, Beschreibung und Canonical über `SEOService`.
- Systemhinweis über `AdminNoticeService::registerCheck()`, wenn aktive Promos ihr Enddatum überschritten haben.

### Admin

- Einheitliches 365CMS-Plugin-Layout (`cms_plugin_workspace_open()`): Kopfzeile mit Versions-/Status-Badge und vier KPI-Karten (Promos, live, Impressions, Klickrate), linkes Plugin-Menü mit Zähler-Badges, Übersicht mit Schnellaktionen, System-Check und zuletzt bearbeiteten Promos.
- Promo-Liste mit Filter-Toolbar (Suche, Status inkl. „live“/„abgelaufen“, Platzierung); Formular als eigene Ansicht.
- Neu: Promo duplizieren, Statistik je Promo bzw. gesamt zurücksetzen, Einstellungen zurücksetzen, Leistungs-Export als CSV (Excel-tauglich, Formel-Injektion entschärft) und JSON.
- UTM-Builder zeigt die finale Ziel-URL als Vorschau mit Kopierfunktion, statt die gespeicherte Ziel-URL zu verändern.
- Lösch- und Rücksetzaktionen mit Bestätigung (`data-cms-confirm`).

### Sicherheit & Performance

- Klick-Webhook ausschließlich über `CMS\Http\Client` (SSRF-Schutz); veraltetes `curl_close()` entfernt.
- Keine PHP-Session mehr für anonyme Besucher (Frequency-Cap nur per Cookie) – Seiten bleiben cachebar.
- Klick-Route leitet nur aktive Promos in ihrer Laufzeit weiter, sendet `no-store`/`noindex`.
- Schema-Prüfung nur bei geänderter DB-Version (bisher 15+ Abfragen pro Request); neue Indizes `idx_hook`, `idx_placement_status`, `idx_status_window`, `idx_priority`; Einstellungen per Upsert; keine `SELECT *`; korrelierte Zählabfragen ersetzt.
- Öffentliches CSS nur auf `/promos` oder wenn eine Hook-Fläche tatsächlich Promos ausspielt (vorher fehlte es auf Seiten mit Hook-Promos).
- Admin-CSS ausschließlich mit Core-Tokens (Light/Dark).

## 3.0.4 - 2026-09-30

### Geändert

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „365CMS | Promos“ (bisher „Promos“), damit die öffentlichen 365CMS-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.

## 3.0.3 - 2026-09-27

### Behoben

- Installer-Fix: Aktivierung scheiterte an SHOW COLUMNS … LIKE ? (MariaDB/MySQL-Syntaxfehler).
- UTM-Builder in promos-admin.js ausgelagert (CSP-konform); aktive Unterseite in Sidebar/Breadcrumb.

### Geändert

- Admin-Oberfläche an das einheitliche, schlichte 365CMS-Plugin-Admin-Design angeglichen: keine Emoji-/Kürzel-Icons mehr, Icon-Buttons mit Tabler-Icons, Menüname ohne `365CMS | `/`365NET | `-Präfix (der Core sortiert Plugins im Abschnitt „Plugin-Erweiterungen“).
- Kompatibilität mit 365CMS 3.4.00 geprüft (Produktiv-CSP mit Nonces und Trusted Types, PHP 8.4); `requires_cms` auf `3.4.00` angehoben.

## 3.0.2 - 2026-05-31

- Admin-Menüeintrag wird in der Core-Sidebar mit `365CMS | ` vorangestellt, damit 365CMS-Plugins gemeinsam sortiert werden.

## 3.0.1 - 2026-05-18

- CSRF-Token-Prüfung und Admin-Redirects für 365CMS v3.x.x gehärtet.
- Ziel-URLs, Richtext-Inhalte und Slugs strenger normalisiert und validiert.
- Public-Archiv semantisch modernisiert, Inline-Styles entfernt und Escaping vereinheitlicht.
- Admin-Oberfläche ruhiger gestaltet und Emoji-Deko durch klare Textlabels ersetzt.

## 3.0.0 - 2026-05-17

- Plugin-Version und Update-Metadaten auf 365CMS 3.0.0 aktualisiert.
- Admin-Redirect-Härtung aus dem bisherigen Unreleased-Zweig als stabiler 3.0.0-Stand dokumentiert.

## [Unreleased] - 2026-04-04

- Admin-Redirects nach schreibenden Aktionen wurden auf feste interne Dashboard-Routen begrenzt.
- Damit ist der Open-Redirect-Befund im Promo-Admin geschlossen.

## 1.1.0 - 2026-03-14

- Automatische Promo-Ausspielung über Theme-Hooks wie `after_header`, `home_content` und `before_footer` ergänzt
- Platzierungen um `theme_hook` und `hook_priority` erweitert
- Admin-UI zeigt Hook-Zuordnungen jetzt direkt in Platzierungen und Promo-Listen an

## 1.0.0 - 2026-03-14

- Erstes vollständiges Plugin-Setup für `cms-promos`
- Promo-, Platzierungs- und Einstellungsseiten im Admin ergänzt
- Datenbankstruktur für Promos, Platzierungen und Settings eingeführt
- Öffentliche Promo-Übersicht und Klick-Redirect-Tracking ergänzt

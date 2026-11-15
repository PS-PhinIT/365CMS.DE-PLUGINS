# Changelog – CMS Beratung

Ältere Versionen (bis 2.9.794) sind in `update.json` dokumentiert.

## 3.0.3 – 2026-11-15

Funktions-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft auf der 365CMS-Testinstallation (Core-`main`).

### Neu

- **FUN-PLG-03 – Sammelaktion für Formular-Anfragen:** markierte Anfragen auf einen Status setzen (Neu, Gelesen, In Bearbeitung, Beantwortet, Erledigt, Archiviert) oder löschen; „Alle auswählen“ über die neue Datei `assets/js/admin-list.js`, die nur auf dieser Seite geladen wird.

## 3.0.2 – 2026-11-15

Sicherheits-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`).

### Sicherheit

- **SEC-PLG-01 – Bild-Upload:** Bisher reichten Magic Bytes (`exif_imagetype()`) bzw. ein `image/*`-MIME-Typ – eine Datei aus JPEG-Header und PHP-Code wurde als `.jpg` gespeichert. Jetzt muss die Datei mit Abmessungen als Bild lesbar sein (`getimagesize()`), ihr Typ muss zur Endung passen und `finfo` muss denselben MIME-Typ melden. Erlaubt sind JPG, PNG, GIF, WebP und SVG (aktive SVG-Inhalte werden weiterhin abgewiesen); BMP und ICO entfallen.
- **SEC-PLG-04 – Import:** Landingpage-Import (Datei oder eingefügter Text) höchstens 2 MB, JSON-Verschachtelung höchstens 64 Ebenen.
- **SEC-PLG-05:** JSON-Antworten des Medien-Uploads mit `JSON_HEX_TAG`/`JSON_HEX_AMP`.

## 3.0.1 – 2026-11-15

Übernommen aus PR #12; geprüft gegen die 365CMS-4.0.0-Testinstallation.

### Sicherheit & Datenschutz

- **Kontaktformular:** Ratenbegrenzung (höchstens 5 Anfragen je 10 Minuten pro Sitzung und IP, Core-`Security::checkRateLimit()`/`checkDbRateLimit()`) und Core-Antispam (`AntispamService`, sofern im Core aktiviert).
- **DSGVO-Auskunft:** Beim Abschließen einer Auskunft unter *Auskunft & Löschen* werden die Anfragen der Person (Abgleich über die E-Mail-Adresse) als JSON-Datei in die private Ablage geschrieben. Herunterladen und Löschen erfolgt unter *Formular-Anfragen*; nach 30 Tagen entfernt der tägliche Cron die Dateien.
- **DSGVO-Löschung:** Die Anfragen der Person werden beim Ausführen eines Löschantrags entfernt.
- **Selbstauskunft:** Die Anfragen erscheinen im Datenexport des Mitgliederbereichs.
- Die gespeicherte IP-Adresse kommt jetzt aus `Security::getClientIp()` (berücksichtigt vertrauenswürdige Proxys) statt aus `REMOTE_ADDR`.

## 3.0.0 – 2026-11-15

### Behoben (Integrationstest)

- **Plugin startete nicht:** Der Guard `if (class_exists('CMS_Beratung', false)) return;` stand vor einer unbedingten Klassendeklaration; PHP registriert solche Klassen schon beim Kompilieren, der Guard war daher immer wahr und das Plugin lud weder Routen noch Admin-Seiten. Die Klasse steht jetzt in einem `if (!class_exists(...))`-Block.
- **Slug:** Ein leeres Slug-Feld ergab immer `beratung` (mit Zählersuffix), weil der Sanitizer den Fallback vor der Titel-Ableitung setzte. Der Slug wird jetzt aus öffentlichem bzw. internem Titel abgeleitet; Umlaute werden transliteriert (`ä` → `ae` …).
- **`plugins.xml`:** Speichern, Statuswechsel und Löschen einer Landingpage verwerfen das gespeicherte Sitemap-Bundle (`SEOService::invalidateSavedSitemaps()`), damit veröffentlichte Seiten sofort in der Sitemap stehen.
- Import ohne Slug meldete fälschlich „Slug-Konflikt erkannt“.
- **Inhaltsverzeichnis (öffentlich):** Zwischen 1100 px und ca. 1800 px Breite war die rechte Inhaltsleiste fast vollständig abgeschnitten (bei 1280 px 36 von 280 px sichtbar): Eine ältere Regel mit `.is-toc-expanded` überstimmte die spätere 280-px-Spalte. Die Spalte bekommt jetzt Leistenbreite + Abstand + 16 px Randabstand; der Inhalt schrumpft entsprechend.
- Editor „Allgemeine Einstellungen“: Eingabefelder neben Feldern mit Hinweistext wurden vertikal gestreckt (`align-content:start`).

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

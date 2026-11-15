# Audit 365CMS-Plugins – Sicherheit, Kompatibilität, Funktionen, Design, Performance

> **Datum:** 2026-11-15 · **Umfang:** ausschließlich die Plugins dieses Repositorys (kein Core, keine Themes) · **Bezug:** 365CMS Core 4.0.0 (`Version::CURRENT`); 4.1.0 ist noch nicht veröffentlicht – auch der aktuelle Core-`main` meldet 4.0.0.
> **Ablauf:** fünf Prüfbereiche, je ein eigener Branch und Pull Request. Dieses Protokoll wird mit jedem PR fortgeschrieben.

| PR | Prüfbereich | Branch | Status |
|---|---|---|---|
| #1 | Sicherheit & bekannte Exploits | `claude/plugin-audit-security` | in Arbeit |
| #2 | Core-Kompatibilität (4.0.0 / Core-`main`), Bugs, falsche Verweise | – | offen |
| #3 | Fehlende Funktionen & unvollständige Workflows | – | offen |
| #4 | Einheitliches Admin-Design & Plugin-Dashboard | – | offen |
| #5 | Speed, Datenbank-Schema & Best Practices | – | offen |

## 1. Inventar

| Plugin (Ordner) | Slug | Menü / Admin-URL | Version vor Audit | PHP-Dateien / Zeilen | JS / CSS (Zeilen) | Tabellen (ohne Präfix) | `plugin.json` |
|---|---|---|---|---:|---:|---|---|
| `cms-beratung` | `cms-beratung` | 365CMS \| Beratung · `/admin/plugins/cms-beratung` | 3.0.1 | 13 / 6.149 | 1.788 / 4.889 | `beratung_landingpages`, `beratung_design_presets`, `beratung_settings`, `beratung_form_submissions` | fehlte → in PR #1 ergänzt |
| `cms-booking` | `cms-booking` | 365CMS \| Buchungen · `/admin/plugins/booking` | 4.0.0 | 21 / 6.739 | 231 / 779 | `bookings`, `booking_providers`, `booking_services`, `booking_availability`, `booking_meta`, `booking_settings` | ja |
| `cms-projects` | `cms-projects` | 365CMS \| Projekte · `/admin/plugins/cms-projects` | 4.0.1 | 18 / 4.873 | 171 / 792 | `projects_projects`, `projects_boards`, `projects_widgets`, `projects_tasks`, `projects_audit_logs` | ja |
| `cms-promos` | `cms-promos` | 365CMS \| Promos · `/admin/plugins/promos-dashboard` | 4.0.2 | 10 / 3.087 | 70 / 404 | `promos`, `promo_placements`, `promo_settings` | ja |
| `365NET-eventsandspeaker` | `cms-365neteventsandspeaker` | 365CMS \| Events & Speaker · `/admin/plugins/365netevents` | 4.0.0 | 21 / 10.672 | 478 / 1.452 | `365net_events`, `365net_event_speakers`, `365net_event_speaker_rel`, `365net_event_settings` | ja |
| `365NET-expertsandcompanie` | `cms-365netexpertsandcompanie` | 365CMS \| Experts & Companies · `/admin/plugins/experts-companie` | 2.0.0 | 24 / 9.871 | 305 / 2.665 | `365net_excomp_experts`, `365net_excomp_companies`, `365net_excomp_settings` | ja |

Alle sechs Plugins verlangen `Requires CMS/Requires at least: 4.0.0` und `Requires PHP: 8.4.1`.

### 1.1 In den Core übernommene Module

Die in 365CMS 4.0.0 fest integrierten Module `cms-contact`, `cms-downloads`, `cms-feed`, `cms-knowledgebase` und `cms-newsletter` liegen **nicht** mehr als Ordner im Repository. Im Katalog `index.json` sind alle fünf korrekt mit `"integrated_in_core": "4.0.0"`, `"install_supported": false` und der Core-Route markiert; `cms-forum` (Ordner entfernt) steht mit `"install_supported": false`. Kein Handlungsbedarf, keine Doppelpflege.

## 2. Prüfmethode

- **Statisch:** Muster-Scans über alle PHP/JS-Dateien (SQL-Interpolation in `ORDER BY`/`LIMIT`/`IN`/`WHERE`, `query()/exec()` mit Variablen, Redirects ohne `safeLocalPath()`, externe Abrufe, Uploads, `eval`/`unserialize`/`extract`, Inline-Handler, `<script>` ohne Nonce, `innerHTML`, `style=`, `json_encode` ohne `JSON_HEX_TAG`, Ausgabe von Request-Werten, Klartext-Secrets) und eine Funktionsanalyse aller Methoden, die `$_POST`/`$_FILES` lesen (CSRF- und Rechteprüfung im Aufrufpfad).
- **Dynamisch auf einer echten 365CMS-4.0.0-Installation** (MariaDB 10.11, Core-Router, Produktiv-CSP; PHP 8.3 mit testbedingten Anpassungen in einer Kopie des Cores, siehe `DOC/audit/PLUGINS-V4-UPGRADE-2026-11-14.md` §5): alle sechs Plugins aktiviert – die beiden `365NET-*`-Plugins dabei **erstmals** auf einer echten Installation.
  - Crawl aller 49 Plugin-Admin-Seiten (Status, PHP-Meldungen, Layout).
  - XSS: Payloads (`<script>…`, `"><img onerror=…>`) in allen Textfeldern der „Neu“-Formulare aller Plugins, `javascript:`-URLs in allen URL-Feldern, HTML/Skript in Editor.js-Blöcken (Events) und im Promo-HTML; Prüfung aller Admin-Listen, öffentlichen Archive und Detailseiten auf unmaskierte Ausgabe.
  - Rechte: alle Admin-Seiten, CSV-Exporte und Upload-APIs als Gast und als Mitglied (Rolle `member`).
  - CSRF: Admin-Aktionen mit falschem Token, öffentliche Formulare mit falschem Token.
  - Uploads: SVG mit `<script>`, `shell.php`, `shell.php.jpg`, Polyglot (JPEG-Header + PHP), vertauschte Endung, BMP – gegen beide Upload-Endpunkte.
  - Missbrauch: Serien-Absendungen der öffentlichen Formulare (Buchung, Beratungsanfrage).
  - Regression: vorhandene Workflow-Tests für Promos, Projects, Booking und Beratung.

## 3. Befunde

Schweregrade: `CRITICAL` · `HIGH` · `MEDIUM` · `LOW`. Zeilennummern beziehen sich auf den Stand vor der Korrektur.

### 3.1 Sicherheit (PR #1)

| ID | Schwere | Plugin | Datei:Zeile | Befund | Status |
|---|---|---|---|---|---|
| SEC-PLG-01 | MEDIUM | `cms-beratung` | `admin/class-admin-pages.php:157`, `:1822` | Der Bild-Upload akzeptierte Dateien, deren erste Bytes wie ein Bild aussehen (`exif_imagetype()`, ersatzweise `image/*` per `finfo`), ohne Abgleich mit der Endung. Eine Datei aus JPEG-Header und PHP-Code wurde als `/uploads/beratung/shell-php.jpg` gespeichert (Test). Keine Ausführung auf korrekt konfigurierten Servern, aber ein Baustein für Angriffe über Fehlkonfiguration oder Include-Lücken. | [x] Behoben in PR #1 – Bild muss mit Abmessungen lesbar sein (`getimagesize()`), Typ muss zur Endung passen, `finfo` muss denselben MIME melden; nur JPG/PNG/GIF/WebP/SVG |
| SEC-PLG-02 | MEDIUM | `cms-booking` | `includes/class-frontend.php:188` | Öffentliches Buchungsformular ohne Ratenbegrenzung. Offene Anfragen (`pending`) blockieren das Zeitfenster (`class-bookings.php:366`), ein Bot konnte also alle Termine eines Anbieters belegen (Denial of Service der Terminvergabe). Honeypot und CSRF-Token waren vorhanden. | [x] Behoben in PR #1 – höchstens 10 Versuche je Stunde pro Sitzung und 10 angelegte Buchungen je Stunde pro IP |
| SEC-PLG-03 | LOW | `cms-beratung`, `365NET-eventsandspeaker` | `templates/landingpage.php:204 ff.`, `class-renderer.php:238`; `templates/single-event.php:134`, `archive-events.php:126` | Öffentliche Templates setzen `style="--var:…"` (CSS-Custom-Properties mit maskierten bzw. farb-/zahlvalidierten Werten). Der Core-`InlineStyleRewriter` überführt sie in nonce-geschützte Klassen, die CSP bleibt gewahrt; der Katalog verlangt trotzdem keine `style`-Attribute. | [ ] Offen – PR #4 |
| SEC-PLG-04 | LOW | `cms-beratung` | `admin/class-admin-pages.php:1936` | Landingpage-Import (Datei oder Text) ohne Größenlimit, `json_decode()` mit Standardtiefe 512 (nur Administratoren). | [x] Behoben in PR #1 – höchstens 2 MB, Tiefe 64 |
| SEC-PLG-05 | LOW | `cms-beratung`, `cms-booking`, `365NET-expertsandcompanie` | `admin/class-admin-pages.php:1818`; `includes/class-frontend.php:452–494`; `includes/class-post-type.php:442` | JSON-Antworten (Medien-Upload, Slot-API) ohne `JSON_HEX_TAG`. Kein ausnutzbarer Pfad gefunden (Content-Type JSON), aber Abweichung vom Projektstandard. | [x] Behoben in PR #1 |
| SEC-PLG-06 | LOW | `365NET-eventsandspeaker` | Events-Formular, Feld `image_url` | `javascript:alert(…)` wird nicht abgewiesen, sondern zu einem relativen Pfad `/javascript:alert(…)` normalisiert. Die Ausgabe ist harmlos, der Nutzer erhält aber keinen Hinweis auf die ungültige URL. | [ ] Offen – PR #3 (Validierung/Fehlermeldungen) |

**Ohne Befund (geprüft):**

- **SQL:** Alle Abfragen nutzen Prepared Statements über `Database`/PDO. Interpolierte Werte sind ausschließlich auf `int` gecastete `LIMIT`/`OFFSET`/Batch-Größen, per `match` aus festen Werten gewählte `ORDER BY`-Klauseln, Platzhalterlisten für `IN (…)` oder Spaltennamen aus festen Kandidatenlisten (`365NET-eventsandspeaker`, Firmenquelle). DDL (`CREATE/ALTER/DROP`) nur mit Präfix und festen Tabellennamen.
- **XSS:** Kein einziger Payload erschien unmaskiert – weder in Admin-Listen noch in öffentlichen Archiven und Detailseiten (Events, Experts, Companies, Promos, Projekte, Buchungsseiten, Startseite mit Promo-Hook). `javascript:`-URLs wurden in allen URL-Feldern verworfen oder entschärft. Keine Ausgabe von `$_GET`/`$_POST` ohne Maskierung.
- **CSRF & Rechte:** Jede schreibende Admin-Aktion prüft ein CSRF-Token und läuft hinter einer Admin-Prüfung (`isAdmin()`, `current_user_can('manage_options')`, `guard()`/`guardPost()`); Exporte per GET verlangen ein persistentes Token. Gast und Mitglied sehen auf keiner der 49 Admin-Seiten Plugin-Inhalte, erhalten keinen CSV-Download und werden an beiden Upload-APIs mit 403 (Experts) bzw. Umleitung (Beratung) abgewiesen. Mitgliederbereiche (`cms-booking`, `cms-projects`) verwenden die gültige Capability `member` und sind rein lesend.
- **CSP:** Keine Inline-Event-Handler, keine `<script>`-Blöcke ohne Nonce (JSON-Konfiguration als `type="application/json"` bzw. `data-config`), `innerHTML` nur in der DOMPurify-/Trusted-Types-Senke von `cms-beratung`. `style=`-Attribute in `cms-booking` stehen nur in E-Mail-Vorlagen (nicht CSP-relevant).
- **SSRF & Redirects:** Externe Abrufe nur über `CMS\Http\Client` (Promos-Webhook, Google-Suche in Events & Speaker). Admin-Redirects über `Security::safeLocalPath()`; die übrigen `Location`-Header bauen ihr Ziel intern (Routensegmente, Bestätigungsseite) bzw. sind der vom Admin gepflegte Promo-Zielort.
- **Secrets:** Google-API-Key (Events & Speaker) und Promo-Webhook-URL werden über `CryptoService` verschlüsselt und im Formular nicht angezeigt; keine weiteren Zugangsdaten in den Plugins.
- **Uploads Experts & Companies:** Endungs-Whitelist, MIME-Abgleich mit der Endung, `getimagesize()`, aktive SVG-Inhalte abgewiesen, zufälliges Namenssuffix – alle Testdateien abgewiesen.
- **Öffentliche Formulare:** `cms-beratung` (Token, Honeypot, Ratenbegrenzung, Core-Antispam) – sechste Anfrage in 10 Minuten abgewiesen.

### 3.2 Weitere Befunde (für PR #2–#5 vorgemerkt)

| ID | Schwere | Plugin | Datei | Befund | Status |
|---|---|---|---|---|---|
| FUN-PLG-01 | MEDIUM | `365NET-eventsandspeaker` | Event speichern | Der Slug wird nicht aus dem Titel abgeleitet: „JS Test Event“ ergab `eintrag-2`, ein weiteres Event `eintrag`. | [ ] Offen – PR #3 |
| DB-PLG-01 | LOW | `365NET-eventsandspeaker`, `365NET-expertsandcompanie` | Schema-Prüfung beim Laden | Beim ersten Laden nach der Aktivierung wird die Einstellungstabelle abgefragt, bevor sie existiert (`Table '…_365net_event_settings' doesn't exist`, `…_365net_excomp_settings`) – Fehlermeldung im PHP-Log. | [ ] Offen – PR #2 |

## 4. Prüfprotokoll PR #1

| Prüfung | Ergebnis |
|---|---|
| `php -l` auf alle geänderten PHP-Dateien | fehlerfrei |
| Crawl der 49 Admin-Seiten aller sechs Plugins | alle 200, keine PHP-Meldungen |
| XSS-Payloads in allen „Neu“-Formularen + Ausgabeprüfung | keine unmaskierte Ausgabe |
| Rechte (Gast, Mitglied) auf Admin-Seiten, Exporte, Upload-APIs | keine Inhalte, keine Downloads, Uploads abgewiesen |
| Upload-Tests nach SEC-PLG-01 | Polyglot, vertauschte Endung, BMP abgewiesen; echtes PNG/JPEG angenommen |
| Buchung nach SEC-PLG-02 | reguläre Buchung inkl. Bestätigungsseite funktioniert; Serienbuchung wird begrenzt |
| Regression Promos, Projects, Booking, Beratung | ohne Fehler; PHP-Log ohne Einträge (außer Mailversand der Sandbox) |

## 5. Versionen nach PR #1

| Plugin | Version |
|---|---|
| `cms-beratung` | 3.0.1 → **3.0.2** (+ neue `plugin.json`) |
| `cms-booking` | 4.0.0 → **4.0.1** |
| `365NET-expertsandcompanie` | 2.0.0 → **2.0.1** |
| übrige | unverändert |

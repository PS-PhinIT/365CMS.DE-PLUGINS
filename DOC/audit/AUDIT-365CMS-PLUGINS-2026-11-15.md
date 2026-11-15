# Audit 365CMS-Plugins – Sicherheit, Kompatibilität, Funktionen, Design, Performance

> **Datum:** 2026-11-15 · **Umfang:** ausschließlich die Plugins dieses Repositorys (kein Core, keine Themes) · **Bezug:** 365CMS Core 4.0.0 (`Version::CURRENT`); 4.1.0 ist noch nicht veröffentlicht – auch der aktuelle Core-`main` meldet 4.0.0.
> **Ablauf:** fünf Prüfbereiche, je ein eigener Branch und Pull Request. Dieses Protokoll wird mit jedem PR fortgeschrieben.

| PR | Prüfbereich | Branch | Status |
|---|---|---|---|
| #1 | Sicherheit & bekannte Exploits | `claude/plugin-audit-security` | [x] gemergt (PS-PhinIT/365CMS.DE-PLUGINS#13) |
| #2 | Core-Kompatibilität (4.0.0 / Core-`main`), Bugs, falsche Verweise | `claude/plugin-audit-compat` | in Arbeit |
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
| DB-PLG-01 | LOW | `365NET-eventsandspeaker`, `365NET-expertsandcompanie` | Schema-Prüfung beim Laden | Beim ersten Laden nach der Aktivierung wird die Einstellungstabelle abgefragt, bevor sie existiert (`Table '…_365net_event_settings' doesn't exist`, `…_365net_excomp_settings`) – Fehlermeldung im PHP-Log. | [x] Behoben in PR #2 (siehe 3.3) |

### 3.3 Core-Kompatibilität (PR #2)

Geprüft gegen den **aktuellen Core-`main`** (`f7c8210d`, `Version::CURRENT` 4.0.0, Schema v25) – das ist der Stand, aus dem 4.1.0 entsteht. Die Testinstallation wurde dafür von einem älteren 4.0.0-Build auf diesen Stand aktualisiert (Upgrade-Pfad mit allen sechs aktiven Plugins) und anschließend mit leerer Datenbank neu aufgesetzt (Erstaktivierung aller Plugins). Die Plugin-Ordner heißen dabei wie die Slugs, so wie sie die Marketplace-Installation anlegt.

| ID | Schwere | Plugin | Datei | Befund | Status |
|---|---|---|---|---|---|
| DB-PLG-02 | MEDIUM | `365NET-expertsandcompanie` | `cms-365NETexpertsandcompanie.php` | Die Hauptdatei war gemischt geschrieben, der Slug ist `cms-365netexpertsandcompanie`. `PluginManager::deletePlugin()` sucht exakt `<slug>.php` – auf Linux nicht gefunden, die Deinstallation lief nie, alle drei Tabellen blieben nach dem Löschen bestehen (Test über die Plugin-Verwaltung). | [x] Behoben in PR #2 – Datei in `cms-365netexpertsandcompanie.php` umbenannt; Löschen entfernt Tabellen und Option |
| DB-PLG-01 | LOW | beide `365NET-*` | `includes/class-database.php` | siehe 3.2 | [x] Behoben in PR #2 – Schema-Version in der Autoload-Option `cms_365neteventsandspeaker_db_version` bzw. `cms_365netexpertsandcompanie_db_version` (`OptionStore`, keine Abfrage je Anfrage); Tabelle wird nur gelesen, wenn sie existiert; Deinstallation entfernt die Option |
| FUN-PLG-02 | MEDIUM | beide `365NET-*` | Repository-Ordner `365NET-eventsandspeaker/`, `365NET-expertsandcompanie/` | Die Ordner heißen nicht wie die Slugs (`cms-365neteventsandspeaker`, `cms-365netexpertsandcompanie`). Wird ein Ordner unverändert nach `CMS/plugins/` kopiert (Git-Checkout, FTP), lautet der Slug `365net-…`; der Core sucht dann `365net_eventsandspeaker_activate()` usw. – ein Funktionsname, der mit einer Ziffer beginnt, kann nicht existieren: Aktivierung, Deaktivierung und Deinstallation laufen nicht (Test), und `deletePlugin()` findet keine Hauptdatei. Marketplace-Installationen (Ordner = Slug) sind nicht betroffen. | [ ] Offen – Entscheidung nötig: Repository-Ordner in die Slugs umbenennen (betrifft `index.json`-Pfade und Deploy-Abläufe) oder als Installationshinweis dokumentieren (in den CHANGELOGs ergänzt) |

**Ohne Befund (geprüft):**

- **Core-APIs:** Jeder Aufruf aus den sechs Plugins auf `CMS\…`-Klassen, -Methoden, -Konstanten und globale Core-Funktionen existiert im aktuellen Core-`main`.
- **Entfernte 3.5.x-APIs** (`buildSidebarPluginIcon()`, `cmsNormalizeSidebarActivePage()`, `isGroupActive()`, `isSlugActive()`, `sidebarChildIcon()`, `sidebarNavTitleHtml()`, `sidebarTopLevelIcon()`, `Database::invalidateFeedCache()`): keine Verwendung.
- **PHP 8.4/8.5:** keine `curl_close()`, `finfo_close()`, `imagedestroy()`, `PDO::MYSQL_ATTR_*`, `FILTER_SANITIZE_STRING`, `strftime()`, `utf8_encode()`, keine implizit nullbaren Parameter; das PHP-Log bleibt über alle Tests leer. (Die Laufzeit selbst ist in der Sandbox PHP 8.3, siehe Testumgebung.)
- **Routing:** `/admin/plugins/<plugin>` und `/admin/plugins/<plugin>/<unterseite>` für alle 49 Seiten (200, kein PHP-Hinweis); `?page=<unterseite>` öffnet in allen sechs Plugins die richtige Unterseite (aktiver Menüpunkt geprüft); unbekannte Unterseiten zeigen den Core-Hinweis. Mitgliederbereich `/member/plugin/booking` und `/member/plugin/projects` als Mitglied 200, unbekannter Bereich 404.
- **Lebenszyklus:** Für `cms-promos`, `cms-projects`, `cms-booking`, `cms-beratung` und – mit Ordner = Slug – beide `365NET-*`-Plugins: Aktivieren legt Tabellen und Versionsoption an, Löschen über die Plugin-Verwaltung ruft `<slug>_uninstall()` auf und entfernt Tabellen und Option.
- **Upgrade-Pfad:** Wechsel von einem älteren 4.0.0-Build auf den Core-`main` mit allen sechs aktiven Plugins ohne Fehler; alle Workflow- und Sicherheitstests danach grün.

**Core-Anbindungen (Ist-Stand, optional):**

| Plugin | Sitemap | Suche | Löschfrist | Systemhinweis | DSGVO / Selbstauskunft |
|---|---|---|---|---|---|
| `cms-beratung` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ |
| `cms-booking` | ✓ | – | ✓ | ✓ | ✓ / – |
| `cms-projects` | ✓ | ✓ | ✓ (Audit-Log) | ✓ | – (keine Personendaten außer Audit) |
| `cms-promos` | ✓ | – | – (keine Personendaten) | ✓ | – |
| `365NET-eventsandspeaker` | ✓ | ✓ | – | ✓ | – / ✓ |
| `365NET-expertsandcompanie` | ✓ | ✓ | – | ✓ | ✓ / – |

Keines der Plugins registriert einen `ContentTypeRegistry`-Typ (optional). Fehlende Anbindungen mit fachlichem Nutzen (Selbstauskunft für Buchungen, Suche für Buchungsanbieter) sind für PR #3 vorgemerkt.

**Core-Befund (außerhalb des Plugin-Umfangs, nicht geändert):** Die mit dem Core gelieferten Editor.js-Werkzeuge `assets/editorjs/link.umd.js`, `image.umd.js` und `attaches.umd.js` verletzen die Produktiv-CSP (`script-src` ohne `unsafe-eval`, `require-trusted-types-for`). Das tritt identisch im Beitrags- und Seiteneditor des Cores auf (`/admin/posts?action=edit`, `/admin/pages?action=edit`) und in den Plugins, die den Editor über `EditorJsAssetService::render()` einbinden (Events & Speaker, Experts & Companies). In der Testinstallation ist die CSP „Report-Only“; im erzwingenden Modus würden die drei Werkzeuge vermutlich nicht laden. Bitte im Core-Repository prüfen.

## 4. Prüfprotokolle

### 4.1 PR #1

| Prüfung | Ergebnis |
|---|---|
| `php -l` auf alle geänderten PHP-Dateien | fehlerfrei |
| Crawl der 49 Admin-Seiten aller sechs Plugins | alle 200, keine PHP-Meldungen |
| XSS-Payloads in allen „Neu“-Formularen + Ausgabeprüfung | keine unmaskierte Ausgabe |
| Rechte (Gast, Mitglied) auf Admin-Seiten, Exporte, Upload-APIs | keine Inhalte, keine Downloads, Uploads abgewiesen |
| Upload-Tests nach SEC-PLG-01 | Polyglot, vertauschte Endung, BMP abgewiesen; echtes PNG/JPEG angenommen |
| Buchung nach SEC-PLG-02 | reguläre Buchung inkl. Bestätigungsseite funktioniert; Serienbuchung wird begrenzt |
| Regression Promos, Projects, Booking, Beratung | ohne Fehler; PHP-Log ohne Einträge (außer Mailversand der Sandbox) |

### 4.2 PR #2

| Prüfung | Ergebnis |
|---|---|
| Statischer Abgleich aller Core-Aufrufe gegen Core-`main` | alle vorhanden |
| Upgrade der Testinstallation auf Core-`main`, danach alle Tests | grün, PHP-Log leer |
| Erstaktivierung aller sechs Plugins auf leerer Datenbank | Tabellen + Versionsoptionen angelegt, PHP-Log leer (vorher: zwei SQL-Fehler, DB-PLG-01) |
| Aktivieren → Deaktivieren → Löschen je Plugin | alle sechs entfernen Tabellen und Option (Experts & Companies vorher nicht, DB-PLG-02) |
| Routing inkl. `?page=` und Mitgliederbereich | wie erwartet |
| Browser (Chromium) über 63 Seiten | keine Plugin-bedingten Fehler; CSP-Verletzungen nur durch Core-Editor.js-Werkzeuge (s. Core-Befund) |
| Regression: Promos, Projects, Booking, Beratung, XSS/Rechte/Uploads, DSGVO | grün |

## 5. Versionen

| Plugin | Version |
|---|---|
| `cms-beratung` | 3.0.1 → **3.0.2** (+ neue `plugin.json`) |
| `cms-booking` | 4.0.0 → **4.0.1** |
| `365NET-expertsandcompanie` | 2.0.0 → **2.0.1** (PR #1) → **2.0.2** (PR #2, Hauptdatei umbenannt) |
| `365NET-eventsandspeaker` | 4.0.0 → **4.0.1** (PR #2) |
| `cms-promos`, `cms-projects` | unverändert |

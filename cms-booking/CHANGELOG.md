# Changelog – CMS Booking

## 4.1.1 – 2026-11-17

- Öffentliche Buchungsseiten nutzen die Inhaltsbreite des Themes statt fest 1100 px.

## 4.1.0 – 2026-11-16

Plugin-Audit gegen 365CMS 4.1.8 (getestet auf 4.1.8, weiterhin lauffähig ab 4.0.0).

- Medien-Verwendungsnachweis (365CMS 4.1): Anbieterbilder und -texte erscheinen in der Mediathek unter „verwendet in …“.
- Sicherheit: Doppelbuchungen bei gleichzeitigen Anfragen verhindert (Sperre je Anbieter).
- Fix: Puffer nach bestehenden Buchungen wird reserviert; ungültige Kalenderdaten und vergangene Zeitfenster werden abgewiesen; Formular nach Validierungsfehler wieder benutzbar; Array-Eingaben führen nicht mehr zu HTTP 500.
- Fix: „Anbieter synchronisieren“ setzt Status, Einstellungen und Slug nicht mehr zurück.
- Fix: Mitgliederbereich zeigt das Icon 📅 statt des Textes „TI-CALENDAR-EVENT“.
- Speed: Öffentliche Buchungsseite 217 → 37 SQL-Abfragen.

## 4.0.2 – 2026-11-15

Funktions-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft auf der 365CMS-Testinstallation (Core-`main`).

### Behoben

- **FUN-PLG-04 – Statuswechsel ohne Prüfung:** `confirm()`, `cancel()`, `complete()` und `no_show()` setzten jeden Status unabhängig vom aktuellen und lösten jedes Mal die Kundenmail aus – ein zweites Bestätigen schickte die Bestätigung erneut, abgeschlossene Termine ließen sich stornieren. Jetzt nur noch `pending → confirmed/cancelled` und `confirmed → completed/no_show/cancelled`; das Update ist an den gelesenen Status gebunden, gleichzeitige Anfragen lösen den Wechsel nur einmal aus.
- **FUN-PLG-05 – DSGVO-Auskunft ohne Ergebnis:** `dsgvo_export_data` löste nur eine Action ohne Abnehmer aus; Auskunft und Löschung berücksichtigten außerdem nur Buchungen mit Konto. Jetzt entsteht eine JSON-Auskunftsdatei in der privaten Ablage (Download/Löschen unter *Buchungen*, Entfernung nach 30 Tagen), und Auskunft wie Löschung erfassen auch Gastbuchungen über die vom Core gelieferte E-Mail-Adresse.

### Neu

- **FUN-PLG-03 – Sammelaktion in der Buchungsliste:** bestätigen, stornieren, abschließen, „nicht erschienen“ oder löschen für markierte Buchungen (höchstens 200 je Aufruf); Benachrichtigungen wie bei der Einzelaktion, nicht mögliche Wechsel werden übersprungen und gemeldet. „Alle auswählen“ über die neue Datei `assets/js/booking-admin.js`.
- **Selbstauskunft:** Buchungen erscheinen im Datenexport des Mitgliederbereichs (`cms_member_export_data`).

## 4.0.1 – 2026-11-15

Sicherheits-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`).

### Sicherheit

- **SEC-PLG-02 – Buchungsformular ohne Ratenbegrenzung:** Offene Anfragen (`pending`) blockieren das Zeitfenster; ein Bot konnte so sämtliche Termine eines Anbieters belegen. Jetzt höchstens 10 Buchungsversuche je Stunde pro Sitzung (`Security::checkRateLimit()`) und 10 angelegte Buchungen je Stunde pro IP (`checkDbRateLimit()`, IP über `Security::getClientIp()`). Meldung auf Deutsch und Englisch.
- **SEC-PLG-05:** JSON-Antworten der Slot-API mit `JSON_HEX_TAG`/`JSON_HEX_AMP`.
- `update.json`: `tested_cms` von `3.4.00` auf `4.0.0` korrigiert.

## 4.0.0 – 2026-11-15

### Behoben

- **Buchungsformular (öffentlich):** Der Core-Router verlangt für öffentliche POSTs ein `form_guard`-Einmaltoken; das Formular sendet ein eigenes Token und wurde mit 403 abgewiesen. Das Plugin meldet die Route `/booking/<anbieter>/<leistung>` über den Filter `cms_public_form_persistent_token` mit seinem Token an.
- **Buchung angemeldeter Mitglieder:** `CMS\Auth::getUser()` existiert in 4.0.0 nicht – das Speichern schlug für eingeloggte Nutzer fehl. Benutzer-ID jetzt über `Auth::isLoggedIn()`/`Auth::instance()->currentUser()`; Fehler beim Speichern werden mit Datei/Zeile protokolliert.
- **`plugins.xml`:** Nach Änderungen an Anbietern und Leistungen wird das gespeicherte Sitemap-Bundle verworfen (`SEOService::invalidateSavedSitemaps()`).
- Validierungsfehler im Admin (`InvalidArgumentException`) landen nicht mehr im PHP-Fehlerprotokoll.
- **E-Mail-Benachrichtigungen:** `CMS_Booking_Notifications` wurde nie instanziiert – Kunden-, Anbieter- und Admin-Mails wurden nicht versendet. Die Klasse wird jetzt bei `cms_init` registriert.
- **Buchungsabschluss:** Nach dem Speichern rief das Frontend die nicht existierende Methode `send_booking_created()` auf; Kunden sahen „Speichern fehlgeschlagen“, obwohl die Buchung angelegt war.
- **Member-Bereich:** Die Spalte „Service“ zeigte immer „—“ (falscher Schlüssel `service_name`); Status jetzt mit deutschen Bezeichnungen.
- **Suche:** `%`/`_` in Suchbegriffen werden maskiert; Zählung und Liste nutzen dieselben Suchfelder.
- **Primärfarbe:** Die Einstellung wird auf den Buchungsseiten angewendet (nonce-geschützter Style-Block).

### Neu

- Einheitliches 365CMS-Plugin-Layout: Kopfzeile mit Version, Status und KPIs (offene Anfragen, heute, 7 Tage, aktive Anbieter), linkes Menü **Übersicht · Buchungen · Anbieter · Verfügbarkeit · Leistungen · Einstellungen**, Übersicht mit Schnellaktionen, System-Check und anstehenden Terminen.
- Anbieter manuell anlegen und bearbeiten (bisher nur über Quell-Plugins); synchronisierte Anbieter: Kontaktdaten, Zeitzone, Währung und Status bearbeitbar.
- Seite **Verfügbarkeit**: Wochenplan mit bis zu zwei Zeitfenstern je Tag, Abwesenheiten für Zeiträume (bisher ohne Admin-Oberfläche).
- Buchungsdetail mit allen Angaben, Meta-Daten und interner Notiz; Statusaktionen kontextabhängig.
- CSV-Export der (gefilterten) Buchungen; Einstellungen zurücksetzen; Erinnerungen und Anonymisierung manuell auslösen.
- Terminerinnerungen über `cms_cron_hourly` (Einstellung „Erinnerungen versenden“ existierte bereits, wurde aber nie ausgeführt).
- Datenaufbewahrung: Regel `cms_booking_customers` anonymisiert Kundendaten alter, abgeschlossener Termine (Standard 730 Tage).
- Systemhinweis `cms_booking_pending` für Anfragen, deren Termin unbearbeitet verstrichen ist; Sitemap-Einträge `/booking/<anbieter>`.
- Lebenszyklus-Callbacks `cms_booking_activate()`, `cms_booking_deactivate()`, `cms_booking_uninstall()`.

### Geändert

- Voraussetzung 365CMS 4.0.0 und PHP 8.4.1; Schema-Version 3 (Indizes `idx_reminder`, `idx_provider_date`).
- Versand über den Core-`MailService` statt `mail()`; die Absenderadresse der Einstellungen wird als Reply-To verwendet.
- Zugangs-Token für Bestätigungsseite und iCal nutzen `SECURE_AUTH_KEY`/`AUTH_KEY`; bereits versendete Links bleiben gültig.
- Alle Admin-Formulare nach dem PRG-Muster mit Flash-Meldungen; Capability `manage_options`; Redirects über `Security::safeLocalPath()`.
- Keine `SELECT *` mehr (auch nicht beim Lesen aus Quell-Plugin-Tabellen); Einstellungen einmal je Request gelesen.
- Admin-Views ohne `style`-Attribute, Admin-CSS nur mit Core-Tokens; `booking-admin.js` und die Admin-Traits entfernt (nicht mehr benötigt); Member-Icon Tabler statt Emoji, Capability `member`.


Alle nennenswerten Änderungen an diesem Plugin werden hier dokumentiert.

## [3.0.8] – 2026-09-30

### Geändert

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „365CMS | Buchungen“ (bisher „Buchungen“), damit die öffentlichen 365CMS-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.

## [3.0.7] – 2026-09-27

### Behoben

- Inline-Skripte und onchange/onclick-Handler durch booking-admin.js mit data-Attributen ersetzt (CSP-konform).
- DSGVO-Export/-Löschung verarbeitet Anfragen ohne Benutzerkonto (user_id NULL) ohne TypeError.

### Geändert

- Admin-Oberfläche an das einheitliche, schlichte 365CMS-Plugin-Admin-Design angeglichen: keine Emoji-/Kürzel-Icons mehr, Icon-Buttons mit Tabler-Icons, Menüname ohne `365CMS | `/`365NET | `-Präfix (der Core sortiert Plugins im Abschnitt „Plugin-Erweiterungen“).
- Kompatibilität mit 365CMS 3.4.00 geprüft (Produktiv-CSP mit Nonces und Trusted Types, PHP 8.4); `requires_cms` auf `3.4.00` angehoben.

## [3.0.6] – 2026-05-31

### Geändert

- Der Booking-Admin-Menüeintrag wird in der Core-Sidebar mit `365NET | ` vorangestellt, damit 365-Network-Plugins gemeinsam sortiert werden.

## [3.0.5] – 2026-05-25

### Fehlerbehebungen

- Der Guard der Hauptdatei wurde von einem selbst-auslösenden `if (class_exists(...)) return;` auf das funktionierende `cms-feed`-Muster `if (!class_exists(...)) { class ... }` umgestellt.
- Dadurch wird `CMS_Booking::instance()` beim Plugin-Laden wieder ausgeführt und der `cms_admin_menu`-Hook für den Booking-Menüeintrag tatsächlich registriert.

## [3.0.4] – 2026-05-25

### Fehlerbehebungen

- Das Admin-Menü lädt die zentralen 365CMS Admin-Menü-Helper nun defensiv, bevor `add_menu_page()` geprüft wird. Dadurch erscheint der Booking-Menüeintrag zuverlässig in der Sidebar.
- Der Parent-Slug wurde auf `booking` vereinheitlicht; Unterseiten heißen `bookings`, `providers`, `services` und `settings`.
- Interne Admin-Tab-Links zeigen jetzt konsistent auf `/admin/plugins/booking/*` und passen zur Member-Dashboard-Admin-URL.

## [3.0.3] – 2026-05-25

### Fehlerbehebungen

- Der Installer legt Booking-Tabellen ohne Inline-Foreign-Keys an und ergänzt Relationen anschließend idempotent über `INFORMATION_SCHEMA`-Prüfungen.
- Foreign-Key-Constraints erhalten prefixsichere Namen wie `fk_bs_booking_services_provider`, damit parallele Installationen mit anderen Tabellenpräfixen keine schemaweiten Namenskollisionen auslösen.
- Einzelne FK-Additionsfehler werden separat protokolliert und blockieren nicht mehr das Erstellen der Tabellen, Seeds und DB-Version.
- `CMS_BOOKING_DB_VERSION` wurde auf `2` angehoben, damit bestehende Installationen die robustere Migration erneut durchlaufen.
- Die Plugin-Hauptdatei schützt Konstanten und Hauptklasse gegen versehentliches erneutes Laden.

## [3.0.2] – 2026-05-25

### Fehlerbehebungen

- Der Installer liest und schreibt die Core-Tabelle `settings` wieder mit den korrekten Spalten `option_name` und `option_value`; dadurch verschwinden die `Unknown column 'setting_value'`-Logs beim DB-Versionscheck.
- Foreign Keys werden beim Anlegen der Booking-Tabellen ohne manuell vergebene Constraint-Namen erstellt, damit MariaDB/MySQL keine schemaweiten Namenskollisionen (`errno: 121`) mehr erzeugt.
- Die Installer-Klasse ist gegen versehentliches erneutes Laden geschützt.

## [3.0.0] – 2026-05-17

### Sicherheitsfixes

- Public-Frontend-Routen senden jetzt `X-Content-Type-Options`, `X-Frame-Options` und `Referrer-Policy`, sofern noch keine Header verschickt wurden.
- Bestätigungs- und iCal-Zugangstoken werden per `hash_hmac('sha256', ...)` erzeugt; der iCal-Link enthält den geprüften Token wieder korrekt.
- Dynamische Provider-Tabellen- und Spaltennamen in der Integration-API werden per Allowlist validiert, bevor sie in SQL-Identifier gelangen.
- iCal-Export normalisiert UID, Dateiname und Mailfelder und sendet zusätzlich `X-Content-Type-Options: nosniff`.
- Das öffentliche Buchungsformular escaped den CSRF-Token im Attributkontext explizit mit `ENT_QUOTES` und `UTF-8`.
- Öffentliche Slot-Buttons und Zusammenfassungen werden jetzt DOM-sicher per `createElement()` und `textContent` statt per `innerHTML` aufgebaut.
- Damit ist der letzte DOM-XSS-Befund im Booking-Frontend bereinigt.
- Das Admin-Bestätigungsmodal wird ebenfalls vollständig per DOM-API erzeugt; `innerHTML` und Hilfs-Escaping-Sinks sind entfernt.
- Das Buchungsformular gibt `data-available-dates` attributsicher aus und lädt Public-CSS/-JS nicht mehr zusätzlich zur zentralen Hook-Ausgabe.
- Öffentliche Buchungs-POSTs schlagen jetzt geschlossen fehl, falls der CMS-Sicherheitsdienst fehlt; Bestätigungs- und iCal-Zugangstoken werden timing-sicher mit `hash_equals()` geprüft.
- Provider- und Bestätigungsseiten laden das Public-CSS nicht mehr doppelt; Admin-Links in neuen Tabs verwenden `rel="noopener noreferrer"`.

### Design & Performance

- Public-Templates verwenden weniger statische Inline-Styles; Formular-Schritte wechseln auf das native `hidden`-Attribut und werden per JS ohne Layout-Inline-Manipulation sichtbar gemacht.
- Provider-, Buchungs- und Bestätigungsansicht wurden von dekorativer Emoji-UI bereinigt, damit sie ruhiger in das PHINIT-Theme fallen.
- Booking-Public-CSS bindet zentrale PHINIT-Tokens ein, ergänzt sichtbare `:focus-visible`-Zustände und stellt 44px-Touch-Targets für Kalender, Slots und Buttons sicher.
- Die Admin-Buchungsübersicht nutzt wiederverwendbare CSS-Klassen für Filter, Aktionen, Meta-Texte und Pagination statt statischer Layout-Inline-Styles.

## [1.0.0] – 2025-06-28

### Hinzugefügt

- **Universelles Buchungssystem** mit Provider-Architektur
- **Hook-basierte Integration** (`booking_register_providers`) für externe Plugins
- Automatische Erkennung und Registrierung von cms-experts, cms-speakers, cms-events, cms-companies
- **6 Datenbank-Tabellen**: `booking_providers`, `booking_services`, `booking_availability`, `bookings`, `booking_meta`, `booking_settings`
- **Admin-Backend** mit 5 Bereichen: Dashboard, Buchungen, Anbieter, Leistungen, Einstellungen
- **Buchungsformular** mit Kalender-Widget, Slot-Auswahl und Step-by-Step-Steuerung
- **Verfügbarkeits-Engine**: Wochenplan, Datums-Overrides, Puffer-Zeiten, Slot-Berechnung
- **Status-Workflow**: Ausstehend → Bestätigt → Abgeschlossen / Storniert / Nicht-Erschienen
- **E-Mail-Benachrichtigungen**: Kundenbestätigung, Status-Updates, Anbieter-Benachrichtigung, Admin-Info
- **iCal-Export** (.ics-Download) und **Google-Kalender-Link**
- **Provider-Übersichtsseite** mit Service-Karten
- **Bestätigungsseite** mit Zusammenfassung und Kalender-Links
- **Booking-Button und -Widget** zum Einbetten auf Single-Pages anderer Plugins
- **4 Buchungs-Kontaktformulare** in cms-contact projiziert (booking-simple, booking-expert, booking-event, booking-service)
- **Slots-API** (`/api/booking/slots/{providerId}/{date}`) für AJAX-Kalender
- **14 konfigurierbare Einstellungen** (E-Mail, Vorlaufzeiten, Stornierungsfrist, Auto-Bestätigung, Erinnerungen, Primärfarbe)
- **DSGVO-Hooks** für Datenexport und -löschung (Anonymisierung)
- Responsive Design für alle Seiten
- CSRF-Schutz und Honeypot in allen Formularen

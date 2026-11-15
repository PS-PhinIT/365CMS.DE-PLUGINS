# Changelog – CMS Booking

Alle nennenswerten Änderungen an diesem Plugin werden hier dokumentiert.

## 4.0.0 – 2026-11-14

### Layout

- Alle Admin-Seiten folgen dem Plugin-Layout-Vertrag 4.0.0: `cms_plugin_workspace_open()` mit Kopfzeile „365CMS | Buchungen“, Versions-/Statusbadge und vier Live-KPIs (offene Anfragen, Termine heute, nächste 7 Tage, aktive Anbieter), linkes Plugin-Menü (Übersicht, Buchungen mit Zähler offener Anfragen, Anbieter, Leistungen, Einstellungen).
- Übersicht über `cms_plugin_dashboard()`: Schnellaktionen, System-Check (Tabellen, Schema-Version, Admin-/Absenderadresse, buchbare Anbieter, Erinnerungen, Löschfrist, Integrationen), zuletzt eingegangene Buchungen, darunter Tabelle „Nächste Termine“.
- Unterseiten einheitlich Header → Meldungen → Toolbar/Filter → Tabelle; das Leistungs-Modal wurde durch eine eigene Bearbeitungsansicht ersetzt.
- Eigene Layout-Shell (`renderAdminLayoutStart`, Header/Sidebar-Fallback, `booking-admin-shell`) und das Laden von `shared/admin/plugin-admin-contract.php` entfernt; Rückfall-Partial `includes/plugin-workspace.php` und `assets/css/cms-plugin-workspace.css` nur für Cores ohne Layout-Helfer.
- `booking-admin.css` neu: nur noch Toolbar, Sammelaktionen, Formular-Raster und Verfügbarkeit, ausschließlich Tokens, Rundung ≤ 2px, keine Inline-Styles und Emojis mehr in den Views.

### Kompatibilität 4.0.0

- Lifecycle über `cms_booking_activate()`, `cms_booking_deactivate()` und `cms_booking_uninstall()`; die Registrierung auf den nicht existierenden Hook `plugin_uninstalled` entfällt (Deinstallation entfernte bisher keine Tabellen).
- Plugin-Header um `Requires CMS`, `Requires at least` und `Requires PHP` ergänzt; `plugin.json` neu.
- Menü-Slugs `booking`, `bookings`, `providers`, `services`, `settings` und die statischen Methoden `render_dashboard|render_bookings|render_providers|render_services|render_settings` (Core-Fallback im AdminRouter) bleiben erhalten; Menü-Icon `ti ti-calendar-event`.
- Mitgliederbereich mit Capability `member`, CSRF über `PluginDashboardRegistry::csrfField('booking')` (`member_plugin_booking`), Kachel mit Zähler anstehender Termine.
- Neu angebunden: `cms_sitemap_entries`, `search_results`, `cms_member_export_data`, `dsgvo_export_data`/`dsgvo_delete_data` mit Konto **und** E-Mail, `DataRetentionService::registerRule()` (`cms-booking.bookings`), `AdminNoticeService::registerCheck()` (offene Anfragen, unvollständige Mail-Konfiguration), Filter `cms_public_form_persistent_token`.
- Mails über `cms_mail()` (Core-MailService/MailQueue) statt `mail()`.
- SEO-Metadaten über `SEOService::setRequestMeta()` mit Canonical; Bestätigungsseite `noindex`.

### Fertiggestellt

- Behoben: Das öffentliche Buchungsformular scheiterte in 4.0.0 an der `form_guard`-Prüfung des Core-Routers (403) – das Formular sendet jetzt beide Tokens, das Plugin meldet sein Token über den Router-Filter.
- Behoben: Benachrichtigungen wurden nie versendet (Klasse nicht initialisiert, Aufruf der fehlenden Methode `send_booking_created()` endete in „Fehler beim Speichern“, obwohl die Buchung angelegt war).
- Behoben: Buchungen angemeldeter Nutzer schlugen wegen `Auth::getUser()` (existiert nicht) fehl.
- Anbieter manuell anlegen und bearbeiten (Kontakt, Zeitzone, Währung, Tageslimit, Beschreibung); Sync aus Quell-Plugins überschreibt diese Felder nicht mehr mit NULL.
- Verfügbarkeit im Admin: Wochenplan mit bis zu zwei Zeitfenstern je Tag, Ausnahmen (Abwesenheit/zusätzliche Zeiten) anlegen und löschen – bisher gab es dafür keine Oberfläche.
- Buchungen: Filter nach Status, Anbieter, Zeitraum und Suche (Name, E-Mail, Telefon, Nr.), Sammelaktionen (bestätigen, abschließen, nicht erschienen, stornieren, zurücksetzen, löschen), Detailansicht mit interner Notiz, CSV-Export der gefilterten Liste.
- Anbieter und Leistungen: Sammelaktionen (aktivieren, deaktivieren, löschen), Filter, Paginierung; Löschen-Button für Anbieter ergänzt.
- Erinnerungs-Mails über `cms_cron_hourly` (Einstellungen „Erinnerungen versenden“/„Stunden vorher“ wurden bisher nie gelesen).
- Selbst-Stornierung auf der Bestätigungsseite und im Mitgliederbereich unter Beachtung der „Stornierungsfrist“ (bisher ungenutzte Einstellung).
- Einstellungen „Standard-Dauer/-Puffer“ befüllen neue Leistungen, „Währung“ gilt für neue Anbieter und Preise, „Primärfarbe“ wirkt (optional) auf den Buchungsseiten; neu: Löschfrist, Rate-Limit, Selbst-Stornierung.
- Sofortbuchungen werden direkt bestätigt angelegt (eine Mail statt zweier).
- Vergangene Zeitfenster und die Mindest-Vorlaufzeit werden im Kalender und in der Slot-API berücksichtigt; Vorlauf-Fenster (min./max. Tage) bestimmt den Kalenderzeitraum.

### Sicherheit

- Alle Admin-POSTs: Capability + CSRF vor jeder Ausgabe, danach PRG (303) mit Meldung; Löschen/Status nur per POST, Bestätigungen über `data-cms-confirm` statt eigenem JS-Dialog.
- Öffentliches Formular: Rate-Limit je IP (`Security::checkDbRateLimit`), Honeypot, Längenbegrenzung, Arrays in POST-Feldern werden verworfen.
- Zugangs-Token für Bestätigung, iCal und Stornierung mit `AUTH_KEY` (3.x-Links bleiben gültig), Bestätigungs- und ICS-Antworten mit `Cache-Control: private, no-store`.
- ICS nach RFC 5545: TEXT-Escaping, quoted CN-Parameter, Zeilenfaltung bei 75 Oktetten, UTC-Zeiten aus der Anbieter-Zeitzone, `METHOD:PUBLISH`, Status je Buchungsstatus, Meeting-URL nur http/https.
- Mails: Betreff/Absendername ohne Steuerzeichen, Absender als quoted-string, alle Platzhalter escaped.
- CSV-Export mit Formel-Injection-Schutz (`= + - @ Tab CR` → `'`), UTF-8 mit BOM, Semikolon.
- Quell-Tabellen der Integrationen werden nur mit validierten, gequoteten Spalten gelesen.

### Performance

- Kalender: Ausnahmen und Buchungen des gesamten Zeitraums werden je mit einer Abfrage geladen (vorher 2 Abfragen je Tag bei 90 Tagen), Tageslimits über gruppierte Zählung statt bis zu drei Abfragen je Tag.
- Schema-Version 3: Indizes `idx_provider_date_status`, `idx_status_date`, `idx_user_date`, `idx_reminder`, `idx_provider_date`, `idx_provider_dow`, `idx_provider_status_sort`, `idx_status_name` (idempotent per `SHOW INDEX`).
- Schema-Prüfung liest die Version über den OptionStore (keine zusätzliche Abfrage je Request); Einstellungen einmal pro Request über `CMS_Booking_Settings`.
- Alle `SELECT *` durch Spaltenlisten ersetzt; Assets mit Versions-Cache-Buster nur auf eigenen Seiten.

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

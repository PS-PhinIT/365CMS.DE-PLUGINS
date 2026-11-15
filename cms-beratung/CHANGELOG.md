# Changelog – CMS Beratung

## 3.0.0 – 2026-11-14

Benötigt 365CMS 4.0.0 und PHP 8.4.1.

### Layout
- Alle Adminseiten folgen dem Plugin-Layout-Vertrag 4.0.0: `cms_plugin_workspace_open()` mit Titel „365CMS | Beratung“, Versions-/Statusbadge (Warnung bei fehlenden Tabellen oder fehlendem Empfänger) und vier Live-KPIs (Landingpages, veröffentlicht, neue Anfragen, Anfragen gesamt).
- Linkes Plugin-Menü: Übersicht, Landingpages, Neue Landingpage, Anfragen (Zähler neuer Anfragen), M365 FAQs, Design Presets, Einstellungen (Gruppe mit Design, Kontaktformular, Datenschutz, SEO, Tracking), Import und Export, Hilfe.
- Neue Übersicht mit `cms_plugin_dashboard()`: Schnellaktionen, System-Check (Tabellen, Schema-Version, Empfänger, Speicherung, Spamschutz, Löschfrist, SEO-Service), zuletzt geänderte Landingpages und neueste Anfragen.
- Unterseiten einheitlich: Header → Meldungen → Filter-Toolbar → Karten/Tabellen (`users-table-container`); eigene Hero-, Karten- und Emoji-Gestaltung entfernt, Icons als Tabler-Icons.
- Admin-CSS neu und auf Tokens (`--cms-plugin-*`/`--tblr-*`) umgestellt, Kantenrundung 2px, Dark Mode über Tokens; `admin-faq.css` und `admin-media.css` in `admin.css` zusammengeführt.
- Ansichten nach `admin/views/` ausgelagert, Vorlagen in `admin/class-admin-defaults.php`.

### Kompatibilität 4.0.0
- Bootstrap-Header mit `Requires CMS`, `Requires at least` und `Requires PHP`; neue `plugin.json`; `update.json` ohne `requires`-Feld.
- Lebenszyklus über `cms_beratung_activate()`, `cms_beratung_deactivate()` und `cms_beratung_uninstall()` (entfernt eigene Tabellen, Schema-Option und DSGVO-Exportdateien) statt des Hooks `plugin_activated`.
- Verweise auf den nicht vorhandenen gemeinsamen Admin-Vertrag (`shared/admin/plugin-admin-contract.php`) und `renderAdminLayoutStart()` entfernt; Admin-Assets werden nur noch in den eigenen Callbacks ausgegeben (bisher global im Hook `head`).
- Meta-Angaben (Titel, Beschreibung, Canonical, Robots, Open Graph, Twitter) über `SEOService::setRequestMeta()`; das Plugin ergänzt nur noch eigenes JSON-LD.
- Sitemap (`cms_sitemap_entries`) und Seitensuche (`search_results`, Typ `beratung`) für veröffentlichte, indexierbare Landingpages.
- DSGVO: `dsgvo_export_data` (JSON-Datei in der privaten Ablage, Download unter Anfragen), `dsgvo_delete_data` und `cms_member_export_data` (Abgleich über die E-Mail-Adresse).
- Löschfrist als Regel `cms_beratung.submissions` in `DataRetentionService` (Standard: nie).
- Systemhinweise über `AdminNoticeService` (fehlende Tabellen, fehlender Empfänger, neue Anfragen).
- Vorschau als eigene Route `/api/cms-beratung/preview` (statt eines vollständigen HTML-Dokuments in der Admin-Shell); alte `?view=cms-beratung-preview`-Links leiten um.

### Fertiggestellt
- Anfragen: Filter (Status, Landingpage, Suche), Pagination, Detailansicht mit Status, interner Notiz und Löschen, Sammelaktionen, CSV-Export (UTF-8 mit BOM, Semikolon, Formel-Injection-Schutz); neue Anfragen werden beim Öffnen auf „Gelesen“ gesetzt.
- Landingpages: Suche, Statusfilter, Sortierung, Pagination, Sammelaktionen (Status, Löschen), JSON-Download je Zeile.
- Eigene Design-Presets anlegen und löschen (bisher nur „im Datenmodell vorbereitet“).
- Einstellungen in Bereiche aufgeteilt; bisher nicht bearbeitbare Optionen (Benachrichtigung, Datenschutz-Metadaten, Löschfrist, FAQ-/Download-/Buchungs-Tracking) sind jetzt im Formular.
- Bisher gespeicherte, aber nie gelesene Optionen werden ausgewertet: Fehlermeldung, Sicherheitsfrage (Rechenaufgabe, global oder je Landingpage), Anfragen im Backend anzeigen, eigene CSS-Klasse erlauben, Theme-Schrift, Löschfrist, Tracking (Matomo/GA4/GTM nach Einwilligung oder DOM-Ereignis `cms-beratung:track`).
- Konfiguration (Einstellungen, FAQs, eigene Presets) als JSON exportieren und importieren.
- Fehler behoben: Häkchen in den globalen Einstellungen ließen sich nicht abwählen (verstecktes Feld „0“ wurde als aktiv gewertet); E-Mail-/Telefon-Ziele von Buttons gingen beim erneuten Speichern verloren.

### Sicherheit
- Admin-Builder ohne `innerHTML`: Vorlagen werden von einem strikten DOM-Builder (`createElement`, `setAttribute`, `replaceChildren`) umgesetzt, Event-Handler, `style` und Skript-URLs werden verworfen; Bereichsnamen werden escaped (vorher ungefiltert eingesetzt).
- JSON-LD und Script-Datenblöcke mit `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` und Nonce; Scripts mit Nonce und `defer`.
- Formular: Ratenbegrenzung (Sitzung und Datenbank, 5 je 10 Minuten), Core-Antispam, Feldlängen, Mails als reiner Text (vorher als HTML interpretiert), Betreff ohne Steuerzeichen, Reply-To nur mit gültiger Adresse.
- Bild-Upload: `finfo`-MIME-Whitelist je Endung, Größenprüfung vor der Inhaltsprüfung, zufällige Dateinamen, strengere SVG-Prüfung (DOCTYPE/ENTITY, `style`, externe Referenzen).
- JSON-Import: höchstens 2 MB, Endung `.json`, MIME-Prüfung, Schema- und Inhaltsprüfung, verständliche Fehlermeldungen; interne IDs werden nicht übernommen.
- Freier HTML-Bereich über den Core-Purifier (Profil `hub`), sonst verschärfter Fallback.
- Alle Schreibaktionen prüfen Admin-Recht und CSRF serverseitig; Redirects mit `Security::safeLocalPath()` und Status 303; Downloads per POST mit Token.

### Performance
- Routenregistrierung liest nur Slugs veröffentlichter Seiten (statt `SELECT *` aller Landingpages bei jedem Request); Standalone-Auflösung ohne Schleife über alle Seiten.
- Explizite Spaltenlisten statt `SELECT *`; FAQ-Konfiguration aus dem gebündelten Einstellungs-Cache (vorher 10 Einzelabfragen); Einstellungen speichern ohne Abfrage je Schlüssel.
- Schema-Prüfungen, Seeds und Migrationen nur bei Versionswechsel (vorher zusätzlich bei jedem Speichern und beim Öffnen von Editor/Presets); Schema-Version über `OptionStore`.
- Schema 4: Indizes `idx_sender_email`, `idx_status_created` (Anfragen) und `idx_status_updated` (Landingpages).
- Löschen alter Anfragen blockweise; `config/global-design.json` einmal pro Request gelesen.

## 2.9.794
- Fix PHP 8.5: Der Bild-Upload im Landingpage-Editor scheiterte am Deprecation-Hinweis von `finfo_close()` vor der JSON-Antwort.

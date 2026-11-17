# Changelog – 365CMS | Experts & Companies

## 2.1.1 – 2026-11-17

- Content-Breite folgt dem Theme: leer = Breite des aktiven Themes. Die zusätzliche feste Grenze von 1160 px entfällt; der alte Standard 1160px wird beim Update einmalig geleert.

## 2.1.0 – 2026-11-16

Plugin-Audit gegen 365CMS 4.1.8 (getestet auf 4.1.8, weiterhin lauffähig ab 4.0.0).

- Medien-Verwendungsnachweis (365CMS 4.1) für Experts und Companies.
- Fix: Öffentliche Karten und Detailseiten zeigen keine Speaker im Entwurf und keine inaktiven Einträge.
- Fix: Suche maskiert LIKE-Platzhalter.

## 2.0.2 – 2026-11-15

Kompatibilitäts-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft gegen den aktuellen Core-`main`.

### Behoben

- **DB-PLG-02 – Deinstallation lief nie:** Die Hauptdatei hieß `cms-365NETexpertsandcompanie.php`, der Slug ist `cms-365netexpertsandcompanie`. Beim Löschen sucht der Core exakt `<slug>.php`; auf Linux (Groß-/Kleinschreibung) fand er die Datei nicht, rief `cms_365netexpertsandcompanie_uninstall()` nicht auf und die drei Tabellen blieben bestehen (Test: Löschen über die Plugin-Verwaltung). Die Datei heißt jetzt `cms-365netexpertsandcompanie.php`; das Löschen entfernt Tabellen und Option.
- **DB-PLG-01:** Vor der ersten Installation wurde die noch fehlende Tabelle `365net_excomp_settings` abgefragt; der Core protokollierte einen SQL-Fehler. Die Schema-Version steht jetzt in der Autoload-Option `cms_365netexpertsandcompanie_db_version` (`OptionStore`); ohne Option und ohne Tabelle liefert `getSettings()` die Standardwerte ohne Abfrage.

## 2.0.1 – 2026-11-15

Sicherheits-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`): Plugin erstmals auf einer echten 365CMS-4.0.0-Installation aktiviert und geprüft (Admin-Seiten, öffentliche Seiten, XSS-, CSRF-, Rechte- und Upload-Tests).

### Sicherheit

- **SEC-PLG-05:** JSON-Antwort des Medien-Uploads mit `JSON_HEX_TAG`/`JSON_HEX_AMP`.

## 2.0.0 – 2026-11-14

### Layout
- Admin läuft über den AdminRouter unter `/admin/plugins/experts-companie` im Layout-Vertrag 4.0.0 (Kopfzeile mit vier Live-KPI, Plugin-Menü links, Inhalt rechts); Menü- und Kopftitel „365CMS | Experts & Companies“, Icon `ti ti-heart-handshake`.
- Jede Unterseite folgt „Header → Toolbar/Filter → Inhalt“: Public Hub, Experts, Companies, Import & Export (neu), Einstellungen sowie die versteckten Seiten „Expert anlegen“/„Company anlegen“ (Menüpunkt Experts bzw. Companies bleibt aktiv).
- Übersicht: Schnellaktionen, Status-/System-Check (Schema-Version, sichtbare Einträge, Hauptmenü-Link, Upload-Ordner, Sitemap/Suche, Events & Speaker) und zuletzt bearbeitete Einträge; Statusbadge „Aktualisierung ausstehend“ bei veraltetem Schema.
- Views nach `admin/views/` ausgelagert; Tabellen in `users-table-container`, keine doppelten Plugin-Header, keine Emojis.
- `assets/css/admin.css` neu: nur Tokens (`--cms-*`/`--tblr-*`), Kantenrundung ≤ 2px, Dark-Mode über Tokens, alles unter `.cms-plugin-admin-content` bzw. `.cms-excomp-`; alte Klassen (Header-, Karten-, Note-Varianten) entfernt.
- Admin-CSS/-JS werden nur noch in den eigenen Callbacks ausgegeben (vorher per Hook `head` bzw. als Inline-`<style>`).

### Kompatibilität 4.0.0
- Eigene Admin-Shell (`renderAdminLayoutStart()`, `admin/partials/admin-menu.php`, `header.php`/`sidebar.php`) und die Routen `/admin/experts-companie/*` entfernt; die alten Adressen leiten weiter (GET 301, POST 307).
- Lifecycle-Funktionen `cms_365NETexpertsandcompanie_activate()/_deactivate()/_uninstall()` ersetzen den Hook `plugin_activated`; Uninstall entfernt nur die eigenen Tabellen und DSGVO-Auskunftsdateien.
- Plugin-Header mit `Requires CMS`, `Requires at least` (4.0.0) und `Requires PHP` (8.4.1); `plugin.json` neu.
- Toter Filter `admin_menu_items` entfernt.

### Fertiggestellt
- Experts/Companies: Löschen (einzeln und als Sammelaktion), Aktivieren/Deaktivieren als Sammelaktion, Status-Filter, Sortierung, Seiten (25 je Seite); Löschen löst Verweise in Companies, Experts und Speakern.
- CSV-Export (UTF-8 mit BOM, `;`, Formel-Injektionsschutz) aus Liste oder „Import & Export“, leere Vorlage, CSV-Import im Format der Seed-Dateien (`defaults/*.csv`) mit Aktualisieren per ID, Ergebniszusammenfassung je Zeile.
- Expert-Feld „E-Mail“ (Kontakt-Button auf der Detailseite, DSGVO-Zuordnung, Speaker-Abgleich im Events-Plugin).
- Status als Auswahlfeld statt Freitext; Pflichtfelder und Fehlermeldungen beim Speichern.
- Sitemap (`cms_sitemap_entries`): drei Verzeichnisse und alle aktiven Detailseiten; Seitensuche (`search_results`): Experts und Companies, alle Suchwörter müssen vorkommen.
- SEO-Meta (Titel, Beschreibung, Canonical, Robots) für Verzeichnisse und Detailseiten; Filteransichten `noindex`; unbekannte Detailseiten mit HTTP 404.
- DSGVO: `dsgvo_export_data` schreibt eine Auskunftsdatei (privater Speicher, Download/Löschen unter „Import & Export“, automatische Entfernung nach 30 Tagen), `dsgvo_delete_data` löscht Expert-Profile und entfernt Company-Kontaktdaten der Adresse.
- Systemhinweis (`AdminNoticeService::registerCheck`) bei fehlenden Tabellen oder ausstehender Migration.
- Fehler behoben: Speichern der Einstellungen hat `schema_version` und nicht gesendete Felder geleert.

### Sicherheit
- Jede Schreibaktion prüft Capability (`manage_options`) und CSRF serverseitig; Downloads mit wiederverwendbarem Token, Redirects nur lokal (`Security::safeLocalPath`, 303).
- Design-Variablen werden geprüft (Längen/Farben) und als Stylesheet `/experts-companie/design-vars` geliefert – keine Inline-`<style>`-Blöcke mehr in Templates; Header-Button-Ziele nur `/pfad` oder http(s).
- Mediathek-Dialog und Bildfelder ohne `innerHTML` (DOM-APIs), Konfiguration über `data-config` statt Script-Block.
- Bild-Upload: Endungs-Whitelist (JPG, PNG, GIF, WebP, AVIF, SVG), finfo-MIME passend zur Endung, Bildprüfung, Dateiname mit Zufallsanteil, SVG ohne Scripts, Event-Handler, Entities oder externe Verweise; `finfo_close()` entfällt.
- CSV-Import: Endung, MIME, 2 MB, 5.000 Zeilen, Kodierungserkennung; Werte laufen durch die Speichervalidierung.

### Performance
- Schema-Prüfung, Seeds und Speaker-Abgleich nur noch bei Versionswechsel (vorher bei jedem Request).
- Archive laden verknüpfte Companies, Experts und Speaker gebündelt (`IN (…)`) statt bis zu fünf Abfragen je Karte.
- Einstellungen einmal je Anfrage; KPI über zwei gebündelte COUNT-Abfragen; `SELECT *` durch Spaltenlisten ersetzt; Indizes `idx_updated`, `idx_email`, `idx_status_updated` per Migration.

## 1.2.0 – 2026-11-14
- Einheitliches Plugin-Layout (PhinIT-Admin-Navigation 3.0) mit KPI-Kopfzeile, Plugin-Menü und Übersicht (Schnellaktionen, Status-Check, zuletzt bearbeitet); Name „PHINIT | Experts & Companies“.

Ältere Versionen: siehe `update.json`.

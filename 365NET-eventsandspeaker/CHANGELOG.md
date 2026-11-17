# 365CMS | Events & Speaker – Changelog

## 4.1.1 – 2026-11-17

- Containerbreite folgt dem Theme: 0 = Breite des aktiven Themes (bisher fest 1160 px). Der alte Standard 1160 wird beim Update einmalig auf 0 gesetzt; eigene Werte bis 1920 px.
- Neue Einstellung „Fallback-Breite px“ (Standard 1160): gilt nur, wenn das Theme keine Breite liefert.

## 4.1.0 – 2026-11-16

Plugin-Audit gegen 365CMS 4.1.8 (getestet auf 4.1.8, weiterhin lauffähig ab 4.0.0).

- Medien-Verwendungsnachweis (365CMS 4.1) für Events und Speaker.
- Fix (Datenverlust): Schema-Update und Synchronisierung löschen keine im Admin angelegten Speaker mehr.
- Fix: Google-Backfill ergänzt nur leere Felder statt gepflegte Biografien zu überschreiben.
- Fix: Zuordnungen von Speakern jenseits der ersten 300 bleiben beim Speichern erhalten (Transaktion).
- Fix: Öffentliche Seiten zeigen keine Speaker im Entwurf und keine inaktiven Firmen/Experts; laufende mehrtägige Events gelten als kommend.

## 4.0.2 – 2026-11-15

Funktions-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft auf der 365CMS-Testinstallation (Core-`main`).

### Behoben

- **FUN-PLG-01 – Slug:** Ein leeres Slug-Feld ergab bei Events und Speakern immer `eintrag`, `eintrag-2` … (`$data['slug'] ?? $title` greift bei leerem String nicht). Der Slug wird jetzt aus Titel bzw. Anzeigenamen gebildet (Umlaute transliteriert, z. B. `slug-test-groesse-2026`).
- **SEC-PLG-06 – Bild-URLs:** Werte mit einem anderen Schema als `http(s)` (`javascript:`, `data:`, `vbscript:` …) wurden zu einem relativen Pfad `/javascript:…` gemacht; jetzt werden sie verworfen (Feld bleibt leer bzw. behält den bisherigen Wert).
- **Seed-Beschreibungen** (`defaults/event-descriptions.php`): 64 sprachliche Fehler korrigiert („ist als Unabhängig vom …“ → „ist als unabhängige Fachveranstaltung vom …“, „am Herbst 2026“ → „im Herbst 2026“ usw.). Gilt für neue Installationen und „Seed neu einspielen“; bestehende Texte bleiben unverändert.

## 4.0.1 – 2026-11-15

Kompatibilitäts-Audit (`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`), geprüft gegen den aktuellen Core-`main`.

### Behoben

- **DB-PLG-01:** Vor der ersten Installation fragte die Schema-Prüfung die noch fehlende Tabelle `365net_event_settings` ab; der Core protokollierte dabei einen SQL-Fehler. Die installierte Schema-Version steht jetzt in der Autoload-Option `cms_365neteventsandspeaker_db_version` (`OptionStore`, keine eigene Abfrage je Anfrage); die Tabelle wird nur gelesen, wenn sie existiert. Bestehende Installationen übernehmen die Version beim nächsten Aufruf, die Deinstallation entfernt die Option.

### Hinweis zur Installation

- Lebenszyklus-Callbacks (`cms_365neteventsandspeaker_activate/_deactivate/_uninstall`) ruft der Core nur auf, wenn der Plugin-Ordner wie der Slug heißt (`plugins/cms-365neteventsandspeaker/`), so wie es die Marketplace-Installation anlegt. Bei einer Kopie des Repository-Ordners `365NET-eventsandspeaker/` lautet der Slug `365net-eventsandspeaker`; dafür kann es keinen gültigen PHP-Funktionsnamen geben (FUN-PLG-02).

## 4.0.0 – 2026-11-14

### Layout
- Alle Admin-Seiten laufen über den Core-AdminRouter (`/admin/plugins/365netevents/…`) statt über eigene Routen mit selbst gezeichneter Shell; `renderAdminLayoutStart()/End()`, der Require von `admin-menu.php` und der wirkungslose Filter `admin_menu_items` entfallen.
- Menü- und Kopfzeilentitel „365CMS | Events & Speaker“, Tabler-Icon `ti ti-calendar-event` statt Emoji.
- Unterseiten Events, Speaker, Kategorien & Tags und Einstellungen einheitlich: Seitenkopf (`admin-page-header`) → Meldungen → Toolbar/Filter → Karte mit Tabelle (`users-table-container`) bzw. Formular. Keine Emojis, Aktions-Icons als Tabler-Icons mit Screenreader-Text.
- Kopfzeile mit Status-Badge (Aktiv / Aktualisierung ausstehend / Datenbankfehler) und vier KPI (kommende veröffentlichte Events, Events gesamt, öffentliche Speaker, Entwürfe), Übersicht mit Schnellaktionen, System-Check (Tabellen, Schema-Version, Eventseite, Entwürfe, Speaker, Experts & Companies, Google-Suche) und zuletzt bearbeiteten Events.
- Admin-CSS neu (`assets/css/admin.css`): nur Tokens, Rundung ≤ 2px, Dark-Mode über die Tokens, alles unter `.cms-plugin-admin-content`; `admin-enhancements.css` und `admin-excomp-forms.css` zusammengeführt und entfernt. CSS/JS werden nur noch auf den eigenen Admin-Seiten ausgegeben (vorher über `head`/`body_end` per Pfadprüfung).
- HTML der Seiten liegt in `admin/views/`.

### Kompatibilität 4.0.0
- Alte Adressen `/admin/365netevents/…` (Lesezeichen, offene Formulare) leiten auf die neuen Plugin-Seiten um (GET 302, POST 307).
- Lebenszyklus über `cms_365neteventsandspeaker_activate()`, `_deactivate()` und `_uninstall()` (entfernt die vier Tabellen); die Registrierung auf `plugin_activated`/`plugin_deactivated` entfällt.
- Plugin-Header mit `Requires CMS: 4.0.0`, `Requires at least: 4.0.0`, `Requires PHP: 8.4.1`; neue Dateien `update.json` und `plugin.json`.
- Sitemap (`cms_sitemap_entries`): `/events`, `/speakers` und alle veröffentlichten Detailseiten. Seitensuche (`search_results`): veröffentlichte Events und Speaker, die alle Suchwörter enthalten (Scopes `events`, `speakers`).
- Selbstauskunft (`cms_member_export_data`): Speaker-Profile mit der E-Mail-Adresse des Mitglieds.
- Systemhinweis (`AdminNoticeService::registerCheck`) bei fehlenden Tabellen oder ausstehender Schema-Aktualisierung.
- SEO-Meta (`SEOService::setRequestMeta`) für Detail- und Übersichtsseiten; die gespeicherten Felder SEO-Titel/-Beschreibung und Social-Bild werden jetzt ausgewertet, `/event-speakers/…` hat `/speakers/…` als Canonical, Such- und Folgeseiten sind `noindex`.
- Verknüpfung mit „365NET Experts & Companies“ nur noch, wenn das Plugin aktiv ist (`PluginManager::isPluginActive()` bzw. dessen Versionskonstante).
- Fehlerprotokoll über `CMS\Logger` (Kanal `plugins`) statt in `logs/` im Web-Root des Plugins.

### Fertiggestellt
- Event- und Speakerlisten: Filter nach Zeitraum (anstehend, vergangen, ohne Datum, alle) bzw. Sortierung, Status und Suche, Sammelaktionen (Veröffentlichen, Entwurf, Löschen), Pagination (25 je Seite), Löschen aus der Liste und im Bearbeitungsformular.
- CSV-Export für Events und Speaker (UTF-8 mit BOM, Semikolon, Schutz vor Formel-Injection) mit den aktiven Filtern.
- Speaker-Backfill über die Google-Suche ist in den Einstellungen startbar (die Route existierte, ein Button fehlte); je Lauf bis zu 25 Speaker ohne Website/LinkedIn, ein Cursor setzt beim nächsten Lauf fort.
- Google API-Key und Suchmaschinen-ID in den Einstellungen; Umgebungsvariablen haben weiterhin Vorrang.
- Feld „Stadt“ im Eventformular; Galerie wird als Liste der Bild-URLs bearbeitet.
- Preisklassen sind fest vorgegeben; die wirkungslosen Eingabefelder dafür entfallen.

### Sicherheit
- Google API-Key verschlüsselt (`CryptoService`, Kontext `settings`); Klartext-Altwerte bleiben lesbar und werden beim nächsten Speichern verschlüsselt, der Key wird nie im Formular ausgegeben.
- Alle Schreibaktionen prüfen Admin-Recht und CSRF, Weiterleitungen nur auf lokale Pfade (`Security::safeLocalPath`, 303). Fehlermeldungen aus `?error=` nur noch aus einer festen Liste.
- `confirm()` und `data-confirm` durch das Core-Attribut `data-cms-confirm` ersetzt; `innerHTML` in `admin.js` durch DOM-APIs (`replaceChildren`, `textContent`); Admin-Script mit CSP-Nonce.
- LIKE-Suchen maskieren `%` und `_`; `IN (...)` über erzeugte Platzhalter.

### Performance
- Fix: Das Speichern der Einstellungen schrieb `schema_version` leer, wodurch beim nächsten Aufruf Schema-Abgleich, Seeds und Verknüpfungslauf erneut liefen.
- Keine `SELECT *` mehr: Listen laden nur die benötigten Spalten (ohne große JSON-Felder), Detail- und Formularabfragen eine explizite Spaltenliste.
- `/events`: Top-Partner-Kennzeichen der verknüpften Firmen in einer Abfrage statt mehrerer Abfragen je Event; Quelltabelle, Spaltenliste und Signal-Abgleich je Anfrage zwischengespeichert.
- Einstellungen je Anfrage nur einmal gelesen; `ensureSchemaForSave()` legt Tabellen nur bei abweichender Schema-Version an.
- Neue Indizes (`status, updated_at`, `start_date`, Speaker `email`, `display_name`) per Migration mit `SHOW INDEX`-Prüfung; Schema-Version 4.0.0.
- Leere `public.js` entfernt; Seed-Beschreibungen liegen in `defaults/event-descriptions.php` (vorher `.php.txt` ohne Zugriffsschutz).

## 3.2.0 – 2026-11-14

- Admin: Einheitliches Plugin-Layout von 365CMS 4.0.0 (PhinIT-Admin-Navigation 3.0). Kopfzeile mit Pluginname, Version, Einzeiler und den vier KPI-Karten rechts daneben; darunter links das Plugin-Menü (Übersicht, Events, Speaker, Kategorien & Tags, Einstellungen) mit aktivem Zustand und Entwürfe-Zähler bei „Events“, rechts der Seiteninhalt. Auf Mobilgeräten steht das Menü als umbrechende Leiste über dem Inhalt.
- Übersicht neu aufgebaut: Schnellaktionen (Neues Event, Neuer Speaker, Vergangene Events, öffentliche Eventseite), Status-/System-Check (Datenbanktabellen, kommende veröffentlichte Events, Entwürfe, öffentliche Speaker, Verknüpfung mit Experts & Companies, Google-Suche für den Speaker-Backfill) und die zuletzt bearbeiteten Events mit Status-Badge. Der doppelte Seitenkopf mit Pluginname und die Bereichs-Kacheln entfallen; die Tabelle „Nächste Events“ bleibt.

## 3.1.0 – 2026-11-14

- Admin: Einheitliches PhinIT-Admin-Layout 2.0 wie 365CMS 4.0.0: Der Seitenleisten-Link öffnet das Dashboard; oben auf allen Plugin-Seiten stehen der Pluginname mit den wichtigsten Kennzahlen (kommende Events, Events gesamt, Speaker, Entwürfe) und ein linksbündiges Menüband mit allen Bereichen (Dashboard, Events, Speaker, Kategorien & Tags, Einstellungen).
- Neues Dashboard unter `/admin/365netevents/dashboard` mit Kennzahlen-Karten, Bereichs-Kacheln und den nächsten fünf Events. Die Event-Liste bleibt unter `/admin/365netevents`; Formular-Ziele, Weiterleitungen und Lesezeichen gelten unverändert. Die doppelten Navigations-Buttons (Speaker, Kategorien & Tags, Einstellungen, Events) in den Seitenköpfen entfallen, das Menüband ersetzt sie.

## 3.0.16 – 2026-11-13

- Admin: Einheitliche PhinIT-Admin-Navigation. Das Plugin erscheint in der Seitenleiste als ein Eintrag ohne aufklappbare Unterpunkte; alle Bereiche sind als Reiter oben auf der Plugin-Seite erreichbar, alte Unterseiten-Adressen leiten weiter. Menü, Reiter und Überschriften ohne Emojis, Aktions-Symbole als Tabler-Icons wie im 365CMS-Core. Geprüft mit 365CMS 4.0.0.

## 3.0.15 – 2026-10-03

- Performance: Schema-Abgleich, Seeds und die automatische Verknüpfung aller Events/Speaker mit Experts & Companies liefen bei jedem Seitenaufruf (rund 1.300 Abfragen inkl. UPDATEs pro Request, auch auf fremden Seiten). ensureSchema() läuft jetzt nur noch, wenn die gespeicherte schema_version nicht zum Code passt; die Verknüpfung bleibt über „Verknüpfungen synchronisieren“ im Admin erreichbar.
- Barrierefreiheit (Mobil): Das Suchfeld im Archiv-Header wurde neben den Buttons auf 23 px Breite gedrückt (WCAG 2.5.8). Unter 640 px stehen Feld und Buttons jetzt untereinander.

## 3.0.14 – 2026-09-30

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „PHINIT | Events & Speaker“ (bisher „365 | Events & Speaker“), damit die PhinIT-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.

# Changelog

## [3.2.2] - 2026-10-25

### Behoben

- Eintrags-Editor: Beim Markieren von Text in den EditorJS-Inhaltsbereichen erschien keine Formatierungsleiste, sodass weder formatiert noch verlinkt werden konnte. Ursache: Der Core (`editor-init.js`) registriert alle Blöcke mit `inlineToolbar: false`. `assets/js/knowledgebase-admin.js` aktiviert die Inline-Toolbar jetzt beim Erzeugen der Editor-Instanzen für Textblöcke (Absatz, Überschrift, Liste, Zitat, Tabelle, Hinweis, Alert, Bildunterschrift, Bild+Text, Akkordeon) mit Fett, Kursiv, Link, Unterstrichen, Markierung, Durchgestrichen und Inline-Code. Das Hyperlink- und das Textfarben-Plugin bleiben außen vor, weil sie unter EditorJS 2.31 fehlerhaft sind (Link wird nicht gesetzt bzw. Popover bricht ab); verlinkt wird über das eingebaute Link-Werkzeug.

## [3.2.1] - 2026-10-25

### Geändert

- Eintrags-Editor: Das Feld „Kategorie“ ist jetzt ein Dropdown mit den unter „Kategorien“ angelegten Einträgen (inkl. „– Keine Kategorie –“). Freitext ist nicht mehr möglich; serverseitig werden beim Speichern aus dem Editor nur bestehende Kategorien akzeptiert. CSV-Import und Standardpakete legen fehlende Kategorien weiterhin automatisch an.
- Detailseite: Unter dem Titel im Kopfbereich wird die Kurzbeschreibung nicht mehr wiederholt; dort erscheint nur noch der Titel (Kategorie-Zeile darüber bleibt).

## [3.2.0] - 2026-10-25

### Geändert

- Detailseite: Die Sidebar hat standardmäßig keinen eigenen Rahmen mehr (vorher Rahmen um die Sidebar **und** um jeden Block) und die Blöcke sitzen bündig ohne doppelten Innenabstand; Blöcke werden nur noch per Trennlinie getrennt.
- Detailseite: Jeder Inhaltsbereich erhält eine eigene Fläche mit Akzentleiste und dezenter, typabhängiger Tönung (Funktionsweise blau, Vorteile grün, Hinweise bernstein, Lizenz/Links neutral, Praxis/Fazit Akzent) sowie optionalem Icon vor der Überschrift.
- Detailseite: Breadcrumb ohne eigene Box.
- Neues Stylesheet `assets/css/knowledgebase-public.css` für Layout-Varianten der öffentlichen KB-Seiten.

### Hinzugefügt

- Design-Tab „Übersichtsseite“: Kopfbereich-Stil, Spaltenanzahl, Karten-Stil, Kurztext-Zeilen, Karten-Hintergrund, Akzentleiste an/aus.
- Design-Tab „Detailseite“: Kopfbereich-Stil, Darstellung der Inhaltsbereiche (Karten/Panel/Minimal), Bereichsfarben und Icons an/aus, Hintergrund der Inhaltsbereiche, Sidebar-Stil (ohne Rahmen/eine Karte/Karte je Block), Sidebar-Position, Sticky-Sidebar, Sidebar-Hintergrund.

## [3.1.1] - 2026-10-25

### Behoben

- CSV-Import und Standardpakete brachen nach dem Update auf 3.1.0 mit „Die Aktion konnte nicht verarbeitet werden.“ ab. Ursache: Die Schema-Migration nutzte `SHOW COLUMNS … LIKE ?`, was mit nativen Prepared Statements (Core: `ATTR_EMULATE_PREPARES = false`) einen SQL-Syntaxfehler auslöst. Dadurch wurden die neuen `content_*`-Spalten nie angelegt und jeder Insert schlug fehl. Die Spaltenprüfung läuft jetzt über `INFORMATION_SCHEMA.COLUMNS`; die Migration wird beim nächsten Seitenaufruf automatisch nachgeholt.

## [3.1.0] - 2026-10-25

### Geändert

- Eintragsinhalte werden nicht mehr als ein einzelnes HTML-Feld gepflegt. Jeder Inhaltsbereich ist jetzt ein eigenes Feld mit eigenem EditorJS-Blockeditor (Core-`EditorJsAssetService`): Allgemeine Infos, Funktionsweise & technische Details, Vorteile & Nutzen, Praxisbeispiel & Einsatz, Hinweise & Best Practices, Lizenz & Verfügbarkeit, Fazit, Weiterführende Links.
- Neue Spalten `content_details`, `content_benefits`, `content_practice`, `content_notes`, `content_license`, `content_conclusion`, `content_links` in `{prefix}kb_entries`; `content` enthält den Bereich „Allgemeine Infos“. Alle Bereiche werden als sanitiertes EditorJS-JSON gespeichert.
- Automatische Migration beim Update: Bestehendes HTML wird anhand der `<h2>`–`<h4>`-Überschriften auf die Bereiche verteilt (Metadaten-Abschnitte und doppelte Kurzbeschreibungen entfallen).
- Öffentliche Detailseite rendert jeden befüllten Bereich als eigene `<section>` mit lokalisierter Überschrift (DE/EN); Site-Table-Shortcodes und Präfix-Platzhalter bleiben erhalten.
- CSV-Standardpakete befüllen die Bereiche direkt; JSON-LD `articleBody` nutzt den Klartext aller Bereiche.
- HTML-Code-Editor samt Snippet-Toolbar und Vorschau aus dem Admin entfernt.

## [3.0.6] - 2026-09-30

### Geändert

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „365CMS | Knowledgebase“ (bisher „Knowledgebase“), damit die öffentlichen 365CMS-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.

## [3.0.5] - 2026-09-27

### Behoben

- CSV-Import ohne PHP-8.4-Deprecation (fgetcsv $escape), Design-Tokens mit CSP-Nonce, Hardreset-Bestätigungen über data-cms-confirm, aktive Unterseite in Sidebar/Breadcrumb, Checkbox-Layout in den Einstellungen korrigiert.

### Geändert

- Admin-Oberfläche an das einheitliche, schlichte 365CMS-Plugin-Admin-Design angeglichen: keine Emoji-/Kürzel-Icons mehr, Icon-Buttons mit Tabler-Icons, Menüname ohne `365CMS | `/`365NET | `-Präfix (der Core sortiert Plugins im Abschnitt „Plugin-Erweiterungen“).
- Kompatibilität mit 365CMS 3.4.00 geprüft (Produktiv-CSP mit Nonces und Trusted Types, PHP 8.4); `requires_cms` auf `3.4.00` angehoben.

## [3.0.4] - 2026-05-31

- Admin-Menüeintrag wird in der Core-Sidebar kurz als `365CMS | KB` angezeigt, damit 365CMS-Plugins gemeinsam sortiert werden.
- Plugin-Metadaten auf Release `3.0.4` aktualisiert.

## [3.0.3] - 2026-05-30

- Admin-Routing für `/admin/plugins/knowledgebase-dashboard/*` gehärtet: Dashboard, Einträge, Kategorien, Editor und Einstellungen erhalten eine Core-Fallback-Zuordnung, falls der Menü-Hook keinen Callback liefert.
- Knowledgebase-Admin-Menü lädt die zentralen Admin-Kompatibilitätsfunktionen nun defensiv, bevor Menüseiten registriert werden.
- Plugin-Metadaten auf Release `3.0.3` aktualisiert.

## [3.0.1] - 2026-05-17

- Öffentliche Such-, Kategorie-, Pagination- und Slug-Parameter werden begrenzt normalisiert.
- Admin-POSTs prüfen CSRF-Tokens fail-closed mit String-Cast und nutzen 303-Redirects.
- Sitemap-XML sendet zusätzlich `X-Content-Type-Options: nosniff`.
- Knowledgebase-Richtext entfernt beim Speichern und Rendern unsichere Attribute, Event-Handler und nicht vertrauenswürdige Link-Ziele.
- Öffentliche Empty-State-Dekoration wurde ruhiger und performancefreundlich ohne Emoji-UI gehalten.
- Plugin-Metadaten auf PHP 8.4 und Release `3.0.1` aktualisiert.

## [3.0.0] - 2026-05-17

- Admin-Redirects nach Schreibaktionen wurden auf feste interne Dashboard-Ziele begrenzt.
- Der Open-Redirect-Befund in `src/Admin/Pages.php` ist damit behoben.
- Öffentliche Tooltip-Elemente werden ohne statisches `innerHTML` per DOM-API aufgebaut.
- Auto-Link-Ausgaben behalten `noopener noreferrer`, wenn Links in neuen Tabs geöffnet werden.

## 1.0.0 - 2026-03-21

- Erstes Release von CMS Knowledgebase
- Admin-Dashboard, Eintragsverwaltung und Einstellungen
- Öffentliche Knowledgebase-Routen `/kb` und `/kb/{slug}`
- Auto-Linking mit Tooltip-Vorschau
- PSR-3-kompatibles Logging via CMS-Logger-Adapter

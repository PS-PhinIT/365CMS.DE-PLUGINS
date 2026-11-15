# Changelog

## 4.0.0 – 2026-11-14

### Layout

- Alle Admin-Seiten nutzen den Plugin-Layout-Vertrag 4.0.0 (`cms_plugin_workspace_open()`/`_close()`): Kopfzeile mit Version, Status und vier Live-KPIs (Promos gesamt, laufende Promos, Impressions, Klicks mit Klickrate), linkes Plugin-Menü (Übersicht, Promos mit Warn-Badge für abgelaufene aktive Promos, Platzierungen, Einstellungen).
- Eigene Sidebar `pr-admin-layout`/`pr-admin-submenu`, `pr-admin-shell`, Info-/Feature-Karten und doppelte KPI-Kacheln entfernt; `renderAdminLayoutStart()/End()` und das Laden von `admin/partials/admin-menu.php` bzw. `../shared/admin/plugin-admin-contract.php` entfallen.
- Übersicht über `cms_plugin_dashboard()`: Schnellaktionen, System-Check (Tabellen, Schema-Version, Hook-Platzierungen, abgelaufene aktive Promos, Promos ohne Ziel-URL, Webhook, Löschfrist), zuletzt bearbeitete Promos; danach Platzierungstabelle.
- Unterseiten einheitlich Header → Meldung → Toolbar/Filter → Tabelle; Formulare als eigene Ansicht (`?action=new`, `?edit=ID`) statt Liste und Formular nebeneinander.
- `promos-admin.css` auf plugin-spezifische Bausteine reduziert (nur Tokens, Rundung ≤ 2px, unter `.cms-plugin-admin-content`); öffentliche CSS-Variablen nicht mehr global auf `:root`.
- Menü-Icon `ti ti-speakerphone`; Rückfall-Layout `includes/plugin-workspace.php` + `assets/css/cms-plugin-workspace.css`.

### Kompatibilität 4.0.0

- Lifecycle-Funktionen `cms_promos_activate()`, `cms_promos_deactivate()`, `cms_promos_uninstall()`; die wirkungslose Registrierung auf den nicht existierenden Hook `plugin_uninstalled` entfernt, der `plugin_activated`-Listener ist durch `cms_promos_activate()` ersetzt. Deinstallation entfernt die drei Plugin-Tabellen und die Option `cms_promos_db_version`.
- Migrationen laufen nur noch bei abweichender Schema-Version (Option `cms_promos_db_version`, gelesen über `OptionStore`), nicht mehr bei jedem Request.
- Plugin-Header mit `Requires CMS`, `Requires at least`, `Requires PHP`; neue `plugin.json`.
- Kein Zugriff mehr auf `../shared/public/plugin-public-i18n.php`; es werden die Core-Helfer `cms_plugin_public_*` mit eingebautem Rückfall genutzt.
- Sitemap: `/promos` und `/en/promos` über `cms_sitemap_entries`, wenn Promos laufen und die Indexierung erlaubt ist.
- Seitensuche: `search_results` liefert laufende Promos, die alle Suchwörter enthalten (Link auf die Karte im Archiv, `_type` = `promos`).
- Datenaufbewahrung: Regel `cms_promos.expired_promos` (abgelaufene/archivierte Promos, Frist in den Einstellungen, Standard 0 = nie).
- Systemhinweise: Warnung bei veraltetem Schema oder aktiviertem Klick-Export ohne Webhook-URL.
- SEO-Meta (Titel, Beschreibung, Canonical, Robots) für das Archiv; Platzierungs-Unterseiten `noindex,follow`, unbekannte Platzierung liefert 404; Klick-URLs mit `X-Robots-Tag: noindex, nofollow`.

### Fertiggestellt

- Promo-Liste mit Suche, Status-Filter (inkl. „läuft gerade“, „abgelaufen“), Platzierungs-Filter, Sortierung, Pagination (25 je Seite) und Sammelaktionen (aktivieren, pausieren, archivieren, Entwurf, Zähler zurücksetzen, löschen).
- Platzierungs-Liste mit Suche, Status-/Hook-Filter und Sammelaktionen (aktivieren, deaktivieren, löschen).
- CSV-Export der Promos (UTF-8 mit BOM, `;`, Formel-Injection-Schutz) und JSON-Export/-Import der Konfiguration (Abgleich über Slug, Größen-/Endungs-/MIME-Prüfung, Ergebniszusammenfassung).
- Validierungsfehler behalten die Formulareingaben; Löschen nur nach Bestätigung (`data-cms-confirm`).
- Einstellung „Archiv indexieren“ und Löschfrist inklusive manueller Bereinigung.
- Hook-Zonen außerhalb von `/promos` erhalten jetzt das öffentliche CSS (vorher nur auf `/promos`).
- Bild-URL akzeptiert auch lokale Pfade (`/uploads/…`); Bilder werden auch im Archiv angezeigt.

### Sicherheit

- Webhook-Aufruf über `CMS\Http\Client` (SSRF-Schutz, Timeouts) statt `curl_*`/`file_get_contents()`; `curl_close()` entfernt.
- HTML-Zusatztext über einen Whitelist-Sanitizer: nur erlaubte Tags, alle Attribute außer geprüftem `href` werden verworfen (vorher Regex-Entfernung einzelner Attribute; `href` mit einfachen Anführungszeichen ging verloren).
- Webhook-URL (kann ein Token enthalten) wird mit `CryptoService` verschlüsselt gespeichert (Kontext `settings`, Bindung `cms_promos.webhook_url`); Klartext-Altwerte bleiben lesbar und werden beim nächsten Speichern verschlüsselt. Das Formular zeigt die URL nie an (Platzhalter „gespeichert – leer lassen zum Beibehalten“, Checkbox „Webhook-URL entfernen“). Validierung beim Speichern, nicht im JSON-Export, Klick-Export ohne URL wird abgelehnt.
- Admin-Aktionen prüfen Admin-Rolle, `manage_options` und CSRF; Exporte mit eigenem Persistent-Token; Redirects nur über `Security::safeLocalPath()` auf die eigene Unterseite.
- Klicks auf Entwürfe liefern 404; Klicks und Impressions von Bots werden nicht gezählt.

### Performance

- Keine PHP-Session mehr bei jedem Request (`bootstrap_session()` entfernt); der Frequency-Cap nutzt nur eine bereits laufende Session, sonst das Cookie – öffentliche Seiten bleiben cachebar (Lazy Sessions des Core).
- Ausspielung: eine Abfrage für alle aktiven Hook-Platzierungen und eine für deren laufende Promos (vorher je Hook und je Platzierung eine Abfrage); Impressions werden mit einer UPDATE-Anweisung am Request-Ende gezählt.
- `SELECT *` durch explizite Spaltenlisten ersetzt; Promo-Zähler je Platzierung per Join statt korrelierter Unterabfrage.
- Neue Indizes (Schema 1.2.0): `idx_status_window`, `idx_placement_status`, `idx_updated` auf `promos`, `idx_hook` auf `promo_placements`.
- Einstellungen per `INSERT … ON DUPLICATE KEY UPDATE` in einer Anweisung; Seeds per `INSERT IGNORE`; eindeutige Slugs mit einer Abfrage.
- Zähler-Updates verändern `updated_at` nicht mehr.

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

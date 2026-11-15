# 365CMS | Events & Speaker

Version: 4.0.1
Modulares 365CMS-Plugin für Events, Messen und verknüpfte Speaker.

## Anforderungen

- 365CMS 4.0.0 oder neuer
- PHP 8.4.1 oder neuer
- Optional: „365NET Experts & Companies“ (Verknüpfung von Events/Speakern mit Firmen und Experten)
- Optional: Google Programmable Search (API-Key + Suchmaschinen-ID) für den Speaker-Backfill

## Features

- Feste Seed-Daten aus der gelieferten CSV-Struktur direkt im Plugin (`defaults/seed-data.php`).
- Beim Aktivieren des Plugins wird ein Full-Refresh ausgeführt: Tabellen/Spalten werden idempotent angelegt und bestehende Seed-Events über `source_nr`, `unique_id` sowie Titel/Datum/Ort-Fingerprint aktualisiert.
- Seed-Beschreibungen werden für bestehende Events ebenfalls nachgezogen (inkl. `excerpt` und `seo_description`), auch wenn ältere Datensätze zuvor keine `source_nr` hatten.
- MySQL-Tabellen mit `unique_id`, `slug`, `status`, `created_at` und `updated_at`.
- Prepared Statements über `CMS\Database`/PDO.
- MariaDB-kompatible Tabellenchecks über den zentralen `Database::tableExists()`-Helper statt `SHOW TABLES LIKE ?`.
- Admin „365CMS | Events & Speaker“ unter `/admin/plugins/365netevents` im Layout-Vertrag von 365CMS 4.0.0: Kopfzeile mit Status und vier KPI-Karten, links das Plugin-Menü (Übersicht, Events mit Entwürfe-Zähler, Speaker, Kategorien & Tags, Einstellungen), rechts der Inhalt. Alte Adressen `/admin/365netevents/…` leiten um.
- Übersicht: Schnellaktionen, System-Check, zuletzt bearbeitete Events und die nächsten fünf Events.
- Listen mit Filtern, Sammelaktionen (Veröffentlichen, Entwurf, Löschen), Pagination und CSV-Export.
- Erweiterter Adminbereich mit vertikal gestapelten UX-Sektionen für Basisdaten, EditorJS-Beschreibung/Bio, Medien, Ort/Online, Kontakt, Kategorien, Tags, Preisklassen, Speaker-Zuordnung und SEO.
- Einstellungen für alle Public-Texte, Farben, Rundungen, Containerbreite sowie Header-/Footer-Abstände.
- Backfill für bestehende Event-Beschreibungen sowie Speaker-Enrichment auf Basis von Google-Custom-Search-Treffern (je Lauf bis zu 25 Speaker). API-Key und Suchmaschinen-ID über Umgebungsvariablen (`CMS_365NETEVENTS_GOOGLE_API_KEY`, `CMS_365NETEVENTS_GOOGLE_CSE_ID`, Vorrang) oder in den Einstellungen (Key verschlüsselt über den CryptoService).
- Sitemap-Einträge, Treffer in der Seitensuche (`events`, `speakers`), SEO-Meta je Seite, Systemhinweise im Admin.
- Eventübersicht zeigt standardmäßig nur den aktuellen Monat; vergangene Events werden erst über den Public-Button `Vergangene Events anzeigen` geladen.
- Public-Content setzt keinen eigenen Seitenhintergrund mehr, damit der Theme-Hintergrund erhalten bleibt.
- EditorJS-JSON für Event-Beschreibungen (`description_json`) und Speaker-Bios (`bio_json`) mit Core-Renderer-Fallback im Frontend.
- Umfangreiche Metafelder: Event-Bild, Galerie, Zielgruppe, Format, Durchführungsart, Level, Sprache, Barrierefreiheit, Tickets, Kapazität, Social-/SEO-Bilder sowie Speaker-Foto, Spezialisierungen, Sprachen, Formate, Honorarbereich und Social Links.
- CSRF-Schutz über `CMS\Security::generateToken()`/`verifyToken()`.
- Public-Routen:
  - `/events`
  - `/events/{slug}`
  - `/event-speakers`
  - `/event-speakers/{slug}`
- Public-Layout mit `max-width: 1160px; margin: 0 auto;`.
- Fehler werden über den Core-Logger (Kanal `plugins`) protokolliert.
- Beim Löschen des Plugins (`cms_365neteventsandspeaker_uninstall()`) werden die vier Tabellen entfernt.

## Tabellen

- `{prefix}365net_events`
- `{prefix}365net_event_speakers`
- `{prefix}365net_event_speaker_rel`
- `{prefix}365net_event_settings`

Die Tabellen werden idempotent angelegt und bei bestehenden Installationen um neue Metaspalten erweitert. Sichere relative Media-Pfade wie `/uploads/...` sind für Bildfelder erlaubt.

## Hooks

- `cms_365net_events_activated`
- `cms_365net_events_deactivated`
- `cms_365net_event_created`
- `cms_365net_event_updated`
- `cms_365net_event_deleted`
- `cms_365net_event_viewed`
- `cms_365net_speaker_created`
- `cms_365net_speaker_updated`
- `cms_365net_speaker_deleted`
- `cms_365net_speaker_viewed`

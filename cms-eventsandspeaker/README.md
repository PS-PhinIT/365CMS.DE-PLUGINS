# 365NET | Events & Speaker

Version: 3.1.0
Modulares 365CMS-Plugin für Events, Messen und verknüpfte Speaker.

## Features

- Feste Seed-Daten aus der gelieferten CSV-Struktur direkt im Plugin (`defaults/seed-data.php`).
- Beim Aktivieren des Plugins wird ein Full-Refresh ausgeführt: Tabellen/Spalten werden idempotent angelegt und bestehende Seed-Events über `source_nr`, `unique_id` sowie Titel/Datum/Ort-Fingerprint aktualisiert.
- Seed-Beschreibungen werden für bestehende Events ebenfalls nachgezogen (inkl. `excerpt` und `seo_description`), auch wenn ältere Datensätze zuvor keine `source_nr` hatten.
- MySQL-Tabellen mit `unique_id`, `slug`, `status`, `created_at` und `updated_at`.
- Prepared Statements über `CMS\Database`/PDO.
- MariaDB-kompatible Tabellenchecks über den zentralen `Database::tableExists()`-Helper statt `SHOW TABLES LIKE ?`.
- Admin-Dashboard mit Kennzahlen und Bereichs-Kacheln unter `/admin/365netevents/dashboard` (Ziel des Seitenleisten-Links); Admin-CRUD für Events und Speaker unter `/admin/365netevents`.
- Erweiterter Adminbereich mit vertikal gestapelten UX-Sektionen für Basisdaten, EditorJS-Beschreibung/Bio, Medien, Ort/Online, Kontakt, Kategorien, Tags, Preisklassen, Speaker-Zuordnung und SEO.
- Einstellungsseite unter `/admin/365netevents/settings` für alle Public-Texte, Farben, Rundungen, Containerbreite sowie Header-/Footer-Abstände.
- Settings-Backfill für bestehende Event-Beschreibungen sowie Speaker-Enrichment auf Basis von Google-Custom-Search-Treffern.
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
- Logs nach `logs/cms-365netevents.log` plus PHP error log.

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

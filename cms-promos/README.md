# 365CMS | Promos

Promo- und Kampagnen-Plugin für 365CMS 4.0.0 mit Platzierungen, CTA-Elementen, Ziel-URLs, Laufzeiten, Frequency-Cap, UTM-Parametern und Klick-/Impression-Statistik.

- **Voraussetzungen:** 365CMS 4.0.0, PHP 8.4.1
- **Admin:** `/admin/plugins/promos-dashboard` (Übersicht, Promos, Platzierungen, Einstellungen) im einheitlichen Plugin-Layout
- **Technische Dokumentation:** [DOC/README.md](DOC/README.md)
- **Öffentlich:** `/promos`, `/promos/placement/<slug>`, Klick-Weiterleitung `/promo/click/<slug>` (jeweils auch unter `/en/…`)

## Funktionen

- Promos mit Titel, Teaser, Zusatztext (DE/EN), Bild, CTA, Laufzeit, Priorität, Featured-Flag
- Platzierungen mit automatischer Ausspielung über die Theme-Hooks `body_start`, `after_header`, `home_content`, `before_footer`
- Frequency-Cap je Besucher (Cookie, ohne Session)
- Filter, Duplizieren, Statistik zurücksetzen, CSV/JSON-Export
- Klick-Export über die Action `cms_promos_click_export` und optional per Webhook (Core-HTTP-Client mit SSRF-Schutz)
- Sitemap-Einträge (`cms_sitemap_entries`), Systemhinweis für abgelaufene Promos

## Lebenszyklus

| Ereignis | Verhalten |
|---|---|
| Aktivieren | Tabellen `promos`, `promo_placements`, `promo_settings` anlegen/migrieren, Standardwerte setzen |
| Deaktivieren | Daten bleiben erhalten |
| Löschen | Tabellen und Option `cms_promos_db_version` werden entfernt |

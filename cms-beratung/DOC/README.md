# 365CMS | Beratung – Admin & Integration (ab 3.0.0)

> Weitere Dokumente: [DATABASE.md](DATABASE.md) · [HOOKS.md](HOOKS.md) · [API.md](API.md) · [GLOBAL-DESIGN-MODULE.md](GLOBAL-DESIGN-MODULE.md) · [Changelog](../CHANGELOG.md)

> **Plugin:** `cms-beratung` · **Version:** 3.0.3 · **Stand:** 2026-11-15
> **Voraussetzungen:** 365CMS 4.0.0 (Plugin-Layout-Vertrag), PHP 8.4.1

## 1. Admin-Oberfläche

### Kopfzeile

| Element | Inhalt |
|---|---|
| Titel | `365CMS \| Beratung`, Versions-Badge, Status „n neue Anfrage(n)“ bzw. „n online“ |
| KPI 1 | Landingpages gesamt |
| KPI 2 | Veröffentlicht |
| KPI 3 | Neue Anfragen (Ton „warning“) |
| KPI 4 | Offene Anfragen (neu, gelesen, in Bearbeitung) |

### Plugin-Menü (links)

| Menüpunkt | URL | Inhalt |
|---|---|---|
| Übersicht | `/admin/plugins/cms-beratung/cms-beratung` | Schnellaktionen, System-Check, neueste Anfragen und Landingpages |
| Landingpages | `…/cms-beratung-landingpages` | Liste mit Filter (Status, Suche); Bearbeiten, Vorschau, Publicseite, Standalone, Export, Duplizieren, Veröffentlichen/Deaktivieren, Löschen. Badge: Anzahl |
| ↳ Editor | `…/cms-beratung?view=cms-beratung-new[&id=…]` | Builder mit Vorlagen, Hero, Bereichen, Kontakt, Design, SEO (aktiver Menüpunkt: Landingpages) |
| Formular-Anfragen | `…/cms-beratung-submissions` | Filter (Status, Landingpage, Suche), Detail `?view_id=<id>` mit Status/Notiz, Sammelaktion (Status setzen, löschen), Löschen, CSV-Export, alte Anfragen löschen. Badge: neue Anfragen |
| M365 FAQs | `…/cms-beratung-faqs` | Zentrale FAQs für alle Landingpages, Anzeige- und Schema-Optionen |
| Design-Presets | `…/cms-beratung-presets` | System- und eigene Presets; Anlegen/Bearbeiten/Löschen, „Übernehmen“ in die globalen Farben |
| Globale Einstellungen | `…/cms-beratung-settings` | Design, Kontakt, **Datenschutz**, SEO, Tracking |
| Import & Export | `…/cms-beratung-import-export` | JSON-Import (neu/überschreiben, Bilder überspringen), JSON-Anzeige und -Download |
| Hilfe | `…/cms-beratung-help` | Kurzreferenz |

Alle Formulare: CSRF → Aktion → Flash-Meldung → 303-Redirect.

## 2. Öffentliche Ausgabe

| Route | Beschreibung |
|---|---|
| `/beratung/<slug>`, `/de/…`, `/en/…` (GET/POST) | Veröffentlichte Landingpage mit Theme-Header/-Footer; POST verarbeitet das Kontaktformular (Honeypot, Token, max. 5 Anfragen je 10 Minuten pro Sitzung und IP, Core-Antispam) |
| `/beratung/<standalone-slug>` | Standalone-Variante ohne Theme-Header/-Footer (Einstellung im Hero) |
| `/beratung-vorschau/<id>` | Vorschau auch von Entwürfen, nur für Administratoren, `noindex`/`no-store`, Formular deaktiviert |

Die Routen sind parametrisiert; der Slug wird mit einer Abfrage aufgelöst. Assets (`frontend*.css`, `frontend.js`) laden nur auf Landingpages. Per-Page-Designwerte werden als CSS-Custom-Properties ausgegeben; die Core-CSP-Komponente `InlineStyleRewriter` überführt sie in nonce-geschützte Klassen (Werte sind farb-/zahlvalidiert).

## 3. Core-Integrationen

| Integration | Umsetzung |
|---|---|
| Sitemap | `cms_sitemap_entries`: veröffentlichte Landingpages ohne `noindex` |
| Seitensuche | `search_results`: Titel, Meta-Beschreibung, Fokus-Keyword und Bereichsinhalte; `_type = beratung` |
| Datenaufbewahrung | Regel `cms_beratung_submissions` – löscht Anfragen nach `retention_days`, wenn `auto_delete_submissions_enabled` aktiv ist |
| Systemhinweise | `cms_beratung_new_submissions` – neue Formular-Anfragen |
| DSGVO | `dsgvo_export_data` schreibt die Anfragen der Person als JSON in die private Ablage (`PrivateStorageService`, `cms-beratung/privacy-exports`; Download und Löschen unter Formular-Anfragen, automatische Entfernung nach 30 Tagen); `dsgvo_delete_data` löscht die Anfragen der Person; `cms_member_export_data` ergänzt die Selbstauskunft. Zuordnung über die vom Core gelieferte E-Mail-Adresse |
| Spamschutz | Honeypot, Ratenbegrenzung über `Security::checkRateLimit()`/`checkDbRateLimit()`, `AntispamService::evaluate()` (wenn im Core aktiv); IP über `Security::getClientIp()` |
| E-Mail | `cms_mail()` (Core-MailService) für Empfänger- und Absenderkopie |
| SEO | Meta, Open Graph, Twitter Card, Canonical, Robots und JSON-LD (FAQ, Service, Breadcrumb) im `head` |
| Experten | optional `CMS_365NET_Experts_And_Companie_Database` (Bereich „Zusammenarbeit“), nur wenn die Klasse vorhanden ist |

## 4. Lebenszyklus

| Callback | Verhalten |
|---|---|
| `cms_beratung_activate()` | Tabellen anlegen/migrieren, Standard-Presets, Einstellungen und FAQs seeden |
| `cms_beratung_deactivate()` | keine Datenänderung |
| `cms_beratung_uninstall()` | Tabellen `beratung_landingpages`, `beratung_settings`, `beratung_design_presets`, `beratung_form_submissions` und Option `beratung_db_version` entfernen |

## 5. Sicherheit

- Admin: `current_user_can('manage_options')` + `isAdmin()`, CSRF je Aktion (`beratung_landingpage_action`, `beratung_save_landingpage`, `beratung_settings`, `beratung_presets`, `beratung_submissions`, `beratung_m365_faqs`, `beratung_import_json`), persistentes Token `beratung_export` für Downloads.
- Medien-Upload `POST /api/cms-beratung/media-upload`: Admin + persistentes Token `editorjs_media`, Bildprüfung per `finfo`, SVG-Prüfung auf aktive Inhalte, 10 MB, eindeutige Dateinamen unter `/uploads/beratung/`.
- Builder-JS: alle Werte mit `esc()`, eine HTML-Senke (`setHtml()`, DOMPurify als TrustedHTML), keine Inline-Handler oder -Styles.
- Ausgaben mit `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`, eingebettetes JSON mit `JSON_HEX_TAG`, CSV mit Formel-Schutz.
- Formular-Anfragen: IP/User-Agent nur bei aktivierter Option, Löschfrist über die Datenaufbewahrung.

## 6. Assets

| Datei | Geladen |
|---|---|
| `assets/css/frontend*.css`, `assets/js/frontend.js` | nur auf Landingpages und in der Vorschau |
| `assets/css/admin.css`, `admin-media.css`, `admin-faq.css` | nur Beratungs-Adminseiten (Core-Tokens) |
| `assets/js/admin-builder.js` | nur im Editor, `defer` |

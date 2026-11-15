# 365CMS – Plugin-Repository

Offizielle Plugins für **365CMS 4.0.0**

| | |
|---|---|
| **Voraussetzungen** | 365CMS ≥ 4.0.0 · PHP ≥ 8.4.1 |
| **Autor** | [365 Network](https://365network.de) |
| **Katalog** | [`index.json`](index.json) (Version 2, Stand 15.11.2026) |
| **Lizenz** | proprietär (siehe `plugin.json` des jeweiligen Plugins) |

---

## Inhalt

- [Plugin-Übersicht](#plugin-übersicht)
- [Plugins im Detail](#plugins-im-detail)
- [In den Core übernommene Plugins](#in-den-core-übernommene-plugins)
- [Plugin-Integrationen](#plugin-integrationen)
- [Installation & Updates](#installation--updates)
- [Aufbau eines Plugins](#aufbau-eines-plugins)
- [Entwicklung & Konventionen](#entwicklung--konventionen)
- [Dokumentation](#dokumentation)

---

## Plugin-Übersicht

| Plugin | Ordner | Slug | Version | Admin | Zweck |
|---|---|---|---|---|---|
| **365CMS \| Buchungen** | [`cms-booking`](cms-booking/) | `cms-booking` | 4.0.1 | `/admin/plugins/booking` | Termin- und Buchungssystem mit Anbietern, Leistungen, Verfügbarkeiten, Mails und iCal |
| **365CMS \| Beratung** | [`cms-beratung`](cms-beratung/) | `cms-beratung` | 3.0.2 | `/admin/plugins/cms-beratung` | Landingpage-Builder für M365-, Copilot-, KI-, Security- und Compliance-Beratung mit Anfrageformular |
| **365CMS \| Promos** | [`cms-promos`](cms-promos/) | `cms-promos` | 4.0.2 | `/admin/plugins/promos-dashboard` | Banner, Teaserflächen, Platzierungen und Klick-/Impression-Statistik |
| **365CMS \| Projekte** | [`cms-projects`](cms-projects/) | `cms-projects` | 4.0.1 | `/admin/plugins/cms-projects` | Projekte, Boards, Tickets und Widgets für Admin-, Member- und Public-Bereich |
| **365CMS \| Events & Speaker** | [`365NET-eventsandspeaker`](365NET-eventsandspeaker/) | `cms-365neteventsandspeaker` | 4.0.1 | `/admin/plugins/365netevents` | Event- und Speaker-Verzeichnis mit Seed-Daten |
| **365CMS \| Experts & Companies** | [`365NET-expertsandcompanie`](365NET-expertsandcompanie/) | `cms-365netexpertsandcompanie` | 2.0.2 | `/admin/plugins/experts-companie` | Experten- und Firmenverzeichnis mit Public Hub und CSV-Import/-Export |

> Bei den beiden `365NET-*`-Plugins weicht der **Ordnername** vom **Installations-Slug** ab. Maßgeblich für Core, Katalog und Updater ist der Slug.

Alle Plugins nutzen das einheitliche Plugin-Admin-Layout von 365CMS 4.0.0 (Kopfzeile mit KPI-Karten, linkes Plugin-Menü, Übersicht mit Schnellaktionen und System-Check) und sind an Sitemap, Seitensuche, DSGVO-Export/-Löschung und Systemhinweise des Cores angebunden, soweit das fachlich passt.

---

## Plugins im Detail

### 365CMS | Buchungen – `cms-booking`

Universelles Buchungssystem. Andere Plugins können sich über den Hook `booking_register_providers` als Anbietertyp registrieren.

- Anbieter, Leistungen, Wochenpläne, Abwesenheiten und Slot-Berechnung mit Pufferzeiten
- Buchungsworkflow: Ausstehend → Bestätigt → Abgeschlossen / Storniert / Nicht erschienen
- E-Mail-Benachrichtigungen über den Core-MailService, Terminerinnerungen per Cron
- iCal-Export (`.ics`) und Google-Kalender-Link, CSV-Export
- Ratenbegrenzung des öffentlichen Formulars, Anonymisierung alter Buchungen als Aufbewahrungsregel
- **Öffentlich:** `/booking/<anbieter>`, `/booking/<anbieter>/<leistung>`, `/booking/confirm/<id>`, `/booking/ical/<id>`, Slot-API `/api/booking/slots/<anbieter>/<datum>` (jeweils auch unter `/en/…`)
- **Doku:** [README](cms-booking/README.md) · [DOC](cms-booking/DOC/README.md) · [CHANGELOG](cms-booking/CHANGELOG.md)

### 365CMS | Beratung – `cms-beratung`

Landingpage-Builder für Beratungsleistungen mit Bereichs-Baukasten, zentralen M365-FAQs, Design-Presets und SEO-Ausgabe.

- Landingpages mit Vorschau, Import & Export (JSON)
- Kontaktformular mit Ratenbegrenzung und Core-Antispam; Formular-Anfragen mit Status, Notiz und CSV-Export
- DSGVO-Auskunft, -Löschung und Löschfrist über System › Datenaufbewahrung
- **Öffentlich:** `/beratung/<slug>` (auch `/de/…`, `/en/…`), Vorschau `/beratung-vorschau/<id>`
- **API:** `POST /api/cms-beratung/media-upload`
- **Doku:** [README](cms-beratung/README.md) · [DOC](cms-beratung/DOC/README.md) · [Design-Modul](cms-beratung/DOC/GLOBAL-DESIGN-MODULE.md) · [CHANGELOG](cms-beratung/CHANGELOG.md)

### 365CMS | Promos – `cms-promos`

Promo- und Kampagnenverwaltung mit automatischer Ausspielung über die Theme-Hooks `body_start`, `after_header`, `home_content` und `before_footer`.

- Promos mit Teaser, CTA, Laufzeit, Priorität, UTM-Parametern und Frequency-Cap (Cookie, ohne Session)
- Platzierungen, Duplizieren, Statistik zurücksetzen, CSV/JSON-Export
- Klick-Export per Action `cms_promos_click_export` oder Webhook (verschlüsselt gespeichert, SSRF-geschützt)
- **Öffentlich:** `/promos`, `/promos/placement/<slug>`, Klick-Weiterleitung `/promo/click/<slug>` (jeweils auch unter `/en/…`)
- **Doku:** [README](cms-promos/README.md) · [DOC](cms-promos/DOC/README.md) · [CHANGELOG](cms-promos/CHANGELOG.md)

### 365CMS | Projekte – `cms-projects`

Projektsteuerung mit Projekt-Dashboards, Boards (Kanban, DIY, MIRO, FLOW, Roadmap), Tickets und Widgets.

- Vollständiges CRUD für Projekte, Boards und Widgets, Projekt-JSON-Export
- Audit-Log mit Filter, CSV-Export und Löschfrist
- **Öffentlich:** `/projects`, `/projects/<slug>` (auch `/en/…`) · **Member:** `/member/plugin/projects`
- **Shortcodes:** `[cms_projects project="…" scope="public|member"]`, `[cms_projects_widget project="…" scope="…" type="…"]`
- **Doku:** [README](cms-projects/README.md) · [DOC](cms-projects/DOC/README.md) · [CHANGELOG](cms-projects/CHANGELOG.md)

### 365CMS | Events & Speaker – `cms-365neteventsandspeaker`

Event- und Speaker-Verzeichnis mit festen Seed-Daten, EditorJS-Beschreibungen und umfangreichen Metafeldern.

- Listen mit Filtern, Sammelaktionen, Pagination und CSV-Export; Kategorien & Tags
- Speaker-Backfill über Google Programmable Search (optional; API-Key verschlüsselt oder per Umgebungsvariable)
- **Öffentlich:** `/events`, `/events/<slug>`, `/speakers`, `/speakers/<slug>`, `/event-speakers`, `/event-speakers/<slug>`
- **Doku:** [README](365NET-eventsandspeaker/README.md) · [CHANGELOG](365NET-eventsandspeaker/CHANGELOG.md)

### 365CMS | Experts & Companies – `cms-365netexpertsandcompanie`

Kombi-Plugin für Experten und Firmen mit gemeinsamem Public Hub, Verzeichnissen und Detailseiten.

- Suche, Status-Filter, Sammelaktionen; CSV-Import/-Export mit Formel-Injektionsschutz
- DSGVO-Auskunft/-Löschung, Theme-Overrides unter `<theme>/cms-expertsandcompanie/`
- **Öffentlich:** `/experts-companie` (Aliase `/experts-and-companie`, `/experts-companies`), `/experts`, `/experts/<id>`, `/companies`, `/companies/<id>`
- **Doku:** [README](365NET-expertsandcompanie/README.md) · [Features](365NET-expertsandcompanie/FeatureList.md) · [CHANGELOG](365NET-expertsandcompanie/CHANGELOG.md)

---

## In den Core übernommene Plugins

Diese Plugins sind seit 365CMS 4.0.0 fester Bestandteil des Cores. Ihre Ordner liegen **nicht** mehr im Repository; im Katalog stehen sie mit `"integrated_in_core": "4.0.0"` und `"install_supported": false`, damit ältere Updater kein Update mehr anbieten.

| Plugin | Funktion | Neue Core-Route |
|---|---|---|
| `cms-contact` | Kontakt- und Formularbaukasten | `/admin/forms` |
| `cms-downloads` | Download-Verwaltung | `/admin/downloads` |
| `cms-feed` | RSS-/Atom-Feeds und Aggregator | `/admin/settings?tab=feeds` |
| `cms-knowledgebase` | Wissensdatenbank und Glossar | `/admin/knowledgebase` |
| `cms-newsletter` | Newsletter mit Double-Opt-In | `/admin/newsletter` |

Beim Update auf 4.0.0 deaktiviert der Core aktive Exemplare, übernimmt deren Daten idempotent und leitet alte Admin-Adressen per 301 auf die Core-Route um. Danach können die alten Plugin-Ordner gelöscht werden.

`cms-forum` wurde am 14.11.2026 aus dem Repository entfernt und ist nicht für 365CMS 4.0.0 freigegeben.

---

## Plugin-Integrationen

Die Plugins funktionieren eigenständig. Ist ein Partner-Plugin aktiv, werden zusätzliche Funktionen freigeschaltet (Prüfung über `PluginManager::isPluginActive()`):

| Plugin | Optionale Integration |
|---|---|
| Events & Speaker | Experts & Companies – Verknüpfung von Events/Speakern mit Firmen und Experten |
| Experts & Companies | Events & Speaker – Verknüpfung von Experts/Companies mit Speakern |
| Buchungen | Weitere Plugins registrieren sich über `booking_register_providers` als Anbietertyp |

---

## Installation & Updates

1. Plugin-Ordner nach `CMS/plugins/` der 365CMS-Installation kopieren.
2. Unter **Plugins** aktivieren. Beim Aktivieren legt das Plugin seine Tabellen idempotent an bzw. migriert sie.
3. Die Admin-Seiten erscheinen im Abschnitt **Plugin-Erweiterungen** der Seitenleiste (Menütitel `365CMS | …`).

| Ereignis | Verhalten |
|---|---|
| Aktivieren | Tabellen anlegen/migrieren, Standardwerte setzen (`<slug>_activate`) |
| Deaktivieren | Daten bleiben erhalten (`<slug>_deactivate`) |
| Löschen | Tabellen und Schema-Option werden entfernt (`<slug>_uninstall`) |

**Updates:** Der Core-Updater liest [`index.json`](index.json) und daraus das `update.json` des jeweiligen Plugins (Version, Mindestanforderungen, Changelog). Schema-Migrationen laufen nur bei einem Versionswechsel, nicht bei jedem Request.

---

## Aufbau eines Plugins

```
<plugin-ordner>/
├── <slug>.php          # Hauptdatei: Plugin-Header, Bootstrap, Lifecycle-Funktionen
├── plugin.json         # Manifest: Slug, Version, Anforderungen, Routen, Tabellen
├── update.json         # Update-Infos für den Core-Updater inkl. Changelog
├── CHANGELOG.md        # Versionshistorie
├── README.md           # Kurzbeschreibung des Plugins
├── FeatureList.md      # (optional) Funktionsliste
├── DOC/                # (optional) README, DATABASE, HOOKS, API
├── admin/              # Admin-Seiten und Views
├── includes/           # Klassen (Installer, Repository, Frontend, Integrationen …)
├── templates/          # Öffentliche und Member-Templates
└── assets/             # CSS und JavaScript
```

Repository-Root:

```
365CMS.DE-PLUGINS/
├── index.json          # Plugin-Katalog für den Core-Updater
├── README.md           # diese Datei
├── DOC/                # übergreifende Dokumentation und Audit-Berichte
├── .github/            # Copilot-Anweisungen, Issue- und PR-Vorlagen
└── <plugin-ordner>/    # je ein Ordner pro Plugin
```

---

## Entwicklung & Konventionen

Die verbindlichen Regeln stehen in [`.github/copilot-instructions.md`](.github/copilot-instructions.md) und den themenspezifischen Dateien unter [`.github/instructions/`](.github/instructions/) (PHP, Datenbank, CSS, Admin-, Member- und Public-Layout).

Kurzfassung:

- `declare(strict_types=1);` und `ABSPATH`-Guard in jeder PHP-Datei
- Hooks ausschließlich über `CMS\Hooks` (`addAction`, `doAction`, `addFilter`, `applyFilters`)
- Datenbankzugriffe nur als Prepared Statements, kein `SELECT *`, Whitelists für `ORDER BY`/`LIMIT`/`IN`
- CSRF-Token und Rechteprüfung bei jedem POST/AJAX, Ausgaben per `htmlspecialchars()` escapen
- CSP-konform: keine Inline-Handler, keine Inline-Skripte ohne Nonce, kein unsicheres `innerHTML`
- Externe Abrufe über `CMS\Http\Client`, Secrets über den `CryptoService`
- Admin-CSS nur mit Core-Tokens (`--cms-*`, `--tblr-*`), Light- und Dark-Mode
- Cross-Plugin-Zugriffe immer mit `PluginManager::isPluginActive()` absichern
- DSGVO-Hooks registrieren, sobald personenbezogene Daten verarbeitet werden

**Vor jedem Commit:** Version in Plugin-Header, `plugin.json` und `update.json` anheben, `CHANGELOG.md` ergänzen und alle PHP-Dateien prüfen:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

Fehler und Wünsche über die [Issue-Vorlagen](.github/ISSUE_TEMPLATE/) melden, Änderungen per Pull Request nach der [PR-Vorlage](.github/PULL_REQUEST_TEMPLATE.md) einreichen.

---

## Dokumentation

| Dokument | Inhalt |
|---|---|
| [`DOC/365CMS-PLUGINS-V4-UPGRADE-2026-11-14.md`](DOC/365CMS-PLUGINS-V4-UPGRADE-2026-11-14.md) | Inventar und Prüfkatalog des Upgrades auf 365CMS 4.0.0 |
| [`DOC/audit/PLUGINS-V4-UPGRADE-2026-11-14.md`](DOC/audit/PLUGINS-V4-UPGRADE-2026-11-14.md) | Maßgeblicher Upgrade-Bericht inkl. Integrationstest |
| [`DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md`](DOC/audit/AUDIT-365CMS-PLUGINS-2026-11-15.md) | Audit: Sicherheit, Kompatibilität, Funktionen, Design, Performance |
| `<plugin>/README.md`, `<plugin>/DOC/` | Plugin-spezifische Dokumentation (Datenbank, Hooks, API) |
| `<plugin>/CHANGELOG.md` | Versionshistorie je Plugin |

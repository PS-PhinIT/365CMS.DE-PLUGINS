# CMS Knowledgebase

CMS Knowledgebase erweitert 365CMS um eine kleine, performante Wissensdatenbank mit automatisch verlinkten Fachbegriffen, Tooltip-Vorschauen und öffentlichen KB-Seiten unter `/kb`.

## Features

- Auto-Linking für Fokusbegriffe und Synonyme
- Tooltip-Vorschau mit Vanilla JavaScript
- Öffentliche Übersicht und Detailseiten unter `/kb` und `/kb/{slug}`
- Admin-CRUD für Einträge und Plugin-Einstellungen
- Strukturierte Inhaltsbereiche je Eintrag, jeweils mit eigenem EditorJS-Blockeditor (siehe unten)
- PSR-3-kompatibles Logging über den vorhandenen CMS-Logger
- Optionaler Output-Buffer-Fallback, falls ein Theme den Filter `content_render` nicht nutzt

## Struktur

- `cms-knowledgebase.php` – Bootstrap, Hooks, Autoloading
- `src/` – Namespaced Klassen unter `CmsKnowledgebase\`
- `admin/views/` – Admin-Templates
- `templates/` – Öffentliche Templates
- `assets/` – CSS und Vanilla-JS

## Inhaltsbereiche

Eintragsinhalte werden nicht als ein HTML-Block gespeichert, sondern pro Bereich in einer eigenen Spalte von `{prefix}kb_entries` (EditorJS-JSON). Die Definition liegt zentral in `src/Support/ContentSections.php`.

| Bereich | Spalte |
|---------|--------|
| Allgemeine Infos | `content` |
| Funktionsweise & technische Details | `content_details` |
| Vorteile & Nutzen | `content_benefits` |
| Praxisbeispiel & Einsatz | `content_practice` |
| Hinweise & Best Practices | `content_notes` |
| Lizenz & Verfügbarkeit | `content_license` |
| Fazit | `content_conclusion` |
| Weiterführende Links | `content_links` |

- Leere Bereiche werden auf der Detailseite nicht ausgegeben.
- Kurzbeschreibung, Tooltip-Text und Synonyme bleiben bewusst Klartextfelder (Tooltip, Meta-Description, JSON-LD).
- Beim Update auf 3.1.0 wird vorhandenes HTML automatisch anhand der Zwischenüberschriften auf die Bereiche verteilt.

## CSV-Import

CSV-Dateien liegen in `csv_kb/` und werden automatisch als Paket erkannt, wenn sie auf `_Glossar.csv` enden (`Kategorie_Glossar.csv`; Unterstriche werden im Kategorienamen zu `&`). Importiert wird unter Einstellungen → **Import**.

`csv_kb/Beispiel_Vorlage.csv` enthält alle Felder aus „Eintrag anlegen“ mit drei Beispieleinträgen (vollständig, minimal, inaktiv). Die Vorlage selbst wird nicht importiert; im Import-Tab gibt es einen Download-Button.

| Spalte | Feld im Editor | Wenn die Zelle leer ist |
|--------|----------------|-------------------------|
| Titel | Titel (Pflicht) | Zeile wird übersprungen |
| Fokusbegriff | Fokusbegriff | Titel |
| Slug | Slug | aus dem Titel |
| Kategorie | Kategorie | aus dem Dateinamen; fehlende Kategorien werden angelegt |
| Priorität | Priorität (1–9999) | 100, 110, 120 … in Dateireihenfolge |
| Max. Links pro Seite | Max. Links pro Seite (1–20) | 1 |
| Kurzbeschreibung / Excerpt | Kurzbeschreibung / Excerpt | „Allgemeine Infos“, gekürzt auf 280 Zeichen |
| Tooltip-Text | Tooltip-Text | Kurzbeschreibung |
| Synonyme | Synonyme (getrennt durch `,` `;` `\|` oder Zeilenumbruch) | keine |
| Allgemeine Infos | Inhaltsbereich `content` | Kurzbeschreibung |
| Funktionsweise & technische Details | Inhaltsbereich `content_details` | leer |
| Vorteile & Nutzen | Inhaltsbereich `content_benefits` | leer |
| Praxisbeispiel & Einsatz | Inhaltsbereich `content_practice` | leer |
| Hinweise & Best Practices | Inhaltsbereich `content_notes` | leer |
| Lizenz & Verfügbarkeit | Inhaltsbereich `content_license` | leer |
| Fazit | Inhaltsbereich `content_conclusion` | leer |
| Weiterführende Links | Inhaltsbereich `content_links` | leer |
| Aktiv | Aktiv (`ja`/`nein`, `1`/`0`, `x`) | ja |
| Nur ganze Wörter verlinken | Nur ganze Wörter verlinken | ja |
| Groß-/Kleinschreibung beachten | Groß-/Kleinschreibung beachten | nein |

- **Spaltennamen:** Groß-/Kleinschreibung, Umlaute, Leer- und Sonderzeichen spielen keine Rolle, die Reihenfolge ist egal, unbekannte Spalten werden ignoriert. Die bisherigen Namen (`Eintrag`, `Kurzbeschreibung`, `Langbeschreibung`, `Lizenz-Info`, `MS Learn / Weblinks`) und die DB-Spaltennamen (`content_details`, `is_active` …) funktionieren ebenfalls.
- **Format:** Trennzeichen `;`, `,` oder Tab (automatisch erkannt), UTF-8 mit oder ohne BOM.
- **Inhaltsbereiche** sind Klartext: Jede Zeile wird ein Absatz, Zeilen mit `- `, `* ` oder `• ` eine Aufzählung, Zeilen mit `1. ` eine nummerierte Liste. HTML wird als Text maskiert.
- **Weiterführende Links:** `Linktext: https://…`, mehrere per Zeilenumbruch oder ` | `. Ohne Linktext wird er aus der Domain abgeleitet (z. B. „Microsoft Learn“).
- **Re-Import:** Bestehende Einträge werden über den Slug erkannt. Gefüllte Zellen überschreiben den gespeicherten Wert, leere Zellen lassen ihn unverändert – auch im Editor ergänzte Inhaltsbereiche, Status und Priorität. Inhalte entfernen geht im Editor.

## Design-Einstellungen

Admin → Knowledgebase → Einstellungen → **Design**. Farben und Maße werden als CSS-Variablen (`--kb-*`) im `<head>` ausgegeben, Stil-Varianten als Modifier-Klassen am `<main class="cms-kb-content">`. Die Umsetzung liegt in `assets/css/knowledgebase-public.css` (nur auf `/kb`, `/kb/{slug}`, `/glossar` geladen) und übersteuert die Theme-Basis aus `cms-phinit/assets/css/page-knowledgebase.css`.

| Bereich | Einstellung | Werte / Token |
|---------|-------------|---------------|
| Allgemein | Content-/Sidebar-Breite, Radius, Akzent, Fläche, Rahmen, Tooltip | `--kb-content-max-width`, `--kb-sidebar-width`, `--kb-radius`, `--kb-accent`, … |
| Übersicht | Kopfbereich | `tinted` · `surface` · `accent` → `cms-kb-content--hero-*` |
| Übersicht | Spalten (Desktop) | `auto` · `2` · `3` · `4` → `cms-kb-content--cols-*` (≤1024px max. 2, ≤640px 1) |
| Übersicht | Karten-Stil | `elevated` · `outline` · `tinted` → `cms-kb-content--cards-*` |
| Übersicht | Kurztext-Zeilen | `0`–`8` (0 = ungekürzt) → `--kb-excerpt-lines` |
| Übersicht | Karten-Hintergrund, Akzentleiste | `--kb-card-bg`, `has-card-accent` |
| Detailseite | Kopfbereich | `tinted` · `surface` · `accent` |
| Detailseite | Inhaltsbereiche | `cards` (Karte je Bereich) · `panel` (ein Panel mit Trennlinien) · `minimal` |
| Detailseite | Bereichsfarben nach Typ, Icons | `has-section-tints`, `has-section-icons`; Hintergrund `--kb-section-bg` |
| Detailseite | Sidebar-Stil | `plain` (ohne Rahmen, Standard) · `card` (ein Rahmen) · `sections` (Karte je Block) |
| Detailseite | Sidebar-Position, Sticky, Hintergrund | `left`/`right`, `has-sticky-sidebar`, `--kb-sidebar-bg` |

## Hinweise

- Für zuverlässiges Auto-Linking ist der Filter `content_render` der bevorzugte Weg.
- Der Output-Buffer-Fallback ist standardmäßig aktiv, damit bestehende Themes trotzdem profitieren.
- Der bestehende leere Tippfehler-Ordner `cms-knowledbase` wurde absichtlich nicht überschrieben.

## Sicherheitsstatus (2026-04-04)

- Snyk-Code-Audit für `cms-knowledgebase` abgeschlossen, aktuell ohne offene Findings.
- Admin-Redirects verweisen nach schreibenden Aktionen jetzt nur noch auf feste interne Dashboard-Ziele.
- Dadurch sind die bisherigen Open-Redirect-Befunde im Admin-Flow beseitigt.

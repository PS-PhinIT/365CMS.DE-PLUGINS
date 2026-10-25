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

## Hinweise

- Für zuverlässiges Auto-Linking ist der Filter `content_render` der bevorzugte Weg.
- Der Output-Buffer-Fallback ist standardmäßig aktiv, damit bestehende Themes trotzdem profitieren.
- Der bestehende leere Tippfehler-Ordner `cms-knowledbase` wurde absichtlich nicht überschrieben.

## Sicherheitsstatus (2026-04-04)

- Snyk-Code-Audit für `cms-knowledgebase` abgeschlossen, aktuell ohne offene Findings.
- Admin-Redirects verweisen nach schreibenden Aktionen jetzt nur noch auf feste interne Dashboard-Ziele.
- Dadurch sind die bisherigen Open-Redirect-Befunde im Admin-Flow beseitigt.

# CMS Projects

`cms-projects` ist ein 365CMS-Plugin für JIRA-ähnliche Projektsteuerung mit Projekt-Dashboards, Boards und Widgets.

## Enthalten

- Projektverwaltung im Adminbereich
- Projekt-Dashboard je Projekt
- Board-Typen:
  - `Kanban`
  - `DIY`
  - `MIRO`
  - `FLOW`
  - `Roadmap`
- Widgets für `Public`, `Member` oder `beide` Bereiche
- Public-Routen:
  - `/projects`
  - `/projects/<slug>`
- Member-Bereich:
  - `/member/plugin/projects`

## Widgets

Widgets können aktuell als folgende Typen gespeichert werden:

- `text`
- `checklist`
- `links`
- `stats`
- `timeline`

## Content-Tags

Das Plugin verarbeitet im Seiteninhalt folgende Tags:

```text
[cms_projects project="mein-projekt" scope="public"]
[cms_projects project="mein-projekt" scope="member"]
[cms_projects_widget project="mein-projekt" scope="public"]
[cms_projects_widget project="mein-projekt" scope="member" type="stats"]
```

`scope="member"` wird nur für angemeldete Personen ausgegeben; `scope="public"` nur für aktive, öffentliche Projekte.

## Anforderungen

- 365CMS 4.0.0 oder neuer
- PHP 8.4.1 oder neuer

## Administration

`/admin/plugins/cms-projects` – Übersicht, Projekte, Boards (inkl. Drag-and-drop-Ansicht), Tickets, Widgets, Audit-Log, Einstellungen.

- Filter, Suche, Sammelaktionen und Pagination in allen Listen
- CSV-Export von Tickets und Audit-Log, JSON-Export/-Import ganzer Projekte
- Öffentliche Projekte (Status „Aktiv“, Sichtbarkeit „Öffentlich“) erscheinen in Sitemap und Seitensuche (abschaltbar)
- Audit-Log mit einstellbarer Löschfrist (System → Datenaufbewahrung), DSGVO-Auskunft und -Anonymisierung

## Lebenszyklus

- Aktivieren: `cms_projects_activate()` legt die Tabellen an und migriert Indizes
- Deaktivieren: `cms_projects_deactivate()` (Daten bleiben erhalten)
- Löschen: `cms_projects_uninstall()` entfernt Tabellen, Optionen und DSGVO-Exportdateien des Plugins

# 365CMS | Projekte – Hooks

## Vom Plugin genutzte Core-Hooks

| Hook | Typ | Zweck |
|---|---|---|
| `cms_init` | Action | Schema-Prüfung (nur bei neuer Version), Aufbewahrungsregel und Systemhinweis registrieren |
| `cms_admin_menu` | Action | Menü `365CMS \| Projekte` (Übersicht, Projekte, Audit-Log, Einstellungen) |
| `register_routes` | Action | `/projects`, `/projects/:slug` (+ `/en/…`) |
| `head` | Action | öffentliches CSS auf `/projects*` |
| `member_dashboard_init` | Action | Member-Bereich `projects` über `PluginDashboardRegistry` (Capability `member`) |
| `member_plugin_section_head` | Action | CSS im Member-Bereich „Projekte“ |
| `content_render`, `cms_content` | Filter | Shortcodes `[cms_projects]`, `[cms_projects_widget]` |
| `cms_sitemap_entries` | Filter | Archiv und öffentliche Projekte für `plugins.xml` |
| `search_results` | Filter | öffentliche Projekte in `/search` (`_type = projects`, `_type_label = Projekte`) |

Registrierungen an Core-Diensten:

| Dienst | Schlüssel |
|---|---|
| `DataRetentionService::registerRule()` | `cms_projects_audit_logs` – Löschung des Audit-Protokolls nach Frist |
| `AdminNoticeService::registerCheck()` | `cms_projects_overdue` – Hinweis auf überfällige Tickets |

## Shortcodes

```text
[cms_projects project="slug" scope="public"]          Dashboard (Boards + Widgets)
[cms_projects project="slug" scope="member"]          nur für angemeldete Besucher
[cms_projects_widget project="slug" scope="public" type="stats"]
```

`type`: `text`, `checklist`, `links`, `stats`, `timeline` (optional).

## Lebenszyklus-Funktionen

| Funktion | Aufruf durch |
|---|---|
| `cms_projects_activate()` | `PluginManager` beim Aktivieren (`boot(true)`) |
| `cms_projects_deactivate()` | beim Deaktivieren (keine Datenänderung) |
| `cms_projects_uninstall()` | beim Löschen (Tabellen und Optionen entfernen) |

Das Plugin löst selbst keine eigenen Actions oder Filter aus.

# 365CMS | Projekte – Datenbank

> Stand 4.0.0 (Schema `CMS_Projects_Service::DB_VERSION` = `2`) · Präfix aus `Database::getPrefix()` · InnoDB · `utf8mb4_unicode_ci`

Tabellen werden über `CMS_Projects_Repository::ensureTables()` angelegt – nur bei Aktivierung, über *Einstellungen › Tabellen prüfen* oder wenn die Option `cms_projects_db_version` von `DB_VERSION` abweicht.

## `{prefix}projects_projects`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `name` | VARCHAR(190) | |
| `slug` | VARCHAR(120), UNIQUE | `/projects/<slug>` |
| `summary` | TEXT | max. 1.200 Zeichen |
| `description` | LONGTEXT | max. 20.000 Zeichen |
| `status` | VARCHAR(20) | `draft`, `active`, `paused`, `archived` |
| `visibility` | VARCHAR(20) | `public`, `member`, `private` |
| `owner_name` | VARCHAR(190) | |
| `accent_color` | VARCHAR(20) | `#rrggbb` (validiert) |
| `member_headline`, `public_headline` | VARCHAR(190) | |
| `created_at`, `updated_at` | DATETIME | |

Indizes: `uniq_slug (slug)`, `idx_status_visibility (status, visibility)`.

## `{prefix}projects_boards`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `project_id` | INT UNSIGNED | → Projekt (Löschen kaskadiert im Code, transaktional) |
| `board_type` | VARCHAR(30) | `kanban`, `diy`, `miro`, `flow`, `roadmap` |
| `title` | VARCHAR(190) | |
| `description` | TEXT | |
| `position` | INT UNSIGNED | Sortierung |
| `is_public`, `is_active` | TINYINT(1) | |
| `payload` | LONGTEXT | JSON: Gruppen unter `columns`/`lanes`/`clusters`/`stages`/`milestones` (`key`, `title`, `items`, optional `wip_limit`) |
| `created_at`, `updated_at` | DATETIME | |

Indizes: `idx_project_position (project_id, position)`, `idx_project_public (project_id, is_public, is_active)`.

## `{prefix}projects_widgets`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `project_id` | INT UNSIGNED | |
| `scope` | VARCHAR(20) | `public`, `member`, `both` |
| `widget_type` | VARCHAR(30) | `text`, `checklist`, `links`, `stats`, `timeline` |
| `title` | VARCHAR(190) | |
| `content` | LONGTEXT | Freitext |
| `position` | INT UNSIGNED | |
| `is_active` | TINYINT(1) | |
| `payload` | LONGTEXT | JSON `items` je Typ (Links nur `http`/`https`) |
| `created_at`, `updated_at` | DATETIME | |

Indizes: `idx_project_scope (project_id, scope, is_active)`, `idx_project_widget_position (project_id, position)`.

## `{prefix}projects_tasks`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED, PK, AI | |
| `project_id`, `board_id` | INT UNSIGNED | Board-Löschung entfernt dessen Tickets |
| `column_key` | VARCHAR(80) | Spalten-Schlüssel des Boards |
| `title` | VARCHAR(190) | |
| `description` | LONGTEXT | |
| `priority` | VARCHAR(20) | `low`, `medium`, `high`, `critical` |
| `assignee_name` | VARCHAR(190) | Freitext |
| `due_date` | DATE NULL | Grundlage für „überfällig“ |
| `sort_order` | INT UNSIGNED | Reihenfolge in der Spalte (Drag & Drop) |
| `is_public`, `is_active` | TINYINT(1) | |
| `payload` | LONGTEXT | reserviert |
| `created_at`, `updated_at` | DATETIME | |

Indizes: `idx_project_board_column (project_id, board_id, column_key)`, `idx_board_public_active (board_id, is_public, is_active)`, `idx_project_sort (project_id, sort_order, id)`.

## `{prefix}projects_audit_logs`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, AI | |
| `actor_id` | INT UNSIGNED | Benutzer-ID |
| `actor_name` | VARCHAR(190) | **personenbezogen** → Aufbewahrungsregel |
| `action` | VARCHAR(80) | z. B. `project.create`, `board.update`, `task.move`, `widget.delete` |
| `entity_type` | VARCHAR(50) | `project`, `board`, `widget`, `task` |
| `entity_id`, `project_id` | INT UNSIGNED | |
| `result` | VARCHAR(20) | `success`, `failure` |
| `context_json` | LONGTEXT | JSON-Kontext ohne Inhalte |
| `created_at` | DATETIME | |

Indizes: `idx_created_at (created_at)`, `idx_project_result (project_id, result)`.

## Optionen (Core-Tabelle `settings`)

| Option | Bedeutung |
|---|---|
| `cms_projects_db_version` | installierte Schema-Version |
| `cms_projects_audit_retention_days` | Löschfrist des Audit-Protokolls (Standard 365, 0 = unbegrenzt) |

## Deinstallation

`cms_projects_uninstall()` → `DROP TABLE` für alle fünf Tabellen, Löschen beider Optionen.

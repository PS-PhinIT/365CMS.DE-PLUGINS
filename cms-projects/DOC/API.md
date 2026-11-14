# 365CMS | Projekte – PHP-API

## `CMS_Projects` (Bootstrap, Singleton)

| Methode | Beschreibung |
|---|---|
| `instance()` | Singleton, erstellt Service, Admin, Public, Member-Dashboard, Shortcodes |
| `service(): CMS_Projects_Service` | Zugriff für Lebenszyklus-Funktionen |
| `boot()` | `cms_init` |
| `sitemapEntries(mixed $entries): array` | Filter `cms_sitemap_entries` |
| `searchResults(mixed $results, mixed $query, mixed $limit, mixed $context): array` | Filter `search_results` |

## `CMS_Projects_Service`

Schreibmethoden liefern `['success' => bool, 'message' => string, 'id' => int?]` und schreiben einen Audit-Eintrag.

| Bereich | Methoden |
|---|---|
| Schema & Optionen | `boot(bool $force = false)`, `installedDbVersion()`, `uninstall()`, `getAuditRetentionDays()`, `saveAuditRetentionDays(int)` |
| Projekte | `getProjects()`, `getPublicProjects()`, `getMemberProjects()`, `findProjects(string $status, string $visibility, string $search)`, `findProject(int)`, `findProjectBySlug(string)`, `saveProject(array, ?int)`, `deleteProject(int)`, `exportProject(int): ?array`, `getProjectDefaults()`, `getProjectPreviewLinks(array)` |
| Boards | `getProjectBoards(int $projectId, string $scope)`, `findBoard(int)`, `saveBoard(array)` (mit `board_id` = Update), `deleteBoard(int $boardId, int $projectId)` |
| Widgets | `getProjectWidgets(int, string $scope)`, `findWidget(int)`, `saveWidget(array)` (mit `widget_id` = Update), `deleteWidget(int, int)` |
| Tickets | `getProjectTasks(int, string)`, `findTask(int)`, `saveTask(array)`, `deleteTask(int, int)`, `moveTask(int $taskId, int $projectId, int $boardId, string $columnKey, array $orderedIds)`, `getRecentTasks(int)`, `countOverdueTasks()`, `getTaskDefaults()` |
| Dashboards | `getProjectDashboardPayload(int $projectId, string $scope)` – `admin`, `member`, `public` mit Sichtbarkeits-/Statusprüfung |
| Audit | `findAuditLogs(int $limit, int $projectId, string $result, string $entityType)`, `getAdminAuditLogs(int)`, `countAuditLogs()`, `countAuditLogsDue()`, `pruneAuditLogs(): int` |
| Suche | `searchPublicProjects(array $words, int $limit)` |
| Kataloge | `getSummary()`, `getBoardTypes()`, `getWidgetTypes()`, `getProjectStatuses()`, `getProjectVisibilities()`, `getWidgetScopes()`, `getTaskPriorities()` |
| Darstellung | `static accentSwatch(array $project, string $class)` – CSP-konformer Farbpunkt (nonce-`<style>` + Klasse) |

Konstanten: `DB_VERSION`, `DB_VERSION_OPTION`, `AUDIT_RETENTION_OPTION`, `AUDIT_RETENTION_DEFAULT` (365).

## `CMS_Projects_Repository`

Datenzugriff über `CMS\Database` (Prepared Statements, explizite Spaltenlisten). Neben Lesen/Speichern je Tabelle: `deleteProjectCascade()`, `deleteBoardCascade()` (transaktional über `getPdo()`), `deleteWidget()`, `findProjects()`, `getRecentTasks()`, `countOverdueTasks()`, `findAuditLogs()`, `countAuditLogsOlderThan()`, `deleteAuditLogsOlderThan()` (in Blöcken), `searchPublicProjects()`, `dropTables()`.

## `CMS_Projects_Admin`

| Methode | Beschreibung |
|---|---|
| `registerPages()` | Menü + Unterseiten (`manage_options`) |
| `dispatchPage()` | gemeinsamer Callback: Rechte, Export, POST (PRG), Layout-Vertrag, Ansicht |
| `renderOverviewPage()`, `renderProjectsPage()` | Kompatibilitäts-Einstiege |
| `static pageUrl(string $slug, array $query)` | interne Admin-URL |
| `static projectUrl(int $projectId, string $tab, array $query)` | Projekt-Reiter-URL |
| `static exportUrl(string $format, array $query)` | Export (`project_json`, `audit_csv`) mit persistentem Token |

## `CMS_Projects_Public`, `CMS_Projects_Member_Dashboard`, `CMS_Projects_Shortcode`

| Klasse | Methoden |
|---|---|
| Public | `registerRoutes()`, `archivePage()`, `singlePage(string $slug)`, `static isRenderingPublicProjectsPage()` |
| Member | `register(PluginDashboardRegistry)`, `renderPage(object $user, array $params)`, `getDashboardStats(object $user)` |
| Shortcode | `processContent(mixed)`, `renderDashboard(array)`, `renderWidgets(array)`, `static markStylesLoaded()` |

## Changelog

Siehe [../CHANGELOG.md](../CHANGELOG.md).

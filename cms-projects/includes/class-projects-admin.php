<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Bereich von CMS Projects (Layout-Vertrag 4.0.0).
 *
 * Routen: /admin/plugins/cms-projects/<unterseite> (zusätzlich ?page=<unterseite>).
 * Jeder Callback gibt nur Inhalt aus; die Shell liefert der Core-AdminRouter.
 */
final class CMS_Projects_Admin
{
    public const ROOT_SLUG = 'cms-projects';
    public const PAGE_OVERVIEW = 'cms-projects';
    public const PAGE_PROJECTS = 'cms-projects-projects';
    public const PAGE_BOARDS = 'cms-projects-boards';
    public const PAGE_TASKS = 'cms-projects-tasks';
    public const PAGE_WIDGETS = 'cms-projects-widgets';
    public const PAGE_AUDIT = 'cms-projects-audit';
    public const PAGE_SETTINGS = 'cms-projects-settings';

    private const CSRF_ACTION = 'cms_projects_admin';
    private const FLASH_KEY = 'cms_projects_admin_flash';
    private const MENU_TITLE = '365CMS | Projekte';

    private ?string $csrfToken = null;
    private ?array $summary = null;

    public function __construct(private readonly CMS_Projects_Service $service)
    {
    }

    public function registerPages(): void
    {
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        add_menu_page('CMS Projects', self::MENU_TITLE, 'admin', self::ROOT_SLUG, [$this, 'dispatchPage'], 'ti ti-briefcase', 83);

        foreach ($this->getPages() as $slug => $page) {
            add_submenu_page(self::ROOT_SLUG, $page['title'], $page['label'], 'admin', $slug, [$this, 'dispatchPage']);
        }
    }

    /** @return array<string, array{label:string,title:string,icon:string,group?:string}> */
    private function getPages(): array
    {
        return [
            self::PAGE_OVERVIEW => ['label' => 'Übersicht', 'title' => 'Projektübersicht', 'icon' => 'ti ti-layout-dashboard'],
            self::PAGE_PROJECTS => ['label' => 'Projekte', 'title' => 'Projektverwaltung', 'icon' => 'ti ti-briefcase'],
            self::PAGE_BOARDS => ['label' => 'Boards', 'title' => 'Boards', 'icon' => 'ti ti-layout-kanban', 'group' => 'Projektinhalte'],
            self::PAGE_TASKS => ['label' => 'Tickets', 'title' => 'Tickets', 'icon' => 'ti ti-checklist', 'group' => 'Projektinhalte'],
            self::PAGE_WIDGETS => ['label' => 'Widgets', 'title' => 'Widgets', 'icon' => 'ti ti-layout-grid', 'group' => 'Projektinhalte'],
            self::PAGE_AUDIT => ['label' => 'Audit-Log', 'title' => 'Audit-Log', 'icon' => 'ti ti-history', 'group' => 'System'],
            self::PAGE_SETTINGS => ['label' => 'Einstellungen', 'title' => 'Einstellungen', 'icon' => 'ti ti-settings', 'group' => 'System'],
        ];
    }

    // ── Dispatcher ───────────────────────────────────────────────────────────

    public function dispatchPage(): void
    {
        if (!$this->currentUserCanManageProjects()) {
            http_response_code(403);
            echo '<div class="alert alert-danger" role="alert">Keine Berechtigung für die Projektverwaltung.</div>';
            return;
        }

        $page = $this->resolvePage();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['cms_projects_action'])) {
            $this->handlePost($page);
        }

        try {
            $this->renderPage($page);
        } catch (\Throwable $e) {
            error_log('[cms-projects] admin render failed page=' . $page . ' error=' . $e->getMessage());
            echo '<div class="alert alert-danger" role="alert">Die Seite konnte nicht geladen werden. Bitte prüfen Sie, ob die Datenbanktabellen des Plugins vorhanden sind (Übersicht → System-Check).</div>';
        }
    }

    private function resolvePage(): string
    {
        $requested = strtolower(trim((string) ($_GET['page'] ?? '')));
        $requested = (string) preg_replace('/[^a-z0-9_-]+/', '', $requested);

        if ($requested === '') {
            $path = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/');
            if (preg_match('#^/admin/plugins/' . preg_quote(self::ROOT_SLUG, '#') . '/([a-z0-9_-]+)#', $path, $matches) === 1) {
                $requested = $matches[1];
            }
        }

        return isset($this->getPages()[$requested]) ? $requested : self::PAGE_OVERVIEW;
    }

    private function renderPage(string $page): void
    {
        $this->loadWorkspace();
        $flash = $this->consumeFlash();
        $projectId = in_array($page, [self::PAGE_BOARDS, self::PAGE_TASKS, self::PAGE_WIDGETS], true) ? $this->resolveProjectId() : max(0, (int) ($_GET['project_id'] ?? 0));

        echo $this->assetTags($page);
        echo cms_plugin_workspace_open($this->buildWorkspaceArgs($page, $projectId));

        if ($flash !== null) {
            echo '<div class="alert alert-' . ($flash['type'] === 'success' ? 'success' : 'danger') . '" role="alert">' . CMS_Projects_View::esc($flash['message']) . '</div>';
        }

        $service = $this->service;
        $e = static fn (mixed $value): string => CMS_Projects_View::esc($value);

        switch ($page) {
            case self::PAGE_PROJECTS:
                $this->renderProjectsPage($e);
                break;
            case self::PAGE_BOARDS:
                $this->renderBoardsPage($e, $projectId);
                break;
            case self::PAGE_TASKS:
                $this->renderTasksPage($e, $projectId);
                break;
            case self::PAGE_WIDGETS:
                $this->renderWidgetsPage($e, $projectId);
                break;
            case self::PAGE_AUDIT:
                $this->renderAuditPage($e);
                break;
            case self::PAGE_SETTINGS:
                $settings = CMS_Projects_Settings::all();
                $auditCount = $service->countAuditLogs();
                include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/settings.php';
                break;
            default:
                $this->renderOverviewPage($e);
        }

        echo cms_plugin_workspace_close();
    }

    // ── Seiten ───────────────────────────────────────────────────────────────

    private function renderOverviewPage(callable $e): void
    {
        $summary = $this->getSummary();
        $tableStatus = $this->service->getTableStatus();
        $missingTables = array_keys(array_filter($tableStatus, static fn (bool $exists): bool => !$exists));
        $settings = CMS_Projects_Settings::all();

        $checks = [
            [
                'label' => 'Datenbanktabellen',
                'status' => $missingTables === [] ? 'ok' : 'error',
                'value' => $missingTables === [] ? count($tableStatus) . ' von ' . count($tableStatus) . ' vorhanden' : 'fehlend: ' . implode(', ', $missingTables),
                'hint' => $missingTables === [] ? '' : 'Plugin deaktivieren und erneut aktivieren, um die Tabellen anzulegen.',
            ],
            [
                'label' => 'Schema-Version',
                'status' => CMS_Projects_Settings::isSchemaCurrent() ? 'ok' : 'warning',
                'value' => CMS_Projects_Settings::storedSchemaVersion() !== '' ? CMS_Projects_Settings::storedSchemaVersion() : 'nicht gesetzt',
                'hint' => CMS_Projects_Settings::isSchemaCurrent() ? '' : 'Erwartet: ' . CMS_Projects_Settings::SCHEMA_VERSION,
            ],
            [
                'label' => 'Öffentliche Projekte',
                'status' => $summary['public_projects'] > 0 ? 'ok' : 'info',
                'value' => $summary['public_projects'] > 0 ? $summary['public_projects'] . ' unter /projects sichtbar' : 'keine – /projects ist leer',
                'url' => $this->url(self::PAGE_PROJECTS, ['visibility' => 'public']),
            ],
            [
                'label' => 'Überfällige Tickets',
                'status' => $summary['overdue_tasks'] > 0 ? 'warning' : 'ok',
                'value' => $summary['overdue_tasks'] > 0 ? $summary['overdue_tasks'] . ' offen' : 'keine',
                'url' => $this->url(self::PAGE_TASKS, ['overdue' => '1', 'project_id' => '0']),
            ],
            [
                'label' => 'Sitemap & Suche',
                'status' => $settings['sitemap_enabled'] || $settings['search_enabled'] ? 'ok' : 'muted',
                'value' => 'Sitemap ' . ($settings['sitemap_enabled'] ? 'an' : 'aus') . ' · Suche ' . ($settings['search_enabled'] ? 'an' : 'aus'),
                'url' => $this->url(self::PAGE_SETTINGS),
            ],
            [
                'label' => 'Aufbewahrung Audit-Log',
                'status' => $settings['audit_retention_days'] > 0 ? 'ok' : 'info',
                'value' => $settings['audit_retention_days'] > 0 ? $settings['audit_retention_days'] . ' Tage' : 'unbegrenzt',
                'url' => $this->url(self::PAGE_SETTINGS),
            ],
        ];

        $activity = [];
        foreach ($this->service->getRecentAuditLogs(8) as $log) {
            $activity[] = [
                'title' => $this->service->describeAuditAction((string) $log['action']) . ((int) $log['entity_id'] > 0 ? ' #' . (int) $log['entity_id'] : ''),
                'meta' => $this->formatDateTime((string) $log['created_at']) . ' · ' . ((string) $log['actor_name'] !== '' ? (string) $log['actor_name'] : 'system'),
                'badge' => $log['result'] === 'success' ? 'OK' : 'Fehler',
                'tone' => $log['result'] === 'success' ? 'success' : 'danger',
                'url' => (int) $log['project_id'] > 0 ? $this->url(self::PAGE_PROJECTS, ['project_id' => (int) $log['project_id']]) : '',
            ];
        }

        echo cms_plugin_dashboard([
            'intro' => 'Projekt-Dashboards mit Boards, Tickets und Widgets für den Mitglieder- und den öffentlichen Bereich.',
            'actions' => [
                ['label' => 'Neues Projekt', 'url' => $this->url(self::PAGE_PROJECTS, ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Stammdaten und Sichtbarkeit festlegen'],
                ['label' => 'Ticket anlegen', 'url' => $this->url(self::PAGE_TASKS, ['action' => 'new']), 'icon' => 'ti ti-square-plus', 'description' => 'Aufgabe einer Board-Spalte zuordnen'],
                ['label' => 'Board-Ansicht', 'url' => $this->url(self::PAGE_BOARDS), 'icon' => 'ti ti-layout-kanban', 'description' => 'Tickets per Drag-and-drop verschieben'],
                ['label' => 'Öffentliche Projekte', 'url' => '/projects', 'icon' => 'ti ti-external-link', 'external' => true, 'description' => 'Archiv unter /projects öffnen'],
            ],
            'checks' => $checks,
            'activity' => [
                'heading' => 'Letzte Änderungen',
                'items' => $activity,
                'empty' => 'Noch keine Änderungen protokolliert.',
                'more_url' => $this->url(self::PAGE_AUDIT),
                'more_label' => 'Audit-Log öffnen',
            ],
        ]);

        $dueTasks = $this->service->getDueTasks(14, 10);
        $projectNames = $this->getProjectNameMap();
        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/overview-due.php';
    }

    private function renderProjectsPage(callable $e): void
    {
        $projectStatuses = $this->service->getProjectStatuses();
        $projectVisibilities = $this->service->getProjectVisibilities();
        $statusTones = $this->service->getProjectStatusTones();
        $csrfToken = $this->csrfToken();

        $editId = max(0, (int) ($_GET['project_id'] ?? 0));
        if ($editId > 0 || (string) ($_GET['action'] ?? '') === 'new') {
            $project = $editId > 0 ? $this->service->findProject($editId) : null;
            if ($editId > 0 && $project === null) {
                echo '<div class="alert alert-danger" role="alert">Das Projekt wurde nicht gefunden.</div>';
                return;
            }

            $projectFormValues = $project ?? $this->service->getProjectDefaults();
            include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/project-form.php';
            return;
        }

        $filters = $this->service->normalizeProjectFilters($_GET);
        $pageData = $this->service->getProjectsPage($filters, max(1, (int) ($_GET['paged'] ?? 1)));
        $importMaxKb = (int) (CMS_Projects_Service::IMPORT_MAX_BYTES / 1024);
        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/projects-list.php';
    }

    private function renderBoardsPage(callable $e, int $projectId): void
    {
        $projectOptions = $this->service->getProjectOptions();
        $project = $projectId > 0 ? $this->service->findProject($projectId) : null;
        $csrfToken = $this->csrfToken();
        $boardTypes = $this->service->getBoardTypes();
        $boards = $project !== null ? $this->service->getProjectBoards($projectId, 'admin') : [];

        $editBoardId = max(0, (int) ($_GET['board_id'] ?? 0));
        $boardFormValues = $editBoardId > 0 && $project !== null ? $this->service->findBoard($projectId, $editBoardId) : null;
        if ($boardFormValues === null) {
            $editBoardId = 0;
            $boardFormValues = $this->service->getBoardDefaults();
        }

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/boards.php';
    }

    private function renderTasksPage(callable $e, int $projectId): void
    {
        $projectOptions = $this->service->getProjectOptions();
        $allProjects = $projectId === 0 && (string) ($_GET['project_id'] ?? '') === '0';
        $project = $projectId > 0 ? $this->service->findProject($projectId) : null;
        $csrfToken = $this->csrfToken();
        $taskPriorities = $this->service->getTaskPriorities();
        $boards = $project !== null ? $this->service->getProjectBoards($projectId, 'admin', []) : [];
        $boardOptions = $this->service->getBoardColumnOptions($boards);
        $boardTitles = array_column($boards, 'title', 'id');

        $filters = $this->service->normalizeTaskFilters(array_merge($_GET, ['project_id' => $projectId]));
        $pageData = ($project !== null || $allProjects) ? $this->service->getTasksPage($filters, max(1, (int) ($_GET['paged'] ?? 1))) : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 50];
        $projectNames = $allProjects ? $this->getProjectNameMap() : [];

        $editTaskId = max(0, (int) ($_GET['task_id'] ?? 0));
        $taskFormValues = $this->service->getTaskDefaults();
        if ($project !== null && $editTaskId > 0) {
            $task = $this->service->findTask($editTaskId);
            if ($task !== null && (int) $task['project_id'] === $projectId) {
                $taskFormValues = array_merge($taskFormValues, $task);
            } else {
                $editTaskId = 0;
            }
        } else {
            $editTaskId = 0;
        }
        $showForm = $project !== null && ($editTaskId > 0 || (string) ($_GET['action'] ?? '') === 'new');

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/tasks.php';
    }

    private function renderWidgetsPage(callable $e, int $projectId): void
    {
        $projectOptions = $this->service->getProjectOptions();
        $project = $projectId > 0 ? $this->service->findProject($projectId) : null;
        $csrfToken = $this->csrfToken();
        $widgetTypes = $this->service->getWidgetTypes();
        $widgetScopes = $this->service->getWidgetScopes();
        $widgets = $project !== null ? $this->service->getProjectWidgets($projectId, 'admin') : [];

        $editWidgetId = max(0, (int) ($_GET['widget_id'] ?? 0));
        $widgetFormValues = $editWidgetId > 0 && $project !== null ? $this->service->findWidget($projectId, $editWidgetId) : null;
        if ($widgetFormValues === null) {
            $editWidgetId = 0;
            $widgetFormValues = $this->service->getWidgetDefaults();
        }

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/widgets.php';
    }

    private function renderAuditPage(callable $e): void
    {
        $filters = $this->service->normalizeAuditFilters($_GET);
        $pageData = $this->service->getAuditPage($filters, max(1, (int) ($_GET['paged'] ?? 1)));
        $entityTypes = $this->service->getAuditEntityTypes();
        $csrfToken = $this->csrfToken();
        $service = $this->service;
        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/audit.php';
    }

    // ── POST-Verarbeitung (PRG) ──────────────────────────────────────────────

    private function handlePost(string $page): void
    {
        $action = (string) ($_POST['cms_projects_action'] ?? '');
        $projectId = max(0, (int) ($_POST['project_id'] ?? 0));
        $token = (string) ($_POST['csrf_token'] ?? '');
        $isDownload = in_array($action, ['export_tasks', 'export_audit', 'export_project'], true);

        $security = class_exists('CMS\\Security') ? \CMS\Security::instance() : null;
        $tokenValid = $security !== null && ($isDownload
            ? $security->verifyPersistentToken($token, self::CSRF_ACTION)
            : $security->verifyToken($token, self::CSRF_ACTION));

        if (!$tokenValid) {
            $this->redirectWithFlash('error', 'Sicherheitsprüfung fehlgeschlagen. Bitte das Formular erneut absenden.', $this->url($page, $projectId > 0 ? ['project_id' => $projectId] : []));
        }

        switch ($action) {
            case 'save_project':
                $editId = max(0, (int) ($_POST['edit_project_id'] ?? 0));
                $result = $this->service->saveProject($_POST, $editId > 0 ? $editId : null);
                $target = !empty($result['success'])
                    ? $this->url(self::PAGE_PROJECTS, ['project_id' => (int) $result['id']])
                    : $this->url(self::PAGE_PROJECTS, $editId > 0 ? ['project_id' => $editId] : ['action' => 'new']);
                $this->redirectWithResult($result, $target);
                // no break – redirectWithResult beendet den Request
            case 'delete_project':
                $result = $this->service->deleteProject($projectId);
                $this->redirectWithResult($result, $this->url(self::PAGE_PROJECTS));
                // no break
            case 'bulk_projects':
                $result = $this->service->bulkProjects((string) ($_POST['bulk_operation'] ?? ''), $this->postedIds());
                $this->redirectWithResult($result, $this->url(self::PAGE_PROJECTS, $this->postedReturnQuery(['q', 'status', 'visibility', 'paged'])));
                // no break
            case 'import_project':
                $result = $this->handleImport();
                $this->redirectWithResult($result, !empty($result['id']) ? $this->url(self::PAGE_PROJECTS, ['project_id' => (int) $result['id']]) : $this->url(self::PAGE_PROJECTS));
                // no break
            case 'export_project':
                $this->sendProjectExport($projectId);
                // no break
            case 'save_board':
                $result = $this->service->saveBoard($_POST);
                $this->redirectWithResult($result, $this->url(self::PAGE_BOARDS, ['project_id' => $projectId]));
                // no break
            case 'bulk_boards':
                $result = $this->service->bulkBoards($projectId, (string) ($_POST['bulk_operation'] ?? ''), $this->postedIds());
                $this->redirectWithResult($result, $this->url(self::PAGE_BOARDS, ['project_id' => $projectId]));
                // no break
            case 'move_task':
                $orderedTaskIds = array_map('intval', explode(',', (string) ($_POST['ordered_task_ids'] ?? '')));
                $result = $this->service->moveTask(
                    max(0, (int) ($_POST['task_id'] ?? 0)),
                    $projectId,
                    max(0, (int) ($_POST['target_board_id'] ?? 0)),
                    (string) ($_POST['target_column_key'] ?? ''),
                    $orderedTaskIds
                );
                $this->redirectWithResult($result, $this->url(self::PAGE_BOARDS, ['project_id' => $projectId]) . '#cp-board-view');
                // no break
            case 'save_task':
                $result = $this->service->saveTask($_POST);
                $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
                $target = !empty($result['success'])
                    ? $this->url(self::PAGE_TASKS, ['project_id' => $projectId])
                    : $this->url(self::PAGE_TASKS, $taskId > 0 ? ['project_id' => $projectId, 'task_id' => $taskId] : ['project_id' => $projectId, 'action' => 'new']);
                $this->redirectWithResult($result, $target);
                // no break
            case 'delete_task':
                $result = $this->service->deleteTask(max(0, (int) ($_POST['task_id'] ?? 0)), $projectId);
                $this->redirectWithResult($result, $this->url(self::PAGE_TASKS, ['project_id' => $projectId]));
                // no break
            case 'bulk_tasks':
                $result = $this->service->bulkTasks($projectId, (string) ($_POST['bulk_operation'] ?? ''), $this->postedIds());
                $this->redirectWithResult($result, $this->url(self::PAGE_TASKS, array_merge(['project_id' => $projectId], $this->postedReturnQuery(['q', 'board_id', 'priority', 'state', 'overdue', 'paged']))));
                // no break
            case 'export_tasks':
                $this->sendCsv('cms-projects-tickets-' . date('Ymd-His') . '.csv', $this->service->buildTasksCsvRows($projectId));
                // no break
            case 'save_widget':
                $result = $this->service->saveWidget($_POST);
                $this->redirectWithResult($result, $this->url(self::PAGE_WIDGETS, ['project_id' => $projectId]));
                // no break
            case 'bulk_widgets':
                $result = $this->service->bulkWidgets($projectId, (string) ($_POST['bulk_operation'] ?? ''), $this->postedIds());
                $this->redirectWithResult($result, $this->url(self::PAGE_WIDGETS, ['project_id' => $projectId]));
                // no break
            case 'export_audit':
                $this->sendCsv('cms-projects-audit-' . date('Ymd-His') . '.csv', $this->service->buildAuditCsvRows([
                    'q' => (string) ($_POST['q'] ?? ''),
                    'result' => (string) ($_POST['result'] ?? ''),
                    'entity_type' => (string) ($_POST['entity_type'] ?? ''),
                ]));
                // no break
            case 'save_settings':
                $values = [
                    'audit_retention_days' => max(0, min(3650, (int) ($_POST['audit_retention_days'] ?? 0))),
                    'sitemap_enabled' => !empty($_POST['sitemap_enabled']),
                    'search_enabled' => !empty($_POST['search_enabled']),
                ];
                $saved = CMS_Projects_Settings::save($values);
                if ($saved) {
                    $this->service->recordSettingsChange($values);
                }
                $this->redirectWithResult(
                    ['success' => $saved, 'message' => $saved ? 'Einstellungen wurden gespeichert.' : 'Einstellungen konnten nicht gespeichert werden.'],
                    $this->url(self::PAGE_SETTINGS)
                );
                // no break
            default:
                $this->redirectWithFlash('error', 'Unbekannte Aktion.', $this->url($page));
        }
    }

    private function handleImport(): array
    {
        $file = $_FILES['import_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Bitte eine Exportdatei (.json) auswählen.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > CMS_Projects_Service::IMPORT_MAX_BYTES) {
            return ['success' => false, 'message' => 'Die Datei ist leer oder größer als ' . (int) (CMS_Projects_Service::IMPORT_MAX_BYTES / 1024) . ' KB.'];
        }

        $name = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            return ['success' => false, 'message' => 'Nur Dateien mit der Endung .json sind erlaubt.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'message' => 'Die hochgeladene Datei ist ungültig.'];
        }

        if (class_exists('finfo')) {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!in_array($mime, ['application/json', 'text/plain', 'text/json'], true)) {
                return ['success' => false, 'message' => 'Der Dateityp ' . $mime . ' wird nicht unterstützt.'];
            }
        }

        $raw = file_get_contents($tmp);
        if (!is_string($raw) || $raw === '') {
            return ['success' => false, 'message' => 'Die Datei konnte nicht gelesen werden.'];
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['success' => false, 'message' => 'Die Datei enthält kein gültiges JSON.'];
        }

        return is_array($data) ? $this->service->importProject($data) : ['success' => false, 'message' => 'Die Datei enthält keine Projektdaten.'];
    }

    private function sendProjectExport(int $projectId): never
    {
        $export = $this->service->buildProjectExport($projectId);
        if ($export === null) {
            $this->redirectWithFlash('error', 'Das Projekt wurde nicht gefunden.', $this->url(self::PAGE_PROJECTS));
        }

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($export['project']['slug'] ?? 'projekt'))) ?: 'projekt';

        $this->cleanOutputBuffers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="cms-projects-' . $slug . '-' . date('Ymd') . '.json"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo is_string($json) ? $json : '{}';
        exit;
    }

    /** @param list<list<string>> $rows */
    private function sendCsv(string $filename, array $rows): never
    {
        $this->cleanOutputBuffers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }

        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'csvCell'], $row), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    private function csvCell(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function cleanOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /** @return list<int> */
    private function postedIds(): array
    {
        $ids = $_POST['ids'] ?? [];
        return is_array($ids) ? array_values(array_map(static fn (mixed $id): int => is_scalar($id) ? (int) $id : 0, $ids)) : [];
    }

    /** Filterparameter der Liste für den Rücksprung nach einer Sammelaktion. */
    private function postedReturnQuery(array $keys): array
    {
        $query = [];
        foreach ($keys as $key) {
            $value = trim((string) ($_POST['return_' . $key] ?? ''));
            if ($value !== '' && preg_match('/^[\p{L}\p{N} ._@-]{1,100}$/u', $value) === 1) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function redirectWithResult(array $result, string $target): never
    {
        $this->redirectWithFlash(!empty($result['success']) ? 'success' : 'error', (string) ($result['message'] ?? 'Aktion abgeschlossen.'), $target);
    }

    private function redirectWithFlash(string $type, string $message, string $target): never
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::FLASH_KEY] = ['type' => $type === 'success' ? 'success' : 'error', 'message' => $message];
        }

        $fallback = $this->url(self::PAGE_OVERVIEW);
        $hash = '';
        if (str_contains($target, '#')) {
            [$target, $hash] = explode('#', $target, 2);
            $hash = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $hash) === 1 ? '#' . $hash : '';
        }

        $path = class_exists('CMS\\Security') && method_exists('CMS\\Security', 'safeLocalPath')
            ? \CMS\Security::safeLocalPath($target, $fallback)
            : (str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $fallback);

        $this->cleanOutputBuffers();
        header('Location: ' . (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . $path . $hash, true, 303);
        exit;
    }

    /** @return array{type:string,message:string}|null */
    private function consumeFlash(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION[self::FLASH_KEY]) || !is_array($_SESSION[self::FLASH_KEY])) {
            return null;
        }

        $flash = $_SESSION[self::FLASH_KEY];
        unset($_SESSION[self::FLASH_KEY]);

        return ['type' => (string) ($flash['type'] ?? 'error'), 'message' => (string) ($flash['message'] ?? '')];
    }

    // ── Layout-Helfer ────────────────────────────────────────────────────────

    private function loadWorkspace(): void
    {
        if (!function_exists('cms_plugin_workspace_open')) {
            $core = ABSPATH . 'admin/partials/plugin-workspace.php';
            if (is_file($core)) {
                require_once $core;
            }
        }

        if (!function_exists('cms_plugin_workspace_open')) {
            require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/plugin-workspace.php';
            echo '<link rel="stylesheet" href="' . CMS_Projects_View::esc(CMS_PROJECTS_PLUGIN_URL . 'assets/css/cms-plugin-workspace.css?v=' . CMS_PROJECTS_VERSION) . '">';
        }
    }

    private function assetTags(string $page): string
    {
        $html = '<link rel="stylesheet" href="' . CMS_Projects_View::esc(CMS_PROJECTS_PLUGIN_URL . 'assets/css/admin.css?v=' . CMS_PROJECTS_VERSION) . '">';
        if (in_array($page, [self::PAGE_BOARDS, self::PAGE_WIDGETS], true)) {
            $html .= '<link rel="stylesheet" href="' . CMS_Projects_View::esc(CMS_PROJECTS_PLUGIN_URL . 'assets/css/style.css?v=' . CMS_PROJECTS_VERSION) . '">';
        }

        return $html . '<script src="' . CMS_Projects_View::esc(CMS_PROJECTS_PLUGIN_URL . 'assets/js/admin-board.js?v=' . CMS_PROJECTS_VERSION) . '" defer></script>';
    }

    private function buildWorkspaceArgs(string $page, int $projectId): array
    {
        $summary = $this->getSummary();
        $schemaOk = CMS_Projects_Settings::isSchemaCurrent();
        $projectQuery = $projectId > 0 ? ['project_id' => $projectId] : [];

        $nav = [];
        foreach ($this->getPages() as $slug => $config) {
            $item = [
                'slug' => $slug,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'url' => $this->url($slug, in_array($slug, [self::PAGE_BOARDS, self::PAGE_TASKS, self::PAGE_WIDGETS], true) ? $projectQuery : []),
                'active' => $slug === $page,
            ];
            if (isset($config['group'])) {
                $item['group'] = $config['group'];
            }
            if ($slug === self::PAGE_PROJECTS && $summary['projects'] > 0) {
                $item['badge'] = $summary['projects'];
            }
            if ($slug === self::PAGE_TASKS && $summary['overdue_tasks'] > 0) {
                $item['badge'] = $summary['overdue_tasks'];
                $item['badge_tone'] = 'warning';
            }
            $nav[] = $item;
        }

        return [
            'plugin' => self::ROOT_SLUG,
            'title' => self::MENU_TITLE,
            'icon' => 'ti ti-briefcase',
            'version' => CMS_PROJECTS_VERSION,
            'status' => $schemaOk ? ['label' => 'Aktiv', 'tone' => 'success'] : ['label' => 'Schema veraltet', 'tone' => 'warning'],
            'description' => 'Projekt-Dashboards mit Boards, Tickets und Widgets für Mitglieder und Öffentlichkeit.',
            'stats' => [
                ['label' => 'Projekte', 'value' => $summary['projects'], 'icon' => 'ti ti-briefcase', 'url' => $this->url(self::PAGE_PROJECTS)],
                ['label' => 'Aktive Projekte', 'value' => $summary['active_projects'], 'icon' => 'ti ti-player-play', 'tone' => 'success', 'url' => $this->url(self::PAGE_PROJECTS, ['status' => 'active'])],
                ['label' => 'Offene Tickets', 'value' => $summary['active_tasks'], 'icon' => 'ti ti-checklist', 'url' => $this->url(self::PAGE_TASKS, ['project_id' => '0', 'state' => 'active'])],
                ['label' => 'Überfällig', 'value' => $summary['overdue_tasks'], 'icon' => 'ti ti-alarm', 'tone' => $summary['overdue_tasks'] > 0 ? 'warning' : 'muted', 'url' => $this->url(self::PAGE_TASKS, ['project_id' => '0', 'overdue' => '1'])],
            ],
            'nav' => $nav,
            'nav_explicit' => true,
        ];
    }

    /**
     * Kopf einer Unterseite: Titel, Einzeiler, Aktionen (bereits escaped).
     *
     * @param list<array{label:string,url:string,icon?:string,primary?:bool,external?:bool}> $actions
     */
    private function pageHeader(string $title, string $description, array $actions = []): string
    {
        $html = '<div class="admin-page-header"><div><h2>' . CMS_Projects_View::esc($title) . '</h2>'
            . ($description !== '' ? '<p>' . CMS_Projects_View::esc($description) . '</p>' : '') . '</div>';
        if ($actions !== []) {
            $html .= '<div class="header-actions">';
            foreach ($actions as $action) {
                $html .= '<a class="btn ' . (!empty($action['primary']) ? 'btn-primary' : 'btn-outline-secondary') . '" href="' . CMS_Projects_View::esc($action['url']) . '"'
                    . (!empty($action['external']) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
                    . (!empty($action['icon']) ? '<i class="' . CMS_Projects_View::esc($action['icon']) . '" aria-hidden="true"></i> ' : '')
                    . CMS_Projects_View::esc($action['label']) . '</a>';
            }
            $html .= '</div>';
        }

        return $html . '</div>';
    }

    /** Projektauswahl (GET) für projektbezogene Unterseiten. */
    private function projectSwitcher(string $page, array $projectOptions, int $projectId, bool $allowAll = false, array $extraFields = []): string
    {
        $html = '<form method="get" action="' . CMS_Projects_View::esc($this->url($page)) . '" class="cp-toolbar toolbar">'
            . '<label class="cp-toolbar__field"><span class="form-label">Projekt</span><select class="form-select" name="project_id" data-cp-autosubmit>';
        if ($allowAll) {
            $html .= '<option value="0"' . ($projectId === 0 ? ' selected' : '') . '>Alle Projekte</option>';
        }
        foreach ($projectOptions as $option) {
            $html .= '<option value="' . (int) $option['id'] . '"' . ((int) $option['id'] === $projectId ? ' selected' : '') . '>'
                . CMS_Projects_View::esc($option['name']) . '</option>';
        }
        $html .= '</select></label>';
        foreach ($extraFields as $field) {
            $html .= $field;
        }

        return $html . '<div class="cp-toolbar__actions"><button type="submit" class="btn btn-outline-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Anzeigen</button></div></form>';
    }

    /** @param array{page:int,pages:int,total:int} $pageData */
    private function pagination(array $pageData, string $page, array $query): string
    {
        if ((int) $pageData['pages'] <= 1) {
            return '';
        }

        $current = (int) $pageData['page'];
        $pages = (int) $pageData['pages'];
        $html = '<nav class="cp-pagination" aria-label="Seiten"><ul class="pagination">';
        $link = function (int $target, string $label, bool $disabled = false, bool $active = false) use ($page, $query): string {
            $query['paged'] = $target;
            return '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '">'
                . ($disabled || $active
                    ? '<span class="page-link"' . ($active ? ' aria-current="page"' : '') . '>' . CMS_Projects_View::esc($label) . '</span>'
                    : '<a class="page-link" href="' . CMS_Projects_View::esc($this->url($page, $query)) . '">' . CMS_Projects_View::esc($label) . '</a>')
                . '</li>';
        };

        $html .= $link(max(1, $current - 1), 'Zurück', $current <= 1);
        for ($i = max(1, $current - 3); $i <= min($pages, $current + 3); $i++) {
            $html .= $link($i, (string) $i, false, $i === $current);
        }
        $html .= $link(min($pages, $current + 1), 'Weiter', $current >= $pages);

        return $html . '</ul><span class="cp-muted">' . (int) $pageData['total'] . ' Einträge</span></nav>';
    }

    private function hiddenFields(string $action, int $projectId = 0): string
    {
        return '<input type="hidden" name="csrf_token" value="' . CMS_Projects_View::esc($this->csrfToken()) . '">'
            . '<input type="hidden" name="cms_projects_action" value="' . CMS_Projects_View::esc($action) . '">'
            . ($projectId > 0 ? '<input type="hidden" name="project_id" value="' . $projectId . '">' : '');
    }

    private function url(string $page, array $query = []): string
    {
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
        return '/admin/plugins/' . self::ROOT_SLUG . '/' . rawurlencode($page) . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function formatDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : $value;
    }

    private function formatDate(string $value): string
    {
        $timestamp = $value !== '' ? strtotime($value) : false;
        return $timestamp !== false ? date('d.m.Y', $timestamp) : '';
    }

    private function resolveProjectId(): int
    {
        if ((string) ($_GET['project_id'] ?? '') === '0') {
            return 0;
        }

        $requested = max(0, (int) ($_GET['project_id'] ?? 0));
        $options = $this->service->getProjectOptions();
        foreach ($options as $option) {
            if ((int) $option['id'] === $requested) {
                return $requested;
            }
        }

        return (int) ($options[0]['id'] ?? 0);
    }

    /** @return array<int,string> */
    private function getProjectNameMap(): array
    {
        return array_column($this->service->getProjectOptions(), 'name', 'id');
    }

    private function getSummary(): array
    {
        return $this->summary ??= $this->service->getSummary();
    }

    private function csrfToken(): string
    {
        if ($this->csrfToken === null) {
            $this->csrfToken = class_exists('CMS\\Security') ? \CMS\Security::instance()->generateToken(self::CSRF_ACTION) : '';
        }

        return $this->csrfToken;
    }

    private function currentUserCanManageProjects(): bool
    {
        return class_exists('CMS\\Auth') && \CMS\Auth::isAdmin();
    }
}

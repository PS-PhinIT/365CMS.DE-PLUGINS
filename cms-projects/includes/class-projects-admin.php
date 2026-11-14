<?php
/**
 * Admin-Seiten von CMS Projects im einheitlichen 365CMS-4.0.0-Plugin-Layout.
 *
 * Menü: Übersicht · Projekte (mit Projekt-Reitern Stammdaten/Boards/Widgets/Tickets) · Audit-Log · Einstellungen
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use CMS\Security;

final class CMS_Projects_Admin
{
    public const ROOT_SLUG = 'cms-projects';
    public const CAPABILITY = 'manage_options';
    private const CSRF_ACTION = 'cms_projects_admin';
    private const EXPORT_ACTION = 'cms_projects_export';
    private const NOTICE_KEY = 'cms_projects_admin_notice';

    /** @var array<string,array{label:string,icon:string}> */
    private const PAGES = [
        'cms-projects' => ['label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard'],
        'cms-projects-projects' => ['label' => 'Projekte', 'icon' => 'ti ti-briefcase'],
        'cms-projects-audit' => ['label' => 'Audit-Log', 'icon' => 'ti ti-history'],
        'cms-projects-settings' => ['label' => 'Einstellungen', 'icon' => 'ti ti-settings'],
    ];

    /** @var array<string,array{label:string,icon:string}> */
    private const PROJECT_TABS = [
        'details' => ['label' => 'Stammdaten', 'icon' => 'ti ti-file-description'],
        'boards' => ['label' => 'Boards', 'icon' => 'ti ti-layout-kanban'],
        'widgets' => ['label' => 'Widgets', 'icon' => 'ti ti-layout-grid'],
        'tasks' => ['label' => 'Tickets', 'icon' => 'ti ti-checklist'],
    ];

    public function __construct(private readonly CMS_Projects_Service $service)
    {
    }

    public function registerPages(): void
    {
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        $callback = [$this, 'dispatchPage'];
        add_menu_page('Projekte', '365CMS | Projekte', self::CAPABILITY, self::ROOT_SLUG, $callback, 'ti-layout-kanban', 83);
        foreach (self::PAGES as $slug => $page) {
            add_submenu_page(self::ROOT_SLUG, $page['label'], $page['label'], self::CAPABILITY, $slug, $callback);
        }
    }

    /** Kompatibilität für ältere Aufrufer (Router-Fallbacks, Bookmarks). */
    public function renderOverviewPage(): void
    {
        $_GET['page'] = self::ROOT_SLUG;
        $this->dispatchPage();
    }

    public function renderProjectsPage(): void
    {
        $_GET['page'] = 'cms-projects-projects';
        $this->dispatchPage();
    }

    public function dispatchPage(): void
    {
        if (!function_exists('current_user_can') || !current_user_can(self::CAPABILITY)) {
            header('Location: ' . Security::safeLocalPath('/admin', '/'), true, 303);
            exit;
        }

        $page = $this->currentPage();
        $this->handleExport($page);
        $this->handlePost($page);

        if (!function_exists('cms_plugin_workspace_open') && is_file(ABSPATH . 'admin/partials/plugin-workspace.php')) {
            require_once ABSPATH . 'admin/partials/plugin-workspace.php';
        }

        $summary = $this->service->getSummary();
        $overdue = $this->service->countOverdueTasks();
        $projectId = $page === 'cms-projects-projects' ? max(0, (int) ($_GET['project_id'] ?? 0)) : 0;
        $project = $projectId > 0 ? $this->service->findProject($projectId) : null;
        $tab = $this->currentTab();

        $this->enqueueStyle();
        echo cms_plugin_workspace_open([
            'plugin' => self::ROOT_SLUG,
            'title' => '365CMS | Projekte',
            'icon' => 'ti ti-layout-kanban',
            'version' => CMS_PROJECTS_VERSION,
            'status' => ['label' => (int) $summary['active_projects'] . ' aktiv', 'tone' => (int) $summary['active_projects'] > 0 ? 'success' : 'muted'],
            'description' => 'Projekt-Dashboards mit Boards, Tickets und Widgets für Member- und Public-Bereiche.',
            'stats' => [
                ['label' => 'Projekte', 'value' => (int) $summary['projects'], 'icon' => 'ti ti-briefcase', 'url' => self::pageUrl('cms-projects-projects')],
                ['label' => 'Aktive Projekte', 'value' => (int) $summary['active_projects'], 'icon' => 'ti ti-player-play', 'tone' => 'success', 'url' => self::pageUrl('cms-projects-projects', ['status' => 'active'])],
                ['label' => 'Tickets', 'value' => (int) $summary['tasks'], 'icon' => 'ti ti-checklist'],
                ['label' => 'Überfällig', 'value' => $overdue, 'icon' => 'ti ti-alarm', 'tone' => $overdue > 0 ? 'warning' : 'muted'],
            ],
            'nav' => $this->navItems($page, $project, $tab, (int) $summary['projects']),
            'nav_explicit' => true,
        ]);

        $this->renderNotice();

        match (true) {
            $page === 'cms-projects-projects' && $project !== null => $this->renderProjectTab($project, $tab),
            $page === 'cms-projects-projects' && (string) ($_GET['action'] ?? '') === 'new' => $this->renderProjectTab(null, 'details'),
            $page === 'cms-projects-projects' => $this->renderProjectList(),
            $page === 'cms-projects-audit' => $this->renderAudit(),
            $page === 'cms-projects-settings' => $this->renderSettings(),
            default => $this->renderDashboard($summary, $overdue),
        };

        echo cms_plugin_workspace_close();
    }

    /**
     * @param array<string,int> $summary
     */
    private function renderDashboard(array $summary, int $overdue): void
    {
        $schemaOk = $this->service->installedDbVersion() === CMS_Projects_Service::DB_VERSION;
        $retention = $this->service->getAuditRetentionDays();
        $auditCount = $this->service->countAuditLogs();
        $auditDue = $this->service->countAuditLogsDue();

        $activity = [];
        foreach ($this->service->getRecentTasks(8) as $task) {
            $due = (string) ($task['due_date'] ?? '');
            $isOverdue = $due !== '' && $due < date('Y-m-d');
            $activity[] = [
                'title' => (string) $task['title'],
                'url' => self::pageUrl('cms-projects-projects', ['project_id' => (int) $task['project_id'], 'tab' => 'tasks', 'task_id' => (int) $task['id']]),
                'meta' => (string) ($task['project_name'] ?? '') . ' · ' . (string) ($task['priority_label'] ?? '') . ($due !== '' ? ' · fällig ' . date('d.m.Y', (int) strtotime($due)) : ''),
                'badge' => $isOverdue ? 'überfällig' : (string) ($task['column_key'] ?? ''),
                'tone' => $isOverdue ? 'warning' : 'info',
            ];
        }

        echo cms_plugin_dashboard([
            'intro' => 'Projekte anlegen, Boards strukturieren, Tickets per Drag & Drop steuern und Dashboards für Mitglieder und Öffentlichkeit freigeben.',
            'actions' => [
                ['label' => 'Neues Projekt', 'url' => self::pageUrl('cms-projects-projects', ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Stammdaten, Sichtbarkeit und Headlines festlegen.'],
                ['label' => 'Projekte verwalten', 'url' => self::pageUrl('cms-projects-projects'), 'icon' => 'ti ti-briefcase', 'description' => 'Boards, Widgets und Tickets je Projekt pflegen.'],
                ['label' => 'Öffentliche Projekte', 'url' => '/projects', 'icon' => 'ti ti-world', 'external' => true, 'description' => 'Archiv /projects ansehen.'],
                ['label' => 'Member-Bereich', 'url' => '/member/plugin/projects', 'icon' => 'ti ti-users', 'external' => true, 'description' => 'Projektansicht für Mitglieder.'],
            ],
            'checks' => [
                ['label' => 'Datenbank-Schema', 'status' => $schemaOk ? 'ok' : 'warning', 'value' => $schemaOk ? 'v' . CMS_Projects_Service::DB_VERSION : 'Migration ausstehend', 'hint' => '5 Tabellen (Projekte, Boards, Widgets, Tickets, Audit).', 'url' => self::pageUrl('cms-projects-settings')],
                ['label' => 'Öffentliche Projekte', 'status' => (int) $summary['public_projects'] > 0 ? 'ok' : 'info', 'value' => (int) $summary['public_projects'], 'hint' => 'In Sitemap und Seitensuche gemeldet.'],
                ['label' => 'Überfällige Tickets', 'status' => $overdue > 0 ? 'warning' : 'ok', 'value' => $overdue],
                ['label' => 'Audit-Protokoll', 'status' => $auditDue > 0 ? 'warning' : 'ok', 'value' => number_format($auditCount, 0, ',', '.') . ' Einträge', 'hint' => $retention > 0 ? sprintf('Löschfrist %d Tage (%d fällig).', $retention, $auditDue) : 'Keine Löschfrist gesetzt.', 'url' => self::pageUrl('cms-projects-audit')],
                ['label' => 'Boards / Widgets', 'status' => 'info', 'value' => (int) $summary['boards'] . ' / ' . (int) $summary['widgets']],
            ],
            'activity' => [
                'heading' => 'Zuletzt geänderte Tickets',
                'items' => $activity,
                'empty' => 'Noch keine Tickets vorhanden.',
            ],
        ]);
    }

    private function renderProjectList(): void
    {
        $filters = [
            'status' => $this->requestKey($_GET, 'status'),
            'visibility' => $this->requestKey($_GET, 'visibility'),
            'q' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100)),
        ];
        $projects = $this->service->findProjects($filters['status'], $filters['visibility'], $filters['q']);
        $projectStatuses = $this->service->getProjectStatuses();
        $projectVisibilities = $this->service->getProjectVisibilities();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/projects-list.php';
    }

    /**
     * @param array<string,mixed>|null $project
     */
    private function renderProjectTab(?array $project, string $tab): void
    {
        $service = $this->service;
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);
        $projectId = (int) ($project['id'] ?? 0);
        $tab = $project === null ? 'details' : $tab;

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/project-header.php';
        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/project-' . $tab . '.php';

        if ($tab === 'tasks') {
            $this->enqueueScript('admin-board.js');
        }
    }

    private function renderAudit(): void
    {
        $filters = [
            'project' => max(0, (int) ($_GET['project'] ?? 0)),
            'result' => $this->requestKey($_GET, 'result'),
            'entity' => $this->requestKey($_GET, 'entity'),
        ];
        $logs = $this->service->findAuditLogs(200, $filters['project'], $filters['result'], $filters['entity']);
        $projects = $this->service->getProjects();
        $retentionDays = $this->service->getAuditRetentionDays();

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/audit.php';
    }

    private function renderSettings(): void
    {
        $retentionDays = $this->service->getAuditRetentionDays();
        $auditCount = $this->service->countAuditLogs();
        $auditDue = $this->service->countAuditLogsDue();
        $schemaVersion = $this->service->installedDbVersion();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

        include CMS_PROJECTS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * @param array<string,mixed>|null $project
     * @return list<array<string,mixed>>
     */
    private function navItems(string $page, ?array $project, string $tab, int $projectCount): array
    {
        $items = [];
        foreach (self::PAGES as $slug => $config) {
            if ($slug === 'cms-projects-audit' && $project !== null) {
                $projectId = (int) $project['id'];
                $counts = ['boards' => (int) ($project['board_count'] ?? 0), 'widgets' => (int) ($project['widget_count'] ?? 0), 'tasks' => (int) ($project['task_count'] ?? 0)];
                foreach (self::PROJECT_TABS as $tabKey => $tabConfig) {
                    $items[] = [
                        'slug' => 'project-' . $tabKey,
                        'label' => $tabConfig['label'],
                        'icon' => $tabConfig['icon'],
                        'url' => self::pageUrl('cms-projects-projects', ['project_id' => $projectId, 'tab' => $tabKey]),
                        'active' => $tab === $tabKey,
                        'group' => mb_strimwidth('Projekt: ' . (string) $project['name'], 0, 40, '…'),
                        'badge' => $counts[$tabKey] ?? null,
                    ];
                }
            }

            $items[] = [
                'slug' => $slug,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'url' => self::pageUrl($slug),
                'active' => $slug === $page && ($project === null || $slug !== 'cms-projects-projects'),
                'badge' => $slug === 'cms-projects-projects' ? $projectCount : null,
            ];
        }

        return $items;
    }

    private function handlePost(string $page): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !isset($_POST['cms_projects_action'])) {
            return;
        }

        $projectId = max(0, (int) ($_POST['project_id'] ?? 0));
        if (!Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), self::CSRF_ACTION)) {
            $this->storeNotice(false, 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
            $this->redirect($page, $projectId > 0 ? ['project_id' => $projectId, 'tab' => $this->currentTab()] : []);
        }

        $action = $this->requestKey($_POST, 'cms_projects_action');
        $tab = match ($action) {
            'save_board', 'delete_board' => 'boards',
            'save_widget', 'delete_widget' => 'widgets',
            'save_task', 'delete_task', 'move_task' => 'tasks',
            default => 'details',
        };

        $result = match ($action) {
            'save_project' => $this->service->saveProject($_POST, $projectId > 0 ? $projectId : null),
            'delete_project' => $this->service->deleteProject($projectId),
            'save_board' => $this->service->saveBoard($_POST),
            'delete_board' => $this->service->deleteBoard(max(0, (int) ($_POST['board_id'] ?? 0)), $projectId),
            'save_widget' => $this->service->saveWidget($_POST),
            'delete_widget' => $this->service->deleteWidget(max(0, (int) ($_POST['widget_id'] ?? 0)), $projectId),
            'save_task' => $this->service->saveTask($_POST),
            'delete_task' => $this->service->deleteTask(max(0, (int) ($_POST['task_id'] ?? 0)), $projectId),
            'move_task' => $this->service->moveTask(
                max(0, (int) ($_POST['task_id'] ?? 0)),
                $projectId,
                max(0, (int) ($_POST['target_board_id'] ?? 0)),
                (string) ($_POST['target_column_key'] ?? ''),
                array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['ordered_task_ids'] ?? ''))), static fn (int $id): bool => $id > 0))
            ),
            'save_settings' => $this->service->saveAuditRetentionDays((int) ($_POST['audit_retention_days'] ?? CMS_Projects_Service::AUDIT_RETENTION_DEFAULT)),
            'prune_audit' => ['success' => true, 'message' => sprintf('%d Audit-Einträge gelöscht.', $this->service->pruneAuditLogs())],
            'repair_schema' => $this->repairSchema(),
            default => ['success' => false, 'message' => 'Unbekannte Aktion.'],
        };

        $success = !empty($result['success']);
        $this->storeNotice($success, (string) ($result['message'] ?? ''));

        if ($action === 'delete_project' && $success) {
            $this->redirect('cms-projects-projects');
        }
        if ($action === 'save_project') {
            $savedId = $success ? (int) ($result['id'] ?? $projectId) : $projectId;
            $this->redirect('cms-projects-projects', $savedId > 0 ? ['project_id' => $savedId, 'tab' => 'details'] : ['action' => 'new']);
        }
        if (in_array($action, ['save_settings', 'prune_audit', 'repair_schema'], true)) {
            $this->redirect('cms-projects-settings');
        }

        $query = ['project_id' => $projectId, 'tab' => $tab];
        if (!$success && $action === 'save_task' && (int) ($_POST['task_id'] ?? 0) > 0) {
            $query['task_id'] = (int) $_POST['task_id'];
        }
        $this->redirect('cms-projects-projects', $query);
    }

    /**
     * @return array{success:bool,message:string}
     */
    private function repairSchema(): array
    {
        $this->service->boot(true);

        return $this->service->installedDbVersion() === CMS_Projects_Service::DB_VERSION
            ? ['success' => true, 'message' => 'Tabellen geprüft und aktualisiert.']
            : ['success' => false, 'message' => 'Die Tabellen konnten nicht aktualisiert werden (siehe Fehlerprotokoll).'];
    }

    /**
     * JSON-Export eines Projekts bzw. CSV-Export des Audit-Logs (GET mit persistentem Token).
     */
    private function handleExport(string $page): void
    {
        $format = $this->requestKey($_GET, 'export');
        if ($format === '') {
            return;
        }

        if (!Security::instance()->verifyPersistentToken((string) ($_GET['token'] ?? ''), self::EXPORT_ACTION)) {
            $this->storeNotice(false, 'Export abgelehnt: ungültiges oder abgelaufenes Token.');
            $this->redirect($page);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        if ($format === 'project_json') {
            $data = $this->service->exportProject(max(0, (int) ($_GET['project_id'] ?? 0)));
            if ($data === null) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="projekt-' . preg_replace('/[^a-z0-9-]/', '', (string) ($data['project']['slug'] ?? 'export')) . '-' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            exit;
        }

        if ($format === 'audit_csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="projekte-audit-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'wb');
            if ($out !== false) {
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['ID', 'Zeitpunkt', 'Benutzer', 'Aktion', 'Entität', 'Entität-ID', 'Projekt-ID', 'Ergebnis'], ';', '"', '');
                foreach ($this->service->findAuditLogs(5000, max(0, (int) ($_GET['project'] ?? 0)), $this->requestKey($_GET, 'result'), $this->requestKey($_GET, 'entity')) as $log) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        (string) $log['id'], (string) $log['created_at'], (string) $log['actor_name'], (string) $log['action'],
                        (string) $log['entity_type'], (string) $log['entity_id'], (string) $log['project_id'], (string) $log['result'],
                    ]), ';', '"', '');
                }
                fclose($out);
            }
            exit;
        }

        http_response_code(400);
        exit;
    }

    private function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value) ? "'" . $value : $value;
    }

    /**
     * @param array<string,scalar> $query
     */
    public static function exportUrl(string $format, array $query = []): string
    {
        return self::pageUrl('cms-projects-projects', ['export' => $format, 'token' => Security::instance()->generateToken(self::EXPORT_ACTION)] + $query);
    }

    /**
     * @param array<string,scalar> $query
     */
    public static function pageUrl(string $slug, array $query = []): string
    {
        $slug = array_key_exists($slug, self::PAGES) ? $slug : self::ROOT_SLUG;
        $url = '/admin/plugins/' . self::ROOT_SLUG . '/' . rawurlencode($slug);

        return $query !== [] ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    /**
     * @param array<string,scalar> $query
     */
    public static function projectUrl(int $projectId, string $tab = 'details', array $query = []): string
    {
        return self::pageUrl('cms-projects-projects', ['project_id' => $projectId, 'tab' => $tab] + $query);
    }

    private function currentPage(): string
    {
        $page = strtolower(trim((string) ($_GET['page'] ?? self::ROOT_SLUG)));

        return array_key_exists($page, self::PAGES) ? $page : self::ROOT_SLUG;
    }

    private function currentTab(): string
    {
        $tab = $this->requestKey($_GET, 'tab');

        return array_key_exists($tab, self::PROJECT_TABS) ? $tab : 'details';
    }

    /** Gemeinsame Board-/Widget-Bausteine (style.css) plus Admin-Ergänzungen (admin.css), nur auf Projektseiten. */
    private function enqueueStyle(): void
    {
        foreach (['style.css', 'admin.css'] as $file) {
            $cssFile = CMS_PROJECTS_PLUGIN_DIR . 'assets/css/' . $file;
            if (is_file($cssFile)) {
                echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROJECTS_PLUGIN_URL . 'assets/css/' . $file . '?v=' . (string) filemtime($cssFile), ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }
    }

    private function enqueueScript(string $file): void
    {
        $path = CMS_PROJECTS_PLUGIN_DIR . 'assets/js/' . $file;
        if (is_file($path)) {
            echo '<script src="' . htmlspecialchars(CMS_PROJECTS_PLUGIN_URL . 'assets/js/' . $file . '?v=' . (string) filemtime($path), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }

    private function storeNotice(bool $success, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::NOTICE_KEY] = ['type' => $success ? 'success' : 'danger', 'message' => $message];
        }
    }

    private function renderNotice(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::NOTICE_KEY]) || !is_array($_SESSION[self::NOTICE_KEY])) {
            return;
        }

        $notice = $_SESSION[self::NOTICE_KEY];
        unset($_SESSION[self::NOTICE_KEY]);
        $type = ($notice['type'] ?? '') === 'success' ? 'success' : 'danger';
        echo '<div class="alert alert-' . $type . '" role="' . ($type === 'success' ? 'status' : 'alert') . '">'
            . htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    /**
     * @param array<string,scalar> $query
     */
    private function redirect(string $slug, array $query = []): never
    {
        header('Location: ' . Security::safeLocalPath(self::pageUrl($slug, $query), self::pageUrl(self::ROOT_SLUG)), true, 303);
        exit;
    }

    /**
     * @param array<string,mixed> $source
     */
    private function requestKey(array $source, string $key): string
    {
        $value = isset($source[$key]) && is_string($source[$key]) ? strtolower(trim($source[$key])) : '';

        return (string) preg_replace('/[^a-z0-9_]+/', '', $value);
    }
}

<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Projects_Service
{
    private const MAX_SLUG_LENGTH = 120;
    private const MAX_SHORT_TEXT_LENGTH = 190;
    private const MAX_SUMMARY_LENGTH = 1200;
    private const MAX_DESCRIPTION_LENGTH = 20000;
    private const MAX_COLUMN_KEY_LENGTH = 80;
    private const MAX_PAYLOAD_LENGTH = 65535;

    public function __construct(private readonly CMS_Projects_Repository $repository)
    {
    }

    public const DB_VERSION = '2';
    public const DB_VERSION_OPTION = 'cms_projects_db_version';
    public const AUDIT_RETENTION_OPTION = 'cms_projects_audit_retention_days';
    public const AUDIT_RETENTION_DEFAULT = 365;

    /**
     * Tabellen nur anlegen/migrieren, wenn sich die Schema-Version geändert hat
     * (Autoload-Option über OptionStore – keine zusätzliche Abfrage pro Request).
     */
    public function boot(bool $force = false): void
    {
        if (!$force && $this->installedDbVersion() === self::DB_VERSION) {
            return;
        }

        try {
            $this->repository->ensureTables();
            if (function_exists('update_option')) {
                update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
            }
        } catch (\Throwable $e) {
            error_log('[cms-projects] Tabellen konnten nicht angelegt werden: ' . $e->getMessage());
        }
    }

    public function installedDbVersion(): string
    {
        if (class_exists('CMS\\Services\\OptionStore')) {
            return (string) \CMS\Services\OptionStore::getInstance()->get(self::DB_VERSION_OPTION, '');
        }

        return function_exists('get_option') ? (string) get_option(self::DB_VERSION_OPTION, '') : '';
    }

    /** Deinstallation: Tabellen und Optionen entfernen. */
    public function uninstall(): void
    {
        try {
            $this->repository->dropTables();
            $db = \CMS\Database::instance();
            $db->execute("DELETE FROM {$db->getPrefix()}settings WHERE option_name IN (?, ?)", [self::DB_VERSION_OPTION, self::AUDIT_RETENTION_OPTION]);
        } catch (\Throwable $e) {
            error_log('[cms-projects] Deinstallation fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function getAuditRetentionDays(): int
    {
        $value = class_exists('CMS\\Services\\OptionStore')
            ? \CMS\Services\OptionStore::getInstance()->get(self::AUDIT_RETENTION_OPTION)
            : (function_exists('get_option') ? get_option(self::AUDIT_RETENTION_OPTION) : null);

        return $value === null || $value === '' ? self::AUDIT_RETENTION_DEFAULT : max(0, min(3650, (int) $value));
    }

    public function saveAuditRetentionDays(int $days): array
    {
        $days = max(0, min(3650, $days));
        if (!function_exists('update_option') || !update_option(self::AUDIT_RETENTION_OPTION, (string) $days)) {
            return ['success' => false, 'message' => 'Die Aufbewahrungsfrist konnte nicht gespeichert werden.'];
        }

        return ['success' => true, 'message' => $days > 0 ? sprintf('Audit-Einträge werden nach %d Tagen gelöscht.', $days) : 'Audit-Einträge werden unbegrenzt aufbewahrt.'];
    }

    public function countAuditLogs(): int
    {
        return $this->repository->countAuditLogs();
    }

    public function countAuditLogsDue(): int
    {
        return $this->repository->countAuditLogsOlderThan($this->getAuditRetentionDays());
    }

    public function pruneAuditLogs(): int
    {
        return $this->repository->deleteAuditLogsOlderThan($this->getAuditRetentionDays());
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findAuditLogs(int $limit = 200, int $projectId = 0, string $result = '', string $entityType = ''): array
    {
        return $this->repository->findAuditLogs($limit, $projectId, $result, preg_replace('/[^a-z_]/', '', $entityType) ?? '');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findProjects(string $status = '', string $visibility = '', string $search = ''): array
    {
        $status = isset($this->getProjectStatuses()[$status]) ? $status : '';
        $visibility = isset($this->getProjectVisibilities()[$visibility]) ? $visibility : '';

        return $this->decorateProjects($this->repository->findProjects($status, $visibility, $search));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getRecentTasks(int $limit = 8): array
    {
        return array_map(function (array $task): array {
            $task['priority_label'] = (string) ($this->getTaskPriorities()[$task['priority'] ?? 'medium'] ?? 'Mittel');
            return $task;
        }, $this->repository->getRecentTasks($limit));
    }

    /**
     * @param list<string> $words
     * @return list<array<string,mixed>>
     */
    public function searchPublicProjects(array $words, int $limit = 10): array
    {
        return $this->repository->searchPublicProjects($words, $limit);
    }

    public function countOverdueTasks(): int
    {
        return $this->repository->countOverdueTasks();
    }

    public function findBoard(int $boardId): ?array
    {
        $board = $boardId > 0 ? $this->repository->findBoardById($boardId) : null;
        if ($board === null) {
            return null;
        }
        $board['payload_data'] = $this->sanitizeBoardPayload($this->decodePayload((string) ($board['payload'] ?? '')));

        return $board;
    }

    public function findWidget(int $widgetId): ?array
    {
        $widget = $widgetId > 0 ? $this->repository->findWidgetById($widgetId) : null;
        if ($widget === null) {
            return null;
        }
        $widget['payload_data'] = $this->sanitizeWidgetPayload((string) ($widget['widget_type'] ?? 'text'), $this->decodePayload((string) ($widget['payload'] ?? '')));

        return $widget;
    }

    public function deleteProject(int $projectId): array
    {
        $project = $this->findProject($projectId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Das Projekt wurde nicht gefunden.'];
        }

        $ok = $this->repository->deleteProjectCascade($projectId);
        $this->recordAuditLog('project.delete', 'project', $projectId, $projectId, $ok, ['slug' => (string) ($project['slug'] ?? '')]);

        return $ok
            ? ['success' => true, 'message' => 'Projekt inkl. Boards, Widgets und Tickets gelöscht.']
            : ['success' => false, 'message' => 'Projekt konnte nicht gelöscht werden.'];
    }

    public function deleteBoard(int $boardId, int $projectId): array
    {
        $board = $this->repository->findBoardById($boardId);
        if ($board === null || (int) ($board['project_id'] ?? 0) !== $projectId) {
            return ['success' => false, 'message' => 'Das Board wurde nicht gefunden.'];
        }

        $ok = $this->repository->deleteBoardCascade($boardId);
        $this->recordAuditLog('board.delete', 'board', $boardId, $projectId, $ok, []);

        return $ok
            ? ['success' => true, 'message' => 'Board inkl. seiner Tickets gelöscht.']
            : ['success' => false, 'message' => 'Board konnte nicht gelöscht werden.'];
    }

    public function deleteWidget(int $widgetId, int $projectId): array
    {
        $widget = $this->repository->findWidgetById($widgetId);
        if ($widget === null || (int) ($widget['project_id'] ?? 0) !== $projectId) {
            return ['success' => false, 'message' => 'Das Widget wurde nicht gefunden.'];
        }

        $ok = $this->repository->deleteWidget($widgetId);
        $this->recordAuditLog('widget.delete', 'widget', $widgetId, $projectId, $ok, []);

        return $ok
            ? ['success' => true, 'message' => 'Widget gelöscht.']
            : ['success' => false, 'message' => 'Widget konnte nicht gelöscht werden.'];
    }

    /**
     * Vollständiger Projekt-Export (Stammdaten, Boards, Widgets, Tickets) als Array für JSON.
     *
     * @return array<string,mixed>|null
     */
    public function exportProject(int $projectId): ?array
    {
        $project = $this->repository->findProjectById($projectId);
        if ($project === null) {
            return null;
        }

        $strip = static function (array $row): array {
            unset($row['payload']);
            return $row;
        };

        return [
            'format' => 'cms-projects-export',
            'version' => 1,
            'exported_at' => date('c'),
            'project' => $project,
            'boards' => array_map(fn (array $b): array => $strip($b) + ['payload' => $this->decodePayload((string) ($b['payload'] ?? ''))], $this->repository->getBoardsByProject($projectId)),
            'widgets' => array_map(fn (array $w): array => $strip($w) + ['payload' => $this->decodePayload((string) ($w['payload'] ?? ''))], $this->repository->getWidgetsByProject($projectId)),
            'tasks' => array_map($strip, $this->repository->getTasksByProject($projectId)),
        ];
    }

    /**
     * CSP-konformer Farbpunkt mit Projekt-Akzentfarbe: Die validierte Farbe wird als Klasse
     * über einen nonce-geschützten <style>-Block gesetzt (keine style-Attribute).
     */
    public static function accentSwatch(array $project, string $class = 'cp-project-color'): string
    {
        static $emitted = [];
        $color = strtolower((string) ($project['accent_color'] ?? '#2563eb'));
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
            $color = '#2563eb';
        }
        $token = substr($color, 1);
        $html = '';
        if (!isset($emitted[$token])) {
            $emitted[$token] = true;
            $nonce = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
            $html .= '<style' . $nonce . '>.cp-accent-' . $token . '{--cp-project-accent:' . $color . '}</style>';
        }

        return $html . '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' cp-accent-' . $token . '" aria-hidden="true"></span>';
    }

    public function getSummary(): array
    {
        return $this->repository->getSummaryCounts();
    }

    public function getBoardTypes(): array
    {
        return [
            'kanban' => ['label' => 'Kanban', 'description' => 'Backlog, laufende Arbeit und Übergaben im Sprintfluss.'],
            'diy' => ['label' => 'DIY', 'description' => 'Selbstbau-, Aufgaben- und Material-Tracking mit klaren Arbeitspaketen.'],
            'miro' => ['label' => 'MIRO', 'description' => 'Freies Ideen- und Whiteboard-Cluster für Workshops und Brainstorming.'],
            'flow' => ['label' => 'FLOW', 'description' => 'Kontinuierlicher Durchsatz mit Fokus auf Blocker und Engpässe.'],
            'roadmap' => ['label' => 'Roadmap', 'description' => 'Zeitliche Planung nach Quartalen, Phasen oder Releases.'],
        ];
    }

    public function getWidgetTypes(): array
    {
        return [
            'text' => ['label' => 'Text', 'description' => 'Freier Beschreibungstext oder Status-Update.'],
            'checklist' => ['label' => 'Checkliste', 'description' => 'Abhakbare Listen für Deliverables und To-dos.'],
            'links' => ['label' => 'Links', 'description' => 'Schnellzugriffe auf Dokumente, Tickets und Ressourcen.'],
            'stats' => ['label' => 'Kennzahlen', 'description' => 'Projektmetriken wie Fortschritt, Budget oder offene Punkte.'],
            'timeline' => ['label' => 'Timeline', 'description' => 'Wichtige Meilensteine und Termine.'],
        ];
    }

    public function getProjectStatuses(): array
    {
        return ['draft' => 'Entwurf', 'active' => 'Aktiv', 'paused' => 'Pausiert', 'archived' => 'Archiviert'];
    }

    public function getProjectVisibilities(): array
    {
        return ['public' => 'Public', 'member' => 'Member', 'private' => 'Privat'];
    }

    public function getWidgetScopes(): array
    {
        return ['public' => 'Nur Public', 'member' => 'Nur Member', 'both' => 'Public + Member'];
    }

    public function getTaskPriorities(): array
    {
        return [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'critical' => 'Kritisch',
        ];
    }

    public function getProjects(): array
    {
        $projects = $this->repository->getProjects();
        return $this->decorateProjects($projects);
    }

    public function getPublicProjects(): array
    {
        $projects = $this->repository->getPublicProjects();
        return $this->decorateProjects($projects);
    }

    public function getMemberProjects(): array
    {
        $projects = $this->repository->getMemberProjects();
        return $this->decorateProjects($projects);
    }

    public function findProject(int $id): ?array
    {
        $project = $this->repository->findProjectById($id);
        return $project !== null ? $this->decorateProject($project) : null;
    }

    public function findProjectBySlug(string $slug): ?array
    {
        $project = $this->repository->findProjectBySlug($this->normalizeSlug($slug));
        return $project !== null ? $this->decorateProject($project) : null;
    }

    public function getProjectBoards(int $projectId, string $scope = 'admin'): array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $boards = $this->repository->getBoardsByProject($projectId, $scope === 'admin', $scope === 'public' ? true : null);
        $tasksByBoard = $this->groupTasksByBoard($this->getProjectTasks($projectId, $scope));

        return array_map(function (array $board) use ($tasksByBoard): array {
            $board['payload_data'] = $this->sanitizeBoardPayload($this->decodePayload((string) ($board['payload'] ?? '')));
            $typeConfig = $this->getBoardTypes()[$board['board_type'] ?? 'kanban'] ?? ['label' => 'Board', 'description' => ''];
            $board['board_label'] = (string) ($typeConfig['label'] ?? 'Board');
            $board['tasks'] = $tasksByBoard[(int) ($board['id'] ?? 0)] ?? [];
            $board['task_count'] = count($board['tasks']);
            $board['payload_data'] = $this->mergeTasksIntoBoardPayload($board['payload_data'], $board['tasks']);
            return $board;
        }, $boards);
    }

    public function getProjectWidgets(int $projectId, string $scope = 'admin'): array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $widgets = $this->repository->getWidgetsByProject($projectId, $scope, $scope === 'admin');
        return array_map(function (array $widget): array {
            $widget['payload_data'] = $this->sanitizeWidgetPayload((string) ($widget['widget_type'] ?? 'text'), $this->decodePayload((string) ($widget['payload'] ?? '')));
            $widgetType = $this->getWidgetTypes()[$widget['widget_type'] ?? 'text'] ?? ['label' => 'Widget', 'description' => ''];
            $widget['widget_label'] = (string) ($widgetType['label'] ?? 'Widget');
            return $widget;
        }, $widgets);
    }

    public function getProjectDashboardPayload(int $projectId, string $scope): ?array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $project = $this->findProject($projectId);
        if ($project === null) {
            return null;
        }

        if (!$this->canAccessProjectScope($project, $scope)) {
            return null;
        }

        return [
            'project' => $project,
            'boards' => $this->getProjectBoards($projectId, $scope),
            'widgets' => $this->getProjectWidgets($projectId, $scope),
        ];
    }

    public function getProjectTasks(int $projectId, string $scope = 'admin'): array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $tasks = $this->repository->getTasksByProject($projectId, $scope, $scope === 'admin');

        return array_map(function (array $task): array {
            $task['priority_label'] = (string) ($this->getTaskPriorities()[$task['priority'] ?? 'medium'] ?? 'Mittel');
            $task['column_key'] = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
            $task['payload_data'] = $this->decodePayload((string) ($task['payload'] ?? ''));
            return $task;
        }, $tasks);
    }

    public function findTask(int $taskId): ?array
    {
        if ($taskId <= 0) {
            return null;
        }

        $task = $this->repository->findTaskById($taskId);
        if ($task === null) {
            return null;
        }

        $task['priority_label'] = (string) ($this->getTaskPriorities()[$task['priority'] ?? 'medium'] ?? 'Mittel');
        $task['column_key'] = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
        $task['payload_data'] = $this->decodePayload((string) ($task['payload'] ?? ''));
        return $task;
    }

    public function saveTask(array $input): array
    {
        $taskId = max(0, (int) ($input['task_id'] ?? 0));
        $projectId = (int) ($input['project_id'] ?? 0);
        $project = $this->findProject($projectId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Projekt für das Ticket wurde nicht gefunden.'];
        }

        $existingTask = $taskId > 0 ? $this->findTask($taskId) : null;
        if ($taskId > 0 && ($existingTask === null || (int) ($existingTask['project_id'] ?? 0) !== $projectId)) {
            return ['success' => false, 'message' => 'Das zu bearbeitende Ticket wurde nicht gefunden.'];
        }

        $boardId = (int) ($input['board_id'] ?? 0);
        $board = $this->findBoardForProject($projectId, $boardId);
        if ($board === null) {
            return ['success' => false, 'message' => 'Board für das Ticket wurde nicht gefunden.'];
        }

        $title = $this->sanitizeText((string) ($input['title'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
        if ($title === '') {
            return ['success' => false, 'message' => 'Ticket-Titel ist erforderlich.'];
        }

        $priority = (string) ($input['priority'] ?? 'medium');
        if (!isset($this->getTaskPriorities()[$priority])) {
            $priority = 'medium';
        }

        $columnKey = $this->resolveBoardColumnKey($board, (string) ($input['column_key'] ?? ''));
        if ($columnKey === '') {
            return ['success' => false, 'message' => 'Für das Ticket wurde keine gültige Board-Spalte gefunden.'];
        }

        $dueDate = $this->normalizeDueDate((string) ($input['due_date'] ?? ''));
        if ((string) ($input['due_date'] ?? '') !== '' && $dueDate === null) {
            return ['success' => false, 'message' => 'Das Fälligkeitsdatum ist ungültig.'];
        }

        $saveId = $this->repository->saveTask([
            'project_id' => $projectId,
            'board_id' => $boardId,
            'column_key' => $columnKey,
            'title' => $title,
            'description' => $this->sanitizeMultilineText((string) ($input['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH),
            'priority' => $priority,
            'assignee_name' => $this->sanitizeText((string) ($input['assignee_name'] ?? ''), self::MAX_SHORT_TEXT_LENGTH),
            'due_date' => $dueDate ?? '',
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'is_public' => $this->toBooleanFlag($input['is_public'] ?? 0),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => json_encode((array) (($existingTask['payload_data'] ?? []) ?: []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $taskId > 0 ? $taskId : null);

        if ($saveId === false) {
            $this->logOperationFailure('save_task', ['project_id' => $projectId, 'task_id' => $taskId]);
            $this->recordAuditLog(
                $taskId > 0 ? 'task.update' : 'task.create',
                'task',
                $taskId,
                $projectId,
                false,
                ['board_id' => $boardId, 'column_key' => $columnKey]
            );
            return ['success' => false, 'message' => 'Ticket konnte nicht gespeichert werden.'];
        }

        $this->recordAuditLog(
            $taskId > 0 ? 'task.update' : 'task.create',
            'task',
            (int) $saveId,
            $projectId,
            true,
            ['board_id' => $boardId, 'column_key' => $columnKey]
        );
        return ['success' => true, 'message' => $taskId > 0 ? 'Ticket wurde aktualisiert.' : 'Ticket wurde gespeichert.', 'id' => (int) $saveId];
    }

    public function deleteTask(int $taskId, int $projectId): array
    {
        $task = $this->findTask($taskId);
        if ($task === null || (int) ($task['project_id'] ?? 0) !== $projectId) {
            return ['success' => false, 'message' => 'Das Ticket wurde nicht gefunden.'];
        }

        if (!$this->repository->deleteTask($taskId)) {
            $this->logOperationFailure('delete_task', ['project_id' => $projectId, 'task_id' => $taskId]);
            $this->recordAuditLog('task.delete', 'task', $taskId, $projectId, false, []);
            return ['success' => false, 'message' => 'Ticket konnte nicht gelöscht werden.'];
        }

        $this->recordAuditLog('task.delete', 'task', $taskId, $projectId, true, []);
        return ['success' => true, 'message' => 'Ticket wurde gelöscht.', 'id' => $taskId];
    }

    public function moveTask(int $taskId, int $projectId, int $targetBoardId, string $targetColumnKey, array $orderedTaskIds = []): array
    {
        $task = $this->findTask($taskId);
        if ($task === null || (int) ($task['project_id'] ?? 0) !== $projectId) {
            return ['success' => false, 'message' => 'Das zu verschiebende Ticket wurde nicht gefunden.'];
        }

        $board = $this->findBoardForProject($projectId, $targetBoardId);
        if ($board === null) {
            return ['success' => false, 'message' => 'Ziel-Board wurde nicht gefunden.'];
        }

        $targetColumnKey = $this->normalizeColumnKey($targetColumnKey);
        if ($targetColumnKey === '') {
            return ['success' => false, 'message' => 'Ziel-Spalte ist ungültig.'];
        }

        $resolvedColumnKey = $this->resolveBoardColumnKey($board, $targetColumnKey);
        if ($resolvedColumnKey === '') {
            return ['success' => false, 'message' => 'Ziel-Spalte wurde im Board nicht gefunden.'];
        }

        $sourceBoardId = (int) ($task['board_id'] ?? 0);
        $sourceColumnKey = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
        $allTasks = $this->getProjectTasks($projectId, 'admin');
        $targetTasks = $this->getColumnTasks($allTasks, $targetBoardId, $resolvedColumnKey, $taskId);
        $finalTargetTaskIds = $this->buildOrderedTaskIds($orderedTaskIds, $targetTasks, $taskId);

        if (!$this->persistTaskOrder($finalTargetTaskIds, $targetBoardId, $resolvedColumnKey)) {
            $this->logOperationFailure('move_task_target', ['project_id' => $projectId, 'task_id' => $taskId, 'board_id' => $targetBoardId, 'column_key' => $resolvedColumnKey]);
            $this->recordAuditLog('task.move', 'task', $taskId, $projectId, false, [
                'target_board_id' => $targetBoardId,
                'target_column_key' => $resolvedColumnKey,
            ]);
            return ['success' => false, 'message' => 'Ticket konnte nicht verschoben werden.'];
        }

        if ($sourceBoardId !== $targetBoardId || $sourceColumnKey !== $resolvedColumnKey) {
            $sourceTasks = $this->getColumnTasks($allTasks, $sourceBoardId, $sourceColumnKey, $taskId);
            $sourceTaskIds = array_map(static fn (array $columnTask): int => (int) ($columnTask['id'] ?? 0), $sourceTasks);

            if (!$this->persistTaskOrder($sourceTaskIds, $sourceBoardId, $sourceColumnKey)) {
                $this->logOperationFailure('move_task_source', ['project_id' => $projectId, 'task_id' => $taskId, 'board_id' => $sourceBoardId, 'column_key' => $sourceColumnKey]);
                $this->recordAuditLog('task.move', 'task', $taskId, $projectId, false, [
                    'source_board_id' => $sourceBoardId,
                    'source_column_key' => $sourceColumnKey,
                    'target_board_id' => $targetBoardId,
                    'target_column_key' => $resolvedColumnKey,
                ]);
                return ['success' => false, 'message' => 'Quell-Spalte konnte nach dem Verschieben nicht neu sortiert werden.'];
            }
        }

        $this->recordAuditLog('task.move', 'task', $taskId, $projectId, true, [
            'source_board_id' => $sourceBoardId,
            'source_column_key' => $sourceColumnKey,
            'target_board_id' => $targetBoardId,
            'target_column_key' => $resolvedColumnKey,
        ]);
        return ['success' => true, 'message' => 'Ticket wurde verschoben.', 'id' => $taskId];
    }

    public function getTaskDefaults(): array
    {
        return [
            'id' => 0,
            'project_id' => 0,
            'board_id' => 0,
            'column_key' => '',
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'priority_label' => 'Mittel',
            'assignee_name' => '',
            'due_date' => '',
            'sort_order' => 0,
            'is_public' => 0,
            'is_active' => 1,
            'payload_data' => [],
        ];
    }

    public function getProjectDefaults(): array
    {
        return [
            'id' => 0,
            'name' => '',
            'slug' => '',
            'summary' => '',
            'description' => '',
            'status' => 'active',
            'visibility' => 'member',
            'owner_name' => '',
            'accent_color' => '#2563eb',
            'member_headline' => 'Projekt-Dashboard für Mitglieder',
            'public_headline' => 'Öffentliche Projektübersicht',
        ];
    }

    public function saveProject(array $input, ?int $id = null): array
    {
        $name = $this->sanitizeText((string) ($input['name'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
        $slug = $this->normalizeSlug((string) ($input['slug'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'message' => 'Projektname ist erforderlich.'];
        }

        if ($slug === '') {
            $slug = $this->normalizeSlug($name);
        }

        if ($slug === '') {
            return ['success' => false, 'message' => 'Für das Projekt konnte kein gültiger Slug erzeugt werden.'];
        }

        $existing = $this->findProjectBySlug($slug);
        if ($existing !== null && $id !== (int) ($existing['id'] ?? 0)) {
            return ['success' => false, 'message' => 'Der Projekt-Slug ist bereits vergeben.'];
        }

        $status = (string) ($input['status'] ?? 'active');
        if (!isset($this->getProjectStatuses()[$status])) {
            $status = 'active';
        }

        $visibility = (string) ($input['visibility'] ?? 'member');
        if (!isset($this->getProjectVisibilities()[$visibility])) {
            $visibility = 'member';
        }

        $saveId = $this->repository->saveProject([
            'name' => $name,
            'slug' => $slug,
            'summary' => $this->sanitizeMultilineText((string) ($input['summary'] ?? ''), self::MAX_SUMMARY_LENGTH),
            'description' => $this->sanitizeMultilineText((string) ($input['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH),
            'status' => $status,
            'visibility' => $visibility,
            'owner_name' => $this->sanitizeText((string) ($input['owner_name'] ?? ''), self::MAX_SHORT_TEXT_LENGTH),
            'accent_color' => $this->normalizeColor((string) ($input['accent_color'] ?? '#2563eb')),
            'member_headline' => $this->sanitizeText((string) ($input['member_headline'] ?? ''), self::MAX_SHORT_TEXT_LENGTH),
            'public_headline' => $this->sanitizeText((string) ($input['public_headline'] ?? ''), self::MAX_SHORT_TEXT_LENGTH),
        ], $id);

        if ($saveId === false) {
            $this->logOperationFailure('save_project', ['project_id' => $id ?? 0, 'slug' => $slug]);
            $this->recordAuditLog(
                $id !== null ? 'project.update' : 'project.create',
                'project',
                (int) ($id ?? 0),
                (int) ($id ?? 0),
                false,
                ['slug' => $slug]
            );
            return ['success' => false, 'message' => 'Projekt konnte nicht gespeichert werden.'];
        }

        $this->recordAuditLog(
            $id !== null ? 'project.update' : 'project.create',
            'project',
            (int) $saveId,
            (int) $saveId,
            true,
            ['slug' => $slug]
        );
        return ['success' => true, 'message' => 'Projekt wurde gespeichert.', 'id' => (int) $saveId];
    }

    public function saveBoard(array $input): array
    {
        $projectId = (int) ($input['project_id'] ?? 0);
        $project = $this->findProject($projectId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Projekt für das Board wurde nicht gefunden.'];
        }

        $boardType = (string) ($input['board_type'] ?? 'kanban');
        if (!isset($this->getBoardTypes()[$boardType])) {
            $boardType = 'kanban';
        }

        $title = $this->sanitizeText((string) ($input['title'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
        if ($title === '') {
            return ['success' => false, 'message' => 'Board-Titel ist erforderlich.'];
        }

        $payload = trim((string) ($input['payload'] ?? ''));
        if (strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return ['success' => false, 'message' => 'Board-Payload ist zu groß.'];
        }
        if ($payload === '') {
            $payload = json_encode($this->getDefaultBoardPayload($boardType), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $decoded = $this->parsePayload($payload);
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Board-Payload muss gültiges JSON sein.'];
        }

        $decoded = $this->sanitizeBoardPayload($decoded);

        $boardId = max(0, (int) ($input['board_id'] ?? 0));
        if ($boardId > 0) {
            $existing = $this->repository->findBoardById($boardId);
            if ($existing === null || (int) ($existing['project_id'] ?? 0) !== $projectId) {
                return ['success' => false, 'message' => 'Das zu bearbeitende Board wurde nicht gefunden.'];
            }
        }

        $saveId = $this->repository->saveBoard([
            'project_id' => $projectId,
            'board_type' => $boardType,
            'title' => $title,
            'description' => $this->sanitizeMultilineText((string) ($input['description'] ?? ''), self::MAX_SUMMARY_LENGTH),
            'position' => max(0, (int) ($input['position'] ?? 0)),
            'is_public' => $this->toBooleanFlag($input['is_public'] ?? 0),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $boardId > 0 ? $boardId : null);

        $auditAction = $boardId > 0 ? 'board.update' : 'board.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_board', ['project_id' => $projectId, 'board_type' => $boardType]);
            $this->recordAuditLog($auditAction, 'board', $boardId, $projectId, false, ['board_type' => $boardType]);
            return ['success' => false, 'message' => 'Board konnte nicht gespeichert werden.'];
        }

        $this->recordAuditLog($auditAction, 'board', (int) $saveId, $projectId, true, ['board_type' => $boardType]);
        return ['success' => true, 'message' => 'Board wurde gespeichert.', 'id' => (int) $saveId];
    }

    public function saveWidget(array $input): array
    {
        $projectId = (int) ($input['project_id'] ?? 0);
        $project = $this->findProject($projectId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Projekt für das Widget wurde nicht gefunden.'];
        }

        $widgetType = (string) ($input['widget_type'] ?? 'text');
        if (!isset($this->getWidgetTypes()[$widgetType])) {
            $widgetType = 'text';
        }

        $scope = (string) ($input['scope'] ?? 'both');
        if (!isset($this->getWidgetScopes()[$scope])) {
            $scope = 'both';
        }

        $title = $this->sanitizeText((string) ($input['title'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
        if ($title === '') {
            return ['success' => false, 'message' => 'Widget-Titel ist erforderlich.'];
        }

        $payload = trim((string) ($input['payload'] ?? ''));
        if (strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return ['success' => false, 'message' => 'Widget-Payload ist zu groß.'];
        }
        if ($payload === '') {
            $payload = json_encode($this->getDefaultWidgetPayload($widgetType), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $decoded = $this->parsePayload($payload);
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Widget-Payload muss gültiges JSON sein.'];
        }

        $decoded = $this->sanitizeWidgetPayload($widgetType, $decoded);

        $widgetId = max(0, (int) ($input['widget_id'] ?? 0));
        if ($widgetId > 0) {
            $existing = $this->repository->findWidgetById($widgetId);
            if ($existing === null || (int) ($existing['project_id'] ?? 0) !== $projectId) {
                return ['success' => false, 'message' => 'Das zu bearbeitende Widget wurde nicht gefunden.'];
            }
        }

        $saveId = $this->repository->saveWidget([
            'project_id' => $projectId,
            'scope' => $scope,
            'widget_type' => $widgetType,
            'title' => $title,
            'content' => $this->sanitizeMultilineText((string) ($input['content'] ?? ''), self::MAX_DESCRIPTION_LENGTH),
            'position' => max(0, (int) ($input['position'] ?? 0)),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $widgetId > 0 ? $widgetId : null);

        $auditAction = $widgetId > 0 ? 'widget.update' : 'widget.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_widget', ['project_id' => $projectId, 'widget_type' => $widgetType]);
            $this->recordAuditLog($auditAction, 'widget', $widgetId, $projectId, false, ['widget_type' => $widgetType, 'scope' => $scope]);
            return ['success' => false, 'message' => 'Widget konnte nicht gespeichert werden.'];
        }

        $this->recordAuditLog($auditAction, 'widget', (int) $saveId, $projectId, true, ['widget_type' => $widgetType, 'scope' => $scope]);
        return ['success' => true, 'message' => 'Widget wurde gespeichert.', 'id' => (int) $saveId];
    }

    public function getProjectPreviewLinks(array $project): array
    {
        $slug = (string) ($project['slug'] ?? '');
        return [
            'public' => $slug !== '' ? '/projects/' . rawurlencode($slug) : '/projects',
            'member' => $slug !== '' ? '/member/plugin/projects?project=' . rawurlencode($slug) : '/member/plugin/projects',
        ];
    }

    public function getAdminAuditLogs(int $limit = 100): array
    {
        $logs = $this->repository->getAuditLogs($limit);

        return array_map(function (array $log): array {
            $context = $this->decodePayload((string) ($log['context_json'] ?? ''));
            $log['context'] = is_array($context) ? $context : [];
            return $log;
        }, $logs);
    }

    private function decorateProjects(array $projects): array
    {
        if ($projects === []) {
            return [];
        }

        $counts = $this->repository->getProjectEntityCounts(array_column($projects, 'id'));

        return array_map(function (array $project) use ($counts): array {
            $projectId = (int) ($project['id'] ?? 0);
            $aggregate = $counts[$projectId] ?? ['board_count' => 0, 'widget_count' => 0, 'task_count' => 0];
            $project['board_count'] = (int) ($aggregate['board_count'] ?? 0);
            $project['widget_count'] = (int) ($aggregate['widget_count'] ?? 0);
            $project['task_count'] = (int) ($aggregate['task_count'] ?? 0);
            $project['preview_links'] = $this->getProjectPreviewLinks($project);
            return $project;
        }, $projects);
    }

    private function decorateProject(array $project): array
    {
        $projects = $this->decorateProjects([$project]);
        return $projects[0] ?? $project;
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\-\_]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
        return substr($value, 0, self::MAX_SLUG_LENGTH);
    }

    private function normalizeColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return strtolower($value);
        }
        return '#2563eb';
    }

    private function decodePayload(string $payload): array
    {
        $decoded = $this->parsePayload($payload);
        return $decoded ?? [];
    }

    private function parsePayload(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        if (strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeDashboardScope(string $scope): string
    {
        return in_array($scope, ['admin', 'member', 'public'], true) ? $scope : 'public';
    }

    private function canAccessProjectScope(array $project, string $scope): bool
    {
        $visibility = (string) ($project['visibility'] ?? 'member');
        $status = (string) ($project['status'] ?? 'draft');

        return match ($scope) {
            'admin' => true,
            'member' => in_array($visibility, ['member', 'public'], true) && in_array($status, ['active', 'paused'], true),
            default => $visibility === 'public' && $status === 'active',
        };
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        return $this->truncate($value, $maxLength);
    }

    private function sanitizeMultilineText(string $value, int $maxLength): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", strip_tags($value)));
        return $this->truncate($value, $maxLength);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }

    private function sanitizeBoardPayload(array $payload): array
    {
        foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return [$key => $this->sanitizeBoardGroups($payload[$key])];
            }
        }

        return ['columns' => []];
    }

    private function sanitizeBoardGroups(array $groups): array
    {
        $sanitized = [];

        foreach (array_values($groups) as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                $label = $this->sanitizeText((string) $item, self::MAX_SHORT_TEXT_LENGTH);
                if ($label !== '') {
                    $items[] = $label;
                }
            }

            $title = $this->sanitizeText((string) ($group['title'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
            if ($title === '' && $items === []) {
                continue;
            }

            $sanitized[] = [
                'key' => $this->buildColumnKey((string) ($group['key'] ?? ''), $title, $index),
                'title' => $title !== '' ? $title : 'Block',
                'wip_limit' => $this->normalizeWipLimit($group['wip_limit'] ?? null),
                'items' => $items,
            ];
        }

        return $sanitized;
    }

    private function groupTasksByBoard(array $tasks): array
    {
        $grouped = [];

        foreach ($tasks as $task) {
            $boardId = (int) ($task['board_id'] ?? 0);
            if ($boardId <= 0) {
                continue;
            }

            $grouped[$boardId][] = $task;
        }

        return $grouped;
    }

    private function mergeTasksIntoBoardPayload(array $payload, array $tasks): array
    {
        $taskGroups = [];
        foreach ($tasks as $task) {
            $columnKey = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
            if ($columnKey === '') {
                continue;
            }

            $taskGroups[$columnKey][] = $task;
        }

        foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $payload[$key] = array_map(function (array $group, int $index) use ($taskGroups): array {
                $columnKey = $this->buildColumnKey((string) ($group['key'] ?? ''), (string) ($group['title'] ?? 'Block'), $index);
                $wipLimit = $this->normalizeWipLimit($group['wip_limit'] ?? null);
                $tasks = $taskGroups[$columnKey] ?? [];
                $wipCount = count($tasks);
                $group['key'] = $columnKey;
                $group['wip_limit'] = $wipLimit;
                $group['wip_count'] = $wipCount;
                $group['wip_limit_reached'] = $wipLimit > 0 && $wipCount >= $wipLimit;
                $group['wip_over_limit'] = $wipLimit > 0 && $wipCount > $wipLimit;
                $group['tasks'] = $tasks;
                return $group;
            }, array_values($payload[$key]), array_keys(array_values($payload[$key])));

            break;
        }

        return $payload;
    }

    private function getColumnTasks(array $tasks, int $boardId, string $columnKey, int $excludedTaskId = 0): array
    {
        $columnTasks = [];

        foreach ($tasks as $task) {
            if ((int) ($task['id'] ?? 0) === $excludedTaskId) {
                continue;
            }

            if ((int) ($task['board_id'] ?? 0) !== $boardId) {
                continue;
            }

            if ($this->normalizeColumnKey((string) ($task['column_key'] ?? '')) !== $columnKey) {
                continue;
            }

            $columnTasks[] = $task;
        }

        return $columnTasks;
    }

    private function buildOrderedTaskIds(array $orderedTaskIds, array $targetTasks, int $movedTaskId): array
    {
        $existingTaskIds = [];
        foreach ($targetTasks as $task) {
            $taskId = (int) ($task['id'] ?? 0);
            if ($taskId > 0) {
                $existingTaskIds[$taskId] = true;
            }
        }

        $finalOrder = [];
        foreach ($orderedTaskIds as $requestedTaskId) {
            $requestedTaskId = (int) $requestedTaskId;
            if ($requestedTaskId <= 0 || isset($finalOrder[$requestedTaskId])) {
                continue;
            }

            if ($requestedTaskId === $movedTaskId || isset($existingTaskIds[$requestedTaskId])) {
                $finalOrder[$requestedTaskId] = $requestedTaskId;
            }
        }

        if (!isset($finalOrder[$movedTaskId])) {
            $finalOrder[$movedTaskId] = $movedTaskId;
        }

        foreach (array_keys($existingTaskIds) as $existingTaskId) {
            if (!isset($finalOrder[$existingTaskId])) {
                $finalOrder[$existingTaskId] = $existingTaskId;
            }
        }

        return array_values($finalOrder);
    }

    private function persistTaskOrder(array $taskIds, int $boardId, string $columnKey): bool
    {
        $position = 1;

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;
            if ($taskId <= 0) {
                continue;
            }

            if (!$this->repository->updateTaskPosition($taskId, $boardId, $columnKey, $position)) {
                return false;
            }

            $position++;
        }

        return true;
    }

    private function findBoardForProject(int $projectId, int $boardId): ?array
    {
        if ($boardId <= 0) {
            return null;
        }

        foreach ($this->repository->getBoardsByProject($projectId) as $board) {
            if ((int) ($board['id'] ?? 0) === $boardId) {
                $board['payload_data'] = $this->sanitizeBoardPayload($this->decodePayload((string) ($board['payload'] ?? '')));
                return $board;
            }
        }

        return null;
    }

    private function resolveBoardColumnKey(array $board, string $requestedKey): string
    {
        $requestedKey = $this->normalizeColumnKey($requestedKey);
        $payload = (array) ($board['payload_data'] ?? []);

        foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            foreach (array_values($payload[$key]) as $index => $group) {
                if (!is_array($group)) {
                    continue;
                }

                $columnKey = $this->buildColumnKey((string) ($group['key'] ?? ''), (string) ($group['title'] ?? 'Block'), $index);
                if ($requestedKey === '' || $requestedKey === $columnKey) {
                    return $columnKey;
                }
            }
        }

        return '';
    }

    private function buildColumnKey(string $key, string $title, int $index): string
    {
        $normalized = $this->normalizeColumnKey($key);
        if ($normalized !== '') {
            return $normalized;
        }

        $normalized = $this->normalizeColumnKey($this->normalizeSlug($title));
        if ($normalized !== '') {
            return $normalized;
        }

        return 'column-' . ($index + 1);
    }

    private function normalizeColumnKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9\-_]+/', '-', $key) ?? '';
        $key = trim($key, '-_');
        $key = preg_replace('/[-_]{2,}/', '-', $key) ?? '';
        return $this->truncate($key, self::MAX_COLUMN_KEY_LENGTH);
    }

    private function normalizeDueDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($errors === false) {
            $errors = ['warning_count' => 0, 'error_count' => 0];
        }

        if (!$date instanceof \DateTimeImmutable || !is_array($errors) || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function normalizeWipLimit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (!is_scalar($value)) {
            return 0;
        }

        return max(0, min(9999, (int) $value));
    }

    private function sanitizeWidgetPayload(string $widgetType, array $payload): array
    {
        $items = [];

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($widgetType === 'checklist') {
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                if ($label === '') {
                    continue;
                }
                $items[] = ['label' => $label, 'done' => !empty($item['done'])];
                continue;
            }

            if ($widgetType === 'links') {
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                $url = $this->sanitizeUrl((string) ($item['url'] ?? ''));
                if ($label === '' || $url === '') {
                    continue;
                }
                $items[] = ['label' => $label, 'url' => $url];
                continue;
            }

            if ($widgetType === 'stats') {
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                $value = $this->sanitizeText((string) ($item['value'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                if ($label === '' && $value === '') {
                    continue;
                }
                $items[] = ['label' => $label, 'value' => $value];
                continue;
            }

            if ($widgetType === 'timeline') {
                $date = $this->sanitizeText((string) ($item['date'] ?? ''), 40);
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                if ($date === '' && $label === '') {
                    continue;
                }
                $items[] = ['date' => $date, 'label' => $label];
            }
        }

        return ['items' => $items];
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]]/', $url) === 1) {
            return '';
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if (!is_string($validated)) {
            return '';
        }

        $parts = parse_url($validated);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            return '';
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return '';
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        return $validated;
    }

    private function toBooleanFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function getDefaultBoardPayload(string $boardType): array
    {
        return match ($boardType) {
            'kanban' => [
                'columns' => [
                    ['title' => 'Backlog', 'items' => ['Projektziele schärfen', 'Tickets priorisieren']],
                    ['title' => 'In Arbeit', 'items' => ['Dashboard-MVP bauen', 'Widgets testen']],
                    ['title' => 'Review', 'items' => ['Stakeholder-Feedback einsammeln']],
                    ['title' => 'Done', 'items' => ['Projekt-Kickoff durchgeführt']],
                ],
            ],
            'diy' => [
                'lanes' => [
                    ['title' => 'Idee', 'items' => ['Anforderungen sammeln', 'Materialliste definieren']],
                    ['title' => 'Bauen', 'items' => ['Modul umsetzen', 'Komponenten verbinden']],
                    ['title' => 'Test', 'items' => ['Abnahme mit Team']],
                    ['title' => 'Learn', 'items' => ['Retrospektive dokumentieren']],
                ],
            ],
            'miro' => [
                'clusters' => [
                    ['title' => 'Ideen', 'items' => ['Neue Features', 'Nutzerwünsche']],
                    ['title' => 'Referenzen', 'items' => ['Wettbewerb analysieren', 'Best Practices sammeln']],
                    ['title' => 'Nächste Schritte', 'items' => ['Workshop planen']],
                ],
            ],
            'flow' => [
                'stages' => [
                    ['title' => 'Ready', 'items' => ['Stories fertig spezifiziert']],
                    ['title' => 'Doing', 'items' => ['Implementierung', 'Abstimmung mit Design']],
                    ['title' => 'Blocked', 'items' => ['Abhängigkeit zu externem API']],
                    ['title' => 'Released', 'items' => ['MVP live']],
                ],
            ],
            default => [
                'milestones' => [
                    ['title' => 'Q1', 'items' => ['Projektstart', 'Requirements']],
                    ['title' => 'Q2', 'items' => ['MVP und erste Widgets']],
                    ['title' => 'Q3', 'items' => ['Automationen und Reports']],
                    ['title' => 'Q4', 'items' => ['Rollout und Optimierung']],
                ],
            ],
        };
    }

    private function getDefaultWidgetPayload(string $widgetType): array
    {
        return match ($widgetType) {
            'checklist' => ['items' => [['label' => 'Projektbriefing freigeben', 'done' => true], ['label' => 'Stakeholder onboarden', 'done' => false], ['label' => 'Sprintziel festlegen', 'done' => false]]],
            'links' => ['items' => [['label' => 'Konzept', 'url' => 'https://example.com/konzept'], ['label' => 'Designboard', 'url' => 'https://example.com/designboard']]],
            'stats' => ['items' => [['label' => 'Fortschritt', 'value' => '68%'], ['label' => 'Offene Punkte', 'value' => '14'], ['label' => 'Sprint', 'value' => 'Sprint 04']]],
            'timeline' => ['items' => [['date' => '2026-04-01', 'label' => 'Kickoff'], ['date' => '2026-04-12', 'label' => 'MVP Review'], ['date' => '2026-04-30', 'label' => 'Release-Kandidat']]],
            default => ['items' => []],
        };
    }

    private function logOperationFailure(string $operation, array $context = []): void
    {
        $segments = [];
        foreach ($context as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $segments[] = $key . '=' . (string) $value;
        }

        error_log('[cms-projects] operation_failed=' . $operation . ($segments !== [] ? ' ' . implode(' ', $segments) : ''));
    }

    private function getActorContext(): array
    {
        if (!class_exists('CMS\\Auth')) {
            return ['id' => 0, 'name' => 'system'];
        }

        $auth = \CMS\Auth::instance();
        if (!method_exists($auth, 'currentUser')) {
            return ['id' => 0, 'name' => 'system'];
        }

        $user = $auth->currentUser();
        if (!is_object($user)) {
            return ['id' => 0, 'name' => 'system'];
        }

        $id = isset($user->id) ? (int) $user->id : 0;
        $name = '';
        foreach (['display_name', 'name', 'username', 'email'] as $field) {
            if (isset($user->{$field}) && is_string($user->{$field}) && trim($user->{$field}) !== '') {
                $name = trim((string) $user->{$field});
                break;
            }
        }

        if ($name === '') {
            $name = $id > 0 ? 'user-' . $id : 'system';
        }

        return ['id' => max(0, $id), 'name' => $this->truncate($name, self::MAX_SHORT_TEXT_LENGTH)];
    }

    private function recordAuditLog(
        string $action,
        string $entityType,
        int $entityId,
        int $projectId,
        bool $success,
        array $context = []
    ): void {
        $actor = $this->getActorContext();
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($contextJson)) {
            $contextJson = '{}';
        }

        $this->repository->insertAuditLog([
            'actor_id' => (int) ($actor['id'] ?? 0),
            'actor_name' => (string) ($actor['name'] ?? 'system'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => max(0, $entityId),
            'project_id' => max(0, $projectId),
            'result' => $success ? 'success' : 'failure',
            'context_json' => $contextJson,
        ]);
    }
}

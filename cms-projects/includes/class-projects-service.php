<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fachlogik von CMS Projects: Validierung, Sichtbarkeit, Board-Zusammenbau, Audit-Log, Export/Import.
 */
final class CMS_Projects_Service
{
    public const EXPORT_FORMAT = 'cms-projects/project';
    public const IMPORT_MAX_BYTES = 1048576;

    private const MAX_SLUG_LENGTH = 120;
    private const MAX_SHORT_TEXT_LENGTH = 190;
    private const MAX_SUMMARY_LENGTH = 1200;
    private const MAX_DESCRIPTION_LENGTH = 20000;
    private const MAX_COLUMN_KEY_LENGTH = 80;
    private const MAX_PAYLOAD_LENGTH = 65535;
    private const BOARD_GROUP_KEYS = ['columns', 'lanes', 'clusters', 'stages', 'milestones'];

    /** @var array<int, array<string,mixed>|null> Request-Cache für Projekte */
    private array $projectCache = [];

    public function __construct(private readonly CMS_Projects_Repository $repository)
    {
    }

    // ── Lebenszyklus ─────────────────────────────────────────────────────────

    /** Tabellen anlegen und Indizes migrieren (idempotent). */
    public function install(): void
    {
        $this->repository->ensureTables();
        $this->repository->migrateIndexes();
        CMS_Projects_Settings::markSchemaCurrent();
    }

    /** Läuft bei `cms_init`: Migration nur bei geänderter Schema-Version. */
    public function boot(): void
    {
        if (CMS_Projects_Settings::isSchemaCurrent()) {
            return;
        }

        try {
            $this->install();
        } catch (\Throwable $e) {
            error_log('[cms-projects] schema migration failed: ' . $e->getMessage());
        }
    }

    public function uninstall(): void
    {
        $this->repository->dropTables();
        CMS_Projects_Settings::deleteAll();
    }

    public function getTableStatus(): array
    {
        return $this->repository->getTableStatus();
    }

    // ── Stammdaten ───────────────────────────────────────────────────────────

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

    public function getProjectStatusTones(): array
    {
        return ['draft' => 'muted', 'active' => 'success', 'paused' => 'warning', 'archived' => 'muted'];
    }

    public function getProjectVisibilities(): array
    {
        return ['public' => 'Öffentlich', 'member' => 'Mitglieder', 'private' => 'Privat'];
    }

    public function getWidgetScopes(): array
    {
        return ['public' => 'Nur öffentlich', 'member' => 'Nur Mitglieder', 'both' => 'Öffentlich + Mitglieder'];
    }

    public function getTaskPriorities(): array
    {
        return ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch', 'critical' => 'Kritisch'];
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

    public function getBoardDefaults(string $boardType = 'kanban'): array
    {
        return [
            'id' => 0,
            'board_type' => $boardType,
            'title' => '',
            'description' => '',
            'position' => 0,
            'is_public' => 1,
            'is_active' => 1,
            'payload_json' => $this->encodeJson($this->getDefaultBoardPayload($boardType)),
        ];
    }

    public function getWidgetDefaults(string $widgetType = 'stats'): array
    {
        return [
            'id' => 0,
            'widget_type' => $widgetType,
            'scope' => 'both',
            'title' => '',
            'content' => '',
            'position' => 0,
            'is_active' => 1,
            'payload_json' => $this->encodeJson($this->getDefaultWidgetPayload($widgetType)),
        ];
    }

    // ── Projekte lesen ───────────────────────────────────────────────────────

    /**
     * @param array{q?:string,status?:string,visibility?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function getProjectsPage(array $filters, int $page, int $perPage = 25): array
    {
        $filters = $this->normalizeProjectFilters($filters);
        $total = $this->repository->countProjectsFiltered($filters);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = max(1, min($pages, $page));
        $items = $this->repository->findProjects($filters, $perPage, ($page - 1) * $perPage);

        return [
            'items' => $this->decorateProjects($items),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function normalizeProjectFilters(array $filters): array
    {
        $status = (string) ($filters['status'] ?? '');
        $visibility = (string) ($filters['visibility'] ?? '');

        return [
            'q' => $this->sanitizeText((string) ($filters['q'] ?? ''), 100),
            'status' => isset($this->getProjectStatuses()[$status]) ? $status : '',
            'visibility' => isset($this->getProjectVisibilities()[$visibility]) ? $visibility : '',
        ];
    }

    public function getProjectOptions(): array
    {
        return $this->repository->getProjectOptions();
    }

    public function getPublicProjects(): array
    {
        return $this->decorateProjects($this->repository->getPublicProjects());
    }

    public function getMemberProjects(): array
    {
        return $this->decorateProjects($this->repository->getMemberProjects());
    }

    public function countMemberProjects(): int
    {
        return $this->repository->countMemberProjects();
    }

    public function findProject(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if (!array_key_exists($id, $this->projectCache)) {
            $project = $this->repository->findProjectById($id);
            $this->projectCache[$id] = $project !== null ? $this->decorateProject($project) : null;
        }

        return $this->projectCache[$id];
    }

    public function findProjectBySlug(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $project = $this->repository->findProjectBySlug($slug);
        return $project !== null ? $this->decorateProject($project) : null;
    }

    /** Projekt für einen Bereich, nur wenn Sichtbarkeit und Status den Zugriff erlauben. */
    public function findAccessibleProjectBySlug(string $slug, string $scope): ?array
    {
        $project = $this->findProjectBySlug($slug);
        if ($project === null || !$this->canAccessProjectScope($project, $this->normalizeDashboardScope($scope))) {
            return null;
        }

        return $project;
    }

    public function getProjectPreviewLinks(array $project): array
    {
        $slug = (string) ($project['slug'] ?? '');
        return [
            'public' => $slug !== '' ? '/projects/' . rawurlencode($slug) : '/projects',
            'member' => $slug !== '' ? '/member/plugin/projects?project=' . rawurlencode($slug) : '/member/plugin/projects',
        ];
    }

    // ── Boards, Widgets, Tickets lesen ───────────────────────────────────────

    public function getProjectBoards(int $projectId, string $scope = 'admin', ?array $tasks = null): array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $boards = $this->repository->getBoardsByProject($projectId, $scope === 'admin', $scope === 'public' ? true : null);
        if ($boards === []) {
            return [];
        }

        $tasksByBoard = $this->groupTasksByBoard($tasks ?? $this->getProjectTasks($projectId, $scope));
        $boardTypes = $this->getBoardTypes();

        return array_map(function (array $board) use ($tasksByBoard, $boardTypes): array {
            $board['payload_data'] = $this->sanitizeBoardPayload($this->decodePayload((string) ($board['payload'] ?? '')));
            $board['board_label'] = (string) ($boardTypes[$board['board_type'] ?? 'kanban']['label'] ?? 'Board');
            $board['tasks'] = $tasksByBoard[(int) ($board['id'] ?? 0)] ?? [];
            $board['task_count'] = count($board['tasks']);
            $board['payload_data'] = $this->mergeTasksIntoBoardPayload($board['payload_data'], $board['tasks']);
            return $board;
        }, $boards);
    }

    /** Board eines Projekts für das Bearbeitungsformular (Payload ohne Tickets, als JSON). */
    public function findBoard(int $projectId, int $boardId): ?array
    {
        $board = $this->findBoardForProject($projectId, $boardId);
        if ($board === null) {
            return null;
        }

        $board['payload_json'] = $this->encodeJson((array) ($board['payload_data'] ?? []));
        return $board;
    }

    /**
     * Spaltenauswahl je Board für das Ticketformular.
     *
     * @return list<array{id:int,title:string,columns:list<array{key:string,title:string}>}>
     */
    public function getBoardColumnOptions(array $boards): array
    {
        $options = [];
        foreach ($boards as $board) {
            $columns = [];
            foreach ($this->collectBoardGroups((array) ($board['payload_data'] ?? [])) as $group) {
                $key = (string) ($group['key'] ?? '');
                if ($key !== '') {
                    $columns[] = ['key' => $key, 'title' => (string) ($group['title'] ?? 'Block')];
                }
            }

            if ($columns !== []) {
                $options[] = ['id' => (int) ($board['id'] ?? 0), 'title' => (string) ($board['title'] ?? 'Board'), 'columns' => $columns];
            }
        }

        return $options;
    }

    public function getProjectWidgets(int $projectId, string $scope = 'admin'): array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $widgets = $this->repository->getWidgetsByProject($projectId, $scope, $scope === 'admin');
        $widgetTypes = $this->getWidgetTypes();

        return array_map(function (array $widget) use ($widgetTypes): array {
            $widget['payload_data'] = $this->sanitizeWidgetPayload((string) ($widget['widget_type'] ?? 'text'), $this->decodePayload((string) ($widget['payload'] ?? '')));
            $widget['widget_label'] = (string) ($widgetTypes[$widget['widget_type'] ?? 'text']['label'] ?? 'Widget');
            return $widget;
        }, $widgets);
    }

    public function findWidget(int $projectId, int $widgetId): ?array
    {
        if ($widgetId <= 0) {
            return null;
        }

        $widget = $this->repository->findWidgetById($widgetId);
        if ($widget === null || (int) ($widget['project_id'] ?? 0) !== $projectId) {
            return null;
        }

        $widget['payload_data'] = $this->sanitizeWidgetPayload((string) ($widget['widget_type'] ?? 'text'), $this->decodePayload((string) ($widget['payload'] ?? '')));
        $widget['payload_json'] = $this->encodeJson($widget['payload_data']);
        return $widget;
    }

    public function getProjectDashboardPayload(int $projectId, string $scope): ?array
    {
        $scope = $this->normalizeDashboardScope($scope);
        $project = $this->findProject($projectId);
        if ($project === null || !$this->canAccessProjectScope($project, $scope)) {
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
        return array_map([$this, 'decorateTask'], $this->repository->getTasksByProject($projectId, $scope, $scope === 'admin'));
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function getTasksPage(array $filters, int $page, int $perPage = 50): array
    {
        $filters = $this->normalizeTaskFilters($filters);
        $total = $this->repository->countTasksFiltered($filters);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = max(1, min($pages, $page));

        return [
            'items' => array_map([$this, 'decorateTask'], $this->repository->findTasks($filters, $perPage, ($page - 1) * $perPage)),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function normalizeTaskFilters(array $filters): array
    {
        $priority = (string) ($filters['priority'] ?? '');
        $state = (string) ($filters['state'] ?? '');

        return [
            'project_id' => max(0, (int) ($filters['project_id'] ?? 0)),
            'board_id' => max(0, (int) ($filters['board_id'] ?? 0)),
            'q' => $this->sanitizeText((string) ($filters['q'] ?? ''), 100),
            'priority' => isset($this->getTaskPriorities()[$priority]) ? $priority : '',
            'state' => in_array($state, ['active', 'inactive'], true) ? $state : '',
            'overdue' => !empty($filters['overdue']),
        ];
    }

    public function getDueTasks(int $days = 14, int $limit = 10): array
    {
        return array_map([$this, 'decorateTask'], $this->repository->getDueTasks($days, $limit));
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

        $task = $this->decorateTask($task);
        $task['payload_data'] = $this->decodePayload((string) ($task['payload'] ?? ''));
        return $task;
    }

    // ── Projekte schreiben ───────────────────────────────────────────────────

    public function saveProject(array $input, ?int $id = null): array
    {
        if ($id !== null && $this->findProject($id) === null) {
            return ['success' => false, 'message' => 'Das zu bearbeitende Projekt wurde nicht gefunden.'];
        }

        $name = $this->sanitizeText((string) ($input['name'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
        if ($name === '') {
            return ['success' => false, 'message' => 'Projektname ist erforderlich.'];
        }

        $slug = $this->normalizeSlug((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->normalizeSlug($name);
        }

        if ($slug === '') {
            return ['success' => false, 'message' => 'Für das Projekt konnte kein gültiger Slug erzeugt werden.'];
        }

        $existing = $this->repository->findProjectBySlug($slug);
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

        $action = $id !== null ? 'project.update' : 'project.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_project', ['project_id' => $id ?? 0, 'slug' => $slug]);
            $this->recordAuditLog($action, 'project', (int) ($id ?? 0), (int) ($id ?? 0), false, ['slug' => $slug]);
            return ['success' => false, 'message' => 'Projekt konnte nicht gespeichert werden.'];
        }

        unset($this->projectCache[(int) $saveId]);
        $this->recordAuditLog($action, 'project', (int) $saveId, (int) $saveId, true, ['slug' => $slug]);
        return ['success' => true, 'message' => $id !== null ? 'Projekt wurde aktualisiert.' : 'Projekt wurde angelegt.', 'id' => (int) $saveId];
    }

    public function deleteProject(int $projectId): array
    {
        $project = $this->findProject($projectId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Das Projekt wurde nicht gefunden.'];
        }

        $deleted = $this->repository->deleteProjects([$projectId]);
        unset($this->projectCache[$projectId]);
        $this->recordAuditLog('project.delete', 'project', $projectId, $projectId, $deleted > 0, ['slug' => (string) ($project['slug'] ?? '')]);

        return $deleted > 0
            ? ['success' => true, 'message' => 'Projekt „' . (string) ($project['name'] ?? '') . '“ wurde samt Boards, Widgets und Tickets gelöscht.']
            : ['success' => false, 'message' => 'Projekt konnte nicht gelöscht werden.'];
    }

    /** @param list<int> $ids */
    public function bulkProjects(string $operation, array $ids): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return ['success' => false, 'message' => 'Bitte mindestens ein Projekt auswählen.'];
        }

        if ($operation === 'delete') {
            $count = $this->repository->deleteProjects($ids);
            $this->projectCache = [];
            $this->recordAuditLog('project.bulk_delete', 'project', 0, 0, $count > 0, ['ids' => $ids]);
            return ['success' => $count > 0, 'message' => $count . ' Projekt(e) gelöscht.'];
        }

        if (str_starts_with($operation, 'status:')) {
            $status = substr($operation, 7);
            if (!isset($this->getProjectStatuses()[$status])) {
                return ['success' => false, 'message' => 'Ungültiger Status.'];
            }

            $count = $this->repository->updateProjectsStatus($ids, $status);
            $this->projectCache = [];
            $this->recordAuditLog('project.bulk_status', 'project', 0, 0, true, ['ids' => $ids, 'status' => $status]);
            return ['success' => true, 'message' => $count . ' Projekt(e) auf „' . $this->getProjectStatuses()[$status] . '“ gesetzt.'];
        }

        return ['success' => false, 'message' => 'Unbekannte Sammelaktion.'];
    }

    // ── Boards schreiben ─────────────────────────────────────────────────────

    public function saveBoard(array $input): array
    {
        $projectId = (int) ($input['project_id'] ?? 0);
        if ($this->findProject($projectId) === null) {
            return ['success' => false, 'message' => 'Projekt für das Board wurde nicht gefunden.'];
        }

        $boardId = max(0, (int) ($input['board_id'] ?? 0));
        if ($boardId > 0 && $this->findBoardForProject($projectId, $boardId) === null) {
            return ['success' => false, 'message' => 'Das zu bearbeitende Board wurde nicht gefunden.'];
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
            return ['success' => false, 'message' => 'Board-Payload ist zu groß (max. 64 KB).'];
        }

        $decoded = $payload === '' ? $this->getDefaultBoardPayload($boardType) : $this->parsePayload($payload);
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Board-Payload muss gültiges JSON sein.'];
        }

        $decoded = $this->sanitizeBoardPayload($decoded);
        $columnKeys = array_values(array_filter(array_map(
            static fn (array $group): string => (string) ($group['key'] ?? ''),
            $this->collectBoardGroups($decoded)
        )));
        if ($columnKeys === []) {
            return ['success' => false, 'message' => 'Das Board benötigt mindestens eine Spalte (z. B. "columns": [{"title": "Backlog"}]).'];
        }

        $saveId = $this->repository->saveBoard([
            'project_id' => $projectId,
            'board_type' => $boardType,
            'title' => $title,
            'description' => $this->sanitizeMultilineText((string) ($input['description'] ?? ''), self::MAX_SUMMARY_LENGTH),
            'position' => max(0, (int) ($input['position'] ?? 0)),
            'is_public' => $this->toBooleanFlag($input['is_public'] ?? 0),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => $this->encodeJson($decoded),
        ], $boardId > 0 ? $boardId : null);

        $action = $boardId > 0 ? 'board.update' : 'board.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_board', ['project_id' => $projectId, 'board_type' => $boardType]);
            $this->recordAuditLog($action, 'board', $boardId, $projectId, false, ['board_type' => $boardType]);
            return ['success' => false, 'message' => 'Board konnte nicht gespeichert werden.'];
        }

        $moved = $boardId > 0 ? $this->repository->reassignOrphanTasks($boardId, $columnKeys, $columnKeys[0]) : 0;
        $this->repository->touchProject($projectId);
        $this->recordAuditLog($action, 'board', (int) $saveId, $projectId, true, ['board_type' => $boardType, 'reassigned_tasks' => $moved]);

        $message = $boardId > 0 ? 'Board wurde aktualisiert.' : 'Board wurde angelegt.';
        if ($moved > 0) {
            $message .= ' ' . $moved . ' Ticket(s) aus entfernten Spalten wurden in die erste Spalte verschoben.';
        }

        return ['success' => true, 'message' => $message, 'id' => (int) $saveId];
    }

    /** @param list<int> $ids */
    public function bulkBoards(int $projectId, string $operation, array $ids): array
    {
        return $this->runBulk('board', $projectId, $operation, $ids, [
            'activate' => fn (array $list): int => $this->repository->updateBoardsFlag($projectId, $list, 'is_active', 1),
            'deactivate' => fn (array $list): int => $this->repository->updateBoardsFlag($projectId, $list, 'is_active', 0),
            'public' => fn (array $list): int => $this->repository->updateBoardsFlag($projectId, $list, 'is_public', 1),
            'internal' => fn (array $list): int => $this->repository->updateBoardsFlag($projectId, $list, 'is_public', 0),
            'delete' => fn (array $list): int => $this->repository->deleteBoards($projectId, $list),
        ], 'Board(s)');
    }

    // ── Widgets schreiben ────────────────────────────────────────────────────

    public function saveWidget(array $input): array
    {
        $projectId = (int) ($input['project_id'] ?? 0);
        if ($this->findProject($projectId) === null) {
            return ['success' => false, 'message' => 'Projekt für das Widget wurde nicht gefunden.'];
        }

        $widgetId = max(0, (int) ($input['widget_id'] ?? 0));
        if ($widgetId > 0 && $this->findWidget($projectId, $widgetId) === null) {
            return ['success' => false, 'message' => 'Das zu bearbeitende Widget wurde nicht gefunden.'];
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
            return ['success' => false, 'message' => 'Widget-Payload ist zu groß (max. 64 KB).'];
        }

        $decoded = $payload === '' ? $this->getDefaultWidgetPayload($widgetType) : $this->parsePayload($payload);
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Widget-Payload muss gültiges JSON sein.'];
        }

        $saveId = $this->repository->saveWidget([
            'project_id' => $projectId,
            'scope' => $scope,
            'widget_type' => $widgetType,
            'title' => $title,
            'content' => $this->sanitizeMultilineText((string) ($input['content'] ?? ''), self::MAX_DESCRIPTION_LENGTH),
            'position' => max(0, (int) ($input['position'] ?? 0)),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => $this->encodeJson($this->sanitizeWidgetPayload($widgetType, $decoded)),
        ], $widgetId > 0 ? $widgetId : null);

        $action = $widgetId > 0 ? 'widget.update' : 'widget.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_widget', ['project_id' => $projectId, 'widget_type' => $widgetType]);
            $this->recordAuditLog($action, 'widget', $widgetId, $projectId, false, ['widget_type' => $widgetType, 'scope' => $scope]);
            return ['success' => false, 'message' => 'Widget konnte nicht gespeichert werden.'];
        }

        $this->repository->touchProject($projectId);
        $this->recordAuditLog($action, 'widget', (int) $saveId, $projectId, true, ['widget_type' => $widgetType, 'scope' => $scope]);
        return ['success' => true, 'message' => $widgetId > 0 ? 'Widget wurde aktualisiert.' : 'Widget wurde angelegt.', 'id' => (int) $saveId];
    }

    /** @param list<int> $ids */
    public function bulkWidgets(int $projectId, string $operation, array $ids): array
    {
        return $this->runBulk('widget', $projectId, $operation, $ids, [
            'activate' => fn (array $list): int => $this->repository->updateWidgetsFlag($projectId, $list, 'is_active', 1),
            'deactivate' => fn (array $list): int => $this->repository->updateWidgetsFlag($projectId, $list, 'is_active', 0),
            'delete' => fn (array $list): int => $this->repository->deleteWidgets($projectId, $list),
        ], 'Widget(s)');
    }

    // ── Tickets schreiben ────────────────────────────────────────────────────

    public function saveTask(array $input): array
    {
        $taskId = max(0, (int) ($input['task_id'] ?? 0));
        $projectId = (int) ($input['project_id'] ?? 0);
        if ($this->findProject($projectId) === null) {
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
            return ['success' => false, 'message' => 'Die gewählte Spalte gehört nicht zum gewählten Board.'];
        }

        $dueDate = $this->normalizeDueDate((string) ($input['due_date'] ?? ''));
        if ($dueDate === null) {
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
            'due_date' => $dueDate,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'is_public' => $this->toBooleanFlag($input['is_public'] ?? 0),
            'is_active' => $this->toBooleanFlag($input['is_active'] ?? 0),
            'payload' => $this->encodeJson((array) ($existingTask['payload_data'] ?? [])),
        ], $taskId > 0 ? $taskId : null);

        $action = $taskId > 0 ? 'task.update' : 'task.create';
        if ($saveId === false) {
            $this->logOperationFailure('save_task', ['project_id' => $projectId, 'task_id' => $taskId]);
            $this->recordAuditLog($action, 'task', $taskId, $projectId, false, ['board_id' => $boardId, 'column_key' => $columnKey]);
            return ['success' => false, 'message' => 'Ticket konnte nicht gespeichert werden.'];
        }

        $this->repository->touchProject($projectId);
        $this->recordAuditLog($action, 'task', (int) $saveId, $projectId, true, ['board_id' => $boardId, 'column_key' => $columnKey]);
        return ['success' => true, 'message' => $taskId > 0 ? 'Ticket wurde aktualisiert.' : 'Ticket wurde angelegt.', 'id' => (int) $saveId];
    }

    public function deleteTask(int $taskId, int $projectId): array
    {
        $task = $this->findTask($taskId);
        if ($task === null || (int) ($task['project_id'] ?? 0) !== $projectId) {
            return ['success' => false, 'message' => 'Das Ticket wurde nicht gefunden.'];
        }

        if ($this->repository->deleteTasks($projectId, [$taskId]) < 1) {
            $this->logOperationFailure('delete_task', ['project_id' => $projectId, 'task_id' => $taskId]);
            $this->recordAuditLog('task.delete', 'task', $taskId, $projectId, false);
            return ['success' => false, 'message' => 'Ticket konnte nicht gelöscht werden.'];
        }

        $this->recordAuditLog('task.delete', 'task', $taskId, $projectId, true);
        return ['success' => true, 'message' => 'Ticket wurde gelöscht.', 'id' => $taskId];
    }

    /** @param list<int> $ids */
    public function bulkTasks(int $projectId, string $operation, array $ids): array
    {
        return $this->runBulk('task', $projectId, $operation, $ids, [
            'activate' => fn (array $list): int => $this->repository->updateTasksFlag($projectId, $list, 'is_active', 1),
            'deactivate' => fn (array $list): int => $this->repository->updateTasksFlag($projectId, $list, 'is_active', 0),
            'public' => fn (array $list): int => $this->repository->updateTasksFlag($projectId, $list, 'is_public', 1),
            'internal' => fn (array $list): int => $this->repository->updateTasksFlag($projectId, $list, 'is_public', 0),
            'delete' => fn (array $list): int => $this->repository->deleteTasks($projectId, $list),
        ], 'Ticket(s)');
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
        $resolvedColumnKey = $targetColumnKey !== '' ? $this->resolveBoardColumnKey($board, $targetColumnKey) : '';
        if ($resolvedColumnKey === '') {
            return ['success' => false, 'message' => 'Ziel-Spalte wurde im Board nicht gefunden.'];
        }

        $sourceBoardId = (int) ($task['board_id'] ?? 0);
        $sourceColumnKey = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
        $allTasks = $this->getProjectTasks($projectId, 'admin');
        $targetTasks = $this->getColumnTasks($allTasks, $targetBoardId, $resolvedColumnKey, $taskId);
        $finalTargetTaskIds = $this->buildOrderedTaskIds($orderedTaskIds, $targetTasks, $taskId);
        $context = [
            'source_board_id' => $sourceBoardId,
            'source_column_key' => $sourceColumnKey,
            'target_board_id' => $targetBoardId,
            'target_column_key' => $resolvedColumnKey,
        ];

        if (!$this->repository->updateTaskPositions($projectId, $finalTargetTaskIds, $targetBoardId, $resolvedColumnKey)) {
            $this->logOperationFailure('move_task_target', ['project_id' => $projectId, 'task_id' => $taskId]);
            $this->recordAuditLog('task.move', 'task', $taskId, $projectId, false, $context);
            return ['success' => false, 'message' => 'Ticket konnte nicht verschoben werden.'];
        }

        if ($sourceBoardId !== $targetBoardId || $sourceColumnKey !== $resolvedColumnKey) {
            $sourceTaskIds = array_map(
                static fn (array $columnTask): int => (int) ($columnTask['id'] ?? 0),
                $this->getColumnTasks($allTasks, $sourceBoardId, $sourceColumnKey, $taskId)
            );

            if (!$this->repository->updateTaskPositions($projectId, $sourceTaskIds, $sourceBoardId, $sourceColumnKey)) {
                $this->logOperationFailure('move_task_source', ['project_id' => $projectId, 'task_id' => $taskId]);
                $this->recordAuditLog('task.move', 'task', $taskId, $projectId, false, $context);
                return ['success' => false, 'message' => 'Quell-Spalte konnte nach dem Verschieben nicht neu sortiert werden.'];
            }
        }

        $this->recordAuditLog('task.move', 'task', $taskId, $projectId, true, $context);
        return ['success' => true, 'message' => 'Ticket „' . (string) ($task['title'] ?? '') . '“ wurde verschoben.', 'id' => $taskId];
    }

    // ── Audit-Log ────────────────────────────────────────────────────────────

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function getAuditPage(array $filters, int $page, int $perPage = 50): array
    {
        $filters = $this->normalizeAuditFilters($filters);
        $total = $this->repository->countAuditLogs($filters);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = max(1, min($pages, $page));

        return [
            'items' => $this->repository->findAuditLogs($filters, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function normalizeAuditFilters(array $filters): array
    {
        $result = (string) ($filters['result'] ?? '');
        $entityType = (string) ($filters['entity_type'] ?? '');

        return [
            'q' => $this->sanitizeText((string) ($filters['q'] ?? ''), 80),
            'result' => in_array($result, ['success', 'failure'], true) ? $result : '',
            'entity_type' => preg_match('/^[a-z_]{1,50}$/', $entityType) === 1 ? $entityType : '',
            'project_id' => max(0, (int) ($filters['project_id'] ?? 0)),
        ];
    }

    public function getRecentAuditLogs(int $limit = 8): array
    {
        return $this->repository->findAuditLogs([], $limit);
    }

    public function getAuditEntityTypes(): array
    {
        return $this->repository->getAuditEntityTypes();
    }

    public function describeAuditAction(string $action): string
    {
        $labels = [
            'project.create' => 'Projekt angelegt',
            'project.update' => 'Projekt aktualisiert',
            'project.delete' => 'Projekt gelöscht',
            'project.bulk_delete' => 'Projekte gelöscht (Sammelaktion)',
            'project.bulk_status' => 'Projektstatus geändert (Sammelaktion)',
            'project.import' => 'Projekt importiert',
            'board.create' => 'Board angelegt',
            'board.update' => 'Board aktualisiert',
            'board.bulk' => 'Boards geändert (Sammelaktion)',
            'widget.create' => 'Widget angelegt',
            'widget.update' => 'Widget aktualisiert',
            'widget.bulk' => 'Widgets geändert (Sammelaktion)',
            'task.create' => 'Ticket angelegt',
            'task.update' => 'Ticket aktualisiert',
            'task.delete' => 'Ticket gelöscht',
            'task.move' => 'Ticket verschoben',
            'task.bulk' => 'Tickets geändert (Sammelaktion)',
            'settings.update' => 'Einstellungen gespeichert',
        ];

        return $labels[$action] ?? $action;
    }

    // ── Export / Import ──────────────────────────────────────────────────────

    /** @return list<list<string>> CSV-Zeilen inkl. Kopfzeile */
    public function buildTasksCsvRows(int $projectId): array
    {
        $projectNames = [];
        foreach ($this->repository->getProjectOptions() as $option) {
            $projectNames[(int) $option['id']] = (string) $option['name'];
        }

        $boardTitles = [];
        $priorities = $this->getTaskPriorities();
        $rows = [['ID', 'Projekt', 'Board', 'Spalte', 'Titel', 'Beschreibung', 'Priorität', 'Zuständig', 'Fällig am', 'Öffentlich', 'Aktiv', 'Erstellt', 'Aktualisiert']];

        foreach ($this->repository->getTasksForExport($projectId) as $task) {
            $taskProjectId = (int) $task['project_id'];
            if (!isset($boardTitles[$taskProjectId])) {
                $boardTitles[$taskProjectId] = [];
                foreach ($this->repository->getBoardsByProject($taskProjectId) as $board) {
                    $boardTitles[$taskProjectId][(int) $board['id']] = (string) $board['title'];
                }
            }

            $rows[] = [
                (string) $task['id'],
                $projectNames[$taskProjectId] ?? ('#' . $taskProjectId),
                $boardTitles[$taskProjectId][(int) $task['board_id']] ?? ('#' . (int) $task['board_id']),
                (string) $task['column_key'],
                (string) $task['title'],
                (string) $task['description'],
                (string) ($priorities[$task['priority']] ?? $task['priority']),
                (string) $task['assignee_name'],
                (string) $task['due_date'],
                !empty($task['is_public']) ? 'ja' : 'nein',
                !empty($task['is_active']) ? 'ja' : 'nein',
                (string) $task['created_at'],
                (string) $task['updated_at'],
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    public function buildAuditCsvRows(array $filters): array
    {
        $rows = [['ID', 'Zeitpunkt', 'Benutzer', 'Aktion', 'Beschreibung', 'Entität', 'Entitäts-ID', 'Projekt-ID', 'Ergebnis']];
        foreach ($this->repository->findAuditLogs($this->normalizeAuditFilters($filters), 20000) as $log) {
            $rows[] = [
                (string) $log['id'],
                (string) $log['created_at'],
                (string) ($log['actor_name'] !== '' ? $log['actor_name'] : 'system'),
                (string) $log['action'],
                $this->describeAuditAction((string) $log['action']),
                (string) $log['entity_type'],
                (string) $log['entity_id'],
                (string) $log['project_id'],
                (string) $log['result'],
            ];
        }

        return $rows;
    }

    /** Vollständige Projektkonfiguration (Projekt, Boards, Widgets, Tickets) als exportierbares Array. */
    public function buildProjectExport(int $projectId): ?array
    {
        $project = $this->repository->findProjectById($projectId);
        if ($project === null) {
            return null;
        }

        $boards = $this->repository->getBoardsByProject($projectId);
        $boardIndex = [];
        $exportBoards = [];
        foreach ($boards as $index => $board) {
            $boardIndex[(int) $board['id']] = $index;
            $exportBoards[] = [
                'board_type' => $board['board_type'],
                'title' => $board['title'],
                'description' => $board['description'],
                'position' => $board['position'],
                'is_public' => (int) $board['is_public'],
                'is_active' => (int) $board['is_active'],
                'payload' => $this->sanitizeBoardPayload($this->decodePayload((string) $board['payload'])),
            ];
        }

        $exportWidgets = array_map(fn (array $widget): array => [
            'widget_type' => $widget['widget_type'],
            'scope' => $widget['scope'],
            'title' => $widget['title'],
            'content' => $widget['content'],
            'position' => $widget['position'],
            'is_active' => (int) $widget['is_active'],
            'payload' => $this->sanitizeWidgetPayload((string) $widget['widget_type'], $this->decodePayload((string) $widget['payload'])),
        ], $this->repository->getWidgetsByProject($projectId));

        $exportTasks = [];
        foreach ($this->repository->getTasksByProject($projectId) as $task) {
            if (!isset($boardIndex[(int) $task['board_id']])) {
                continue;
            }

            $exportTasks[] = [
                'board' => $boardIndex[(int) $task['board_id']],
                'column_key' => $task['column_key'],
                'title' => $task['title'],
                'description' => $task['description'],
                'priority' => $task['priority'],
                'assignee_name' => $task['assignee_name'],
                'due_date' => $task['due_date'],
                'sort_order' => $task['sort_order'],
                'is_public' => (int) $task['is_public'],
                'is_active' => (int) $task['is_active'],
            ];
        }

        return [
            'format' => self::EXPORT_FORMAT,
            'version' => 1,
            'plugin_version' => defined('CMS_PROJECTS_VERSION') ? CMS_PROJECTS_VERSION : '',
            'exported_at' => date('c'),
            'project' => [
                'name' => $project['name'],
                'slug' => $project['slug'],
                'summary' => $project['summary'],
                'description' => $project['description'],
                'status' => $project['status'],
                'visibility' => $project['visibility'],
                'owner_name' => $project['owner_name'],
                'accent_color' => $project['accent_color'],
                'member_headline' => $project['member_headline'],
                'public_headline' => $project['public_headline'],
            ],
            'boards' => $exportBoards,
            'widgets' => $exportWidgets,
            'tasks' => $exportTasks,
        ];
    }

    /**
     * Legt aus einem Export ein neues Projekt an. Ein belegter Slug erhält ein Suffix, importierte
     * Projekte starten als Entwurf, damit nichts ungeprüft öffentlich wird.
     */
    public function importProject(array $data): array
    {
        if (($data['format'] ?? '') !== self::EXPORT_FORMAT || !is_array($data['project'] ?? null)) {
            return ['success' => false, 'message' => 'Die Datei ist kein gültiger CMS-Projects-Export.'];
        }

        $projectData = (array) $data['project'];
        $baseSlug = $this->normalizeSlug((string) ($projectData['slug'] ?? '')) ?: $this->normalizeSlug((string) ($projectData['name'] ?? ''));
        if ($baseSlug === '') {
            return ['success' => false, 'message' => 'Im Export fehlt ein gültiger Projektname.'];
        }

        $slug = $baseSlug;
        for ($suffix = 2; $this->repository->slugExists($slug) && $suffix < 100; $suffix++) {
            $slug = substr($baseSlug, 0, self::MAX_SLUG_LENGTH - 4) . '-' . $suffix;
        }

        $projectData['slug'] = $slug;
        $projectData['status'] = 'draft';
        $result = $this->saveProject($projectData);
        if (empty($result['success'])) {
            return $result;
        }

        $projectId = (int) $result['id'];
        $boardIds = [];
        $errors = 0;
        foreach (array_slice((array) ($data['boards'] ?? []), 0, 50) as $index => $board) {
            if (!is_array($board)) {
                $errors++;
                continue;
            }

            $boardResult = $this->saveBoard([
                'project_id' => $projectId,
                'board_type' => (string) ($board['board_type'] ?? 'kanban'),
                'title' => (string) ($board['title'] ?? ''),
                'description' => (string) ($board['description'] ?? ''),
                'position' => (int) ($board['position'] ?? 0),
                'is_public' => !empty($board['is_public']) ? '1' : '0',
                'is_active' => !empty($board['is_active']) ? '1' : '0',
                'payload' => $this->encodeJson(is_array($board['payload'] ?? null) ? $board['payload'] : []),
            ]);
            if (!empty($boardResult['success'])) {
                $boardIds[(int) $index] = (int) $boardResult['id'];
            } else {
                $errors++;
            }
        }

        $widgetCount = 0;
        foreach (array_slice((array) ($data['widgets'] ?? []), 0, 100) as $widget) {
            if (!is_array($widget)) {
                $errors++;
                continue;
            }

            $widgetResult = $this->saveWidget([
                'project_id' => $projectId,
                'widget_type' => (string) ($widget['widget_type'] ?? 'text'),
                'scope' => (string) ($widget['scope'] ?? 'both'),
                'title' => (string) ($widget['title'] ?? ''),
                'content' => (string) ($widget['content'] ?? ''),
                'position' => (int) ($widget['position'] ?? 0),
                'is_active' => !empty($widget['is_active']) ? '1' : '0',
                'payload' => $this->encodeJson(is_array($widget['payload'] ?? null) ? $widget['payload'] : []),
            ]);
            !empty($widgetResult['success']) ? $widgetCount++ : $errors++;
        }

        $taskCount = 0;
        foreach (array_slice((array) ($data['tasks'] ?? []), 0, 2000) as $task) {
            $boardId = is_array($task) ? ($boardIds[(int) ($task['board'] ?? -1)] ?? 0) : 0;
            if ($boardId <= 0) {
                $errors++;
                continue;
            }

            $taskResult = $this->saveTask([
                'project_id' => $projectId,
                'board_id' => $boardId,
                'column_key' => (string) ($task['column_key'] ?? ''),
                'title' => (string) ($task['title'] ?? ''),
                'description' => (string) ($task['description'] ?? ''),
                'priority' => (string) ($task['priority'] ?? 'medium'),
                'assignee_name' => (string) ($task['assignee_name'] ?? ''),
                'due_date' => (string) ($task['due_date'] ?? ''),
                'sort_order' => (int) ($task['sort_order'] ?? 0),
                'is_public' => !empty($task['is_public']) ? '1' : '0',
                'is_active' => !empty($task['is_active']) ? '1' : '0',
            ]);
            !empty($taskResult['success']) ? $taskCount++ : $errors++;
        }

        $this->recordAuditLog('project.import', 'project', $projectId, $projectId, true, [
            'slug' => $slug,
            'boards' => count($boardIds),
            'widgets' => $widgetCount,
            'tasks' => $taskCount,
            'errors' => $errors,
        ]);

        return [
            'success' => true,
            'id' => $projectId,
            'message' => sprintf(
                'Projekt „%s“ importiert (Slug „%s“, Status Entwurf): %d Board(s), %d Widget(s), %d Ticket(s)%s.',
                (string) ($projectData['name'] ?? ''),
                $slug,
                count($boardIds),
                $widgetCount,
                $taskCount,
                $errors > 0 ? ', ' . $errors . ' Eintrag/Einträge übersprungen' : ''
            ),
        ];
    }

    // ── Suche, Sitemap ───────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> Suchtreffer im Core-Format */
    public function searchPublicProjects(string $query, int $limit, string $locale = 'de'): array
    {
        $words = preg_split('/\s+/u', trim(strip_tags($query))) ?: [];
        $words = array_slice(array_values(array_filter(array_map(
            fn (string $word): string => $this->truncate($word, 60),
            $words
        ), static fn (string $word): bool => $word !== '' && (function_exists('mb_strlen') ? mb_strlen($word) : strlen($word)) >= 2)), 0, 6);

        if ($words === []) {
            return [];
        }

        $prefix = $locale === 'en' ? 'en/projects/' : 'projects/';
        $results = [];
        foreach ($this->repository->searchPublicProjects($words, max(1, min(20, $limit))) as $project) {
            $excerpt = (string) ($project['summary'] !== '' ? $project['summary'] : $project['description']);
            $results[] = [
                'title' => (string) $project['name'],
                'url' => $prefix . rawurlencode((string) $project['slug']),
                'excerpt' => $this->truncate(trim((string) preg_replace('/\s+/u', ' ', $excerpt)), 240),
                '_type' => 'projects',
                '_type_label' => $locale === 'en' ? 'Project' : 'Projekt',
                'date' => (string) $project['updated_at'],
            ];
        }

        return $results;
    }

    /** @return list<array{url:string,lastmod:string,changefreq:string,priority:float}> */
    public function getSitemapEntries(): array
    {
        $projects = $this->repository->getPublicProjectSlugs();
        if ($projects === []) {
            return [];
        }

        $entries = [[
            'url' => 'projects',
            'lastmod' => (string) ($projects[0]['updated_at'] ?? date('Y-m-d H:i:s')),
            'changefreq' => 'weekly',
            'priority' => 0.5,
        ]];

        foreach ($projects as $project) {
            if ($project['slug'] === '') {
                continue;
            }

            $entries[] = [
                'url' => 'projects/' . rawurlencode($project['slug']),
                'lastmod' => $project['updated_at'] !== '' ? $project['updated_at'] : date('Y-m-d H:i:s'),
                'changefreq' => 'weekly',
                'priority' => 0.6,
            ];
        }

        return $entries;
    }

    // ── Datenschutz und Aufbewahrung ─────────────────────────────────────────

    /** Audit-Einträge einer Person (Admin-Aktionen) für Auskunft/Export. */
    public function collectPersonalAuditData(int $userId): array
    {
        return array_map(fn (array $log): array => [
            'created_at' => $log['created_at'],
            'action' => $log['action'],
            'description' => $this->describeAuditAction((string) $log['action']),
            'entity_type' => $log['entity_type'],
            'entity_id' => $log['entity_id'],
            'project_id' => $log['project_id'],
            'result' => $log['result'],
            'actor_name' => $log['actor_name'],
        ], $this->repository->getAuditLogsForActor($userId));
    }

    public function anonymizePersonalAuditData(int $userId): int
    {
        return $this->repository->anonymizeAuditActor($userId);
    }

    public function countAuditLogsDue(int $days): int
    {
        return $days > 0 ? $this->repository->countAuditLogsBefore($this->retentionCutoff($days)) : 0;
    }

    public function pruneAuditLogs(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = $this->retentionCutoff($days);
        $total = 0;
        for ($batch = 0; $batch < 50; $batch++) {
            $deleted = $this->repository->deleteAuditLogsBefore($cutoff, 1000);
            $total += $deleted;
            if ($deleted < 1000) {
                break;
            }
        }

        return $total;
    }

    public function countAuditLogs(): int
    {
        return $this->repository->countAuditLogs();
    }

    public function recordSettingsChange(array $settings): void
    {
        $this->recordAuditLog('settings.update', 'settings', 0, 0, true, $settings);
    }

    // ── interne Helfer ───────────────────────────────────────────────────────

    /**
     * @param array<string, callable(list<int>): int> $operations
     */
    private function runBulk(string $entity, int $projectId, string $operation, array $ids, array $operations, string $label): array
    {
        if ($this->findProject($projectId) === null) {
            return ['success' => false, 'message' => 'Projekt wurde nicht gefunden.'];
        }

        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return ['success' => false, 'message' => 'Bitte mindestens einen Eintrag auswählen.'];
        }

        if (!isset($operations[$operation])) {
            return ['success' => false, 'message' => 'Unbekannte Sammelaktion.'];
        }

        $count = (int) $operations[$operation]($ids);
        $this->repository->touchProject($projectId);
        $this->recordAuditLog($entity . '.bulk', $entity, 0, $projectId, true, ['operation' => $operation, 'ids' => $ids, 'affected' => $count]);

        $labels = [
            'activate' => 'aktiviert',
            'deactivate' => 'deaktiviert',
            'public' => 'öffentlich geschaltet',
            'internal' => 'auf intern gesetzt',
            'delete' => 'gelöscht',
        ];

        return ['success' => true, 'message' => $count . ' ' . $label . ' ' . ($labels[$operation] ?? 'geändert') . '.'];
    }

    /** @return list<int> */
    private function normalizeIdList(array $ids): array
    {
        return array_slice(array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => is_scalar($value) ? (int) $value : 0, $ids),
            static fn (int $value): bool => $value > 0
        ))), 0, 500);
    }

    private function retentionCutoff(int $days): string
    {
        return date('Y-m-d H:i:s', time() - ($days * 86400));
    }

    private function decorateTask(array $task): array
    {
        $task['priority_label'] = (string) ($this->getTaskPriorities()[$task['priority'] ?? 'medium'] ?? 'Mittel');
        $task['column_key'] = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
        $dueDate = (string) ($task['due_date'] ?? '');
        $task['is_overdue'] = $dueDate !== '' && !empty($task['is_active']) && $dueDate < date('Y-m-d');
        return $task;
    }

    private function decorateProjects(array $projects): array
    {
        if ($projects === []) {
            return [];
        }

        $counts = $this->repository->getProjectEntityCounts(array_column($projects, 'id'));

        return array_map(function (array $project) use ($counts): array {
            $aggregate = $counts[(int) ($project['id'] ?? 0)] ?? ['board_count' => 0, 'widget_count' => 0, 'task_count' => 0];
            $project['board_count'] = (int) ($aggregate['board_count'] ?? 0);
            $project['widget_count'] = (int) ($aggregate['widget_count'] ?? 0);
            $project['task_count'] = (int) ($aggregate['task_count'] ?? 0);
            $project['preview_links'] = $this->getProjectPreviewLinks($project);
            return $project;
        }, $projects);
    }

    private function decorateProject(array $project): array
    {
        return $this->decorateProjects([$project])[0] ?? $project;
    }

    private function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']);
        $value = preg_replace('/[^a-z0-9\-\_]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
        return substr($value, 0, self::MAX_SLUG_LENGTH);
    }

    private function normalizeColor(string $value): string
    {
        $value = trim($value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '#2563eb';
    }

    private function decodePayload(string $payload): array
    {
        return $this->parsePayload($payload) ?? [];
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
        $value = trim(preg_replace('/[[:cntrl:]]+/u', ' ', strip_tags($value)) ?? '');
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

    /** @return list<array<string,mixed>> */
    private function collectBoardGroups(array $payload): array
    {
        foreach (self::BOARD_GROUP_KEYS as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        return [];
    }

    private function sanitizeBoardPayload(array $payload): array
    {
        foreach (self::BOARD_GROUP_KEYS as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return [$key => $this->sanitizeBoardGroups($payload[$key])];
            }
        }

        return ['columns' => []];
    }

    private function sanitizeBoardGroups(array $groups): array
    {
        $sanitized = [];
        $usedKeys = [];

        foreach (array_values($groups) as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $label = $this->sanitizeText((string) $item, self::MAX_SHORT_TEXT_LENGTH);
                if ($label !== '') {
                    $items[] = $label;
                }
            }

            $title = $this->sanitizeText((string) ($group['title'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
            if ($title === '' && $items === []) {
                continue;
            }

            $key = $this->buildColumnKey((string) ($group['key'] ?? ''), $title, $index);
            if (isset($usedKeys[$key])) {
                $key = $this->truncate($key, self::MAX_COLUMN_KEY_LENGTH - 4) . '-' . ($index + 1);
            }
            $usedKeys[$key] = true;

            $sanitized[] = [
                'key' => $key,
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
            if ($boardId > 0) {
                $grouped[$boardId][] = $task;
            }
        }

        return $grouped;
    }

    private function mergeTasksIntoBoardPayload(array $payload, array $tasks): array
    {
        $taskGroups = [];
        foreach ($tasks as $task) {
            $columnKey = $this->normalizeColumnKey((string) ($task['column_key'] ?? ''));
            if ($columnKey !== '') {
                $taskGroups[$columnKey][] = $task;
            }
        }

        foreach (self::BOARD_GROUP_KEYS as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $payload[$key] = array_map(function (array $group, int $index) use ($taskGroups): array {
                $columnKey = $this->buildColumnKey((string) ($group['key'] ?? ''), (string) ($group['title'] ?? 'Block'), $index);
                $wipLimit = $this->normalizeWipLimit($group['wip_limit'] ?? null);
                $columnTasks = $taskGroups[$columnKey] ?? [];
                $wipCount = count($columnTasks);
                $group['key'] = $columnKey;
                $group['wip_limit'] = $wipLimit;
                $group['wip_count'] = $wipCount;
                $group['wip_limit_reached'] = $wipLimit > 0 && $wipCount >= $wipLimit;
                $group['wip_over_limit'] = $wipLimit > 0 && $wipCount > $wipLimit;
                $group['tasks'] = $columnTasks;
                return $group;
            }, array_values($payload[$key]), array_keys(array_values($payload[$key])));

            break;
        }

        return $payload;
    }

    private function getColumnTasks(array $tasks, int $boardId, string $columnKey, int $excludedTaskId = 0): array
    {
        return array_values(array_filter($tasks, fn (array $task): bool => (int) ($task['id'] ?? 0) !== $excludedTaskId
            && (int) ($task['board_id'] ?? 0) === $boardId
            && $this->normalizeColumnKey((string) ($task['column_key'] ?? '')) === $columnKey));
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

        $finalOrder[$movedTaskId] ??= $movedTaskId;
        foreach (array_keys($existingTaskIds) as $existingTaskId) {
            $finalOrder[$existingTaskId] ??= $existingTaskId;
        }

        return array_values($finalOrder);
    }

    private function findBoardForProject(int $projectId, int $boardId): ?array
    {
        if ($boardId <= 0) {
            return null;
        }

        $board = $this->repository->findBoardById($boardId);
        if ($board === null || (int) ($board['project_id'] ?? 0) !== $projectId) {
            return null;
        }

        $board['payload_data'] = $this->sanitizeBoardPayload($this->decodePayload((string) ($board['payload'] ?? '')));
        return $board;
    }

    private function resolveBoardColumnKey(array $board, string $requestedKey): string
    {
        $requestedKey = $this->normalizeColumnKey($requestedKey);

        foreach ($this->collectBoardGroups((array) ($board['payload_data'] ?? [])) as $index => $group) {
            $columnKey = $this->buildColumnKey((string) ($group['key'] ?? ''), (string) ($group['title'] ?? 'Block'), $index);
            if ($requestedKey === '' || $requestedKey === $columnKey) {
                return $columnKey;
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
        return $normalized !== '' ? $normalized : 'column-' . ($index + 1);
    }

    private function normalizeColumnKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9\-_]+/', '-', $key) ?? '';
        $key = trim($key, '-_');
        $key = preg_replace('/[-_]{2,}/', '-', $key) ?? '';
        return $this->truncate($key, self::MAX_COLUMN_KEY_LENGTH);
    }

    /** '' = kein Datum, null = ungültig */
    private function normalizeDueDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function normalizeWipLimit(mixed $value): int
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
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
                if ($label !== '') {
                    $items[] = ['label' => $label, 'done' => !empty($item['done'])];
                }
                continue;
            }

            if ($widgetType === 'links') {
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                $url = $this->sanitizeUrl((string) ($item['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $items[] = ['label' => $label, 'url' => $url];
                }
                continue;
            }

            if ($widgetType === 'stats') {
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                $value = $this->sanitizeText((string) ($item['value'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                if ($label !== '' || $value !== '') {
                    $items[] = ['label' => $label, 'value' => $value];
                }
                continue;
            }

            if ($widgetType === 'timeline') {
                $date = $this->sanitizeText((string) ($item['date'] ?? ''), 40);
                $label = $this->sanitizeText((string) ($item['label'] ?? ''), self::MAX_SHORT_TEXT_LENGTH);
                if ($date !== '' || $label !== '') {
                    $items[] = ['date' => $date, 'label' => $label];
                }
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
        $parts = is_string($validated) ? parse_url($validated) : false;
        if (!is_array($parts)) {
            return '';
        }

        if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
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

        return (string) $validated;
    }

    private function toBooleanFlag(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value === 1,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true),
            default => false,
        };
    }

    private function getDefaultBoardPayload(string $boardType): array
    {
        return match ($boardType) {
            'kanban' => ['columns' => [
                ['title' => 'Backlog', 'wip_limit' => 0, 'items' => []],
                ['title' => 'In Arbeit', 'wip_limit' => 5, 'items' => []],
                ['title' => 'Review', 'wip_limit' => 3, 'items' => []],
                ['title' => 'Done', 'wip_limit' => 0, 'items' => []],
            ]],
            'diy' => ['lanes' => [
                ['title' => 'Idee', 'items' => ['Anforderungen sammeln', 'Materialliste definieren']],
                ['title' => 'Bauen', 'items' => []],
                ['title' => 'Test', 'items' => []],
                ['title' => 'Learn', 'items' => []],
            ]],
            'miro' => ['clusters' => [
                ['title' => 'Ideen', 'items' => []],
                ['title' => 'Referenzen', 'items' => []],
                ['title' => 'Nächste Schritte', 'items' => []],
            ]],
            'flow' => ['stages' => [
                ['title' => 'Ready', 'items' => []],
                ['title' => 'Doing', 'wip_limit' => 4, 'items' => []],
                ['title' => 'Blocked', 'items' => []],
                ['title' => 'Released', 'items' => []],
            ]],
            default => ['milestones' => [
                ['title' => 'Q1', 'items' => []],
                ['title' => 'Q2', 'items' => []],
                ['title' => 'Q3', 'items' => []],
                ['title' => 'Q4', 'items' => []],
            ]],
        };
    }

    private function getDefaultWidgetPayload(string $widgetType): array
    {
        return match ($widgetType) {
            'checklist' => ['items' => [['label' => 'Projektbriefing freigeben', 'done' => false], ['label' => 'Stakeholder onboarden', 'done' => false]]],
            'links' => ['items' => [['label' => 'Konzept', 'url' => 'https://example.com/konzept']]],
            'stats' => ['items' => [['label' => 'Fortschritt', 'value' => '0%'], ['label' => 'Offene Punkte', 'value' => '0']]],
            'timeline' => ['items' => [['date' => date('Y-m-d'), 'label' => 'Kickoff']]],
            default => ['items' => []],
        };
    }

    private function logOperationFailure(string $operation, array $context = []): void
    {
        $segments = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $segments[] = $key . '=' . (string) $value;
            }
        }

        error_log('[cms-projects] operation_failed=' . $operation . ($segments !== [] ? ' ' . implode(' ', $segments) : ''));
    }

    private function getActorContext(): array
    {
        if (!class_exists('CMS\\Auth')) {
            return ['id' => 0, 'name' => 'system'];
        }

        $auth = \CMS\Auth::instance();
        $user = method_exists($auth, 'currentUser') ? $auth->currentUser() : null;
        if (!is_object($user)) {
            return ['id' => 0, 'name' => 'system'];
        }

        $id = isset($user->id) ? (int) $user->id : 0;
        $name = '';
        foreach (['display_name', 'name', 'username'] as $field) {
            if (isset($user->{$field}) && is_string($user->{$field}) && trim($user->{$field}) !== '') {
                $name = trim($user->{$field});
                break;
            }
        }

        if ($name === '') {
            $name = $id > 0 ? 'user-' . $id : 'system';
        }

        return ['id' => max(0, $id), 'name' => $this->truncate($name, self::MAX_SHORT_TEXT_LENGTH)];
    }

    private function recordAuditLog(string $action, string $entityType, int $entityId, int $projectId, bool $success, array $context = []): void
    {
        $actor = $this->getActorContext();
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $this->repository->insertAuditLog([
                'actor_id' => (int) ($actor['id'] ?? 0),
                'actor_name' => (string) ($actor['name'] ?? 'system'),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => max(0, $entityId),
                'project_id' => max(0, $projectId),
                'result' => $success ? 'success' : 'failure',
                'context_json' => is_string($contextJson) ? $contextJson : '{}',
            ]);
        } catch (\Throwable $e) {
            error_log('[cms-projects] audit log write failed: ' . $e->getMessage());
        }
    }
}

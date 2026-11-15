<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Projects_Repository
{
    /** Explizite Spaltenlisten (keine Wildcard-Selects). */
    private const PROJECT_COLUMNS = 'id, name, slug, summary, description, status, visibility, owner_name, accent_color, member_headline, public_headline, created_at, updated_at';
    private const BOARD_COLUMNS = 'id, project_id, board_type, title, description, position, is_public, is_active, payload, created_at, updated_at';
    private const WIDGET_COLUMNS = 'id, project_id, scope, widget_type, title, content, position, is_active, payload, created_at, updated_at';
    private const TASK_COLUMNS = 'id, project_id, board_id, column_key, title, description, priority, assignee_name, due_date, sort_order, is_public, is_active, payload, created_at, updated_at';
    private const AUDIT_COLUMNS = 'id, actor_id, actor_name, action, entity_type, entity_id, project_id, result, context_json, created_at';

    private \CMS\Database $db;
    private string $projectsTable = 'projects_projects';
    private string $boardsTable = 'projects_boards';
    private string $widgetsTable = 'projects_widgets';
    private string $tasksTable = 'projects_tasks';
    private string $auditLogTable = 'projects_audit_logs';
    private string $projectsTableFull;
    private string $boardsTableFull;
    private string $widgetsTableFull;
    private string $tasksTableFull;
    private string $auditLogTableFull;

    public function __construct()
    {
        $this->db = \CMS\Database::instance();
        $prefix = $this->sanitizeSqlIdentifier((string) $this->db->getPrefix());
        $this->projectsTableFull = $this->sanitizeSqlIdentifier($prefix . $this->projectsTable);
        $this->boardsTableFull = $this->sanitizeSqlIdentifier($prefix . $this->boardsTable);
        $this->widgetsTableFull = $this->sanitizeSqlIdentifier($prefix . $this->widgetsTable);
        $this->tasksTableFull = $this->sanitizeSqlIdentifier($prefix . $this->tasksTable);
        $this->auditLogTableFull = $this->sanitizeSqlIdentifier($prefix . $this->auditLogTable);
    }

    public function ensureTables(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->projectsTableFull}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(120) NOT NULL,
            `summary` TEXT DEFAULT NULL,
            `description` LONGTEXT DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `visibility` VARCHAR(20) NOT NULL DEFAULT 'member',
            `owner_name` VARCHAR(190) NOT NULL DEFAULT '',
            `accent_color` VARCHAR(20) NOT NULL DEFAULT '#2563eb',
            `member_headline` VARCHAR(190) NOT NULL DEFAULT '',
            `public_headline` VARCHAR(190) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_slug` (`slug`),
            KEY `idx_status_visibility` (`status`, `visibility`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->boardsTableFull}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id` INT UNSIGNED NOT NULL,
            `board_type` VARCHAR(30) NOT NULL,
            `title` VARCHAR(190) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_public` TINYINT(1) NOT NULL DEFAULT 1,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `payload` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_project_position` (`project_id`, `position`),
            KEY `idx_project_public` (`project_id`, `is_public`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->widgetsTableFull}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id` INT UNSIGNED NOT NULL,
            `scope` VARCHAR(20) NOT NULL DEFAULT 'both',
            `widget_type` VARCHAR(30) NOT NULL DEFAULT 'text',
            `title` VARCHAR(190) NOT NULL,
            `content` LONGTEXT DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `payload` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_project_scope` (`project_id`, `scope`, `is_active`),
            KEY `idx_project_widget_position` (`project_id`, `position`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->tasksTableFull}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id` INT UNSIGNED NOT NULL,
            `board_id` INT UNSIGNED NOT NULL,
            `column_key` VARCHAR(80) NOT NULL,
            `title` VARCHAR(190) NOT NULL,
            `description` LONGTEXT DEFAULT NULL,
            `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
            `assignee_name` VARCHAR(190) NOT NULL DEFAULT '',
            `due_date` DATE DEFAULT NULL,
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_public` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `payload` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_project_board_column` (`project_id`, `board_id`, `column_key`),
            KEY `idx_board_public_active` (`board_id`, `is_public`, `is_active`),
            KEY `idx_project_sort` (`project_id`, `sort_order`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->auditLogTableFull}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `actor_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `actor_name` VARCHAR(190) NOT NULL DEFAULT '',
            `action` VARCHAR(80) NOT NULL,
            `entity_type` VARCHAR(50) NOT NULL,
            `entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `project_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `result` VARCHAR(20) NOT NULL DEFAULT 'success',
            `context_json` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_project_result` (`project_id`, `result`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function getProjects(): array
    {
        $rows = $this->db->get_results('SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` ORDER BY updated_at DESC, id DESC") ?: [];
        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function getPublicProjects(): array
    {
        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE status = ? AND visibility = ? ORDER BY updated_at DESC, id DESC",
            ['active', 'public']
        ) ?: [];

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function getMemberProjects(): array
    {
        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE status IN (?, ?) AND visibility IN (?, ?) ORDER BY updated_at DESC, id DESC",
            ['active', 'paused', 'member', 'public']
        ) ?: [];

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function findProjectById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapProjectRow($row) : null;
    }

    public function findProjectBySlug(string $slug): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE slug = ? LIMIT 1", [$slug]);
        return $row ? $this->mapProjectRow($row) : null;
    }

    public function saveProject(array $data, ?int $id = null): int|false
    {
        $payload = [
            'name' => (string) ($data['name'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'status' => (string) ($data['status'] ?? 'active'),
            'visibility' => (string) ($data['visibility'] ?? 'member'),
            'owner_name' => (string) ($data['owner_name'] ?? ''),
            'accent_color' => (string) ($data['accent_color'] ?? '#2563eb'),
            'member_headline' => (string) ($data['member_headline'] ?? ''),
            'public_headline' => (string) ($data['public_headline'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->projectsTable, $payload);
        }

        $updated = $this->db->update($this->projectsTable, $payload, ['id' => $id]);
        return $updated ? $id : false;
    }

    public function getBoardsByProject(int $projectId, bool $includeInactive = true, ?bool $publicOnly = null): array
    {
        $sql = 'SELECT ' . self::BOARD_COLUMNS . " FROM `{$this->boardsTableFull}` WHERE project_id = ?";
        $params = [$projectId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($publicOnly === true) {
            $sql .= ' AND is_public = 1';
        }

        $sql .= ' ORDER BY position ASC, id ASC';
        $rows = $this->db->get_results($sql, $params) ?: [];
        return array_map([$this, 'mapBoardRow'], $rows);
    }

    public function saveBoard(array $data, ?int $id = null): int|false
    {
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'board_type' => (string) ($data['board_type'] ?? 'kanban'),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'position' => (int) ($data['position'] ?? 0),
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->boardsTable, $payload);
        }

        $updated = $this->db->update($this->boardsTable, $payload, ['id' => $id]);
        return $updated ? $id : false;
    }

    public function getWidgetsByProject(int $projectId, string $scope = 'all', bool $includeInactive = true): array
    {
        $sql = 'SELECT ' . self::WIDGET_COLUMNS . " FROM `{$this->widgetsTableFull}` WHERE project_id = ?";
        $params = [$projectId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($scope === 'public') {
            $sql .= " AND scope IN ('public', 'both')";
        } elseif ($scope === 'member') {
            $sql .= " AND scope IN ('member', 'both')";
        }

        $sql .= ' ORDER BY position ASC, id ASC';
        $rows = $this->db->get_results($sql, $params) ?: [];
        return array_map([$this, 'mapWidgetRow'], $rows);
    }

    public function saveWidget(array $data, ?int $id = null): int|false
    {
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'scope' => (string) ($data['scope'] ?? 'both'),
            'widget_type' => (string) ($data['widget_type'] ?? 'text'),
            'title' => (string) ($data['title'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->widgetsTable, $payload);
        }

        $updated = $this->db->update($this->widgetsTable, $payload, ['id' => $id]);
        return $updated ? $id : false;
    }

    public function getTasksByProject(int $projectId, string $scope = 'admin', bool $includeInactive = true): array
    {
        $sql = 'SELECT ' . self::TASK_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE project_id = ?";
        $params = [$projectId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($scope === 'public') {
            $sql .= ' AND is_public = 1';
        }

        $sql .= ' ORDER BY board_id ASC, sort_order ASC, id ASC';
        $rows = $this->db->get_results($sql, $params) ?: [];
        return array_map([$this, 'mapTaskRow'], $rows);
    }

    public function getTasksByBoard(int $boardId, string $scope = 'admin', bool $includeInactive = true): array
    {
        $sql = 'SELECT ' . self::TASK_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE board_id = ?";
        $params = [$boardId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($scope === 'public') {
            $sql .= ' AND is_public = 1';
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $this->db->get_results($sql, $params) ?: [];
        return array_map([$this, 'mapTaskRow'], $rows);
    }

    public function findTaskById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::TASK_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapTaskRow($row) : null;
    }

    public function saveTask(array $data, ?int $id = null): int|false
    {
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'board_id' => (int) ($data['board_id'] ?? 0),
            'column_key' => (string) ($data['column_key'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'priority' => (string) ($data['priority'] ?? 'medium'),
            'assignee_name' => (string) ($data['assignee_name'] ?? ''),
            'due_date' => (string) ($data['due_date'] ?? '') !== '' ? (string) ($data['due_date'] ?? '') : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->tasksTable, $payload);
        }

        $updated = $this->db->update($this->tasksTable, $payload, ['id' => $id]);
        return $updated ? $id : false;
    }

    public function updateTaskPosition(int $id, int $boardId, string $columnKey, int $sortOrder): bool
    {
        $updated = $this->db->update($this->tasksTable, [
            'board_id' => $boardId,
            'column_key' => $columnKey,
            'sort_order' => max(0, $sortOrder),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $updated !== false;
    }

    public function deleteTask(int $id): bool
    {
        return $this->db->delete($this->tasksTable, ['id' => $id]);
    }

    public function countProjects(): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->projectsTableFull}`") ?? 0);
    }

    public function countBoards(): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->boardsTableFull}`") ?? 0);
    }

    public function countWidgets(): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->widgetsTableFull}`") ?? 0);
    }

    public function countTasks(): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->tasksTableFull}`") ?? 0);
    }

    public function getProjectEntityCounts(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => (int) $value, $projectIds), static fn (int $value): bool => $value > 0)));
        if ($projectIds === []) {
            return [];
        }

        $inPlaceholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $counts = [];

        foreach ($projectIds as $projectId) {
            $counts[$projectId] = [
                'board_count' => 0,
                'widget_count' => 0,
                'task_count' => 0,
            ];
        }

        $boardRows = $this->db->get_results(
            "SELECT project_id, COUNT(*) AS aggregate_count
             FROM `{$this->boardsTableFull}`
             WHERE project_id IN ({$inPlaceholders})
             GROUP BY project_id",
            $projectIds
        ) ?: [];

        foreach ($boardRows as $row) {
            $projectId = (int) ($row->project_id ?? 0);
            if ($projectId > 0 && isset($counts[$projectId])) {
                $counts[$projectId]['board_count'] = (int) ($row->aggregate_count ?? 0);
            }
        }

        $widgetRows = $this->db->get_results(
            "SELECT project_id, COUNT(*) AS aggregate_count
             FROM `{$this->widgetsTableFull}`
             WHERE project_id IN ({$inPlaceholders})
             GROUP BY project_id",
            $projectIds
        ) ?: [];

        foreach ($widgetRows as $row) {
            $projectId = (int) ($row->project_id ?? 0);
            if ($projectId > 0 && isset($counts[$projectId])) {
                $counts[$projectId]['widget_count'] = (int) ($row->aggregate_count ?? 0);
            }
        }

        $taskRows = $this->db->get_results(
            "SELECT project_id, COUNT(*) AS aggregate_count
             FROM `{$this->tasksTableFull}`
             WHERE project_id IN ({$inPlaceholders})
             GROUP BY project_id",
            $projectIds
        ) ?: [];

        foreach ($taskRows as $row) {
            $projectId = (int) ($row->project_id ?? 0);
            if ($projectId > 0 && isset($counts[$projectId])) {
                $counts[$projectId]['task_count'] = (int) ($row->aggregate_count ?? 0);
            }
        }

        return $counts;
    }

    public function getSummaryCounts(): array
    {
        $row = $this->db->get_row(
            "SELECT
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}`) AS projects,
                (SELECT COUNT(*) FROM `{$this->boardsTableFull}`) AS boards,
                (SELECT COUNT(*) FROM `{$this->widgetsTableFull}`) AS widgets,
                (SELECT COUNT(*) FROM `{$this->tasksTableFull}`) AS tasks,
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE status = ?) AS active_projects,
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE visibility = ?) AS public_projects",
            ['active', 'public']
        );

        if (!$row) {
            return [
                'projects' => 0,
                'boards' => 0,
                'widgets' => 0,
                'tasks' => 0,
                'active_projects' => 0,
                'public_projects' => 0,
            ];
        }

        return [
            'projects' => (int) ($row->projects ?? 0),
            'boards' => (int) ($row->boards ?? 0),
            'widgets' => (int) ($row->widgets ?? 0),
            'tasks' => (int) ($row->tasks ?? 0),
            'active_projects' => (int) ($row->active_projects ?? 0),
            'public_projects' => (int) ($row->public_projects ?? 0),
        ];
    }

    public function insertAuditLog(array $payload): int|false
    {
        return $this->db->insert($this->auditLogTable, [
            'actor_id' => max(0, (int) ($payload['actor_id'] ?? 0)),
            'actor_name' => (string) ($payload['actor_name'] ?? ''),
            'action' => (string) ($payload['action'] ?? ''),
            'entity_type' => (string) ($payload['entity_type'] ?? ''),
            'entity_id' => max(0, (int) ($payload['entity_id'] ?? 0)),
            'project_id' => max(0, (int) ($payload['project_id'] ?? 0)),
            'result' => (string) ($payload['result'] ?? 'success'),
            'context_json' => (string) ($payload['context_json'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getAuditLogs(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->db->get_results(
            'SELECT ' . self::AUDIT_COLUMNS . " FROM `{$this->auditLogTableFull}` ORDER BY id DESC LIMIT {$limit}"
        ) ?: [];

        return array_map([$this, 'mapAuditLogRow'], $rows);
    }

    /**
     * Mehrere Löschungen atomar ausführen.
     *
     * @param list<array{0:string,1:list<int>}> $statements
     */
    private function runInTransaction(array $statements): bool
    {
        $pdo = $this->db->getPdo();
        try {
            $pdo->beginTransaction();
            foreach ($statements as [$sql, $params]) {
                $this->db->execute($sql, $params);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[cms-projects] transaction failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Projekt inkl. Boards, Widgets und Tickets löschen. */
    public function deleteProjectCascade(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->runInTransaction([
            ["DELETE FROM `{$this->tasksTableFull}` WHERE project_id = ?", [$id]],
            ["DELETE FROM `{$this->widgetsTableFull}` WHERE project_id = ?", [$id]],
            ["DELETE FROM `{$this->boardsTableFull}` WHERE project_id = ?", [$id]],
            ["DELETE FROM `{$this->projectsTableFull}` WHERE id = ?", [$id]],
        ]);
    }

    /** Board inkl. seiner Tickets löschen. */
    public function deleteBoardCascade(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->runInTransaction([
            ["DELETE FROM `{$this->tasksTableFull}` WHERE board_id = ?", [$id]],
            ["DELETE FROM `{$this->boardsTableFull}` WHERE id = ?", [$id]],
        ]);
    }

    public function findBoardById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::BOARD_COLUMNS . " FROM `{$this->boardsTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapBoardRow($row) : null;
    }

    public function findWidgetById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::WIDGET_COLUMNS . " FROM `{$this->widgetsTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapWidgetRow($row) : null;
    }

    public function deleteWidget(int $id): bool
    {
        return $id > 0 && $this->db->delete($this->widgetsTable, ['id' => $id]);
    }

    /**
     * Projekte mit Filtern (Status, Sichtbarkeit, Suche) für die Verwaltung.
     *
     * @return list<array<string,mixed>>
     */
    public function findProjects(string $status = '', string $visibility = '', string $search = ''): array
    {
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($visibility !== '') {
            $where[] = 'visibility = ?';
            $params[] = $visibility;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
            $where[] = '(name LIKE ? OR slug LIKE ? OR owner_name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}`"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY updated_at DESC, id DESC LIMIT 500',
            $params
        ) ?: [];

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    /**
     * Zuletzt geänderte aktive Tickets (Dashboard).
     *
     * @return list<array<string,mixed>>
     */
    public function getRecentTasks(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->db->get_results(
            "SELECT t.id, t.project_id, t.title, t.priority, t.due_date, t.column_key, t.updated_at, p.name AS project_name
             FROM `{$this->tasksTableFull}` t
             INNER JOIN `{$this->projectsTableFull}` p ON p.id = t.project_id
             WHERE t.is_active = 1
             ORDER BY t.updated_at DESC, t.id DESC
             LIMIT {$limit}"
        ) ?: [];

        return array_map(static fn (object $row): array => (array) $row, $rows);
    }

    public function countOverdueTasks(): int
    {
        return (int) ($this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->tasksTableFull}` WHERE is_active = 1 AND due_date IS NOT NULL AND due_date < CURDATE()"
        ) ?? 0);
    }

    /**
     * Audit-Log mit Filtern.
     *
     * @return list<array<string,mixed>>
     */
    public function findAuditLogs(int $limit = 200, int $projectId = 0, string $result = '', string $entityType = ''): array
    {
        $limit = max(1, min(5000, $limit));
        $where = [];
        $params = [];
        if ($projectId > 0) {
            $where[] = 'project_id = ?';
            $params[] = $projectId;
        }
        if (in_array($result, ['success', 'failure'], true)) {
            $where[] = 'result = ?';
            $params[] = $result;
        }
        if ($entityType !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }

        $rows = $this->db->get_results(
            'SELECT ' . self::AUDIT_COLUMNS . " FROM `{$this->auditLogTableFull}`"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY id DESC LIMIT {$limit}",
            $params
        ) ?: [];

        return array_map([$this, 'mapAuditLogRow'], $rows);
    }

    public function countAuditLogs(): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->auditLogTableFull}`") ?? 0);
    }

    public function countAuditLogsOlderThan(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return (int) ($this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->auditLogTableFull}` WHERE created_at < (NOW() - INTERVAL ? DAY)",
            [$days]
        ) ?? 0);
    }

    /** Löscht Audit-Einträge älter als $days Tage in Blöcken; liefert die Anzahl. */
    public function deleteAuditLogsOlderThan(int $days, int $batch = 1000): int
    {
        if ($days <= 0) {
            return 0;
        }

        $batch = max(100, min(5000, $batch));
        $deleted = 0;
        for ($i = 0; $i < 50; $i++) {
            $count = $this->db->execute(
                "DELETE FROM `{$this->auditLogTableFull}` WHERE created_at < (NOW() - INTERVAL ? DAY) LIMIT {$batch}",
                [$days]
            )->rowCount();
            $deleted += $count;
            if ($count < $batch) {
                break;
            }
        }

        return $deleted;
    }

    /** Entfernt alle Plugin-Tabellen (Deinstallation). */
    public function dropTables(): void
    {
        foreach ([$this->auditLogTableFull, $this->tasksTableFull, $this->widgetsTableFull, $this->boardsTableFull, $this->projectsTableFull] as $table) {
            $this->db->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /**
     * Öffentliche, aktive Projekte, die alle Suchwörter in Name, Zusammenfassung oder Beschreibung enthalten.
     *
     * @param list<string> $words
     * @return list<array<string,mixed>>
     */
    public function searchPublicProjects(array $words, int $limit = 10): array
    {
        $words = array_slice(array_values(array_filter(array_map('trim', $words), static fn (string $w): bool => $w !== '')), 0, 8);
        if ($words === []) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $where = ["status = 'active'", "visibility = 'public'"];
        $params = [];
        foreach ($words as $word) {
            $like = '%' . addcslashes(mb_substr($word, 0, 60), '%_\\') . '%';
            $where[] = '(name LIKE ? OR summary LIKE ? OR description LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE " . implode(' AND ', $where)
            . " ORDER BY updated_at DESC LIMIT {$limit}",
            $params
        ) ?: [];

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    private function mapProjectRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
            'slug' => (string) ($row->slug ?? ''),
            'summary' => (string) ($row->summary ?? ''),
            'description' => (string) ($row->description ?? ''),
            'status' => (string) ($row->status ?? 'active'),
            'visibility' => (string) ($row->visibility ?? 'member'),
            'owner_name' => (string) ($row->owner_name ?? ''),
            'accent_color' => (string) ($row->accent_color ?? '#2563eb'),
            'member_headline' => (string) ($row->member_headline ?? ''),
            'public_headline' => (string) ($row->public_headline ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function mapBoardRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'project_id' => (int) ($row->project_id ?? 0),
            'board_type' => (string) ($row->board_type ?? 'kanban'),
            'title' => (string) ($row->title ?? ''),
            'description' => (string) ($row->description ?? ''),
            'position' => (int) ($row->position ?? 0),
            'is_public' => (int) ($row->is_public ?? 0),
            'is_active' => (int) ($row->is_active ?? 0),
            'payload' => (string) ($row->payload ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function mapWidgetRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'project_id' => (int) ($row->project_id ?? 0),
            'scope' => (string) ($row->scope ?? 'both'),
            'widget_type' => (string) ($row->widget_type ?? 'text'),
            'title' => (string) ($row->title ?? ''),
            'content' => (string) ($row->content ?? ''),
            'position' => (int) ($row->position ?? 0),
            'is_active' => (int) ($row->is_active ?? 0),
            'payload' => (string) ($row->payload ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function mapTaskRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'project_id' => (int) ($row->project_id ?? 0),
            'board_id' => (int) ($row->board_id ?? 0),
            'column_key' => (string) ($row->column_key ?? ''),
            'title' => (string) ($row->title ?? ''),
            'description' => (string) ($row->description ?? ''),
            'priority' => (string) ($row->priority ?? 'medium'),
            'assignee_name' => (string) ($row->assignee_name ?? ''),
            'due_date' => (string) ($row->due_date ?? ''),
            'sort_order' => (int) ($row->sort_order ?? 0),
            'is_public' => (int) ($row->is_public ?? 0),
            'is_active' => (int) ($row->is_active ?? 0),
            'payload' => (string) ($row->payload ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function mapAuditLogRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'actor_id' => (int) ($row->actor_id ?? 0),
            'actor_name' => (string) ($row->actor_name ?? ''),
            'action' => (string) ($row->action ?? ''),
            'entity_type' => (string) ($row->entity_type ?? ''),
            'entity_id' => (int) ($row->entity_id ?? 0),
            'project_id' => (int) ($row->project_id ?? 0),
            'result' => (string) ($row->result ?? 'success'),
            'context_json' => (string) ($row->context_json ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
        ];
    }

    private function sanitizeSqlIdentifier(string $identifier): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]+/', '', $identifier) ?? '';
        return $sanitized !== '' ? $sanitized : 'cms_projects';
    }
}

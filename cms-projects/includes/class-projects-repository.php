<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Datenzugriff von CMS Projects (ausschließlich Prepared Statements über \CMS\Database).
 */
final class CMS_Projects_Repository
{
    public const TABLE_PROJECTS = 'projects_projects';
    public const TABLE_BOARDS = 'projects_boards';
    public const TABLE_WIDGETS = 'projects_widgets';
    public const TABLE_TASKS = 'projects_tasks';
    public const TABLE_AUDIT = 'projects_audit_logs';

    private const PROJECT_COLUMNS = 'id, name, slug, summary, description, status, visibility, owner_name, accent_color, member_headline, public_headline, created_at, updated_at';
    private const PROJECT_LIST_COLUMNS = 'id, name, slug, summary, status, visibility, owner_name, accent_color, member_headline, public_headline, created_at, updated_at';
    private const BOARD_COLUMNS = 'id, project_id, board_type, title, description, position, is_public, is_active, payload, created_at, updated_at';
    private const WIDGET_COLUMNS = 'id, project_id, scope, widget_type, title, content, position, is_active, payload, created_at, updated_at';
    private const TASK_COLUMNS = 'id, project_id, board_id, column_key, title, description, priority, assignee_name, due_date, sort_order, is_public, is_active, payload, created_at, updated_at';
    private const TASK_BOARD_COLUMNS = 'id, project_id, board_id, column_key, title, description, priority, assignee_name, due_date, sort_order, is_public, is_active, created_at, updated_at';
    private const TASK_LIST_COLUMNS = 'id, project_id, board_id, column_key, title, priority, assignee_name, due_date, sort_order, is_public, is_active, created_at, updated_at';
    private const AUDIT_COLUMNS = 'id, actor_id, actor_name, action, entity_type, entity_id, project_id, result, context_json, created_at';

    private \CMS\Database $db;
    private string $projectsTableFull;
    private string $boardsTableFull;
    private string $widgetsTableFull;
    private string $tasksTableFull;
    private string $auditLogTableFull;

    public function __construct()
    {
        $this->db = \CMS\Database::instance();
        $prefix = $this->sanitizeSqlIdentifier((string) $this->db->getPrefix());
        $this->projectsTableFull = $this->sanitizeSqlIdentifier($prefix . self::TABLE_PROJECTS);
        $this->boardsTableFull = $this->sanitizeSqlIdentifier($prefix . self::TABLE_BOARDS);
        $this->widgetsTableFull = $this->sanitizeSqlIdentifier($prefix . self::TABLE_WIDGETS);
        $this->tasksTableFull = $this->sanitizeSqlIdentifier($prefix . self::TABLE_TASKS);
        $this->auditLogTableFull = $this->sanitizeSqlIdentifier($prefix . self::TABLE_AUDIT);
    }

    // ── Schema ───────────────────────────────────────────────────────────────

    /** @return list<string> vollständige Tabellennamen des Plugins */
    public function getTableNames(): array
    {
        return [
            $this->projectsTableFull,
            $this->boardsTableFull,
            $this->widgetsTableFull,
            $this->tasksTableFull,
            $this->auditLogTableFull,
        ];
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
            KEY `idx_status_visibility` (`status`, `visibility`),
            KEY `idx_visibility_status_updated` (`visibility`, `status`, `updated_at`),
            KEY `idx_updated` (`updated_at`)
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
            KEY `idx_project_sort` (`project_id`, `sort_order`, `id`),
            KEY `idx_project_board_sort` (`project_id`, `board_id`, `sort_order`),
            KEY `idx_active_due` (`is_active`, `due_date`)
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
            KEY `idx_project_result` (`project_id`, `result`),
            KEY `idx_actor` (`actor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Ergänzt Indizes in bestehenden Installationen (idempotent, Prüfung über information_schema).
     */
    public function migrateIndexes(): void
    {
        $indexes = [
            [$this->projectsTableFull, 'idx_visibility_status_updated', '(`visibility`, `status`, `updated_at`)'],
            [$this->projectsTableFull, 'idx_updated', '(`updated_at`)'],
            [$this->tasksTableFull, 'idx_project_board_sort', '(`project_id`, `board_id`, `sort_order`)'],
            [$this->tasksTableFull, 'idx_active_due', '(`is_active`, `due_date`)'],
            [$this->auditLogTableFull, 'idx_actor', '(`actor_id`)'],
        ];

        foreach ($indexes as [$table, $indexName, $columns]) {
            if ($this->indexExists($table, $indexName)) {
                continue;
            }

            try {
                $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns}");
            } catch (\Throwable $e) {
                error_log('[cms-projects] index migration failed table=' . $table . ' index=' . $indexName);
            }
        }
    }

    public function dropTables(): void
    {
        foreach ($this->getTableNames() as $table) {
            $this->db->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /** @return array<string,bool> Tabellenname → vorhanden */
    public function getTableStatus(): array
    {
        $names = $this->getTableNames();
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $existing = [];
        try {
            $rows = $this->db->get_results(
                "SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})",
                $names
            );
            foreach ($rows as $row) {
                $existing[strtolower((string) ($row->table_name ?? ''))] = true;
            }
        } catch (\Throwable) {
            return array_fill_keys($names, false);
        }

        $status = [];
        foreach ($names as $name) {
            $status[$name] = isset($existing[strtolower($name)]);
        }

        return $status;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $count = $this->db->get_var(
                'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $indexName]
            );
        } catch (\Throwable) {
            return true;
        }

        return (int) $count > 0;
    }

    // ── Projekte ─────────────────────────────────────────────────────────────

    /**
     * @param array{q?:string,status?:string,visibility?:string} $filters
     */
    public function findProjects(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildProjectFilter($filters);
        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_LIST_COLUMNS . " FROM `{$this->projectsTableFull}`{$where} ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?",
            $params
        );

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function countProjectsFiltered(array $filters): int
    {
        [$where, $params] = $this->buildProjectFilter($filters);
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->projectsTableFull}`{$where}", $params) ?? 0);
    }

    /** @return list<array{id:int,name:string,slug:string,status:string,visibility:string}> */
    public function getProjectOptions(): array
    {
        $rows = $this->db->get_results(
            "SELECT id, name, slug, status, visibility FROM `{$this->projectsTableFull}` ORDER BY name ASC, id ASC"
        );

        return array_map(static fn (object $row): array => [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
            'slug' => (string) ($row->slug ?? ''),
            'status' => (string) ($row->status ?? ''),
            'visibility' => (string) ($row->visibility ?? ''),
        ], $rows);
    }

    public function getPublicProjects(): array
    {
        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_LIST_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE visibility = ? AND status = ? ORDER BY updated_at DESC, id DESC",
            ['public', 'active']
        );

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function getMemberProjects(): array
    {
        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_LIST_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE visibility IN (?, ?) AND status IN (?, ?) ORDER BY updated_at DESC, id DESC",
            ['member', 'public', 'active', 'paused']
        );

        return array_map([$this, 'mapProjectRow'], $rows);
    }

    public function countMemberProjects(): int
    {
        return (int) ($this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE visibility IN (?, ?) AND status IN (?, ?)",
            ['member', 'public', 'active', 'paused']
        ) ?? 0);
    }

    /** @return list<array{slug:string,updated_at:string}> */
    public function getPublicProjectSlugs(int $limit = 1000): array
    {
        $rows = $this->db->get_results(
            "SELECT slug, updated_at FROM `{$this->projectsTableFull}` WHERE visibility = ? AND status = ? ORDER BY updated_at DESC LIMIT ?",
            ['public', 'active', max(1, min(5000, $limit))]
        );

        return array_map(static fn (object $row): array => [
            'slug' => (string) ($row->slug ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ], $rows);
    }

    /**
     * Öffentliche Projekte, die alle Suchwörter (Name, Kurzbeschreibung oder Beschreibung) enthalten.
     *
     * @param list<string> $words
     */
    public function searchPublicProjects(array $words, int $limit): array
    {
        if ($words === []) {
            return [];
        }

        $clauses = [];
        $params = ['public', 'active'];
        foreach ($words as $word) {
            $like = '%' . $this->escapeLike($word) . '%';
            $clauses[] = '(name LIKE ? OR summary LIKE ? OR description LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $params[] = max(1, min(50, $limit));

        $rows = $this->db->get_results(
            'SELECT ' . self::PROJECT_COLUMNS . " FROM `{$this->projectsTableFull}` WHERE visibility = ? AND status = ? AND "
            . implode(' AND ', $clauses) . ' ORDER BY updated_at DESC LIMIT ?',
            $params
        );

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

    public function slugExists(string $slug): bool
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE slug = ?", [$slug]) ?? 0) > 0;
    }

    public function saveProject(array $data, ?int $id = null): int|false
    {
        $now = date('Y-m-d H:i:s');
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
            'updated_at' => $now,
        ];

        if ($id === null) {
            $payload['created_at'] = $now;
            $insertId = $this->db->insert(self::TABLE_PROJECTS, $payload);
            return is_int($insertId) && $insertId > 0 ? $insertId : false;
        }

        return $this->db->update(self::TABLE_PROJECTS, $payload, ['id' => $id]) ? $id : false;
    }

    public function touchProject(int $projectId): void
    {
        if ($projectId > 0) {
            $this->db->update(self::TABLE_PROJECTS, ['updated_at' => date('Y-m-d H:i:s')], ['id' => $projectId]);
        }
    }

    /** @param list<int> $ids */
    public function updateProjectsStatus(array $ids, string $status): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $placeholders = $this->placeholders($ids);
        $stmt = $this->db->execute(
            "UPDATE `{$this->projectsTableFull}` SET status = ?, updated_at = ? WHERE id IN ({$placeholders})",
            array_merge([$status, date('Y-m-d H:i:s')], $ids)
        );

        return $stmt->rowCount();
    }

    /**
     * Löscht Projekte samt Boards, Widgets und Tickets.
     *
     * @param list<int> $ids
     */
    public function deleteProjects(array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $placeholders = $this->placeholders($ids);
        $this->db->execute("DELETE FROM `{$this->tasksTableFull}` WHERE project_id IN ({$placeholders})", $ids);
        $this->db->execute("DELETE FROM `{$this->widgetsTableFull}` WHERE project_id IN ({$placeholders})", $ids);
        $this->db->execute("DELETE FROM `{$this->boardsTableFull}` WHERE project_id IN ({$placeholders})", $ids);
        $stmt = $this->db->execute("DELETE FROM `{$this->projectsTableFull}` WHERE id IN ({$placeholders})", $ids);

        return $stmt->rowCount();
    }

    // ── Boards ───────────────────────────────────────────────────────────────

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
        return array_map([$this, 'mapBoardRow'], $this->db->get_results($sql, $params));
    }

    public function findBoardById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::BOARD_COLUMNS . " FROM `{$this->boardsTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapBoardRow($row) : null;
    }

    public function saveBoard(array $data, ?int $id = null): int|false
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'board_type' => (string) ($data['board_type'] ?? 'kanban'),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'position' => (int) ($data['position'] ?? 0),
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => $now,
        ];

        if ($id === null) {
            $payload['created_at'] = $now;
            $insertId = $this->db->insert(self::TABLE_BOARDS, $payload);
            return is_int($insertId) && $insertId > 0 ? $insertId : false;
        }

        return $this->db->update(self::TABLE_BOARDS, $payload, ['id' => $id]) ? $id : false;
    }

    /**
     * Löscht Boards eines Projekts samt ihrer Tickets.
     *
     * @param list<int> $ids
     */
    public function deleteBoards(int $projectId, array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $placeholders = $this->placeholders($ids);
        $this->db->execute(
            "DELETE FROM `{$this->tasksTableFull}` WHERE project_id = ? AND board_id IN ({$placeholders})",
            array_merge([$projectId], $ids)
        );
        $stmt = $this->db->execute(
            "DELETE FROM `{$this->boardsTableFull}` WHERE project_id = ? AND id IN ({$placeholders})",
            array_merge([$projectId], $ids)
        );

        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    public function updateBoardsFlag(int $projectId, array $ids, string $column, int $value): int
    {
        return $this->updateFlag($this->boardsTableFull, $projectId, $ids, $column, $value, ['is_active', 'is_public']);
    }

    // ── Widgets ──────────────────────────────────────────────────────────────

    public function getWidgetsByProject(int $projectId, string $scope = 'all', bool $includeInactive = true): array
    {
        $sql = 'SELECT ' . self::WIDGET_COLUMNS . " FROM `{$this->widgetsTableFull}` WHERE project_id = ?";
        $params = [$projectId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($scope === 'public' || $scope === 'member') {
            $sql .= ' AND scope IN (?, ?)';
            $params[] = $scope;
            $params[] = 'both';
        }

        $sql .= ' ORDER BY position ASC, id ASC';
        return array_map([$this, 'mapWidgetRow'], $this->db->get_results($sql, $params));
    }

    public function findWidgetById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::WIDGET_COLUMNS . " FROM `{$this->widgetsTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapWidgetRow($row) : null;
    }

    public function saveWidget(array $data, ?int $id = null): int|false
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'scope' => (string) ($data['scope'] ?? 'both'),
            'widget_type' => (string) ($data['widget_type'] ?? 'text'),
            'title' => (string) ($data['title'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => $now,
        ];

        if ($id === null) {
            $payload['created_at'] = $now;
            $insertId = $this->db->insert(self::TABLE_WIDGETS, $payload);
            return is_int($insertId) && $insertId > 0 ? $insertId : false;
        }

        return $this->db->update(self::TABLE_WIDGETS, $payload, ['id' => $id]) ? $id : false;
    }

    /** @param list<int> $ids */
    public function deleteWidgets(int $projectId, array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $stmt = $this->db->execute(
            "DELETE FROM `{$this->widgetsTableFull}` WHERE project_id = ? AND id IN ({$this->placeholders($ids)})",
            array_merge([$projectId], $ids)
        );

        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    public function updateWidgetsFlag(int $projectId, array $ids, string $column, int $value): int
    {
        return $this->updateFlag($this->widgetsTableFull, $projectId, $ids, $column, $value, ['is_active']);
    }

    // ── Tickets ──────────────────────────────────────────────────────────────

    /** Tickets eines Projekts für die Board-Darstellung (ohne Payload). */
    public function getTasksByProject(int $projectId, string $scope = 'admin', bool $includeInactive = true): array
    {
        $sql = 'SELECT ' . self::TASK_BOARD_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE project_id = ?";
        $params = [$projectId];

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        if ($scope === 'public') {
            $sql .= ' AND is_public = 1';
        }

        $sql .= ' ORDER BY board_id ASC, sort_order ASC, id ASC';
        return array_map([$this, 'mapTaskRow'], $this->db->get_results($sql, $params));
    }

    /**
     * @param array{project_id?:int,q?:string,board_id?:int,priority?:string,state?:string,overdue?:bool} $filters
     */
    public function findTasks(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildTaskFilter($filters);
        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        $rows = $this->db->get_results(
            'SELECT ' . self::TASK_LIST_COLUMNS . " FROM `{$this->tasksTableFull}`{$where} ORDER BY board_id ASC, sort_order ASC, id ASC LIMIT ? OFFSET ?",
            $params
        );

        return array_map([$this, 'mapTaskRow'], $rows);
    }

    public function countTasksFiltered(array $filters): int
    {
        [$where, $params] = $this->buildTaskFilter($filters);
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->tasksTableFull}`{$where}", $params) ?? 0);
    }

    /** Für den CSV-Export (inkl. Beschreibung). */
    public function getTasksForExport(int $projectId): array
    {
        $sql = 'SELECT ' . self::TASK_BOARD_COLUMNS . " FROM `{$this->tasksTableFull}`";
        $params = [];
        if ($projectId > 0) {
            $sql .= ' WHERE project_id = ?';
            $params[] = $projectId;
        }
        $sql .= ' ORDER BY project_id ASC, board_id ASC, sort_order ASC, id ASC LIMIT 20000';

        return array_map([$this, 'mapTaskRow'], $this->db->get_results($sql, $params));
    }

    /** Aktive Tickets, die überfällig oder in den nächsten $days Tagen fällig sind. */
    public function getDueTasks(int $days, int $limit): array
    {
        $rows = $this->db->get_results(
            'SELECT ' . self::TASK_LIST_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE is_active = 1 AND due_date IS NOT NULL AND due_date <= ? ORDER BY due_date ASC, id ASC LIMIT ?",
            [date('Y-m-d', strtotime('+' . max(0, $days) . ' days')), max(1, min(100, $limit))]
        );

        return array_map([$this, 'mapTaskRow'], $rows);
    }

    public function countOverdueTasks(): int
    {
        return (int) ($this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->tasksTableFull}` WHERE is_active = 1 AND due_date IS NOT NULL AND due_date < ?",
            [date('Y-m-d')]
        ) ?? 0);
    }

    public function findTaskById(int $id): ?array
    {
        $row = $this->db->get_row('SELECT ' . self::TASK_COLUMNS . " FROM `{$this->tasksTableFull}` WHERE id = ? LIMIT 1", [$id]);
        return $row ? $this->mapTaskRow($row) : null;
    }

    public function saveTask(array $data, ?int $id = null): int|false
    {
        $now = date('Y-m-d H:i:s');
        $dueDate = (string) ($data['due_date'] ?? '');
        $payload = [
            'project_id' => (int) ($data['project_id'] ?? 0),
            'board_id' => (int) ($data['board_id'] ?? 0),
            'column_key' => (string) ($data['column_key'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'priority' => (string) ($data['priority'] ?? 'medium'),
            'assignee_name' => (string) ($data['assignee_name'] ?? ''),
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'payload' => (string) ($data['payload'] ?? ''),
            'updated_at' => $now,
        ];

        if ($id === null) {
            $payload['created_at'] = $now;
            $insertId = $this->db->insert(self::TABLE_TASKS, $payload);
            return is_int($insertId) && $insertId > 0 ? $insertId : false;
        }

        return $this->db->update(self::TABLE_TASKS, $payload, ['id' => $id]) ? $id : false;
    }

    /**
     * Setzt Board, Spalte und Reihenfolge mehrerer Tickets in einer Abfrage.
     *
     * @param list<int> $orderedIds Reihenfolge in der Zielspalte (Position = Index + 1)
     */
    public function updateTaskPositions(int $projectId, array $orderedIds, int $boardId, string $columnKey): bool
    {
        $orderedIds = $this->normalizeIds($orderedIds);
        if ($orderedIds === []) {
            return true;
        }

        $cases = [];
        $params = [];
        foreach ($orderedIds as $index => $taskId) {
            $cases[] = 'WHEN ? THEN ?';
            $params[] = $taskId;
            $params[] = $index + 1;
        }

        $params[] = $boardId;
        $params[] = $columnKey;
        $params[] = date('Y-m-d H:i:s');
        $params[] = $projectId;
        $params = array_merge($params, $orderedIds);

        try {
            $this->db->execute(
                "UPDATE `{$this->tasksTableFull}` SET sort_order = CASE id " . implode(' ', $cases) . ' END, board_id = ?, column_key = ?, updated_at = ?'
                . " WHERE project_id = ? AND id IN ({$this->placeholders($orderedIds)})",
                $params
            );
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Verschiebt Tickets eines Boards, deren Spalte nicht mehr existiert, in die Ersatzspalte.
     *
     * @param list<string> $validKeys
     */
    public function reassignOrphanTasks(int $boardId, array $validKeys, string $fallbackKey): int
    {
        if ($validKeys === [] || $fallbackKey === '') {
            return 0;
        }

        $stmt = $this->db->execute(
            "UPDATE `{$this->tasksTableFull}` SET column_key = ?, updated_at = ? WHERE board_id = ? AND column_key NOT IN ("
            . implode(', ', array_fill(0, count($validKeys), '?')) . ')',
            array_merge([$fallbackKey, date('Y-m-d H:i:s'), $boardId], array_values($validKeys))
        );

        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    public function deleteTasks(int $projectId, array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $stmt = $this->db->execute(
            "DELETE FROM `{$this->tasksTableFull}` WHERE project_id = ? AND id IN ({$this->placeholders($ids)})",
            array_merge([$projectId], $ids)
        );

        return $stmt->rowCount();
    }

    /** @param list<int> $ids */
    public function updateTasksFlag(int $projectId, array $ids, string $column, int $value): int
    {
        return $this->updateFlag($this->tasksTableFull, $projectId, $ids, $column, $value, ['is_active', 'is_public']);
    }

    // ── Kennzahlen ───────────────────────────────────────────────────────────

    public function getProjectEntityCounts(array $projectIds): array
    {
        $projectIds = $this->normalizeIds($projectIds);
        if ($projectIds === []) {
            return [];
        }

        $counts = [];
        foreach ($projectIds as $projectId) {
            $counts[$projectId] = ['board_count' => 0, 'widget_count' => 0, 'task_count' => 0];
        }

        $placeholders = $this->placeholders($projectIds);
        $rows = $this->db->get_results(
            "SELECT 'board_count' AS metric, project_id, COUNT(*) AS aggregate_count FROM `{$this->boardsTableFull}` WHERE project_id IN ({$placeholders}) GROUP BY project_id
             UNION ALL
             SELECT 'widget_count' AS metric, project_id, COUNT(*) AS aggregate_count FROM `{$this->widgetsTableFull}` WHERE project_id IN ({$placeholders}) GROUP BY project_id
             UNION ALL
             SELECT 'task_count' AS metric, project_id, COUNT(*) AS aggregate_count FROM `{$this->tasksTableFull}` WHERE project_id IN ({$placeholders}) GROUP BY project_id",
            array_merge($projectIds, $projectIds, $projectIds)
        );

        foreach ($rows as $row) {
            $projectId = (int) ($row->project_id ?? 0);
            $metric = (string) ($row->metric ?? '');
            if (isset($counts[$projectId][$metric])) {
                $counts[$projectId][$metric] = (int) ($row->aggregate_count ?? 0);
            }
        }

        return $counts;
    }

    public function getSummaryCounts(): array
    {
        $today = date('Y-m-d');
        $row = $this->db->get_row(
            "SELECT
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}`) AS projects,
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE status = ?) AS active_projects,
                (SELECT COUNT(*) FROM `{$this->projectsTableFull}` WHERE visibility = ? AND status = ?) AS public_projects,
                (SELECT COUNT(*) FROM `{$this->boardsTableFull}`) AS boards,
                (SELECT COUNT(*) FROM `{$this->widgetsTableFull}`) AS widgets,
                (SELECT COUNT(*) FROM `{$this->tasksTableFull}`) AS tasks,
                (SELECT COUNT(*) FROM `{$this->tasksTableFull}` WHERE is_active = 1) AS active_tasks,
                (SELECT COUNT(*) FROM `{$this->tasksTableFull}` WHERE is_active = 1 AND due_date IS NOT NULL AND due_date < ?) AS overdue_tasks",
            ['active', 'public', 'active', $today]
        );

        $keys = ['projects', 'active_projects', 'public_projects', 'boards', 'widgets', 'tasks', 'active_tasks', 'overdue_tasks'];
        $summary = [];
        foreach ($keys as $key) {
            $summary[$key] = $row ? (int) ($row->{$key} ?? 0) : 0;
        }

        return $summary;
    }

    // ── Audit-Log ────────────────────────────────────────────────────────────

    public function insertAuditLog(array $payload): int|false
    {
        $insertId = $this->db->insert(self::TABLE_AUDIT, [
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

        return is_int($insertId) && $insertId > 0 ? $insertId : false;
    }

    /**
     * @param array{q?:string,result?:string,entity_type?:string,project_id?:int} $filters
     */
    public function findAuditLogs(array $filters, int $limit, int $offset = 0): array
    {
        [$where, $params] = $this->buildAuditFilter($filters);
        $params[] = max(1, min(20000, $limit));
        $params[] = max(0, $offset);

        $rows = $this->db->get_results(
            'SELECT ' . self::AUDIT_COLUMNS . " FROM `{$this->auditLogTableFull}`{$where} ORDER BY id DESC LIMIT ? OFFSET ?",
            $params
        );

        return array_map([$this, 'mapAuditLogRow'], $rows);
    }

    public function countAuditLogs(array $filters = []): int
    {
        [$where, $params] = $this->buildAuditFilter($filters);
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->auditLogTableFull}`{$where}", $params) ?? 0);
    }

    public function getAuditLogsForActor(int $actorId, int $limit = 5000): array
    {
        if ($actorId <= 0) {
            return [];
        }

        $rows = $this->db->get_results(
            'SELECT ' . self::AUDIT_COLUMNS . " FROM `{$this->auditLogTableFull}` WHERE actor_id = ? ORDER BY id DESC LIMIT ?",
            [$actorId, max(1, min(20000, $limit))]
        );

        return array_map([$this, 'mapAuditLogRow'], $rows);
    }

    public function anonymizeAuditActor(int $actorId): int
    {
        if ($actorId <= 0) {
            return 0;
        }

        $stmt = $this->db->execute(
            "UPDATE `{$this->auditLogTableFull}` SET actor_id = 0, actor_name = ? WHERE actor_id = ?",
            ['anonymisiert', $actorId]
        );

        return $stmt->rowCount();
    }

    public function countAuditLogsBefore(string $cutoff): int
    {
        return (int) ($this->db->get_var("SELECT COUNT(*) FROM `{$this->auditLogTableFull}` WHERE created_at < ?", [$cutoff]) ?? 0);
    }

    public function deleteAuditLogsBefore(string $cutoff, int $batchSize = 1000): int
    {
        $stmt = $this->db->execute(
            "DELETE FROM `{$this->auditLogTableFull}` WHERE created_at < ? ORDER BY id ASC LIMIT ?",
            [$cutoff, max(1, min(5000, $batchSize))]
        );

        return $stmt->rowCount();
    }

    /** @return list<string> */
    public function getAuditEntityTypes(): array
    {
        $rows = $this->db->get_col("SELECT DISTINCT entity_type FROM `{$this->auditLogTableFull}` ORDER BY entity_type ASC LIMIT 50");
        return array_values(array_map('strval', $rows));
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    /** @return array{0:string,1:list<mixed>} */
    private function buildProjectFilter(array $filters): array
    {
        $clauses = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $this->escapeLike($q) . '%';
            $clauses[] = '(name LIKE ? OR slug LIKE ? OR owner_name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        foreach (['status', 'visibility'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value !== '') {
                $clauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        return [$clauses !== [] ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function buildTaskFilter(array $filters): array
    {
        $clauses = [];
        $params = [];

        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $clauses[] = 'project_id = ?';
            $params[] = $projectId;
        }

        $boardId = (int) ($filters['board_id'] ?? 0);
        if ($boardId > 0) {
            $clauses[] = 'board_id = ?';
            $params[] = $boardId;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $this->escapeLike($q) . '%';
            $clauses[] = '(title LIKE ? OR assignee_name LIKE ?)';
            array_push($params, $like, $like);
        }

        $priority = trim((string) ($filters['priority'] ?? ''));
        if ($priority !== '') {
            $clauses[] = 'priority = ?';
            $params[] = $priority;
        }

        $state = (string) ($filters['state'] ?? '');
        if ($state === 'active') {
            $clauses[] = 'is_active = 1';
        } elseif ($state === 'inactive') {
            $clauses[] = 'is_active = 0';
        }

        if (!empty($filters['overdue'])) {
            $clauses[] = 'is_active = 1 AND due_date IS NOT NULL AND due_date < ?';
            $params[] = date('Y-m-d');
        }

        return [$clauses !== [] ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function buildAuditFilter(array $filters): array
    {
        $clauses = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $this->escapeLike($q) . '%';
            $clauses[] = '(action LIKE ? OR actor_name LIKE ?)';
            array_push($params, $like, $like);
        }

        foreach (['result', 'entity_type'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value !== '') {
                $clauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $clauses[] = 'project_id = ?';
            $params[] = $projectId;
        }

        return [$clauses !== [] ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /**
     * @param list<int>    $ids
     * @param list<string> $allowedColumns Whitelist der änderbaren Flag-Spalten
     */
    private function updateFlag(string $table, int $projectId, array $ids, string $column, int $value, array $allowedColumns): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === [] || !in_array($column, $allowedColumns, true)) {
            return 0;
        }

        $stmt = $this->db->execute(
            "UPDATE `{$table}` SET `{$column}` = ?, updated_at = ? WHERE project_id = ? AND id IN ({$this->placeholders($ids)})",
            array_merge([$value === 1 ? 1 : 0, date('Y-m-d H:i:s'), $projectId], $ids)
        );

        return $stmt->rowCount();
    }

    /** @return list<int> */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, $ids),
            static fn (int $value): bool => $value > 0
        )));
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
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

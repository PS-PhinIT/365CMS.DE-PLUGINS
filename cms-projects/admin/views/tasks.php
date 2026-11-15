<?php
/**
 * Tickets: Filter, Liste mit Sammelaktionen und Pagination, Formular, CSV-Export.
 *
 * @var callable $e
 * @var array $projectOptions
 * @var array|null $project
 * @var int $projectId
 * @var bool $allProjects
 * @var array $taskPriorities
 * @var array $boardOptions
 * @var array $boardTitles
 * @var array $filters
 * @var array $pageData
 * @var array $projectNames
 * @var array $taskFormValues
 * @var int $editTaskId
 * @var bool $showForm
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$headerActions = [];
if ($project !== null) {
    $headerActions[] = ['label' => 'Neues Ticket', 'url' => $this->url(self::PAGE_TASKS, ['project_id' => $projectId, 'action' => 'new']) . '#cp-task-form', 'icon' => 'ti ti-plus', 'primary' => true];
    $headerActions[] = ['label' => 'Board-Ansicht', 'url' => $this->url(self::PAGE_BOARDS, ['project_id' => $projectId]) . '#cp-board-view', 'icon' => 'ti ti-layout-kanban'];
}
echo $this->pageHeader('Tickets', 'Aufgaben je Board-Spalte filtern, bearbeiten, gesammelt ändern und als CSV exportieren.', $headerActions);

if ($projectOptions === []): ?>
    <section class="admin-card cp-admin-section">
        <p class="cms-plugin-empty">Noch keine Projekte vorhanden. <a href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['action' => 'new'])); ?>">Erstes Projekt anlegen</a></p>
    </section>
    <?php return; ?>
<?php endif;

$priorityField = '<label class="cp-toolbar__field"><span class="form-label">Priorität</span><select class="form-select" name="priority"><option value="">Alle</option>';
foreach ($taskPriorities as $key => $label) {
    $priorityField .= '<option value="' . $e($key) . '"' . ($filters['priority'] === $key ? ' selected' : '') . '>' . $e($label) . '</option>';
}
$priorityField .= '</select></label>';

$stateField = '<label class="cp-toolbar__field"><span class="form-label">Status</span><select class="form-select" name="state">'
    . '<option value="">Alle</option>'
    . '<option value="active"' . ($filters['state'] === 'active' ? ' selected' : '') . '>Aktiv</option>'
    . '<option value="inactive"' . ($filters['state'] === 'inactive' ? ' selected' : '') . '>Inaktiv</option>'
    . '</select></label>';

$boardField = '';
if ($project !== null && $boardTitles !== []) {
    $boardField = '<label class="cp-toolbar__field"><span class="form-label">Board</span><select class="form-select" name="board_id"><option value="0">Alle Boards</option>';
    foreach ($boardTitles as $boardId => $boardTitle) {
        $boardField .= '<option value="' . (int) $boardId . '"' . ((int) $filters['board_id'] === (int) $boardId ? ' selected' : '') . '>' . $e($boardTitle) . '</option>';
    }
    $boardField .= '</select></label>';
}

echo $this->projectSwitcher(self::PAGE_TASKS, $projectOptions, $projectId, true, [
    '<label class="cp-toolbar__field cp-toolbar__field--grow"><span class="form-label">Suche</span><input type="search" class="form-control" name="q" maxlength="100" value="' . $e($filters['q']) . '" placeholder="Titel oder Zuständige Person"></label>',
    $boardField,
    $priorityField,
    $stateField,
    '<label class="form-check cp-toolbar__check"><input type="checkbox" class="form-check-input" name="overdue" value="1"' . ($filters['overdue'] ? ' checked' : '') . '><span class="form-check-label">nur überfällige</span></label>',
]);

$listQuery = array_filter([
    'project_id' => $allProjects ? '0' : (string) $projectId,
    'q' => $filters['q'],
    'board_id' => $filters['board_id'] > 0 ? (string) $filters['board_id'] : '',
    'priority' => $filters['priority'],
    'state' => $filters['state'],
    'overdue' => $filters['overdue'] ? '1' : '',
], static fn (string $value): bool => $value !== '');
?>

<?php if ($showForm): ?>
    <section class="admin-card cp-admin-section" id="cp-task-form">
        <div class="cp-panel-header">
            <h3><?php echo $editTaskId > 0 ? 'Ticket bearbeiten' : 'Neues Ticket'; ?></h3>
            <p>Projekt: <?php echo $e($project['name'] ?? ''); ?></p>
        </div>
        <?php if ($boardOptions === []): ?>
            <p class="cms-plugin-empty">Für Tickets wird ein Board mit mindestens einer Spalte benötigt. <a href="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId]) . '#cp-board-form'); ?>">Board anlegen</a></p>
        <?php else: ?>
            <form method="post" action="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => $projectId])); ?>" class="cp-form" data-cp-task-form>
                <?php echo $this->hiddenFields('save_task', $projectId); ?>
                <input type="hidden" name="task_id" value="<?php echo $editTaskId; ?>">
                <label><span class="form-label">Titel</span><input type="text" class="form-control" name="title" maxlength="190" required placeholder="z. B. API-Fehler bei Login beheben" value="<?php echo $e($taskFormValues['title']); ?>"></label>
                <div class="cp-form-grid-2">
                    <label>
                        <span class="form-label">Board</span>
                        <select class="form-select" name="board_id" required data-cp-board-select>
                            <option value="">Board wählen</option>
                            <?php foreach ($boardOptions as $boardOption): ?>
                                <option value="<?php echo (int) $boardOption['id']; ?>"<?php echo (int) $taskFormValues['board_id'] === (int) $boardOption['id'] || ((int) $taskFormValues['board_id'] === 0 && (int) $filters['board_id'] === (int) $boardOption['id']) ? ' selected' : ''; ?>><?php echo $e($boardOption['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="form-label">Spalte</span>
                        <select class="form-select" name="column_key" required data-cp-column-select>
                            <option value="">Spalte wählen</option>
                            <?php foreach ($boardOptions as $boardOption): ?>
                                <optgroup label="<?php echo $e($boardOption['title']); ?>" data-board-id="<?php echo (int) $boardOption['id']; ?>">
                                    <?php foreach ($boardOption['columns'] as $column): ?>
                                        <option value="<?php echo $e($column['key']); ?>"<?php echo (int) $taskFormValues['board_id'] === (int) $boardOption['id'] && (string) $taskFormValues['column_key'] === (string) $column['key'] ? ' selected' : ''; ?>><?php echo $e($column['title']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="cp-form-grid-2">
                    <label>
                        <span class="form-label">Priorität</span>
                        <select class="form-select" name="priority">
                            <?php foreach ($taskPriorities as $key => $label): ?>
                                <option value="<?php echo $e($key); ?>"<?php echo (string) $taskFormValues['priority'] === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span class="form-label">Sortierung</span><input type="number" class="form-control" name="sort_order" min="0" max="99999" value="<?php echo (int) $taskFormValues['sort_order']; ?>"></label>
                </div>
                <div class="cp-form-grid-2">
                    <label><span class="form-label">Zuständig</span><input type="text" class="form-control" name="assignee_name" maxlength="190" value="<?php echo $e($taskFormValues['assignee_name']); ?>"></label>
                    <label><span class="form-label">Fällig am</span><input type="date" class="form-control" name="due_date" value="<?php echo $e($taskFormValues['due_date']); ?>"></label>
                </div>
                <label><span class="form-label">Beschreibung</span><textarea class="form-control" name="description" rows="5" maxlength="20000" placeholder="Was ist zu tun, welche Akzeptanzkriterien gelten, gibt es Blocker?"><?php echo $e($taskFormValues['description']); ?></textarea></label>
                <div class="cp-form-checks">
                    <label class="form-check"><input type="checkbox" class="form-check-input" name="is_public" value="1"<?php echo !empty($taskFormValues['is_public']) ? ' checked' : ''; ?>><span class="form-check-label">Auch auf der öffentlichen Projektseite zeigen</span></label>
                    <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1"<?php echo !empty($taskFormValues['is_active']) ? ' checked' : ''; ?>><span class="form-check-label">Ticket aktiv</span></label>
                </div>
                <div class="cp-form-actions">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> <?php echo $editTaskId > 0 ? 'Ticket speichern' : 'Ticket anlegen'; ?></button>
                    <a class="btn btn-link" href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => $projectId])); ?>">Abbrechen</a>
                </div>
            </form>
            <?php if ($editTaskId > 0): ?>
                <form method="post" action="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => $projectId])); ?>" class="cp-inline-form" data-cms-confirm="Ticket wirklich löschen?">
                    <?php echo $this->hiddenFields('delete_task', $projectId); ?>
                    <input type="hidden" name="task_id" value="<?php echo $editTaskId; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ti ti-trash" aria-hidden="true"></i> Ticket löschen</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="admin-card cp-admin-section">
    <div class="cp-panel-header cp-panel-header--split">
        <h3><?php echo $allProjects ? 'Tickets aller Projekte' : 'Tickets von „' . $e($project['name'] ?? '') . '“'; ?></h3>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_TASKS, $listQuery)); ?>">
            <?php echo $this->hiddenFields('export_tasks', $projectId); ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV exportieren</button>
        </form>
    </div>
    <?php if ($pageData['items'] === []): ?>
        <p class="cms-plugin-empty">Keine Tickets für diese Auswahl.</p>
    <?php else: ?>
        <?php
        $bulkProjectId = $allProjects ? 0 : $projectId;
        ?>
        <?php if ($bulkProjectId > 0): ?>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_TASKS, $listQuery)); ?>" data-cms-confirm="Sammelaktion auf die ausgewählten Tickets anwenden?">
            <?php echo $this->hiddenFields('bulk_tasks', $projectId); ?>
            <?php foreach (['q', 'board_id', 'priority', 'state', 'overdue'] as $key): ?>
                <input type="hidden" name="return_<?php echo $e($key); ?>" value="<?php echo $e($listQuery[$key] ?? ''); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="return_paged" value="<?php echo (int) $pageData['page']; ?>">
            <div class="cp-bulkbar">
                <label class="cp-toolbar__field">
                    <span class="visually-hidden">Sammelaktion</span>
                    <select class="form-select" name="bulk_operation" required>
                        <option value="">Sammelaktion wählen</option>
                        <option value="activate">Aktivieren</option>
                        <option value="deactivate">Deaktivieren</option>
                        <option value="public">Öffentlich zeigen</option>
                        <option value="internal">Intern</option>
                        <option value="delete">Löschen</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-outline-secondary">Anwenden</button>
            </div>
        <?php else: ?>
            <p class="cp-field-hint">Sammelaktionen stehen zur Verfügung, sobald ein einzelnes Projekt gewählt ist.</p>
        <?php endif; ?>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <?php if ($bulkProjectId > 0): ?><th class="cp-col-check"><input type="checkbox" class="form-check-input" data-cp-check-all aria-label="Alle auswählen"></th><?php endif; ?>
                            <th>Titel</th>
                            <th><?php echo $allProjects ? 'Projekt' : 'Board / Spalte'; ?></th>
                            <th>Priorität</th><th>Zuständig</th><th>Fällig</th><th>Sichtbarkeit</th><th>Status</th><th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageData['items'] as $task): ?>
                            <tr>
                                <?php if ($bulkProjectId > 0): ?><td class="cp-col-check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo (int) $task['id']; ?>" aria-label="Ticket auswählen"></td><?php endif; ?>
                                <td><a href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => (int) $task['project_id'], 'task_id' => (int) $task['id']]) . '#cp-task-form'); ?>"><strong><?php echo $e($task['title']); ?></strong></a></td>
                                <td>
                                    <?php if ($allProjects): ?>
                                        <?php echo $e($projectNames[(int) $task['project_id']] ?? ('#' . (int) $task['project_id'])); ?>
                                    <?php else: ?>
                                        <?php echo $e($boardTitles[(int) $task['board_id']] ?? ('#' . (int) $task['board_id'])); ?> <span class="cp-muted">/ <?php echo $e($task['column_key']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="cp-badge cp-badge--priority-<?php echo $e($task['priority']); ?>"><?php echo $e($task['priority_label']); ?></span></td>
                                <td><?php echo $e($task['assignee_name'] !== '' ? $task['assignee_name'] : '–'); ?></td>
                                <td>
                                    <span class="<?php echo !empty($task['is_overdue']) ? 'cp-text-danger' : ''; ?>"><?php echo $e($this->formatDate((string) $task['due_date'])); ?></span>
                                </td>
                                <td><?php echo !empty($task['is_public']) ? 'Öffentlich' : 'Intern'; ?></td>
                                <td><span class="cp-badge cp-badge--<?php echo !empty($task['is_active']) ? 'success' : 'muted'; ?>"><?php echo !empty($task['is_active']) ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                                <td class="cp-row-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => (int) $task['project_id'], 'task_id' => (int) $task['id']]) . '#cp-task-form'); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php if ($bulkProjectId > 0): ?>
        </form>
        <?php endif; ?>
        <?php echo $this->pagination($pageData, self::PAGE_TASKS, $listQuery); ?>
    <?php endif; ?>
</section>

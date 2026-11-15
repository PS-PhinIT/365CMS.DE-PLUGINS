<?php
/**
 * Tickets eines Projekts: Board-Ansicht mit Drag & Drop, Ticketliste, Formular.
 *
 * @var CMS_Projects_Service $service
 * @var array<string,mixed> $project
 * @var int $projectId
 * @var string $csrfToken
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$projectBoards = $service->getProjectBoards($projectId, 'admin');
require __DIR__ . '/helpers.php';

$projectTasks = $service->getProjectTasks($projectId, 'admin');
$taskPriorities = $service->getTaskPriorities();
$taskFormValues = $service->getTaskDefaults();
$selectedTaskId = max(0, (int) ($_GET['task_id'] ?? 0));
if ($selectedTaskId > 0) {
    $selectedTask = $service->findTask($selectedTaskId);
    if ($selectedTask !== null && (int) ($selectedTask['project_id'] ?? 0) === $projectId) {
        $taskFormValues = array_merge($taskFormValues, $selectedTask);
    }
}
$activeTaskId = (int) ($taskFormValues['id'] ?? 0);
$today = date('Y-m-d');
?>
<form method="post" hidden data-cp-task-move-form>
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="cms_projects_action" value="move_task">
    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
    <input type="hidden" name="task_id" value="0">
    <input type="hidden" name="target_board_id" value="0">
    <input type="hidden" name="target_column_key" value="">
    <input type="hidden" name="ordered_task_ids" value="">
</form>

<?php if ($projectBoards === []): ?>
    <div class="alert alert-info" role="status">Für Tickets wird mindestens ein Board benötigt. <a href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'boards')); ?>">Board anlegen</a></div>
<?php else: ?>
    <section class="admin-card">
        <div class="cp-panel-header"><h3>Board-Ansicht</h3><span class="cp-muted">Tickets per Drag &amp; Drop zwischen Spalten verschieben</span></div>
        <div class="cp-stack-list">
            <?php foreach ($projectBoards as $board): ?>
                <article class="cp-item-card">
                    <div class="cp-item-card__head"><strong><?php echo $e($board['title']); ?></strong><span class="cp-badge"><?php echo (int) ($board['task_count'] ?? 0); ?> Tickets</span></div>
                    <?php echo $renderBoardPreview($board); ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="cp-split">
    <section class="admin-card">
        <div class="cp-panel-header"><h3>Tickets (<?php echo count($projectTasks); ?>)</h3></div>
        <?php if ($projectTasks === []): ?>
            <div class="empty-state"><p><strong>Noch keine Tickets</strong></p></div>
        <?php else: ?>
            <div class="users-table-container">
                <table class="users-table">
                    <thead><tr><th scope="col">Ticket</th><th scope="col">Spalte</th><th scope="col">Priorität</th><th scope="col">Fällig</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($projectTasks as $task): ?>
                        <?php $due = (string) ($task['due_date'] ?? ''); $overdue = $due !== '' && $due < $today && !empty($task['is_active']); ?>
                        <tr<?php echo (int) $task['id'] === $activeTaskId ? ' class="cp-row--active"' : ''; ?>>
                            <td>
                                <a class="cp-admin-link" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'tasks', ['task_id' => (int) $task['id']])); ?>"><?php echo $e($task['title']); ?></a>
                                <div class="cp-muted"><?php echo $e(($task['assignee_name'] ?? '') !== '' ? $task['assignee_name'] : 'nicht zugewiesen'); ?> · <?php echo !empty($task['is_public']) ? 'Public' : 'Intern'; ?><?php echo empty($task['is_active']) ? ' · inaktiv' : ''; ?></div>
                            </td>
                            <td><span class="cp-badge"><?php echo $e($task['column_key']); ?></span></td>
                            <td><?php echo $e($task['priority_label'] ?? ''); ?></td>
                            <td><?php if ($due !== ''): ?><span class="<?php echo $overdue ? 'cp-badge cp-badge--overdue' : ''; ?>"><?php echo $e(date('d.m.Y', (int) strtotime($due))); ?></span><?php else: ?>–<?php endif; ?></td>
                            <td>
                                <div class="cp-action-group">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'tasks', ['task_id' => (int) $task['id']])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Ticket bearbeiten</span></a>
                                    <form method="post" data-cms-confirm="Ticket „<?php echo $e($task['title']); ?>“ löschen?">
                                        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                        <input type="hidden" name="cms_projects_action" value="delete_task">
                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                        <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Ticket löschen</span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card" id="cp-task-form">
        <div class="cp-panel-header"><h3><?php echo $activeTaskId > 0 ? 'Ticket bearbeiten' : 'Ticket anlegen'; ?></h3></div>
        <?php if ($boardColumnOptions === []): ?>
            <p class="cp-muted">Lege zuerst ein Board mit mindestens einer Spalte an.</p>
        <?php else: ?>
        <form method="post" class="admin-form cp-form">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="cms_projects_action" value="save_task">
            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
            <input type="hidden" name="task_id" value="<?php echo $activeTaskId; ?>">
            <div class="form-group"><label class="form-label required" for="cp-task-title">Titel</label><input id="cp-task-title" class="form-control" type="text" name="title" maxlength="190" required value="<?php echo $e($taskFormValues['title'] ?? ''); ?>"></div>
            <div class="cp-form-grid">
                <div class="form-group">
                    <label class="form-label required" for="cp-task-board">Board</label>
                    <select id="cp-task-board" class="form-select" name="board_id" required>
                        <option value="">Board wählen</option>
                        <?php foreach ($boardColumnOptions as $option): ?>
                            <option value="<?php echo (int) $option['id']; ?>" <?php echo ((int) ($taskFormValues['board_id'] ?? 0) === (int) $option['id']) ? 'selected' : ''; ?>><?php echo $e($option['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required" for="cp-task-column">Spalte</label>
                    <select id="cp-task-column" class="form-select" name="column_key" required>
                        <option value="">Spalte wählen</option>
                        <?php foreach ($boardColumnOptions as $option): ?>
                            <optgroup label="<?php echo $e($option['title']); ?>">
                                <?php foreach ($option['columns'] as $column): ?>
                                    <option value="<?php echo $e($column['key']); ?>" <?php echo ((string) ($taskFormValues['column_key'] ?? '') === (string) $column['key']) ? 'selected' : ''; ?>><?php echo $e($column['title']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cp-task-priority">Priorität</label>
                    <select id="cp-task-priority" class="form-select" name="priority">
                        <?php foreach ($taskPriorities as $key => $label): ?>
                            <option value="<?php echo $e($key); ?>" <?php echo ((string) ($taskFormValues['priority'] ?? 'medium') === (string) $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="cp-task-sort">Sortierung</label><input id="cp-task-sort" class="form-control" type="number" name="sort_order" min="0" max="99999" value="<?php echo (int) ($taskFormValues['sort_order'] ?? 0); ?>"></div>
                <div class="form-group"><label class="form-label" for="cp-task-assignee">Zuständig</label><input id="cp-task-assignee" class="form-control" type="text" name="assignee_name" maxlength="190" value="<?php echo $e($taskFormValues['assignee_name'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="cp-task-due">Fällig am</label><input id="cp-task-due" class="form-control" type="date" name="due_date" value="<?php echo $e($taskFormValues['due_date'] ?? ''); ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="cp-task-description">Beschreibung</label><textarea id="cp-task-description" class="form-control" name="description" rows="5" maxlength="20000" placeholder="Was ist zu tun, Akzeptanzkriterien, Blocker?"><?php echo $e($taskFormValues['description'] ?? ''); ?></textarea></div>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" <?php echo !empty($taskFormValues['is_public']) ? 'checked' : ''; ?>> <span class="form-check-label">Auch im Public-Board zeigen</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo !array_key_exists('is_active', $taskFormValues) || !empty($taskFormValues['is_active']) ? 'checked' : ''; ?>> <span class="form-check-label">Ticket aktiv</span></label>
            <div class="cp-form-actions">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> <?php echo $activeTaskId > 0 ? 'Ticket aktualisieren' : 'Ticket speichern'; ?></button>
                <?php if ($activeTaskId > 0): ?><a class="btn btn-link" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'tasks')); ?>">Neues Ticket</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </section>
</div>

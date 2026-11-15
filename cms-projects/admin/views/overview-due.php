<?php
/**
 * Übersicht: fällige und überfällige Tickets (nach cms_plugin_dashboard()).
 *
 * @var callable $e
 * @var list<array<string,mixed>> $dueTasks
 * @var array<int,string> $projectNames
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="admin-card cp-admin-section">
    <div class="cp-panel-header">
        <h3>Fällige Tickets (überfällig und nächste 14 Tage)</h3>
    </div>
    <?php if ($dueTasks === []): ?>
        <p class="cms-plugin-empty">Keine aktiven Tickets mit Fälligkeit in den nächsten 14 Tagen.</p>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr><th>Ticket</th><th>Projekt</th><th>Priorität</th><th>Zuständig</th><th>Fällig am</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($dueTasks as $task): ?>
                        <tr>
                            <td><a href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => (int) $task['project_id'], 'task_id' => (int) $task['id']])); ?>"><?php echo $e($task['title']); ?></a></td>
                            <td><?php echo $e($projectNames[(int) $task['project_id']] ?? ('#' . (int) $task['project_id'])); ?></td>
                            <td><?php echo $e($task['priority_label']); ?></td>
                            <td><?php echo $e($task['assignee_name'] !== '' ? $task['assignee_name'] : '–'); ?></td>
                            <td>
                                <span class="<?php echo !empty($task['is_overdue']) ? 'cp-text-danger' : ''; ?>"><?php echo $e($this->formatDate((string) $task['due_date'])); ?></span>
                                <?php if (!empty($task['is_overdue'])): ?><span class="cp-badge cp-badge--danger">überfällig</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
/**
 * Boards eines Projekts: Liste mit Sammelaktionen, Formular und Board-Ansicht mit Drag-and-drop.
 *
 * @var callable $e
 * @var array $projectOptions
 * @var array|null $project
 * @var int $projectId
 * @var array $boardTypes
 * @var array $boards
 * @var array $boardFormValues
 * @var int $editBoardId
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo $this->pageHeader('Boards', 'Kanban-, Flow-, Roadmap- und Ideenboards je Projekt; Tickets per Drag-and-drop verschieben.', $project !== null ? [
    ['label' => 'Neues Board', 'url' => $this->url(self::PAGE_BOARDS, ['project_id' => $projectId]) . '#cp-board-form', 'icon' => 'ti ti-plus', 'primary' => true],
    ['label' => 'Ticket anlegen', 'url' => $this->url(self::PAGE_TASKS, ['project_id' => $projectId, 'action' => 'new']), 'icon' => 'ti ti-square-plus'],
] : []);

if ($projectOptions === []): ?>
    <section class="admin-card cp-admin-section">
        <p class="cms-plugin-empty">Noch keine Projekte vorhanden. <a href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['action' => 'new'])); ?>">Erstes Projekt anlegen</a></p>
    </section>
    <?php return; ?>
<?php endif;

echo $this->projectSwitcher(self::PAGE_BOARDS, $projectOptions, $projectId);

if ($project === null) {
    return;
}
?>
<section class="admin-card cp-admin-section">
    <div class="cp-panel-header"><h3>Boards von „<?php echo $e($project['name']); ?>“</h3></div>
    <?php if ($boards === []): ?>
        <p class="cms-plugin-empty">Für dieses Projekt gibt es noch keine Boards.</p>
    <?php else: ?>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId])); ?>" data-cms-confirm="Sammelaktion auf die ausgewählten Boards anwenden? Beim Löschen werden auch deren Tickets entfernt.">
            <?php echo $this->hiddenFields('bulk_boards', $projectId); ?>
            <div class="cp-bulkbar">
                <label class="cp-toolbar__field">
                    <span class="visually-hidden">Sammelaktion</span>
                    <select class="form-select" name="bulk_operation" required>
                        <option value="">Sammelaktion wählen</option>
                        <option value="activate">Aktivieren</option>
                        <option value="deactivate">Deaktivieren</option>
                        <option value="public">Öffentlich zeigen</option>
                        <option value="internal">Nur Mitglieder</option>
                        <option value="delete">Löschen (inkl. Tickets)</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-outline-secondary">Anwenden</button>
            </div>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th class="cp-col-check"><input type="checkbox" class="form-check-input" data-cp-check-all aria-label="Alle auswählen"></th>
                            <th>Titel</th><th>Typ</th><th>Spalten</th><th>Tickets</th><th>Position</th><th>Sichtbarkeit</th><th>Status</th><th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boards as $board): ?>
                            <tr>
                                <td class="cp-col-check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo (int) $board['id']; ?>" aria-label="Board auswählen"></td>
                                <td><strong><?php echo $e($board['title']); ?></strong></td>
                                <td><?php echo $e($board['board_label']); ?></td>
                                <td><?php echo count(CMS_Projects_View::boardGroups($board)); ?></td>
                                <td><?php echo (int) $board['task_count']; ?></td>
                                <td><?php echo (int) $board['position']; ?></td>
                                <td><?php echo !empty($board['is_public']) ? 'Öffentlich + Mitglieder' : 'Nur Mitglieder'; ?></td>
                                <td><span class="cp-badge cp-badge--<?php echo !empty($board['is_active']) ? 'success' : 'muted'; ?>"><?php echo !empty($board['is_active']) ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                                <td class="cp-row-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId, 'board_id' => (int) $board['id']]) . '#cp-board-form'); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => $projectId, 'board_id' => (int) $board['id']])); ?>" title="Tickets"><i class="ti ti-checklist" aria-hidden="true"></i><span class="visually-hidden">Tickets</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="admin-card cp-admin-section" id="cp-board-form">
    <div class="cp-panel-header">
        <h3><?php echo $editBoardId > 0 ? 'Board bearbeiten' : 'Neues Board'; ?></h3>
        <p>Spalten werden als JSON gepflegt. Werden Spalten entfernt, wandern deren Tickets in die erste Spalte.</p>
    </div>
    <form method="post" action="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId])); ?>" class="cp-form">
        <?php echo $this->hiddenFields('save_board', $projectId); ?>
        <input type="hidden" name="board_id" value="<?php echo $editBoardId; ?>">
        <div class="cp-form-grid-2">
            <label><span class="form-label">Titel</span><input type="text" class="form-control" name="title" maxlength="190" required placeholder="z. B. Delivery Board" value="<?php echo $e($boardFormValues['title'] ?? ''); ?>"></label>
            <label>
                <span class="form-label">Board-Typ</span>
                <select class="form-select" name="board_type">
                    <?php foreach ($boardTypes as $key => $config): ?>
                        <option value="<?php echo $e($key); ?>"<?php echo ($boardFormValues['board_type'] ?? 'kanban') === $key ? ' selected' : ''; ?>><?php echo $e($config['label']); ?> – <?php echo $e($config['description']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="cp-form-grid-2">
            <label><span class="form-label">Position</span><input type="number" class="form-control" name="position" min="0" max="9999" value="<?php echo (int) ($boardFormValues['position'] ?? 0); ?>"></label>
            <div class="cp-form-checks">
                <label class="form-check"><input type="checkbox" class="form-check-input" name="is_public" value="1"<?php echo !empty($boardFormValues['is_public']) ? ' checked' : ''; ?>><span class="form-check-label">Auch auf der öffentlichen Projektseite zeigen</span></label>
                <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1"<?php echo !empty($boardFormValues['is_active']) ? ' checked' : ''; ?>><span class="form-check-label">Board aktiv</span></label>
            </div>
        </div>
        <label><span class="form-label">Beschreibung</span><textarea class="form-control" name="description" rows="2" maxlength="1200"><?php echo $e($boardFormValues['description'] ?? ''); ?></textarea></label>
        <label>
            <span class="form-label">Spalten (JSON)</span>
            <textarea class="form-control cp-code-input" name="payload" rows="12" spellcheck="false" autocomplete="off"><?php echo $e($boardFormValues['payload_json'] ?? ''); ?></textarea>
            <small class="cp-field-hint">Schlüssel <code>columns</code>, <code>lanes</code>, <code>clusters</code>, <code>stages</code> oder <code>milestones</code>; je Spalte <code>title</code>, optional <code>key</code>, <code>wip_limit</code> und statische <code>items</code>. Leer lassen für die Vorlage des Board-Typs.</small>
        </label>
        <div class="cp-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> <?php echo $editBoardId > 0 ? 'Board speichern' : 'Board anlegen'; ?></button>
            <?php if ($editBoardId > 0): ?>
                <a class="btn btn-link" href="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId]) . '#cp-board-form'); ?>">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($boards !== []): ?>
    <section class="admin-card cp-admin-section" id="cp-board-view">
        <div class="cp-panel-header">
            <h3>Board-Ansicht</h3>
            <p>Tickets zwischen Spalten und Boards ziehen; die Reihenfolge wird gespeichert. Alternativ Board und Spalte im Ticketformular ändern.</p>
        </div>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $projectId])); ?>" hidden data-cp-task-move-form>
            <?php echo $this->hiddenFields('move_task', $projectId); ?>
            <input type="hidden" name="task_id" value="0">
            <input type="hidden" name="target_board_id" value="0">
            <input type="hidden" name="target_column_key" value="">
            <input type="hidden" name="ordered_task_ids" value="">
        </form>
        <div class="cp-board-stack cp-board-stack--admin">
            <?php foreach ($boards as $board): ?>
                <?php echo CMS_Projects_View::renderBoard($board, ['draggable' => true, 'show_description' => true]); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

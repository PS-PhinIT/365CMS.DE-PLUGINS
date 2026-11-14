<?php
/**
 * Boards eines Projekts: Liste mit Vorschau, Bearbeiten/Löschen, Formular.
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

$boardTypes = $service->getBoardTypes();
$editBoard = null;
$editId = max(0, (int) ($_GET['board_id'] ?? 0));
if ($editId > 0) {
    $candidate = $service->findBoard($editId);
    $editBoard = $candidate !== null && (int) $candidate['project_id'] === $projectId ? $candidate : null;
}
$payloadJson = $editBoard !== null
    ? (string) json_encode($editBoard['payload_data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : "{\n  \"columns\": [\n    { \"title\": \"Backlog\", \"wip_limit\": 5, \"items\": [] },\n    { \"title\": \"In Arbeit\", \"wip_limit\": 3, \"items\": [] },\n    { \"title\": \"Erledigt\", \"items\": [] }\n  ]\n}";
?>
<div class="cp-split">
    <section class="admin-card">
        <div class="cp-panel-header"><h3>Boards (<?php echo count($projectBoards); ?>)</h3></div>
        <?php if ($projectBoards === []): ?>
            <div class="empty-state"><p><strong>Noch keine Boards</strong></p><p class="cp-muted">Ein Board strukturiert Tickets in Spalten, Lanes, Stages oder Meilensteinen.</p></div>
        <?php else: ?>
            <div class="cp-stack-list">
                <?php foreach ($projectBoards as $board): ?>
                    <article class="cp-item-card">
                        <div class="cp-item-card__head">
                            <div>
                                <strong><?php echo $e($board['title']); ?></strong>
                                <span class="cp-badge"><?php echo $e($board['board_label'] ?? ''); ?></span>
                                <span class="cp-badge"><?php echo !empty($board['is_public']) ? 'Public' : 'Member'; ?></span>
                                <?php if (empty($board['is_active'])): ?><span class="cp-badge cp-badge--archived">inaktiv</span><?php endif; ?>
                                <span class="cp-badge"><?php echo (int) ($board['task_count'] ?? 0); ?> Tickets</span>
                            </div>
                            <div class="cp-action-group">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'boards', ['board_id' => (int) $board['id']])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Board bearbeiten</span></a>
                                <form method="post" data-cms-confirm="Board „<?php echo $e($board['title']); ?>“ inkl. <?php echo (int) ($board['task_count'] ?? 0); ?> Ticket(s) löschen?">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="cms_projects_action" value="delete_board">
                                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                    <input type="hidden" name="board_id" value="<?php echo (int) $board['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Board löschen</span></button>
                                </form>
                            </div>
                        </div>
                        <?php if (($board['description'] ?? '') !== ''): ?><p class="cp-muted"><?php echo $e($board['description']); ?></p><?php endif; ?>
                        <?php echo $renderBoardPreview($board); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card" id="cp-board-form">
        <div class="cp-panel-header"><h3><?php echo $editBoard !== null ? 'Board bearbeiten' : 'Board anlegen'; ?></h3></div>
        <form method="post" class="admin-form cp-form">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="cms_projects_action" value="save_board">
            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
            <input type="hidden" name="board_id" value="<?php echo (int) ($editBoard['id'] ?? 0); ?>">
            <div class="form-group"><label class="form-label required" for="cp-board-title">Titel</label><input id="cp-board-title" class="form-control" type="text" name="title" maxlength="190" required placeholder="z. B. Delivery Board" value="<?php echo $e($editBoard['title'] ?? ''); ?>"></div>
            <div class="cp-form-grid">
                <div class="form-group">
                    <label class="form-label" for="cp-board-type">Board-Typ</label>
                    <select id="cp-board-type" class="form-select" name="board_type">
                        <?php foreach ($boardTypes as $key => $config): ?>
                            <option value="<?php echo $e($key); ?>" <?php echo (($editBoard['board_type'] ?? 'kanban') === $key) ? 'selected' : ''; ?>><?php echo $e($config['label'] ?? $key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="cp-board-position">Position</label><input id="cp-board-position" class="form-control" type="number" name="position" min="0" max="9999" value="<?php echo (int) ($editBoard['position'] ?? count($projectBoards)); ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="cp-board-description">Beschreibung</label><textarea id="cp-board-description" class="form-control" name="description" rows="3" maxlength="1200"><?php echo $e($editBoard['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label class="form-label" for="cp-board-payload">Struktur (JSON)</label><textarea id="cp-board-payload" class="form-control cp-code-input" name="payload" rows="12" spellcheck="false" autocomplete="off"><?php echo $e($payloadJson); ?></textarea><div class="form-hint">Gruppen unter <code>columns</code>, <code>lanes</code>, <code>clusters</code>, <code>stages</code> oder <code>milestones</code>; optional <code>wip_limit</code>. Leer = Vorlage des Board-Typs. Ungültige Inhalte werden serverseitig gefiltert.</div></div>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" <?php echo $editBoard === null || !empty($editBoard['is_public']) ? 'checked' : ''; ?>> <span class="form-check-label">Auch im Public-Dashboard zeigen</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo $editBoard === null || !empty($editBoard['is_active']) ? 'checked' : ''; ?>> <span class="form-check-label">Board aktiv</span></label>
            <div class="cp-form-actions">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Board speichern</button>
                <?php if ($editBoard !== null): ?><a class="btn btn-link" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'boards')); ?>">Neues Board</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php
/**
 * Projekt anlegen/bearbeiten inkl. Export und Löschen.
 *
 * @var callable $e
 * @var array $projectFormValues
 * @var array|null $project
 * @var array $projectStatuses
 * @var array $projectVisibilities
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$projectIdValue = (int) ($projectFormValues['id'] ?? 0);
$isEdit = $projectIdValue > 0;
$actions = [['label' => 'Zur Liste', 'url' => $this->url(self::PAGE_PROJECTS), 'icon' => 'ti ti-arrow-left']];
if ($isEdit && $project !== null) {
    $actions[] = ['label' => 'Boards', 'url' => $this->url(self::PAGE_BOARDS, ['project_id' => $projectIdValue]), 'icon' => 'ti ti-layout-kanban'];
    $actions[] = ['label' => 'Tickets', 'url' => $this->url(self::PAGE_TASKS, ['project_id' => $projectIdValue]), 'icon' => 'ti ti-checklist'];
    $actions[] = ['label' => 'Widgets', 'url' => $this->url(self::PAGE_WIDGETS, ['project_id' => $projectIdValue]), 'icon' => 'ti ti-layout-grid'];
}

echo $this->pageHeader(
    $isEdit ? 'Projekt bearbeiten' : 'Neues Projekt',
    $isEdit ? (string) ($projectFormValues['name'] ?? '') : 'Stammdaten, Sichtbarkeit und Überschriften für Mitglieder- und öffentliche Ansicht.',
    $actions
);
?>
<div class="cp-admin-columns">
    <section class="admin-card cp-admin-section">
        <form method="post" action="<?php echo $e($this->url(self::PAGE_PROJECTS, $isEdit ? ['project_id' => $projectIdValue] : ['action' => 'new'])); ?>" class="cp-form">
            <?php echo $this->hiddenFields('save_project'); ?>
            <input type="hidden" name="edit_project_id" value="<?php echo $projectIdValue; ?>">
            <fieldset class="cp-form-section">
                <legend class="cp-form-section__title">Projektkern</legend>
                <label><span class="form-label">Name</span><input type="text" class="form-control" name="name" maxlength="190" required placeholder="z. B. Kundenportal 2026" value="<?php echo $e($projectFormValues['name'] ?? ''); ?>"></label>
                <label><span class="form-label">Slug</span><input type="text" class="form-control" name="slug" maxlength="120" pattern="[a-z0-9_-]*" placeholder="kundenportal-2026" value="<?php echo $e($projectFormValues['slug'] ?? ''); ?>"><small class="cp-field-hint">Leer lassen, um den Slug aus dem Namen zu erzeugen. Erlaubt: a–z, 0–9, - und _.</small></label>
                <label><span class="form-label">Owner / Lead</span><input type="text" class="form-control" name="owner_name" maxlength="190" placeholder="Projektleitung oder Team" value="<?php echo $e($projectFormValues['owner_name'] ?? ''); ?>"></label>
            </fieldset>
            <fieldset class="cp-form-section">
                <legend class="cp-form-section__title">Status und Darstellung</legend>
                <div class="cp-form-grid-2">
                    <label>
                        <span class="form-label">Status</span>
                        <select class="form-select" name="status">
                            <?php foreach ($projectStatuses as $key => $label): ?>
                                <option value="<?php echo $e($key); ?>"<?php echo ($projectFormValues['status'] ?? 'active') === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="form-label">Sichtbarkeit</span>
                        <select class="form-select" name="visibility">
                            <?php foreach ($projectVisibilities as $key => $label): ?>
                                <option value="<?php echo $e($key); ?>"<?php echo ($projectFormValues['visibility'] ?? 'member') === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <small class="cp-field-hint">Öffentlich sichtbar unter /projects sind nur aktive Projekte mit Sichtbarkeit „Öffentlich“; Mitglieder sehen aktive und pausierte Projekte mit Sichtbarkeit „Öffentlich“ oder „Mitglieder“.</small>
                <label><span class="form-label">Akzentfarbe</span><input type="color" class="form-control form-control-color" name="accent_color" value="<?php echo $e($projectFormValues['accent_color'] ?? '#2563eb'); ?>"></label>
            </fieldset>
            <fieldset class="cp-form-section">
                <legend class="cp-form-section__title">Überschriften und Inhalt</legend>
                <label><span class="form-label">Überschrift Mitgliederbereich</span><input type="text" class="form-control" name="member_headline" maxlength="190" value="<?php echo $e($projectFormValues['member_headline'] ?? ''); ?>"></label>
                <label><span class="form-label">Überschrift öffentliche Seite</span><input type="text" class="form-control" name="public_headline" maxlength="190" value="<?php echo $e($projectFormValues['public_headline'] ?? ''); ?>"></label>
                <label><span class="form-label">Kurzbeschreibung</span><textarea class="form-control" name="summary" rows="3" maxlength="1200" placeholder="Für Listen, Suche und Kopfbereich."><?php echo $e($projectFormValues['summary'] ?? ''); ?></textarea></label>
                <label><span class="form-label">Beschreibung</span><textarea class="form-control" name="description" rows="6" maxlength="20000" placeholder="Projektkontext, Ziele, Status und nächste Schritte."><?php echo $e($projectFormValues['description'] ?? ''); ?></textarea></label>
            </fieldset>
            <div class="cp-form-actions">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> <?php echo $isEdit ? 'Projekt speichern' : 'Projekt anlegen'; ?></button>
            </div>
        </form>
    </section>

    <?php if ($isEdit && $project !== null): ?>
        <aside class="cp-admin-aside">
            <section class="admin-card cp-admin-section">
                <div class="cp-panel-header"><h3>Kennzahlen</h3></div>
                <ul class="cp-facts">
                    <li><span>Boards</span><strong><?php echo (int) $project['board_count']; ?></strong></li>
                    <li><span>Widgets</span><strong><?php echo (int) $project['widget_count']; ?></strong></li>
                    <li><span>Tickets</span><strong><?php echo (int) $project['task_count']; ?></strong></li>
                    <li><span>Aktualisiert</span><strong><?php echo $e($this->formatDateTime((string) $project['updated_at'])); ?></strong></li>
                </ul>
                <div class="cp-inline-actions">
                    <?php if ($project['visibility'] !== 'private'): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($project['preview_links']['member']); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-users" aria-hidden="true"></i> Mitgliederansicht</a>
                    <?php endif; ?>
                    <?php if ($project['visibility'] === 'public' && $project['status'] === 'active'): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($project['preview_links']['public']); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-world" aria-hidden="true"></i> Öffentliche Seite</a>
                    <?php endif; ?>
                </div>
            </section>
            <section class="admin-card cp-admin-section">
                <div class="cp-panel-header"><h3>Export</h3><p>Projekt samt Boards, Widgets und Tickets als JSON herunterladen.</p></div>
                <form method="post" action="<?php echo $e($this->url(self::PAGE_PROJECTS, ['project_id' => $projectIdValue])); ?>">
                    <?php echo $this->hiddenFields('export_project', $projectIdValue); ?>
                    <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-download" aria-hidden="true"></i> JSON exportieren</button>
                </form>
            </section>
            <section class="admin-card cp-admin-section cp-danger-zone">
                <div class="cp-panel-header"><h3>Projekt löschen</h3><p>Entfernt das Projekt mit allen Boards, Widgets und Tickets endgültig.</p></div>
                <form method="post" action="<?php echo $e($this->url(self::PAGE_PROJECTS, ['project_id' => $projectIdValue])); ?>" data-cms-confirm="Projekt samt Boards, Widgets und Tickets endgültig löschen?">
                    <?php echo $this->hiddenFields('delete_project', $projectIdValue); ?>
                    <button type="submit" class="btn btn-danger"><i class="ti ti-trash" aria-hidden="true"></i> Projekt löschen</button>
                </form>
            </section>
        </aside>
    <?php endif; ?>
</div>

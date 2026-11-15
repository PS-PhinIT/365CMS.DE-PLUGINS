<?php
/**
 * Projektliste mit Filter, Sammelaktionen, Pagination und JSON-Import.
 *
 * @var callable $e
 * @var array $filters
 * @var array $pageData
 * @var array $projectStatuses
 * @var array $projectVisibilities
 * @var array $statusTones
 * @var int $importMaxKb
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo $this->pageHeader('Projekte', 'Projekte anlegen, filtern und gesammelt bearbeiten.', [
    ['label' => 'Neues Projekt', 'url' => $this->url(self::PAGE_PROJECTS, ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true],
]);
?>
<form method="get" action="<?php echo $e($this->url(self::PAGE_PROJECTS)); ?>" class="cp-toolbar toolbar">
    <label class="cp-toolbar__field cp-toolbar__field--grow">
        <span class="form-label">Suche</span>
        <input type="search" class="form-control" name="q" maxlength="100" value="<?php echo $e($filters['q']); ?>" placeholder="Name, Slug oder Owner">
    </label>
    <label class="cp-toolbar__field">
        <span class="form-label">Status</span>
        <select class="form-select" name="status">
            <option value="">Alle</option>
            <?php foreach ($projectStatuses as $key => $label): ?>
                <option value="<?php echo $e($key); ?>"<?php echo $filters['status'] === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="cp-toolbar__field">
        <span class="form-label">Sichtbarkeit</span>
        <select class="form-select" name="visibility">
            <option value="">Alle</option>
            <?php foreach ($projectVisibilities as $key => $label): ?>
                <option value="<?php echo $e($key); ?>"<?php echo $filters['visibility'] === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="cp-toolbar__actions">
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['visibility'] !== ''): ?>
            <a class="btn btn-link" href="<?php echo $e($this->url(self::PAGE_PROJECTS)); ?>">Zurücksetzen</a>
        <?php endif; ?>
    </div>
</form>

<section class="admin-card cp-admin-section">
    <?php if ($pageData['items'] === []): ?>
        <p class="cms-plugin-empty">
            <?php echo $pageData['total'] === 0 && $filters['q'] === '' && $filters['status'] === '' && $filters['visibility'] === ''
                ? 'Noch keine Projekte angelegt.'
                : 'Keine Projekte für diesen Filter gefunden.'; ?>
        </p>
    <?php else: ?>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_PROJECTS)); ?>" data-cms-confirm="Sammelaktion auf die ausgewählten Projekte anwenden? Beim Löschen werden auch Boards, Widgets und Tickets entfernt.">
            <?php echo $this->hiddenFields('bulk_projects'); ?>
            <?php foreach (['q', 'status', 'visibility'] as $key): ?>
                <input type="hidden" name="return_<?php echo $e($key); ?>" value="<?php echo $e($filters[$key]); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="return_paged" value="<?php echo (int) $pageData['page']; ?>">
            <div class="cp-bulkbar">
                <label class="cp-toolbar__field">
                    <span class="visually-hidden">Sammelaktion</span>
                    <select class="form-select" name="bulk_operation" required>
                        <option value="">Sammelaktion wählen</option>
                        <?php foreach ($projectStatuses as $key => $label): ?>
                            <option value="status:<?php echo $e($key); ?>">Status: <?php echo $e($label); ?></option>
                        <?php endforeach; ?>
                        <option value="delete">Löschen</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-outline-secondary">Anwenden</button>
            </div>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th class="cp-col-check"><input type="checkbox" class="form-check-input" data-cp-check-all aria-label="Alle auswählen"></th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Sichtbarkeit</th>
                            <th>Boards</th>
                            <th>Widgets</th>
                            <th>Tickets</th>
                            <th>Aktualisiert</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageData['items'] as $project): ?>
                            <?php $id = (int) $project['id']; ?>
                            <tr>
                                <td class="cp-col-check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo $id; ?>" aria-label="Projekt auswählen"></td>
                                <td>
                                    <a href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['project_id' => $id])); ?>"><strong><?php echo $e($project['name']); ?></strong></a>
                                    <div class="cp-muted"><code><?php echo $e($project['slug']); ?></code></div>
                                </td>
                                <td><span class="cp-badge cp-badge--<?php echo $e($statusTones[$project['status']] ?? 'muted'); ?>"><?php echo $e($projectStatuses[$project['status']] ?? $project['status']); ?></span></td>
                                <td><?php echo $e($projectVisibilities[$project['visibility']] ?? $project['visibility']); ?></td>
                                <td><a href="<?php echo $e($this->url(self::PAGE_BOARDS, ['project_id' => $id])); ?>"><?php echo (int) $project['board_count']; ?></a></td>
                                <td><a href="<?php echo $e($this->url(self::PAGE_WIDGETS, ['project_id' => $id])); ?>"><?php echo (int) $project['widget_count']; ?></a></td>
                                <td><a href="<?php echo $e($this->url(self::PAGE_TASKS, ['project_id' => $id])); ?>"><?php echo (int) $project['task_count']; ?></a></td>
                                <td><?php echo $e($this->formatDateTime((string) $project['updated_at'])); ?></td>
                                <td class="cp-row-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['project_id' => $id])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                    <?php if ($project['visibility'] !== 'private'): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($project['preview_links']['member']); ?>" target="_blank" rel="noopener noreferrer" title="Mitgliederansicht"><i class="ti ti-users" aria-hidden="true"></i><span class="visually-hidden">Mitgliederansicht</span></a>
                                    <?php endif; ?>
                                    <?php if ($project['visibility'] === 'public' && $project['status'] === 'active'): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($project['preview_links']['public']); ?>" target="_blank" rel="noopener noreferrer" title="Öffentliche Seite"><i class="ti ti-world" aria-hidden="true"></i><span class="visually-hidden">Öffentliche Seite</span></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php echo $this->pagination($pageData, self::PAGE_PROJECTS, $filters); ?>
    <?php endif; ?>
</section>

<section class="admin-card cp-admin-section">
    <div class="cp-panel-header">
        <h3>Projekt importieren</h3>
        <p>JSON-Export eines Projekts (inkl. Boards, Widgets, Tickets) einlesen. Importierte Projekte starten als Entwurf.</p>
    </div>
    <form method="post" action="<?php echo $e($this->url(self::PAGE_PROJECTS)); ?>" enctype="multipart/form-data" class="cp-inline-upload">
        <?php echo $this->hiddenFields('import_project'); ?>
        <label class="cp-toolbar__field cp-toolbar__field--grow">
            <span class="form-label">Exportdatei (.json, max. <?php echo (int) $importMaxKb; ?> KB)</span>
            <input type="file" class="form-control" name="import_file" accept=".json,application/json" required>
        </label>
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-upload" aria-hidden="true"></i> Importieren</button>
    </form>
</section>

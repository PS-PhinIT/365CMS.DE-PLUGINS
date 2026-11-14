<?php
/**
 * Projektliste mit Filter-Toolbar.
 *
 * @var list<array<string,mixed>> $projects
 * @var array{status:string,visibility:string,q:string} $filters
 * @var array<string,string> $projectStatuses
 * @var array<string,string> $projectVisibilities
 * @var string $csrfToken
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Projects_Admin::pageUrl('cms-projects-projects');
$filtered = $filters['status'] !== '' || $filters['visibility'] !== '' || $filters['q'] !== '';
?>
<div class="admin-page-header">
    <div>
        <h2>Projekte</h2>
        <p>Projekt-Dashboards mit Boards, Widgets und Tickets. Sichtbarkeit steuert Member- und Public-Ausgabe.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Projects_Admin::pageUrl('cms-projects-projects', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neues Projekt</a>
    </div>
</div>

<form method="get" action="<?php echo $e($listUrl); ?>" class="cp-toolbar" role="search" aria-label="Projekte filtern">
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-filter-q">Suche</label>
        <input id="cp-filter-q" type="search" name="q" class="form-control" value="<?php echo $e($filters['q']); ?>" placeholder="Name, Slug oder Owner">
    </div>
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-filter-status">Status</label>
        <select id="cp-filter-status" name="status" class="form-select">
            <option value="">Alle</option>
            <?php foreach ($projectStatuses as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-filter-visibility">Sichtbarkeit</label>
        <select id="cp-filter-visibility" name="visibility" class="form-select">
            <option value="">Alle</option>
            <?php foreach ($projectVisibilities as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['visibility'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cp-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filtered): ?><a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Zurücksetzen</a><?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($projects === []): ?>
        <div class="empty-state">
            <p><strong><?php echo $filtered ? 'Keine Projekte für diesen Filter' : 'Noch keine Projekte angelegt'; ?></strong></p>
            <p class="cp-muted">Ein Projekt bündelt Boards, Tickets und Widgets für Member- und Public-Dashboards.</p>
        </div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Projekt</th><th scope="col">Status</th><th scope="col">Sichtbarkeit</th><th scope="col" class="cp-num">Boards</th><th scope="col" class="cp-num">Widgets</th><th scope="col" class="cp-num">Tickets</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php $id = (int) $project['id']; ?>
                    <tr>
                        <td>
                            <?php echo CMS_Projects_Service::accentSwatch($project, 'cp-swatch'); ?>
                            <a class="cp-admin-link" href="<?php echo $e(CMS_Projects_Admin::projectUrl($id)); ?>"><?php echo $e($project['name']); ?></a>
                            <div class="cp-muted"><?php echo $e($project['slug']); ?><?php echo ($project['owner_name'] ?? '') !== '' ? ' · ' . $e($project['owner_name']) : ''; ?></div>
                        </td>
                        <td><span class="cp-badge cp-badge--<?php echo $e($project['status']); ?>"><?php echo $e($projectStatuses[$project['status']] ?? $project['status']); ?></span></td>
                        <td><?php echo $e($projectVisibilities[$project['visibility']] ?? $project['visibility']); ?></td>
                        <td class="cp-num"><a href="<?php echo $e(CMS_Projects_Admin::projectUrl($id, 'boards')); ?>"><?php echo (int) ($project['board_count'] ?? 0); ?></a></td>
                        <td class="cp-num"><a href="<?php echo $e(CMS_Projects_Admin::projectUrl($id, 'widgets')); ?>"><?php echo (int) ($project['widget_count'] ?? 0); ?></a></td>
                        <td class="cp-num"><a href="<?php echo $e(CMS_Projects_Admin::projectUrl($id, 'tasks')); ?>"><?php echo (int) ($project['task_count'] ?? 0); ?></a></td>
                        <td>
                            <div class="cp-action-group">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::projectUrl($id)); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <?php if (($project['visibility'] ?? '') === 'public'): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($project['preview_links']['public'] ?? '/projects'); ?>" target="_blank" rel="noopener noreferrer" title="Öffentlich ansehen"><i class="ti ti-external-link" aria-hidden="true"></i><span class="visually-hidden">Öffentlich ansehen (neuer Tab)</span></a>
                                <?php endif; ?>
                                <form method="post" data-cms-confirm="Projekt „<?php echo $e($project['name']); ?>“ inkl. aller Boards, Widgets und Tickets endgültig löschen?">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="cms_projects_action" value="delete_project">
                                    <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

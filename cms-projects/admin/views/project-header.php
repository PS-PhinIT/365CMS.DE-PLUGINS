<?php
/**
 * Kopf der Projekt-Unterseiten (Titel, Vorschau, Export, Löschen).
 *
 * @var array<string,mixed>|null $project
 * @var string $tab
 * @var string $csrfToken
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$tabTitles = ['details' => 'Stammdaten', 'boards' => 'Boards', 'widgets' => 'Widgets', 'tasks' => 'Tickets'];
?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $project !== null ? $e($project['name']) . ' – ' . $e($tabTitles[$tab] ?? '') : 'Neues Projekt'; ?></h2>
        <p><?php echo $project !== null ? $e(($project['summary'] ?? '') !== '' ? mb_strimwidth((string) $project['summary'], 0, 180, '…') : 'Projekt-Slug: ' . $project['slug']) : 'Stammdaten, Sichtbarkeit und Headlines für Member- und Public-Dashboards.'; ?></p>
    </div>
    <div class="header-actions">
        <?php if ($project !== null): ?>
            <a class="btn btn-outline-secondary" href="<?php echo $e($project['preview_links']['member'] ?? '/member/plugin/projects'); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-users" aria-hidden="true"></i> Member</a>
            <?php if (($project['visibility'] ?? '') === 'public'): ?>
                <a class="btn btn-outline-secondary" href="<?php echo $e($project['preview_links']['public'] ?? '/projects'); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-world" aria-hidden="true"></i> Public</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::exportUrl('project_json', ['project_id' => (int) $project['id']])); ?>"><i class="ti ti-braces" aria-hidden="true"></i> JSON-Export</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::pageUrl('cms-projects-projects')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Alle Projekte</a>
    </div>
</div>

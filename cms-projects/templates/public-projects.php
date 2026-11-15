<?php
/**
 * Öffentliche Projektseiten (Archiv, Einzelprojekt, nicht gefunden).
 *
 * @var array $projects
 * @var array|null $currentProject
 * @var array|null $dashboard
 * @var string $view archive|single|not_found
 * @var string $publicLang
 * @var array $statusLabels
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$publicLang = $publicLang === 'en' ? 'en' : 'de';
$i18n = [
    'kicker' => ['de' => 'Projekte', 'en' => 'Projects'],
    'hero_title_archive' => ['de' => 'Öffentliche Projektübersicht', 'en' => 'Public project overview'],
    'hero_desc_archive' => ['de' => 'Projekt-Dashboards, Statusinformationen und Boards im öffentlichen Zugriff.', 'en' => 'Project dashboards, status updates, and boards available publicly.'],
    'not_found_title' => ['de' => 'Projekt nicht gefunden', 'en' => 'Project not found'],
    'not_found_desc' => ['de' => 'Das Projekt existiert nicht oder ist nicht öffentlich.', 'en' => 'The project does not exist or is not public.'],
    'back' => ['de' => 'Zur Projektübersicht', 'en' => 'Back to all projects'],
    'project_count' => ['de' => 'Projekt(e)', 'en' => 'Project(s)'],
    'boards' => ['de' => 'Boards', 'en' => 'Boards'],
    'widgets' => ['de' => 'Widgets', 'en' => 'Widgets'],
    'status_empty_projects' => ['de' => 'Aktuell sind keine öffentlichen Projekte sichtbar.', 'en' => 'There are currently no public projects visible.'],
    'default_owner' => ['de' => 'Projektteam', 'en' => 'Project team'],
    'open_project' => ['de' => 'Projekt öffnen', 'en' => 'Open project'],
    'status_empty_boards' => ['de' => 'Für dieses Projekt sind aktuell keine öffentlichen Boards sichtbar.', 'en' => 'No boards are currently visible for this project.'],
    'status_empty_widgets' => ['de' => 'Für dieses Projekt gibt es keine öffentlichen Widgets.', 'en' => 'There are no public widgets for this project.'],
];
$t = static fn (string $key): string => (string) ($i18n[$key][$publicLang] ?? $i18n[$key]['de'] ?? $key);
$e = static fn (mixed $value): string => CMS_Projects_View::esc($value);
$projectPath = static function (string $slug) use ($publicLang): string {
    $relative = $slug !== '' ? 'projects/' . rawurlencode($slug) : 'projects';
    return function_exists('cms_plugin_public_localized_path')
        ? (string) cms_plugin_public_localized_path($relative, $publicLang)
        : ($publicLang === 'en' ? '/en/' : '/') . $relative;
};

CMS_Projects_View::markStylesheetPrinted();
echo CMS_Projects_View::accentStyleBlock($currentProject !== null ? array_merge($projects, [$currentProject]) : $projects);
?>
<main class="cp-public-shell">
    <section class="cp-public-hero">
        <div class="cp-hero-copy">
            <span class="cp-kicker"><?php echo $e($t('kicker')); ?></span>
            <?php if ($view === 'single' && $currentProject !== null): ?>
                <h1><?php echo $e($currentProject['public_headline'] !== '' ? $currentProject['public_headline'] : $currentProject['name']); ?></h1>
                <p><?php echo $e($currentProject['summary']); ?></p>
            <?php elseif ($view === 'not_found'): ?>
                <h1><?php echo $e($t('not_found_title')); ?></h1>
                <p><?php echo $e($t('not_found_desc')); ?></p>
            <?php else: ?>
                <h1><?php echo $e($t('hero_title_archive')); ?></h1>
                <p><?php echo $e($t('hero_desc_archive')); ?></p>
            <?php endif; ?>
        </div>
        <div class="cp-hero-meta">
            <?php if ($view === 'single' && $currentProject !== null): ?>
                <span class="cp-hero-pill"><?php echo count((array) ($dashboard['boards'] ?? [])); ?> <?php echo $e($t('boards')); ?></span>
                <span class="cp-hero-pill"><?php echo count((array) ($dashboard['widgets'] ?? [])); ?> <?php echo $e($t('widgets')); ?></span>
                <a class="cp-hero-pill" href="<?php echo $e($projectPath('')); ?>"><?php echo $e($t('back')); ?></a>
            <?php else: ?>
                <span class="cp-hero-pill"><?php echo count($projects); ?> <?php echo $e($t('project_count')); ?></span>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($view === 'single' && $dashboard !== null && $currentProject !== null): ?>
        <section class="cp-dashboard-shell">
            <div class="cp-dashboard-main">
                <article class="cp-project-detail-card <?php echo $e(CMS_Projects_View::accentClass($currentProject)); ?>">
                    <span class="cp-project-color" aria-hidden="true"></span>
                    <div class="cp-card-meta-row">
                        <span class="cp-badge"><?php echo $e($statusLabels[$currentProject['status']] ?? $currentProject['status']); ?></span>
                        <?php if ($currentProject['owner_name'] !== ''): ?>
                            <span class="cp-badge"><?php echo $e($currentProject['owner_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo $e($currentProject['name']); ?></h2>
                    <?php if ($currentProject['description'] !== ''): ?>
                        <p><?php echo nl2br($e($currentProject['description'])); ?></p>
                    <?php endif; ?>
                </article>
                <div class="cp-board-stack">
                    <?php if (($dashboard['boards'] ?? []) === []): ?>
                        <div class="cp-empty-state" role="status"><?php echo $e($t('status_empty_boards')); ?></div>
                    <?php else: ?>
                        <?php foreach ($dashboard['boards'] as $board): ?>
                            <?php echo CMS_Projects_View::renderBoard($board, ['show_description' => true, 'lang' => $publicLang]); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="cp-dashboard-side">
                <?php if (($dashboard['widgets'] ?? []) === []): ?>
                    <div class="cp-empty-state" role="status"><?php echo $e($t('status_empty_widgets')); ?></div>
                <?php else: ?>
                    <?php foreach ($dashboard['widgets'] as $widget): ?>
                        <?php echo CMS_Projects_View::renderWidget($widget); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </section>
    <?php else: ?>
        <section class="cp-project-grid">
            <?php if ($projects === []): ?>
                <div class="cp-empty-state" role="status"><?php echo $e($t('status_empty_projects')); ?></div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <article class="cp-project-card <?php echo $e(CMS_Projects_View::accentClass($project)); ?>">
                        <span class="cp-project-color" aria-hidden="true"></span>
                        <div class="cp-card-meta-row">
                            <span class="cp-badge"><?php echo $e($statusLabels[$project['status']] ?? $project['status']); ?></span>
                            <span class="cp-badge"><?php echo $e($project['owner_name'] !== '' ? $project['owner_name'] : $t('default_owner')); ?></span>
                        </div>
                        <h2><?php echo $e($project['name']); ?></h2>
                        <p><?php echo $e($project['summary']); ?></p>
                        <a class="cp-button" href="<?php echo $e($projectPath((string) $project['slug'])); ?>"><?php echo $e($t('open_project')); ?></a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

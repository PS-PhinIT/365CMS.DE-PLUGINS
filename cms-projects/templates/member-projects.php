<?php
/**
 * Mitgliederbereich: Projektauswahl, Boards und Widgets (nur lesend).
 *
 * @var array $projects
 * @var array|null $currentProject
 * @var array|null $dashboard
 * @var array $statusLabels
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => CMS_Projects_View::esc($value);
echo CMS_Projects_View::stylesheetTag();
echo CMS_Projects_View::accentStyleBlock($projects);
?>
<section class="cp-member-shell" aria-label="Projekte">
    <section class="cp-public-hero">
        <div class="cp-hero-copy">
            <span class="cp-kicker">Projekte</span>
            <?php if ($currentProject !== null): ?>
                <h1><?php echo $e($currentProject['member_headline'] !== '' ? $currentProject['member_headline'] : $currentProject['name']); ?></h1>
                <p><?php echo $e($currentProject['summary']); ?></p>
            <?php else: ?>
                <h1>Projekte</h1>
                <p>Projektboards und Widgets für Mitglieder.</p>
            <?php endif; ?>
        </div>
        <div class="cp-hero-meta">
            <span class="cp-hero-pill"><?php echo count($projects); ?> Projekt(e)</span>
            <?php if ($dashboard !== null): ?>
                <span class="cp-hero-pill"><?php echo count((array) $dashboard['boards']); ?> Boards</span>
                <span class="cp-hero-pill"><?php echo count((array) $dashboard['widgets']); ?> Widgets</span>
            <?php endif; ?>
        </div>
    </section>

    <?php if (count($projects) > 1): ?>
        <nav class="cp-member-project-switcher" aria-label="Projekt wählen">
            <?php foreach ($projects as $project): ?>
                <?php $isCurrent = (int) ($currentProject['id'] ?? 0) === (int) $project['id']; ?>
                <a class="cp-member-project-pill<?php echo $isCurrent ? ' is-active' : ''; ?>" href="/member/plugin/projects?project=<?php echo $e(rawurlencode((string) $project['slug'])); ?>"<?php echo $isCurrent ? ' aria-current="page"' : ''; ?>><?php echo $e($project['name']); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($dashboard === null || $currentProject === null): ?>
        <div class="cp-empty-state" role="status">Noch keine Projekte verfügbar.</div>
    <?php else: ?>
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
                    <?php if ($currentProject['visibility'] === 'public' && $currentProject['status'] === 'active'): ?>
                        <div class="cp-inline-actions">
                            <a class="cp-button" href="<?php echo $e($currentProject['preview_links']['public']); ?>">Öffentliche Seite ansehen</a>
                        </div>
                    <?php endif; ?>
                </article>
                <div class="cp-board-stack">
                    <?php if ($dashboard['boards'] === []): ?>
                        <div class="cp-empty-state" role="status">Für dieses Projekt sind aktuell keine Boards verfügbar.</div>
                    <?php else: ?>
                        <?php foreach ($dashboard['boards'] as $board): ?>
                            <?php echo CMS_Projects_View::renderBoard($board, ['show_description' => true]); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="cp-dashboard-side">
                <?php if ($dashboard['widgets'] === []): ?>
                    <div class="cp-empty-state" role="status">Für dieses Projekt sind aktuell keine Widgets verfügbar.</div>
                <?php else: ?>
                    <?php foreach ($dashboard['widgets'] as $widget): ?>
                        <?php echo CMS_Projects_View::renderWidget($widget); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </section>
    <?php endif; ?>
</section>

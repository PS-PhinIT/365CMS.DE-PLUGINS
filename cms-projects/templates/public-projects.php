<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$publicLang = isset($publicLang) && $publicLang === 'en' ? 'en' : 'de';
$publicI18n = [
    'hero_title_archive' => ['de' => 'Oeffentliche Projektuebersicht', 'en' => 'Public project overview'],
    'hero_desc_archive' => ['de' => 'Projekt-Dashboards, Statusinformationen und Boards im oeffentlichen Zugriff.', 'en' => 'Project dashboards, status updates, and boards available publicly.'],
    'project_count' => ['de' => 'Projekt(e)', 'en' => 'Project(s)'],
    'boards' => ['de' => 'Boards', 'en' => 'Boards'],
    'widgets' => ['de' => 'Widgets', 'en' => 'Widgets'],
    'tickets' => ['de' => 'Tickets', 'en' => 'Tickets'],
    'status_empty_projects' => ['de' => 'Aktuell sind keine oeffentlichen Projekte sichtbar.', 'en' => 'There are currently no public projects visible.'],
    'default_owner' => ['de' => 'Projektteam', 'en' => 'Project team'],
    'open_project' => ['de' => 'Projekt oeffnen', 'en' => 'Open project'],
    'status_empty_boards' => ['de' => 'Fuer dieses oeffentliche Projekt sind aktuell keine Boards sichtbar.', 'en' => 'No boards are currently visible for this public project.'],
    'status_empty_widgets' => ['de' => 'Fuer dieses Projekt sind keine oeffentlichen Widgets vorhanden.', 'en' => 'There are no public widgets for this project.'],
    'block_default' => ['de' => 'Block', 'en' => 'Block'],
    'wip_limit_reached' => ['de' => 'WIP-Limit erreicht', 'en' => 'WIP limit reached'],
    'wip_limit_exceeded' => ['de' => 'WIP-Limit ueberschritten', 'en' => 'WIP limit exceeded'],
];
$t = static function (string $key, string $fallback = '') use ($publicI18n, $publicLang): string {
    $entry = $publicI18n[$key] ?? null;
    if (!is_array($entry)) {
        return $fallback !== '' ? $fallback : $key;
    }

    if (function_exists('cms_plugin_public_i18n_value')) {
        return (string) cms_plugin_public_i18n_value([
            'value' => (string) ($entry['de'] ?? ''),
            'value_en' => (string) ($entry['en'] ?? ''),
        ], 'value', $publicLang, $fallback !== '' ? $fallback : $key);
    }

    if ($publicLang === 'en' && isset($entry['en']) && $entry['en'] !== '') {
        return (string) $entry['en'];
    }

    return (string) ($entry['de'] ?? ($fallback !== '' ? $fallback : $key));
};
$buildProjectPath = static function (string $slug, string $lang) : string {
    $relativePath = $slug !== '' ? 'projects/' . rawurlencode($slug) : 'projects';
    if (function_exists('cms_plugin_public_localized_path')) {
        return (string) cms_plugin_public_localized_path($relativePath, $lang);
    }

    return $lang === 'en' ? '/en/' . $relativePath : '/' . $relativePath;
};

$renderBoardBlock = static function (array $board) use ($t): void {
    $payload = (array) ($board['payload_data'] ?? []);
    $groups = [];
    foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            $groups = $payload[$key];
            break;
        }
    }
    echo '<article class="cp-board-card">';
    echo '<div class="cp-board-card__head"><strong>' . htmlspecialchars((string) ($board['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($board['board_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
    if (!empty($board['description'])) {
        echo '<p>' . htmlspecialchars((string) ($board['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<div class="cp-board-columns">';
    foreach ($groups as $group) {
        $wipLimit = max(0, (int) ($group['wip_limit'] ?? 0));
        $wipCount = max(0, (int) ($group['wip_count'] ?? count((array) ($group['tasks'] ?? []))));
        $wipReached = !empty($group['wip_limit_reached']);
        $wipOverLimit = !empty($group['wip_over_limit']);
        echo '<div class="cp-board-column">';
        echo '<h4>' . htmlspecialchars((string) ($group['title'] ?? $t('block_default', 'Block')), ENT_QUOTES, 'UTF-8') . '</h4>';
        if ($wipLimit > 0) {
            echo '<div class="cp-ticket-meta">';
            echo '<span class="cp-badge">WIP ' . $wipCount . '/' . $wipLimit . '</span>';
            if ($wipOverLimit) {
                echo '<span class="cp-badge">' . htmlspecialchars($t('wip_limit_exceeded', 'WIP limit exceeded'), ENT_QUOTES, 'UTF-8') . '</span>';
            } elseif ($wipReached) {
                echo '<span class="cp-badge">' . htmlspecialchars($t('wip_limit_reached', 'WIP limit reached'), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</div>';
        }
        echo '<ul>';
        foreach ((array) ($group['items'] ?? []) as $item) {
            echo '<li>' . htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
        if (((array) ($group['tasks'] ?? [])) !== []) {
            echo '<div class="cp-ticket-stack">';
            foreach ((array) ($group['tasks'] ?? []) as $task) {
                echo '<article class="cp-ticket-card">';
                echo '<div class="cp-ticket-card__head"><strong>' . htmlspecialchars((string) ($task['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span class="cp-badge">' . htmlspecialchars((string) ($task['priority_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
                if (!empty($task['description'])) {
                    echo '<p>' . nl2br(htmlspecialchars((string) ($task['description'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
                }
                echo '<div class="cp-ticket-meta">';
                if (!empty($task['assignee_name'])) {
                    echo '<span>' . htmlspecialchars((string) ($task['assignee_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
                }
                if (!empty($task['due_date'])) {
                    echo '<span>' . htmlspecialchars((string) ($task['due_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
                }
                echo '</div>';
                echo '</article>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</article>';
};

$renderWidgetBlock = static function (array $widget): void {
    $payload = (array) ($widget['payload_data'] ?? []);
    $type = (string) ($widget['widget_type'] ?? 'text');
    echo '<article class="cp-widget-card">';
    echo '<div class="cp-widget-card__head"><strong>' . htmlspecialchars((string) ($widget['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($widget['widget_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
    if ($type === 'text') {
        echo '<p>' . nl2br(htmlspecialchars((string) ($widget['content'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
    } elseif ($type === 'checklist') {
        echo '<ul class="cp-checklist">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            echo '<li class="' . (!empty($item['done']) ? 'done' : '') . '">' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    } elseif ($type === 'links') {
        echo '<ul class="cp-link-list">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
            echo '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a></li>';
        }
        echo '</ul>';
    } elseif ($type === 'stats') {
        echo '<div class="cp-stats-grid">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            echo '<div class="cp-stat-card"><strong>' . htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
        }
        echo '</div>';
    } else {
        echo '<ul class="cp-timeline">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            echo '<li><strong>' . htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</article>';
};
?>
<main class="cp-public-shell">
    <section class="cp-public-hero">
        <div class="cp-hero-copy">
            <span class="cp-kicker">365CMS Projects</span>
            <h1><?php echo $view === 'single' && $currentProject !== null ? htmlspecialchars((string) (($currentProject['public_headline'] ?? '') !== '' ? ($currentProject['public_headline'] ?? '') : ($currentProject['name'] ?? '')), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('hero_title_archive', 'Public project overview'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo $view === 'single' && $currentProject !== null ? htmlspecialchars((string) ($currentProject['summary'] ?? ''), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('hero_desc_archive', ''), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="cp-hero-meta">
            <span class="cp-hero-pill"><?php echo count($projects); ?> <?php echo htmlspecialchars($t('project_count', 'Project(s)'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($view === 'single' && $currentProject !== null): ?>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['board_count'] ?? 0); ?> <?php echo htmlspecialchars($t('boards', 'Boards'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['widget_count'] ?? 0); ?> <?php echo htmlspecialchars($t('widgets', 'Widgets'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['task_count'] ?? 0); ?> <?php echo htmlspecialchars($t('tickets', 'Tickets'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($view === 'archive'): ?>
        <section class="cp-project-grid">
            <?php if ($projects === []): ?>
                <div class="cp-empty-state" role="status" aria-live="polite"><?php echo htmlspecialchars($t('status_empty_projects', ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <article class="cp-project-card">
                        <?php echo CMS_Projects_Service::accentSwatch($project); ?>
                        <div class="cp-card-meta-row">
                            <span class="cp-badge"><?php echo htmlspecialchars((string) ($project['status'] ?? 'active'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="cp-badge"><?php echo htmlspecialchars((string) (($project['owner_name'] ?? '') !== '' ? ($project['owner_name'] ?? '') : $t('default_owner', 'Project team')), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <h2><?php echo htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p><?php echo htmlspecialchars((string) ($project['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="cp-project-card__meta">
                            <span><?php echo (int) ($project['board_count'] ?? 0); ?> <?php echo htmlspecialchars($t('boards', 'Boards'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo (int) ($project['widget_count'] ?? 0); ?> <?php echo htmlspecialchars($t('widgets', 'Widgets'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo (int) ($project['task_count'] ?? 0); ?> <?php echo htmlspecialchars($t('tickets', 'Tickets'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <a class="cp-button" href="<?php echo htmlspecialchars($buildProjectPath((string) ($project['slug'] ?? ''), $publicLang), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t('open_project', 'Open project'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php elseif ($dashboard !== null): ?>
        <section class="cp-dashboard-shell">
            <div class="cp-dashboard-main">
                <article class="cp-project-detail-card">
                    <?php echo CMS_Projects_Service::accentSwatch((array) ($dashboard['project'] ?? [])); ?>
                    <div class="cp-card-meta-row">
                        <span class="cp-badge"><?php echo htmlspecialchars((string) ($dashboard['project']['status'] ?? 'active'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($dashboard['project']['owner_name'])): ?>
                            <span class="cp-badge"><?php echo htmlspecialchars((string) ($dashboard['project']['owner_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo htmlspecialchars((string) ($dashboard['project']['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars((string) ($dashboard['project']['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                </article>
                <div class="cp-board-stack">
                    <?php if (($dashboard['boards'] ?? []) === []): ?>
                        <div class="cp-empty-state" role="status" aria-live="polite"><?php echo htmlspecialchars($t('status_empty_boards', ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php else: ?>
                        <?php foreach (($dashboard['boards'] ?? []) as $board): ?>
                            <?php $renderBoardBlock($board); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="cp-dashboard-side">
                <?php if (($dashboard['widgets'] ?? []) === []): ?>
                    <div class="cp-empty-state" role="status" aria-live="polite"><?php echo htmlspecialchars($t('status_empty_widgets', ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                    <?php foreach (($dashboard['widgets'] ?? []) as $widget): ?>
                        <?php $renderWidgetBlock($widget); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </section>
    <?php endif; ?>
</main>

<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$renderBoardBlock = static function (array $board): void {
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
    echo '<div class="cp-board-columns">';
    foreach ($groups as $group) {
        $wipLimit = max(0, (int) ($group['wip_limit'] ?? 0));
        $wipCount = max(0, (int) ($group['wip_count'] ?? count((array) ($group['tasks'] ?? []))));
        $wipReached = !empty($group['wip_limit_reached']);
        $wipOverLimit = !empty($group['wip_over_limit']);
        echo '<div class="cp-board-column">';
        echo '<h4>' . htmlspecialchars((string) ($group['title'] ?? 'Block'), ENT_QUOTES, 'UTF-8') . '</h4>';
        if ($wipLimit > 0) {
            echo '<div class="cp-ticket-meta">';
            echo '<span class="cp-badge">WIP ' . $wipCount . '/' . $wipLimit . '</span>';
            if ($wipOverLimit) {
                echo '<span class="cp-badge">Limit ueberschritten</span>';
            } elseif ($wipReached) {
                echo '<span class="cp-badge">Limit erreicht</span>';
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
<section class="cp-member-shell" aria-label="Projektbereich">
    <section class="cp-public-hero">
        <div class="cp-hero-copy">
            <span class="cp-kicker">Member Projects</span>
            <h1><?php echo $currentProject !== null ? htmlspecialchars((string) ((($currentProject['member_headline'] ?? '') !== '' ? ($currentProject['member_headline'] ?? '') : ($currentProject['name'] ?? ''))), ENT_QUOTES, 'UTF-8') : 'Projekte'; ?></h1>
            <p><?php echo $currentProject !== null ? htmlspecialchars((string) ($currentProject['summary'] ?? ''), ENT_QUOTES, 'UTF-8') : 'Projektboards und Widgets im Member-Bereich.'; ?></p>
        </div>
        <div class="cp-hero-meta">
            <span class="cp-hero-pill"><?php echo count($projects); ?> Projekt(e)</span>
            <?php if ($currentProject !== null): ?>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['board_count'] ?? 0); ?> Boards</span>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['widget_count'] ?? 0); ?> Widgets</span>
                <span class="cp-hero-pill"><?php echo (int) ($currentProject['task_count'] ?? 0); ?> Tickets</span>
            <?php endif; ?>
        </div>
    </section>

    <div class="cp-member-project-switcher">
        <?php foreach ($projects as $project): ?>
            <a class="cp-member-project-pill <?php echo (($currentProject['id'] ?? 0) === ($project['id'] ?? 0)) ? 'active' : ''; ?>" href="/member/plugin/projects?project=<?php echo rawurlencode((string) ($project['slug'] ?? '')); ?>"><?php echo htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($dashboard === null): ?>
        <div class="cp-empty-state" role="status" aria-live="polite">Noch keine Projekte verfügbar.</div>
    <?php else: ?>
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
                    <div class="cp-inline-actions">
                        <a class="cp-button" href="<?php echo htmlspecialchars((string) ($dashboard['project']['preview_links']['public'] ?? '/projects'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Public ansehen</a>
                    </div>
                </article>
                <div class="cp-board-stack">
                    <?php if (($dashboard['boards'] ?? []) === []): ?>
                        <div class="cp-empty-state" role="status" aria-live="polite">Für dieses Projekt sind aktuell keine Boards verfügbar.</div>
                    <?php else: ?>
                        <?php foreach (($dashboard['boards'] ?? []) as $board): ?>
                            <?php $renderBoardBlock($board); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="cp-dashboard-side">
                <?php if (($dashboard['widgets'] ?? []) === []): ?>
                    <div class="cp-empty-state" role="status" aria-live="polite">Für dieses Projekt sind aktuell keine Widgets verfügbar.</div>
                <?php else: ?>
                    <?php foreach (($dashboard['widgets'] ?? []) as $widget): ?>
                        <?php $renderWidgetBlock($widget); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </section>
    <?php endif; ?>
</section>

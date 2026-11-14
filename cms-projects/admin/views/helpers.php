<?php
/**
 * Gemeinsame Render-Helfer der Projekt-Adminansichten (Board-/Widget-Vorschau, Spaltenoptionen).
 *
 * Erwartet: $projectBoards (für $boardColumnOptions).
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$collectBoardGroups = static function (array $payload): array {
    foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            return $payload[$key];
        }
    }

    return [];
};

$renderBoardPreview = static function (array $board): string {
    $payload = (array) ($board['payload_data'] ?? []);
    $groups = [];
    $boardId = (int) ($board['id'] ?? 0);
    foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            $groups = $payload[$key];
            break;
        }
    }

    ob_start();
    echo '<div class="cp-board-preview-grid">';
    foreach ($groups as $group) {
        $title = htmlspecialchars((string) ($group['title'] ?? 'Block'), ENT_QUOTES, 'UTF-8');
        $columnKey = htmlspecialchars((string) ($group['key'] ?? ''), ENT_QUOTES, 'UTF-8');
        $wipLimit = max(0, (int) ($group['wip_limit'] ?? 0));
        $wipCount = max(0, (int) ($group['wip_count'] ?? count((array) ($group['tasks'] ?? []))));
        $wipReached = !empty($group['wip_limit_reached']);
        $wipOverLimit = !empty($group['wip_over_limit']);
        echo '<article class="cp-board-preview-column" data-drop-board-id="' . $boardId . '" data-drop-column-key="' . $columnKey . '">';
        echo '<h4>' . $title . '</h4>';
        if ($wipLimit > 0) {
            echo '<div class="cp-ticket-meta">';
            echo '<span class="cp-badge">WIP ' . $wipCount . '/' . $wipLimit . '</span>';
            if ($wipOverLimit) {
                echo '<span class="cp-badge">Limit überschritten</span>';
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
        $tasks = (array) ($group['tasks'] ?? []);
        echo '<div class="cp-ticket-stack" data-ticket-stack>';
        foreach ($tasks as $task) {
            echo '<article class="cp-ticket-card cp-ticket-card--draggable" draggable="true" data-task-id="' . (int) ($task['id'] ?? 0) . '">';
            echo '<div class="cp-ticket-card__head">';
            echo '<strong>' . htmlspecialchars((string) ($task['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>';
            echo '<span class="cp-badge">' . htmlspecialchars((string) ($task['priority_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
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
        echo '<div class="cp-ticket-dropzone-hint' . ($tasks !== [] ? ' cp-ticket-dropzone-hint--hidden' : '') . '">Ticket hier ablegen</div>';
        echo '</article>';
    }
    echo '</div>';
    return (string) ob_get_clean();
};

$renderWidgetPreview = static function (array $widget): string {
    $payload = (array) ($widget['payload_data'] ?? []);
    $widgetType = (string) ($widget['widget_type'] ?? 'text');
    ob_start();

    if ($widgetType === 'text') {
        echo '<p>' . nl2br(htmlspecialchars((string) ($widget['content'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
    } elseif ($widgetType === 'checklist') {
        echo '<ul class="cp-checklist">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $done = !empty($item['done']) ? ' done' : '';
            echo '<li class="' . $done . '">' . $label . '</li>';
        }
        echo '</ul>';
    } elseif ($widgetType === 'links') {
        echo '<ul class="cp-link-list">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
            echo '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a></li>';
        }
        echo '</ul>';
    } elseif ($widgetType === 'stats') {
        echo '<div class="cp-stats-grid">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            echo '<div class="cp-stat-card">';
            echo '<strong>' . htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>';
            echo '<span>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<ul class="cp-timeline">';
        foreach ((array) ($payload['items'] ?? []) as $item) {
            $date = htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            echo '<li><strong>' . $date . '</strong><span>' . $label . '</span></li>';
        }
        echo '</ul>';
    }

    return (string) ob_get_clean();
};

$boardColumnOptions = [];
foreach (($projectBoards ?? []) as $board) {
    $payload = (array) ($board['payload_data'] ?? []);
    $groups = $collectBoardGroups($payload);
    if ($groups === []) {
        continue;
    }

    $columns = [];
    foreach ($groups as $group) {
        $columnKey = (string) ($group['key'] ?? '');
        $columnTitle = (string) ($group['title'] ?? 'Block');
        if ($columnKey === '') {
            continue;
        }

        $columns[] = [
            'key' => $columnKey,
            'title' => $columnTitle,
        ];
    }

    if ($columns !== []) {
        $boardColumnOptions[] = [
            'id' => (int) ($board['id'] ?? 0),
            'title' => (string) ($board['title'] ?? 'Board'),
            'columns' => $columns,
        ];
    }
}


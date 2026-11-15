<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gemeinsame Ausgabe-Bausteine für Board- und Widget-Darstellungen (Admin-Vorschau, Member, Public, Shortcode).
 * Alle Werte werden hier escaped; Markup enthält keine Inline-Styles oder Event-Handler.
 */
final class CMS_Projects_View
{
    private const GROUP_KEYS = ['columns', 'lanes', 'clusters', 'stages', 'milestones'];

    /** @var array<string,bool> bereits ausgegebene Stylesheets je Request */
    private static array $printedStyles = [];

    public static function esc(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
    }

    /** Liefert die Labels je Sprache für Frontend-Ausgaben. */
    public static function labels(string $lang = 'de'): array
    {
        $de = [
            'block' => 'Block',
            'wip_reached' => 'WIP-Limit erreicht',
            'wip_exceeded' => 'WIP-Limit überschritten',
            'drop_hint' => 'Ticket hier ablegen',
            'overdue' => 'überfällig',
            'empty_column' => 'Keine Tickets',
        ];
        $en = [
            'block' => 'Block',
            'wip_reached' => 'WIP limit reached',
            'wip_exceeded' => 'WIP limit exceeded',
            'drop_hint' => 'Drop ticket here',
            'overdue' => 'overdue',
            'empty_column' => 'No tickets',
        ];

        return $lang === 'en' ? $en : $de;
    }

    /**
     * CSS-Datei des Plugins einmal je Request ausgeben (z. B. für Shortcodes auf beliebigen Seiten).
     */
    public static function stylesheetTag(string $file = 'assets/css/style.css'): string
    {
        if (isset(self::$printedStyles[$file])) {
            return '';
        }

        $path = CMS_PROJECTS_PLUGIN_DIR . $file;
        if (!is_file($path)) {
            return '';
        }

        self::$printedStyles[$file] = true;
        return '<link rel="stylesheet" href="' . self::esc(CMS_PROJECTS_PLUGIN_URL . $file . '?v=' . CMS_PROJECTS_VERSION . '-' . (int) filemtime($path)) . '">' . "\n";
    }

    public static function markStylesheetPrinted(string $file = 'assets/css/style.css'): void
    {
        self::$printedStyles[$file] = true;
    }

    /**
     * Nonce-geschützter Style-Block mit den Akzentfarben der Projekte (Klassen `cp-accent-<id>`).
     * Farbwerte sind serverseitig auf #rrggbb validiert.
     */
    public static function accentStyleBlock(array $projects): string
    {
        $rules = [];
        foreach ($projects as $project) {
            if (!is_array($project)) {
                continue;
            }

            $id = (int) ($project['id'] ?? 0);
            $color = strtolower((string) ($project['accent_color'] ?? ''));
            if ($id > 0 && preg_match('/^#[0-9a-f]{6}$/', $color) === 1) {
                $rules[$id] = '.cp-accent-' . $id . '{--cp-project-accent:' . $color . ';}';
            }
        }

        if ($rules === []) {
            return '';
        }

        $nonce = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
        return '<style' . $nonce . '>' . implode('', $rules) . '</style>';
    }

    public static function accentClass(array $project): string
    {
        $id = (int) ($project['id'] ?? 0);
        return $id > 0 ? 'cp-accent-' . $id : '';
    }

    /** @return list<array<string,mixed>> */
    public static function boardGroups(array $board): array
    {
        $payload = (array) ($board['payload_data'] ?? []);
        foreach (self::GROUP_KEYS as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param array{draggable?:bool,show_description?:bool,lang?:string} $options
     */
    public static function renderBoard(array $board, array $options = []): string
    {
        $draggable = !empty($options['draggable']);
        $labels = self::labels((string) ($options['lang'] ?? 'de'));
        $boardId = (int) ($board['id'] ?? 0);

        $html = '<article class="cp-board-card">';
        $html .= '<div class="cp-board-card__head"><strong>' . self::esc($board['title'] ?? '') . '</strong>'
            . '<span class="cp-badge">' . self::esc($board['board_label'] ?? 'Board') . '</span></div>';
        if (!empty($options['show_description']) && trim((string) ($board['description'] ?? '')) !== '') {
            $html .= '<p class="cp-board-card__text">' . self::esc($board['description'] ?? '') . '</p>';
        }

        $html .= '<div class="cp-board-columns">';
        foreach (self::boardGroups($board) as $group) {
            $tasks = array_values(array_filter((array) ($group['tasks'] ?? []), 'is_array'));
            $wipLimit = max(0, (int) ($group['wip_limit'] ?? 0));
            $wipCount = max(0, (int) ($group['wip_count'] ?? count($tasks)));

            $html .= '<div class="cp-board-column' . ($draggable ? ' cp-board-column--dropzone' : '') . '"'
                . ($draggable ? ' data-drop-board-id="' . $boardId . '" data-drop-column-key="' . self::esc($group['key'] ?? '') . '"' : '') . '>';
            $html .= '<h4>' . self::esc(($group['title'] ?? '') !== '' ? $group['title'] : $labels['block']) . '</h4>';

            if ($wipLimit > 0) {
                $html .= '<div class="cp-ticket-meta"><span class="cp-badge">WIP ' . $wipCount . '/' . $wipLimit . '</span>';
                if (!empty($group['wip_over_limit'])) {
                    $html .= '<span class="cp-badge cp-badge--danger">' . self::esc($labels['wip_exceeded']) . '</span>';
                } elseif (!empty($group['wip_limit_reached'])) {
                    $html .= '<span class="cp-badge cp-badge--warning">' . self::esc($labels['wip_reached']) . '</span>';
                }
                $html .= '</div>';
            }

            $items = (array) ($group['items'] ?? []);
            if ($items !== []) {
                $html .= '<ul class="cp-board-column__items">';
                foreach ($items as $item) {
                    $html .= '<li>' . self::esc($item) . '</li>';
                }
                $html .= '</ul>';
            }

            if ($tasks !== [] || $draggable) {
                $html .= '<div class="cp-ticket-stack"' . ($draggable ? ' data-ticket-stack' : '') . '>';
                foreach ($tasks as $task) {
                    $html .= self::renderTaskCard($task, $draggable, $labels);
                }
                $html .= '</div>';
            }

            if ($draggable) {
                $html .= '<div class="cp-ticket-dropzone-hint' . ($tasks !== [] ? ' cp-ticket-dropzone-hint--hidden' : '') . '">' . self::esc($labels['drop_hint']) . '</div>';
            }

            $html .= '</div>';
        }

        return $html . '</div></article>';
    }

    public static function renderTaskCard(array $task, bool $draggable, array $labels): string
    {
        $classes = 'cp-ticket-card cp-ticket-card--' . self::esc($task['priority'] ?? 'medium');
        if ($draggable) {
            $classes .= ' cp-ticket-card--draggable';
        }

        $html = '<article class="' . $classes . '"' . ($draggable ? ' draggable="true" data-task-id="' . (int) ($task['id'] ?? 0) . '"' : '') . '>';
        $html .= '<div class="cp-ticket-card__head"><strong>' . self::esc($task['title'] ?? '') . '</strong>'
            . '<span class="cp-badge">' . self::esc($task['priority_label'] ?? '') . '</span></div>';
        if (trim((string) ($task['description'] ?? '')) !== '') {
            $html .= '<p>' . nl2br(self::esc($task['description'] ?? '')) . '</p>';
        }

        $meta = [];
        if (trim((string) ($task['assignee_name'] ?? '')) !== '') {
            $meta[] = '<span>' . self::esc($task['assignee_name']) . '</span>';
        }
        if (trim((string) ($task['due_date'] ?? '')) !== '') {
            $meta[] = '<span' . (!empty($task['is_overdue']) ? ' class="cp-text-danger"' : '') . '>' . self::esc($task['due_date'])
                . (!empty($task['is_overdue']) ? ' (' . self::esc($labels['overdue'] ?? 'überfällig') . ')' : '') . '</span>';
        }
        if ($meta !== []) {
            $html .= '<div class="cp-ticket-meta">' . implode('', $meta) . '</div>';
        }

        return $html . '</article>';
    }

    public static function renderWidget(array $widget): string
    {
        $payload = (array) ($widget['payload_data'] ?? []);
        $items = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));
        $type = (string) ($widget['widget_type'] ?? 'text');

        $html = '<article class="cp-widget-card">';
        $html .= '<div class="cp-widget-card__head"><strong>' . self::esc($widget['title'] ?? '') . '</strong>'
            . '<span class="cp-badge">' . self::esc($widget['widget_label'] ?? 'Widget') . '</span></div>';

        if (trim((string) ($widget['content'] ?? '')) !== '') {
            $html .= '<p>' . nl2br(self::esc($widget['content'] ?? '')) . '</p>';
        }

        if ($type === 'checklist') {
            $html .= '<ul class="cp-checklist">';
            foreach ($items as $item) {
                $html .= '<li' . (!empty($item['done']) ? ' class="done"' : '') . '>' . self::esc($item['label'] ?? '') . '</li>';
            }
            $html .= '</ul>';
        } elseif ($type === 'links') {
            $html .= '<ul class="cp-link-list">';
            foreach ($items as $item) {
                $url = (string) ($item['url'] ?? '');
                if (preg_match('#^https?://#i', $url) !== 1) {
                    continue;
                }
                $html .= '<li><a href="' . self::esc($url) . '" target="_blank" rel="noopener noreferrer">' . self::esc($item['label'] ?? '') . '</a></li>';
            }
            $html .= '</ul>';
        } elseif ($type === 'stats') {
            $html .= '<div class="cp-stats-grid">';
            foreach ($items as $item) {
                $html .= '<div class="cp-stat-card"><strong>' . self::esc($item['value'] ?? '') . '</strong><span>' . self::esc($item['label'] ?? '') . '</span></div>';
            }
            $html .= '</div>';
        } elseif ($type === 'timeline') {
            $html .= '<ul class="cp-timeline">';
            foreach ($items as $item) {
                $html .= '<li><strong>' . self::esc($item['date'] ?? '') . '</strong><span>' . self::esc($item['label'] ?? '') . '</span></li>';
            }
            $html .= '</ul>';
        }

        return $html . '</article>';
    }
}

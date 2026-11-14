<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Projects_Shortcode
{
    private static bool $stylesLoaded = false;

    public static function markStylesLoaded(): void
    {
        self::$stylesLoaded = true;
    }

    /** Stylesheet einmalig mit der ersten Shortcode-Ausgabe liefern (Shortcodes stehen auf beliebigen Seiten). */
    private function stylesheetOnce(): string
    {
        if (self::$stylesLoaded) {
            return '';
        }
        self::$stylesLoaded = true;
        $cssFile = CMS_PROJECTS_PLUGIN_DIR . 'assets/css/style.css';

        return is_file($cssFile)
            ? '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROJECTS_PLUGIN_URL . 'assets/css/style.css?v=' . (string) filemtime($cssFile), ENT_QUOTES, 'UTF-8') . '">'
            : '';
    }

    public function __construct(private readonly CMS_Projects_Service $service)
    {
        if (class_exists('CMS\\Hooks')) {
            // Aktueller Core rendert Seiten/Beiträge über content_render; cms_content bleibt für ältere Themes.
            \CMS\Hooks::addFilter('content_render', [$this, 'processContent'], 20);
            \CMS\Hooks::addFilter('cms_content', [$this, 'processContent'], 20);
        }
    }

    public function processContent(mixed $content): mixed
    {
        if (!is_string($content) || !str_contains($content, '[cms_projects')) {
            return $content;
        }

        $content = preg_replace_callback('/\[cms_projects(?=[\s\]])([^\]]*)\]/', function (array $matches): string {
            $atts = $this->parseAttributes((string) ($matches[1] ?? ''));
            return $this->renderDashboard($atts);
        }, $content) ?? $content;

        return preg_replace_callback('/\[cms_projects_widget([^\]]*)\]/', function (array $matches): string {
            $atts = $this->parseAttributes((string) ($matches[1] ?? ''));
            return $this->renderWidgets($atts);
        }, $content) ?? $content;
    }

    public function renderDashboard(array $atts = []): string
    {
        $atts = array_merge([
            'project' => '',
            'scope' => 'public',
        ], $atts);

        $project = $this->service->findProjectBySlug((string) ($atts['project'] ?? ''));
        if ($project === null) {
            return '';
        }

        $scope = (string) ($atts['scope'] ?? 'public');
        if (!in_array($scope, ['public', 'member'], true)) {
            $scope = 'public';
        }

        if ($scope === 'public' && ($project['visibility'] ?? '') !== 'public') {
            return '';
        }

        // Member-Inhalte nur für angemeldete Besucher – auch wenn der Shortcode auf einer öffentlichen Seite steht.
        if ($scope === 'member' && (($project['visibility'] ?? '') === 'private' || !$this->isLoggedIn())) {
            return '';
        }

        $dashboard = $this->service->getProjectDashboardPayload((int) ($project['id'] ?? 0), $scope);
        if ($dashboard === null) {
            return '';
        }

        ob_start();
        echo $this->stylesheetOnce();
        echo '<section class="cp-dashboard-shell">';
        echo '<div class="cp-dashboard-main">';
        echo '<article class="cp-project-detail-card">';
        echo CMS_Projects_Service::accentSwatch($project);
        echo '<h2>' . htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p>' . nl2br(htmlspecialchars((string) ($project['summary'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
        echo '</article>';
        echo '<div class="cp-board-stack">';
        foreach ((array) ($dashboard['boards'] ?? []) as $board) {
            $this->renderBoard($board);
        }
        echo '</div>';
        echo '</div>';
        echo '<aside class="cp-dashboard-side">';
        foreach ((array) ($dashboard['widgets'] ?? []) as $widget) {
            $this->renderWidget($widget);
        }
        echo '</aside>';
        echo '</section>';
        return (string) ob_get_clean();
    }

    public function renderWidgets(array $atts = []): string
    {
        $atts = array_merge([
            'project' => '',
            'scope' => 'public',
            'type' => '',
        ], $atts);

        $project = $this->service->findProjectBySlug((string) ($atts['project'] ?? ''));
        if ($project === null) {
            return '';
        }

        $scope = (string) ($atts['scope'] ?? 'public');
        if (!in_array($scope, ['public', 'member'], true)) {
            $scope = 'public';
        }

        if ($scope === 'public' && ($project['visibility'] ?? '') !== 'public') {
            return '';
        }

        // Member-Inhalte nur für angemeldete Besucher – auch wenn der Shortcode auf einer öffentlichen Seite steht.
        if ($scope === 'member' && (($project['visibility'] ?? '') === 'private' || !$this->isLoggedIn())) {
            return '';
        }

        $allowedStatuses = $scope === 'public' ? ['active'] : ['active', 'paused'];
        if (!in_array((string) ($project['status'] ?? ''), $allowedStatuses, true)) {
            return '';
        }

        $widgets = $this->service->getProjectWidgets((int) ($project['id'] ?? 0), $scope);
        $requestedType = strtolower(trim((string) ($atts['type'] ?? '')));
        if ($requestedType !== '' && !in_array($requestedType, ['text', 'checklist', 'links', 'stats', 'timeline'], true)) {
            return '';
        }

        if ($requestedType !== '') {
            $widgets = array_values(array_filter($widgets, static function (array $widget) use ($requestedType): bool {
                return strtolower((string) ($widget['widget_type'] ?? '')) === $requestedType;
            }));
        }

        if ($widgets === []) {
            return '';
        }

        ob_start();
        echo $this->stylesheetOnce();
        echo '<div class="cp-widget-grid">';
        foreach ($widgets as $widget) {
            $this->renderWidget($widget);
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    private function parseAttributes(string $attributeString): array
    {
        $parsed = [];
        preg_match_all('/(\w+)\s*=\s*["\']?([^"\'>\s]*)["\']?/', $attributeString, $pairs, PREG_SET_ORDER);
        foreach ($pairs as $pair) {
            $parsed[(string) ($pair[1] ?? '')] = (string) ($pair[2] ?? '');
        }
        return $parsed;
    }

    private function isLoggedIn(): bool
    {
        return class_exists('CMS\\Auth') && \CMS\Auth::isLoggedIn();
    }

    private function renderBoard(array $board): void
    {
        $payload = (array) ($board['payload_data'] ?? []);
        $groups = [];
        foreach (['columns', 'lanes', 'clusters', 'stages', 'milestones'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $groups = $payload[$key];
                break;
            }
        }

        echo '<article class="cp-board-card">';
        echo '<div class="cp-board-card__head"><strong>' . htmlspecialchars((string) ($board['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($board['board_label'] ?? 'Board'), ENT_QUOTES, 'UTF-8') . '</span></div>';
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
                    echo '<span class="cp-badge">WIP limit exceeded</span>';
                } elseif ($wipReached) {
                    echo '<span class="cp-badge">WIP limit reached</span>';
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
    }

    private function renderWidget(array $widget): void
    {
        $payload = (array) ($widget['payload_data'] ?? []);
        $type = (string) ($widget['widget_type'] ?? 'text');

        echo '<article class="cp-widget-card">';
        echo '<div class="cp-widget-card__head"><strong>' . htmlspecialchars((string) ($widget['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string) ($widget['widget_label'] ?? 'Widget'), ENT_QUOTES, 'UTF-8') . '</span></div>';

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
    }
}

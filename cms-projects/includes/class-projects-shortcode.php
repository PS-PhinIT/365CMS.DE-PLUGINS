<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content-Tags [cms_projects project="slug" scope="public|member"] und
 * [cms_projects_widget project="slug" scope="public|member" type="stats"].
 *
 * scope="member" wird nur für angemeldete Personen ausgegeben.
 */
final class CMS_Projects_Shortcode
{
    private const WIDGET_TYPES = ['text', 'checklist', 'links', 'stats', 'timeline'];

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

        try {
            $content = preg_replace_callback('/\[cms_projects(?=[\s\]])([^\]]*)\]/', function (array $matches): string {
                return $this->renderDashboard($this->parseAttributes((string) ($matches[1] ?? '')));
            }, $content) ?? $content;

            return preg_replace_callback('/\[cms_projects_widget(?=[\s\]])([^\]]*)\]/', function (array $matches): string {
                return $this->renderWidgets($this->parseAttributes((string) ($matches[1] ?? '')));
            }, $content) ?? $content;
        } catch (\Throwable $e) {
            error_log('[cms-projects] shortcode rendering failed: ' . $e->getMessage());
            return $content;
        }
    }

    public function renderDashboard(array $atts = []): string
    {
        [$project, $scope] = $this->resolveProject($atts);
        if ($project === null) {
            return '';
        }

        $dashboard = $this->service->getProjectDashboardPayload((int) $project['id'], $scope);
        if ($dashboard === null) {
            return '';
        }

        $html = CMS_Projects_View::stylesheetTag() . CMS_Projects_View::accentStyleBlock([$project]);
        $html .= '<section class="cp-shortcode"><div class="cp-dashboard-shell"><div class="cp-dashboard-main">';
        $html .= '<article class="cp-project-detail-card ' . CMS_Projects_View::esc(CMS_Projects_View::accentClass($project)) . '">'
            . '<span class="cp-project-color" aria-hidden="true"></span>'
            . '<h2>' . CMS_Projects_View::esc($project['name']) . '</h2>'
            . ($project['summary'] !== '' ? '<p>' . nl2br(CMS_Projects_View::esc($project['summary'])) . '</p>' : '')
            . '</article>';
        $html .= '<div class="cp-board-stack">';
        foreach ((array) $dashboard['boards'] as $board) {
            $html .= CMS_Projects_View::renderBoard($board, ['show_description' => true]);
        }
        $html .= '</div></div><aside class="cp-dashboard-side">';
        foreach ((array) $dashboard['widgets'] as $widget) {
            $html .= CMS_Projects_View::renderWidget($widget);
        }

        return $html . '</aside></div></section>';
    }

    public function renderWidgets(array $atts = []): string
    {
        [$project, $scope] = $this->resolveProject($atts);
        if ($project === null) {
            return '';
        }

        $requestedType = strtolower(trim((string) ($atts['type'] ?? '')));
        if ($requestedType !== '' && !in_array($requestedType, self::WIDGET_TYPES, true)) {
            return '';
        }

        $widgets = $this->service->getProjectWidgets((int) $project['id'], $scope);
        if ($requestedType !== '') {
            $widgets = array_values(array_filter($widgets, static fn (array $widget): bool => (string) ($widget['widget_type'] ?? '') === $requestedType));
        }

        if ($widgets === []) {
            return '';
        }

        $html = CMS_Projects_View::stylesheetTag() . '<div class="cp-shortcode"><div class="cp-widget-grid">';
        foreach ($widgets as $widget) {
            $html .= CMS_Projects_View::renderWidget($widget);
        }

        return $html . '</div></div>';
    }

    /** @return array{0:array<string,mixed>|null,1:string} */
    private function resolveProject(array $atts): array
    {
        $scope = (string) ($atts['scope'] ?? 'public');
        if (!in_array($scope, ['public', 'member'], true)) {
            $scope = 'public';
        }

        if ($scope === 'member' && !(class_exists('CMS\\Auth') && \CMS\Auth::isLoggedIn())) {
            return [null, $scope];
        }

        return [$this->service->findAccessibleProjectBySlug((string) ($atts['project'] ?? ''), $scope), $scope];
    }

    private function parseAttributes(string $attributeString): array
    {
        $parsed = [];
        preg_match_all('/(\w+)\s*=\s*["\']?([^"\'>\s\]]*)["\']?/', $attributeString, $pairs, PREG_SET_ORDER);
        foreach ($pairs as $pair) {
            $parsed[strtolower((string) ($pair[1] ?? ''))] = (string) ($pair[2] ?? '');
        }

        return $parsed;
    }
}

<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Öffentliche Projektseiten: /projects, /projects/:slug (+ /en/…).
 */
final class CMS_Projects_Public
{
    private static bool $renderingPublicProjectsPage = false;

    public function __construct(private readonly CMS_Projects_Service $service)
    {
    }

    public static function isRenderingPublicProjectsPage(): bool
    {
        return self::$renderingPublicProjectsPage;
    }

    public function registerRoutes(object $router): void
    {
        if (!method_exists($router, 'addRoute')) {
            return;
        }

        $router->addRoute('GET', '/projects', [$this, 'archivePage']);
        $router->addRoute('GET', '/projects/:slug', [$this, 'singlePage']);
        $router->addRoute('GET', '/en/projects', [$this, 'archivePage']);
        $router->addRoute('GET', '/en/projects/:slug', [$this, 'singlePage']);
    }

    public function archivePage(): void
    {
        $lang = $this->language();
        $projects = $this->service->getPublicProjects();
        $this->setMeta(
            $lang === 'en' ? 'Public projects' : 'Öffentliche Projekte',
            $lang === 'en' ? 'Project dashboards, status updates and boards available publicly.' : 'Projekt-Dashboards, Statusinformationen und Boards im öffentlichen Zugriff.',
            $this->localizedPath('projects', $lang),
            $projects !== []
        );
        $this->renderTemplate($projects, null, null, 'archive');
    }

    public function singlePage(mixed $slug = ''): void
    {
        $slug = is_string($slug) && trim($slug) !== '' ? $slug : (string) ($_GET['slug'] ?? '');
        $slug = substr((string) (preg_replace('/[^a-z0-9\-_]/i', '', $slug) ?? ''), 0, 120);
        $project = $slug !== '' ? $this->service->findAccessibleProjectBySlug($slug, 'public') : null;
        $dashboard = $project !== null ? $this->service->getProjectDashboardPayload((int) $project['id'], 'public') : null;

        if ($project === null || $dashboard === null) {
            $this->renderNotFound();
            return;
        }

        $lang = $this->language();
        $description = trim((string) ($project['summary'] !== '' ? $project['summary'] : $project['description']));
        $this->setMeta(
            (string) $project['name'],
            function_exists('mb_substr') ? mb_substr($description, 0, 160) : substr($description, 0, 160),
            $this->localizedPath('projects/' . rawurlencode((string) $project['slug']), $lang),
            true
        );

        $this->renderTemplate($this->service->getPublicProjects(), $project, $dashboard, 'single');
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        $this->setMeta($this->language() === 'en' ? 'Project not found' : 'Projekt nicht gefunden', '', '', false);
        $this->renderTemplate($this->service->getPublicProjects(), null, null, 'not_found');
    }

    private function renderTemplate(array $projects, ?array $currentProject, ?array $dashboard, string $view): void
    {
        self::$renderingPublicProjectsPage = true;
        $publicLang = $this->language();
        $statusLabels = $publicLang === 'en'
            ? ['active' => 'Active', 'paused' => 'Paused', 'draft' => 'Draft', 'archived' => 'Archived']
            : $this->service->getProjectStatuses();
        $theme = class_exists('CMS\\ThemeManager') ? \CMS\ThemeManager::instance() : null;

        try {
            if ($theme !== null) {
                $theme->getHeader();
            }

            include CMS_PROJECTS_PLUGIN_DIR . 'templates/public-projects.php';

            if ($theme !== null) {
                $theme->getFooter();
            }
        } finally {
            self::$renderingPublicProjectsPage = false;
        }
    }

    private function setMeta(string $title, string $description, string $canonicalPath, bool $index): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }

        try {
            $meta = [
                'title' => $title,
                'description' => $description,
                'robots_index' => $index,
                'robots_follow' => true,
                'og_type' => 'website',
            ];
            if ($canonicalPath !== '' && defined('SITE_URL')) {
                $meta['canonical_url'] = rtrim((string) SITE_URL, '/') . $canonicalPath;
            }
            \CMS\Services\SEOService::getInstance()->setRequestMeta($meta);
        } catch (\Throwable) {
            // SEO-Metadaten sind optional.
        }
    }

    private function language(): string
    {
        return function_exists('cms_plugin_public_language') && cms_plugin_public_language() === 'en' ? 'en' : 'de';
    }

    /** Pfad relativ zu SITE_URL (ohne Unterverzeichnis-Präfix, das SITE_URL bereits enthält). */
    private function localizedPath(string $relativePath, string $lang): string
    {
        return ($lang === 'en' ? '/en/' : '/') . ltrim($relativePath, '/');
    }
}

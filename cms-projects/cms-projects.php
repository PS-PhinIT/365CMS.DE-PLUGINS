<?php
/**
 * Plugin Name: CMS Projects
 * Plugin URI: https://365network.de/cms-projects
 * Description: Projektmanagement mit Projekt-Dashboards, Boards, Tickets und Widgets für Member- und Public-Bereiche.
 * Version: 4.0.0
 * Author: 365 Network
 * Author URI: https://365network.de
 * Requires CMS: 4.0.0
 * Requires at least: 4.0.0
 * Requires PHP: 8.4.1
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (defined('CMS_PROJECTS_BOOTSTRAPPED')) {
    return;
}

define('CMS_PROJECTS_BOOTSTRAPPED', true);
defined('CMS_PROJECTS_VERSION') || define('CMS_PROJECTS_VERSION', '4.0.0');
defined('CMS_PROJECTS_PLUGIN_DIR') || define('CMS_PROJECTS_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR);
defined('CMS_PROJECTS_PLUGIN_URL') || define('CMS_PROJECTS_PLUGIN_URL', '/plugins/cms-projects/');

require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-repository.php';
require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-service.php';
require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-admin.php';
require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-public.php';
require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-member-dashboard.php';
require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-shortcode.php';

final class CMS_Projects
{
    private static ?self $instance = null;
    private CMS_Projects_Service $service;
    private CMS_Projects_Admin $admin;
    private CMS_Projects_Public $public;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function service(): CMS_Projects_Service
    {
        return $this->service;
    }

    private function __construct()
    {
        $this->service = new CMS_Projects_Service(new CMS_Projects_Repository());
        $this->admin = new CMS_Projects_Admin($this->service);
        $this->public = new CMS_Projects_Public($this->service);
        new CMS_Projects_Member_Dashboard($this->service);
        new CMS_Projects_Shortcode($this->service);
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        \CMS\Hooks::addAction('cms_init', [$this, 'boot'], 10);
        \CMS\Hooks::addAction('register_routes', [$this->public, 'registerRoutes'], 10);
        \CMS\Hooks::addAction('cms_admin_menu', [$this->admin, 'registerPages'], 20);
        \CMS\Hooks::addAction('head', [$this, 'enqueuePublicStyles'], 10);
        \CMS\Hooks::addFilter('cms_sitemap_entries', [$this, 'sitemapEntries'], 10);
        \CMS\Hooks::addFilter('search_results', [$this, 'searchResults'], 10);
    }

    public function boot(): void
    {
        $this->service->boot();
        $this->registerRetentionRule();
        $this->registerAdminChecks();
    }

    /**
     * Audit-Einträge enthalten Benutzernamen → Löschfrist über System › Datenaufbewahrung.
     */
    private function registerRetentionRule(): void
    {
        if (!class_exists('CMS\\Services\\DataRetentionService') || !class_exists('CMS\\Services\\Retention\\RetentionRule')) {
            return;
        }

        $service = $this->service;
        try {
            \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                'cms_projects_audit_logs',
                'Projekte: Audit-Protokoll',
                'Änderungsprotokoll der Projektverwaltung (enthält Benutzernamen); Frist unter Projekte › Einstellungen.',
                static fn (): int => $service->getAuditRetentionDays(),
                static fn (): int => $service->countAuditLogsDue(),
                static fn (): int => $service->pruneAuditLogs(),
                '/admin/plugins/cms-projects/cms-projects-settings'
            ));
        } catch (\Throwable $e) {
            error_log('[cms-projects] Aufbewahrungsregel nicht registriert: ' . $e->getMessage());
        }
    }

    private function registerAdminChecks(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService')) {
            return;
        }

        $service = $this->service;
        \CMS\Services\AdminNoticeService::registerCheck('cms_projects_overdue', static function () use ($service): ?array {
            $overdue = $service->countOverdueTasks();
            if ($overdue <= 0) {
                return null;
            }

            return \CMS\Services\AdminNoticeService::makeNotice(
                'cms_projects_overdue',
                'info',
                'Überfällige Tickets',
                sprintf('%d aktive Projekt-Ticket(s) haben ihr Fälligkeitsdatum überschritten.', $overdue),
                '/admin/plugins/cms-projects/cms-projects',
                'Projekte öffnen'
            );
        });
    }

    /**
     * Öffentliche Projektseiten für plugins.xml.
     *
     * @return array<int,mixed>
     */
    public function sitemapEntries(mixed $entries): array
    {
        $entries = is_array($entries) ? $entries : [];
        $projects = $this->service->getPublicProjects();
        if ($projects === []) {
            return $entries;
        }

        $entries[] = ['url' => 'projects', 'lastmod' => (string) ($projects[0]['updated_at'] ?? ''), 'changefreq' => 'weekly', 'priority' => 0.5];
        foreach ($projects as $project) {
            $entries[] = [
                'url' => 'projects/' . rawurlencode((string) $project['slug']),
                'lastmod' => (string) ($project['updated_at'] ?? ''),
                'changefreq' => 'weekly',
                'priority' => 0.5,
            ];
        }

        return $entries;
    }

    /**
     * Öffentliche Projekte in der Seitensuche (/search), nur Treffer mit allen Suchwörtern.
     *
     * @return array<int,mixed>
     */
    public function searchResults(mixed $results, mixed $query = '', mixed $limit = 10, mixed $context = []): array
    {
        $results = is_array($results) ? $results : [];
        $context = is_array($context) ? $context : [];
        $type = (string) ($context['type'] ?? '');
        if ($type !== '' && $type !== 'projects') {
            return $results;
        }

        $words = preg_split('/\s+/u', trim((string) $query)) ?: [];
        foreach ($this->service->searchPublicProjects($words, max(1, min(20, (int) $limit))) as $project) {
            $results[] = [
                'title' => (string) $project['name'],
                'url' => '/projects/' . rawurlencode((string) $project['slug']),
                'excerpt' => mb_substr(trim((string) ($project['summary'] !== '' ? $project['summary'] : strip_tags((string) $project['description']))), 0, 240),
                '_type' => 'projects',
                '_type_label' => 'Projekte',
                'date' => (string) ($project['updated_at'] ?? ''),
            ];
        }

        return $results;
    }

    public function enqueuePublicStyles(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET'
            || !$this->isPublicProjectsRoute()
            || !CMS_Projects_Public::isRenderingPublicProjectsPage()) {
            return;
        }
        CMS_Projects_Shortcode::markStylesLoaded();

        $cssFile = CMS_PROJECTS_PLUGIN_DIR . 'assets/css/style.css';
        if (!is_file($cssFile)) {
            return;
        }

        echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROJECTS_PLUGIN_URL . 'assets/css/style.css?v=' . (string) filemtime($cssFile), ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    private function isPublicProjectsRoute(): bool
    {
        $path = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');

        return $path === '/projects' || str_starts_with($path, '/projects/')
            || $path === '/en/projects' || str_starts_with($path, '/en/projects/');
    }
}

/*
 * Lebenszyklus-Callbacks (PluginManager: <slug>_activate/_deactivate/_uninstall).
 */
if (!function_exists('cms_projects_activate')) {
    function cms_projects_activate(): void
    {
        CMS_Projects::instance()->service()->boot(true);
    }
}

if (!function_exists('cms_projects_deactivate')) {
    function cms_projects_deactivate(): void
    {
        // Daten bleiben erhalten.
    }
}

if (!function_exists('cms_projects_uninstall')) {
    function cms_projects_uninstall(): void
    {
        CMS_Projects::instance()->service()->uninstall();
    }
}

CMS_Projects::instance();

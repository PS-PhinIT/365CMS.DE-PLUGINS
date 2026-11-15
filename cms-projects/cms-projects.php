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
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CMS_PROJECTS_BOOTSTRAPPED')) {
    define('CMS_PROJECTS_BOOTSTRAPPED', true);
    defined('CMS_PROJECTS_VERSION') || define('CMS_PROJECTS_VERSION', '4.0.0');
    defined('CMS_PROJECTS_PLUGIN_DIR') || define('CMS_PROJECTS_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR);
    defined('CMS_PROJECTS_PLUGIN_URL') || define('CMS_PROJECTS_PLUGIN_URL', '/plugins/cms-projects/');

    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-settings.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-repository.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-service.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-view.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-admin.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-public.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-member-dashboard.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-shortcode.php';
    require_once CMS_PROJECTS_PLUGIN_DIR . 'includes/class-projects-integrations.php';

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

        private function __construct()
        {
            $this->service = new CMS_Projects_Service(new CMS_Projects_Repository());
            $this->admin = new CMS_Projects_Admin($this->service);
            $this->public = new CMS_Projects_Public($this->service);
            new CMS_Projects_Member_Dashboard($this->service);
            new CMS_Projects_Shortcode($this->service);
            $this->registerHooks();
        }

        public function service(): CMS_Projects_Service
        {
            return $this->service;
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
            (new CMS_Projects_Integrations($this->service))->register();
        }

        /** Migration nur bei geänderter Schema-Version (keine DDL bei jedem Request). */
        public function boot(): void
        {
            $this->service->boot();
        }

        /** Frontend-CSS nur auf den eigenen öffentlichen Routen. */
        public function enqueuePublicStyles(): void
        {
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET' || !CMS_Projects_Public::isRenderingPublicProjectsPage()) {
                return;
            }

            echo CMS_Projects_View::stylesheetTag();
        }
    }

    CMS_Projects::instance();
}

if (!function_exists('cms_projects_activate')) {
    /** Lebenszyklus: Tabellen idempotent anlegen, Indizes migrieren, Schema-Version speichern. */
    function cms_projects_activate(): void
    {
        if (class_exists('CMS_Projects')) {
            CMS_Projects::instance()->service()->install();
        }
    }
}

if (!function_exists('cms_projects_deactivate')) {
    /** Lebenszyklus: Daten bleiben erhalten; nur der Request-Cache der Systemhinweise wird geleert. */
    function cms_projects_deactivate(): void
    {
        if (class_exists('CMS\\Services\\AdminNoticeService')) {
            try {
                \CMS\Services\AdminNoticeService::getInstance()->clearCache();
            } catch (\Throwable) {
                // optional
            }
        }
    }
}

if (!function_exists('cms_projects_uninstall')) {
    /** Lebenszyklus: eigene Tabellen, Optionen und DSGVO-Exportdateien entfernen. */
    function cms_projects_uninstall(): void
    {
        if (!class_exists('CMS_Projects')) {
            return;
        }

        CMS_Projects::instance()->service()->uninstall();
        CMS_Projects_Integrations::removePrivacyExports();
    }
}

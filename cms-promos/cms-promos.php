<?php
/**
 * Plugin Name: CMS Promos
 * Plugin URI: https://365network.de/cms-promos
 * Description: Promo- und Kampagnenverwaltung für Banner, Teaserflächen, Platzierungen und klickbare Aktionsboxen im 365CMS.
 * Version: 4.0.0
 * Author: 365 Network
 * Author URI: https://365network.de
 * Requires CMS: 4.0.0
 * Requires at least: 4.0.0
 * Requires PHP: 8.4.1
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('CMS_PROMOS_VERSION', '4.0.0');
define('CMS_PROMOS_DB_VERSION', '1.2.0');
define('CMS_PROMOS_SLUG', 'cms-promos');
define('CMS_PROMOS_PLUGIN_DIR', dirname(__FILE__) . '/');
define('CMS_PROMOS_PLUGIN_URL', '/plugins/cms-promos/');

final class CMS_Promos
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void
    {
        $files = [
            'includes/class-sanitizer.php',
            'includes/class-installer.php',
            'includes/class-repository.php',
            'includes/class-public-controller.php',
            'includes/class-integrations.php',
            'admin/class-admin-menu.php',
            'admin/class-admin-pages.php',
        ];

        foreach ($files as $file) {
            $path = CMS_PROMOS_PLUGIN_DIR . $file;
            if (is_file($path)) {
                require_once $path;
                continue;
            }

            error_log('[cms-promos] Missing dependency file: ' . $file);
        }
    }

    private function init_hooks(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        \CMS\Hooks::addAction('cms_init', [$this, 'init_plugin'], 10);
        \CMS\Hooks::addAction('cms_init', [CMS_Promos_Integrations::class, 'register_core_services'], 20);
        \CMS\Hooks::addAction('cms_admin_menu', [CMS_Promos_Admin_Menu::class, 'register'], 10);
        \CMS\Hooks::addAction('register_routes', [CMS_Promos_Public_Controller::instance(), 'register_routes'], 10);
        \CMS\Hooks::addAction('head', [CMS_Promos_Public_Controller::instance(), 'enqueue_styles'], 20);
        \CMS\Hooks::addFilter('cms_sitemap_entries', [CMS_Promos_Integrations::class, 'sitemap_entries'], 20);
        \CMS\Hooks::addFilter('search_results', [CMS_Promos_Integrations::class, 'search_results'], 20);
    }

    /**
     * Migrationen laufen nur, wenn die gespeicherte Schema-Version nicht zum Code passt.
     */
    public function init_plugin(): void
    {
        try {
            CMS_Promos_Installer::maybe_install();
        } catch (\Throwable $e) {
            error_log('[cms-promos] Schema check failed: ' . $e->getMessage());
        }
    }
}

CMS_Promos::instance();

if (!function_exists('cms_promos_activate')) {
    /**
     * Lifecycle: vom PluginManager nach dem Laden der Bootstrap-Datei aufgerufen.
     */
    function cms_promos_activate(): void
    {
        if (class_exists('CMS_Promos_Installer')) {
            CMS_Promos_Installer::install();
        }
    }
}

if (!function_exists('cms_promos_deactivate')) {
    /**
     * Lifecycle: Daten bleiben erhalten; nur Laufzeit-Caches werden verworfen.
     */
    function cms_promos_deactivate(): void
    {
        if (class_exists('CMS\\Services\\AdminNoticeService')) {
            try {
                \CMS\Services\AdminNoticeService::getInstance()->clearCache();
            } catch (\Throwable) {
                // Systemhinweise werden beim nächsten Aufruf ohnehin neu berechnet.
            }
        }
    }
}

if (!function_exists('cms_promos_uninstall')) {
    /**
     * Lifecycle: entfernt ausschließlich die eigenen Tabellen und Optionen des Plugins.
     */
    function cms_promos_uninstall(): void
    {
        if (class_exists('CMS_Promos_Installer')) {
            CMS_Promos_Installer::uninstall();
        }
    }
}

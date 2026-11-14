<?php
/**
 * Plugin Name: CMS Beratung
 * Plugin URI:  https://365network.de/cms-beratung
 * Description: Spezialisierter 365CMS Landingpage Builder für Microsoft 365, Copilot, KI, Security, Compliance und IT Consulting Beratungsleistungen.
 * Version:     2.9.794
 * Author:      365 Network
 * Author URI:  https://365network.de
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

defined('CMS_BERATUNG_VERSION') || define('CMS_BERATUNG_VERSION', '2.9.794');
defined('CMS_BERATUNG_DB_VERSION') || define('CMS_BERATUNG_DB_VERSION', '3');
defined('CMS_BERATUNG_PLUGIN_DIR') || define('CMS_BERATUNG_PLUGIN_DIR', dirname(__FILE__) . '/');
defined('CMS_BERATUNG_PLUGIN_URL') || define('CMS_BERATUNG_PLUGIN_URL', '/plugins/cms-beratung/');

final class CMS_Beratung
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
        $sharedAdmin = dirname(rtrim(CMS_BERATUNG_PLUGIN_DIR, '/\\')) . '/shared/admin/plugin-admin-contract.php';
        if (is_file($sharedAdmin)) {
            require_once $sharedAdmin;
        }

        foreach ([
            'includes/class-installer.php',
            'includes/class-settings.php',
            'includes/class-storage.php',
            'includes/class-renderer.php',
            'includes/class-forms.php',
            'includes/class-seo.php',
            'includes/class-import-export.php',
            'includes/class-frontend.php',
            'admin/class-admin-pages.php',
            'admin/class-admin-menu.php',
        ] as $file) {
            $path = CMS_BERATUNG_PLUGIN_DIR . $file;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    private function init_hooks(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        \CMS\Hooks::addAction('cms_init', [$this, 'init_plugin'], 10);
        \CMS\Hooks::addAction('plugin_activated', [$this, 'on_activation'], 10);
        \CMS\Hooks::addAction('cms_admin_menu', [CMS_Beratung_Admin_Menu::class, 'register'], 10);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Admin_Pages::class, 'register_admin_routes'], 9);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Frontend::class, 'instance'], 10);
        \CMS\Hooks::addAction('head', [CMS_Beratung_Admin_Pages::class, 'enqueue_admin_assets_for_request'], 20);
    }

    public function init_plugin(): void
    {
        CMS_Beratung_Installer::maybe_install();
        CMS_Beratung_Frontend::instance();
    }

    public function on_activation(string $plugin): void
    {
        if ($plugin !== 'cms-beratung') {
            return;
        }

        CMS_Beratung_Installer::install();
        CMS_Beratung_Frontend::instance();
    }
}

CMS_Beratung::instance();

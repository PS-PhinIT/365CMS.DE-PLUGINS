<?php
/**
 * Plugin Name:       CMS Beratung
 * Plugin URI:        https://365network.de/cms-beratung
 * Description:       Spezialisierter 365CMS Landingpage Builder für Microsoft 365, Copilot, KI, Security, Compliance und IT Consulting Beratungsleistungen.
 * Version:           3.0.0
 * Author:            365 Network
 * Author URI:        https://365network.de
 * Requires CMS:      4.0.0
 * Requires at least: 4.0.0
 * Requires PHP:      8.4.1
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

defined('CMS_BERATUNG_VERSION') || define('CMS_BERATUNG_VERSION', '3.0.0');
defined('CMS_BERATUNG_DB_VERSION') || define('CMS_BERATUNG_DB_VERSION', '4');
defined('CMS_BERATUNG_PLUGIN_DIR') || define('CMS_BERATUNG_PLUGIN_DIR', __DIR__ . '/');
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
        self::load_dependencies();
        $this->init_hooks();
    }

    public static function load_dependencies(): void
    {
        foreach ([
            'includes/class-installer.php',
            'includes/class-settings.php',
            'includes/class-storage.php',
            'includes/class-renderer.php',
            'includes/class-forms.php',
            'includes/class-seo.php',
            'includes/class-import-export.php',
            'includes/class-integrations.php',
            'includes/class-frontend.php',
            'admin/class-admin-defaults.php',
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
        \CMS\Hooks::addAction('cms_admin_menu', [CMS_Beratung_Admin_Menu::class, 'register'], 10);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Admin_Pages::class, 'register_admin_routes'], 9);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Frontend::class, 'instance'], 10);
        CMS_Beratung_Integrations::register();
    }

    public function init_plugin(): void
    {
        try {
            CMS_Beratung_Installer::maybe_install();
        } catch (\Throwable $e) {
            error_log('CMS Beratung: Schema-Migration fehlgeschlagen: ' . $e->getMessage());
        }
        CMS_Beratung_Frontend::instance();
    }
}

CMS_Beratung::instance();

if (!function_exists('cms_beratung_activate')) {
    /** Aktivierung: Tabellen idempotent anlegen, Standardwerte ergänzen, Schema-Version speichern. */
    function cms_beratung_activate(): void
    {
        CMS_Beratung::load_dependencies();
        CMS_Beratung_Installer::install();
    }
}

if (!function_exists('cms_beratung_deactivate')) {
    /** Deaktivierung: Daten bleiben erhalten; nur der Hinweis-Cache wird verworfen. */
    function cms_beratung_deactivate(): void
    {
        CMS_Beratung::load_dependencies();
        CMS_Beratung_Installer::deactivate();
    }
}

if (!function_exists('cms_beratung_uninstall')) {
    /** Löschen: eigene Tabellen, Optionen und DSGVO-Exportdateien entfernen. */
    function cms_beratung_uninstall(): void
    {
        CMS_Beratung::load_dependencies();
        CMS_Beratung_Installer::uninstall();
    }
}

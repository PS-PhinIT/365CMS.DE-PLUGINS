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

defined('CMS_PROMOS_VERSION') || define('CMS_PROMOS_VERSION', '4.0.0');
defined('CMS_PROMOS_DB_VERSION') || define('CMS_PROMOS_DB_VERSION', '1.2.0');
defined('CMS_PROMOS_PLUGIN_DIR') || define('CMS_PROMOS_PLUGIN_DIR', __DIR__ . '/');
defined('CMS_PROMOS_PLUGIN_URL') || define('CMS_PROMOS_PLUGIN_URL', '/plugins/cms-promos/');

if (!class_exists('CMS_Promos', false)) {
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
                'includes/class-installer.php',
                'includes/class-repository.php',
                'includes/class-public-controller.php',
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
            \CMS\Hooks::addAction('cms_admin_menu', [CMS_Promos_Admin_Menu::class, 'register'], 10);
            \CMS\Hooks::addAction('register_routes', [CMS_Promos_Public_Controller::instance(), 'register_routes'], 10);
            \CMS\Hooks::addAction('head', [$this, 'enqueue_styles'], 20);
            \CMS\Hooks::addFilter('cms_sitemap_entries', [$this, 'sitemap_entries'], 10);
        }

        public function init_plugin(): void
        {
            // Schema nur bei geänderter DB-Version anlegen/migrieren (statt bei jedem Request).
            CMS_Promos_Installer::maybe_install();
            $this->register_admin_checks();
        }

        /**
         * Systemhinweis im Admin-Sidebar-Footer: aktive Promos mit abgelaufenem Enddatum.
         */
        private function register_admin_checks(): void
        {
            if (!class_exists('CMS\\Services\\AdminNoticeService')) {
                return;
            }

            \CMS\Services\AdminNoticeService::registerCheck('cms_promos_expired', static function (): ?array {
                $expired = CMS_Promos_Repository::instance()->count_expired_active();
                if ($expired <= 0) {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms_promos_expired',
                    'info',
                    'Promos abgelaufen',
                    sprintf('%d aktive Promo(s) haben ihr Enddatum überschritten und werden nicht mehr ausgespielt.', $expired),
                    '/admin/plugins/promos-dashboard/promos-items?status=expired',
                    'Promos prüfen'
                );
            });
        }

        /**
         * Öffentliche Archivseiten der Promos für plugins.xml melden.
         *
         * @param mixed $entries
         * @return array<int,mixed>
         */
        public function sitemap_entries(mixed $entries): array
        {
            $entries = is_array($entries) ? $entries : [];
            $repository = CMS_Promos_Repository::instance();
            if ($repository->count_live_promos() <= 0) {
                return $entries;
            }

            $lastmod = $repository->get_last_modified();
            $entries[] = ['url' => 'promos', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => 0.4];
            $entries[] = ['url' => 'en/promos', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => 0.3];

            return $entries;
        }

        /**
         * Öffentliches CSS nur auf den Promo-Routen oder wenn eine Theme-Hook-Fläche Promos ausspielt.
         */
        public function enqueue_styles(): void
        {
            if ($this->is_admin_request()) {
                return;
            }

            if (!$this->is_promos_public_route() && !CMS_Promos_Repository::instance()->has_live_hook_placements()) {
                return;
            }

            $css = CMS_PROMOS_PLUGIN_DIR . 'assets/css/promos-public.css';
            if (!is_file($css)) {
                return;
            }

            echo '<link rel="stylesheet" href="'
                . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/css/promos-public.css?v=' . (string) filemtime($css), ENT_QUOTES, 'UTF-8')
                . '">' . "\n";
        }

        private function is_promos_public_route(): bool
        {
            if (function_exists('cms_plugin_public_path_without_lang')) {
                $pathWithoutLang = cms_plugin_public_path_without_lang();
                return $pathWithoutLang === 'promos' || str_starts_with($pathWithoutLang, 'promos/');
            }

            $path = trim(strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '')), '/');
            if (str_starts_with($path, 'en/')) {
                $path = substr($path, 3);
            }

            return $path === 'promos' || str_starts_with($path, 'promos/');
        }

        private function is_admin_request(): bool
        {
            $path = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');

            return $path === '/admin' || str_starts_with($path, '/admin/');
        }
    }
}

/*
 * Lebenszyklus-Callbacks (PluginManager ruft <slug>_activate/_deactivate/_uninstall auf).
 */
if (!function_exists('cms_promos_activate')) {
    function cms_promos_activate(): void
    {
        CMS_Promos_Installer::install();
    }
}

if (!function_exists('cms_promos_deactivate')) {
    function cms_promos_deactivate(): void
    {
        // Daten bleiben erhalten; nur Laufzeit-Caches leeren.
        if (class_exists('CMS\\Services\\AdminNoticeService')) {
            \CMS\Services\AdminNoticeService::getInstance()->clearCache();
        }
    }
}

if (!function_exists('cms_promos_uninstall')) {
    function cms_promos_uninstall(): void
    {
        CMS_Promos_Installer::uninstall();
    }
}

CMS_Promos::instance();

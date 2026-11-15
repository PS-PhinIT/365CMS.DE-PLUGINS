<?php
/**
 * Plugin Name: CMS Beratung
 * Plugin URI:  https://365network.de/cms-beratung
 * Description: Spezialisierter 365CMS Landingpage Builder für Microsoft 365, Copilot, KI, Security, Compliance und IT Consulting Beratungsleistungen.
 * Version:     3.0.0
 * Author:      365 Network
 * Author URI:  https://365network.de
 * Requires CMS: 4.0.0
 * Requires at least: 4.0.0
 * Requires PHP: 8.4.1
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

defined('CMS_BERATUNG_VERSION') || define('CMS_BERATUNG_VERSION', '3.0.0');
defined('CMS_BERATUNG_DB_VERSION') || define('CMS_BERATUNG_DB_VERSION', '3');
defined('CMS_BERATUNG_PLUGIN_DIR') || define('CMS_BERATUNG_PLUGIN_DIR', __DIR__ . '/');
defined('CMS_BERATUNG_PLUGIN_URL') || define('CMS_BERATUNG_PLUGIN_URL', '/plugins/cms-beratung/');

if (class_exists('CMS_Beratung', false)) {
    return;
}

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
        \CMS\Hooks::addAction('cms_admin_menu', [CMS_Beratung_Admin_Menu::class, 'register'], 10);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Admin_Pages::class, 'register_admin_routes'], 9);
        \CMS\Hooks::addAction('register_routes', [CMS_Beratung_Frontend::class, 'instance'], 10);
        \CMS\Hooks::addFilter('cms_sitemap_entries', [$this, 'sitemap_entries'], 10);
        \CMS\Hooks::addFilter('search_results', [$this, 'search_results'], 10);
    }

    public function init_plugin(): void
    {
        CMS_Beratung_Installer::maybe_install();
        CMS_Beratung_Frontend::instance();
        $this->register_core_integrations();
    }

    /**
     * Löschfrist für Formular-Anfragen (personenbezogen) und Systemhinweis für neue Anfragen.
     */
    private function register_core_integrations(): void
    {
        $retentionDays = static function (): int {
            $settings = CMS_Beratung_Settings::all();
            return ($settings['auto_delete_submissions_enabled'] ?? '0') === '1' ? max(1, (int) ($settings['retention_days'] ?? 180)) : 0;
        };

        if (class_exists('CMS\\Services\\DataRetentionService') && class_exists('CMS\\Services\\Retention\\RetentionRule')) {
            try {
                \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                    'cms_beratung_submissions',
                    'Beratung: Formular-Anfragen',
                    'Löscht Anfragen aus Landingpage-Formularen nach der Frist (Beratung › Globale Einstellungen › Datenschutz).',
                    $retentionDays,
                    static fn (): int => CMS_Beratung_Storage::instance()->count_submissions_older_than($retentionDays()),
                    static fn (): int => $retentionDays() > 0 ? CMS_Beratung_Storage::instance()->purge_old_submissions($retentionDays()) : 0,
                    '/admin/plugins/cms-beratung/cms-beratung-settings'
                ));
            } catch (\Throwable $e) {
                error_log('[cms-beratung] Aufbewahrungsregel nicht registriert: ' . $e->getMessage());
            }
        }

        if (class_exists('CMS\\Services\\AdminNoticeService')) {
            \CMS\Services\AdminNoticeService::registerCheck('cms_beratung_new_submissions', static function (): ?array {
                $new = (int) (CMS_Beratung_Storage::instance()->submission_counts()['new'] ?? 0);
                if ($new <= 0) {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms_beratung_new_submissions',
                    'info',
                    'Neue Beratungsanfragen',
                    sprintf('%d neue Anfrage(n) aus Landingpage-Formularen.', $new),
                    '/admin/plugins/cms-beratung/cms-beratung-submissions?status=new',
                    'Anfragen öffnen'
                );
            });
        }
    }

    /**
     * Veröffentlichte, indexierbare Landingpages für plugins.xml.
     *
     * @return array<int,mixed>
     */
    public function sitemap_entries(mixed $entries): array
    {
        $entries = is_array($entries) ? $entries : [];
        foreach (CMS_Beratung_Storage::instance()->published_index() as $page) {
            $entries[] = ['url' => 'beratung/' . rawurlencode((string) $page['slug']), 'lastmod' => (string) ($page['updated_at'] ?? ''), 'changefreq' => 'monthly', 'priority' => 0.7];
        }

        return $entries;
    }

    /**
     * Veröffentlichte Landingpages in der Seitensuche (alle Suchwörter müssen vorkommen).
     *
     * @return array<int,mixed>
     */
    public function search_results(mixed $results, mixed $query = '', mixed $limit = 10, mixed $context = []): array
    {
        $results = is_array($results) ? $results : [];
        $type = is_array($context) ? (string) ($context['type'] ?? '') : '';
        if ($type !== '' && $type !== 'beratung') {
            return $results;
        }

        $words = preg_split('/\s+/u', trim((string) $query)) ?: [];
        foreach (CMS_Beratung_Storage::instance()->search_published($words, max(1, min(20, (int) $limit))) as $page) {
            $results[] = [
                'title' => (string) $page['public_title'],
                'url' => '/beratung/' . rawurlencode((string) $page['slug']),
                'excerpt' => mb_substr((string) ($page['meta_description'] ?? ''), 0, 240),
                '_type' => 'beratung',
                '_type_label' => 'Beratung',
                'date' => (string) ($page['updated_at'] ?? ''),
            ];
        }

        return $results;
    }
}

/*
 * Lebenszyklus-Callbacks (PluginManager: <slug>_activate/_deactivate/_uninstall).
 */
if (!function_exists('cms_beratung_activate')) {
    function cms_beratung_activate(): void
    {
        CMS_Beratung_Installer::install();
    }
}

if (!function_exists('cms_beratung_deactivate')) {
    function cms_beratung_deactivate(): void
    {
        // Landingpages und Anfragen bleiben erhalten.
    }
}

if (!function_exists('cms_beratung_uninstall')) {
    function cms_beratung_uninstall(): void
    {
        CMS_Beratung_Installer::uninstall();
    }
}

CMS_Beratung::instance();

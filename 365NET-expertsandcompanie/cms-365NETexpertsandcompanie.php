<?php
/**
 * Plugin Name: 365CMS | Experts & Companies
 * Plugin URI: https://365network.de/cms-365NETexpertsandcompanie
 * Description: Vereint Experten und Firmen in einem eigenständigen Plugin mit gemeinsamer Übersicht, Detailseiten, CSV-Import/-Export und Admin-Zentrale.
 * Version: 2.0.0
 * Author: 365 Network
 * Author URI: https://365network.de
 * Requires CMS: 4.0.0
 * Requires at least: 4.0.0
 * Requires PHP: 8.4.1
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$pluginDirPath = str_replace('\\', '/', dirname(__FILE__));
$pluginFolderName = basename($pluginDirPath);

defined('CMS_365NET_EXCOMP_VERSION') || define('CMS_365NET_EXCOMP_VERSION', '2.0.0');
defined('CMS_365NET_EXCOMP_PLUGIN_DIR') || define('CMS_365NET_EXCOMP_PLUGIN_DIR', rtrim($pluginDirPath, '/') . '/');
defined('CMS_365NET_EXCOMP_PLUGIN_URL') || define('CMS_365NET_EXCOMP_PLUGIN_URL', '/plugins/' . $pluginFolderName . '/');
defined('CMS_365NET_EXCOMP_TEXT_DOMAIN') || define('CMS_365NET_EXCOMP_TEXT_DOMAIN', 'cms-365netexpertsandcompanie');

if (!class_exists('CMS_365NET_Experts_And_Companie', false)) {
    final class CMS_365NET_Experts_And_Companie
    {
        private static ?self $instance = null;
        private bool $componentsBootstrapped = false;
        private bool $schemaEnsured = false;
        private ?string $requestPathCache = null;

        private string $version = CMS_365NET_EXCOMP_VERSION;
        private string $pluginDir = CMS_365NET_EXCOMP_PLUGIN_DIR;
        private string $pluginUrl = CMS_365NET_EXCOMP_PLUGIN_URL;

        public static function instance(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function normalizeMediaUrl(string $value): string
        {
            $value = trim(str_replace("\0", '', $value));
            if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return '';
            }

            $legacyBrokenInternal = preg_match('#^https?://(uploads|media|media-file)(/|\?|$)#i', $value) === 1;
            if ($legacyBrokenInternal) {
                $parts = parse_url($value);
                $host = strtolower((string) ($parts['host'] ?? ''));
                $path = (string) ($parts['path'] ?? '');
                $query = isset($parts['query']) ? ('?' . (string) $parts['query']) : '';
                $fragment = isset($parts['fragment']) ? ('#' . (string) $parts['fragment']) : '';
                return '/' . ltrim($host . '/' . ltrim($path, '/'), '/') . $query . $fragment;
            }

            if (preg_match('#^https?://#i', $value) === 1) {
                $parts = parse_url($value);
                $siteParts = defined('SITE_URL') ? parse_url((string) SITE_URL) : [];
                $host = strtolower((string) ($parts['host'] ?? ''));
                $siteHost = is_array($siteParts) ? strtolower((string) ($siteParts['host'] ?? '')) : '';
                if ($host !== '' && $siteHost !== '' && $host === $siteHost) {
                    $path = (string) ($parts['path'] ?? '');
                    $query = isset($parts['query']) ? ('?' . (string) $parts['query']) : '';
                    $fragment = isset($parts['fragment']) ? ('#' . (string) $parts['fragment']) : '';
                    if (preg_match('#^/(uploads|media)(/|$)#i', $path) === 1 || preg_match('#^/media-file(\?|$)#i', $path . $query) === 1) {
                        return $path . $query . $fragment;
                    }
                }

                return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
            }

            if (preg_match('#^(uploads|media)(/|$)#i', $value) === 1 || preg_match('#^media-file(\?|$)#i', $value) === 1) {
                $value = '/' . ltrim($value, '/');
            }

            if (preg_match('#^/(uploads|media)(/|$)#i', $value) === 1 || preg_match('#^/media-file(\?|$)#i', $value) === 1) {
                return $value;
            }

            return '';
        }

        private function __construct()
        {
            $this->loadDependencies();
            $this->initHooks();
            $this->bootstrapHookComponents();

            if ($this->canBootstrapComponents()) {
                $this->bootstrapComponents();
            }
        }

        private function loadDependencies(): void
        {
            $includesDir = $this->pluginDir . 'includes/';
            $includesReal = realpath($includesDir);
            if ($includesReal === false || !is_dir($includesReal)) {
                return;
            }

            foreach ([
                'class-database.php',
                'class-transfer.php',
                'class-template-loader.php',
                'class-integrations.php',
                'class-admin.php',
                'class-post-type.php',
            ] as $file) {
                $candidate = $includesReal . DIRECTORY_SEPARATOR . $file;
                $realPath = realpath($candidate);
                if ($realPath !== false && str_starts_with($realPath, $includesReal . DIRECTORY_SEPARATOR) && is_file($realPath)) {
                    require_once $realPath;
                }
            }
        }

        private function initHooks(): void
        {
            if (!class_exists('CMS\\Hooks')) {
                return;
            }

            CMS\Hooks::addAction('cms_init', [$this, 'initPlugin'], 10);
            // Nur Frontend-Routen des Plugins; das Admin-CSS geben die Admin-Callbacks selbst aus.
            CMS\Hooks::addAction('head', [$this, 'enqueueStyles'], 10);

            if (class_exists('CMS_365NET_Experts_And_Companie_Integrations', false)) {
                CMS_365NET_Experts_And_Companie_Integrations::register();
            }
        }

        private function bootstrapHookComponents(): void
        {
            if (!class_exists('CMS\\Hooks')) {
                return;
            }

            foreach (['CMS_365NET_Experts_And_Companie_Admin', 'CMS_365NET_Experts_And_Companie_Post_Type', 'CMS_365NET_Experts_And_Companie_Template_Loader'] as $class) {
                if (class_exists($class, false)) {
                    $class::instance();
                }
            }
        }

        private function canBootstrapComponents(): bool
        {
            return class_exists('CMS\\Hooks');
        }

        private function bootstrapComponents(): void
        {
            if ($this->componentsBootstrapped) {
                return;
            }

            $this->componentsBootstrapped = true;

            foreach (['CMS_365NET_Experts_And_Companie_Template_Loader', 'CMS_365NET_Experts_And_Companie_Admin', 'CMS_365NET_Experts_And_Companie_Post_Type'] as $class) {
                if (class_exists($class)) {
                    $class::instance();
                }
            }
        }

        public function initPlugin(): void
        {
            $this->maybeUpgradeSchema();

            if (!$this->canBootstrapComponents()) {
                return;
            }

            $this->bootstrapComponents();
        }

        /**
         * Migration nur bei fehlender oder abweichender Schema-Version (eine gebündelte Einstellungsabfrage je Anfrage).
         */
        private function maybeUpgradeSchema(): void
        {
            if ($this->schemaEnsured || !class_exists('CMS_365NET_Experts_And_Companie_Database', false) || !class_exists('CMS\\Database')) {
                return;
            }
            $this->schemaEnsured = true;

            try {
                $db = CMS_365NET_Experts_And_Companie_Database::instance();
                if ($db->needsSchemaUpgrade()) {
                    $db->ensureSchema(false);
                }
            } catch (\Throwable $e) {
                error_log('CMS 365NET Experts & Companies [schema]: ' . $e->getMessage());
            }
        }

        /** Lifecycle: Tabellen anlegen/anheben und Seeds einspielen (leere Tabellen). */
        public function activate(): void
        {
            if (!class_exists('CMS_365NET_Experts_And_Companie_Database', false)) {
                return;
            }
            CMS_365NET_Experts_And_Companie_Database::instance()->ensureSchema(false);
            $this->schemaEnsured = true;
            if (class_exists('CMS\\Hooks')) {
                CMS\Hooks::doAction('cms_365net_excomp_activated');
            }
        }

        /** Nach Änderungen an öffentlichen Daten (Einträge, Einstellungen): Erweiterungspunkt für Caches. */
        public static function flushPublicCache(): void
        {
            if (class_exists('CMS\\Hooks')) {
                CMS\Hooks::doAction('cms_365net_excomp_changed');
            }
        }

        public function enqueueStyles(): void
        {
            if (!$this->isFrontendRoute()) {
                return;
            }

            $this->enqueueStyleFile('style.css');
            $css = self::designVarsCss();
            if ($css !== '') {
                $href = '/experts-companie/design-vars?v=' . substr(hash('sha256', $css), 0, 12);
                echo '<link rel="stylesheet" href="' . htmlspecialchars(rtrim((string) SITE_URL, '/') . $href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        private function enqueueStyleFile(string $file): void
        {
            $path = $this->pluginDir . 'assets/css/' . $file;
            if (!is_file($path)) {
                return;
            }

            $href = $this->pluginUrl . 'assets/css/' . $file . '?v=' . rawurlencode($this->version) . '-' . (string) filemtime($path);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        /**
         * Design-Variablen der Publicseiten (Einstellungen → Layout, Rundungen, Farben) als CSS.
         * Nur geprüfte Werte (Längen/Farben), damit keine CSS-Injektion möglich ist.
         */
        public static function designVarsCss(): string
        {
            if (!class_exists('CMS_365NET_Experts_And_Companie_Database', false) || !class_exists('CMS\\Database')) {
                return '';
            }

            try {
                $settings = CMS_365NET_Experts_And_Companie_Database::instance()->getSettings();
            } catch (\Throwable) {
                return '';
            }
            $defaults = CMS_365NET_Experts_And_Companie_Database::defaultSettings();
            $value = static function (string $key, string $fallbackKey = '') use ($settings, $defaults): string {
                foreach ([$settings[$key] ?? '', $fallbackKey !== '' ? ($settings[$fallbackKey] ?? '') : '', $defaults[$key] ?? ''] as $candidate) {
                    $clean = CMS_365NET_Experts_And_Companie_Database::sanitizeCssValue($key, (string) $candidate);
                    if ($clean !== '') {
                        return $clean;
                    }
                }

                return '';
            };

            $vars = [
                '--cms-excomp-page-pt' => $value('layout_page_padding_top'),
                '--cms-excomp-page-pb' => $value('layout_page_padding_bottom'),
                '--cms-excomp-content-max' => $value('layout_content_max_width'),
                '--cms-excomp-grid-gap' => $value('layout_grid_gap'),
                '--cms-excomp-grid-gap-x' => $value('layout_grid_gap_x', 'layout_grid_gap'),
                '--cms-excomp-grid-gap-y' => $value('layout_grid_gap_y', 'layout_grid_gap'),
                '--cms-excomp-section-gap' => $value('layout_section_gap'),
                '--cms-excomp-radius-card' => $value('style_radius_card'),
                '--cms-excomp-radius-btn' => $value('style_radius_button'),
                '--cms-excomp-radius-surface' => $value('style_radius_surface'),
                '--cms-excomp-radius-hero' => $value('style_radius_hero'),
                '--cms-excomp-color-bg' => $value('color_bg'),
                '--cms-excomp-color-text' => $value('color_text'),
                '--cms-excomp-color-primary' => $value('color_primary'),
                '--cms-excomp-color-hero-start' => $value('color_hero_start'),
                '--cms-excomp-color-hero-end' => $value('color_hero_end'),
                '--cms-excomp-color-expert-accent' => $value('color_expert_accent'),
                '--cms-excomp-color-company-accent' => $value('color_company_accent'),
                '--cms-excomp-color-card-bg' => $value('color_card_bg'),
                '--cms-excomp-color-border' => $value('color_border'),
            ];
            $parts = [];
            foreach ($vars as $name => $css) {
                if ($css !== '') {
                    $parts[] = $name . ':' . $css;
                }
            }

            return $parts === [] ? '' : ':root{' . implode(';', $parts) . ";}\n";
        }

        /**
         * Kompatibilität für Theme-Overrides älterer Templates: Styles kommen seit 2.0.0 als <link> im Kopf
         * (Hook `head`); Inline-Styles werden nicht mehr ausgegeben.
         */
        public static function printInlineStyle(string $file, string $styleId = ''): void
        {
        }

        private function isFrontendRoute(): bool
        {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if (!in_array($method, ['GET', 'HEAD'], true)) {
                return false;
            }

            $path = $this->requestPath();
            return $path === '/experts'
                || str_starts_with($path, '/experts/')
                || $path === '/companies'
                || str_starts_with($path, '/companies/')
                || $path === '/experts-companie'
                || str_starts_with($path, '/experts-companie/')
                || $path === '/experts-and-companie'
                || str_starts_with($path, '/experts-and-companie/')
                || $path === '/experts-companies'
                || str_starts_with($path, '/experts-companies/');
        }

        private function requestPath(): string
        {
            if ($this->requestPathCache !== null) {
                return $this->requestPathCache;
            }

            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $this->requestPathCache = '/' . trim($path, '/');
            return $this->requestPathCache === '/' ? '/' : $this->requestPathCache;
        }

        public function getVersion(): string
        {
            return $this->version;
        }

        public function getPluginDir(): string
        {
            return $this->pluginDir;
        }

        public function getPluginUrl(): string
        {
            return $this->pluginUrl;
        }
    }
}

CMS_365NET_Experts_And_Companie::instance();

/*
 * Lebenszyklus (PluginManager ruft <slug>_activate/_deactivate/_uninstall; Slug = Ordner/Bootstrap-Name).
 */
if (!function_exists('cms_365NETexpertsandcompanie_activate')) {
    function cms_365NETexpertsandcompanie_activate(): void
    {
        CMS_365NET_Experts_And_Companie::instance()->activate();
    }
}

if (!function_exists('cms_365NETexpertsandcompanie_deactivate')) {
    function cms_365NETexpertsandcompanie_deactivate(): void
    {
        // Daten bleiben erhalten; nur den zwischengespeicherten Systemhinweis verwerfen.
        if (class_exists('CMS\\Services\\AdminNoticeService') && method_exists('CMS\\Services\\AdminNoticeService', 'clearCache')) {
            try {
                \CMS\Services\AdminNoticeService::getInstance()->clearCache();
            } catch (\Throwable) {
            }
        }
    }
}

if (!function_exists('cms_365NETexpertsandcompanie_uninstall')) {
    function cms_365NETexpertsandcompanie_uninstall(): void
    {
        // Nur eigene Daten: Tabellen 365net_excomp_* und DSGVO-Auskunftsdateien. Hochgeladene Bilder unter
        // /uploads/experts-companie bleiben (können in Mediathek/Inhalten weiter verwendet werden).
        if (class_exists('CMS_365NET_Experts_And_Companie_Integrations', false)) {
            CMS_365NET_Experts_And_Companie_Integrations::removeAllPrivacyExports();
        }
        if (class_exists('CMS_365NET_Experts_And_Companie_Database', false) && class_exists('CMS\\Database')) {
            CMS_365NET_Experts_And_Companie_Database::instance()->dropTables();
        }
    }
}

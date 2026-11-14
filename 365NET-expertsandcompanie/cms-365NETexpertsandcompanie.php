<?php
/**
 * Plugin Name: 365 | Experts & Companies
 * Plugin URI: https://365network.de/cms-365NETexpertsandcompanie
 * Description: Vereint Experten und Firmen vollständig in einem eigenständigen Plugin mit gemeinsamer Übersicht und Admin-Zentrale.
 * Version: 1.2.0
 * Author: 365 Network
 * Author URI: https://365network.de
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$pluginDirPath = str_replace('\\', '/', dirname(__FILE__));
$pluginFolderName = basename($pluginDirPath);

defined('CMS_365NET_EXCOMP_VERSION') || define('CMS_365NET_EXCOMP_VERSION', '1.2.0');
defined('CMS_365NET_EXCOMP_PLUGIN_DIR') || define('CMS_365NET_EXCOMP_PLUGIN_DIR', rtrim($pluginDirPath, '/') . '/');
defined('CMS_365NET_EXCOMP_PLUGIN_URL') || define('CMS_365NET_EXCOMP_PLUGIN_URL', '/plugins/' . $pluginFolderName . '/');
defined('CMS_365NET_EXCOMP_TEXT_DOMAIN') || define('CMS_365NET_EXCOMP_TEXT_DOMAIN', 'cms-365netexpertsandcompanie');

if (!class_exists('CMS_365NET_Experts_And_Companie', false)) {
    final class CMS_365NET_Experts_And_Companie
    {
        private static ?self $instance = null;
        /** @var array<string, bool> */
        private static array $inlineStylePrinted = [];
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
                'class-template-loader.php',
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
            CMS\Hooks::addAction('plugin_activated', [$this, 'onActivation'], 10);
            CMS\Hooks::addAction('head', [$this, 'enqueueStyles'], 10);
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
            $this->ensureSchema(false);

            if (!$this->canBootstrapComponents()) {
                return;
            }

            $this->bootstrapComponents();
        }

        public function onActivation(string $plugin): void
        {
            $slug = $this->normalizePluginSlug($plugin);
            if ($slug !== 'cms-365netexpertsandcompanie') {
                return;
            }

            $this->ensureSchema(true);

            if (class_exists('CMS\\Hooks')) {
                CMS\Hooks::doAction('cms_365net_excomp_activated');
            }
        }

        private function ensureSchema(bool $forceSeed = false): void
        {
            if ($this->schemaEnsured && !$forceSeed) {
                return;
            }

            if (!class_exists('CMS_365NET_Experts_And_Companie_Database')) {
                return;
            }

            CMS_365NET_Experts_And_Companie_Database::instance()->ensureSchema($forceSeed);
            $this->schemaEnsured = true;
        }

        private function normalizePluginSlug(string $plugin): string
        {
            $plugin = str_replace('\\\\', '/', trim((string) $plugin));
            if ($plugin === '') {
                return '';
            }

            $parts = explode('/', trim($plugin, '/'));
            $last = (string) end($parts);
            if ($last !== '' && str_ends_with(strtolower($last), '.php')) {
                $last = substr($last, 0, -4);
            }

            return strtolower(trim($last));
        }

        public function enqueueStyles(): void
        {
            if ($this->isFrontendRoute()) {
                $this->enqueueStyleFile('style.css');
            }

            if ($this->isAdminRoute()) {
                $this->enqueueStyleFile('admin.css');
            }
        }

        private function enqueueStyleFile(string $file): void
        {
            $path = $this->pluginDir . 'assets/css/' . $file;
            if (!is_file($path)) {
                return;
            }

            $href = $this->pluginUrl . 'assets/css/' . $file . '?v=' . (string) filemtime($path);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        public static function printInlineStyle(string $file, string $styleId = ''): void
        {
            $safeFile = basename($file);
            if ($safeFile === '' || pathinfo($safeFile, PATHINFO_EXTENSION) !== 'css') {
                return;
            }

            $key = ($styleId !== '' ? $styleId : $safeFile);
            if (isset(self::$inlineStylePrinted[$key])) {
                return;
            }

            $path = CMS_365NET_EXCOMP_PLUGIN_DIR . 'assets/css/' . $safeFile;
            if (!is_file($path)) {
                return;
            }

            $content = (string) file_get_contents($path);
            if (trim($content) === '') {
                return;
            }

            self::$inlineStylePrinted[$key] = true;
            $idAttr = $styleId !== '' ? ' id="' . htmlspecialchars($styleId, ENT_QUOTES, 'UTF-8') . '"' : '';
            $nonceAttr = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
            echo '<style' . $idAttr . $nonceAttr . '>' . $content . '</style>' . "\n";
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

        private function isAdminRoute(): bool
        {
            return str_starts_with($this->requestPath(), '/admin/experts-companie');
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

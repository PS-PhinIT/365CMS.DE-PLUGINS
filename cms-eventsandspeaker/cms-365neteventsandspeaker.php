<?php
/**
 * Plugin Name: 365 | Events & Speaker
 * Plugin URI: https://365network.de/cms-365neteventsandspeaker
 * Description: Modulares Event- und Speaker-Verzeichnis für 365CMS mit Seed-Daten, Admin-CRUD und Public-Views.
 * Version: 3.1.0
 * Author: 365 Network
 * Author URI: https://365network.de
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$pluginDirPath = str_replace('\\', '/', dirname(__FILE__));
$pluginFolderName = basename($pluginDirPath);

defined('CMS_365NET_EVENTS_VERSION') || define('CMS_365NET_EVENTS_VERSION', '3.1.0');
defined('CMS_365NET_EVENTS_PLUGIN_DIR') || define('CMS_365NET_EVENTS_PLUGIN_DIR', rtrim($pluginDirPath, '/') . '/');
defined('CMS_365NET_EVENTS_PLUGIN_URL') || define('CMS_365NET_EVENTS_PLUGIN_URL', '/plugins/' . $pluginFolderName . '/');
defined('CMS_365NET_EVENTS_TEXT_DOMAIN') || define('CMS_365NET_EVENTS_TEXT_DOMAIN', 'cms-365neteventsandspeaker');

if (!class_exists('CMS_365NET_Events', false)) {
    /**
     * Bootstrap-Klasse des 365NET Event/Speaker-Plugins.
     *
     * Die Klasse lädt Abhängigkeiten, registriert CMS-Hooks und hält die
     * Initialisierung bewusst schlank. Fachlogik befindet sich in den
     * jeweiligen Include-Klassen.
     */
    final class CMS_365NET_Events
    {
        private static ?self $instance = null;
        private bool $componentsBootstrapped = false;
        private ?string $requestPathCache = null;

        private string $version = CMS_365NET_EVENTS_VERSION;
        private string $pluginDir = CMS_365NET_EVENTS_PLUGIN_DIR;
        private string $pluginUrl = CMS_365NET_EVENTS_PLUGIN_URL;

        public static function instance(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
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

        /**
         * Lädt ausschließlich Plugin-Dateien aus dem eigenen includes-Ordner.
         */
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
            CMS\Hooks::addAction('plugin_deactivated', [$this, 'onDeactivation'], 10);
            CMS\Hooks::addAction('head', [$this, 'enqueueStyles'], 10);
            CMS\Hooks::addAction('body_end', [$this, 'enqueueScripts'], 10);
        }

        /**
         * Komponenten, die bereits vor DB-Verfügbarkeit Hooks registrieren können.
         */
        private function bootstrapHookComponents(): void
        {
            if (!class_exists('CMS\\Hooks')) {
                return;
            }

            foreach (['CMS_365NET_Events_Post_Type', 'CMS_365NET_Events_Admin', 'CMS_365NET_Events_Template_Loader'] as $class) {
                if (class_exists($class, false)) {
                    $class::instance();
                }
            }
        }

        private function canBootstrapComponents(): bool
        {
            return class_exists('CMS\\Hooks') && class_exists('CMS\\Database');
        }

        private function bootstrapComponents(): void
        {
            if ($this->componentsBootstrapped) {
                return;
            }

            $this->componentsBootstrapped = true;

            foreach (['CMS_365NET_Events_Database', 'CMS_365NET_Events_Template_Loader', 'CMS_365NET_Events_Admin', 'CMS_365NET_Events_Post_Type'] as $class) {
                if (class_exists($class)) {
                    $class::instance();
                }
            }
        }

        public function initPlugin(): void
        {
            if (!$this->canBootstrapComponents()) {
                return;
            }

            try {
                $database = CMS_365NET_Events_Database::instance();
                if (!$database->isSchemaCurrent()) {
                    $database->ensureSchema();
                }
            } catch (Throwable $e) {
                $this->log('initPlugin', $e);
            }

            $this->bootstrapComponents();
        }

        public function onActivation(string $plugin): void
        {
            if (!$this->isCurrentPluginSlug($plugin)) {
                return;
            }

            if (class_exists('CMS\\Database') && class_exists('CMS_365NET_Events_Database')) {
                try {
                    CMS_365NET_Events_Database::instance()->ensureSchema();
                } catch (Throwable $e) {
                    $this->log('activation', $e);
                }
            }

            if (class_exists('CMS\\Hooks')) {
                CMS\Hooks::doAction('cms_365net_events_activated');
            }
        }

        public function onDeactivation(string $plugin): void
        {
            if (!$this->isCurrentPluginSlug($plugin)) {
                return;
            }

            if (class_exists('CMS\\Hooks')) {
                CMS\Hooks::doAction('cms_365net_events_deactivated');
            }
        }

        private function isCurrentPluginSlug(string $plugin): bool
        {
            $slug = $this->normalizePluginSlug($plugin);

            return in_array($slug, ['cms-365netevents', 'cms-365neteventsandspeaker'], true);
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
                $this->enqueueStyleFile('public.css');
                $this->enqueuePublicSettingsStyle();
            }

            if ($this->isAdminRoute()) {
                $this->enqueueStyleFile('admin.css');
                $this->enqueueStyleFile('admin-enhancements.css');
                $this->enqueueStyleFile('admin-excomp-forms.css');
            }
        }

        public function enqueueScripts(): void
        {
            if ($this->isFrontendRoute()) {
                $this->enqueueScriptFile('public.js');
            }

            if ($this->isAdminRoute()) {
                $this->enqueueScriptFile('admin.js');
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

        private function enqueuePublicSettingsStyle(): void
        {
            if (!class_exists('CMS_365NET_Events_Database')) {
                return;
            }

            $settings = CMS_365NET_Events_Database::instance()->getSettings();
            $color = static fn(string $key, string $fallback): string => preg_match('/^#[0-9a-f]{6}$/i', (string) ($settings[$key] ?? '')) === 1 ? (string) $settings[$key] : $fallback;
            $number = static fn(string $key, int $fallback, int $min = 0, int $max = 1800): int => max($min, min($max, (int) ($settings[$key] ?? $fallback)));

            $primary = htmlspecialchars($color('layout_primary_color', '#1d4ed8'), ENT_QUOTES, 'UTF-8');
            $accent = htmlspecialchars($color('layout_accent_color', '#f59e0b'), ENT_QUOTES, 'UTF-8');
            $eventCardTopBorder = htmlspecialchars($color('layout_event_card_top_border_color', (string) ($settings['layout_accent_color'] ?? '#f59e0b')), ENT_QUOTES, 'UTF-8');
            $speakerCardTopBorder = htmlspecialchars($color('layout_speaker_card_top_border_color', '#8b5cf6'), ENT_QUOTES, 'UTF-8');
            $text = htmlspecialchars($color('layout_text_color', '#0f172a'), ENT_QUOTES, 'UTF-8');
            $cardBg = htmlspecialchars($color('layout_card_background', '#ffffff'), ENT_QUOTES, 'UTF-8');
            $cardBorder = htmlspecialchars($color('layout_card_border', '#e2e8f0'), ENT_QUOTES, 'UTF-8');

            $gapX = $number('layout_gap', 18, 0, 120);
            $gapY = max(0, min(120, (int) ($settings['layout_gap_y'] ?? $gapX)));

            $css = '.cms-events-public{'
                . '--cms-events-primary:' . $primary . ';'
                . '--cms-events-accent:' . $accent . ';'
                . '--cms-events-event-card-top-border:' . $eventCardTopBorder . ';'
                . '--cms-events-speaker-card-top-border:' . $speakerCardTopBorder . ';'
                . '--cms-events-text:' . $text . ';'
                . '--cms-events-card-bg:' . $cardBg . ';'
                . '--cms-events-card-border:' . $cardBorder . ';'
                . '--cms-events-radius:' . $number('layout_radius', 24, 0, 96) . 'px;'
                . '--cms-events-card-radius:' . $number('layout_card_radius', 20, 0, 96) . 'px;'
                . '--cms-events-gap:' . $gapX . 'px;'
                . '--cms-events-gap-y:' . $gapY . 'px;'
                . '--cms-events-top:' . $number('layout_top_spacing', 32, 0, 240) . 'px;'
                . '--cms-events-bottom:' . $number('layout_bottom_spacing', 56, 0, 240) . 'px;'
                . '--cms-events-width:' . $number('layout_container_width', 1160, 320, 1800) . 'px;'
                . 'background:transparent!important;'
                . 'padding-top:var(--cms-events-top)!important;'
                . 'padding-bottom:var(--cms-events-bottom)!important;'
                . 'color:var(--cms-events-text)!important'
                . '}'
                . '.cms-events-container{max-width:var(--cms-events-width)!important}'
                . '.cms-events-hero{border-radius:var(--cms-events-radius)!important;background:linear-gradient(135deg,var(--cms-events-primary),#172554)!important}'
                . '.cms-events-card,.cms-events-detail-main,.cms-events-sidecard,.cms-events-search{background:var(--cms-events-card-bg)!important;border-color:var(--cms-events-card-border)!important;border-radius:var(--cms-events-card-radius)!important}'
                . '.cms-events-grid{column-gap:var(--cms-events-gap)!important;row-gap:var(--cms-events-gap-y,var(--cms-events-gap))!important}'
                . '.cms-events-search button,.cms-events-btn{background:var(--cms-events-primary)!important}'
                . '.cms-events-kicker{color:var(--cms-events-accent)!important}'
                . '.cms-events-card__more{color:var(--cms-events-primary)!important}'
                . '.cms-events-hero--has-actions{position:relative!important;padding-bottom:72px!important}'
                . '.cms-events-hero__actions{position:absolute;right:16px;bottom:14px;display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px;max-width:calc(100% - 24px)}'
                . '.cms-events-hero__action{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border-radius:10px;background:rgba(255,255,255,.96);border:1px solid rgba(203,213,225,.95);color:#0f172a;text-decoration:none;font-size:.82rem;font-weight:800;white-space:nowrap}'
                . '.cms-events-hero__action:hover{background:#fff;border-color:#fff;color:var(--cms-events-primary)!important}'
                . '.cms-events-hero--compact.cms-events-hero--no-text{min-height:0!important;padding-top:12px!important;padding-bottom:12px!important}'
                . '.cms-events-hero--compact.cms-events-hero--no-text.cms-events-hero--has-actions{padding-bottom:12px!important}'
                . '.cms-events-hero--compact.cms-events-hero--no-text .cms-events-hero__actions{position:static;right:auto;bottom:auto;max-width:none;justify-content:flex-start}'
                . '@media (max-width:760px){.cms-events-hero--has-actions{padding-bottom:86px!important}.cms-events-hero__actions{left:16px;right:16px;justify-content:flex-start}.cms-events-hero__action{flex:1 1 auto;justify-content:center}.cms-events-hero--compact.cms-events-hero--no-text.cms-events-hero--has-actions{padding-bottom:12px!important}.cms-events-hero--compact.cms-events-hero--no-text .cms-events-hero__actions{left:auto;right:auto}}';

            echo '<style id="cms-365neteventsandspeaker-settings" ' . \CMS\Security::instance()->nonceAttr() . '>' . $css . '</style>' . "\n";
        }

        private function enqueueScriptFile(string $file): void
        {
            $path = $this->pluginDir . 'assets/js/' . $file;
            if (!is_file($path)) {
                return;
            }

            $src = $this->pluginUrl . 'assets/js/' . $file . '?v=' . (string) filemtime($path);
            echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }

        private function isFrontendRoute(): bool
        {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if (!in_array($method, ['GET', 'HEAD'], true)) {
                return false;
            }

            $path = $this->requestPath();
            return $path === '/events'
                || str_starts_with($path, '/events/')
                || $path === '/speakers'
                || str_starts_with($path, '/speakers/')
                || $path === '/event-speakers'
                || str_starts_with($path, '/event-speakers/');
        }

        private function isAdminRoute(): bool
        {
            $path = $this->requestPath();
            if (str_starts_with($path, '/admin/365netevents') || str_starts_with($path, '/admin/365neteventsandspeaker')) {
                return true;
            }

            $page = strtolower(trim((string) ($_GET['page'] ?? '')));
            if ($page !== '' && str_starts_with($page, '365netevents')) {
                return true;
            }

            return false;
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

        public function log(string $context, Throwable $error): void
        {
            $message = '[' . date('c') . '] ' . $context . ': ' . $error->getMessage() . PHP_EOL;
            $logDir = $this->pluginDir . 'logs/';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            if (is_dir($logDir) && is_writable($logDir)) {
                @file_put_contents($logDir . 'cms-365neteventsandspeaker.log', $message, FILE_APPEND | LOCK_EX);
            }

            error_log('CMS 365NET Events [' . $context . ']: ' . $error->getMessage());
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

CMS_365NET_Events::instance();

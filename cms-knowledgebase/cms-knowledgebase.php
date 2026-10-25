<?php
/**
 * Plugin Name: CMS Knowledgebase
 * Plugin URI: https://365network.de/cms-knowledgebase
 * Description: Wissensdatenbank mit Auto-Linking, Tooltips und öffentlichen KB-Seiten.
 * Version: 3.1.1
 * Author: 365 Network
 * Author URI: https://365network.de
 *
 * @package CMS_Knowledgebase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('CMS_KNOWLEDGEBASE_VERSION', '3.1.1');
define('CMS_KNOWLEDGEBASE_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('CMS_KNOWLEDGEBASE_PLUGIN_URL', '/plugins/cms-knowledgebase/');

$sharedAdminContract = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'plugin-admin-contract.php';
if (is_file($sharedAdminContract)) {
    require_once $sharedAdminContract;
}

$sharedPublicI18n = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'plugin-public-i18n.php';
if (is_file($sharedPublicI18n)) {
    require_once $sharedPublicI18n;
}

$psrLoggerInterface = ABSPATH . 'assets/psr/Log/LoggerInterface.php';
if (is_file($psrLoggerInterface)) {
    require_once $psrLoggerInterface;
}

$psrNullLogger = ABSPATH . 'assets/psr/Log/NullLogger.php';
if (is_file($psrNullLogger)) {
    require_once $psrNullLogger;
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'CmsKnowledgebase\\')) {
        return;
    }

    $relative = substr($class, strlen('CmsKnowledgebase\\'));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR . $relativePath . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

final class CMS_Knowledgebase
{
    private static ?self $instance = null;

    private $logger;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->logger = \CmsKnowledgebase\Support\LoggerFactory::create();
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        \CMS\Hooks::addAction('cms_init', [$this, 'boot'], 10);
        \CMS\Hooks::addAction('plugin_activated', [$this, 'on_activation'], 10);
        \CMS\Hooks::addAction('plugin_uninstalled', [$this, 'on_uninstall'], 10);
        \CMS\Hooks::addAction('cms_admin_menu', [\CmsKnowledgebase\Admin\Menu::class, 'register'], 10);
        \CMS\Hooks::addFilter('admin_menu_items', [\CmsKnowledgebase\Admin\Menu::class, 'addMenuItems'], 10);
        \CMS\Hooks::addAction('register_routes', [\CmsKnowledgebase\Admin\Menu::class, 'registerAdminRoutes'], 10);
        \CMS\Hooks::addAction('register_routes', [\CmsKnowledgebase\Http\PublicController::instance(), 'registerRoutes'], 10);
        \CMS\Hooks::addAction('head', [$this, 'output_public_style_variables'], 9);
        \CMS\Hooks::addAction('head', [$this, 'enqueue_public_styles'], 10);
        \CMS\Hooks::addAction('before_footer', [$this, 'enqueue_public_scripts'], 10);
        \CMS\Hooks::addAction('main_nav', [\CmsKnowledgebase\Http\PublicController::instance(), 'renderNavItem'], 30);
        \CMS\Hooks::addFilter('content_render', [\CmsKnowledgebase\Service\Linker::instance(), 'filterContent'], 20);
    }

    public function boot(): void
    {
        try {
            \CmsKnowledgebase\Database\Installer::instance()->maybeUpgrade();
            \CmsKnowledgebase\Service\Linker::instance()->bootOutputBufferFallback();
        } catch (\Throwable $exception) {
            $this->logger->error('CMS Knowledgebase konnte beim Booten nicht vollständig initialisiert werden.', [
                'exception' => $exception,
            ]);
        }
    }

    public function on_activation(string $pluginSlug = ''): void
    {
        if ($pluginSlug !== '' && $pluginSlug !== 'cms-knowledgebase') {
            return;
        }

        try {
            \CmsKnowledgebase\Database\Installer::instance()->install();
            $this->logger->info('CMS Knowledgebase aktiviert.', ['plugin' => 'cms-knowledgebase']);
        } catch (\Throwable $exception) {
            $this->logger->critical('CMS Knowledgebase konnte bei der Aktivierung nicht installiert werden.', [
                'plugin' => 'cms-knowledgebase',
                'exception' => $exception,
            ]);
        }
    }

    public function on_uninstall(string $pluginSlug = ''): void
    {
        if ($pluginSlug !== '' && $pluginSlug !== 'cms-knowledgebase') {
            return;
        }

        $this->logger->info('CMS Knowledgebase deinstalliert.', ['plugin' => 'cms-knowledgebase']);
    }

    public function enqueue_public_styles(): void
    {
        if (!\CmsKnowledgebase\Support\RequestInspector::shouldLoadTooltipAssets()) {
            return;
        }

        $file = CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'assets/css/knowledgebase-tooltip.css';
        if (is_file($file)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_KNOWLEDGEBASE_PLUGIN_URL . 'assets/css/knowledgebase-tooltip.css?v=' . filemtime($file), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }

    public function output_public_style_variables(): void
    {
        if (!\CmsKnowledgebase\Support\RequestInspector::shouldOutputPublicStyleVariables()) {
            return;
        }

        $tokens = \CmsKnowledgebase\Repository\EntryRepository::instance()->getPublicDesignTokens();
        if ($tokens === []) {
            return;
        }

        echo '<style id="cms-knowledgebase-style-tokens"' . (class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '') . ">\n:root {\n";
        foreach ($tokens as $name => $value) {
            echo '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ";\n";
        }
        echo "}\n</style>\n";
    }

    public function enqueue_public_scripts(): void
    {
        if (!\CmsKnowledgebase\Support\RequestInspector::shouldLoadTooltipAssets()) {
            return;
        }

        $file = CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'assets/js/knowledgebase-tooltip.js';
        if (is_file($file)) {
            echo '<script src="' . htmlspecialchars(CMS_KNOWLEDGEBASE_PLUGIN_URL . 'assets/js/knowledgebase-tooltip.js?v=' . filemtime($file), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }
}

CMS_Knowledgebase::instance();

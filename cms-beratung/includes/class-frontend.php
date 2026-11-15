<?php
/**
 * CMS Beratung – public routing and asset loading.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Frontend
{
    private static ?self $instance = null;
    private bool $routesRegistered = false;
    private ?array $currentPage = null;
    private bool $publicScriptsPrinted = false;

    public static function instance(?\CMS\Router $router = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::$instance->register_routes($router);
        return self::$instance;
    }

    private function __construct()
    {
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::addAction('head', [$this, 'enqueue_public_assets'], 18);
            \CMS\Hooks::addAction('head', [$this, 'output_seo_head'], 19);
            \CMS\Hooks::addAction('body_end', [$this, 'enqueue_public_scripts'], 20);
            \CMS\Hooks::addFilter('body_class', [$this, 'filter_body_class'], 20);
        }
    }

    public function filter_body_class(mixed $bodyClass): string
    {
        $classes = trim((string) $bodyClass);
        if ($this->resolve_current_page() === null) {
            return $classes;
        }
        $list = preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($list)) {
            $list = [];
        }
        $list[] = 'cms-beratung-page';
        return implode(' ', array_values(array_unique($list)));
    }

    public function enqueue_public_assets(): void
    {
        if ($this->resolve_current_page() === null) {
            return;
        }
        $css = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend.css', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($css) . '">' . "\n";
        }
        $extraCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-extra.css';
        if (is_file($extraCss)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-extra.css', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($extraCss) . '">' . "\n";
        }
        $themeSafeCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-theme-safe.css';
        if (is_file($themeSafeCss)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-theme-safe.css', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($themeSafeCss) . '">' . "\n";
        }
        $premiumCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-premium.css';
        if (is_file($premiumCss)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-premium.css', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($premiumCss) . '">' . "\n";
        }
        $globalDesignCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-global-design.css';
        if (is_file($globalDesignCss)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-global-design.css', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($globalDesignCss) . '">' . "\n";
        }
    }

    public function output_seo_head(): void
    {
        $page = $this->resolve_current_page();
        if ($page === null) {
            return;
        }
        CMS_Beratung_SEO::output_head($page);
    }

    public function enqueue_public_scripts(): void
    {
        if ($this->resolve_current_page() === null) {
            return;
        }
        if ($this->publicScriptsPrinted) {
            return;
        }
        $this->publicScriptsPrinted = true;
        $js = CMS_BERATUNG_PLUGIN_DIR . 'assets/js/frontend.js';
        if (is_file($js)) {
            echo '<script src="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/js/frontend.js', ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($js) . '" defer></script>' . "\n";
        }
    }

    /**
     * Parametrisierte Routen statt einer Route je Landingpage: bisher wurden bei jedem Request alle
     * Landingpages inkl. JSON-Spalten geladen, nur um Routen zu registrieren.
     */
    private function register_routes(?\CMS\Router $router = null): void
    {
        if ($this->routesRegistered || !class_exists('CMS\\Router')) {
            return;
        }
        $this->routesRegistered = true;
        $router = $router instanceof \CMS\Router ? $router : \CMS\Router::instance();

        foreach (['/beratung/:slug', '/de/beratung/:slug', '/en/beratung/:slug'] as $route) {
            foreach (['GET', 'POST'] as $method) {
                $router->addRoute($method, $route, function (string $slug = ''): void {
                    $this->dispatch_slug($slug);
                });
            }
        }

        // Admin-Vorschau (auch Entwürfe) über die normale Theme-Pipeline (CSP-Nonce, Inline-Style-Rewriter).
        $router->addRoute('GET', '/beratung-vorschau/:id', function (string $id = ''): void {
            $this->render_preview((int) $id);
        });
    }

    private function dispatch_slug(string $slug): void
    {
        $slug = strtolower(trim($slug, '/'));
        $match = CMS_Beratung_Storage::instance()->resolve_public_slug($slug);
        if ($match === null) {
            $this->render_not_found();
        }

        $this->render_page($match['slug'], $match['standalone']);
    }

    private function render_not_found(): never
    {
        http_response_code(404);
        if (class_exists('CMS\\Router') && method_exists(\CMS\Router::instance(), 'render404')) {
            \CMS\Router::instance()->render404();
        } else {
            echo 'Landingpage nicht gefunden.';
        }
        exit;
    }

    private function render_preview(int $id): void
    {
        if (!class_exists('CMS\\Auth') || !\CMS\Auth::instance()->isAdmin()) {
            $this->render_not_found();
        }

        $page = CMS_Beratung_Storage::instance()->get_landingpage($id);
        if ($page === null) {
            $this->render_not_found();
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex, nofollow');
        }
        $page['noindex'] = 1;
        $page['nofollow'] = 1;
        $page['is_preview'] = 1;
        $page['show_header'] = 1;
        $page['show_footer'] = 1;
        $this->currentPage = $page;

        if (class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->getHeader(['title' => 'Vorschau: ' . (string) ($page['public_title'] ?? 'CMS Beratung')]);
        }
        echo '<div class="cms-beratung-previewbar" role="status">Vorschau (' . htmlspecialchars((string) ($page['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') . ') · nur für Administratoren sichtbar · Formulare werden nicht gesendet</div>';
        CMS_Beratung_Renderer::render($page, ['success' => false, 'message' => '', 'errors' => [], 'values' => []]);
        $this->enqueue_public_scripts();
        if (class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->getFooter();
        }
        exit;
    }

    private function render_page(string $slug, bool $standaloneVariant = false): void
    {
        $page = CMS_Beratung_Storage::instance()->get_landingpage_by_slug($slug, true);
        if ($page === null) {
            $this->render_not_found();
        }

        $hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
        if ($standaloneVariant) {
            if (empty($hero['standalone_header_enabled'])) {
                $this->render_not_found();
            }
            $page['show_header'] = 0;
            $page['show_footer'] = 0;
            $page['is_standalone_variant'] = 1;
        } else {
            $page['show_header'] = 1;
            $page['show_footer'] = 1;
        }

        $this->currentPage = $page;
        $formResult = CMS_Beratung_Forms::handle_submission($page);

        if ($standaloneVariant) {
            $this->render_standalone_document($page, $formResult);
            exit;
        }

        if (!empty($page['show_header']) && class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->getHeader(['title' => (string) ($page['public_title'] ?? 'CMS Beratung')]);
        }

        CMS_Beratung_Renderer::render($page, $formResult);
        $this->enqueue_public_scripts();

        if (!empty($page['show_footer']) && class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->getFooter();
        }
        exit;
    }

    /** @param array<string,mixed> $page @param array{success:bool,message:string} $formResult */
    private function render_standalone_document(array $page, array $formResult): void
    {
        if (class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->loadTheme();
        }
        $title = htmlspecialchars((string) ($page['meta_title'] ?? $page['public_title'] ?? 'CMS Beratung'), ENT_QUOTES, 'UTF-8');
        $bodyClass = 'cms-beratung-page cms-beratung-standalone-page';
        if (class_exists('CMS\\Hooks')) {
            $bodyClass = (string) \CMS\Hooks::applyFilters('body_class', $bodyClass);
        }
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title>';
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::doAction('head');
        } else {
            $this->enqueue_public_assets();
            $this->output_seo_head();
        }
        echo '</head><body class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '">';
        CMS_Beratung_Renderer::render_standalone_header($page);
        $page['standalone_header_rendered_above_content'] = 1;
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::doAction('body_start');
        }
        CMS_Beratung_Renderer::render($page, $formResult);
        if (class_exists('CMS\\Hooks')) {
            \CMS\Hooks::doAction('before_footer');
            \CMS\Hooks::doAction('body_end');
        } else {
            $this->enqueue_public_scripts();
        }
        echo '</body></html>';
    }

    private function resolve_current_page(): ?array
    {
        if ($this->currentPage !== null) {
            return $this->currentPage;
        }
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $path = strtolower(trim((string) preg_replace('#/+#', '/', $path), '/'));
        if (preg_match('#^(?:[a-z]{2}/)?beratung/([a-z0-9_-]+)$#', $path, $matches) !== 1) {
            return null;
        }
        $match = CMS_Beratung_Storage::instance()->resolve_public_slug((string) $matches[1]);
        if ($match === null) {
            return null;
        }
        $page = CMS_Beratung_Storage::instance()->get_landingpage_by_slug($match['slug'], true);
        if ($page !== null && $match['standalone']) {
            $page['show_header'] = 0;
            $page['show_footer'] = 0;
            $page['is_standalone_variant'] = 1;
        }
        $this->currentPage = $page;
        return $this->currentPage;
    }
}

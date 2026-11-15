<?php
/**
 * CMS Beratung – öffentliche Routen, Vorschau und Asset-Ausgabe.
 *
 * Assets werden nur auf Landingpage-Routen ausgegeben; die Routenliste stammt aus einer schlanken
 * Abfrage (veröffentlichte Slugs) und wird pro Request einmal ermittelt.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Frontend
{
    /** Frontend-Stylesheets in Ladereihenfolge. */
    public const STYLESHEETS = ['frontend.css', 'frontend-extra.css', 'frontend-theme-safe.css', 'frontend-premium.css', 'frontend-global-design.css'];

    private static ?self $instance = null;
    private bool $routesRegistered = false;
    private ?array $currentPage = null;
    private bool $currentResolved = false;
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
        $classes = trim(is_scalar($bodyClass) ? (string) $bodyClass : '');
        if ($this->resolve_current_page() === null) {
            return $classes;
        }
        $list = preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY);
        $list = is_array($list) ? $list : [];
        $list[] = 'cms-beratung-page';
        return implode(' ', array_values(array_unique($list)));
    }

    /** `<link>`-Tags der Frontend-Stylesheets mit Cache-Buster. */
    public static function stylesheet_tags(): string
    {
        $html = '';
        foreach (self::STYLESHEETS as $file) {
            $path = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/' . $file;
            if (is_file($path)) {
                $html .= '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/css/' . $file . '?v=' . CMS_BERATUNG_VERSION . '-' . filemtime($path), ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }
        return $html;
    }

    public static function script_tag(): string
    {
        $js = CMS_BERATUNG_PLUGIN_DIR . 'assets/js/frontend.js';
        if (!is_file($js)) {
            return '';
        }
        $nonce = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
        return '<script src="' . htmlspecialchars(CMS_BERATUNG_PLUGIN_URL . 'assets/js/frontend.js?v=' . CMS_BERATUNG_VERSION . '-' . filemtime($js), ENT_QUOTES, 'UTF-8') . '" defer' . $nonce . '></script>' . "\n";
    }

    public function enqueue_public_assets(): void
    {
        if ($this->resolve_current_page() === null) {
            return;
        }
        echo self::stylesheet_tags();
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
        if ($this->publicScriptsPrinted || $this->resolve_current_page() === null) {
            return;
        }
        $this->publicScriptsPrinted = true;
        echo self::script_tag();
    }

    private function register_routes(?\CMS\Router $router = null): void
    {
        if ($this->routesRegistered || !class_exists('CMS\\Router')) {
            return;
        }
        $this->routesRegistered = true;
        $router = $router instanceof \CMS\Router ? $router : \CMS\Router::instance();

        $router->addRoute('GET', '/api/cms-beratung/preview', [$this, 'render_preview']);

        foreach (CMS_Beratung_Storage::instance()->published_routes() as $route) {
            $slug = $route['slug'];
            foreach (['/beratung/' . $slug, '/de/beratung/' . $slug, '/en/beratung/' . $slug] as $path) {
                $handler = function () use ($slug): void {
                    $this->render_page($slug);
                };
                $router->addRoute('GET', $path, $handler);
                $router->addRoute('POST', $path, $handler);
            }
            $standaloneSlug = $route['standalone_slug'];
            if ($standaloneSlug === '') {
                continue;
            }
            foreach (['/beratung/' . $standaloneSlug, '/de/beratung/' . $standaloneSlug, '/en/beratung/' . $standaloneSlug] as $path) {
                $handler = function () use ($slug): void {
                    $this->render_page($slug, true);
                };
                $router->addRoute('GET', $path, $handler);
                $router->addRoute('POST', $path, $handler);
            }
        }
    }

    /** Entwurfs-Vorschau (nur Admins, noindex, eigenes Dokument ohne Theme). */
    public function render_preview(): void
    {
        $isAdmin = class_exists('CMS\\Auth') && \CMS\Auth::instance()->isAdmin();
        if (!$isAdmin) {
            header('Location: ' . (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . '/', true, 303);
            exit;
        }
        $page = CMS_Beratung_Storage::instance()->get_landingpage(max(0, (int) ($_GET['id'] ?? 0)));
        if ($page === null) {
            http_response_code(404);
            echo 'Landingpage-Vorschau nicht gefunden.';
            exit;
        }
        header('X-Robots-Tag: noindex, nofollow');
        $this->currentPage = $page;
        $this->currentResolved = true;
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vorschau: '
            . htmlspecialchars((string) ($page['public_title'] ?? 'CMS Beratung'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo self::stylesheet_tags();
        echo '</head><body class="cms-beratung-page cms-beratung-preview-page"><div class="cms-beratung-previewbar">Entwurfs-Vorschau · nur für berechtigte Benutzer</div>';
        CMS_Beratung_Renderer::render($page, ['success' => false, 'message' => '', 'errors' => [], 'values' => []]);
        echo self::script_tag();
        echo '</body></html>';
        exit;
    }

    private function render_page(string $slug, bool $standaloneVariant = false): void
    {
        $page = CMS_Beratung_Storage::instance()->get_landingpage_by_slug($slug, true);
        if ($page === null) {
            http_response_code(404);
            echo 'Landingpage nicht gefunden.';
            exit;
        }

        $hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
        if ($standaloneVariant) {
            if (empty($hero['standalone_header_enabled'])) {
                http_response_code(404);
                echo 'Standalone Publicsite nicht aktiviert.';
                exit;
            }
            $page['show_header'] = 0;
            $page['show_footer'] = 0;
            $page['is_standalone_variant'] = 1;
        } else {
            $page['show_header'] = 1;
            $page['show_footer'] = 1;
        }

        $this->currentPage = $page;
        $this->currentResolved = true;
        $formResult = CMS_Beratung_Forms::handle_submission($page);
        CMS_Beratung_SEO::apply_request_meta($page);

        if ($standaloneVariant) {
            $this->render_standalone_document($page, $formResult);
            exit;
        }

        if (class_exists('CMS\\ThemeManager')) {
            \CMS\ThemeManager::instance()->getHeader(['title' => (string) ($page['public_title'] ?? 'CMS Beratung')]);
        }

        CMS_Beratung_Renderer::render($page, $formResult);
        $this->enqueue_public_scripts();

        if (class_exists('CMS\\ThemeManager')) {
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
        $title = htmlspecialchars((string) (trim((string) ($page['meta_title'] ?? '')) ?: ($page['public_title'] ?? 'CMS Beratung')), ENT_QUOTES, 'UTF-8');
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

    /** Aktuelle Landingpage aus dem Pfad – ohne Treffer keine Abfrage (Regex-Vorprüfung). */
    private function resolve_current_page(): ?array
    {
        if ($this->currentResolved) {
            return $this->currentPage;
        }
        $this->currentResolved = true;
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $path = strtolower(trim((string) preg_replace('#/+#', '/', $path), '/'));
        if (preg_match('#^(?:[a-z]{2}/)?beratung/([a-z0-9_-]+)$#', $path, $matches) !== 1) {
            return null;
        }
        $requestSlug = (string) $matches[1];
        $storage = CMS_Beratung_Storage::instance();
        $this->currentPage = $storage->get_landingpage_by_slug($requestSlug, true);
        if ($this->currentPage === null) {
            $mainSlug = $storage->slug_for_standalone($requestSlug);
            $page = $mainSlug !== null ? $storage->get_landingpage_by_slug($mainSlug, true) : null;
            if ($page !== null) {
                $page['show_header'] = 0;
                $page['show_footer'] = 0;
                $page['is_standalone_variant'] = 1;
                $this->currentPage = $page;
            }
        }
        return $this->currentPage;
    }
}

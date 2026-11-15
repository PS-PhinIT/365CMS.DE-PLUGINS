<?php
/**
 * Öffentliche Routen (/promos, Klick-Redirect) und automatische Ausspielung über Theme-Hooks.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Public_Controller
{
    private const FREQUENCY_COOKIE = 'cms_promos_freq';
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|monitor|headless|lighthouse|curl|wget|python-requests|go-http-client/i';

    private static ?self $instance = null;

    /** @var array<int,int> im Request ausgespielte Promo-IDs (Impressions werden einmal am Request-Ende gezählt) */
    private array $pendingImpressions = [];
    private bool $flushRegistered = false;
    /** @var array<string,array<int,int>>|null */
    private ?array $frequencyHistory = null;
    /** @var array<int,list<array<string,mixed>>>|null */
    private ?array $hookPromos = null;
    private bool $archiveRendered = false;

    private function __construct()
    {
        $this->register_theme_hooks();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function register_theme_hooks(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        \CMS\Hooks::addAction('body_start', [$this, 'render_body_start_promos'], 20);
        \CMS\Hooks::addAction('after_header', [$this, 'render_after_header_promos'], 20);
        \CMS\Hooks::addAction('home_content', [$this, 'render_home_content_promos'], 20);
        \CMS\Hooks::addAction('before_footer', [$this, 'render_before_footer_promos'], 20);
    }

    public function register_routes(mixed $router = null): void
    {
        if (!is_object($router) || !method_exists($router, 'addRoute')) {
            return;
        }

        $router->addRoute('GET', '/promos', [$this, 'archive_page']);
        $router->addRoute('GET', '/en/promos', [$this, 'archive_page']);
        $router->addRoute('GET', '/promos/placement/:slug', [$this, 'archive_page']);
        $router->addRoute('GET', '/en/promos/placement/:slug', [$this, 'archive_page']);
        $router->addRoute('GET', '/promo/click/:slug', [$this, 'redirect_promo']);
        $router->addRoute('GET', '/en/promo/click/:slug', [$this, 'redirect_promo']);
    }

    // ── Assets ──────────────────────────────────────────────────────────────

    /**
     * Öffentliches CSS nur auf /promos oder wenn eine aktive Hook-Platzierung existiert
     * (die Abfrage wird für die Ausspielung im selben Request wiederverwendet).
     */
    public function enqueue_styles(): void
    {
        if ($this->is_admin_request()) {
            return;
        }

        try {
            if (!$this->is_promos_public_route() && CMS_Promos_Repository::instance()->get_hook_placement_map() === []) {
                return;
            }
        } catch (\Throwable $e) {
            $this->log_error('Asset check failed: ' . $e->getMessage());
            return;
        }

        $css = CMS_PROMOS_PLUGIN_DIR . 'assets/css/promos-public.css';
        if (!is_file($css)) {
            return;
        }

        echo '<link rel="stylesheet" href="'
            . $this->escape(CMS_PROMOS_PLUGIN_URL . 'assets/css/promos-public.css?v=' . CMS_PROMOS_VERSION . '-' . (string) filemtime($css))
            . '">' . "\n";
    }

    // ── Archiv ──────────────────────────────────────────────────────────────

    public function archive_page(string $slug = ''): void
    {
        $lang = $this->current_public_lang();
        $slug = $this->normalize_slug($slug);
        $repository = CMS_Promos_Repository::instance();
        $settings = $repository->get_settings();
        $placements = $repository->get_active_placements();
        $currentPlacement = $slug !== '' ? $repository->get_placement_by_slug($slug) : null;
        $placementNotFound = $slug !== '' && ($currentPlacement === null || ($currentPlacement['status'] ?? '') !== 'active');
        if ($placementNotFound) {
            $currentPlacement = null;
        }

        $promos = $placementNotFound ? [] : $this->apply_frequency_cap($repository->get_active_promos($slug !== '' ? $slug : null));
        $this->queue_impressions($promos);
        $this->archiveRendered = true;

        if ($placementNotFound && !headers_sent()) {
            http_response_code(404);
        }

        $this->set_archive_meta($settings, $lang, $currentPlacement, $placementNotFound, count($promos));

        $theme = class_exists('CMS\\ThemeManager') ? \CMS\ThemeManager::instance() : null;
        if ($theme !== null) {
            $theme->getHeader();
        }

        $template = CMS_PROMOS_PLUGIN_DIR . 'templates/archive-promos.php';
        if (is_file($template)) {
            include $template;
        } else {
            $this->log_error('Archive template missing: ' . $template);
        }

        if ($theme !== null) {
            $theme->getFooter();
        }
    }

    /**
     * Titel, Beschreibung, Canonical und Robots der Archivseite. Platzierungs-Unterseiten sind Filteransichten
     * und werden mit noindex,follow ausgeliefert.
     *
     * @param array<string,string> $settings
     */
    private function set_archive_meta(array $settings, string $lang, ?array $placement, bool $notFound, int $promoCount): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }

        try {
            $title = $this->i18n_value($settings, 'archive_title', $lang, 'Promotions & Highlights');
            if ($placement !== null) {
                $title = $this->i18n_value($placement, 'name', $lang, $title) . ' – ' . $title;
            }
            $indexable = ($settings['archive_indexable'] ?? '1') === '1' && $placement === null && !$notFound && $promoCount > 0;
            $canonicalPath = $placement !== null
                ? $this->localized_path('promos/placement/' . rawurlencode((string) $placement['slug']), $lang)
                : $this->localized_path('promos', $lang);

            \CMS\Services\SEOService::getInstance()->setRequestMeta([
                'title' => $title,
                'description' => mb_substr($this->i18n_value($settings, 'archive_description', $lang), 0, 300),
                'canonical_url' => rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/') . $canonicalPath,
                'robots_index' => $indexable,
                'robots_follow' => true,
                'og_type' => 'website',
            ]);
        } catch (\Throwable $e) {
            $this->log_error('Could not set archive meta: ' . $e->getMessage());
        }
    }

    // ── Klick-Redirect ──────────────────────────────────────────────────────

    public function redirect_promo(string $slug = ''): void
    {
        $lang = $this->current_public_lang();
        $slug = $this->normalize_slug($slug);
        $repository = CMS_Promos_Repository::instance();
        $promo = $slug !== '' ? $repository->get_promo_for_click($slug) : null;
        // Entwürfe sind nie veröffentlicht worden; pausierte/abgelaufene Promos leiten weiter, zählen aber nicht.
        $targetUrl = $promo !== null && ($promo['status'] ?? '') !== 'draft' ? $this->build_target_url($promo) : '';

        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, max-age=0', true);
        }

        if ($promo === null || $targetUrl === '') {
            http_response_code(404);
            echo '<!doctype html><html lang="' . $this->escape($lang) . '"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>'
                . $this->escape($this->public_label('not_found_title', $lang)) . '</title></head><body><h1>'
                . $this->escape($this->public_label('not_found_title', $lang)) . '</h1><p><a href="'
                . $this->escape($this->localized_path('promos', $lang)) . '">' . $this->escape($this->public_label('back_to_archive', $lang))
                . '</a></p></body></html>';
            return;
        }

        if ((int) ($promo['is_live'] ?? 0) === 1 && !$this->is_bot()) {
            $repository->increment_click((int) $promo['id']);
            $this->dispatch_click_export_event($promo, $targetUrl, $lang, $repository->get_settings());
        }

        if (headers_sent()) {
            $this->log_error('Cannot redirect promo click because headers were already sent');
            echo '<a href="' . $this->escape($targetUrl) . '" rel="noopener noreferrer">' . $this->escape($this->public_label('continue_cta', $lang)) . '</a>';
            return;
        }

        header('Location: ' . $targetUrl, true, 302);
        exit;
    }

    // ── Theme-Hooks ─────────────────────────────────────────────────────────

    public function render_body_start_promos(): void
    {
        $this->render_theme_hook('body_start');
    }

    public function render_after_header_promos(): void
    {
        $this->render_theme_hook('after_header');
    }

    public function render_home_content_promos(): void
    {
        $this->render_theme_hook('home_content');
    }

    public function render_before_footer_promos(): void
    {
        $this->render_theme_hook('before_footer');
    }

    /**
     * Laufende Promos aller Hook-Platzierungen in einer Abfrage laden (statt einer Abfrage je Platzierung).
     *
     * @return array<int,list<array<string,mixed>>>
     */
    private function hook_promos(): array
    {
        if ($this->hookPromos !== null) {
            return $this->hookPromos;
        }

        $repository = CMS_Promos_Repository::instance();
        $ids = [];
        foreach ($repository->get_hook_placement_map() as $placements) {
            foreach ($placements as $placement) {
                $ids[] = (int) ($placement['id'] ?? 0);
            }
        }

        return $this->hookPromos = $ids === [] ? [] : $repository->get_live_promos_for_placements($ids);
    }

    private function render_theme_hook(string $themeHook): void
    {
        if ($this->is_admin_request()) {
            return;
        }

        try {
            $repository = CMS_Promos_Repository::instance();
            $placements = $repository->get_hook_placements($themeHook);
            if ($placements === []) {
                return;
            }

            $promosByPlacement = $this->hook_promos();
            $lang = $this->current_public_lang();
            $settings = $repository->get_settings();

            foreach ($placements as $placement) {
                $placementId = (int) ($placement['id'] ?? 0);
                $candidates = $promosByPlacement[$placementId] ?? [];
                if ($candidates === []) {
                    continue;
                }

                $promos = $this->apply_frequency_cap($candidates, max(1, (int) ($placement['max_items'] ?? 3)));
                if ($promos === []) {
                    continue;
                }

                $this->queue_impressions($promos);
                echo $this->render_hook_zone($themeHook, $placement, $promos, $settings, $lang);
            }
        } catch (\Throwable $e) {
            $this->log_error('Hook rendering failed (' . $themeHook . '): ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $placement
     * @param list<array<string,mixed>> $promos
     * @param array<string,string> $settings
     */
    private function render_hook_zone(string $themeHook, array $placement, array $promos, array $settings, string $lang): string
    {
        $html = '<section class="promos-hook-zone promos-hook-zone--' . $this->escape($themeHook) . '" aria-label="' . $this->escape($this->i18n_value($placement, 'name', $lang, $this->public_label('placement_kicker', $lang))) . '">'
            . '<div class="promos-hook-zone__inner"><div class="promos-hook-zone__header">'
            . '<span class="promos-kicker">' . $this->escape($this->public_label('placement_kicker', $lang)) . '</span>'
            . '<h2 class="promos-hook-zone__title">' . $this->escape($this->i18n_value($placement, 'name', $lang, $this->public_label('placement_kicker', $lang))) . '</h2>';
        $description = $this->i18n_value($placement, 'description', $lang);
        if ($description !== '') {
            $html .= '<p class="promos-hook-zone__lead">' . $this->escape($description) . '</p>';
        }
        $html .= '</div><div class="promos-hook-grid">';

        $newTab = ($settings['default_target_behavior'] ?? 'same_tab') === 'new_tab';
        foreach ($promos as $promo) {
            $title = $this->i18n_value($promo, 'title', $lang, $this->public_label('promo_generic', $lang));
            $buttonLabel = $this->button_label($promo, $settings, $lang);
            $hasTarget = trim((string) ($promo['target_url'] ?? '')) !== '';
            $href = $hasTarget
                ? $this->localized_path('promo/click/' . rawurlencode((string) ($promo['slug'] ?? '')), $lang)
                : $this->localized_path('promos', $lang);

            $html .= '<article class="promos-hook-card' . (!empty($promo['is_featured']) ? ' promos-hook-card--featured' : '') . '">';
            $image = CMS_Promos_Sanitizer::link((string) ($promo['image_url'] ?? ''));
            if ($image !== '') {
                $html .= '<div class="promos-hook-card__media"><img src="' . $this->escape($image) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async"></div>';
            }
            $html .= '<div class="promos-hook-card__content">'
                . '<span class="promos-card__eyebrow">' . $this->escape($this->i18n_value($promo, 'placement_name', $lang, $this->public_label('promo_generic', $lang))) . '</span>'
                . '<h3 class="promos-card__title">' . $this->escape($title) . '</h3>';
            $teaser = $this->i18n_value($promo, 'teaser', $lang);
            if ($teaser !== '') {
                $html .= '<p>' . $this->escape($teaser) . '</p>';
            }
            $content = $this->i18n_value($promo, 'content_html', $lang);
            if ($content !== '') {
                $html .= '<div class="promos-hook-card__body">' . CMS_Promos_Sanitizer::html($content) . '</div>';
            }
            $html .= '<a class="promos-card__button" href="' . $this->escape($href) . '"'
                . ($hasTarget ? ' rel="sponsored noopener"' : '')
                . ($hasTarget && $newTab ? ' target="_blank"' : '') . '>' . $this->escape($buttonLabel) . '</a>'
                . '</div></article>';
        }

        return $html . '</div></div></section>';
    }

    /**
     * @param array<string,mixed> $promo
     * @param array<string,string> $settings
     */
    public function button_label(array $promo, array $settings, string $lang): string
    {
        $default = $this->i18n_value($settings, 'default_button_label', $lang, $this->public_label('default_button', $lang));

        return $this->i18n_value($promo, 'button_label', $lang, $default);
    }

    // ── Impressions ─────────────────────────────────────────────────────────

    /**
     * @param list<array<string,mixed>> $promos
     */
    private function queue_impressions(array $promos): void
    {
        if ($promos === [] || $this->is_bot()) {
            return;
        }

        foreach ($promos as $promo) {
            $id = (int) ($promo['id'] ?? 0);
            if ($id > 0) {
                $this->pendingImpressions[$id] = $id;
            }
        }

        if (!$this->flushRegistered) {
            $this->flushRegistered = true;
            register_shutdown_function([$this, 'flush_impressions']);
        }
    }

    /** Zählt alle Impressions des Requests mit einer UPDATE-Anweisung. */
    public function flush_impressions(): void
    {
        if ($this->pendingImpressions === []) {
            return;
        }

        $ids = array_values($this->pendingImpressions);
        $this->pendingImpressions = [];
        try {
            CMS_Promos_Repository::instance()->increment_impressions($ids);
        } catch (\Throwable $e) {
            $this->log_error('Impression flush failed: ' . $e->getMessage());
        }
    }

    // ── Frequency Cap ───────────────────────────────────────────────────────

    /**
     * Filtert Promos, deren Anzeige-Limit im Zeitfenster erreicht ist, begrenzt auf $limit und merkt
     * die Anzeige im Cookie (bzw. in einer bereits laufenden Session) vor.
     *
     * @param list<array<string,mixed>> $promos
     * @return list<array<string,mixed>>
     */
    private function apply_frequency_cap(array $promos, int $limit = 0): array
    {
        if ($promos === []) {
            return [];
        }

        $history = $this->read_frequency_history();
        $now = time();
        $changed = false;
        $result = [];

        foreach ($promos as $promo) {
            if ($limit > 0 && count($result) >= $limit) {
                break;
            }

            $promoId = (int) ($promo['id'] ?? 0);
            if ($promoId <= 0) {
                continue;
            }

            $cap = max(0, (int) ($promo['frequency_cap'] ?? 0));
            if ($cap <= 0) {
                $result[] = $promo;
                continue;
            }

            $windowStart = $now - (max(1, (int) ($promo['frequency_window_hours'] ?? 24)) * 3600);
            $key = (string) $promoId;
            $timestamps = array_values(array_filter($history[$key] ?? [], static fn (int $ts): bool => $ts >= $windowStart));
            if (count($timestamps) >= $cap) {
                continue;
            }

            $timestamps[] = $now;
            $history[$key] = array_slice($timestamps, -20);
            $result[] = $promo;
            $changed = true;
        }

        if ($changed) {
            $this->write_frequency_history($history);
        }

        return $result;
    }

    /**
     * @return array<string,array<int,int>>
     */
    private function read_frequency_history(): array
    {
        if ($this->frequencyHistory !== null) {
            return $this->frequencyHistory;
        }

        // Nur eine bereits laufende Session nutzen – nie eine starten (Lazy Sessions/öffentliches Caching des Core).
        $raw = session_status() === PHP_SESSION_ACTIVE ? ($_SESSION[self::FREQUENCY_COOKIE] ?? null) : null;
        if (!is_array($raw)) {
            $cookie = (string) ($_COOKIE[self::FREQUENCY_COOKIE] ?? '');
            $raw = $cookie !== '' && strlen($cookie) <= 8192 ? json_decode($cookie, true) : null;
        }

        $history = [];
        foreach (is_array($raw) ? $raw : [] as $promoId => $timestamps) {
            $key = preg_replace('/[^0-9]/', '', (string) $promoId) ?? '';
            if ($key === '' || !is_array($timestamps)) {
                continue;
            }
            $history[$key] = array_values(array_filter(array_map('intval', $timestamps), static fn (int $ts): bool => $ts > 0));
        }

        return $this->frequencyHistory = $history;
    }

    /**
     * @param array<string,array<int,int>> $history
     */
    private function write_frequency_history(array $history): void
    {
        $now = time();
        $cutoff = $now - (14 * 24 * 3600);
        $normalized = [];
        foreach ($history as $promoId => $timestamps) {
            $clean = array_values(array_filter(array_map('intval', $timestamps), static fn (int $ts): bool => $ts >= $cutoff));
            if ($clean !== []) {
                $normalized[(string) $promoId] = array_slice($clean, -20);
            }
        }
        if (count($normalized) > 200) {
            $normalized = array_slice($normalized, -200, null, true);
        }

        $this->frequencyHistory = $normalized;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::FREQUENCY_COOKIE] = $normalized;
        }

        if (headers_sent()) {
            return;
        }

        $payload = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        setcookie(self::FREQUENCY_COOKIE, $payload, [
            'expires' => $now + (14 * 24 * 3600),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::FREQUENCY_COOKIE] = $payload;
    }

    // ── Ziel-URL & Klick-Export ─────────────────────────────────────────────

    /**
     * @param array<string,mixed> $promo
     */
    private function build_target_url(array $promo): string
    {
        $targetUrl = CMS_Promos_Sanitizer::public_url((string) ($promo['target_url'] ?? ''));
        if ($targetUrl === '') {
            return '';
        }

        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $value = trim((string) ($promo[$key] ?? ''));
            if ($value !== '') {
                $utm[$key] = $value;
            }
        }
        if ($utm === []) {
            return $targetUrl;
        }

        $parts = parse_url($targetUrl);
        if (!is_array($parts)) {
            return $targetUrl;
        }

        $existing = [];
        parse_str((string) ($parts['query'] ?? ''), $existing);
        foreach ($utm as $key => $value) {
            if (!array_key_exists($key, $existing) || trim((string) (is_scalar($existing[$key]) ? $existing[$key] : '')) === '') {
                $existing[$key] = $value;
            }
        }

        $parts['query'] = http_build_query($existing);

        return CMS_Promos_Sanitizer::public_url($this->unparse_url($parts));
    }

    /**
     * @param array<string,mixed> $parts
     */
    private function unparse_url(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '') !== '' ? '?' . (string) $parts['query'] : '';
        $fragment = (string) ($parts['fragment'] ?? '') !== '' ? '#' . (string) $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    /**
     * @param array<string,mixed> $promo
     * @param array<string,string> $settings
     */
    private function dispatch_click_export_event(array $promo, string $targetUrl, string $lang, array $settings): void
    {
        $payload = [
            'event' => 'promo_click',
            'timestamp' => gmdate('c'),
            'lang' => $lang,
            'promo_id' => (int) ($promo['id'] ?? 0),
            'promo_slug' => (string) ($promo['slug'] ?? ''),
            'promo_title' => $this->i18n_value($promo, 'title', $lang),
            'placement_id' => isset($promo['placement_id']) ? (int) $promo['placement_id'] : null,
            'target_url' => $targetUrl,
            'request_path' => (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'),
            'utm_source' => (string) ($promo['utm_source'] ?? ''),
            'utm_medium' => (string) ($promo['utm_medium'] ?? ''),
            'utm_campaign' => (string) ($promo['utm_campaign'] ?? ''),
            'utm_term' => (string) ($promo['utm_term'] ?? ''),
            'utm_content' => (string) ($promo['utm_content'] ?? ''),
        ];

        try {
            if (class_exists('CMS\\Hooks')) {
                \CMS\Hooks::doAction('cms_promos_click_export', $payload);
            }
        } catch (\Throwable $e) {
            $this->log_error('Click export hook failed: ' . $e->getMessage());
        }

        if (($settings['click_export_enabled'] ?? '0') !== '1') {
            return;
        }

        $webhookUrl = CMS_Promos_Repository::instance()->get_webhook_url();
        if ($webhookUrl !== '') {
            $this->send_webhook_json($webhookUrl, $payload);
        }
    }

    /**
     * Webhook über den Core-HTTP-Client (SSRF-Schutz, Timeouts). Kurzes Timeout, weil der Besucher auf den
     * Redirect wartet; Fehler werden nur protokolliert.
     *
     * @param array<string,mixed> $payload
     */
    private function send_webhook_json(string $url, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body) || !class_exists('CMS\\Http\\Client')) {
            return;
        }

        try {
            $response = \CMS\Http\Client::getInstance()->post($url, $body, [
                'headers' => ['Content-Type: application/json', 'Accept: application/json'],
                'timeout' => 2,
                'connectTimeout' => 1,
                'maxBytes' => 65536,
                'userAgent' => '365CMS-Promos/' . CMS_PROMOS_VERSION,
            ]);
            if (empty($response['success'])) {
                $this->log_error('Click webhook failed: HTTP ' . (int) ($response['status'] ?? 0) . ' ' . (string) ($response['error'] ?? ''));
            }
        } catch (\Throwable $e) {
            $this->log_error('Click webhook failed: ' . $e->getMessage());
        }
    }

    // ── Hilfen ──────────────────────────────────────────────────────────────

    private function is_bot(): bool
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    private function is_promos_public_route(): bool
    {
        $path = function_exists('cms_plugin_public_path_without_lang')
            ? trim((string) cms_plugin_public_path_without_lang(), '/')
            : (string) preg_replace('#^en(?:/|$)#', '', trim(strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '')), '/'));

        return $this->archiveRendered || $path === 'promos' || str_starts_with($path, 'promos/');
    }

    private function is_admin_request(): bool
    {
        $path = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function normalize_slug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);

        return substr(trim($slug, '-'), 0, 120);
    }

    public function current_public_lang(): string
    {
        if (function_exists('cms_plugin_public_language')) {
            return cms_plugin_public_language() === 'en' ? 'en' : 'de';
        }

        $path = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');

        return $path === 'en' || str_starts_with($path, 'en/') ? 'en' : 'de';
    }

    public function localized_path(string $path, string $lang): string
    {
        if (function_exists('cms_plugin_public_localized_path')) {
            return (string) cms_plugin_public_localized_path($path, $lang);
        }

        $path = trim($path, '/');
        if ($path === '') {
            return $lang === 'en' ? '/en' : '/';
        }

        return $lang === 'en' ? '/en/' . $path : '/' . $path;
    }

    /**
     * @param array<string,mixed> $values
     */
    public function i18n_value(array $values, string $key, string $lang, string $fallback = ''): string
    {
        if (function_exists('cms_plugin_public_i18n_value')) {
            return (string) cms_plugin_public_i18n_value($values, $key, $lang, $fallback);
        }

        if ($lang === 'en' && isset($values[$key . '_en']) && trim((string) $values[$key . '_en']) !== '') {
            return (string) $values[$key . '_en'];
        }
        if (isset($values[$key]) && trim((string) $values[$key]) !== '') {
            return (string) $values[$key];
        }

        return $fallback;
    }

    public function public_label(string $key, string $lang): string
    {
        $labels = [
            'placement_kicker' => ['de' => 'Promo-Platzierung', 'en' => 'Promo placement'],
            'default_button' => ['de' => 'Mehr erfahren', 'en' => 'Learn more'],
            'promo_generic' => ['de' => 'Promo', 'en' => 'Promo'],
            'not_found_title' => ['de' => '404 – Promo nicht gefunden', 'en' => '404 – Promo not found'],
            'continue_cta' => ['de' => 'Weiter zur Promo', 'en' => 'Continue to promo'],
            'back_to_archive' => ['de' => 'Zur Promo-Übersicht', 'en' => 'Back to all promos'],
        ];

        return $labels[$key][$lang] ?? $labels[$key]['de'] ?? '';
    }

    private function log_error(string $message): void
    {
        error_log('[cms-promos] ' . $message);
    }
}

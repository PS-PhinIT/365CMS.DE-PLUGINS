<?php
/**
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Public_Controller
{
    private const FREQUENCY_COOKIE = 'cms_promos_freq';
    private static ?self $instance = null;

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

    public function register_routes($router): void
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

    public function archive_page(string $slug = ''): void
    {
        $lang = $this->current_public_lang();
        $slug = $this->normalize_slug($slug);
        $repository = CMS_Promos_Repository::instance();
        $settings = $repository->get_settings();
        $placements = $repository->get_placements();
        $currentPlacement = $slug !== '' ? $repository->get_placement_by_slug($slug) : null;
        $promos = $this->apply_frequency_cap($repository->get_active_promos($slug !== '' ? $slug : null));
        $repository->increment_impressions(array_map(static fn (array $promo): int => (int) ($promo['id'] ?? 0), $promos));

        $this->set_archive_meta($settings, $currentPlacement, $lang, $slug);

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

    public function redirect_promo(string $slug): void
    {
        $lang = $this->current_public_lang();
        $slug = $this->normalize_slug($slug);
        $promo = CMS_Promos_Repository::instance()->get_promo_by_slug($slug);
        $targetUrl = $promo !== null ? $this->build_target_url($promo) : '';
        if ($promo === null || $targetUrl === '' || !$this->is_promo_live($promo)) {
            http_response_code(404);
            header('X-Robots-Tag: noindex, nofollow', true);
            $theme = class_exists('CMS\\ThemeManager') ? \CMS\ThemeManager::instance() : null;
            $theme?->getHeader();
            echo '<main class="promos-shell promos-shell--404"><h1>' . $this->escape_html($this->public_label('not_found_title', $lang)) . '</h1>'
                . '<p><a href="' . $this->escape_attr($this->localized_path('promos', $lang)) . '">' . $this->escape_html($this->public_label('back_to_archive', $lang)) . '</a></p></main>';
            $theme?->getFooter();
            return;
        }

        $repository = CMS_Promos_Repository::instance();
        $repository->increment_click((int) $promo['id']);
        $this->dispatch_click_export_event($promo, $targetUrl, $lang, $repository->get_settings());
        if (headers_sent()) {
            $this->log_error('Cannot redirect promo click because headers were already sent');
            echo '<a href="' . $this->escape_attr($targetUrl) . '">' . $this->escape_html($this->public_label('continue_cta', $lang)) . '</a>';
            return;
        }

        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');
        header('Location: ' . $targetUrl, true, 302);
        exit;
    }

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

    private function render_theme_hook(string $themeHook): void
    {
        $lang = $this->current_public_lang();
        $repository = CMS_Promos_Repository::instance();
        $placements = $repository->get_hook_placements($themeHook);
        if ($placements === []) {
            return;
        }

        $settings = $repository->get_settings();

        foreach ($placements as $placement) {
            $placementId = (int) ($placement['id'] ?? 0);
            if ($placementId <= 0) {
                continue;
            }

            $promos = $this->apply_frequency_cap($repository->get_active_promos_for_placement($placementId, (int) ($placement['max_items'] ?? 0)));
            if ($promos === []) {
                continue;
            }

            $repository->increment_impressions(array_map(static fn (array $promo): int => (int) ($promo['id'] ?? 0), $promos));

            echo '<section class="promos-hook-zone promos-hook-zone--' . $this->escape_attr($themeHook) . '">';
            echo '<div class="promos-hook-zone__inner">';
            echo '<div class="promos-hook-zone__header">';
            echo '<span class="promos-kicker">' . $this->escape_html($this->public_label('placement_kicker', $lang)) . '</span>';
            echo '<h2 class="promos-hook-zone__title">' . $this->escape_html($this->i18n_value($placement, 'name', $lang, $this->public_label('placement_kicker', $lang))) . '</h2>';
            $placementDescription = $this->i18n_value($placement, 'description', $lang);
            if ($placementDescription !== '') {
                echo '<p class="promos-hook-zone__lead">' . $this->escape_html($placementDescription) . '</p>';
            }
            echo '</div>';
            echo '<div class="promos-hook-grid">';

            foreach ($promos as $promo) {
                $buttonLabel = trim((string) ($promo['button_label'] ?? ''));
                if ($buttonLabel === '') {
                    $buttonLabel = $this->i18n_value($settings, 'default_button_label', $lang, $this->public_label('default_button', $lang));
                } else {
                    $buttonLabel = $this->i18n_value($promo, 'button_label', $lang, $buttonLabel);
                }

                $targetBehavior = ((string) ($settings['default_target_behavior'] ?? 'same_tab') === 'new_tab') ? ' target="_blank" rel="noopener noreferrer"' : '';
                $redirectUrl = !empty($promo['target_url'])
                    ? $this->localized_path('promo/click/' . rawurlencode((string) ($promo['slug'] ?? '')), $lang)
                    : $this->localized_path('promos', $lang);

                echo '<article class="promos-hook-card' . (!empty($promo['is_featured']) ? ' promos-hook-card--featured' : '') . '">';
                if (!empty($promo['image_url'])) {
                    echo '<div class="promos-hook-card__media">';
                    echo '<img src="' . $this->escape_attr((string) $promo['image_url']) . '" alt="' . $this->escape_attr($this->i18n_value($promo, 'title', $lang, $this->public_label('promo_generic', $lang))) . '" loading="lazy">';
                    echo '</div>';
                }
                echo '<div class="promos-hook-card__content">';
                echo '<span class="promos-card__eyebrow">' . $this->escape_html($this->i18n_value($promo, 'placement_name', $lang, $this->public_label('promo_generic', $lang))) . '</span>';
                echo '<h3 class="promos-card__title">' . $this->escape_html($this->i18n_value($promo, 'title', $lang, $this->public_label('promo_generic', $lang))) . '</h3>';
                $teaser = $this->i18n_value($promo, 'teaser', $lang);
                if ($teaser !== '') {
                    echo '<p>' . $this->escape_html($teaser) . '</p>';
                }
                $contentHtml = $this->i18n_value($promo, 'content_html', $lang);
                if ($contentHtml !== '') {
                    echo '<div class="promos-hook-card__body">' . $this->sanitize_public_html($contentHtml) . '</div>';
                }
                echo '<a class="promos-card__button" href="' . $this->escape_attr($redirectUrl) . '"' . $targetBehavior . '>' . $this->escape_html($buttonLabel) . '</a>';
                echo '</div>';
                echo '</article>';
            }

            echo '</div>';
            echo '</div>';
            echo '</section>';
        }
    }

    /**
     * Titel, Beschreibung und Canonical der Archivseite für den Core-SEO-Dienst.
     *
     * @param array<string,string>     $settings
     * @param array<string,mixed>|null $placement
     */
    private function set_archive_meta(array $settings, ?array $placement, string $lang, string $slug): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }

        $title = $this->i18n_value($settings, 'archive_title', $lang, 'Promotions & Highlights');
        if ($placement !== null) {
            $title = $this->i18n_value($placement, 'name', $lang, $title) . ' – ' . $title;
        }
        $path = $slug !== '' ? 'promos/placement/' . rawurlencode($slug) : 'promos';

        try {
            \CMS\Services\SEOService::getInstance()->setRequestMeta([
                'title' => $title,
                'description' => mb_substr($this->i18n_value($settings, 'archive_description', $lang), 0, 160),
                'canonical_url' => rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/') . $this->localized_path($path, $lang),
                'robots_index' => $placement === null,
                'robots_follow' => true,
            ]);
        } catch (\Throwable $e) {
            $this->log_error('SEO meta could not be set: ' . $e->getMessage());
        }
    }

    /** Nur aktive Promos innerhalb ihrer Laufzeit dürfen per Klick-Route weiterleiten. */
    private function is_promo_live(array $promo): bool
    {
        if ((string) ($promo['status'] ?? '') !== 'active') {
            return false;
        }

        $now = time();
        $start = !empty($promo['start_at']) ? strtotime((string) $promo['start_at']) : false;
        $end = !empty($promo['end_at']) ? strtotime((string) $promo['end_at']) : false;

        return ($start === false || $start <= $now) && ($end === false || $end >= $now);
    }

    private function escape_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escape_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function normalize_slug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return substr($slug, 0, 120);
    }

    private function sanitize_redirect_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]]/', $url) === 1) {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            return '';
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return '';
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        return $url;
    }

    private function build_target_url(array $promo): string
    {
        $targetUrl = $this->sanitize_redirect_url((string) ($promo['target_url'] ?? ''));
        if ($targetUrl === '') {
            return '';
        }

        $utm = [
            'utm_source' => (string) ($promo['utm_source'] ?? ''),
            'utm_medium' => (string) ($promo['utm_medium'] ?? ''),
            'utm_campaign' => (string) ($promo['utm_campaign'] ?? ''),
            'utm_term' => (string) ($promo['utm_term'] ?? ''),
            'utm_content' => (string) ($promo['utm_content'] ?? ''),
        ];
        $utm = array_filter($utm, static fn (string $value): bool => trim($value) !== '');
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
            if (!array_key_exists($key, $existing) || trim((string) $existing[$key]) === '') {
                $existing[$key] = $value;
            }
        }

        $parts['query'] = http_build_query($existing);
        return $this->unparse_url($parts);
    }

    /**
     * @param array<string,mixed> $parts
     */
    private function unparse_url(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $auth = $user !== '' ? $user . ($pass !== '' ? ':' . $pass : '') . '@' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    /**
     * @param array<int,array<string,mixed>> $promos
     * @return array<int,array<string,mixed>>
     */
    private function apply_frequency_cap(array $promos): array
    {
        if ($promos === []) {
            return [];
        }

        $history = $this->read_frequency_history();
        $now = time();
        $changed = false;
        $filtered = [];

        foreach ($promos as $promo) {
            $promoId = (int) ($promo['id'] ?? 0);
            if ($promoId <= 0) {
                continue;
            }

            $cap = max(0, (int) ($promo['frequency_cap'] ?? 0));
            $windowHours = max(1, (int) ($promo['frequency_window_hours'] ?? 24));
            if ($cap <= 0) {
                $filtered[] = $promo;
                continue;
            }

            $windowStart = $now - ($windowHours * 3600);
            $key = (string) $promoId;
            $timestamps = array_values(array_filter(
                $history[$key] ?? [],
                static fn (int $timestamp): bool => $timestamp >= $windowStart
            ));

            if (count($timestamps) >= $cap) {
                $history[$key] = $timestamps;
                $changed = true;
                continue;
            }

            $timestamps[] = $now;
            $history[$key] = array_slice($timestamps, -20);
            $filtered[] = $promo;
            $changed = true;
        }

        if ($changed) {
            $this->write_frequency_history($history);
        }

        return $filtered;
    }

    /**
     * @return array<string,array<int,int>>
     */
    private function read_frequency_history(): array
    {
        $raw = (string) ($_COOKIE[self::FREQUENCY_COOKIE] ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $history = [];
        foreach ($decoded as $promoId => $timestamps) {
            if (!is_array($timestamps)) {
                continue;
            }
            $key = preg_replace('/[^0-9]/', '', (string) $promoId) ?? '';
            if ($key === '') {
                continue;
            }
            $history[$key] = array_values(array_filter(array_map('intval', $timestamps), static fn (int $timestamp): bool => $timestamp > 0));
        }

        return $history;
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
            $clean = array_values(array_filter(array_map('intval', $timestamps), static fn (int $timestamp): bool => $timestamp >= $cutoff));
            if ($clean !== []) {
                $normalized[$promoId] = array_slice($clean, -20);
            }
        }
        if (count($normalized) > 200) {
            $normalized = array_slice($normalized, -200, null, true);
        }

        if (headers_sent()) {
            return;
        }

        $payload = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        setcookie(self::FREQUENCY_COOKIE, $payload, [
            'expires' => $now + (30 * 24 * 3600),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::FREQUENCY_COOKIE] = $payload;
    }

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

        if (class_exists('CMS\\Hooks') && method_exists('CMS\\Hooks', 'doAction')) {
            \CMS\Hooks::doAction('cms_promos_click_export', $payload);
        }

        if ((string) ($settings['click_export_enabled'] ?? '0') !== '1') {
            return;
        }

        $webhookUrl = $this->sanitize_redirect_url((string) ($settings['click_export_webhook_url'] ?? ''));
        if ($webhookUrl === '') {
            return;
        }

        $this->send_webhook_json($webhookUrl, $payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function send_webhook_json(string $url, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            return;
        }

        // Ausschließlich über den Core-HTTP-Client (SSRF-Schutz: keine privaten/internen Ziele).
        if (!class_exists('CMS\\Http\\Client')) {
            return;
        }

        try {
            $response = \CMS\Http\Client::getInstance()->post($url, $body, [
                'headers' => ['Content-Type: application/json'],
                'timeout' => 2,
                'connectTimeout' => 1,
                'maxBytes' => 4096,
                'userAgent' => '365CMS-Promos/' . CMS_PROMOS_VERSION,
            ]);
            if (empty($response['success'])) {
                $this->log_error('Click export webhook failed: ' . (string) ($response['error'] ?? ('HTTP ' . (int) ($response['status'] ?? 0))));
            }
        } catch (\Throwable $e) {
            $this->log_error('Click export webhook failed: ' . $e->getMessage());
        }
    }

    private function current_public_lang(): string
    {
        if (function_exists('cms_plugin_public_language')) {
            return cms_plugin_public_language();
        }

        $path = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');
        return $path === 'en' || str_starts_with($path, 'en/') ? 'en' : 'de';
    }

    private function localized_path(string $path, string $lang): string
    {
        if (function_exists('cms_plugin_public_localized_path')) {
            return cms_plugin_public_localized_path($path, $lang);
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
    private function i18n_value(array $values, string $key, string $lang, string $fallback = ''): string
    {
        if (function_exists('cms_plugin_public_i18n_value')) {
            return cms_plugin_public_i18n_value($values, $key, $lang, $fallback);
        }

        if ($lang === 'en' && isset($values[$key . '_en']) && (string) $values[$key . '_en'] !== '') {
            return (string) $values[$key . '_en'];
        }
        if (isset($values[$key]) && (string) $values[$key] !== '') {
            return (string) $values[$key];
        }
        return $fallback;
    }

    private function public_label(string $key, string $lang): string
    {
        $labels = [
            'placement_kicker' => ['de' => 'Promo-Platzierung', 'en' => 'Promo placement'],
            'default_button' => ['de' => 'Mehr erfahren', 'en' => 'Learn more'],
            'promo_generic' => ['de' => 'Promo', 'en' => 'Promo'],
            'not_found_title' => ['de' => '404 - Promo nicht gefunden', 'en' => '404 - Promo not found'],
            'continue_cta' => ['de' => 'Weiter zur Promo', 'en' => 'Continue to promo'],
            'back_to_archive' => ['de' => 'Zur Promo-Übersicht', 'en' => 'Back to all promos'],
        ];

        return $labels[$key][$lang] ?? $labels[$key]['de'] ?? '';
    }

    private function sanitize_public_html(string $html): string
    {
        $html = trim(strip_tags($html, '<p><a><strong><em><ul><ol><li><br><h2><h3><h4><span>'));
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        return preg_replace_callback('/\s+href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', function (array $matches): string {
            $href = html_entity_decode((string) ($matches[2] ?? $matches[3] ?? $matches[4] ?? ''), ENT_QUOTES, 'UTF-8');
            $safeHref = $this->sanitize_redirect_url($href);
            return $safeHref !== '' ? ' href="' . $this->escape_attr($safeHref) . '"' : '';
        }, $html) ?? '';
    }

    private function log_error(string $message): void
    {
        error_log('[cms-promos] ' . $message);
    }
}

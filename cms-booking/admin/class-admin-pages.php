<?php
/**
 * CMS Booking – Admin-Seiten (Layout-Vertrag 4.0.0)
 *
 * Der Core-AdminRouter liefert Shell (Header/Sidebar/Footer), lädt `admin/partials/plugin-workspace.php`
 * und puffert die Ausgabe. Die Callbacks geben deshalb nur Inhalt aus:
 * `cms_plugin_workspace_open()` → Unterseite → `cms_plugin_workspace_close()`.
 *
 * POST-Anfragen werden vor jeder Ausgabe verarbeitet (Capability + CSRF) und per 303 auf die
 * eigene Unterseite zurückgeleitet (PRG, Meldung über die Session).
 *
 * Öffentliche statische Einstiegspunkte (auch vom Core-Fallback genutzt):
 * render_dashboard, render_bookings, render_providers, render_services, render_settings.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Traits laden
$traitsDir = CMS_BOOKING_PLUGIN_DIR . 'admin/modules/';
$traitsDirReal = is_dir($traitsDir) ? realpath($traitsDir) : false;
if ($traitsDirReal !== false) {
    $traitFiles = glob($traitsDirReal . DIRECTORY_SEPARATOR . 'trait-*.php') ?: [];
    sort($traitFiles);
    foreach ($traitFiles as $traitFile) {
        $traitReal = realpath($traitFile);
        if ($traitReal === false || strpos($traitReal, $traitsDirReal . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }
        require_once $traitReal;
    }
}

final class CMS_Booking_Admin_Pages
{
    use CMS_Booking_Page_Dashboard_Trait;
    use CMS_Booking_Page_Bookings_Trait;
    use CMS_Booking_Page_Providers_Trait;
    use CMS_Booking_Page_Services_Trait;
    use CMS_Booking_Page_Settings_Trait;

    private static ?self $instance = null;

    /** Fehlermeldung der aktuellen POST-Anfrage (bei Erfolg wird per PRG umgeleitet). */
    protected string $postError = '';

    private const DEFAULT_PAGE_SLUG = 'booking';
    private const FLASH_KEY = 'cms_booking_admin_flash';

    /** Unterseiten-Slug => Render-Methode (inkl. der Alias-Slugs aus dem Core-Fallback). */
    private const PAGE_CALLBACKS = [
        'booking'            => 'render_dashboard',
        'booking-dashboard'  => 'render_dashboard',
        'bookings'           => 'render_bookings',
        'booking-bookings'   => 'render_bookings',
        'providers'          => 'render_providers',
        'booking-providers'  => 'render_providers',
        'services'           => 'render_services',
        'booking-services'   => 'render_services',
        'settings'           => 'render_settings',
        'booking-settings'   => 'render_settings',
    ];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /* ================================================================== */
    /*  Einstiegspunkte                                                    */
    /* ================================================================== */

    public static function render_dashboard(): void
    {
        self::render_page('booking', static fn (self $page) => $page->render_dashboard_page());
    }

    public static function render_bookings(): void
    {
        self::render_page('bookings', static fn (self $page) => $page->render_bookings_page());
    }

    public static function render_providers(): void
    {
        self::render_page('providers', static fn (self $page) => $page->render_providers_page());
    }

    public static function render_services(): void
    {
        self::render_page('services', static fn (self $page) => $page->render_services_page());
    }

    public static function render_settings(): void
    {
        self::render_page('settings', static fn (self $page) => $page->render_settings_page());
    }

    /** Dispatcher über `$_GET['page']` (Router setzt die Unterseite bei Pfad-Routen). */
    public static function dispatch(): void
    {
        $slug = self::normalize_slug((string) ($_GET['page'] ?? self::DEFAULT_PAGE_SLUG));
        $method = self::PAGE_CALLBACKS[$slug] ?? 'render_dashboard';
        self::$method();
    }

    // Kompatibilität 3.x (frühere Menü-Callbacks)
    public static function dispatch_dashboard(): void { self::render_dashboard(); }
    public static function dispatch_bookings(): void { self::render_bookings(); }
    public static function dispatch_providers(): void { self::render_providers(); }
    public static function dispatch_services(): void { self::render_services(); }
    public static function dispatch_settings(): void { self::render_settings(); }

    /** Legacy-Haupt-Routing (`?section=…`). */
    public function render(): void
    {
        $_GET['page'] = self::normalize_slug((string) ($_GET['section'] ?? self::DEFAULT_PAGE_SLUG)) ?: self::DEFAULT_PAGE_SLUG;
        self::dispatch();
    }

    /* ================================================================== */
    /*  Layout                                                             */
    /* ================================================================== */

    /**
     * @param callable(self): void $renderer
     */
    private static function render_page(string $active, callable $renderer): void
    {
        self::check_access();
        $page = self::instance();

        // POST vor jeder Ausgabe verarbeiten (Erfolg → 303-Weiterleitung, Download → exit).
        if (self::is_post() && CMS_Booking_Installer::tables_present()) {
            try {
                $page->postError = match ($active) {
                    'bookings'  => $page->handle_bookings_post(),
                    'providers' => $page->handle_providers_post(),
                    'services'  => $page->handle_services_post(),
                    'settings'  => $page->handle_settings_post(),
                    default     => '',
                };
            } catch (\Throwable $e) {
                error_log('[cms-booking] admin action failed: ' . $e->getMessage());
                $page->postError = 'Die Aktion konnte nicht ausgeführt werden.';
            }
        }

        self::load_workspace();
        self::enqueue_admin_assets();

        $pending = 0;
        $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'today' => 0, 'week' => 0];
        $providersActive = 0;
        $tablesOk = CMS_Booking_Installer::tables_present();
        if ($tablesOk) {
            try {
                $stats = CMS_Booking_Bookings::instance()->get_stats();
                $pending = $stats['pending'];
                $providersActive = CMS_Booking_Providers::instance()->count('active');
            } catch (\Throwable $e) {
                $tablesOk = false;
            }
        }

        $status = $tablesOk
            ? ['label' => 'Aktiv', 'tone' => 'success']
            : ['label' => 'Tabellen fehlen', 'tone' => 'danger'];
        if ($tablesOk && (CMS_Booking_Settings::get('admin_email') === '' || CMS_Booking_Settings::sender_email() === '')) {
            $status = ['label' => 'Mail-Konfiguration unvollständig', 'tone' => 'warning'];
        }

        echo cms_plugin_workspace_open([
            'plugin'      => CMS_Booking_Admin_Menu::MENU_SLUG,
            'title'       => CMS_Booking_Admin_Menu::MENU_TITLE,
            'icon'        => CMS_Booking_Admin_Menu::MENU_ICON,
            'version'     => CMS_BOOKING_VERSION,
            'status'      => $status,
            'description' => 'Termine, Anbieter und Leistungen – Online-Buchung mit Kalender und Mails.',
            'stats'       => [
                ['label' => 'Offene Anfragen', 'value' => $pending, 'icon' => 'ti ti-inbox', 'tone' => $pending > 0 ? 'warning' : '', 'url' => self::admin_url('bookings', ['status' => 'pending'])],
                ['label' => 'Termine heute', 'value' => $stats['today'], 'icon' => 'ti ti-calendar-due', 'url' => self::admin_url('bookings', ['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')])],
                ['label' => 'Nächste 7 Tage', 'value' => $stats['week'], 'icon' => 'ti ti-calendar-week'],
                ['label' => 'Aktive Anbieter', 'value' => $providersActive, 'icon' => 'ti ti-users', 'url' => self::admin_url('providers', ['status' => 'active'])],
            ],
            'nav'          => [
                ['slug' => 'booking', 'label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard', 'url' => self::admin_url('booking'), 'active' => $active === 'booking'],
                ['slug' => 'bookings', 'label' => 'Buchungen', 'icon' => 'ti ti-calendar', 'badge' => $pending, 'badge_tone' => 'warning', 'url' => self::admin_url('bookings'), 'active' => $active === 'bookings'],
                ['slug' => 'providers', 'label' => 'Anbieter', 'icon' => 'ti ti-users', 'url' => self::admin_url('providers'), 'active' => $active === 'providers'],
                ['slug' => 'services', 'label' => 'Leistungen', 'icon' => 'ti ti-briefcase', 'url' => self::admin_url('services'), 'active' => $active === 'services'],
                ['slug' => 'settings', 'label' => 'Einstellungen', 'icon' => 'ti ti-settings', 'url' => self::admin_url('settings'), 'active' => $active === 'settings'],
            ],
            'nav_explicit' => true,
        ]);

        echo '<div class="booking-admin">';
        if (!$tablesOk) {
            echo '<div class="alert alert-danger" role="alert">Die Tabellen des Buchungssystems fehlen oder sind nicht erreichbar. Bitte das Plugin deaktivieren und wieder aktivieren.</div>';
        } else {
            try {
                $renderer($page);
            } catch (\Throwable $e) {
                error_log('[cms-booking] admin page failed: ' . $e->getMessage());
                echo '<div class="alert alert-danger" role="alert">Die Seite konnte nicht geladen werden.</div>';
            }
        }
        echo '</div>';

        echo cms_plugin_workspace_close();
        self::enqueue_admin_scripts();
    }

    private static function load_workspace(): void
    {
        if (!function_exists('cms_plugin_workspace_open')) {
            $core = ABSPATH . 'admin/partials/plugin-workspace.php';
            if (is_file($core)) {
                require_once $core;
            }
        }
        if (!function_exists('cms_plugin_workspace_open')) {
            require_once CMS_BOOKING_PLUGIN_DIR . 'includes/plugin-workspace.php';
            // Rückfall-CSS nur, wenn der Core das Layout nicht liefert
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/css/cms-plugin-workspace.css?v=' . CMS_BOOKING_VERSION, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    /* ================================================================== */
    /*  Gemeinsame Hilfsmethoden                                           */
    /* ================================================================== */

    protected static function check_access(): bool
    {
        if (!class_exists('CMS\Auth') || !\CMS\Auth::instance()->isAdmin()) {
            header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/', true, 303);
            exit;
        }
        return true;
    }

    /** Capability-Check für zustandsverändernde Admin-Aktionen. */
    protected static function can_manage_admin_actions(): bool
    {
        if (!class_exists('CMS\Auth') || !\CMS\Auth::instance()->isAdmin()) {
            return false;
        }

        return !function_exists('current_user_can') || current_user_can('manage_options');
    }

    protected static function is_post(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    /**
     * Capability + CSRF (Einmal-Token) für POST-Aktionen. Liefert eine Fehlermeldung oder ''.
     */
    protected static function guard_post(string $action, bool $persistent = false): string
    {
        if (!self::can_manage_admin_actions()) {
            return 'Keine Berechtigung für diese Aktion.';
        }
        if (!class_exists('CMS\Security')) {
            return 'Sicherheitsprüfung nicht verfügbar.';
        }
        $token = $_POST['csrf_token'] ?? '';
        $security = \CMS\Security::instance();
        $valid = $persistent
            ? (is_string($token) && $security->verifyPersistentToken($token, $action))
            : $security->verifyToken($token, $action);

        return $valid ? '' : 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.';
    }

    protected static function generate_nonce(string $action): string
    {
        return class_exists('CMS\Security') ? \CMS\Security::instance()->generateToken($action) : '';
    }

    /** Ausgewählte IDs einer Sammelaktion. @return list<int> */
    protected static function posted_ids(string $key = 'ids'): array
    {
        $raw = $_POST[$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
    }

    protected static function post_text(string $key, int $maxLength = 255): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        $value = function_exists('sanitize_text_field') ? sanitize_text_field((string) $value) : trim(strip_tags((string) $value));

        return mb_substr($value, 0, $maxLength);
    }

    protected static function get_text(string $key, int $maxLength = 100): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        return mb_substr(trim(strip_tags((string) $value)), 0, $maxLength);
    }

    /** PRG: Meldung merken und auf eine eigene Unterseite umleiten. */
    protected static function redirect_with(string $slug, array $query, string $type, string $message): never
    {
        if (session_status() === PHP_SESSION_ACTIVE || isset($_SESSION)) {
            $_SESSION[self::FLASH_KEY] = ['type' => $type === 'success' ? 'success' : 'danger', 'message' => $message];
        }
        $path = self::admin_url($slug, $query);
        if (class_exists('CMS\Security') && method_exists('CMS\Security', 'safeLocalPath')) {
            $path = \CMS\Security::safeLocalPath($path, self::admin_url($slug));
        }
        header('Location: ' . (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '') . $path, true, 303);
        exit;
    }

    /** @return array{type: string, message: string}|null */
    protected static function take_flash(): ?array
    {
        $flash = $_SESSION[self::FLASH_KEY] ?? null;
        unset($_SESSION[self::FLASH_KEY]);

        return is_array($flash) && isset($flash['type'], $flash['message']) ? $flash : null;
    }

    /**
     * Meldungen ausgeben (Flash aus PRG oder Fehler der aktuellen Anfrage).
     */
    protected static function render_alerts(string $error = '', string $success = ''): void
    {
        $flash = self::take_flash();
        if ($flash !== null) {
            echo '<div class="alert alert-' . ($flash['type'] === 'success' ? 'success' : 'danger') . '" role="status">'
                . self::esc((string) $flash['message']) . '</div>';
        }
        if ($success !== '') {
            echo '<div class="alert alert-success" role="status">' . self::esc($success) . '</div>';
        }
        if ($error !== '') {
            echo '<div class="alert alert-danger" role="alert">' . self::esc($error) . '</div>';
        }
    }

    protected static function enqueue_admin_assets(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $css = CMS_BOOKING_PLUGIN_DIR . 'assets/css/booking-admin.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/css/booking-admin.css?v=' . CMS_BOOKING_VERSION . '-' . filemtime($css), ENT_QUOTES, 'UTF-8')
                . '">' . "\n";
        }
    }

    protected static function enqueue_admin_scripts(): void
    {
        $js = CMS_BOOKING_PLUGIN_DIR . 'assets/js/booking-admin.js';
        if (is_file($js)) {
            echo '<script src="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/js/booking-admin.js?v=' . CMS_BOOKING_VERSION . '-' . filemtime($js), ENT_QUOTES, 'UTF-8')
                . '" defer></script>' . "\n";
        }
    }

    /**
     * Admin-URL einer Unterseite (`/admin/plugins/booking/<slug>`).
     *
     * @param array<string, string|int> $query
     */
    public static function admin_url(string $slug, array $query = []): string
    {
        $slug = self::normalize_slug($slug);
        if ($slug === '') {
            $slug = self::DEFAULT_PAGE_SLUG;
        }

        $url = '/admin/plugins/' . CMS_Booking_Admin_Menu::MENU_SLUG . '/' . rawurlencode($slug);
        $query = array_filter($query, static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    protected static function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Paginierung (Zurück/Weiter).
     *
     * @param array<string, string|int> $query
     */
    protected static function render_pagination(string $slug, array $query, int $page, int $pages): void
    {
        if ($pages <= 1) {
            return;
        }
        echo '<nav class="booking-admin-pagination" aria-label="Seiten">';
        if ($page > 1) {
            echo '<a class="btn btn-secondary btn-sm" href="' . self::esc(self::admin_url($slug, $query + ['paged' => $page - 1])) . '"><i class="ti ti-chevron-left" aria-hidden="true"></i> Zurück</a>';
        }
        echo '<span class="booking-admin-pagination__info">Seite ' . (int) $page . ' von ' . (int) $pages . '</span>';
        if ($page < $pages) {
            echo '<a class="btn btn-secondary btn-sm" href="' . self::esc(self::admin_url($slug, $query + ['paged' => $page + 1])) . '">Weiter <i class="ti ti-chevron-right" aria-hidden="true"></i></a>';
        }
        echo '</nav>';
    }

    private static function normalize_slug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        return trim($slug, '-');
    }
}

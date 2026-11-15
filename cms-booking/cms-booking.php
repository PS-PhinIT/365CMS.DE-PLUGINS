<?php
/**
 * Plugin Name: CMS Booking
 * Plugin URI:  https://365network.de/cms-booking
 * Description: Universelles Buchungs- und Terminverwaltungssystem – Experten, Speaker, Events, Unternehmen u. a. klinken sich ein.
 * Version:     4.0.1
 * Author:      365 Network
 * Author URI:  https://365network.de
 * Requires CMS: 4.0.0
 * Requires at least: 4.0.0
 * Requires PHP: 8.4.1
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ── Konstanten ────────────────────────────────────────────────────────────────
defined('CMS_BOOKING_VERSION') || define('CMS_BOOKING_VERSION', '4.0.1');
defined('CMS_BOOKING_DB_VERSION') || define('CMS_BOOKING_DB_VERSION', '3');
defined('CMS_BOOKING_PLUGIN_DIR') || define('CMS_BOOKING_PLUGIN_DIR', __DIR__ . '/');
defined('CMS_BOOKING_PLUGIN_URL') || define('CMS_BOOKING_PLUGIN_URL', '/plugins/cms-booking/');

if (!class_exists('CMS_Booking', false)) {
final class CMS_Booking
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

    /* ------------------------------------------------------------------ */
    /*  Abhängigkeiten laden                                               */
    /* ------------------------------------------------------------------ */

    private function load_dependencies(): void
    {
        $files = [
            'includes/class-installer.php',
            'includes/class-providers.php',
            'includes/class-services.php',
            'includes/class-availability.php',
            'includes/class-bookings.php',
            'includes/class-notifications.php',
            'includes/class-calendar-export.php',
            'includes/class-integration.php',
            'includes/class-frontend.php',
            'admin/class-admin-menu.php',
            'admin/class-admin-pages.php',
        ];

        foreach ($files as $file) {
            $path = CMS_BOOKING_PLUGIN_DIR . $file;
            if (is_file($path)) {
                require_once $path;
                continue;
            }
            error_log('[cms-booking] Missing dependency file: ' . $file);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Hooks                                                              */
    /* ------------------------------------------------------------------ */

    private function init_hooks(): void
    {
        if (!class_exists('CMS\Hooks')) {
            return;
        }

        // Kern
        \CMS\Hooks::addAction('cms_init',            [$this, 'init_plugin'],                     10);

        // Admin
        \CMS\Hooks::addAction('cms_admin_menu',       [CMS_Booking_Admin_Menu::class, 'register'], 10);

        // Frontend-Routen
        \CMS\Hooks::addAction('register_routes',      [CMS_Booking_Frontend::class, 'instance'],   10);
        // Member-Dashboard
        \CMS\Hooks::addAction('member_dashboard_init', [$this, 'register_member_section'],          20);

        // Assets
        \CMS\Hooks::addAction('head',                 [$this, 'enqueue_styles'],                   20);
        \CMS\Hooks::addAction('body_end',             [$this, 'enqueue_scripts'],                  20);

        // Integration – gibt anderen Plugins die Möglichkeit, sich zu registrieren
        \CMS\Hooks::addAction('cms_init',             [CMS_Booking_Integration::class, 'init'],    50);

        // Cron: Terminerinnerungen
        \CMS\Hooks::addAction('cms_cron_hourly',      [$this, 'run_hourly_cron'],                  10);

        // 4.0.0: Der Core-Router prüft öffentliche POSTs global (form_guard). Das Buchungsformular nutzt
        // sein eigenes Token `booking_submit`, das hier für die Router-Prüfung benannt wird.
        \CMS\Hooks::addFilter('cms_public_form_persistent_token', [$this, 'public_form_token'], 10);

        // Sitemap: öffentliche Anbieter-Buchungsseiten
        \CMS\Hooks::addFilter('cms_sitemap_entries',  [$this, 'sitemap_entries'],                  10);

        // DSGVO
        \CMS\Hooks::addAction('dsgvo_export_data',    [$this, 'export_user_data'],                 10);
        \CMS\Hooks::addAction('dsgvo_delete_data',    [$this, 'delete_user_data'],                 10);
    }

    /* ------------------------------------------------------------------ */
    /*  Lifecycle                                                          */
    /* ------------------------------------------------------------------ */

    public function init_plugin(): void
    {
        if (class_exists('CMS_Booking_Installer')) {
            CMS_Booking_Installer::maybe_install();
        }

        // Benachrichtigungen hängen sich an booking_created/booking_status_* (vor 4.0.0 nie registriert).
        CMS_Booking_Notifications::instance();
        $this->register_core_integrations();
    }

    /**
     * Aufbewahrungsregel (Anonymisierung alter Buchungen) und Systemhinweis.
     */
    private function register_core_integrations(): void
    {
        if (class_exists('CMS\\Services\\DataRetentionService') && class_exists('CMS\\Services\\Retention\\RetentionRule')) {
            try {
                \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                    'cms_booking_customers',
                    'Buchungen: Kundendaten',
                    'Anonymisiert Name, E-Mail, Telefon und Notizen abgeschlossener, stornierter und nicht wahrgenommener Termine nach Ablauf der Frist.',
                    static fn (): int => (int) (CMS_Booking_Installer::read_settings()['retention_days'] ?? 0),
                    static fn (): int => CMS_Booking_Bookings::instance()->count_anonymizable((int) (CMS_Booking_Installer::read_settings()['retention_days'] ?? 0)),
                    static fn (): int => CMS_Booking_Bookings::instance()->anonymize_older_than((int) (CMS_Booking_Installer::read_settings()['retention_days'] ?? 0)),
                    '/admin/plugins/booking/settings',
                    \CMS\Services\Retention\RetentionRule::ACTION_ANONYMIZE
                ));
            } catch (\Throwable $e) {
                error_log('[cms-booking] Aufbewahrungsregel nicht registriert: ' . $e->getMessage());
            }
        }

        if (class_exists('CMS\\Services\\AdminNoticeService')) {
            \CMS\Services\AdminNoticeService::registerCheck('cms_booking_pending', static function (): ?array {
                $stale = CMS_Booking_Bookings::instance()->count_stale_pending();
                if ($stale <= 0) {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms_booking_pending',
                    'warning',
                    'Unbearbeitete Buchungsanfragen',
                    sprintf('%d Anfrage(n) wurden vor ihrem Termin weder bestätigt noch abgelehnt.', $stale),
                    '/admin/plugins/booking/bookings?status=pending',
                    'Anfragen prüfen'
                );
            });
        }
    }

    /**
     * Filter `cms_public_form_persistent_token` (\CMS\Router::PUBLIC_FORM_TOKEN_FILTER).
     *
     * @return array{action:string,token:string}|null
     */
    public function public_form_token(mixed $current, mixed $routingUri = '', mixed $method = 'GET'): ?array
    {
        if (is_array($current) || strtoupper((string) $method) !== 'POST') {
            return is_array($current) ? $current : null;
        }
        if (preg_match('#^/booking/[a-z0-9_-]+/[a-z0-9_-]+/?$#i', (string) $routingUri) !== 1) {
            return null;
        }

        return ['action' => 'booking_submit', 'token' => (string) ($_POST['csrf_token'] ?? '')];
    }

    public function run_hourly_cron(): void
    {
        try {
            CMS_Booking_Notifications::instance()->send_due_reminders();
        } catch (\Throwable $e) {
            error_log('[cms-booking] reminder cron failed: ' . $e->getMessage());
        }
    }

    /**
     * Aktive Anbieter mit mindestens einer aktiven Leistung für plugins.xml.
     *
     * @return array<int,mixed>
     */
    public function sitemap_entries(mixed $entries): array
    {
        $entries = is_array($entries) ? $entries : [];
        try {
            $db = \CMS\Database::instance();
            $p = $db->getPrefix();
            $stmt = $db->prepare(
                "SELECT bp.slug, MAX(GREATEST(bp.updated_at, bs.updated_at)) AS lastmod
                 FROM {$p}booking_providers bp
                 INNER JOIN {$p}booking_services bs ON bs.provider_id = bp.id AND bs.status = 'active'
                 WHERE bp.status = 'active'
                 GROUP BY bp.id, bp.slug
                 LIMIT 500"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $entries[] = ['url' => 'booking/' . rawurlencode((string) $row['slug']), 'lastmod' => (string) $row['lastmod'], 'changefreq' => 'weekly', 'priority' => 0.5];
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] sitemap entries failed: ' . $e->getMessage());
        }

        return $entries;
    }

    /* ------------------------------------------------------------------ */
    /*  Assets                                                             */
    /* ------------------------------------------------------------------ */

    public function enqueue_styles(): void
    {
        if (!$this->is_booking_public_route()) {
            return;
        }

        $css = CMS_BOOKING_PLUGIN_DIR . 'assets/css/booking-public.css';
        if (file_exists($css)) {
            echo '<link rel="stylesheet" href="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/css/booking-public.css', ENT_QUOTES, 'UTF-8')
                . '?v=' . filemtime($css) . '">' . "\n";
        }

        // Primärfarbe aus den Einstellungen (validiert) per nonce-geschütztem Style-Block statt style-Attributen.
        $color = strtolower((string) (CMS_Booking_Installer::read_settings()['primary_color'] ?? ''));
        if (preg_match('/^#[0-9a-f]{6}$/', $color) === 1 && $color !== '#3b82f6' && class_exists('CMS\\Security')) {
            echo '<style ' . \CMS\Security::instance()->nonceAttr() . '>:root{--booking-primary:' . $color . '}</style>' . "\n";
        }
    }

    public function enqueue_scripts(): void
    {
        if (!$this->is_booking_public_route()) {
            return;
        }

        $js = CMS_BOOKING_PLUGIN_DIR . 'assets/js/booking-public.js';
        if (file_exists($js)) {
            echo '<script src="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/js/booking-public.js', ENT_QUOTES, 'UTF-8')
                . '?v=' . filemtime($js) . '" defer></script>' . "\n";
        }
    }

    private function is_booking_public_route(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return false;
        }

        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $path = '/' . trim($path, '/');

        if (
            $path === '/booking'
            || $path === '/booking/'
            || preg_match('#^/(?:en/)?booking/[^/]+(?:/[^/]+)?$#', $path) === 1
        ) {
            return true;
        }

        if (preg_match('#^/(?:en/)?booking/confirm/\d+$#', $path) === 1) {
            return true;
        }

        if (preg_match('#^/(?:en/)?booking/ical/\d+$#', $path) === 1) {
            return true;
        }

        return preg_match('#^/(?:en/)?api/booking/slots/\d+/\d{4}-\d{2}-\d{2}$#', $path) === 1;
    }

    /* ------------------------------------------------------------------ */
    /*  Member Dashboard                                                    */
    /* ------------------------------------------------------------------ */

    public function register_member_section(\CMS\Member\PluginDashboardRegistry $registry): void
    {
        $registry->register([
            'plugin'          => 'cms-booking',
            'slug'            => 'booking',
            'label'           => 'Buchungen',
            'icon'            => 'ti-calendar-event',
            'capability'      => 'member',
            'category'        => 'plugins',
            'priority'        => 60,
            'admin_url'       => '/admin/plugins/booking/bookings',
            'render_callback' => [$this, 'render_member_page'],
        ]);
    }

    public function render_member_page(object $user, array $params = []): void
    {
        $bookings = class_exists('CMS_Booking_Bookings')
            ? CMS_Booking_Bookings::instance()->get_by_user((int) $user->id, 0, 20)
            : [];

        $statusColors = [
            'pending'   => 'inactive',
            'confirmed' => 'active',
            'cancelled' => 'danger',
            'completed' => 'active',
            'no_show'   => 'danger',
        ];
        $statusLabels = class_exists('CMS_Booking_Bookings') ? CMS_Booking_Bookings::status_labels() : [];
        ?>
        <div class="cms-member-section">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <p><strong>Keine Buchungen vorhanden</strong></p>
                    <p class="text-muted">Sobald Termine gebucht wurden, erscheinen sie hier.</p>
                </div>
            <?php else: ?>
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Anbieter</th>
                                <th>Datum</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $row): ?>
                            <?php $statusClass = $statusColors[$row['status'] ?? ''] ?? 'inactive'; ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($row['service_title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['provider_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(trim(date('d.m.Y', (int) strtotime((string) ($row['booking_date'] ?? 'now'))) . ' ' . substr((string) ($row['start_time'] ?? ''), 0, 5)), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) ($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '—')), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  DSGVO                                                              */
    /* ------------------------------------------------------------------ */

    public function export_user_data(mixed $userId = null): void
    {
        if (class_exists('CMS_Booking_Bookings')) {
            CMS_Booking_Bookings::instance()->export_user_data($userId);
        }
    }

    public function delete_user_data(mixed $userId = null): void
    {
        if (class_exists('CMS_Booking_Bookings')) {
            CMS_Booking_Bookings::instance()->delete_user_data($userId);
        }
    }
}
}

/*
 * Lebenszyklus-Callbacks (PluginManager: <slug>_activate/_deactivate/_uninstall).
 */
if (!function_exists('cms_booking_activate')) {
    function cms_booking_activate(): void
    {
        CMS_Booking_Installer::install();
    }
}

if (!function_exists('cms_booking_deactivate')) {
    function cms_booking_deactivate(): void
    {
        // Buchungsdaten bleiben erhalten.
    }
}

if (!function_exists('cms_booking_uninstall')) {
    function cms_booking_uninstall(): void
    {
        CMS_Booking_Installer::uninstall();
    }
}

CMS_Booking::instance();

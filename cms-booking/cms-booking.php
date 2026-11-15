<?php
/**
 * Plugin Name: CMS Booking
 * Plugin URI:  https://365network.de/cms-booking
 * Description: Universelles Buchungs- und Terminverwaltungssystem – Experten, Speaker, Events, Unternehmen u. a. klinken sich ein.
 * Version:     4.0.0
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
defined('CMS_BOOKING_VERSION') || define('CMS_BOOKING_VERSION', '4.0.0');
defined('CMS_BOOKING_DB_VERSION') || define('CMS_BOOKING_DB_VERSION', '3');
defined('CMS_BOOKING_PLUGIN_DIR') || define('CMS_BOOKING_PLUGIN_DIR', dirname(__FILE__) . '/');
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
        $root = realpath(CMS_BOOKING_PLUGIN_DIR);
        if ($root === false) {
            return;
        }
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $files = [
            'includes/class-settings.php',
            'includes/class-installer.php',
            'includes/class-providers.php',
            'includes/class-services.php',
            'includes/class-availability.php',
            'includes/class-bookings.php',
            'includes/class-notifications.php',
            'includes/class-calendar-export.php',
            'includes/class-integration.php',
            'includes/class-privacy.php',
            'includes/class-public-index.php',
            'includes/class-frontend.php',
            'admin/class-admin-menu.php',
            'admin/class-admin-pages.php',
        ];

        foreach ($files as $relative) {
            $real = realpath($rootPrefix . $relative);
            if ($real !== false && is_file($real) && str_starts_with($real, $rootPrefix)) {
                require_once $real;
            }
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

        // Kern (Schema-Migration nur bei geänderter Schema-Version, Benachrichtigungen, Integrationen)
        \CMS\Hooks::addAction('cms_init',             [$this, 'init_plugin'],                       10);
        \CMS\Hooks::addAction('cms_init',             [CMS_Booking_Privacy::class, 'register_retention_rule'], 20);
        \CMS\Hooks::addAction('cms_init',             [$this, 'register_admin_notices'],            20);
        \CMS\Hooks::addAction('cms_init',             [CMS_Booking_Integration::class, 'init'],     50);

        // Admin
        \CMS\Hooks::addAction('cms_admin_menu',       [CMS_Booking_Admin_Menu::class, 'register'],  10);

        // Frontend-Routen + Token-Freigabe der öffentlichen Formulare für den Core-Router
        \CMS\Hooks::addAction('register_routes',      [CMS_Booking_Frontend::class, 'instance'],    10);
        \CMS\Hooks::addFilter('cms_public_form_persistent_token', [CMS_Booking_Frontend::class, 'public_form_token'], 10);

        // Sitemap und Suche (öffentliche Anbieter-/Leistungsseiten)
        \CMS\Hooks::addFilter('cms_sitemap_entries',  [CMS_Booking_Public_Index::class, 'sitemap_entries'], 20);
        \CMS\Hooks::addFilter('search_results',       [CMS_Booking_Public_Index::class, 'search_results'],  20);

        // Member-Dashboard
        \CMS\Hooks::addAction('member_dashboard_init', [$this, 'register_member_section'],           20);

        // Assets (nur auf eigenen öffentlichen Routen)
        \CMS\Hooks::addAction('head',                 [$this, 'enqueue_styles'],                    20);
        \CMS\Hooks::addAction('body_end',             [$this, 'enqueue_scripts'],                   20);

        // DSGVO
        \CMS\Hooks::addAction('dsgvo_export_data',    [$this, 'export_user_data'],                  10);
        \CMS\Hooks::addAction('dsgvo_delete_data',    [$this, 'delete_user_data'],                  10);
        \CMS\Hooks::addFilter('cms_member_export_data', [CMS_Booking_Privacy::class, 'member_export'], 20);
        \CMS\Hooks::addAction('cms_cron_daily',       [$this, 'cron_daily'],                        45);
    }

    /* ------------------------------------------------------------------ */
    /*  Lifecycle                                                          */
    /* ------------------------------------------------------------------ */

    public function init_plugin(): void
    {
        try {
            CMS_Booking_Installer::maybe_install();
        } catch (\Throwable $e) {
            error_log('[cms-booking] init install skipped: ' . $e->getMessage());
        }

        // Registriert die Hooks booking_created / booking_status_* / cms_cron_hourly.
        CMS_Booking_Notifications::instance();
    }

    public function cron_daily(): void
    {
        try {
            CMS_Booking_Privacy::prune_exports();
        } catch (\Throwable $e) {
            error_log('[cms-booking] export cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Systemhinweise im Admin (Sidebar-Footer, vom Core gecacht).
     */
    public function register_admin_notices(): void
    {
        if (!class_exists('CMS\Services\AdminNoticeService') || !method_exists('CMS\Services\AdminNoticeService', 'registerCheck')) {
            return;
        }

        try {
            \CMS\Services\AdminNoticeService::registerCheck('CMS Booking: offene Anfragen', static function (): ?array {
                $pending = CMS_Booking_Bookings::instance()->count_pending();
                if ($pending <= 0) {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms-booking-pending',
                    'info',
                    'Offene Buchungsanfragen',
                    $pending === 1 ? '1 Buchung wartet auf Bestätigung.' : $pending . ' Buchungen warten auf Bestätigung.',
                    '/admin/plugins/booking/bookings?status=pending',
                    'Anfragen ansehen'
                );
            });

            \CMS\Services\AdminNoticeService::registerCheck('CMS Booking: Benachrichtigungen', static function (): ?array {
                if (CMS_Booking_Settings::get('admin_email') !== '' && CMS_Booking_Settings::sender_email() !== '') {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms-booking-mail-config',
                    'warning',
                    'Buchungs-Mails unvollständig konfiguriert',
                    'Admin-E-Mail oder Absenderadresse fehlt – Benachrichtigungen gehen nur an Kunden/Anbieter bzw. mit Core-Absender raus.',
                    '/admin/plugins/booking/settings',
                    'Einstellungen öffnen'
                );
            });
        } catch (\Throwable $e) {
            error_log('[cms-booking] admin notices not registered: ' . $e->getMessage());
        }
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
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/css/booking-public.css?v=' . CMS_BOOKING_VERSION . '-' . filemtime($css), ENT_QUOTES, 'UTF-8')
                . '">' . "\n";
        }

        // Optionale Primärfarbe (validiertes #rrggbb) als CSS-Variable – nonce-geschützt.
        $color = CMS_Booking_Settings::get('primary_color');
        if (CMS_Booking_Settings::bool('primary_color_custom') && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            $nonce = class_exists('CMS\Security') ? \CMS\Security::instance()->nonceAttr() : '';
            echo '<style ' . $nonce . '>.booking-page{--booking-primary:' . $color . ';}</style>' . "\n";
        }
    }

    public function enqueue_scripts(): void
    {
        if (!$this->is_booking_public_route()) {
            return;
        }

        $js = CMS_BOOKING_PLUGIN_DIR . 'assets/js/booking-public.js';
        if (is_file($js)) {
            echo '<script src="'
                . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/js/booking-public.js?v=' . CMS_BOOKING_VERSION . '-' . filemtime($js), ENT_QUOTES, 'UTF-8')
                . '" defer></script>' . "\n";
        }
    }

    private function is_booking_public_route(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $path = '/' . trim($path, '/');

        // GET- und POST-Antworten (Formular mit Fehlermeldung) der Buchungsseiten
        return preg_match('#^/(?:en/)?booking(?:/[^/]+){1,2}$#', $path) === 1;
    }

    /* ------------------------------------------------------------------ */
    /*  Member Dashboard                                                    */
    /* ------------------------------------------------------------------ */

    public function register_member_section(mixed $registry = null): void
    {
        if (!$registry instanceof \CMS\Member\PluginDashboardRegistry) {
            return;
        }

        $registry->register([
            'plugin'           => 'cms-booking',
            'slug'             => 'booking',
            'label'            => 'Buchungen',
            'icon'             => '🗓️',
            'category'         => 'plugins',
            'priority'         => 60,
            'capability'       => 'member',
            'csrf'             => 'core',
            'dashboard_widget' => [
                'title'          => 'Buchungen',
                'description'    => 'Ihre gebuchten Termine',
                'color'          => '#16a34a',
                'admin_url'      => '/admin/plugins/booking/bookings',
                'stats_callback' => [$this, 'member_stats'],
            ],
            'render_callback'  => [$this, 'render_member_page'],
            'post_callback'    => [$this, 'handle_member_post'],
        ]);
    }

    /**
     * @return array{count: int, label: string}
     */
    public function member_stats(mixed $user = null): array
    {
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $count = 0;
        if ($userId > 0) {
            foreach (CMS_Booking_Bookings::instance()->get_by_user($userId, 0, 200) as $row) {
                if (in_array((string) $row['status'], CMS_Booking_Bookings::ACTIVE_STATUSES, true)
                    && (string) $row['booking_date'] >= date('Y-m-d')) {
                    $count++;
                }
            }
        }

        return ['count' => $count, 'label' => 'anstehende Termine'];
    }

    /**
     * POST im Mitgliederbereich (CSRF `member_plugin_booking` prüft der Core vorab): eigene Buchung stornieren.
     *
     * @return array{type: string, message: string}
     */
    public function handle_member_post(object $user, array $params = []): array
    {
        $userId = (int) ($user->id ?? 0);
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $action = (string) ($_POST['booking_action'] ?? '');

        if ($userId <= 0 || $bookingId <= 0 || $action !== 'cancel') {
            return ['type' => 'error', 'message' => 'Ungültige Aktion.'];
        }

        $booking = CMS_Booking_Bookings::instance()->get($bookingId);
        // Nur eigene Buchungen (user_id = angemeldetes Mitglied).
        if (!$booking || (int) ($booking['user_id'] ?? 0) !== $userId) {
            return ['type' => 'error', 'message' => 'Buchung nicht gefunden.'];
        }
        if (!CMS_Booking_Frontend::instance()->can_customer_cancel($booking)) {
            return ['type' => 'error', 'message' => 'Diese Buchung kann nicht mehr storniert werden.'];
        }

        CMS_Booking_Bookings::instance()->cancel($bookingId);

        return ['type' => 'success', 'message' => 'Buchung #' . $bookingId . ' wurde storniert.'];
    }

    public function render_member_page(object $user, array $params = []): void
    {
        $userId = (int) ($user->id ?? 0);
        $bookings = $userId > 0 ? CMS_Booking_Bookings::instance()->get_by_user($userId, 0, 50) : [];
        $labels = CMS_Booking_Bookings::status_labels();
        $frontend = CMS_Booking_Frontend::instance();
        $csrfField = '';
        if (class_exists('CMS\Member\PluginDashboardRegistry') && method_exists('CMS\Member\PluginDashboardRegistry', 'instance')) {
            $csrfField = \CMS\Member\PluginDashboardRegistry::instance()->csrfField('booking');
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $hours = max(0, CMS_Booking_Settings::int('cancellation_hours', 24));
        ?>
        <div class="cms-member-section">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <p><strong>Keine Buchungen vorhanden</strong></p>
                    <p class="text-muted">Sobald Termine gebucht wurden, erscheinen sie hier.</p>
                </div>
            <?php else: ?>
                <p class="text-muted">Stornierungen sind bis <?php echo (int) $hours; ?> Stunde(n) vor Terminbeginn möglich.</p>
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Leistung</th>
                                <th>Anbieter</th>
                                <th>Datum</th>
                                <th>Status</th>
                                <th><span class="visually-hidden">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $row): ?>
                            <?php $status = (string) ($row['status'] ?? ''); ?>
                            <tr>
                                <td><?php echo $e($row['service_title'] ?? '—'); ?></td>
                                <td><?php echo $e($row['provider_name'] ?? '—'); ?></td>
                                <td><?php echo $e(date('d.m.Y', (int) strtotime((string) $row['booking_date']))); ?>, <?php echo $e(substr((string) $row['start_time'], 0, 5)); ?> Uhr</td>
                                <td>
                                    <span class="status-badge <?php echo $e(CMS_Booking_Bookings::status_badge_class($status)); ?>">
                                        <?php echo $e($labels[$status] ?? $status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($csrfField !== '' && $frontend->can_customer_cancel($row)): ?>
                                    <form method="post" data-cms-confirm="Diesen Termin wirklich stornieren?">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="booking_action" value="cancel">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Stornieren</button>
                                    </form>
                                    <?php endif; ?>
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

    public function export_user_data(mixed $userId = null, mixed $email = null): void
    {
        CMS_Booking_Privacy::handle_export($userId, $email);
    }

    public function delete_user_data(mixed $userId = null, mixed $email = null): void
    {
        CMS_Booking_Privacy::handle_delete($userId, $email);
    }
}
}

CMS_Booking::instance();

/* ---------------------------------------------------------------------- */
/*  Lifecycle-Callbacks (PluginManager: <slug>_activate/_deactivate/_uninstall) */
/* ---------------------------------------------------------------------- */

if (!function_exists('cms_booking_activate')) {
    function cms_booking_activate(): void
    {
        try {
            if (class_exists('CMS_Booking_Installer')) {
                CMS_Booking_Installer::install();
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] activation failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cms_booking_deactivate')) {
    function cms_booking_deactivate(): void
    {
        // Daten bleiben erhalten; nur Laufzeit-Caches verwerfen.
        if (class_exists('CMS_Booking_Settings')) {
            CMS_Booking_Settings::flush();
        }
    }
}

if (!function_exists('cms_booking_uninstall')) {
    function cms_booking_uninstall(): void
    {
        try {
            if (class_exists('CMS_Booking_Installer')) {
                CMS_Booking_Installer::uninstall();
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] uninstall failed: ' . $e->getMessage());
        }
    }
}

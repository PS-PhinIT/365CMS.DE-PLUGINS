<?php
/**
 * CMS Booking – Admin-Seiten im einheitlichen 365CMS-4.0.0-Plugin-Layout.
 *
 * Menü: Übersicht · Buchungen · Anbieter · Verfügbarkeit · Leistungen · Einstellungen
 * Alle Formulare: CSRF → Aktion → Flash-Meldung → 303-Redirect (PRG).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use CMS\Security;

final class CMS_Booking_Admin_Pages
{
    public const CAPABILITY = 'manage_options';
    private const CSRF_ACTION = 'booking_admin';
    private const EXPORT_ACTION = 'booking_export';
    private const NOTICE_KEY = 'booking_admin_notice';
    private const PER_PAGE = 20;

    /** @var array<string,array{label:string,icon:string}> */
    public const PAGES = [
        'booking' => ['label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard'],
        'bookings' => ['label' => 'Buchungen', 'icon' => 'ti ti-calendar-event'],
        'providers' => ['label' => 'Anbieter', 'icon' => 'ti ti-users'],
        'availability' => ['label' => 'Verfügbarkeit', 'icon' => 'ti ti-clock-hour-4'],
        'services' => ['label' => 'Leistungen', 'icon' => 'ti ti-list-details'],
        'settings' => ['label' => 'Einstellungen', 'icon' => 'ti ti-settings'],
    ];

    /* ------------------------------------------------------------------ */
    /*  Einstiegspunkte (Menü-Callbacks und AdminRouter-Fallback)          */
    /* ------------------------------------------------------------------ */

    public static function dispatch(): void
    {
        self::render_page(self::current_page());
    }

    public static function dispatch_dashboard(): void { self::render_page('booking'); }
    public static function dispatch_bookings(): void { self::render_page('bookings'); }
    public static function dispatch_providers(): void { self::render_page('providers'); }
    public static function dispatch_availability(): void { self::render_page('availability'); }
    public static function dispatch_services(): void { self::render_page('services'); }
    public static function dispatch_settings(): void { self::render_page('settings'); }

    // Vom Core-AdminRouter (resolvePluginAdminFallback) referenziert.
    public static function render_dashboard(): void { self::render_page('booking'); }
    public static function render_bookings(): void { self::render_page('bookings'); }
    public static function render_providers(): void { self::render_page('providers'); }
    public static function render_services(): void { self::render_page('services'); }
    public static function render_settings(): void { self::render_page('settings'); }

    private static function render_page(string $page): void
    {
        if (!function_exists('current_user_can') || !current_user_can(self::CAPABILITY)) {
            header('Location: ' . Security::safeLocalPath('/admin', '/'), true, 303);
            exit;
        }

        $page = array_key_exists($page, self::PAGES) ? $page : 'booking';
        self::handle_export($page);
        self::handle_post($page);

        if (!function_exists('cms_plugin_workspace_open') && is_file(ABSPATH . 'admin/partials/plugin-workspace.php')) {
            require_once ABSPATH . 'admin/partials/plugin-workspace.php';
        }

        $stats = CMS_Booking_Bookings::instance()->get_stats();
        $providerCounts = CMS_Booking_Providers::instance()->count_by_status();
        $activeProviders = (int) ($providerCounts['active'] ?? 0);

        self::enqueue_style();
        echo cms_plugin_workspace_open([
            'plugin' => CMS_Booking_Admin_Menu::MENU_SLUG,
            'title' => '365CMS | Buchungen',
            'icon' => 'ti ti-calendar-event',
            'version' => CMS_BOOKING_VERSION,
            'status' => (int) $stats['pending'] > 0
                ? ['label' => $stats['pending'] . ' offen', 'tone' => 'warning']
                : ['label' => 'Keine offenen Anfragen', 'tone' => 'success'],
            'description' => 'Termine, Anbieter, Verfügbarkeiten und Leistungen – mit Bestätigung, Erinnerung und iCal.',
            'stats' => [
                ['label' => 'Offene Anfragen', 'value' => (int) $stats['pending'], 'icon' => 'ti ti-inbox', 'tone' => (int) $stats['pending'] > 0 ? 'warning' : 'muted', 'url' => self::url('bookings', ['status' => 'pending'])],
                ['label' => 'Heute', 'value' => (int) $stats['today'], 'icon' => 'ti ti-calendar-due'],
                ['label' => 'Nächste 7 Tage', 'value' => (int) $stats['week'], 'icon' => 'ti ti-calendar-week'],
                ['label' => 'Aktive Anbieter', 'value' => $activeProviders, 'icon' => 'ti ti-users', 'url' => self::url('providers')],
            ],
            'nav' => self::nav_items($page, (int) $stats['pending']),
            'nav_explicit' => true,
        ]);

        self::render_notice();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

        match ($page) {
            'bookings' => self::view_bookings($csrfToken),
            'providers' => self::view_providers($csrfToken),
            'availability' => self::view_availability($csrfToken),
            'services' => self::view_services($csrfToken),
            'settings' => self::view_settings($csrfToken),
            default => self::view_dashboard($stats, $activeProviders),
        };

        echo cms_plugin_workspace_close();
    }

    /* ------------------------------------------------------------------ */
    /*  Ansichten                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,int> $stats
     */
    private static function view_dashboard(array $stats, int $activeProviders): void
    {
        $settings = self::settings();
        $serviceCount = CMS_Booking_Services::instance()->count();
        $schemaOk = CMS_Booking_Installer::stored_version() === CMS_BOOKING_DB_VERSION;
        $staleOpen = CMS_Booking_Bookings::instance()->count_stale_pending();
        $remindersOn = ($settings['send_reminders'] ?? '1') !== '0';
        $retentionDays = (int) ($settings['retention_days'] ?? 0);
        $mailReady = class_exists('CMS\\Services\\MailService');
        $types = CMS_Booking_Integration::get_provider_types();

        $activity = [];
        foreach (CMS_Booking_Bookings::instance()->get_upcoming(8) as $item) {
            $activity[] = [
                'title' => (string) $item['customer_name'] . ' – ' . (string) ($item['service_title'] ?? 'Termin'),
                'url' => self::url('bookings', ['view' => (int) $item['id']]),
                'meta' => self::format_date((string) $item['booking_date']) . ' ' . substr((string) $item['start_time'], 0, 5) . ' · ' . (string) ($item['provider_name'] ?? ''),
                'badge' => CMS_Booking_Bookings::status_labels()[$item['status']] ?? (string) $item['status'],
                'tone' => $item['status'] === 'pending' ? 'warning' : 'success',
            ];
        }

        echo cms_plugin_dashboard([
            'intro' => 'Buchungsanfragen bestätigen, Anbieter und Wochenpläne pflegen, Leistungen mit Dauer, Puffer und Preis anbieten.',
            'actions' => [
                ['label' => 'Offene Anfragen', 'url' => self::url('bookings', ['status' => 'pending']), 'icon' => 'ti ti-inbox', 'primary' => true, 'description' => 'Anfragen bestätigen oder ablehnen.'],
                ['label' => 'Neuer Anbieter', 'url' => self::url('providers', ['action' => 'new']), 'icon' => 'ti ti-user-plus', 'description' => 'Anbieter ohne Quell-Plugin anlegen.'],
                ['label' => 'Wochenplan pflegen', 'url' => self::url('availability'), 'icon' => 'ti ti-clock-hour-4', 'description' => 'Zeiten und Abwesenheiten je Anbieter.'],
                ['label' => 'Neue Leistung', 'url' => self::url('services', ['action' => 'new']), 'icon' => 'ti ti-plus', 'description' => 'Dauer, Puffer, Preis und Buchungsart.'],
            ],
            'checks' => [
                ['label' => 'Datenbank-Schema', 'status' => $schemaOk ? 'ok' : 'warning', 'value' => $schemaOk ? 'v' . CMS_BOOKING_DB_VERSION : 'Migration ausstehend'],
                ['label' => 'Buchbares Angebot', 'status' => $activeProviders > 0 && $serviceCount > 0 ? 'ok' : 'warning', 'value' => $activeProviders . ' Anbieter / ' . $serviceCount . ' Leistungen', 'hint' => 'Ohne aktive Anbieter mit Leistung und Wochenplan sind keine Buchungen möglich.', 'url' => self::url('providers')],
                ['label' => 'Unbearbeitete Anfragen in der Vergangenheit', 'status' => $staleOpen > 0 ? 'warning' : 'ok', 'value' => $staleOpen, 'url' => self::url('bookings', ['status' => 'pending'])],
                ['label' => 'E-Mail-Versand', 'status' => $mailReady ? 'ok' : 'danger', 'value' => $mailReady ? 'Core-MailService' : 'nicht verfügbar', 'hint' => 'Zustellung und Protokoll unter System › E-Mail.'],
                ['label' => 'Terminerinnerungen', 'status' => $remindersOn ? 'ok' : 'muted', 'value' => $remindersOn ? (int) ($settings['reminder_hours'] ?? 24) . ' h vorher' : 'aus', 'hint' => 'Versand über den stündlichen Core-Cron.', 'url' => self::url('settings')],
                ['label' => 'Anonymisierung alter Buchungen', 'status' => $retentionDays > 0 ? 'ok' : 'info', 'value' => $retentionDays > 0 ? $retentionDays . ' Tage' : 'aus', 'url' => self::url('settings')],
                ['label' => 'Integrationen', 'status' => $types !== [] ? 'ok' : 'muted', 'value' => $types !== [] ? implode(', ', array_keys($types)) : 'keine', 'hint' => 'Plugins, die Anbieter registrieren (CMS_Booking_Integration).'],
            ],
            'activity' => [
                'heading' => 'Anstehende Termine',
                'items' => $activity,
                'empty' => 'Keine anstehenden Termine.',
                'more_url' => self::url('bookings'),
                'more_label' => 'Alle Buchungen',
            ],
        ]);
    }

    private static function view_bookings(string $csrfToken): void
    {
        $bookings = CMS_Booking_Bookings::instance();
        $detailId = self::get_int('view');
        $detail = $detailId > 0 ? $bookings->get($detailId) : null;
        if ($detail !== null) {
            $meta = $bookings->get_all_meta($detailId);
            include CMS_BOOKING_PLUGIN_DIR . 'admin/views/booking-detail.php';
            return;
        }

        $filters = [
            'status' => self::get_key('status'),
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
            'provider_id' => self::get_int('provider_id'),
        ];
        if (!array_key_exists($filters['status'], CMS_Booking_Bookings::status_labels())) {
            $filters['status'] = '';
        }
        $paged = max(1, self::get_int('paged'));
        $items = $bookings->get_all(($paged - 1) * self::PER_PAGE, self::PER_PAGE, $filters['status'], $filters['q'], $filters['provider_id']);
        $total = $bookings->count($filters['status'], $filters['q'], $filters['provider_id']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $statusLabels = CMS_Booking_Bookings::status_labels();
        $providers = CMS_Booking_Providers::instance()->get_all(0, 500);

        $privacyExports = CMS_Booking_Privacy::exports();
        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/bookings.php';
        self::enqueue_admin_script();
    }

    /** „Alle auswählen“ für die Sammelaktion der Buchungsliste (externe Datei, CSP-konform). */
    private static function enqueue_admin_script(): void
    {
        $js = CMS_BOOKING_PLUGIN_DIR . 'assets/js/booking-admin.js';
        if (is_file($js)) {
            echo '<script src="' . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/js/booking-admin.js?v=' . (string) filemtime($js), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }

    private static function view_providers(string $csrfToken): void
    {
        $providersSvc = CMS_Booking_Providers::instance();
        $editId = self::get_int('edit');
        $editProvider = $editId > 0 ? $providersSvc->get($editId) : null;
        $showForm = $editProvider !== null || (string) ($_GET['action'] ?? '') === 'new';

        $filters = ['status' => self::get_key('status'), 'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100)];
        if (!in_array($filters['status'], ['active', 'inactive', 'pending'], true)) {
            $filters['status'] = '';
        }
        $paged = max(1, self::get_int('paged'));
        $items = $showForm ? [] : $providersSvc->get_all(($paged - 1) * self::PER_PAGE, self::PER_PAGE, $filters['status'], $filters['q']);
        $total = $showForm ? 0 : $providersSvc->count($filters['status'], $filters['q']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $types = CMS_Booking_Integration::get_provider_types();
        $settings = self::settings();

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/providers.php';
    }

    private static function view_availability(string $csrfToken): void
    {
        $providers = CMS_Booking_Providers::instance()->get_all(0, 500);
        $providerId = self::get_int('provider_id');
        if ($providerId <= 0 && $providers !== []) {
            $providerId = (int) $providers[0]['id'];
        }
        $provider = $providerId > 0 ? CMS_Booking_Providers::instance()->get($providerId) : null;
        $availability = CMS_Booking_Availability::instance();
        $weekly = $provider !== null ? $availability->get_weekly($providerId) : [];
        $overrides = $provider !== null ? $availability->get_date_overrides($providerId, date('Y-m-d'), date('Y-m-d', strtotime('+1 year'))) : [];
        $dayLabels = CMS_Booking_Availability::day_labels();

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/availability.php';
    }

    private static function view_services(string $csrfToken): void
    {
        $servicesSvc = CMS_Booking_Services::instance();
        $editId = self::get_int('edit');
        $editService = $editId > 0 ? $servicesSvc->get($editId) : null;
        $showForm = $editService !== null || (string) ($_GET['action'] ?? '') === 'new';
        $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $paged = max(1, self::get_int('paged'));
        $items = $showForm ? [] : $servicesSvc->get_all(($paged - 1) * self::PER_PAGE, self::PER_PAGE, $search);
        $total = $showForm ? 0 : $servicesSvc->count($search);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $providers = CMS_Booking_Providers::instance()->get_all(0, 500);
        $settings = self::settings();

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/services.php';
    }

    private static function view_settings(string $csrfToken): void
    {
        $settings = self::settings();
        $retentionDue = CMS_Booking_Bookings::instance()->count_anonymizable((int) ($settings['retention_days'] ?? 0));

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /* ------------------------------------------------------------------ */
    /*  POST-Aktionen (PRG)                                                */
    /* ------------------------------------------------------------------ */

    private static function handle_post(string $page): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !isset($_POST['booking_admin_action'])) {
            return;
        }

        if (!Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), self::CSRF_ACTION)) {
            self::flash(false, 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
            self::redirect($page);
        }

        $action = self::post_key('booking_admin_action');
        $query = [];

        try {
            [$ok, $message, $query] = match ($action) {
                'booking_status' => self::action_booking_status(),
                'booking_bulk' => self::action_booking_bulk(),
                'privacy_download' => self::action_privacy_download(),
                'privacy_delete' => [($ok = CMS_Booking_Privacy::delete_export((string) ($_POST['file'] ?? ''))), $ok ? 'Auskunftsdatei gelöscht.' : 'Auskunftsdatei nicht gefunden.', []],
                'booking_notes' => self::action_booking_notes(),
                'provider_save' => self::action_provider_save(),
                'provider_status' => self::action_provider_status(),
                'provider_delete' => self::action_provider_delete(),
                'provider_sync' => self::action_provider_sync(),
                'availability_weekly' => self::action_availability_weekly(),
                'availability_block' => self::action_availability_block(),
                'availability_delete' => self::action_availability_delete(),
                'service_save' => self::action_service_save(),
                'service_delete' => self::action_service_delete(),
                'settings_save' => self::action_settings_save(),
                'settings_reset' => self::action_settings_reset(),
                'run_reminders' => [true, sprintf('%d Erinnerung(en) versendet.', CMS_Booking_Notifications::instance()->send_due_reminders()), []],
                'run_anonymize' => [true, sprintf('%d Buchung(en) anonymisiert.', CMS_Booking_Bookings::instance()->anonymize_older_than((int) (self::settings()['retention_days'] ?? 0))), []],
                default => [false, 'Unbekannte Aktion.', []],
            };
        } catch (\Throwable $e) {
            if (!$e instanceof \InvalidArgumentException) {
                error_log('[cms-booking] admin action ' . $action . ' failed: ' . $e->getMessage());
            }
            $ok = false;
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Die Aktion konnte nicht ausgeführt werden.';
            $query = [];
        }

        if ($ok && in_array($action, ['provider_save', 'provider_status', 'provider_delete', 'provider_sync', 'service_save', 'service_delete'], true)) {
            self::invalidate_sitemaps();
        }
        self::flash($ok, $message);
        self::redirect($page, $query);
    }

    /** Gespeichertes Sitemap-Bundle verwerfen, damit plugins.xml öffentliche Änderungen sofort zeigt. */
    private static function invalidate_sitemaps(): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }
        try {
            \CMS\Services\SEOService::getInstance()->invalidateSavedSitemaps();
        } catch (\Throwable) {
        }
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_booking_status(): array
    {
        $id = self::post_int('booking_id');
        $status = self::post_key('status');
        $bookings = CMS_Booking_Bookings::instance();
        $labels = CMS_Booking_Bookings::status_labels();
        if ($id <= 0 || $bookings->get($id) === null) {
            return [false, 'Buchung nicht gefunden.', []];
        }

        $result = match ($status) {
            'confirm' => $bookings->confirm($id),
            'cancel' => $bookings->cancel($id),
            'complete' => $bookings->complete($id),
            'no_show' => $bookings->no_show($id),
            'delete' => $bookings->delete($id),
            default => null,
        };
        if ($result === null) {
            return [false, 'Ungültige Aktion.', []];
        }
        if ($status === 'delete') {
            return [(bool) $result, $result ? 'Buchung #' . $id . ' gelöscht.' : 'Buchung konnte nicht gelöscht werden.', []];
        }

        $target = ['confirm' => 'confirmed', 'cancel' => 'cancelled', 'complete' => 'completed', 'no_show' => 'no_show'][$status];
        $back = self::post_int('return_view') > 0 ? ['view' => $id] : self::return_filters();

        return [(bool) $result, $result ? sprintf('Buchung #%d: %s.', $id, $labels[$target] ?? $target) : 'Statuswechsel nicht möglich.', $back];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_booking_notes(): array
    {
        $id = self::post_int('booking_id');
        $notes = mb_substr(trim(strip_tags((string) ($_POST['internal_notes'] ?? ''))), 0, 5000);
        $ok = $id > 0 && CMS_Booking_Bookings::instance()->update($id, ['internal_notes' => $notes]);

        return [$ok, $ok ? 'Interne Notiz gespeichert.' : 'Notiz konnte nicht gespeichert werden.', ['view' => $id]];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_provider_save(): array
    {
        $id = self::post_int('provider_id');
        $savedId = CMS_Booking_Providers::instance()->save_manual($_POST, $id);

        return [true, $id > 0 ? 'Anbieter aktualisiert.' : 'Anbieter angelegt – jetzt Wochenplan und Leistungen ergänzen.', $id > 0 ? [] : ['action' => 'saved', 'id' => $savedId]];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_provider_status(): array
    {
        $status = self::post_key('status') === 'active' ? 'active' : 'inactive';
        $ok = CMS_Booking_Providers::instance()->set_status(self::post_int('provider_id'), $status);

        return [$ok, $ok ? ($status === 'active' ? 'Anbieter aktiviert.' : 'Anbieter deaktiviert.') : 'Status unverändert.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_provider_delete(): array
    {
        $ok = CMS_Booking_Providers::instance()->delete(self::post_int('provider_id'));

        return [$ok, $ok ? 'Anbieter inkl. Leistungen, Wochenplan und Buchungen gelöscht.' : 'Anbieter konnte nicht gelöscht werden.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_provider_sync(): array
    {
        $provider = CMS_Booking_Providers::instance()->get(self::post_int('provider_id'));
        if ($provider === null || (string) $provider['source_plugin'] === CMS_Booking_Providers::MANUAL_SOURCE) {
            return [false, 'Nur Anbieter aus einem Quell-Plugin können synchronisiert werden.', []];
        }
        CMS_Booking_Integration::sync_provider((string) $provider['source_plugin'], (int) $provider['source_id']);

        return [true, 'Anbieter synchronisiert.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_availability_weekly(): array
    {
        $providerId = self::post_int('provider_id');
        if (CMS_Booking_Providers::instance()->get($providerId) === null) {
            return [false, 'Anbieter nicht gefunden.', []];
        }

        $weekly = [];
        $rows = is_array($_POST['weekly'] ?? null) ? $_POST['weekly'] : [];
        foreach ($rows as $day => $ranges) {
            $day = (int) $day;
            if ($day < 0 || $day > 6 || !is_array($ranges) || empty($ranges['enabled'])) {
                continue;
            }
            foreach ([1, 2] as $n) {
                $start = (string) ($ranges['start' . $n] ?? '');
                $end = (string) ($ranges['end' . $n] ?? '');
                if ($start === '' && $end === '') {
                    continue;
                }
                if (!self::valid_time($start) || !self::valid_time($end) || $start >= $end) {
                    throw new \InvalidArgumentException(sprintf('Ungültiger Zeitraum am %s (%s–%s).', CMS_Booking_Availability::day_label($day), $start, $end));
                }
                $weekly[] = ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
            }
        }

        CMS_Booking_Availability::instance()->replace_weekly($providerId, $weekly);

        return [true, sprintf('Wochenplan gespeichert (%d Zeitfenster).', count($weekly)), ['provider_id' => $providerId]];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_availability_block(): array
    {
        $providerId = self::post_int('provider_id');
        $from = (string) ($_POST['date_from'] ?? '');
        $to = (string) ($_POST['date_to'] ?? '') !== '' ? (string) $_POST['date_to'] : $from;
        $start = (string) ($_POST['start_time'] ?? '') !== '' ? (string) $_POST['start_time'] : '00:00';
        $end = (string) ($_POST['end_time'] ?? '') !== '' ? (string) $_POST['end_time'] : '23:59';
        $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (CMS_Booking_Providers::instance()->get($providerId) === null || !$fromDate || !$toDate || $toDate < $fromDate
            || !self::valid_time($start) || !self::valid_time($end) || $start >= $end) {
            throw new \InvalidArgumentException('Bitte gültigen Zeitraum (Datum und Uhrzeit) angeben.');
        }
        if ($fromDate->diff($toDate)->days > 366) {
            throw new \InvalidArgumentException('Abwesenheiten sind auf höchstens ein Jahr begrenzt.');
        }

        $count = 0;
        for ($day = $fromDate; $day <= $toDate; $day = $day->modify('+1 day')) {
            CMS_Booking_Availability::instance()->block_date($providerId, $day->format('Y-m-d'), $start, $end);
            $count++;
        }

        return [true, sprintf('Abwesenheit für %d Tag(e) eingetragen.', $count), ['provider_id' => $providerId]];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_availability_delete(): array
    {
        $ok = CMS_Booking_Availability::instance()->delete(self::post_int('availability_id'));

        return [$ok, $ok ? 'Eintrag entfernt.' : 'Eintrag nicht gefunden.', ['provider_id' => self::post_int('provider_id')]];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_service_save(): array
    {
        $services = CMS_Booking_Services::instance();
        $id = self::post_int('service_id');
        $existing = $id > 0 ? $services->get($id) : null;
        if ($id > 0 && $existing === null) {
            return [false, 'Leistung nicht gefunden.', []];
        }

        $title = mb_substr(trim(strip_tags((string) ($_POST['title'] ?? ''))), 0, 255);
        if ($title === '') {
            throw new \InvalidArgumentException('Bitte einen Titel angeben.');
        }
        $providerId = $existing !== null ? (int) $existing['provider_id'] : self::post_int('provider_id');
        if (CMS_Booking_Providers::instance()->get($providerId) === null) {
            throw new \InvalidArgumentException('Bitte einen gültigen Anbieter wählen.');
        }

        $settingsJson = [];
        if ($existing !== null && is_string($existing['settings_json'] ?? null)) {
            $decoded = json_decode((string) $existing['settings_json'], true);
            $settingsJson = is_array($decoded) ? $decoded : [];
        }
        $settingsJson['daily_booking_limit'] = max(0, min(1000, self::post_int('daily_booking_limit')));
        $meetingUrl = trim((string) ($_POST['meeting_url'] ?? ''));
        if ($meetingUrl !== '' && (preg_match('#^https://#i', $meetingUrl) !== 1 || filter_var($meetingUrl, FILTER_VALIDATE_URL) === false)) {
            throw new \InvalidArgumentException('Der Meeting-Link muss eine https-Adresse sein.');
        }

        $data = [
            'title' => $title,
            'description' => mb_substr(trim(strip_tags((string) ($_POST['description'] ?? ''), '<p><a><strong><em><ul><ol><li><br>')), 0, 10000),
            'duration_min' => max(5, min(1440, self::post_int('duration_min') ?: 60)),
            'buffer_min' => max(0, min(240, self::post_int('buffer_min'))),
            'max_bookings' => max(1, min(500, self::post_int('max_bookings') ?: 1)),
            'price_cents' => max(0, (int) round(((float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'))) * 100)),
            'location_type' => in_array(self::post_key('location_type'), ['online', 'onsite', 'hybrid'], true) ? self::post_key('location_type') : 'online',
            'meeting_url' => $meetingUrl,
            'booking_type' => in_array(self::post_key('booking_type'), ['confirmation', 'instant', 'request'], true) ? self::post_key('booking_type') : 'confirmation',
            'contact_template' => mb_substr(sanitize_text_field((string) ($_POST['contact_template'] ?? '')), 0, 50),
            'settings_json' => (string) json_encode($settingsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($existing !== null) {
            $data['status'] = self::post_key('status') === 'inactive' ? 'inactive' : 'active';
            $services->update($id, $data);

            return [true, 'Leistung aktualisiert.', []];
        }

        $services->create($data + ['provider_id' => $providerId]);

        return [true, 'Leistung angelegt.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_service_delete(): array
    {
        $ok = CMS_Booking_Services::instance()->delete(self::post_int('service_id'));

        return [$ok, $ok ? 'Leistung gelöscht.' : 'Leistung konnte nicht gelöscht werden.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_settings_save(): array
    {
        $values = [
            'admin_email' => (string) (filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: ''),
            'from_name' => mb_substr(sanitize_text_field((string) ($_POST['from_name'] ?? '')), 0, 120),
            'from_email' => (string) (filter_var($_POST['from_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: ''),
            'default_duration' => (string) max(5, min(1440, self::post_int('default_duration') ?: 60)),
            'default_buffer' => (string) max(0, min(240, self::post_int('default_buffer'))),
            'default_timezone' => in_array((string) ($_POST['default_timezone'] ?? ''), timezone_identifiers_list(), true) ? (string) $_POST['default_timezone'] : 'Europe/Berlin',
            'default_currency' => in_array((string) ($_POST['default_currency'] ?? ''), ['EUR', 'CHF', 'USD', 'GBP'], true) ? (string) $_POST['default_currency'] : 'EUR',
            'booking_advance_min' => (string) max(0, min(365, self::post_int('booking_advance_min'))),
            'booking_advance_max' => (string) max(1, min(730, self::post_int('booking_advance_max') ?: 90)),
            'booking_min_notice_hours' => (string) max(0, min(720, self::post_int('booking_min_notice_hours'))),
            'booking_daily_limit' => (string) max(0, min(1000, self::post_int('booking_daily_limit'))),
            'cancellation_hours' => (string) max(0, min(720, self::post_int('cancellation_hours'))),
            'auto_confirm' => !empty($_POST['auto_confirm']) ? '1' : '0',
            'send_reminders' => !empty($_POST['send_reminders']) ? '1' : '0',
            'reminder_hours' => (string) max(1, min(336, self::post_int('reminder_hours') ?: 24)),
            'primary_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['primary_color'] ?? '')) === 1 ? strtolower((string) $_POST['primary_color']) : '',
            'retention_days' => (string) max(0, min(3650, self::post_int('retention_days'))),
        ];
        if ((int) $values['booking_advance_max'] < (int) $values['booking_advance_min']) {
            throw new \InvalidArgumentException('Die maximale Vorlaufzeit muss größer als die minimale sein.');
        }

        CMS_Booking_Installer::write_settings($values);

        return [true, 'Einstellungen gespeichert.', []];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_settings_reset(): array
    {
        CMS_Booking_Installer::write_settings(CMS_Booking_Installer::default_settings());

        return [true, 'Einstellungen auf Standardwerte zurückgesetzt.', []];
    }

    /* ------------------------------------------------------------------ */
    /*  Export                                                             */
    /* ------------------------------------------------------------------ */

    private static function handle_export(string $page): void
    {
        if (self::get_key('export') !== 'csv') {
            return;
        }
        if (!Security::instance()->verifyPersistentToken((string) ($_GET['token'] ?? ''), self::EXPORT_ACTION)) {
            self::flash(false, 'Export abgelehnt: ungültiges oder abgelaufenes Token.');
            self::redirect($page);
        }

        $status = self::get_key('status');
        $rows = CMS_Booking_Bookings::instance()->export_rows(
            array_key_exists($status, CMS_Booking_Bookings::status_labels()) ? $status : '',
            mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
            self::get_int('provider_id')
        );
        $labels = CMS_Booking_Bookings::status_labels();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="buchungen-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Datum', 'Beginn', 'Ende', 'Status', 'Anbieter', 'Leistung', 'Kunde', 'E-Mail', 'Telefon', 'Preis', 'Währung', 'Erstellt'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csv_safe'], [
                (string) $row['id'], (string) $row['booking_date'], substr((string) $row['start_time'], 0, 5), substr((string) $row['end_time'], 0, 5),
                (string) ($labels[$row['status']] ?? $row['status']), (string) ($row['provider_name'] ?? ''), (string) ($row['service_title'] ?? ''),
                (string) $row['customer_name'], (string) $row['customer_email'], (string) ($row['customer_phone'] ?? ''),
                number_format(((int) $row['price_cents']) / 100, 2, ',', ''), (string) $row['currency'], (string) $row['created_at'],
            ]), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    private static function csv_safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value) ? "'" . $value : $value;
    }

    /**
     * @param array<string,scalar> $query
     */
    public static function export_url(array $query = []): string
    {
        return self::url('bookings', ['export' => 'csv', 'token' => Security::instance()->generateToken(self::EXPORT_ACTION)] + array_filter($query, static fn ($v): bool => $v !== '' && $v !== 0));
    }

    /* ------------------------------------------------------------------ */
    /*  Hilfsfunktionen                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,scalar> $query
     */
    public static function url(string $slug, array $query = []): string
    {
        $slug = array_key_exists($slug, self::PAGES) ? $slug : 'booking';
        $url = '/admin/plugins/' . CMS_Booking_Admin_Menu::MENU_SLUG . '/' . rawurlencode($slug);

        return $query !== [] ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    /** Kompatibilität mit älteren Aufrufern. */
    public static function admin_url(string $slug, array $query = []): string
    {
        return self::url($slug, $query);
    }

    public static function format_date(string $date): string
    {
        $ts = $date !== '' ? strtotime($date) : false;

        return $ts !== false ? date('d.m.Y', $ts) : '–';
    }

    public static function status_tone(string $status): string
    {
        return match ($status) {
            'confirmed', 'completed' => 'success',
            'pending' => 'warning',
            'cancelled', 'no_show' => 'danger',
            default => 'muted',
        };
    }

    /** @return array<string,string> */
    public static function settings(): array
    {
        return CMS_Booking_Installer::read_settings();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function nav_items(string $active, int $pending): array
    {
        $items = [];
        foreach (self::PAGES as $slug => $config) {
            $items[] = [
                'slug' => $slug,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'url' => self::url($slug),
                'active' => $slug === $active,
                'badge' => $slug === 'bookings' && $pending > 0 ? $pending : null,
                'badge_tone' => 'warning',
            ];
        }

        return $items;
    }

    /** @return array<string,scalar> */
    /**
     * Sammelaktion: Statuswechsel bzw. Löschen je Buchung über dieselben Methoden wie die Einzelaktion
     * (Benachrichtigungen und Hooks laufen wie gewohnt). Höchstens 200 Buchungen je Aufruf.
     *
     * @return array{0:bool,1:string,2:array<string,scalar>}
     */
    private static function action_booking_bulk(): array
    {
        $operation = self::post_key('bulk_status');
        $raw = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0))), 0, 200);
        $back = self::return_filters();
        if ($ids === []) {
            return [false, 'Bitte mindestens eine Buchung markieren.', $back];
        }
        if (!in_array($operation, ['confirm', 'cancel', 'complete', 'no_show', 'delete'], true)) {
            return [false, 'Bitte eine Sammelaktion wählen.', $back];
        }

        $bookings = CMS_Booking_Bookings::instance();
        $done = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            if ($bookings->get($id) === null) {
                $skipped++;
                continue;
            }
            $ok = match ($operation) {
                'confirm' => $bookings->confirm($id),
                'cancel' => $bookings->cancel($id),
                'complete' => $bookings->complete($id),
                'no_show' => $bookings->no_show($id),
                'delete' => $bookings->delete($id),
            };
            $ok ? $done++ : $skipped++;
        }

        $labels = ['confirm' => 'bestätigt', 'cancel' => 'storniert', 'complete' => 'abgeschlossen', 'no_show' => 'als „nicht erschienen“ markiert', 'delete' => 'gelöscht'];
        $message = sprintf('%d Buchung(en) %s.', $done, $labels[$operation]) . ($skipped > 0 ? sprintf(' %d übersprungen (Statuswechsel nicht möglich oder nicht gefunden).', $skipped) : '');

        return [$done > 0, $message, $back];
    }

    /** @return array{0:bool,1:string,2:array<string,scalar>} */
    private static function action_privacy_download(): array
    {
        $path = CMS_Booking_Privacy::export_path((string) ($_POST['file'] ?? ''));
        if ($path === null) {
            return [false, 'Auskunftsdatei nicht gefunden.', []];
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function return_filters(): array
    {
        $query = [];
        foreach (['status', 'q', 'provider_id', 'paged'] as $key) {
            $value = $_POST['return_' . $key] ?? '';
            if (is_scalar($value) && (string) $value !== '' && (string) $value !== '0') {
                $query[$key] = mb_substr((string) $value, 0, 100);
            }
        }

        return $query;
    }

    private static function current_page(): string
    {
        $page = strtolower(trim((string) ($_GET['page'] ?? 'booking')));

        return array_key_exists($page, self::PAGES) ? $page : 'booking';
    }

    private static function enqueue_style(): void
    {
        $css = CMS_BOOKING_PLUGIN_DIR . 'assets/css/booking-admin.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_BOOKING_PLUGIN_URL . 'assets/css/booking-admin.css?v=' . (string) filemtime($css), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }

    private static function flash(bool $ok, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::NOTICE_KEY] = ['type' => $ok ? 'success' : 'danger', 'message' => $message];
        }
    }

    private static function render_notice(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::NOTICE_KEY]) || !is_array($_SESSION[self::NOTICE_KEY])) {
            return;
        }
        $notice = $_SESSION[self::NOTICE_KEY];
        unset($_SESSION[self::NOTICE_KEY]);
        $type = ($notice['type'] ?? '') === 'success' ? 'success' : 'danger';
        echo '<div class="alert alert-' . $type . '" role="' . ($type === 'success' ? 'status' : 'alert') . '">'
            . htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    /**
     * @param array<string,scalar> $query
     */
    private static function redirect(string $page, array $query = []): never
    {
        header('Location: ' . Security::safeLocalPath(self::url($page, $query), self::url('booking')), true, 303);
        exit;
    }

    private static function valid_time(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    private static function get_int(string $key): int
    {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? max(0, (int) $_GET[$key]) : 0;
    }

    private static function get_key(string $key): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? (string) preg_replace('/[^a-z0-9_]/', '', strtolower($_GET[$key])) : '';
    }

    private static function post_int(string $key): int
    {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? max(0, (int) $_POST[$key]) : 0;
    }

    private static function post_key(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? (string) preg_replace('/[^a-z0-9_]/', '', strtolower($_POST[$key])) : '';
    }
}

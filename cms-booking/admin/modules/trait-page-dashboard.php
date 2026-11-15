<?php
/**
 * CMS Booking – Admin Übersicht (Dashboard)
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait CMS_Booking_Page_Dashboard_Trait
{
    public function render_dashboard_page(): void
    {
        $bookingsSvc = CMS_Booking_Bookings::instance();
        $stats       = $bookingsSvc->get_stats();
        $recent      = $bookingsSvc->query_list([], 0, 8, 'created_desc');
        $upcoming    = $bookingsSvc->get_upcoming(8);
        $providers   = CMS_Booking_Providers::instance()->count('active');
        $services    = CMS_Booking_Services::instance()->count('', 0, 'active');
        $types       = CMS_Booking_Integration::get_provider_types();
        $statusLabels = CMS_Booking_Bookings::status_labels();

        $storedVersion = CMS_Booking_Installer::stored_version();
        $adminEmail    = CMS_Booking_Settings::get('admin_email');
        $senderEmail   = CMS_Booking_Settings::sender_email();
        $retentionDays = CMS_Booking_Settings::int('retention_days', 0);
        $reminders     = CMS_Booking_Settings::bool('send_reminders');

        $checks = [
            ['label' => 'Datenbanktabellen', 'status' => 'ok', 'value' => count(CMS_Booking_Installer::TABLES) . ' vorhanden'],
            [
                'label'  => 'Schema-Version',
                'status' => $storedVersion === CMS_BOOKING_DB_VERSION ? 'ok' : 'warning',
                'value'  => $storedVersion . ' / ' . CMS_BOOKING_DB_VERSION,
                'hint'   => $storedVersion === CMS_BOOKING_DB_VERSION ? '' : 'Migration läuft beim nächsten Seitenaufruf.',
            ],
            [
                'label'  => 'Admin-Benachrichtigung',
                'status' => $adminEmail !== '' ? 'ok' : 'warning',
                'value'  => $adminEmail !== '' ? $adminEmail : 'keine Adresse',
                'url'    => self::admin_url('settings'),
            ],
            [
                'label'  => 'Absenderadresse',
                'status' => $senderEmail !== '' ? 'ok' : 'info',
                'value'  => $senderEmail !== '' ? $senderEmail : 'Core-Mail-Einstellung',
                'url'    => self::admin_url('settings'),
            ],
            [
                'label'  => 'Buchbare Anbieter',
                'status' => $providers > 0 ? 'ok' : 'warning',
                'value'  => $providers . ' aktiv / ' . $services . ' Leistungen',
                'hint'   => $providers > 0 ? '' : 'Ohne aktiven Anbieter mit Leistung und Wochenplan sind keine Buchungen möglich.',
                'url'    => self::admin_url('providers'),
            ],
            [
                'label'  => 'Erinnerungen (Cron stündlich)',
                'status' => $reminders ? 'ok' : 'muted',
                'value'  => $reminders ? CMS_Booking_Settings::int('reminder_hours', 24) . ' Std. vorher' : 'aus',
            ],
            [
                'label'  => 'Löschfrist',
                'status' => $retentionDays > 0 ? 'ok' : 'info',
                'value'  => $retentionDays > 0 ? $retentionDays . ' Tage nach Termin' : 'nie (Standard)',
                'url'    => self::admin_url('settings'),
            ],
            [
                'label'  => 'Integrationen',
                'status' => $types !== [] ? 'ok' : 'muted',
                'value'  => $types !== [] ? implode(', ', array_map(static fn (array $t): string => (string) ($t['label'] ?? ''), $types)) : 'keine (manuelle Anbieter)',
            ],
        ];

        $activity = [];
        foreach ($recent as $item) {
            $status = (string) $item['status'];
            $activity[] = [
                'title' => '#' . (int) $item['id'] . ' ' . (string) $item['customer_name'] . ' – ' . (string) ($item['service_title'] ?? 'Termin'),
                'url'   => self::admin_url('bookings', ['view' => 'detail', 'id' => (int) $item['id']]),
                'meta'  => date('d.m.Y', (int) strtotime((string) $item['booking_date'])) . ', ' . substr((string) $item['start_time'], 0, 5) . ' Uhr · ' . (string) ($item['provider_name'] ?? ''),
                'badge' => $statusLabels[$status] ?? $status,
                'tone'  => match ($status) {
                    'pending' => 'warning',
                    'confirmed', 'completed' => 'success',
                    'cancelled', 'no_show' => 'muted',
                    default => '',
                },
            ];
        }

        echo cms_plugin_dashboard([
            'intro'   => 'Buchungen gesamt: ' . (int) $stats['total'] . ' · bestätigt und anstehend: ' . (int) $stats['confirmed'] . '.',
            'actions' => [
                ['label' => 'Offene Anfragen bearbeiten', 'url' => self::admin_url('bookings', ['status' => 'pending']), 'icon' => 'ti ti-inbox', 'primary' => true, 'description' => (int) $stats['pending'] . ' warten auf Bestätigung'],
                ['label' => 'Anbieter anlegen', 'url' => self::admin_url('providers', ['view' => 'edit']), 'icon' => 'ti ti-user-plus'],
                ['label' => 'Leistung anlegen', 'url' => self::admin_url('services', ['view' => 'edit']), 'icon' => 'ti ti-plus'],
                ['label' => 'Einstellungen', 'url' => self::admin_url('settings'), 'icon' => 'ti ti-settings'],
            ],
            'checks'   => $checks,
            'activity' => [
                'heading'  => 'Zuletzt eingegangen',
                'items'    => $activity,
                'empty'    => 'Noch keine Buchungen vorhanden.',
                'more_url' => self::admin_url('bookings'),
            ],
        ]);

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
}

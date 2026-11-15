<?php
/**
 * CMS Booking – Admin Einstellungen
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait CMS_Booking_Page_Settings_Trait
{
    public function handle_settings_post(): string
    {
        $error = self::guard_post('booking_settings');
        if ($error !== '') {
            return $error;
        }

        $int = static fn (string $key, int $default, int $min, int $max): string
            => (string) max($min, min($max, (int) (is_scalar($_POST[$key] ?? null) ? $_POST[$key] : $default)));
        $email = static function (string $key): string {
            $value = trim((string) (is_scalar($_POST[$key] ?? null) ? $_POST[$key] : ''));

            return $value === '' ? '' : (string) (filter_var($value, FILTER_VALIDATE_EMAIL) ?: '');
        };
        $check = static fn (string $key): string => !empty($_POST[$key]) ? '1' : '0';

        foreach (['admin_email', 'from_email'] as $key) {
            $raw = trim((string) (is_scalar($_POST[$key] ?? null) ? $_POST[$key] : ''));
            if ($raw !== '' && $email($key) === '') {
                return 'Die Adresse im Feld „' . ($key === 'admin_email' ? 'Admin-E-Mail' : 'Absender-E-Mail') . '“ ist ungültig.';
            }
        }

        $timezone = self::post_text('default_timezone', 80);
        $currency = self::post_text('default_currency', 3);
        $color    = self::post_text('primary_color', 7);
        $advanceMin = (int) $int('booking_advance_min', 1, 0, 365);
        $advanceMax = (int) $int('booking_advance_max', 90, 1, 366);
        if ($advanceMax < $advanceMin) {
            return 'Die maximale Vorlaufzeit muss größer oder gleich der minimalen sein.';
        }

        $values = [
            'admin_email'              => $email('admin_email'),
            'from_name'                => mb_substr(str_replace(['"', '<', '>'], '', self::post_text('from_name', 100)), 0, 100),
            'from_email'               => $email('from_email'),
            'default_duration'         => $int('default_duration', 60, 5, 1440),
            'default_buffer'           => $int('default_buffer', 15, 0, 480),
            'default_timezone'         => in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Europe/Berlin',
            'default_currency'         => in_array($currency, ['EUR', 'CHF', 'USD', 'GBP'], true) ? $currency : 'EUR',
            'booking_advance_min'      => (string) $advanceMin,
            'booking_advance_max'      => (string) $advanceMax,
            'booking_min_notice_hours' => $int('booking_min_notice_hours', 0, 0, 720),
            'booking_daily_limit'      => $int('booking_daily_limit', 0, 0, 1000),
            'booking_rate_limit'       => $int('booking_rate_limit', 10, 0, 1000),
            'cancellation_hours'       => $int('cancellation_hours', 24, 0, 720),
            'allow_customer_cancel'    => $check('allow_customer_cancel'),
            'auto_confirm'             => $check('auto_confirm'),
            'send_reminders'           => $check('send_reminders'),
            'reminder_hours'           => $int('reminder_hours', 24, 1, 168),
            'primary_color_custom'     => $check('primary_color_custom'),
            'primary_color'            => preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : '#3b82f6',
            'retention_days'           => $int('retention_days', 0, 0, 3650),
        ];

        CMS_Booking_Settings::save($values);
        self::redirect_with('settings', [], 'success', 'Einstellungen gespeichert.');
    }

    public function render_settings_page(): void
    {
        $error     = $this->postError;
        $csrfToken = self::generate_nonce('booking_settings');
        $settings  = CMS_Booking_Settings::all();
        // Nach einem Validierungsfehler die eingegebenen Werte erneut anzeigen.
        if ($error !== '' && self::is_post()) {
            foreach (array_keys(CMS_Booking_Settings::defaults()) as $key) {
                if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
                    $settings[$key] = mb_substr((string) $_POST[$key], 0, 255);
                }
            }
        }

        include CMS_BOOKING_PLUGIN_DIR . 'admin/views/settings.php';
    }
}

<?php
/**
 * CMS Booking – Einstellungen
 *
 * Zentraler, pro Request gecachter Zugriff auf die Tabelle `booking_settings`
 * (eine Abfrage statt vieler Einzel-Abfragen in Frontend, Mails und Admin).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * Standardwerte aller bekannten Einstellungen.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'admin_email'              => '',
            'from_name'                => defined('SITE_NAME') ? (string) SITE_NAME : '365CMS',
            'from_email'               => '',
            'default_duration'         => '60',
            'default_buffer'           => '15',
            'default_timezone'         => 'Europe/Berlin',
            'default_currency'         => 'EUR',
            'booking_advance_min'      => '1',
            'booking_advance_max'      => '90',
            'booking_min_notice_hours' => '0',
            'booking_daily_limit'      => '0',
            'booking_rate_limit'       => '10',
            'cancellation_hours'       => '24',
            'allow_customer_cancel'    => '1',
            'auto_confirm'             => '0',
            'send_reminders'           => '1',
            'reminder_hours'           => '24',
            'primary_color_custom'     => '0',
            'primary_color'            => '#3b82f6',
            'retention_days'           => '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        try {
            if (class_exists('CMS\Database')) {
                $db   = \CMS\Database::instance();
                $stmt = $db->prepare("SELECT setting_key, setting_value FROM {$db->getPrefix()}booking_settings");
                if ($stmt !== false && $stmt->execute()) {
                    $stored = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                }
            }
        } catch (\Throwable $e) {
            $stored = [];
        }

        $values = self::defaults();
        foreach ($stored as $key => $value) {
            $values[(string) $key] = (string) ($value ?? '');
        }

        return self::$cache = $values;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    /**
     * Speichert mehrere Einstellungen (Upsert) und verwirft den Cache.
     *
     * @param array<string, string> $values
     */
    public static function save(array $values): void
    {
        if ($values === [] || !class_exists('CMS\Database')) {
            return;
        }

        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "INSERT INTO {$db->getPrefix()}booking_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        if ($stmt === false) {
            throw new \RuntimeException('Einstellungen konnten nicht vorbereitet werden.');
        }

        foreach ($values as $key => $value) {
            $stmt->execute([(string) $key, (string) $value]);
        }

        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Effektive Absenderadresse (Plugin-Einstellung, sonst leer = Core-Mail-Konfiguration). */
    public static function sender_email(): string
    {
        $email = filter_var(trim(self::get('from_email')), FILTER_VALIDATE_EMAIL);

        return is_string($email) ? $email : '';
    }

    public static function currency(): string
    {
        $currency = self::get('default_currency', 'EUR');

        return in_array($currency, ['EUR', 'CHF', 'USD', 'GBP'], true) ? $currency : 'EUR';
    }

    public static function timezone(): string
    {
        $timezone = self::get('default_timezone', 'Europe/Berlin');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Europe/Berlin';
    }
}

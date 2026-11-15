<?php
/**
 * CMS Booking – Datenschutz (DSGVO) und Löschfrist
 *
 * - `dsgvo_export_data($userId, $email)`: Auskunft als JSON-Datei im privaten Speicher
 *   (Bereich `cms-booking/privacy-exports`, Aufräumen nach 30 Tagen über `cms_cron_daily`).
 * - `dsgvo_delete_data($userId, $email)`: personenbezogene Daten der Buchungen anonymisieren.
 * - `cms_member_export_data`: Selbstauskunft im Mitgliederbereich (Schlüssel `bookings`).
 * - Aufbewahrungsregel `cms-booking.bookings` (Einstellung „Löschfrist“, Standard 0 = nie).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Privacy
{
    public const EXPORT_AREA = 'cms-booking/privacy-exports';
    public const EXPORT_KEEP_DAYS = 30;
    public const RETENTION_RULE = 'cms-booking.bookings';
    private const FILE_PATTERN = '/^bookings-\d{8}-\d{6}-[a-f0-9]{12}\.json$/';

    /**
     * Strukturierte Auskunft über alle Buchungen einer Person.
     *
     * @return list<array<string, mixed>>
     */
    public static function collect(int $userId, string $email): array
    {
        $labels = CMS_Booking_Bookings::status_labels();
        $entries = [];
        foreach (CMS_Booking_Bookings::instance()->find_by_subject($userId, $email) as $b) {
            $entries[] = [
                'id'             => (int) $b['id'],
                'provider'       => (string) ($b['provider_name'] ?? ''),
                'service'        => (string) ($b['service_title'] ?? ''),
                'date'           => (string) $b['booking_date'],
                'time'           => substr((string) $b['start_time'], 0, 5) . ' – ' . substr((string) $b['end_time'], 0, 5),
                'status'         => $labels[(string) $b['status']] ?? (string) $b['status'],
                'customer_name'  => (string) $b['customer_name'],
                'customer_email' => (string) $b['customer_email'],
                'customer_phone' => (string) ($b['customer_phone'] ?? ''),
                'notes'          => (string) ($b['notes'] ?? ''),
                'location_type'  => (string) ($b['location_type'] ?? ''),
                'price_cents'    => (int) ($b['price_cents'] ?? 0),
                'currency'       => (string) ($b['currency'] ?? ''),
                'created_at'     => (string) ($b['created_at'] ?? ''),
            ];
        }

        return $entries;
    }

    /**
     * Hook `dsgvo_export_data`: Auskunftsdatei schreiben. Liefert den Dateinamen oder ''.
     */
    public static function handle_export(mixed $userId = null, mixed $email = null): string
    {
        $userId = is_numeric($userId) ? (int) $userId : 0;
        $email  = is_string($email) ? trim($email) : '';

        try {
            $entries = self::collect($userId, $email);
            if ($entries === [] || !class_exists('CMS\Services\PrivateStorageService')) {
                return '';
            }

            $dir  = \CMS\Services\PrivateStorageService::getInstance()->path(self::EXPORT_AREA);
            $name = 'bookings-' . date('Ymd-His') . '-' . substr(hash('sha256', $userId . '|' . strtolower($email) . '|' . microtime(true)), 0, 12) . '.json';
            $json = json_encode([
                'type'         => 'bookings',
                'plugin'       => 'cms-booking',
                'generated_at' => date('c'),
                'subject'      => ['user_id' => $userId > 0 ? $userId : null, 'email' => $email],
                'count'        => count($entries),
                'bookings'     => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if (!is_string($json) || file_put_contents($dir . $name, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Export-Datei konnte nicht geschrieben werden.');
            }
            @chmod($dir . $name, 0640);

            if (class_exists('CMS\Hooks')) {
                // Kompatibilität 3.x
                \CMS\Hooks::doAction('dsgvo_export_data_booking', $userId, ['bookings' => $entries, 'file' => $name]);
            }
            self::audit('cms-booking.privacy.export', 'DSGVO-Auskunft: Buchungen exportiert', ['count' => count($entries), 'file' => $name]);

            return $name;
        } catch (\Throwable $e) {
            error_log('[cms-booking] DSGVO export failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Hook `dsgvo_delete_data`: Buchungen der Person anonymisieren.
     */
    public static function handle_delete(mixed $userId = null, mixed $email = null): int
    {
        $userId = is_numeric($userId) ? (int) $userId : 0;
        $email  = is_string($email) ? trim($email) : '';

        try {
            $count = CMS_Booking_Bookings::instance()->anonymize_subject($userId, $email);
            if ($count > 0) {
                self::audit('cms-booking.privacy.delete', 'DSGVO-Löschung: Buchungen anonymisiert', ['count' => $count]);
            }

            return $count;
        } catch (\Throwable $e) {
            error_log('[cms-booking] DSGVO delete failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Filter `cms_member_export_data`.
     */
    public static function member_export(mixed $export = [], mixed $userId = 0, mixed $email = ''): mixed
    {
        if (!is_array($export)) {
            return $export;
        }
        try {
            $entries = self::collect((int) $userId, is_string($email) ? $email : '');
            if ($entries !== []) {
                $export['bookings'] = $entries;
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] member export skipped: ' . $e->getMessage());
        }

        return $export;
    }

    /**
     * Aufbewahrungsregel im Core registrieren (cms_init, Priorität 20).
     */
    public static function register_retention_rule(): void
    {
        if (!class_exists('CMS\Services\DataRetentionService') || !class_exists('CMS\Services\Retention\RetentionRule')
            || !method_exists('CMS\Services\DataRetentionService', 'registerRule')) {
            return;
        }

        try {
            \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                self::RETENTION_RULE,
                'Buchungen (CMS Booking)',
                'Löscht Buchungen samt Kundendaten, deren Termin länger als die eingestellte Frist zurückliegt.',
                static fn (): int => max(0, CMS_Booking_Settings::int('retention_days', 0)),
                static fn (): int => CMS_Booking_Bookings::instance()->count_older_than(max(0, CMS_Booking_Settings::int('retention_days', 0))),
                static fn (): int => CMS_Booking_Bookings::instance()->prune_older_than(max(0, CMS_Booking_Settings::int('retention_days', 0))),
                '/admin/plugins/booking/settings',
            ));
        } catch (\Throwable $e) {
            error_log('[cms-booking] retention rule not registered: ' . $e->getMessage());
        }
    }

    /**
     * Alte Exportdateien entfernen ($days = 0: alle).
     */
    public static function prune_exports(int $days = self::EXPORT_KEEP_DAYS): int
    {
        if (!class_exists('CMS\Services\PrivateStorageService')) {
            return 0;
        }

        try {
            $dir = \CMS\Services\PrivateStorageService::getInstance()->path(self::EXPORT_AREA);
        } catch (\Throwable $e) {
            return 0;
        }

        $cutoff  = time() - max(0, $days) * 86400;
        $deleted = 0;
        foreach (scandir($dir) ?: [] as $file) {
            if (preg_match(self::FILE_PATTERN, $file) !== 1) {
                continue;
            }
            $path = $dir . $file;
            if (is_file($path) && ($days === 0 || (int) filemtime($path) < $cutoff) && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function audit(string $action, string $message, array $context): void
    {
        try {
            if (class_exists('CMS\AuditLogger')) {
                $logger = \CMS\AuditLogger::instance();
                $category = defined('CMS\AuditLogger::CAT_SETTING') ? \CMS\AuditLogger::CAT_SETTING : 'setting';
                $logger->log($category, $action, $message, 'booking', null, $context, 'info');
            }
        } catch (\Throwable $e) {
            // Audit darf DSGVO-Vorgänge nicht abbrechen.
        }
    }
}

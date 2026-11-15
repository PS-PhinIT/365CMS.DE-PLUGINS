<?php
/**
 * CMS Booking – DSGVO-Auskunftsdateien.
 *
 * Beim Abschließen einer Datenschutzauskunft (Core: Auskunft & Löschen → Hook `dsgvo_export_data`)
 * schreibt das Plugin die Buchungen der Person als JSON in die private Ablage (`PrivateStorageService`,
 * Bereich cms-booking/privacy-exports). Download und Löschen unter Buchungen; der tägliche Cron entfernt
 * Dateien nach EXPORT_KEEP_DAYS Tagen.
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
    private const EXPORT_PATTERN = '/^buchungen-\d{8}-\d{6}-[a-f0-9]{12}\.json$/';

    /** @param list<array<string,mixed>> $entries */
    public static function write_export(?int $userId, string $email, array $entries): void
    {
        $dir = self::export_dir();
        if ($entries === [] || $dir === null) {
            return;
        }
        try {
            $name = 'buchungen-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';
            $json = json_encode([
                'type' => 'cms_booking_bookings',
                'generated_at' => date('c'),
                'subject' => ['user_id' => $userId !== null && $userId > 0 ? $userId : null, 'email' => $email],
                'count' => count($entries),
                'bookings' => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($json) && file_put_contents($dir . $name, $json, LOCK_EX) !== false) {
                @chmod($dir . $name, 0640);
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] DSGVO-Export fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /** @return list<array{name:string,size:int,created_at:string}> */
    public static function exports(): array
    {
        $dir = self::export_dir();
        if ($dir === null) {
            return [];
        }
        $files = [];
        foreach (glob($dir . 'buchungen-*.json') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::EXPORT_PATTERN, $name) === 1) {
                $files[] = ['name' => $name, 'size' => (int) filesize($path), 'created_at' => date('Y-m-d H:i:s', (int) filemtime($path))];
            }
        }
        usort($files, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $files;
    }

    /** Vollständiger Pfad einer Exportdatei oder null (nur gültige Dateinamen, kein Path-Traversal). */
    public static function export_path(string $name): ?string
    {
        $dir = self::export_dir();
        if ($dir === null || preg_match(self::EXPORT_PATTERN, $name) !== 1 || !is_file($dir . $name)) {
            return null;
        }

        return $dir . $name;
    }

    public static function delete_export(string $name): bool
    {
        $path = self::export_path($name);

        return $path !== null && @unlink($path);
    }

    public static function prune_exports(): void
    {
        foreach (self::exports() as $file) {
            if (strtotime($file['created_at']) < time() - self::EXPORT_KEEP_DAYS * 86400) {
                self::delete_export($file['name']);
            }
        }
    }

    public static function delete_all_exports(): void
    {
        foreach (self::exports() as $file) {
            self::delete_export($file['name']);
        }
    }

    private static function export_dir(): ?string
    {
        if (!class_exists('CMS\\Services\\PrivateStorageService')) {
            return null;
        }
        try {
            $dir = \CMS\Services\PrivateStorageService::getInstance()->path(self::EXPORT_AREA);
            return is_dir($dir) ? rtrim($dir, '/\\') . '/' : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

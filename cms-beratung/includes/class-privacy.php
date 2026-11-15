<?php
/**
 * CMS Beratung – DSGVO-Anbindung für Formular-Anfragen (übernommen aus PR #12).
 *
 * Anfragen haben kein Benutzerkonto; zugeordnet wird über die vom Core gelieferte E-Mail-Adresse
 * (bei Konten nur bestätigte Adressen, siehe EmailVerificationService::matchableEmail()).
 *
 * - `dsgvo_export_data`: Anfragen der Person als JSON-Datei in die private Ablage
 *   (`PrivateStorageService`, Bereich cms-beratung/privacy-exports); Download unter Formular-Anfragen,
 *   automatische Entfernung nach EXPORT_KEEP_DAYS Tagen über `cms_cron_daily`.
 * - `dsgvo_delete_data`: alle Anfragen der Person löschen.
 * - `cms_member_export_data`: Anfragen in der Selbstauskunft des Mitgliederbereichs.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Privacy
{
    public const EXPORT_AREA = 'cms-beratung/privacy-exports';
    public const EXPORT_KEEP_DAYS = 30;
    private const EXPORT_PATTERN = '/^beratung-\d{8}-\d{6}-[a-f0-9]{12}\.json$/';

    public static function register(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }
        \CMS\Hooks::addAction('dsgvo_export_data', [self::class, 'dsgvo_export'], 20);
        \CMS\Hooks::addAction('dsgvo_delete_data', [self::class, 'dsgvo_delete'], 20);
        \CMS\Hooks::addFilter('cms_member_export_data', [self::class, 'member_export'], 20);
        \CMS\Hooks::addAction('cms_cron_daily', [self::class, 'prune_exports'], 50);
    }

    /** @return list<array<string,mixed>> */
    public static function collect_person(string $email): array
    {
        $entries = [];
        foreach (CMS_Beratung_Storage::instance()->submissions_for_email($email) as $row) {
            $entries[] = [
                'id' => (int) $row['id'],
                'landingpage' => (string) ($row['landingpage_title'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'name' => (string) ($row['sender_name'] ?? ''),
                'email' => (string) ($row['sender_email'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'company' => (string) ($row['company'] ?? ''),
                'topic' => (string) ($row['topic'] ?? ''),
                'desired_service' => (string) ($row['desired_service'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'consent' => !empty($row['consent']),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'user_agent' => (string) ($row['user_agent'] ?? ''),
            ];
        }

        return $entries;
    }

    public static function dsgvo_export(mixed $userId = null, mixed $email = null): void
    {
        try {
            $email = is_string($email) ? trim($email) : '';
            $entries = self::collect_person($email);
            $dir = self::export_dir();
            if ($entries === [] || $dir === null) {
                return;
            }
            $name = 'beratung-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';
            $json = json_encode([
                'type' => 'cms_beratung_submissions',
                'generated_at' => date('c'),
                'subject' => ['user_id' => is_numeric($userId) && (int) $userId > 0 ? (int) $userId : null, 'email' => $email],
                'count' => count($entries),
                'submissions' => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($json) && file_put_contents($dir . $name, $json, LOCK_EX) !== false) {
                @chmod($dir . $name, 0640);
            }
        } catch (\Throwable $e) {
            error_log('[cms-beratung] DSGVO-Export fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public static function dsgvo_delete(mixed $userId = null, mixed $email = null): void
    {
        try {
            CMS_Beratung_Storage::instance()->delete_submissions_for_email(is_string($email) ? $email : '');
        } catch (\Throwable $e) {
            error_log('[cms-beratung] DSGVO-Löschung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public static function member_export(mixed $export = [], mixed $userId = 0, mixed $email = ''): mixed
    {
        if (!is_array($export)) {
            return $export;
        }
        try {
            $entries = self::collect_person(is_string($email) ? $email : '');
            if ($entries !== []) {
                $export['beratung_anfragen'] = $entries;
            }
        } catch (\Throwable $e) {
            error_log('[cms-beratung] Selbstauskunft fehlgeschlagen: ' . $e->getMessage());
        }

        return $export;
    }

    /** @return list<array{name:string,size:int,created_at:string}> */
    public static function exports(): array
    {
        $dir = self::export_dir();
        if ($dir === null) {
            return [];
        }
        $files = [];
        foreach (glob($dir . 'beratung-*.json') ?: [] as $path) {
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

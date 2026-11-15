<?php
/**
 * CMS Beratung – Anbindung an Core-Erweiterungspunkte von 365CMS 4.0.0.
 *
 * - Sitemap (`cms_sitemap_entries`): veröffentlichte, indexierbare Landingpages.
 * - Seitensuche (`search_results`): veröffentlichte, indexierbare Landingpages, alle Suchwörter müssen passen.
 * - DSGVO (`dsgvo_export_data`, `dsgvo_delete_data`, `cms_member_export_data`): Anfragen per E-Mail-Abgleich.
 * - Löschfrist (`DataRetentionService::registerRule`): Anfragen nach Plugin-Einstellung (Standard: nie).
 * - Systemhinweise (`AdminNoticeService::registerCheck`): fehlende Tabellen, neue Anfragen, fehlender Empfänger.
 *
 * Alle Core-Klassen werden per class_exists()/method_exists() geprüft und jeder Hook ist in try/catch gekapselt.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Integrations
{
    public const RETENTION_KEY = 'cms_beratung.submissions';
    public const EXPORT_AREA = 'cms-beratung/privacy-exports';
    public const EXPORT_KEEP_DAYS = 30;
    private const EXPORT_PATTERN = '/^beratung-\d{8}-\d{6}-[a-f0-9]{12}\.json$/';

    public static function register(): void
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        \CMS\Hooks::addFilter('cms_sitemap_entries', [self::class, 'sitemap_entries'], 20);
        \CMS\Hooks::addFilter('search_results', [self::class, 'search_results'], 20);
        \CMS\Hooks::addAction('dsgvo_export_data', [self::class, 'dsgvo_export'], 20);
        \CMS\Hooks::addAction('dsgvo_delete_data', [self::class, 'dsgvo_delete'], 20);
        \CMS\Hooks::addFilter('cms_member_export_data', [self::class, 'member_export'], 20);
        \CMS\Hooks::addAction('cms_init', [self::class, 'register_retention_rule'], 20);
        \CMS\Hooks::addAction('cms_init', [self::class, 'register_notice_checks'], 20);
        \CMS\Hooks::addAction('cms_cron_daily', [self::class, 'prune_privacy_exports'], 50);
    }

    /** @param mixed $entries */
    public static function sitemap_entries(mixed $entries = []): mixed
    {
        if (!is_array($entries)) {
            return $entries;
        }
        try {
            foreach (CMS_Beratung_Storage::instance()->published_routes() as $route) {
                if ($route['noindex']) {
                    continue;
                }
                $entries[] = [
                    'url' => 'beratung/' . rawurlencode($route['slug']),
                    'lastmod' => $route['updated_at'] !== '' ? $route['updated_at'] : date('Y-m-d H:i:s'),
                    'changefreq' => 'weekly',
                    'priority' => 0.6,
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS Beratung sitemap: ' . $e->getMessage());
        }

        return $entries;
    }

    /**
     * @param mixed $results
     * @param array<string,mixed> $context
     */
    public static function search_results(mixed $results = [], mixed $query = '', mixed $limit = 10, mixed $context = []): mixed
    {
        if (!is_array($results) || !is_string($query)) {
            return $results;
        }
        $type = is_array($context) ? (string) ($context['type'] ?? '') : '';
        if ($type !== '' && $type !== 'beratung') {
            return $results;
        }
        $words = CMS_Beratung_Storage::search_words($query);
        if ($words === [] || !class_exists('CMS\\Database')) {
            return $results;
        }

        try {
            $db = \CMS\Database::instance();
            $prefix = $db->getPrefix();
            $where = ["status = 'published'", 'noindex = 0'];
            $params = [];
            foreach ($words as $word) {
                $like = '%' . CMS_Beratung_Storage::escape_like($word) . '%';
                $where[] = '(public_title LIKE ? OR meta_description LIKE ? OR focus_keyword LIKE ? OR hero_json LIKE ? OR sections_json LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like);
            }
            $max = max(1, min(50, is_numeric($limit) ? (int) $limit : 10));
            $stmt = $db->prepare("SELECT public_title, slug, meta_description, hero_json, updated_at FROM {$prefix}beratung_landingpages WHERE " . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT ?');
            if ($stmt === false) {
                return $results;
            }
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, \PDO::PARAM_STR);
            }
            $stmt->bindValue(count($params) + 1, $max, \PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $hero = json_decode((string) ($row['hero_json'] ?? '{}'), true);
                $excerpt = trim((string) ($row['meta_description'] ?? ''));
                if ($excerpt === '' && is_array($hero)) {
                    $excerpt = trim((string) ($hero['subtitle'] ?? $hero['description'] ?? ''));
                }
                $results[] = [
                    'title' => (string) ($row['public_title'] ?? ''),
                    'url' => '/beratung/' . rawurlencode((string) ($row['slug'] ?? '')),
                    'excerpt' => mb_substr($excerpt, 0, 300),
                    '_type' => 'beratung',
                    '_type_label' => 'Beratung',
                    'date' => (string) ($row['updated_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS Beratung search: ' . $e->getMessage());
        }

        return $results;
    }

    /** @return array<int,array<string,mixed>> Anfragen einer Person in exportierbarer Form */
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

    /** Hook `dsgvo_export_data`: Anfragen der Person als JSON in die private Ablage schreiben. */
    public static function dsgvo_export(mixed $userId = null, mixed $email = null): void
    {
        try {
            $email = is_string($email) ? trim($email) : '';
            $entries = self::collect_person($email);
            $dir = self::export_dir();
            if ($entries === [] || $dir === null) {
                return;
            }
            $name = 'beratung-' . date('Ymd-His') . '-' . substr(hash('sha256', strtolower($email) . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 12) . '.json';
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
            error_log('CMS Beratung DSGVO-Export: ' . $e->getMessage());
        }
    }

    /** Hook `dsgvo_delete_data`: alle Anfragen der Person löschen. */
    public static function dsgvo_delete(mixed $userId = null, mixed $email = null): void
    {
        try {
            CMS_Beratung_Storage::instance()->delete_submissions_for_email(is_string($email) ? $email : '');
        } catch (\Throwable $e) {
            error_log('CMS Beratung DSGVO-Löschung: ' . $e->getMessage());
        }
    }

    /** Filter `cms_member_export_data`: Selbstauskunft im Mitgliederbereich. */
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
            error_log('CMS Beratung Selbstauskunft: ' . $e->getMessage());
        }
        return $export;
    }

    public static function register_retention_rule(): void
    {
        if (!class_exists('CMS\\Services\\DataRetentionService') || !class_exists('CMS\\Services\\Retention\\RetentionRule')) {
            return;
        }
        try {
            \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                self::RETENTION_KEY,
                'Beratungsanfragen (CMS Beratung)',
                'Anfragen aus den Landingpage-Formularen; Frist unter CMS Beratung → Einstellungen → Datenschutz (Standard: nie löschen).',
                static fn(): int => CMS_Beratung_Settings::retention_days(),
                static fn(): int => CMS_Beratung_Storage::instance()->count_submissions_older_than(CMS_Beratung_Settings::retention_days()),
                static function (): int {
                    $days = CMS_Beratung_Settings::retention_days();
                    return $days > 0 ? CMS_Beratung_Storage::instance()->purge_old_submissions($days) : 0;
                },
                '/admin/plugins/cms-beratung/cms-beratung-settings?tab=privacy'
            ));
        } catch (\Throwable $e) {
            error_log('CMS Beratung Löschfrist: ' . $e->getMessage());
        }
    }

    public static function register_notice_checks(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService') || !method_exists(\CMS\Services\AdminNoticeService::class, 'registerCheck')) {
            return;
        }
        try {
            \CMS\Services\AdminNoticeService::registerCheck('CMS Beratung', [self::class, 'notice_check']);
        } catch (\Throwable $e) {
            error_log('CMS Beratung Systemhinweis: ' . $e->getMessage());
        }
    }

    /** Günstige Prüfung (eine information_schema-Abfrage, ein COUNT über einen Index). */
    public static function notice_check(): ?array
    {
        try {
            $make = [\CMS\Services\AdminNoticeService::class, 'makeNotice'];
            $missing = CMS_Beratung_Installer::missing_tables();
            if ($missing !== []) {
                return $make('cms-beratung-tables', 'danger', 'CMS Beratung: Tabellen fehlen', 'Fehlende Tabellen: ' . implode(', ', $missing) . '. Plugin deaktivieren und wieder aktivieren.', '/admin/plugins/cms-beratung', 'Zum Plugin');
            }
            $settings = CMS_Beratung_Settings::all();
            if (($settings['notification_enabled'] ?? '1') === '1' && !filter_var((string) ($settings['contact_recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                return $make('cms-beratung-recipient', 'warning', 'CMS Beratung: Empfänger fehlt', 'Benachrichtigungen über neue Anfragen sind aktiv, aber es ist keine Empfänger-E-Mail hinterlegt.', '/admin/plugins/cms-beratung/cms-beratung-settings?tab=contact', 'Einstellungen öffnen');
            }
            $new = CMS_Beratung_Storage::instance()->count_new_submissions();
            if ($new > 0) {
                return $make('cms-beratung-new-submissions', 'info', 'CMS Beratung: neue Anfragen', $new . ' neue Beratungsanfrage(n) warten auf Bearbeitung.', '/admin/plugins/cms-beratung/cms-beratung-submissions?status=new', 'Anfragen ansehen');
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    private static function export_dir(): ?string
    {
        if (!class_exists('CMS\\Services\\PrivateStorageService')) {
            return null;
        }
        try {
            $dir = \CMS\Services\PrivateStorageService::getInstance()->path(self::EXPORT_AREA);
            return is_string($dir) && is_dir($dir) ? rtrim($dir, '/\\') . '/' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array{name:string,size:int,created_at:string}> */
    public static function privacy_exports(): array
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
        usort($files, static fn(array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $files;
    }

    /** Vollständiger Pfad einer Exportdatei oder null (nur gültige Dateinamen). */
    public static function privacy_export_path(string $name): ?string
    {
        $dir = self::export_dir();
        if ($dir === null || preg_match(self::EXPORT_PATTERN, $name) !== 1 || !is_file($dir . $name)) {
            return null;
        }
        return $dir . $name;
    }

    public static function delete_privacy_export(string $name): bool
    {
        $path = self::privacy_export_path($name);
        return $path !== null && @unlink($path);
    }

    public static function prune_privacy_exports(): void
    {
        foreach (self::privacy_exports() as $file) {
            if (strtotime($file['created_at']) < time() - self::EXPORT_KEEP_DAYS * 86400) {
                self::delete_privacy_export($file['name']);
            }
        }
    }

    public static function delete_privacy_exports(): void
    {
        foreach (self::privacy_exports() as $file) {
            self::delete_privacy_export($file['name']);
        }
    }
}

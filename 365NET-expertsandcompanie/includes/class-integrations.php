<?php
/**
 * Anbindung an Core-Erweiterungspunkte von 365CMS 4.0.0:
 * - `cms_sitemap_entries`: Verzeichnisse und aktive Detailseiten,
 * - `search_results`: öffentliche Experts/Companies (alle Suchwörter müssen vorkommen),
 * - `dsgvo_export_data` / `dsgvo_delete_data`: Einträge mit der E-Mail-Adresse der Person,
 * - `AdminNoticeService::registerCheck()`: Hinweis bei fehlenden/veralteten Tabellen,
 * - `cms_cron_daily`: alte DSGVO-Auskunftsdateien entfernen.
 *
 * Alle Callbacks sind gekapselt (try/catch), damit Sitemap, Suche und DSGVO-Abläufe des Cores nie brechen.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Experts_And_Companie_Integrations
{
    public const PRIVACY_AREA = 'experts-companie/privacy-exports';
    public const PRIVACY_KEEP_DAYS = 30;
    private const PRIVACY_PATTERN = '/^excomp-\d{8}-\d{6}-[a-f0-9]{12}\.json$/';
    private const SEARCH_TYPES = ['experts', 'companies'];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !class_exists('CMS\\Hooks')) {
            return;
        }
        self::$registered = true;

        CMS\Hooks::addFilter('cms_sitemap_entries', [self::class, 'sitemapEntries'], 20);
        CMS\Hooks::addFilter('search_results', [self::class, 'searchResults'], 20);
        CMS\Hooks::addAction('dsgvo_export_data', [self::class, 'privacyExport'], 20);
        CMS\Hooks::addAction('dsgvo_delete_data', [self::class, 'privacyDelete'], 20);
        CMS\Hooks::addAction('cms_cron_daily', [self::class, 'prunePrivacyExports'], 60);
        CMS\Hooks::addAction('cms_init', [self::class, 'registerNoticeCheck'], 20);
    }

    private static function db(): ?CMS_365NET_Experts_And_Companie_Database
    {
        return class_exists('CMS\\Database') && class_exists('CMS_365NET_Experts_And_Companie_Database', false)
            ? CMS_365NET_Experts_And_Companie_Database::instance()
            : null;
    }

    /* ---------------------------------------------------------------------
     * Sitemap
     * ------------------------------------------------------------------- */

    /**
     * @param mixed $entries
     * @return mixed
     */
    public static function sitemapEntries(mixed $entries = []): mixed
    {
        if (!is_array($entries) || ($db = self::db()) === null) {
            return $entries;
        }

        try {
            $latest = '';
            foreach (['experts' => 'experts', 'companies' => 'companies'] as $entity => $path) {
                foreach ($db->getSitemapRows($entity) as $row) {
                    $updated = (string) ($row->updated_at ?? '');
                    $latest = max($latest, $updated);
                    $entries[] = [
                        'url' => $path . '/' . (int) ($row->id ?? 0),
                        'lastmod' => $updated !== '' ? $updated : date('Y-m-d H:i:s'),
                        'changefreq' => 'monthly',
                        'priority' => 0.5,
                    ];
                }
            }
            $lastmod = $latest !== '' ? $latest : date('Y-m-d H:i:s');
            foreach (['experts-companie', 'experts', 'companies'] as $archive) {
                $entries[] = ['url' => $archive, 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => 0.6];
            }
        } catch (\Throwable $e) {
            self::log('Sitemap', $e);
        }

        return $entries;
    }

    /* ---------------------------------------------------------------------
     * Seitensuche
     * ------------------------------------------------------------------- */

    /**
     * @param mixed $results
     * @param mixed $context
     * @return mixed
     */
    public static function searchResults(mixed $results = [], mixed $query = '', mixed $limit = 20, mixed $context = []): mixed
    {
        if (!is_array($results) || !is_string($query) || ($db = self::db()) === null) {
            return $results;
        }

        $type = is_array($context) ? strtolower(trim((string) ($context['type'] ?? ''))) : '';
        if ($type !== '' && !in_array($type, self::SEARCH_TYPES, true)) {
            return $results;
        }

        $words = self::searchWords($query);
        if ($words === []) {
            return $results;
        }
        $limit = max(1, min(20, is_numeric($limit) ? (int) $limit : 20));

        try {
            foreach (self::SEARCH_TYPES as $entity) {
                if ($type !== '' && $type !== $entity) {
                    continue;
                }
                foreach ($db->searchPublic($entity, $words, $limit) as $row) {
                    $results[] = $entity === 'experts' ? self::expertHit($row) : self::companyHit($row);
                }
            }
        } catch (\Throwable $e) {
            self::log('Suche', $e);
        }

        return $results;
    }

    /** @return list<string> */
    private static function searchWords(string $query): array
    {
        $query = trim(strip_tags($query));
        $parts = preg_split('/\s+/u', function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query)) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $part = trim($part, " \t\"'.,;:!?()[]");
            if ((function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part)) >= 2) {
                $words[$part] = $part;
            }
        }

        return array_slice(array_values($words), 0, 6);
    }

    /** @return array<string, mixed> */
    private static function expertHit(object $row): array
    {
        $name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
        $meta = trim(implode(' · ', array_filter([(string) ($row->position ?? ''), (string) ($row->company ?? '')])));

        return [
            'title' => $name !== '' ? $name : 'Expert',
            'url' => 'experts/' . (int) ($row->id ?? 0),
            'excerpt' => self::excerpt(($meta !== '' ? $meta . ' – ' : '') . (string) ($row->biography ?? '')),
            '_type' => 'experts',
            '_type_label' => 'Experts',
            'date' => (string) ($row->updated_at ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private static function companyHit(object $row): array
    {
        $meta = trim(implode(' · ', array_filter([(string) ($row->industry ?? ''), (string) ($row->location_city ?? '')])));

        return [
            'title' => trim((string) ($row->name ?? '')) ?: 'Company',
            'url' => 'companies/' . (int) ($row->id ?? 0),
            'excerpt' => self::excerpt(($meta !== '' ? $meta . ' – ' : '') . (string) ($row->description ?? '')),
            '_type' => 'companies',
            '_type_label' => 'Companies',
            'date' => (string) ($row->updated_at ?? ''),
        ];
    }

    public static function excerpt(string $text, int $length = 220): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) <= $length) {
            return $text;
        }

        return rtrim(function_exists('mb_substr') ? mb_substr($text, 0, $length - 1, 'UTF-8') : substr($text, 0, $length - 1)) . '…';
    }

    /* ---------------------------------------------------------------------
     * DSGVO
     * ------------------------------------------------------------------- */

    /** Hook `dsgvo_export_data`: Auskunftsdatei schreiben (nur wenn Einträge existieren). */
    public static function privacyExport(mixed $userId = null, mixed $email = null): void
    {
        $email = is_string($email) ? trim($email) : '';
        $db = self::db();
        if ($email === '' || $db === null || !class_exists('CMS\\Services\\PrivateStorageService')) {
            return;
        }

        try {
            $data = $db->collectPersonalData($email);
            $count = count($data['experts']) + count($data['companies']);
            if ($count === 0) {
                return;
            }

            $dir = \CMS\Services\PrivateStorageService::getInstance()->path(self::PRIVACY_AREA);
            $name = 'excomp-' . date('Ymd-His') . '-' . substr(hash('sha256', strtolower($email) . '|' . microtime(true)), 0, 12) . '.json';
            $json = json_encode([
                'type' => 'experts_companies',
                'generated_at' => date('c'),
                'subject' => ['user_id' => is_numeric($userId) && (int) $userId > 0 ? (int) $userId : null, 'email' => $email],
                'count' => $count,
                'experts' => $data['experts'],
                'companies' => $data['companies'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($json) || file_put_contents($dir . $name, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Auskunftsdatei konnte nicht geschrieben werden.');
            }
            @chmod($dir . $name, 0640);
            self::audit('excomp.privacy.export', 'DSGVO-Auskunft: Experts/Companies exportiert', ['file' => $name, 'count' => $count], 'info');
        } catch (\Throwable $e) {
            self::log('DSGVO-Auskunft', $e);
        }
    }

    /** Hook `dsgvo_delete_data`: Expert-Profile löschen, Company-Kontaktdaten entfernen. */
    public static function privacyDelete(mixed $userId = null, mixed $email = null): void
    {
        $email = is_string($email) ? trim($email) : '';
        $db = self::db();
        if ($email === '' || $db === null) {
            return;
        }

        try {
            $result = $db->deletePersonalData($email);
            if ($result['experts'] + $result['companies'] > 0) {
                self::audit('excomp.privacy.delete', 'DSGVO-Löschung: Experts/Companies bereinigt', $result, 'warning');
                if (class_exists('CMS_365NET_Experts_And_Companie', false)) {
                    CMS_365NET_Experts_And_Companie::flushPublicCache();
                }
            }
        } catch (\Throwable $e) {
            self::log('DSGVO-Löschung', $e);
        }
    }

    /** @return list<array{name:string,size:int,created_at:string}> */
    public static function listPrivacyExports(): array
    {
        $dir = self::privacyDir();
        if ($dir === null) {
            return [];
        }
        $files = [];
        foreach (glob($dir . 'excomp-*.json') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::PRIVACY_PATTERN, $name) === 1) {
                $files[] = ['name' => $name, 'size' => (int) filesize($path), 'created_at' => date('d.m.Y H:i', (int) filemtime($path))];
            }
        }
        usort($files, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $files;
    }

    /** Download einer Auskunftsdatei (Admin + CSRF prüft der Aufrufer). false = nicht vorhanden. */
    public static function streamPrivacyExport(string $name): bool
    {
        $path = self::privacyPath($name);
        if ($path === null) {
            return false;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        readfile($path);
        exit;
    }

    public static function deletePrivacyExport(string $name): bool
    {
        $path = self::privacyPath($name);

        return $path !== null && @unlink($path);
    }

    /** Hook `cms_cron_daily`: Auskunftsdateien nach PRIVACY_KEEP_DAYS entfernen. */
    public static function prunePrivacyExports(): void
    {
        try {
            $dir = self::privacyDir();
            if ($dir === null) {
                return;
            }
            $cutoff = time() - self::PRIVACY_KEEP_DAYS * 86400;
            foreach (glob($dir . 'excomp-*.json') ?: [] as $path) {
                if (preg_match(self::PRIVACY_PATTERN, basename($path)) === 1 && (int) filemtime($path) < $cutoff) {
                    @unlink($path);
                }
            }
        } catch (\Throwable $e) {
            self::log('Aufräumen der DSGVO-Auskünfte', $e);
        }
    }

    /** Uninstall: eigene Auskunftsdateien entfernen. */
    public static function removeAllPrivacyExports(): void
    {
        $dir = self::privacyDir();
        foreach ($dir !== null ? (glob($dir . 'excomp-*.json') ?: []) : [] as $path) {
            if (preg_match(self::PRIVACY_PATTERN, basename($path)) === 1) {
                @unlink($path);
            }
        }
    }

    private static function privacyDir(): ?string
    {
        if (!class_exists('CMS\\Services\\PrivateStorageService')) {
            return null;
        }
        try {
            return \CMS\Services\PrivateStorageService::getInstance()->path(self::PRIVACY_AREA);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function privacyPath(string $name): ?string
    {
        if (preg_match(self::PRIVACY_PATTERN, $name) !== 1 || !class_exists('CMS\\Services\\PrivateStorageService')) {
            return null;
        }
        $path = \CMS\Services\PrivateStorageService::getInstance()->resolve(self::PRIVACY_AREA, $name);

        return is_string($path) && is_file($path) ? $path : null;
    }

    /* ---------------------------------------------------------------------
     * Systemhinweise
     * ------------------------------------------------------------------- */

    public static function registerNoticeCheck(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService') || !method_exists('CMS\\Services\\AdminNoticeService', 'registerCheck')) {
            return;
        }

        \CMS\Services\AdminNoticeService::registerCheck('365CMS | Experts & Companies', static function (): ?array {
            try {
                $db = self::db();
                if ($db === null || $db->isSchemaCurrent()) {
                    return null;
                }
                $stored = $db->storedSchemaVersion();

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'excomp-schema',
                    $stored === '' ? 'danger' : 'warning',
                    $stored === '' ? 'Experts & Companies: Tabellen fehlen' : 'Experts & Companies: Datenbank-Update ausstehend',
                    $stored === ''
                        ? 'Die Tabellen des Plugins sind nicht angelegt. Plugin einmal deaktivieren und wieder aktivieren.'
                        : 'Schema ' . $stored . ' ist älter als ' . CMS_365NET_Experts_And_Companie_Database::SCHEMA_VERSION . '. Die Migration läuft beim nächsten Seitenaufruf.',
                    '/admin/plugins/experts-companie',
                    'Übersicht öffnen'
                );
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /* ---------------------------------------------------------------------
     * Protokoll
     * ------------------------------------------------------------------- */

    /** @param array<string, mixed> $context */
    private static function audit(string $action, string $message, array $context, string $severity): void
    {
        if (!class_exists('CMS\\AuditLogger')) {
            return;
        }
        try {
            \CMS\AuditLogger::instance()->log(\CMS\AuditLogger::CAT_SETTING, $action, $message, 'plugin', null, $context, $severity);
        } catch (\Throwable) {
        }
    }

    private static function log(string $area, \Throwable $e): void
    {
        if (class_exists('CMS\\Logger')) {
            try {
                \CMS\Logger::instance()->withChannel('plugins')->warning('Experts & Companies: ' . $area . ' fehlgeschlagen.', ['message' => $e->getMessage()]);
                return;
            } catch (\Throwable) {
            }
        }
        error_log('CMS 365NET Experts & Companies [' . $area . ']: ' . $e->getMessage());
    }
}

<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Anbindung an Core-Erweiterungspunkte von 365CMS 4.0.0:
 * Sitemap, Seitensuche, DSGVO-Auskunft/-Löschung, Selbstauskunft, Datenaufbewahrung, Systemhinweise.
 * Jede Anbindung ist gegen fehlende Core-Klassen abgesichert und fängt Fehler ab.
 */
final class CMS_Projects_Integrations
{
    public const RETENTION_RULE = 'cms_projects.audit_logs';
    private const PRIVACY_EXPORT_AREA = 'cms-projects/privacy-exports';

    public function __construct(private readonly CMS_Projects_Service $service)
    {
    }

    public function register(): void
    {
        \CMS\Hooks::addFilter('cms_sitemap_entries', [$this, 'filterSitemapEntries'], 20);
        \CMS\Hooks::addFilter('search_results', [$this, 'filterSearchResults'], 20);
        \CMS\Hooks::addFilter('cms_member_export_data', [$this, 'filterMemberExport'], 20);
        \CMS\Hooks::addAction('dsgvo_export_data', [$this, 'handlePrivacyExport'], 20);
        \CMS\Hooks::addAction('dsgvo_delete_data', [$this, 'handlePrivacyDelete'], 20);
        \CMS\Hooks::addAction('cms_init', [$this, 'registerRetentionRule'], 20);
        \CMS\Hooks::addAction('cms_init', [$this, 'registerAdminNotice'], 20);
    }

    public function filterSitemapEntries(mixed $entries = []): mixed
    {
        if (!is_array($entries) || !CMS_Projects_Settings::get('sitemap_enabled')) {
            return $entries;
        }

        try {
            return array_merge($entries, $this->service->getSitemapEntries());
        } catch (\Throwable $e) {
            error_log('[cms-projects] sitemap entries failed: ' . $e->getMessage());
            return $entries;
        }
    }

    public function filterSearchResults(mixed $results = [], mixed $query = '', mixed $limit = 20, mixed $context = []): mixed
    {
        if (!is_array($results) || !is_string($query) || trim($query) === '' || !CMS_Projects_Settings::get('search_enabled')) {
            return $results;
        }

        $context = is_array($context) ? $context : [];
        $type = (string) ($context['type'] ?? '');
        if ($type !== '' && !in_array($type, ['all', 'plugins', 'projects'], true)) {
            return $results;
        }

        try {
            $locale = str_starts_with(strtolower((string) ($context['locale'] ?? 'de')), 'en') ? 'en' : 'de';
            $limit = is_numeric($limit) ? max(1, min(20, (int) $limit)) : 20;
            return array_merge($results, $this->service->searchPublicProjects($query, $limit, $locale));
        } catch (\Throwable $e) {
            error_log('[cms-projects] search failed: ' . $e->getMessage());
            return $results;
        }
    }

    /** Selbstauskunft im Mitgliederbereich: Audit-Einträge, die die Person als Admin ausgelöst hat. */
    public function filterMemberExport(mixed $export = [], mixed $userId = 0, mixed $email = ''): mixed
    {
        if (!is_array($export) || !is_numeric($userId) || (int) $userId <= 0) {
            return $export;
        }

        try {
            $entries = $this->service->collectPersonalAuditData((int) $userId);
            if ($entries !== []) {
                $export['cms_projects_audit_log'] = $entries;
            }
        } catch (\Throwable $e) {
            error_log('[cms-projects] member export failed: ' . $e->getMessage());
        }

        return $export;
    }

    /** Admin → DSGVO-Anfragen → „Abschließen & exportieren“: Exportdatei im privaten Speicher ablegen. */
    public function handlePrivacyExport(mixed $userId = null, mixed $email = null): void
    {
        $userId = is_numeric($userId) ? (int) $userId : 0;
        if ($userId <= 0 || !class_exists('CMS\\Services\\PrivateStorageService')) {
            return;
        }

        try {
            $entries = $this->service->collectPersonalAuditData($userId);
            if ($entries === []) {
                return;
            }

            $storage = \CMS\Services\PrivateStorageService::getInstance();
            $file = $storage->path(self::PRIVACY_EXPORT_AREA) . 'projects-' . date('Ymd-His') . '-' . $storage->randomFilename('json');
            $json = json_encode([
                'type' => 'cms_projects_audit_log',
                'generated_at' => date('c'),
                'subject' => ['user_id' => $userId],
                'count' => count($entries),
                'entries' => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if (is_string($json) && file_put_contents($file, $json, LOCK_EX) !== false) {
                @chmod($file, 0640);
            }
        } catch (\Throwable $e) {
            error_log('[cms-projects] privacy export failed: ' . $e->getMessage());
        }
    }

    /** Vor der Kontolöschung: Audit-Einträge der Person anonymisieren (Protokoll bleibt nachvollziehbar). */
    public function handlePrivacyDelete(mixed $userId = null, mixed $email = null): void
    {
        $userId = is_numeric($userId) ? (int) $userId : 0;
        if ($userId <= 0) {
            return;
        }

        try {
            $this->service->anonymizePersonalAuditData($userId);
        } catch (\Throwable $e) {
            error_log('[cms-projects] privacy delete failed: ' . $e->getMessage());
        }
    }

    public function registerRetentionRule(): void
    {
        if (!class_exists('CMS\\Services\\DataRetentionService') || !class_exists('CMS\\Services\\Retention\\RetentionRule')) {
            return;
        }

        try {
            $service = $this->service;
            \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
                self::RETENTION_RULE,
                'Projekte: Audit-Log',
                'Änderungsprotokoll von CMS Projects; Frist unter Projekte → Einstellungen (0 = nie löschen).',
                static fn (): int => (int) CMS_Projects_Settings::get('audit_retention_days'),
                static fn (): int => $service->countAuditLogsDue((int) CMS_Projects_Settings::get('audit_retention_days')),
                static fn (): int => $service->pruneAuditLogs((int) CMS_Projects_Settings::get('audit_retention_days')),
                '/admin/plugins/cms-projects/cms-projects-settings',
                \CMS\Services\Retention\RetentionRule::ACTION_DELETE
            ));
        } catch (\Throwable $e) {
            error_log('[cms-projects] retention rule registration failed: ' . $e->getMessage());
        }
    }

    public function registerAdminNotice(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService')) {
            return;
        }

        try {
            \CMS\Services\AdminNoticeService::registerCheck('CMS Projects', static function (): ?array {
                if (CMS_Projects_Settings::isSchemaCurrent()) {
                    return null;
                }

                return \CMS\Services\AdminNoticeService::makeNotice(
                    'cms-projects-schema',
                    'warning',
                    'Projekte: Datenbank nicht aktuell',
                    'Die Tabellen von CMS Projects wurden noch nicht auf Version ' . CMS_Projects_Settings::SCHEMA_VERSION . ' migriert.',
                    '/admin/plugins/cms-projects',
                    'System-Check öffnen'
                );
            });
        } catch (\Throwable $e) {
            error_log('[cms-projects] admin notice registration failed: ' . $e->getMessage());
        }
    }

    /** Beim Deinstallieren: eigene DSGVO-Exportdateien entfernen. */
    public static function removePrivacyExports(): void
    {
        if (!class_exists('CMS\\Services\\PrivateStorageService')) {
            return;
        }

        try {
            $dir = \CMS\Services\PrivateStorageService::getInstance()->path(self::PRIVACY_EXPORT_AREA);
            foreach (glob($dir . 'projects-*.json') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        } catch (\Throwable) {
            // nichts zu tun
        }
    }
}

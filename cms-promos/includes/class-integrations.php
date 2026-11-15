<?php
/**
 * Anbindung an Core-Dienste 4.0.0: Sitemap, Seitensuche, Datenaufbewahrung, Systemhinweise.
 *
 * Alle Einstiegspunkte sind gekapselt – ein Fehler hier darf weder Sitemap, Suche noch Admin brechen.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Integrations
{
    public const RETENTION_KEY = 'cms_promos.expired_promos';
    public const SETTINGS_URL = '/admin/plugins/promos-dashboard/promos-settings';

    /** Hook `cms_init` (Priorität 20). */
    public static function register_core_services(): void
    {
        try {
            self::register_retention_rule();
        } catch (\Throwable $e) {
            error_log('[cms-promos] Retention rule registration failed: ' . $e->getMessage());
        }

        try {
            self::register_admin_notice();
        } catch (\Throwable $e) {
            error_log('[cms-promos] Admin notice registration failed: ' . $e->getMessage());
        }
    }

    private static function register_retention_rule(): void
    {
        if (!class_exists('CMS\\Services\\DataRetentionService') || !class_exists('CMS\\Services\\Retention\\RetentionRule')
            || !method_exists('CMS\\Services\\DataRetentionService', 'registerRule')) {
            return;
        }

        $days = static function (): int {
            try {
                return CMS_Promos_Installer::is_schema_current() ? CMS_Promos_Repository::instance()->get_retention_days() : 0;
            } catch (\Throwable) {
                return 0;
            }
        };

        \CMS\Services\DataRetentionService::registerRule(new \CMS\Services\Retention\RetentionRule(
            self::RETENTION_KEY,
            'Promos: abgelaufene und archivierte Kampagnen',
            'Löscht Promos, deren Laufzeitende bzw. Archivierung länger als die Frist zurückliegt (Einstellung im Plugin, 0 = nie).',
            $days,
            static fn (): int => CMS_Promos_Repository::instance()->count_retention_due($days()),
            static fn (): int => CMS_Promos_Repository::instance()->prune_retention($days()),
            self::SETTINGS_URL,
        ));
    }

    private static function register_admin_notice(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService') || !method_exists('CMS\\Services\\AdminNoticeService', 'registerCheck')) {
            return;
        }

        \CMS\Services\AdminNoticeService::registerCheck('cms-promos', static function (): ?array {
            try {
                // Billig: Schema-Version kommt aus dem Autoload-Cache des OptionStore.
                if (!CMS_Promos_Installer::is_schema_current()) {
                    return \CMS\Services\AdminNoticeService::makeNotice(
                        'cms-promos-schema',
                        'warning',
                        'Promos: Datenbank nicht aktuell',
                        'Die Tabellen des Promo-Plugins fehlen oder haben einen alten Stand. Plugin einmal deaktivieren und wieder aktivieren.',
                        '/admin/plugins/promos-dashboard/promos-dashboard',
                        'Promos öffnen'
                    );
                }

                $repository = CMS_Promos_Repository::instance();
                if (($repository->get_settings()['click_export_enabled'] ?? '0') === '1' && $repository->get_webhook_url() === '') {
                    return \CMS\Services\AdminNoticeService::makeNotice(
                        'cms-promos-webhook',
                        'warning',
                        'Promos: Klick-Export ohne Ziel',
                        'Der Klick-Export ist aktiviert, aber es ist keine (entschlüsselbare) Webhook-URL hinterlegt.',
                        self::SETTINGS_URL,
                        'Einstellungen öffnen'
                    );
                }
            } catch (\Throwable) {
                return null;
            }

            return null;
        });
    }

    /**
     * Filter `cms_sitemap_entries`: nur die Archivseite (DE/EN), wenn sie indexierbar ist und laufende Promos zeigt.
     * Platzierungs-Unterseiten sind Filteransichten (noindex) und Klick-URLs sind Redirects.
     */
    public static function sitemap_entries(mixed $entries = []): mixed
    {
        if (!is_array($entries)) {
            return $entries;
        }

        try {
            if (!CMS_Promos_Installer::is_schema_current()) {
                return $entries;
            }

            $repository = CMS_Promos_Repository::instance();
            if (($repository->get_settings()['archive_indexable'] ?? '1') !== '1') {
                return $entries;
            }

            $summary = $repository->get_live_summary();
            if ($summary['count'] <= 0) {
                return $entries;
            }

            $lastmod = $summary['lastmod'] !== '' ? $summary['lastmod'] : gmdate('Y-m-d H:i:s');
            $entries[] = ['url' => 'promos', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => 0.5];
            $entries[] = ['url' => 'en/promos', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => 0.4];
        } catch (\Throwable $e) {
            error_log('[cms-promos] Sitemap entries failed: ' . $e->getMessage());
        }

        return $entries;
    }

    /**
     * Filter `search_results`: laufende Promos, die alle Suchwörter enthalten; Link auf die Karte im Archiv.
     *
     * @param array<string,mixed> $context
     */
    public static function search_results(mixed $results = [], mixed $query = '', mixed $limit = 10, mixed $context = []): mixed
    {
        if (!is_array($results) || !is_string($query)) {
            return $results;
        }

        try {
            $context = is_array($context) ? $context : [];
            $type = (string) ($context['type'] ?? '');
            if ($type !== '' && $type !== 'promos') {
                return $results;
            }
            if (!CMS_Promos_Installer::is_schema_current()) {
                return $results;
            }

            $repository = CMS_Promos_Repository::instance();
            $words = $repository->search_words($query);
            if ($words === []) {
                return $results;
            }

            $controller = CMS_Promos_Public_Controller::instance();
            $locale = strtolower((string) ($context['locale'] ?? ''));
            $lang = $locale !== '' ? (str_starts_with($locale, 'en') ? 'en' : 'de') : $controller->current_public_lang();

            foreach ($repository->search_live_promos($words, max(1, min(20, (int) $limit))) as $row) {
                $title = $controller->i18n_value($row, 'title', $lang, (string) ($row['title'] ?? ''));
                $excerpt = $controller->i18n_value($row, 'teaser', $lang);
                if ($excerpt === '') {
                    $excerpt = trim((string) preg_replace('/\s+/u', ' ', strip_tags($controller->i18n_value($row, 'content_html', $lang))));
                }

                $results[] = [
                    'title' => $title,
                    'url' => $controller->localized_path('promos', $lang) . '#promo-' . rawurlencode((string) ($row['slug'] ?? '')),
                    'excerpt' => mb_substr($excerpt, 0, 240),
                    '_type' => 'promos',
                    '_type_label' => 'Promos',
                    'date' => (string) ($row['updated_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[cms-promos] Search failed: ' . $e->getMessage());
        }

        return $results;
    }
}

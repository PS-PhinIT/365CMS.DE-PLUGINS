<?php
/**
 * Anbindung an Core-Erweiterungspunkte von 365CMS 4.0.0: Sitemap, Seitensuche, Selbstauskunft im
 * Mitgliederbereich und Systemhinweise. Jede Anbindung ist mit class_exists() abgesichert und fängt
 * Fehler ab, damit Sitemap, Suche und Admin nie wegen dieses Plugins brechen.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Integrations
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        CMS\Hooks::addFilter('cms_sitemap_entries', [$this, 'sitemapEntries'], 20);
        CMS\Hooks::addFilter('search_results', [$this, 'searchResults'], 20);
        CMS\Hooks::addFilter('cms_member_export_data', [$this, 'memberExport'], 20);
        CMS\Hooks::addAction('cms_init', [$this, 'registerNoticeCheck'], 20);
    }

    /**
     * Öffentliche, veröffentlichte Detailseiten und die beiden Übersichten für plugins.xml.
     *
     * @param mixed $entries
     * @return mixed
     */
    public function sitemapEntries(mixed $entries = []): mixed
    {
        if (!is_array($entries) || !class_exists('CMS_365NET_Events_Database')) {
            return $entries;
        }

        try {
            $db = CMS_365NET_Events_Database::instance();
            $entries[] = ['url' => 'events', 'changefreq' => 'daily', 'priority' => 0.7];
            $entries[] = ['url' => 'speakers', 'changefreq' => 'weekly', 'priority' => 0.5];

            foreach ($db->getSitemapRows('events') as $row) {
                $entries[] = [
                    'url' => 'events/' . rawurlencode((string) $row->slug),
                    'lastmod' => (string) ($row->updated_at ?? ''),
                    'changefreq' => 'weekly',
                    'priority' => 0.6,
                ];
            }
            foreach ($db->getSitemapRows('speakers') as $row) {
                $entries[] = [
                    'url' => 'speakers/' . rawurlencode((string) $row->slug),
                    'lastmod' => (string) ($row->updated_at ?? ''),
                    'changefreq' => 'monthly',
                    'priority' => 0.4,
                ];
            }
        } catch (\Throwable $e) {
            $this->log('sitemap', $e);
        }

        return $entries;
    }

    /**
     * Treffer der Seitensuche: nur veröffentlichte Events/Speaker, die alle Suchwörter enthalten.
     *
     * @param mixed $results
     * @param mixed $query
     * @param mixed $limit
     * @param mixed $context
     * @return mixed
     */
    public function searchResults(mixed $results = [], mixed $query = '', mixed $limit = 20, mixed $context = []): mixed
    {
        if (!is_array($results) || !is_string($query) || !class_exists('CMS_365NET_Events_Database')) {
            return $results;
        }

        $type = is_array($context) ? (string) ($context['type'] ?? '') : '';
        if ($type !== '' && !in_array($type, ['events', 'speakers'], true)) {
            return $results;
        }

        $words = preg_split('/\s+/u', trim($query)) ?: [];
        $words = array_values(array_filter($words, static fn(string $word): bool => (function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word)) >= 2));
        if ($words === []) {
            return $results;
        }
        $limit = max(1, min(50, is_numeric($limit) ? (int) $limit : 20));

        try {
            $db = CMS_365NET_Events_Database::instance();
            if ($type === '' || $type === 'events') {
                foreach ($db->searchPublished('events', $words, $limit) as $row) {
                    $meta = trim((string) ($row->date_label ?? '') . ' · ' . (string) ($row->location ?? ''), ' ·');
                    $text = (string) (($row->excerpt ?? '') ?: ($row->description ?? ''));
                    $results[] = [
                        'title' => (string) $row->title,
                        'url' => 'events/' . rawurlencode((string) $row->slug),
                        'excerpt' => $this->excerpt(($meta !== '' ? $meta . ' – ' : '') . $text),
                        '_type' => 'events',
                        '_type_label' => 'Events',
                        'date' => (string) (($row->start_date ?? '') ?: ($row->updated_at ?? '')),
                    ];
                }
            }
            if ($type === '' || $type === 'speakers') {
                foreach ($db->searchPublished('speakers', $words, $limit) as $row) {
                    $results[] = [
                        'title' => (string) $row->title,
                        'url' => 'speakers/' . rawurlencode((string) $row->slug),
                        'excerpt' => $this->excerpt(trim((string) ($row->topic ?? '') . ' ' . (string) ($row->award ?? '') . ' ' . (string) ($row->excerpt ?? ''))),
                        '_type' => 'speakers',
                        '_type_label' => 'Speaker',
                        'date' => (string) ($row->updated_at ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->log('search', $e);
        }

        return $results;
    }

    /**
     * Selbstauskunft im Mitgliederbereich: Speaker-Profile, die mit der E-Mail-Adresse des Mitglieds
     * hinterlegt sind (Speaker sind keine CMS-Konten; der Abgleich erfolgt nur über die E-Mail).
     *
     * @param mixed $export
     * @param mixed $userId
     * @param mixed $email
     * @return mixed
     */
    public function memberExport(mixed $export = [], mixed $userId = 0, mixed $email = ''): mixed
    {
        if (!is_array($export) || !is_string($email) || trim($email) === '' || !class_exists('CMS_365NET_Events_Database')) {
            return $export;
        }

        try {
            $profiles = CMS_365NET_Events_Database::instance()->getSpeakerProfilesByEmail($email);
            if ($profiles !== []) {
                $export['event_speaker_profiles'] = $profiles;
            }
        } catch (\Throwable $e) {
            $this->log('member_export', $e);
        }

        return $export;
    }

    /** Systemhinweis im Admin: fehlende Tabellen bzw. ausstehende Schema-Aktualisierung. */
    public function registerNoticeCheck(): void
    {
        if (!class_exists('CMS\\Services\\AdminNoticeService') || !method_exists('CMS\\Services\\AdminNoticeService', 'registerCheck')) {
            return;
        }

        CMS\Services\AdminNoticeService::registerCheck('365net-events-and-speaker', static function (): ?array {
            if (!class_exists('CMS_365NET_Events_Database') || !class_exists('CMS_365NET_Events_Admin')) {
                return null;
            }

            try {
                $db = CMS_365NET_Events_Database::instance();
                if (!$db->tablesPresent()) {
                    return CMS\Services\AdminNoticeService::makeNotice(
                        '365net-events-tables',
                        'danger',
                        'Events & Speaker: Datenbanktabellen fehlen',
                        'Die Tabellen des Plugins sind nicht vollständig vorhanden. Plugin deaktivieren und erneut aktivieren.',
                        CMS_365NET_Events_Admin::url(),
                        'Plugin öffnen'
                    );
                }
                if (!$db->isSchemaCurrent()) {
                    return CMS\Services\AdminNoticeService::makeNotice(
                        '365net-events-schema',
                        'warning',
                        'Events & Speaker: Datenbank-Aktualisierung ausstehend',
                        'Die Schema-Version passt nicht zum Plugin-Stand; sie wird beim nächsten Seitenaufruf angehoben.',
                        CMS_365NET_Events_Admin::url(),
                        'Plugin öffnen'
                    );
                }
            } catch (\Throwable) {
                return null;
            }

            return null;
        });
    }

    private function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return function_exists('mb_substr') ? (string) mb_substr($text, 0, 220, 'UTF-8') : substr($text, 0, 220);
    }

    private function log(string $context, \Throwable $error): void
    {
        if (class_exists('CMS_365NET_Events')) {
            CMS_365NET_Events::instance()->log('integrations.' . $context, $error);
        }
    }
}

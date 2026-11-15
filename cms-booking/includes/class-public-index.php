<?php
/**
 * CMS Booking – Sitemap- und Sucheinträge für öffentliche Buchungsseiten
 *
 * - `cms_sitemap_entries`: Anbieterseiten `/booking/<anbieter>` und Leistungsseiten
 *   `/booking/<anbieter>/<leistung>` aktiver Anbieter mit aktiven Leistungen.
 *   Bestätigungs-, iCal- und API-Routen sind privat und werden nicht gemeldet.
 * - `search_results`: aktive Leistungen, deren Titel/Beschreibung/Anbietername alle Suchwörter enthalten.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Public_Index
{
    public const SEARCH_TYPE = 'booking';

    public static function sitemap_entries(mixed $entries = []): mixed
    {
        if (!is_array($entries)) {
            return $entries;
        }

        try {
            if (!CMS_Booking_Installer::tables_present()) {
                return $entries;
            }

            $providers = CMS_Booking_Providers::instance()->get_public_index();
            foreach ($providers as $provider) {
                $slug = (string) ($provider['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $entries[] = [
                    'url'        => 'booking/' . rawurlencode($slug),
                    'lastmod'    => (string) ($provider['updated_at'] ?? date('Y-m-d H:i:s')),
                    'changefreq' => 'weekly',
                    'priority'   => 0.6,
                ];

                foreach (CMS_Booking_Services::instance()->get_by_provider((int) $provider['id'], 'active') as $service) {
                    $serviceSlug = (string) ($service['slug'] ?? '');
                    if ($serviceSlug === '') {
                        continue;
                    }
                    $entries[] = [
                        'url'        => 'booking/' . rawurlencode($slug) . '/' . rawurlencode($serviceSlug),
                        'lastmod'    => (string) ($service['updated_at'] ?? $provider['updated_at'] ?? date('Y-m-d H:i:s')),
                        'changefreq' => 'weekly',
                        'priority'   => 0.5,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] sitemap entries skipped: ' . $e->getMessage());
        }

        return $entries;
    }

    public static function search_results(mixed $results = [], mixed $query = '', mixed $limit = 10, mixed $context = []): mixed
    {
        if (!is_array($results)) {
            return $results;
        }

        try {
            $type = is_array($context) ? (string) ($context['type'] ?? '') : '';
            if ($type !== '' && $type !== self::SEARCH_TYPE) {
                return $results;
            }

            $query = is_string($query) ? trim($query) : '';
            if (mb_strlen($query) < 2 || !CMS_Booking_Installer::tables_present()) {
                return $results;
            }

            $words = preg_split('/\s+/u', mb_strtolower($query)) ?: [];
            $rows  = CMS_Booking_Services::instance()->search_public($words, max(1, min(20, (int) $limit)));
            foreach ($rows as $row) {
                $title = (string) $row['title'] . ' – ' . (string) $row['provider_name'];
                $excerpt = trim(strip_tags((string) ($row['description'] ?? '')));
                $haystack = mb_strtolower($title . ' ' . $excerpt);
                $allMatch = true;
                foreach ($words as $word) {
                    if ($word !== '' && !str_contains($haystack, $word)) {
                        $allMatch = false;
                        break;
                    }
                }
                if (!$allMatch) {
                    continue;
                }
                $results[] = [
                    'title'       => $title,
                    'url'         => '/booking/' . rawurlencode((string) $row['provider_slug']) . '/' . rawurlencode((string) $row['slug']),
                    'excerpt'     => mb_substr($excerpt, 0, 200),
                    '_type'       => self::SEARCH_TYPE,
                    '_type_label' => 'Termine',
                    'date'        => (string) ($row['updated_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] search skipped: ' . $e->getMessage());
        }

        return $results;
    }
}

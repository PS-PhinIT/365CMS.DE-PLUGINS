<?php
/**
 * CMS Beratung – SEO meta and structured data.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_SEO
{
    /** @param array<string,mixed> $page */
    public static function output_head(array $page): void
    {
        $settings = CMS_Beratung_Settings::all();
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        $title = trim((string) ($page['meta_title'] ?? '')) ?: (string) ($page['public_title'] ?? 'CMS Beratung');
        $description = trim((string) ($page['meta_description'] ?? ''));
        $ogTitle = trim((string) ($seo['og_title'] ?? '')) ?: $title;
        $ogDescription = trim((string) ($seo['og_description'] ?? '')) ?: $description;
        $twitterTitle = trim((string) ($seo['twitter_title'] ?? '')) ?: $ogTitle;
        $twitterDescription = trim((string) ($seo['twitter_description'] ?? '')) ?: $ogDescription;
        $robots = [];
        if (($settings['seo_noindex_per_page_enabled'] ?? '1') === '1' && !empty($page['noindex'])) {
            $robots[] = 'noindex';
        }
        if (($settings['seo_nofollow_per_page_enabled'] ?? '1') === '1' && !empty($page['nofollow'])) {
            $robots[] = 'nofollow';
        }

        if (($settings['seo_meta_title_enabled'] ?? '1') === '1') {
            echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
        }
        if (($settings['seo_meta_description_enabled'] ?? '1') === '1' && $description !== '') {
            echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($robots !== []) {
            echo '<meta name="robots" content="' . htmlspecialchars(implode(', ', $robots), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if (($settings['seo_canonical_enabled'] ?? '1') === '1') {
            $canonical = trim((string) ($page['canonical_url'] ?? ''));
            if ($canonical === '') {
                $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
                $canonical = $base . '/beratung/' . trim((string) ($page['slug'] ?? ''), '/');
            }
            echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if (($settings['seo_open_graph_enabled'] ?? '1') === '1') {
            echo '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            if ($ogDescription !== '') {
                echo '<meta property="og:description" content="' . htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
            if (!empty($seo['og_image'])) { echo '<meta property="og:image" content="' . htmlspecialchars((string) $seo['og_image'], ENT_QUOTES, 'UTF-8') . '">' . "\n"; }
            echo '<meta property="og:type" content="website">' . "\n";
        }
        if (($settings['seo_twitter_card_enabled'] ?? '1') === '1') {
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . htmlspecialchars($twitterTitle, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            if ($twitterDescription !== '') { echo '<meta name="twitter:description" content="' . htmlspecialchars($twitterDescription, ENT_QUOTES, 'UTF-8') . '">' . "\n"; }
            if (!empty($seo['twitter_image'])) { echo '<meta name="twitter:image" content="' . htmlspecialchars((string) $seo['twitter_image'], ENT_QUOTES, 'UTF-8') . '">' . "\n"; }
        }
        self::output_schema($page, $settings);
        self::output_faq_schema($page, $settings);
        self::output_breadcrumb_schema($page, $settings);
        self::output_org_schema($page);
    }

    /** @param array<string,mixed> $page @param array<string,string> $settings */
    private static function output_schema(array $page, array $settings): void
    {
        if (($settings['seo_service_schema_enabled'] ?? '1') !== '1') {
            return;
        }
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => (string) ($seo['service_name'] ?? $page['public_title'] ?? 'CMS Beratung'),
            'description' => (string) ($seo['service_description'] ?? $page['meta_description'] ?? ''),
            'provider' => [
                '@type' => 'Organization',
                'name' => (string) ($seo['provider_name'] ?? '365CMS'),
            ],
            'areaServed' => (string) ($seo['area_served'] ?? 'DE'),
            'audience' => (string) ($seo['audience'] ?? 'IT Administratoren und Unternehmen'),
            'serviceType' => (string) ($seo['category'] ?? 'Microsoft 365 Consulting'),
        ];
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    /** @param array<string,mixed> $page @param array<string,string> $settings */
    private static function output_faq_schema(array $page, array $settings): void
    {
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        if (($settings['seo_faq_schema_enabled'] ?? '1') !== '1' || (array_key_exists('faq_schema_enabled', $seo) && empty($seo['faq_schema_enabled']))) {
            return;
        }
        $entities = [];
        $faq = CMS_Beratung_Storage::instance()->m365_faq_config();
        if (empty($faq['enabled']) || empty($faq['schema_enabled'])) {
            return;
        }
        $items = is_array($faq['items'] ?? null) ? $faq['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['enabled'])) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }
        if ($entities === []) {
            return;
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    /** @param array<string,mixed> $page @param array<string,string> $settings */
    private static function output_breadcrumb_schema(array $page, array $settings): void
    {
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        if (($settings['seo_breadcrumb_schema_enabled'] ?? '1') !== '1' || (array_key_exists('breadcrumb_schema_enabled', $seo) && empty($seo['breadcrumb_schema_enabled']))) {
            return;
        }
        $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
        $schema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => (string) ($page['public_title'] ?? 'Beratung'), 'item' => $base . '/beratung/' . trim((string) ($page['slug'] ?? ''), '/')],
        ]];
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    /** @param array<string,mixed> $page */
    private static function output_org_schema(array $page): void
    {
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        if (empty($seo['organization_schema_enabled']) && empty($seo['local_business_schema_enabled'])) {
            return;
        }
        $schema = ['@context' => 'https://schema.org', '@type' => !empty($seo['local_business_schema_enabled']) ? 'LocalBusiness' : 'Organization', 'name' => (string) ($seo['provider_name'] ?? '365CMS'), 'url' => (string) ($seo['provider_url'] ?? (defined('SITE_URL') ? SITE_URL : ''))];
        if (!empty($seo['region'])) { $schema['areaServed'] = (string) $seo['region']; }
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }
}

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
    /** true, sobald Titel/Meta/Canonical/Social über den Core (SEOService) gesetzt wurden. */
    private static bool $coreMetaApplied = false;

    public static function canonical_url(array $page): string
    {
        $canonical = trim((string) ($page['canonical_url'] ?? ''));
        if ($canonical !== '') {
            return $canonical;
        }
        $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
        return $base . '/beratung/' . rawurlencode(trim((string) ($page['slug'] ?? ''), '/'));
    }

    /**
     * Meta-Angaben der Landingpage für diesen Request an den Core übergeben (Core 4.0.0:
     * SEOService::setRequestMeta). Muss vor dem Theme-Header laufen.
     *
     * @param array<string,mixed> $page
     */
    public static function apply_request_meta(array $page): void
    {
        if (!class_exists('CMS\\Services\\SEOService') || !method_exists(\CMS\Services\SEOService::class, 'getInstance')) {
            return;
        }
        $settings = CMS_Beratung_Settings::all();
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        $title = trim((string) ($page['meta_title'] ?? '')) ?: (string) ($page['public_title'] ?? 'CMS Beratung');
        $description = trim((string) ($page['meta_description'] ?? ''));
        $ogTitle = trim((string) ($seo['og_title'] ?? '')) ?: $title;
        $ogDescription = trim((string) ($seo['og_description'] ?? '')) ?: $description;

        $payload = [
            'robots_index' => !(($settings['seo_noindex_per_page_enabled'] ?? '1') === '1' && !empty($page['noindex'])) && empty($page['is_standalone_variant']),
            'robots_follow' => !(($settings['seo_nofollow_per_page_enabled'] ?? '1') === '1' && !empty($page['nofollow'])),
            'og_type' => 'website',
            'schema_type' => 'WebPage',
        ];
        if (($settings['seo_meta_title_enabled'] ?? '1') === '1') {
            $payload['title'] = $title;
        }
        if (($settings['seo_meta_description_enabled'] ?? '1') === '1' && $description !== '') {
            $payload['description'] = $description;
        }
        if (($settings['seo_canonical_enabled'] ?? '1') === '1') {
            $payload['canonical_url'] = self::canonical_url($page);
        }
        if (($settings['seo_open_graph_enabled'] ?? '1') === '1') {
            $payload['og_title'] = $ogTitle;
            $payload['og_description'] = $ogDescription;
            if (!empty($seo['og_image'])) {
                $payload['og_image'] = (string) $seo['og_image'];
            }
        }
        if (($settings['seo_twitter_card_enabled'] ?? '1') === '1') {
            $payload['twitter_card'] = 'summary_large_image';
            $payload['twitter_title'] = trim((string) ($seo['twitter_title'] ?? '')) ?: $ogTitle;
            $payload['twitter_description'] = trim((string) ($seo['twitter_description'] ?? '')) ?: $ogDescription;
            if (!empty($seo['twitter_image'])) {
                $payload['twitter_image'] = (string) $seo['twitter_image'];
            }
        }

        try {
            \CMS\Services\SEOService::getInstance()->setRequestMeta($payload);
            self::$coreMetaApplied = true;
        } catch (\Throwable $e) {
            error_log('CMS Beratung SEO meta: ' . $e->getMessage());
            self::$coreMetaApplied = false;
        }
    }

    /**
     * Hook `head`: Ohne Core-SEO-Service die klassischen Meta-Tags ausgeben; immer die
     * plugin-spezifischen JSON-LD-Daten (Service, FAQPage, Breadcrumb, Organisation).
     *
     * @param array<string,mixed> $page
     */
    public static function output_head(array $page): void
    {
        $settings = CMS_Beratung_Settings::all();
        if (!self::$coreMetaApplied) {
            self::output_legacy_meta($page, $settings);
        }
        self::output_schema($page, $settings);
        self::output_faq_schema($page, $settings);
        self::output_breadcrumb_schema($page, $settings);
        self::output_org_schema($page);
    }

    /** @param array<string,mixed> $page @param array<string,string> $settings */
    private static function output_legacy_meta(array $page, array $settings): void
    {
        $seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
        $title = trim((string) ($page['meta_title'] ?? '')) ?: (string) ($page['public_title'] ?? 'CMS Beratung');
        $description = trim((string) ($page['meta_description'] ?? ''));
        $ogTitle = trim((string) ($seo['og_title'] ?? '')) ?: $title;
        $ogDescription = trim((string) ($seo['og_description'] ?? '')) ?: $description;
        $twitterTitle = trim((string) ($seo['twitter_title'] ?? '')) ?: $ogTitle;
        $twitterDescription = trim((string) ($seo['twitter_description'] ?? '')) ?: $ogDescription;
        $robots = [];
        if ((($settings['seo_noindex_per_page_enabled'] ?? '1') === '1' && !empty($page['noindex'])) || !empty($page['is_standalone_variant'])) {
            $robots[] = 'noindex';
        }
        if (($settings['seo_nofollow_per_page_enabled'] ?? '1') === '1' && !empty($page['nofollow'])) {
            $robots[] = 'nofollow';
        }
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if (($settings['seo_meta_description_enabled'] ?? '1') === '1' && $description !== '') {
            echo '<meta name="description" content="' . $esc($description) . '">' . "\n";
        }
        if ($robots !== []) {
            echo '<meta name="robots" content="' . $esc(implode(', ', $robots)) . '">' . "\n";
        }
        if (($settings['seo_canonical_enabled'] ?? '1') === '1') {
            echo '<link rel="canonical" href="' . $esc(self::canonical_url($page)) . '">' . "\n";
        }
        if (($settings['seo_open_graph_enabled'] ?? '1') === '1') {
            echo '<meta property="og:title" content="' . $esc($ogTitle) . '">' . "\n";
            if ($ogDescription !== '') {
                echo '<meta property="og:description" content="' . $esc($ogDescription) . '">' . "\n";
            }
            if (!empty($seo['og_image'])) {
                echo '<meta property="og:image" content="' . $esc((string) $seo['og_image']) . '">' . "\n";
            }
            echo '<meta property="og:type" content="website">' . "\n";
        }
        if (($settings['seo_twitter_card_enabled'] ?? '1') === '1') {
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . $esc($twitterTitle) . '">' . "\n";
            if ($twitterDescription !== '') {
                echo '<meta name="twitter:description" content="' . $esc($twitterDescription) . '">' . "\n";
            }
            if (!empty($seo['twitter_image'])) {
                echo '<meta name="twitter:image" content="' . $esc((string) $seo['twitter_image']) . '">' . "\n";
            }
        }
    }

    /** JSON-LD sicher ausgeben: `</script>` und HTML-Sonderzeichen werden escaped, Nonce für die CSP. */
    private static function print_json_ld(array $schema): void
    {
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) {
            return;
        }
        $nonce = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
        echo '<script type="application/ld+json"' . $nonce . '>' . $json . '</script>' . "\n";
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
        self::print_json_ld($schema);
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
        self::print_json_ld($schema);
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
            ['@type' => 'ListItem', 'position' => 2, 'name' => (string) ($page['public_title'] ?? 'Beratung'), 'item' => $base . '/beratung/' . rawurlencode(trim((string) ($page['slug'] ?? ''), '/'))],
        ]];
        self::print_json_ld($schema);
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
        self::print_json_ld($schema);
    }
}

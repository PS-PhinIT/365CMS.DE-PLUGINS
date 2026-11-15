<?php
/**
 * CMS Beratung – JSON import/export.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Import_Export
{
    /** @param array<string,mixed> $page @return array<string,mixed> */
    public static function export_page(array $page): array
    {
        return [
            'schema' => 'cms-beratung-landingpage/v1',
            'format_version' => 1,
            'exported_at' => date('c'),
            'plugin_version' => CMS_BERATUNG_VERSION,
            'landingpage' => self::sanitize_landingpage_payload($page),
        ];
    }

    public static function export_json(array $page): string
    {
        return json_encode(self::export_page($page), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /** @return array<string,mixed>|null */
    public static function decode_import_json(string $json): ?array
    {
        $decoded = json_decode($json, true, 64);
        if (!is_array($decoded)) {
            return null;
        }
        $page = isset($decoded['landingpage']) && is_array($decoded['landingpage']) ? $decoded['landingpage'] : $decoded;
        return self::sanitize_landingpage_payload($page);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function sanitize_landingpage_payload(array $data): array
    {
        $statuses = CMS_Beratung_Settings::statuses();
        $templates = CMS_Beratung_Settings::templates();
        $heroRaw = $data['hero'] ?? $data['hero_json'] ?? [];
        if (is_string($heroRaw)) {
            $decodedHero = json_decode($heroRaw, true);
            $heroRaw = is_array($decodedHero) ? $decodedHero : [];
        }
        if (!is_array($heroRaw)) {
            $heroRaw = [];
        }
        foreach (['standalone_header_enabled', 'standalone_header_slug', 'standalone_header_blog_logo_url', 'standalone_header_partner_logo_url', 'standalone_header_title', 'standalone_header_subtitle', 'standalone_header_menu_enabled'] as $standaloneKey) {
            if (array_key_exists($standaloneKey, $data)) {
                $heroRaw[$standaloneKey] = $data[$standaloneKey];
            }
        }
        for ($i = 1; $i <= 4; $i++) {
            foreach (['label', 'target'] as $menuField) {
                $key = 'standalone_header_menu_' . $menuField . '_' . $i;
                if (array_key_exists($key, $data)) {
                    $heroRaw[$key] = $data[$key];
                }
            }
        }
        $hero = self::sanitize_hero($heroRaw);
        $contactRaw = $data['contact'] ?? $data['contact_json'] ?? $data;
        if (array_key_exists('contact_enabled', $data)) {
            if (is_string($contactRaw)) {
                $decodedContact = json_decode($contactRaw, true);
                $contactRaw = is_array($decodedContact) ? $decodedContact : [];
            }
            if (!is_array($contactRaw)) {
                $contactRaw = [];
            }
            $contactRaw['enabled'] = !empty($data['contact_enabled']);
        }
        $contact = self::sanitize_contact($contactRaw);
        $seo = self::sanitize_seo($data['seo'] ?? $data['seo_json'] ?? $data);
        $sections = self::sanitize_sections($data['sections'] ?? $data['sections_json'] ?? []);
        $design = self::sanitize_design($data['design'] ?? $data['design_json'] ?? []);

        $payload = [
            'id' => (int) ($data['id'] ?? 0),
            'tenant_id' => !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null,
            'internal_title' => self::limit(CMS_Beratung_Settings::text((string) ($data['internal_title'] ?? 'Neue Beratungs Landingpage')), 255),
            'public_title' => self::limit(CMS_Beratung_Settings::text((string) ($data['public_title'] ?? $data['internal_title'] ?? 'Beratung')), 255),
            'slug' => CMS_Beratung_Settings::slug((string) ($data['slug'] ?? ''), ''),
            'meta_title' => self::limit(CMS_Beratung_Settings::text((string) ($data['meta_title'] ?? '')), 255),
            'meta_description' => self::limit(CMS_Beratung_Settings::text((string) ($data['meta_description'] ?? '')), 500),
            'focus_keyword' => self::limit(CMS_Beratung_Settings::text((string) ($data['focus_keyword'] ?? '')), 160),
            'status' => array_key_exists((string) ($data['status'] ?? ''), $statuses) ? (string) $data['status'] : 'draft',
            'template' => array_key_exists((string) ($data['template'] ?? ''), $templates) ? (string) $data['template'] : 'standard',
            'max_content_width' => max(720, min(1160, (int) ($data['max_content_width'] ?? 1160))),
            'custom_design_enabled' => !empty($data['custom_design_enabled']) ? 1 : 0,
            'use_global_settings' => array_key_exists('use_global_settings', $data) ? (!empty($data['use_global_settings']) ? 1 : 0) : 1,
            'use_global_design' => array_key_exists('useGlobalDesign', $data) ? (!empty($data['useGlobalDesign']) ? 1 : 0) : (array_key_exists('use_global_design', $data) ? (!empty($data['use_global_design']) ? 1 : 0) : 1),
            'useGlobalDesign' => array_key_exists('useGlobalDesign', $data) ? !empty($data['useGlobalDesign']) : (array_key_exists('use_global_design', $data) ? !empty($data['use_global_design']) : true),
            'show_header' => array_key_exists('show_header', $data) ? (!empty($data['show_header']) ? 1 : 0) : 1,
            'show_footer' => array_key_exists('show_footer', $data) ? (!empty($data['show_footer']) ? 1 : 0) : 1,
            'show_breadcrumb' => array_key_exists('show_breadcrumb', $data) ? (!empty($data['show_breadcrumb']) ? 1 : 0) : 1,
            'show_toc' => array_key_exists('show_toc', $data) ? (!empty($data['show_toc']) ? 1 : 0) : 1,
            'show_anchor_nav' => array_key_exists('show_anchor_nav', $data) ? (!empty($data['show_anchor_nav']) ? 1 : 0) : 1,
            'noindex' => !empty($data['noindex']) ? 1 : 0,
            'nofollow' => !empty($data['nofollow']) ? 1 : 0,
            'canonical_url' => CMS_Beratung_Settings::public_url((string) ($data['canonical_url'] ?? '')),
            'custom_css_class' => self::css_class((string) ($data['custom_css_class'] ?? '')),
            'hero_json' => json_encode($hero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'contact_json' => json_encode($contact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'seo_json' => json_encode($seo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'design_json' => json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sections_json' => json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tracking_enabled' => array_key_exists('tracking_enabled', $data) ? (!empty($data['tracking_enabled']) ? 1 : 0) : 1,
            'hero' => $hero,
            'contact' => $contact,
            'seo' => $seo,
            'sections' => $sections,
            'design' => $design,
            'created_by' => !empty($data['created_by']) ? (int) $data['created_by'] : null,
            'updated_by' => !empty($data['updated_by']) ? (int) $data['updated_by'] : null,
        ];

        return $payload;
    }

    /** @param mixed $raw @return array<string,mixed> */
    public static function sanitize_hero(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $button = static function (int $number) use ($raw): array {
            $data = is_array($raw['button_' . $number] ?? null) ? $raw['button_' . $number] : [];
            $style = (string) ($data['style'] ?? $raw['button_' . $number . '_style'] ?? 'primary');
            $targetType = (string) ($data['target_type'] ?? $raw['button_' . $number . '_target_type'] ?? 'internal');
            return [
                'text' => self::limit(CMS_Beratung_Settings::text((string) ($data['text'] ?? $raw['button_' . $number . '_text'] ?? '')), 80),
                'target' => self::button_target((string) ($data['target'] ?? $raw['button_' . $number . '_target'] ?? ''), $targetType),
                'target_type' => self::choice($targetType, ['internal', 'external', 'anchor', 'contact', 'email', 'phone', 'bookings', 'download'], 'internal'),
                'style' => self::choice($style, ['primary', 'secondary', 'ghost', 'link'], 'primary'),
            ];
        };

        $defaultTrustBadges = ['Ex-Microsoft MVP', '20+ Jahre', 'LPIC 1 & 2', 'Microsoft zertifiziert'];
        $trustBadgeRawValues = [];
        if (array_key_exists('trust_badges', $raw) && is_array($raw['trust_badges'])) {
            $trustBadgeRawValues = $raw['trust_badges'];
        } elseif (array_key_exists('trust_badge_1', $raw) || array_key_exists('trust_badge_2', $raw) || array_key_exists('trust_badge_3', $raw) || array_key_exists('trust_badge_4', $raw)) {
            $trustBadgeRawValues = [$raw['trust_badge_1'] ?? '', $raw['trust_badge_2'] ?? '', $raw['trust_badge_3'] ?? '', $raw['trust_badge_4'] ?? ''];
        } else {
            $trustBadgeRawValues = $defaultTrustBadges;
        }
        $trustBadges = [];
        foreach (array_slice($trustBadgeRawValues, 0, 4) as $badgeText) {
            $badgeText = self::limit(CMS_Beratung_Settings::text((string) $badgeText), 80);
            if (trim($badgeText) !== '') {
                $trustBadges[] = $badgeText;
            }
        }

        $standaloneMenuItems = [];
        $rawStandaloneMenuItems = is_array($raw['standalone_header_menu_items'] ?? null) ? $raw['standalone_header_menu_items'] : [];
        for ($i = 1; $i <= 4; $i++) {
            $rawItem = is_array($rawStandaloneMenuItems[$i - 1] ?? null) ? $rawStandaloneMenuItems[$i - 1] : [];
            $label = self::limit(CMS_Beratung_Settings::text((string) ($raw['standalone_header_menu_label_' . $i] ?? $rawItem['label'] ?? '')), 80);
            $target = self::menu_target((string) ($raw['standalone_header_menu_target_' . $i] ?? $rawItem['target'] ?? ''));
            if ($label !== '' && $target !== '') {
                $standaloneMenuItems[] = ['label' => $label, 'target' => $target];
            }
        }

        return [
            'enabled' => array_key_exists('enabled', $raw) ? !empty($raw['enabled']) : true,
            'standalone_header_enabled' => !empty($raw['standalone_header_enabled']),
            'standalone_header_slug' => CMS_Beratung_Settings::slug((string) ($raw['standalone_header_slug'] ?? ''), ''),
            'standalone_header_blog_logo_url' => CMS_Beratung_Settings::image_url((string) ($raw['standalone_header_blog_logo_url'] ?? '')),
            'standalone_header_partner_logo_url' => CMS_Beratung_Settings::image_url((string) ($raw['standalone_header_partner_logo_url'] ?? '')),
            'standalone_header_title' => self::limit(CMS_Beratung_Settings::text((string) ($raw['standalone_header_title'] ?? '')), 180),
            'standalone_header_subtitle' => self::limit(CMS_Beratung_Settings::text((string) ($raw['standalone_header_subtitle'] ?? '')), 260),
            'standalone_header_menu_enabled' => array_key_exists('standalone_header_menu_enabled', $raw) ? !empty($raw['standalone_header_menu_enabled']) : true,
            'standalone_header_menu_items' => $standaloneMenuItems,
            'image_url' => CMS_Beratung_Settings::image_url((string) ($raw['image_url'] ?? '')),
            'image_position' => self::choice((string) ($raw['image_position'] ?? 'left'), ['left', 'right'], 'left'),
            'image_flush' => array_key_exists('image_flush', $raw) ? !empty($raw['image_flush']) : true,
            'image_height' => max(180, min(900, (int) ($raw['image_height'] ?? 520))),
            'image_width' => max(25, min(70, (int) ($raw['image_width'] ?? 46))),
            'image_fit' => self::choice((string) ($raw['image_fit'] ?? 'cover'), ['cover', 'contain'], 'cover'),
            'image_alt' => self::limit(CMS_Beratung_Settings::text((string) ($raw['image_alt'] ?? '')), 180),
            'badge_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['badge_text'] ?? '')), 120),
            'badge_show' => array_key_exists('badge_show', $raw) ? !empty($raw['badge_show']) : true,
            'title' => self::limit(CMS_Beratung_Settings::text((string) ($raw['title'] ?? '')), 255),
            'subtitle' => self::limit(CMS_Beratung_Settings::text((string) ($raw['subtitle'] ?? '')), 255),
            'description' => self::limit(CMS_Beratung_Settings::text((string) ($raw['description'] ?? '')), 1200),
            'button_1' => $button(1),
            'button_2' => $button(2),
            'button_3' => $button(3),
            'trust_badges' => $trustBadges,
            'trust_image_enabled' => array_key_exists('trust_image_enabled', $raw) ? !empty($raw['trust_image_enabled']) : true,
            'trust_image_url' => CMS_Beratung_Settings::image_url((string) ($raw['trust_image_url'] ?? '')),
            'trust_image_alt' => self::limit(CMS_Beratung_Settings::text((string) ($raw['trust_image_alt'] ?? 'Portrait eines Microsoft 365 Beraters')), 180),
            'partner_band_enabled' => !empty($raw['partner_band_enabled']),
            'partner_band_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['partner_band_text'] ?? 'Zugehörig zum copilotberater.de Netzwerk')), 180),
            'partner_band_website_label' => self::limit(CMS_Beratung_Settings::text((string) ($raw['partner_band_website_label'] ?? 'copilotberater.de')), 80),
            'partner_band_website_url' => CMS_Beratung_Settings::public_url((string) ($raw['partner_band_website_url'] ?? 'https://copilotberater.de')),
            'partner_band_map_label' => self::limit(CMS_Beratung_Settings::text((string) ($raw['partner_band_map_label'] ?? 'Copilotberater Deutschland Karte')), 120),
            'partner_band_map_url' => CMS_Beratung_Settings::public_url((string) ($raw['partner_band_map_url'] ?? 'https://copilotberater.de/copilotberater-deutschland-karte/')),
            'anchor_nav_layout' => self::choice((string) ($raw['anchor_nav_layout'] ?? 'pills'), ['pills', 'cards', 'goldbar', 'minimal', 'threegrid'], 'pills'),
            'toc_right_display' => self::choice((string) ($raw['toc_right_display'] ?? 'card'), ['off', 'card'], 'card'),
            'toc_right_layout' => self::choice((string) ($raw['toc_right_layout'] ?? 'card'), ['card', 'compact', 'outline'], 'card'),
            'trust_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['trust_text'] ?? '')), 255),
            'background_color' => CMS_Beratung_Settings::color((string) ($raw['background_color'] ?? '#f8fafc'), '#f8fafc'),
            'text_color' => CMS_Beratung_Settings::color((string) ($raw['text_color'] ?? '#111827'), '#111827'),
            'vertical_align' => self::choice((string) ($raw['vertical_align'] ?? 'center'), ['start', 'center', 'end'], 'center'),
            'mobile_order' => self::choice((string) ($raw['mobile_order'] ?? 'image-first'), ['image-first', 'text-first'], 'image-first'),
        ];
    }

    /** @param mixed $raw @return array<string,mixed> */
    public static function sanitize_contact(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $targetType = (string) ($raw['button_target_type'] ?? 'internal');
        $target2Type = (string) ($raw['button_2_target_type'] ?? 'internal');
        return [
            'enabled' => array_key_exists('contact_enabled', $raw) ? !empty($raw['contact_enabled']) : (array_key_exists('enabled', $raw) ? !empty($raw['enabled']) : true),
            'mode' => self::choice((string) ($raw['contact_mode'] ?? $raw['mode'] ?? 'form'), ['form', 'button'], 'form'),
            'layout' => self::choice((string) ($raw['contact_layout'] ?? $raw['layout'] ?? 'split-form'), ['split-form', 'form-left', 'centered-card', 'compact-band', 'image-left-flush'], 'split-form'),
            'eyebrow' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_eyebrow'] ?? $raw['eyebrow'] ?? 'Kontakt')), 120),
            'title' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_title'] ?? $raw['title'] ?? 'Beratungsanfrage senden')), 255),
            'description' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_description'] ?? $raw['description'] ?? 'Beschreiben Sie kurz Ihr Anliegen.')), 1200),
            'image_url' => CMS_Beratung_Settings::image_url((string) ($raw['contact_image_url'] ?? $raw['image_url'] ?? '')),
            'image_width' => max(25, min(60, (int) ($raw['contact_image_width'] ?? $raw['image_width'] ?? 38))),
            'background_color' => CMS_Beratung_Settings::color((string) ($raw['contact_background_color'] ?? $raw['background_color'] ?? '#ffffff'), '#ffffff'),
            'text_color' => CMS_Beratung_Settings::color((string) ($raw['contact_text_color'] ?? $raw['text_color'] ?? '#111827'), '#111827'),
            'button_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_button_text'] ?? $raw['button_text'] ?? 'Kontakt aufnehmen')), 90),
            'button_target' => self::button_target((string) ($raw['contact_button_target'] ?? $raw['button_target'] ?? '#kontakt'), $targetType),
            'button_target_type' => self::choice($targetType, ['internal', 'external', 'anchor', 'contact', 'email', 'phone', 'bookings', 'download'], 'internal'),
            'button_style' => self::choice((string) ($raw['contact_button_style'] ?? $raw['button_style'] ?? 'primary'), ['primary', 'secondary', 'ghost', 'link'], 'primary'),
            'button_2_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_button_2_text'] ?? $raw['button_2_text'] ?? '')), 90),
            'button_2_target' => self::button_target((string) ($raw['contact_button_2_target'] ?? $raw['button_2_target'] ?? ''), $target2Type),
            'button_2_target_type' => self::choice($target2Type, ['internal', 'external', 'anchor', 'contact', 'email', 'phone', 'bookings', 'download'], 'internal'),
            'button_2_style' => self::choice((string) ($raw['contact_button_2_style'] ?? $raw['button_2_style'] ?? 'ghost'), ['primary', 'secondary', 'ghost', 'link'], 'ghost'),
            'note_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_note_text'] ?? $raw['note_text'] ?? 'Hinweis: Ich melde mich in der Regel innerhalb von 1 bis 2 Werktagen mit einer ersten Einschätzung zurück.')), 500),
            'anchor_id' => CMS_Beratung_Settings::slug((string) ($raw['contact_anchor_id'] ?? $raw['anchor_id'] ?? 'kontakt'), 'kontakt'),
            'privacy_text' => self::limit(CMS_Beratung_Settings::text((string) ($raw['contact_privacy_text'] ?? $raw['privacy_text'] ?? 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Anfrage zu.')), 1200),
            'captcha_enabled' => !empty($raw['contact_captcha_enabled'] ?? $raw['captcha_enabled'] ?? false),
        ];
    }

    /** @param mixed $raw @return array<string,mixed> */
    public static function sanitize_seo(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        return [
            'og_title' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_og_title'] ?? $raw['og_title'] ?? '')), 255),
            'og_description' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_og_description'] ?? $raw['og_description'] ?? '')), 500),
            'og_image' => CMS_Beratung_Settings::image_url((string) ($raw['seo_og_image'] ?? $raw['og_image'] ?? '')),
            'twitter_title' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_twitter_title'] ?? $raw['twitter_title'] ?? '')), 255),
            'twitter_description' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_twitter_description'] ?? $raw['twitter_description'] ?? '')), 500),
            'twitter_image' => CMS_Beratung_Settings::image_url((string) ($raw['seo_twitter_image'] ?? $raw['twitter_image'] ?? '')),
            'faq_schema_enabled' => array_key_exists('seo_faq_schema_enabled_page', $raw) ? !empty($raw['seo_faq_schema_enabled_page']) : (array_key_exists('faq_schema_enabled', $raw) ? !empty($raw['faq_schema_enabled']) : true),
            'breadcrumb_schema_enabled' => array_key_exists('seo_breadcrumb_schema_enabled_page', $raw) ? !empty($raw['seo_breadcrumb_schema_enabled_page']) : (array_key_exists('breadcrumb_schema_enabled', $raw) ? !empty($raw['breadcrumb_schema_enabled']) : true),
            'organization_schema_enabled' => !empty($raw['seo_organization_schema_enabled'] ?? $raw['organization_schema_enabled'] ?? false),
            'local_business_schema_enabled' => !empty($raw['seo_local_business_schema_enabled'] ?? $raw['local_business_schema_enabled'] ?? false),
            'service_name' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_service_name'] ?? $raw['service_name'] ?? '')), 255),
            'service_description' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_service_description'] ?? $raw['service_description'] ?? '')), 500),
            'provider_name' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_provider_name'] ?? $raw['provider_name'] ?? '365CMS')), 255),
            'provider_url' => CMS_Beratung_Settings::public_url((string) ($raw['seo_provider_url'] ?? $raw['provider_url'] ?? '')),
            'area_served' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_area_served'] ?? $raw['area_served'] ?? 'DE')), 120),
            'audience' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_audience'] ?? $raw['audience'] ?? 'IT Administratoren und Unternehmen')), 160),
            'region' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_region'] ?? $raw['region'] ?? 'Deutschland')), 120),
            'category' => self::limit(CMS_Beratung_Settings::text((string) ($raw['seo_category'] ?? $raw['category'] ?? 'Microsoft 365 Consulting')), 160),
        ];
    }

    /** @param mixed $raw @return array<int,array<string,mixed>> */
    public static function sanitize_sections(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $sections = [];
        foreach (array_values($raw) as $index => $section) {
            if (!is_array($section)) {
                continue;
            }
            $columns = (int) ($section['columns'] ?? 3);
            $type = self::choice((string) ($section['type'] ?? 'card_grid'), ['partner_band', 'proof', 'booking', 'text', 'card_grid', 'image_cards', 'services', 'offers', 'comparison', 'faq', 'steps', 'cta', 'trust', 'technology', 'collaboration', 'contact', 'divider', 'html', 'infographic', 'cards'], 'card_grid');
            if ($type === 'faq') {
                continue;
            }
            $button1Type = (string) ($section['button_1_target_type'] ?? 'internal');
            $button2Type = (string) ($section['button_2_target_type'] ?? 'internal');
            $sections[] = [
                'id' => self::section_id((string) ($section['id'] ?? 'section-' . ($index + 1))),
                'sort_order' => max(1, min(999, (int) ($section['sort_order'] ?? ($index + 1)))),
                'enabled' => array_key_exists('enabled', $section) ? !empty($section['enabled']) : true,
                'type' => $type === 'cards' ? 'card_grid' : $type,
                'internal_name' => self::limit(CMS_Beratung_Settings::text((string) ($section['internal_name'] ?? $section['title'] ?? 'Bereich ' . ($index + 1))), 160),
                'eyebrow' => self::limit(CMS_Beratung_Settings::text((string) ($section['eyebrow'] ?? '')), 120),
                'title' => self::limit(CMS_Beratung_Settings::text((string) ($section['title'] ?? '')), 255),
                'intro' => self::limit(CMS_Beratung_Settings::text((string) ($section['intro'] ?? $section['text'] ?? '')), 1200),
                'partner_layout' => self::choice((string) ($section['partner_layout'] ?? 'network-card'), ['network-card', 'split-panel', 'centered-badge', 'compact-strip'], 'network-card'),
                'anchor_id' => CMS_Beratung_Settings::slug((string) ($section['anchor_id'] ?? $section['anchor'] ?? $section['id'] ?? 'bereich-' . ($index + 1)), 'bereich-' . ($index + 1)),
                'background_color' => CMS_Beratung_Settings::color((string) ($section['background_color'] ?? $section['background'] ?? '#ffffff'), '#ffffff'),
                'background_image_url' => CMS_Beratung_Settings::image_url((string) ($section['background_image_url'] ?? '')),
                'padding_top' => max(0, min(180, (int) ($section['padding_top'] ?? 56))),
                'padding_bottom' => max(0, min(180, (int) ($section['padding_bottom'] ?? 56))),
                'max_width' => max(720, min(1800, (int) ($section['max_width'] ?? 1200))),
                'text_align' => self::choice((string) ($section['text_align'] ?? 'left'), ['left', 'center', 'right'], 'left'),
                'card_type' => self::choice((string) ($section['card_type'] ?? 'text'), ['text', 'image_label', 'offer_icon_tab', 'step', 'problem_solution'], 'text'),
                'card_design' => self::choice((string) ($section['card_design'] ?? 'standard'), ['standard', 'compact', 'bordered', 'filled', 'minimal', 'accent'], 'standard'),
                'equal_height' => !empty($section['equal_height']),
                'text_color' => CMS_Beratung_Settings::color((string) ($section['text_color'] ?? '#111827'), '#111827'),
                'columns' => $type === 'collaboration' ? max(2, min(4, $columns)) : max(1, min(4, $columns)),
                'anchor' => CMS_Beratung_Settings::slug((string) ($section['anchor'] ?? $section['title'] ?? 'bereich-' . ($index + 1)), 'bereich-' . ($index + 1)),
                'categories_enabled' => !empty($section['categories_enabled']),
                'note_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['note_text'] ?? '')), 500),
                'expert_ids' => self::positive_ids($section['expert_ids'] ?? []),
                'mvp_note_enabled' => array_key_exists('mvp_note_enabled', $section) ? !empty($section['mvp_note_enabled']) : true,
                'mvp_note_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['mvp_note_text'] ?? 'Darunter auch Microsoft MVPs aus dem 365 Network.')), 255),
                'display_style' => self::choice((string) ($section['display_style'] ?? 'cards'), ['cards', 'horizontal', 'vertical', 'icon_grid', 'logo_strip', 'compact', 'large'], 'cards'),
                'auto_number' => array_key_exists('auto_number', $section) ? !empty($section['auto_number']) : true,
                'connector' => array_key_exists('connector', $section) ? !empty($section['connector']) : true,
                'title_band_enabled' => !empty($section['title_band_enabled']),
                'title_band_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['title_band_text'] ?? '')), 255),
                'title_band_background_color' => CMS_Beratung_Settings::color((string) ($section['title_band_background_color'] ?? '#1e3a8a'), '#1e3a8a'),
                'title_band_text_color' => CMS_Beratung_Settings::color((string) ($section['title_band_text_color'] ?? '#ffffff'), '#ffffff'),
                'comparison_variant' => self::choice((string) ($section['comparison_variant'] ?? 'two_columns'), ['two_columns', 'three_columns', 'current_target', 'problem_solution', 'genai_agentic', 'copilot_search', 'governance'], 'two_columns'),
                'border_enabled' => array_key_exists('border_enabled', $section) ? !empty($section['border_enabled']) : true,
                'shadow_enabled' => array_key_exists('shadow_enabled', $section) ? !empty($section['shadow_enabled']) : true,
                'mobile_stack' => array_key_exists('mobile_stack', $section) ? !empty($section['mobile_stack']) : true,
                'faq_allow_multiple' => !empty($section['faq_allow_multiple']),
                'faq_icon_style' => self::choice((string) ($section['faq_icon_style'] ?? 'plus'), ['plus', 'chevron', 'question'], 'plus'),
                'faq_open_behavior' => self::choice((string) ($section['faq_open_behavior'] ?? 'none'), ['none', 'first', 'custom'], 'none'),
                'faq_question_background_color' => CMS_Beratung_Settings::color((string) ($section['faq_question_background_color'] ?? '#ffffff'), '#ffffff'),
                'faq_question_text_color' => CMS_Beratung_Settings::color((string) ($section['faq_question_text_color'] ?? '#111827'), '#111827'),
                'faq_answer_background_color' => CMS_Beratung_Settings::color((string) ($section['faq_answer_background_color'] ?? '#f8fafc'), '#f8fafc'),
                'faq_schema_enabled' => array_key_exists('faq_schema_enabled', $section) ? !empty($section['faq_schema_enabled']) : true,
                'button_1_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['button_1_text'] ?? $section['cta_text'] ?? '')), 90),
                'button_1_target' => self::button_target((string) ($section['button_1_target'] ?? $section['cta_target'] ?? ''), $button1Type),
                'button_1_target_type' => self::choice($button1Type, ['internal', 'external', 'anchor', 'contact', 'email', 'phone', 'bookings', 'download'], 'internal'),
                'button_1_style' => self::choice((string) ($section['button_1_style'] ?? $section['cta_style'] ?? 'primary'), ['primary', 'secondary', 'ghost', 'link'], 'primary'),
                'button_2_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['button_2_text'] ?? '')), 90),
                'button_2_target' => self::button_target((string) ($section['button_2_target'] ?? ''), $button2Type),
                'button_2_target_type' => self::choice($button2Type, ['internal', 'external', 'anchor', 'contact', 'email', 'phone', 'bookings', 'download'], 'internal'),
                'button_2_style' => self::choice((string) ($section['button_2_style'] ?? 'ghost'), ['primary', 'secondary', 'ghost', 'link'], 'ghost'),
                'divider_type' => self::choice((string) ($section['divider_type'] ?? 'line'), ['line', 'title_band', 'icon', 'color_block', 'wave', 'quote', 'cta', 'numbered', 'spacer'], 'line'),
                'divider_title' => self::limit(CMS_Beratung_Settings::text((string) ($section['divider_title'] ?? $section['title'] ?? '')), 180),
                'divider_subtitle' => self::limit(CMS_Beratung_Settings::text((string) ($section['divider_subtitle'] ?? $section['intro'] ?? '')), 500),
                'divider_icon' => self::limit(CMS_Beratung_Settings::text((string) ($section['divider_icon'] ?? '')), 40),
                'divider_image_url' => CMS_Beratung_Settings::image_url((string) ($section['divider_image_url'] ?? '')),
                'divider_image_alt' => self::limit(CMS_Beratung_Settings::text((string) ($section['divider_image_alt'] ?? $section['divider_title'] ?? $section['title'] ?? '')), 180),
                'divider_layout' => self::choice((string) ($section['divider_layout'] ?? 'centered'), ['centered', 'image-left', 'image-right'], 'centered'),
                'divider_image_width' => max(18, min(45, (int) ($section['divider_image_width'] ?? 32))),
                'divider_line_color' => CMS_Beratung_Settings::color((string) ($section['divider_line_color'] ?? '#dbeafe'), '#dbeafe'),
                'divider_width' => max(20, min(100, (int) ($section['divider_width'] ?? 100))),
                'divider_padding_left' => max(0, min(180, (int) ($section['divider_padding_left'] ?? 20))),
                'divider_padding_right' => max(0, min(180, (int) ($section['divider_padding_right'] ?? 20))),
                'divider_mobile_behavior' => self::choice((string) ($section['divider_mobile_behavior'] ?? 'stack'), ['stack', 'compact', 'hide_visual'], 'stack'),
                'booking_url' => self::https_url((string) ($section['booking_url'] ?? $section['booking_link'] ?? $section['booking_embed_url'] ?? '')),
                'booking_display' => self::choice((string) ($section['booking_display'] ?? 'embed'), ['embed', 'link', 'button'], 'embed'),
                'booking_button_text' => self::limit(CMS_Beratung_Settings::text((string) ($section['booking_button_text'] ?? 'Termin buchen')), 90),
                'cards' => in_array($type, ['partner_band', 'booking', 'collaboration', 'cta', 'divider', 'html'], true) ? [] : self::sanitize_cards($section['cards'] ?? []),
                'html' => self::limit(self::safe_html((string) ($section['html'] ?? '')), 12000),
            ];
        }
        usort($sections, static fn(array $a, array $b): int => ((int) ($a['sort_order'] ?? 999)) <=> ((int) ($b['sort_order'] ?? 999)));
        foreach ($sections as $index => &$section) {
            $section['sort_order'] = $index + 1;
        }
        unset($section);
        return $sections;
    }

    /** @param mixed $raw @return array<int,array<string,mixed>> */
    private static function sanitize_cards(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $cards = [];
        foreach (array_values($raw) as $card) {
            if (!is_array($card)) {
                continue;
            }
            $cardType = self::choice((string) ($card['card_type'] ?? $card['type'] ?? 'text'), ['text', 'image_label', 'offer_icon_tab', 'step', 'problem_solution'], 'text');
            $cards[] = [
                'enabled' => array_key_exists('enabled', $card) ? !empty($card['enabled']) : true,
                'card_type' => $cardType,
                'card_layout' => self::choice((string) ($card['card_layout'] ?? 'classic'), ['classic', 'media-left', 'compact', 'spotlight'], 'classic'),
                'card_theme' => self::choice((string) ($card['card_theme'] ?? 'default'), ['default', 'experience', 'consulting', 'exchange', 'iamcp', 'projects', 'security', 'governance', 'microsoft', 'network'], 'default'),
                'icon' => self::limit(CMS_Beratung_Settings::text((string) ($card['icon'] ?? '')), 40),
                'icon_background' => CMS_Beratung_Settings::color((string) ($card['icon_background'] ?? '#eff6ff'), '#eff6ff'),
                'icon_color' => CMS_Beratung_Settings::color((string) ($card['icon_color'] ?? '#2563eb'), '#2563eb'),
                'icon_text_layout' => self::choice((string) ($card['icon_text_layout'] ?? 'below'), ['below', 'right'], 'below'),
                'image_url' => CMS_Beratung_Settings::image_url((string) ($card['image_url'] ?? '')),
                'image_alt' => self::limit(CMS_Beratung_Settings::text((string) ($card['image_alt'] ?? '')), 160),
                'image_height' => max(120, min(520, (int) ($card['image_height'] ?? 230))),
                'image_fit' => self::choice((string) ($card['image_fit'] ?? 'cover'), ['cover', 'contain'], 'cover'),
                'label' => self::limit(CMS_Beratung_Settings::text((string) ($card['label'] ?? '')), 120),
                'label_position' => self::choice((string) ($card['label_position'] ?? 'left'), ['left', 'center', 'right'], 'left'),
                'label_style' => self::choice((string) ($card['label_style'] ?? 'filled'), ['border', 'filled', 'transparent'], 'filled'),
                'category' => self::limit(CMS_Beratung_Settings::text((string) ($card['category'] ?? '')), 120),
                'name' => self::limit(CMS_Beratung_Settings::text((string) ($card['name'] ?? $card['title'] ?? '')), 160),
                'title' => self::limit(CMS_Beratung_Settings::text((string) ($card['title'] ?? '')), 255),
                'text' => self::limit(CMS_Beratung_Settings::text((string) ($card['text'] ?? '')), 1000),
                'extra_text' => self::limit(CMS_Beratung_Settings::text((string) ($card['extra_text'] ?? $card['typical_use'] ?? '')), 1000),
                'metric' => self::limit(CMS_Beratung_Settings::text((string) ($card['metric'] ?? '')), 80),
                'logo_url' => CMS_Beratung_Settings::image_url((string) ($card['logo_url'] ?? '')),
                'question' => self::limit(CMS_Beratung_Settings::text((string) ($card['question'] ?? $card['title'] ?? '')), 255),
                'answer' => self::limit(CMS_Beratung_Settings::text((string) ($card['answer'] ?? $card['text'] ?? '')), 1800),
                'default_open' => !empty($card['default_open']),
                'button_label' => self::limit(CMS_Beratung_Settings::text((string) ($card['button_label'] ?? '')), 80),
                'button_url' => CMS_Beratung_Settings::public_url((string) ($card['button_url'] ?? '')),
                'badge' => self::limit(CMS_Beratung_Settings::text((string) ($card['badge'] ?? '')), 80),
                'featured' => !empty($card['featured']),
                'background_color' => CMS_Beratung_Settings::color((string) ($card['background_color'] ?? '#ffffff'), '#ffffff'),
                'text_color' => CMS_Beratung_Settings::color((string) ($card['text_color'] ?? '#111827'), '#111827'),
                'border_enabled' => array_key_exists('border_enabled', $card) ? !empty($card['border_enabled']) : true,
                'border_color' => CMS_Beratung_Settings::color((string) ($card['border_color'] ?? '#dbeafe'), '#dbeafe'),
                'shadow_enabled' => array_key_exists('shadow_enabled', $card) ? !empty($card['shadow_enabled']) : true,
                'hover_enabled' => array_key_exists('hover_enabled', $card) ? !empty($card['hover_enabled']) : true,
                'tab_enabled' => !empty($card['tab_enabled']),
                'card_size' => self::choice((string) ($card['card_size'] ?? 'medium'), ['small', 'medium', 'large'], 'medium'),
                'step_number' => self::limit(CMS_Beratung_Settings::text((string) ($card['step_number'] ?? '')), 20),
                'auto_number' => !empty($card['auto_number']),
                'connector' => !empty($card['connector']),
                'problem_title' => self::limit(CMS_Beratung_Settings::text((string) ($card['problem_title'] ?? '')), 180),
                'problem_text' => self::limit(CMS_Beratung_Settings::text((string) ($card['problem_text'] ?? '')), 800),
                'solution_title' => self::limit(CMS_Beratung_Settings::text((string) ($card['solution_title'] ?? '')), 180),
                'solution_text' => self::limit(CMS_Beratung_Settings::text((string) ($card['solution_text'] ?? '')), 800),
                'risk_level' => self::choice((string) ($card['risk_level'] ?? 'medium'), ['low', 'medium', 'high'], 'medium'),
                'recommendation' => self::limit(CMS_Beratung_Settings::text((string) ($card['recommendation'] ?? '')), 500),
                'sort_order' => (int) ($card['sort_order'] ?? 0),
            ];
        }
        return $cards;
    }

    private static function choice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /** @return array<int,int> */
    private static function positive_ids(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/[\s,;]+/', $raw) ?: [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $value) {
            if (is_array($value)) {
                $value = $value['id'] ?? $value['value'] ?? 0;
            } elseif (is_object($value)) {
                $value = $value->id ?? $value->value ?? 0;
            }
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_slice(array_values($ids), 0, 24);
    }

    private static function button_target(string $value, string $type): string
    {
        $value = trim($value);
        if ($type === 'contact') {
            return '#kontakt';
        }
        if ($type === 'anchor') {
            $anchor = CMS_Beratung_Settings::slug(ltrim($value, '#'), 'kontakt');
            return '#' . $anchor;
        }
        if ($type === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : CMS_Beratung_Settings::public_url($value);
        }
        if ($type === 'phone') {
            $phone = preg_replace('/[^0-9+]/', '', $value) ?? '';
            return $phone !== '' ? 'tel:' . $phone : '';
        }
        return CMS_Beratung_Settings::public_url($value);
    }

    private static function menu_target(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '#')) {
            return '#' . CMS_Beratung_Settings::slug(ltrim($value, '#'), 'bereich');
        }
        return CMS_Beratung_Settings::public_url($value);
    }

    private static function https_url(string $value): string
    {
        $value = CMS_Beratung_Settings::public_url(trim($value));
        return preg_match('#^https://#i', $value) === 1 ? self::limit($value, 900) : '';
    }

    /** @param mixed $raw @return array<string,mixed> */
    private static function sanitize_design(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $allowed = ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'heading_color', 'button_color', 'button_text_color', 'card_background_color', 'card_border_color'];
        $design = [];
        foreach ($allowed as $key) {
            if (isset($raw[$key])) {
                $design[$key] = CMS_Beratung_Settings::color((string) $raw[$key], CMS_Beratung_Settings::defaults()[$key] ?? '#ffffff');
            }
        }
        $fontSizes = is_array($raw['fontSizes'] ?? null) ? $raw['fontSizes'] : [];
        foreach ([
            'font_size_base' => ['base', 16, 12, 24],
            'font_size_hero' => ['hero', 52, 28, 96],
            'font_size_section_title' => ['sectionTitle', 32, 20, 72],
            'font_size_card_title' => ['cardTitle', 21, 16, 40],
        ] as $key => [$camelKey, $fallback, $min, $max]) {
            $rawValue = $raw[$key] ?? $fontSizes[$camelKey] ?? null;
            if (is_numeric($rawValue)) {
                $design[$key] = max((int) $min, min((int) $max, (int) $rawValue));
            }
        }
        foreach ([
            'header_spacing' => ['headerSpacing', 48, 0, 160],
            'footer_spacing' => ['footerSpacing', 48, 0, 160],
            'card_spacing' => ['cardSpacing', 24, 0, 96],
            'section_content_spacing' => ['sectionContentSpacing', 24, 0, 160],
        ] as $key => [$camelKey, $fallback, $min, $max]) {
            $rawValue = $raw[$key] ?? $raw[$camelKey] ?? null;
            if (is_numeric($rawValue)) {
                $design[$key] = max((int) $min, min((int) $max, (int) $rawValue));
            }
        }
        return $design;
    }

    private static function css_class(string $value): string
    {
        $value = trim((string) preg_replace('/[^a-z0-9_\-\s]+/i', '', strip_tags($value)));
        return self::limit((string) preg_replace('/\s+/', ' ', $value), 120);
    }

    private static function section_id(string $value): string
    {
        return self::limit(CMS_Beratung_Settings::slug($value, 'section'), 80);
    }

    private static function safe_html(string $value): string
    {
        $value = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select)\b[^>]*>.*?</\1>#is', '', $value) ?? '';
        $value = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value) ?? '';
        $value = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $value) ?? '';
        $allowed = '<p><br><strong><b><em><i><u><span><small><mark><ul><ol><li><a><blockquote><code><pre><h2><h3><h4><h5><h6><div><section><article><figure><figcaption><img><table><thead><tbody><tr><th><td><hr>';
        return strip_tags($value, $allowed);
    }

    private static function limit(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}

<?php
/**
 * CMS Beratung – frontend renderer.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Renderer
{
    /** @param array<string,mixed> $page @param array{success:bool,message:string} $formResult */
    public static function render(array $page, array $formResult = ['success' => false, 'message' => '']): void
    {
        $settings = CMS_Beratung_Settings::all();
        $design = self::design_tokens($page, $settings);
        $sections = self::normalize_content_sections(is_array($page['sections'] ?? null) ? $page['sections'] : [], $page);
        $m365Faq = CMS_Beratung_Storage::instance()->m365_faq_config();
        $anchors = array_values(array_filter($sections, static function (array $section): bool {
            if (!empty($section['type']) && (string) $section['type'] === 'collaboration' && empty($section['expert_ids'])) {
                return false;
            }
            return !empty($section['enabled']) && trim((string) ($section['title'] ?? '')) !== '' && trim((string) ($section['anchor_id'] ?? $section['id'] ?? '')) !== '';
        }));
        if (!empty($m365Faq['enabled'])) {
            $anchors[] = ['anchor_id' => (string) ($m365Faq['anchor_id'] ?? 'faq'), 'title' => (string) ($m365Faq['title'] ?? 'FAQ')];
        }
        $csrfToken = class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken('beratung_form_' . (int) ($page['id'] ?? 0)) : '';
        $formGuardToken = class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken('form_guard') : '';

        include CMS_BERATUNG_PLUGIN_DIR . 'templates/landingpage.php';
    }

    /** @param array<int,array<string,mixed>> $sections @param array<string,mixed> $page @return array<int,array<string,mixed>> */
    private static function normalize_content_sections(array $sections, array $page): array
    {
        $sections = array_values(array_filter($sections, static fn($section): bool => is_array($section) && ($section['type'] ?? '') !== 'faq'));
        $hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
        $hasType = static function (array $items, string $type): bool {
            foreach ($items as $section) {
                if (is_array($section) && ($section['type'] ?? '') === $type) {
                    return true;
                }
            }
            return false;
        };
        $insert = static function (string $type, int $position, array $defaults) use (&$sections, $hasType): void {
            if ($hasType($sections, $type)) {
                return;
            }
            array_splice($sections, min($position, count($sections)), 0, [array_merge(['enabled' => true, 'type' => $type, 'cards' => []], $defaults)]);
        };
        $insert('partner_band', 0, [
            'enabled' => !empty($hero['partner_band_enabled']),
            'id' => 'partnerband',
            'anchor_id' => 'partnerband',
            'internal_name' => 'Partnerband',
            'eyebrow' => 'Netzwerk',
            'title' => (string) ($hero['partner_band_text'] ?? 'Zugehörig zum copilotberater.de Netzwerk'),
            'intro' => (string) ($hero['partner_band_description'] ?? $hero['partner_band_intro'] ?? 'Einordnung, Netzwerkbezug und weiterführende Links zum Partnerangebot.'),
            'button_1_text' => (string) ($hero['partner_band_website_label'] ?? 'copilotberater.de'),
            'button_1_target' => (string) ($hero['partner_band_website_url'] ?? 'https://copilotberater.de'),
            'button_1_style' => 'primary',
            'button_2_text' => (string) ($hero['partner_band_map_label'] ?? 'Copilotberater Deutschland Karte'),
            'button_2_target' => (string) ($hero['partner_band_map_url'] ?? 'https://copilotberater.de/copilotberater-deutschland-karte/'),
            'button_2_style' => 'ghost',
        ]);
        $insert('proof', 1, [
            'id' => 'belegbare-grundlagen',
            'anchor_id' => 'belegbare-grundlagen',
            'internal_name' => 'Belegbare Grundlagen',
            'eyebrow' => 'Belegbare Grundlagen',
            'title' => 'Was nach Beratung greifbar wird',
            'intro' => 'Keine erfundenen Kundenzitate. Hier stehen nur nachvollziehbare Erfahrung, klare Projektbelege und später echte freigegebene Referenzen.',
        ]);
        $insert('booking', 2, [
            'id' => 'termin-buchen',
            'anchor_id' => 'termin-buchen',
            'internal_name' => 'Terminbuchung',
            'eyebrow' => 'Termin',
            'title' => 'Direkt einen Termin buchen',
            'intro' => 'Wähle einen passenden Slot für ein erstes Gespräch zu Microsoft 365, Copilot oder Security. Danach klären wir Ziel, Ausgangslage und den nächsten sinnvollen Schritt.',
        ]);
        return $sections;
    }

    /** @param array<string,mixed> $page @param array<string,string> $settings @return array<string,string> */
    private static function design_tokens(array $page, array $settings): array
    {
        $defaultDesign = self::default_design_tokens();
        $globalDesign = self::global_design_defaults($defaultDesign);
        $useGlobalDesign = self::page_uses_global_design($page);
        $pageDesign = is_array($page['design'] ?? null) ? $page['design'] : [];
        $design = is_array($page['design'] ?? null) ? $page['design'] : [];
        if (empty($page['custom_design_enabled']) || !empty($page['use_global_settings'])) {
            $design = [];
        }

        $keys = [
            'primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'heading_color',
            'button_color', 'button_text_color', 'card_background_color', 'card_border_color',
        ];
        $tokens = [];
        foreach ($keys as $key) {
            $tokens[$key] = (string) ($design[$key] ?? $settings[$key] ?? CMS_Beratung_Settings::defaults()[$key] ?? '#ffffff');
        }
        $tokens['background_color'] = 'transparent';
        $tokens['border_radius'] = (string) max(2, min(6, (int) ($settings['border_radius'] ?? 4))) . 'px';
        $tokens['spacing'] = (string) max(12, min(25, (int) ($settings['spacing'] ?? 20))) . 'px';
        $tokens['content_width'] = (string) max(720, min(1160, (int) ($page['max_content_width'] ?? $settings['content_width'] ?? 1160))) . 'px';
        $tokens['card_shadow'] = (($settings['card_shadow_enabled'] ?? '1') === '1') ? '0 8px 24px rgba(15, 23, 42, .08)' : 'none';
        $fallbackDesign = $useGlobalDesign ? $globalDesign : $defaultDesign;
        $tokens['font_size_base'] = self::pixel_token($useGlobalDesign ? ($settings['font_size_base'] ?? null) : null, (int) ($fallbackDesign['fontSizes']['base'] ?? 16), 12, 24);
        $tokens['font_size_hero'] = self::pixel_token($useGlobalDesign ? ($settings['font_size_hero'] ?? null) : null, (int) ($fallbackDesign['fontSizes']['hero'] ?? 52), 28, 96);
        $tokens['font_size_section_title'] = self::pixel_token($useGlobalDesign ? ($settings['font_size_section_title'] ?? null) : null, (int) ($fallbackDesign['fontSizes']['sectionTitle'] ?? 32), 20, 72);
        $tokens['font_size_card_title'] = self::pixel_token($useGlobalDesign ? ($settings['font_size_card_title'] ?? null) : null, (int) ($fallbackDesign['fontSizes']['cardTitle'] ?? 21), 16, 40);
        $tokens['header_spacing'] = self::pixel_token($useGlobalDesign ? ($settings['header_spacing'] ?? null) : null, (int) ($fallbackDesign['headerSpacing'] ?? 48), 0, 160);
        $tokens['footer_spacing'] = self::pixel_token($useGlobalDesign ? ($settings['footer_spacing'] ?? null) : null, (int) ($fallbackDesign['footerSpacing'] ?? 48), 0, 160);
        $tokens['card_spacing'] = self::pixel_token($useGlobalDesign ? ($settings['card_spacing'] ?? null) : null, (int) ($fallbackDesign['cardSpacing'] ?? 24), 0, 96);
        $tokens['section_content_spacing'] = self::pixel_token($useGlobalDesign ? ($settings['section_content_spacing'] ?? null) : null, (int) ($fallbackDesign['sectionContentSpacing'] ?? 24), 0, 160);
        if (!$useGlobalDesign) {
            $tokens['font_size_base'] = self::pixel_token(self::design_number($pageDesign, 'font_size_base', ['fontSizes', 'base']), (int) ($fallbackDesign['fontSizes']['base'] ?? 16), 12, 24);
            $tokens['font_size_hero'] = self::pixel_token(self::design_number($pageDesign, 'font_size_hero', ['fontSizes', 'hero']), (int) ($fallbackDesign['fontSizes']['hero'] ?? 52), 28, 96);
            $tokens['font_size_section_title'] = self::pixel_token(self::design_number($pageDesign, 'font_size_section_title', ['fontSizes', 'sectionTitle']), (int) ($fallbackDesign['fontSizes']['sectionTitle'] ?? 32), 20, 72);
            $tokens['font_size_card_title'] = self::pixel_token(self::design_number($pageDesign, 'font_size_card_title', ['fontSizes', 'cardTitle']), (int) ($fallbackDesign['fontSizes']['cardTitle'] ?? 21), 16, 40);
            $tokens['header_spacing'] = self::pixel_token(self::design_number($pageDesign, 'header_spacing', ['headerSpacing']), (int) ($fallbackDesign['headerSpacing'] ?? 48), 0, 160);
            $tokens['footer_spacing'] = self::pixel_token(self::design_number($pageDesign, 'footer_spacing', ['footerSpacing']), (int) ($fallbackDesign['footerSpacing'] ?? 48), 0, 160);
            $tokens['card_spacing'] = self::pixel_token(self::design_number($pageDesign, 'card_spacing', ['cardSpacing']), (int) ($fallbackDesign['cardSpacing'] ?? 24), 0, 96);
            $tokens['section_content_spacing'] = self::pixel_token(self::design_number($pageDesign, 'section_content_spacing', ['sectionContentSpacing']), (int) ($fallbackDesign['sectionContentSpacing'] ?? 24), 0, 160);
        }
        $tokens['use_global_design'] = $useGlobalDesign ? '1' : '0';

        return $tokens;
    }

    /** @return array{fontSizes:array<string,int>,headerSpacing:int,footerSpacing:int,cardSpacing:int,sectionContentSpacing:int} */
    private static function default_design_tokens(): array
    {
        return [
            'fontSizes' => ['base' => 16, 'hero' => 52, 'sectionTitle' => 32, 'cardTitle' => 21],
            'headerSpacing' => 48,
            'footerSpacing' => 48,
            'cardSpacing' => 24,
            'sectionContentSpacing' => 24,
        ];
    }

    /** @param array{fontSizes:array<string,int>,headerSpacing:int,footerSpacing:int,cardSpacing:int,sectionContentSpacing:int} $defaults @return array{fontSizes:array<string,int>,headerSpacing:int,footerSpacing:int,cardSpacing:int,sectionContentSpacing:int} */
    private static function global_design_defaults(array $defaults): array
    {
        $path = CMS_BERATUNG_PLUGIN_DIR . 'config/global-design.json';
        if (!is_file($path)) {
            return $defaults;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        $fontSizes = is_array($decoded['fontSizes'] ?? null) ? $decoded['fontSizes'] : [];
        return [
            'fontSizes' => [
                'base' => max(12, min(24, (int) ($fontSizes['base'] ?? $defaults['fontSizes']['base']))),
                'hero' => max(28, min(96, (int) ($fontSizes['hero'] ?? $defaults['fontSizes']['hero']))),
                'sectionTitle' => max(20, min(72, (int) ($fontSizes['sectionTitle'] ?? $defaults['fontSizes']['sectionTitle']))),
                'cardTitle' => max(16, min(40, (int) ($fontSizes['cardTitle'] ?? $defaults['fontSizes']['cardTitle']))),
            ],
            'headerSpacing' => max(0, min(160, (int) ($decoded['headerSpacing'] ?? $defaults['headerSpacing']))),
            'footerSpacing' => max(0, min(160, (int) ($decoded['footerSpacing'] ?? $defaults['footerSpacing']))),
            'cardSpacing' => max(0, min(96, (int) ($decoded['cardSpacing'] ?? $defaults['cardSpacing']))),
            'sectionContentSpacing' => max(0, min(160, (int) ($decoded['sectionContentSpacing'] ?? $defaults['sectionContentSpacing']))),
        ];
    }

    /** @param array<string,mixed> $page */
    private static function page_uses_global_design(array $page): bool
    {
        if (array_key_exists('useGlobalDesign', $page)) {
            return !empty($page['useGlobalDesign']);
        }
        if (array_key_exists('use_global_design', $page)) {
            return !empty($page['use_global_design']);
        }
        return true;
    }

    private static function pixel_token(mixed $value, int $fallback, int $min, int $max): string
    {
        $number = is_numeric($value) ? (int) $value : $fallback;
        return (string) max($min, min($max, $number)) . 'px';
    }

    /** @param array<string,mixed> $design @param array<int,string> $path */
    private static function design_number(array $design, string $flatKey, array $path): mixed
    {
        if (array_key_exists($flatKey, $design)) {
            return $design[$flatKey];
        }
        $value = $design;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function nl(string $value): string
    {
        return nl2br(self::esc($value));
    }

    /** @param array<string,mixed> $page */
    public static function standalone_header_enabled(array $page): bool
    {
        return self::standalone_header_state($page)['enabled'];
    }

    /** @param array<string,mixed> $page */
    public static function render_standalone_header(array $page): void
    {
        $state = self::standalone_header_state($page);
        if (!$state['enabled']) {
            return;
        }

        $settings = CMS_Beratung_Settings::all();
        $contentWidth = (string) max(720, min(1160, (int) ($page['max_content_width'] ?? $settings['content_width'] ?? 1160))) . 'px';
        echo '<header class="cms-beratung-standalone-header" aria-label="Publicsite Header" style="--beratung-width:' . self::esc($contentWidth) . ';">';
        if ($state['has_main_content']) {
            echo '<div class="cms-beratung-standalone-header__inner">';
            if ($state['blog_logo_url'] !== '' || $state['partner_logo_url'] !== '') {
                echo '<div class="cms-beratung-standalone-header__logos" aria-label="Logos">';
                if ($state['blog_logo_url'] !== '') {
                    echo '<img src="' . self::esc($state['blog_logo_url']) . '" alt="Blog Logo" loading="eager" decoding="async">';
                }
                if ($state['blog_logo_url'] !== '' && $state['partner_logo_url'] !== '') {
                    echo '<span class="cms-beratung-standalone-header__logo-separator" aria-hidden="true">×</span>';
                }
                if ($state['partner_logo_url'] !== '') {
                    echo '<img src="' . self::esc($state['partner_logo_url']) . '" alt="Partner Logo" loading="eager" decoding="async">';
                }
                echo '</div>';
            }
            echo '<div class="cms-beratung-standalone-header__title">';
            if ($state['title'] !== '') {
                echo '<strong>' . self::esc($state['title']) . '</strong>';
            }
            if ($state['subtitle'] !== '') {
                echo '<span>' . self::esc($state['subtitle']) . '</span>';
            }
            echo '</div></div>';
        }
        if ($state['menu_enabled'] && $state['menu_items'] !== []) {
            echo '<nav class="cms-beratung-standalone-header__menu" aria-label="Publicsite Menüband">';
            foreach ($state['menu_items'] as $menuItem) {
                echo '<a href="' . self::esc((string) $menuItem['target']) . '">' . self::esc((string) $menuItem['label']) . '</a>';
            }
            echo '</nav>';
        }
        echo '</header>';
    }

    /** @param array<string,mixed> $page @return array{enabled:bool,has_main_content:bool,blog_logo_url:string,partner_logo_url:string,title:string,subtitle:string,menu_enabled:bool,menu_items:array<int,array<string,string>>} */
    private static function standalone_header_state(array $page): array
    {
        $hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
        $blogLogoUrl = trim((string) ($hero['standalone_header_blog_logo_url'] ?? ''));
        $partnerLogoUrl = trim((string) ($hero['standalone_header_partner_logo_url'] ?? ''));
        $title = trim((string) ($hero['standalone_header_title'] ?? ''));
        $subtitle = trim((string) ($hero['standalone_header_subtitle'] ?? ''));
        $menuItems = array_values(array_filter(is_array($hero['standalone_header_menu_items'] ?? null) ? $hero['standalone_header_menu_items'] : [], static fn(mixed $item): bool => is_array($item) && trim((string) ($item['label'] ?? '')) !== '' && trim((string) ($item['target'] ?? '')) !== ''));
        $menuItems = array_map(static fn(array $item): array => [
            'label' => trim((string) ($item['label'] ?? '')),
            'target' => trim((string) ($item['target'] ?? '')),
        ], $menuItems);
        $menuEnabled = !empty($hero['standalone_header_menu_enabled']);
        $hasMainContent = $blogLogoUrl !== '' || $partnerLogoUrl !== '' || $title !== '' || $subtitle !== '';
        $hasContent = $hasMainContent || ($menuEnabled && $menuItems !== []);
        $enabled = (!empty($page['is_standalone_variant']) || empty($page['show_header'])) && !empty($hero['standalone_header_enabled']) && $hasContent;

        return [
            'enabled' => $enabled,
            'has_main_content' => $hasMainContent,
            'blog_logo_url' => $blogLogoUrl,
            'partner_logo_url' => $partnerLogoUrl,
            'title' => $title,
            'subtitle' => $subtitle,
            'menu_enabled' => $menuEnabled,
            'menu_items' => $menuItems,
        ];
    }

    public static function safe_html(string $value): string
    {
        $value = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select)\\b[^>]*>.*?</\\1>#is', '', $value) ?? '';
        $value = preg_replace('/\\s+on[a-z]+\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/i', '', $value) ?? '';
        $value = preg_replace('/(href|src)\\s*=\\s*("|\')\\s*javascript:[^"\']*\\2/i', '$1="#"', $value) ?? '';
        $allowed = '<p><br><strong><b><em><i><u><span><small><mark><ul><ol><li><a><blockquote><code><pre><h2><h3><h4><h5><h6><div><section><article><figure><figcaption><img><table><thead><tbody><tr><th><td><hr>';
        return strip_tags($value, $allowed);
    }
}

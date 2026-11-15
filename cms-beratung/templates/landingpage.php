<?php
/**
 * CMS Beratung frontend template.
 *
 * @var array<string,mixed> $page
 * @var array<string,string> $settings
 * @var array<string,string> $design
 * @var array<int,array<string,mixed>> $sections
 * @var array<int,array<string,mixed>> $anchors
 * @var array<string,mixed> $m365Faq
 * @var array{success:bool,message:string} $formResult
 * @var string $csrfToken
 * @var string $formGuardToken
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$renderer = CMS_Beratung_Renderer::class;
$customClass = trim((string) ($page['custom_css_class'] ?? ''));
$hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
$contact = is_array($page['contact'] ?? null) ? $page['contact'] : [];
$formErrors = is_array($formResult['errors'] ?? null) ? $formResult['errors'] : [];
$formValues = is_array($formResult['values'] ?? null) ? $formResult['values'] : [];
$primaryCtaUsed = false;
$heroPortraitPath = CMS_BERATUNG_PLUGIN_DIR . 'assets/img/consultant-portrait.svg';
$heroPortraitUrl = CMS_BERATUNG_PLUGIN_URL . 'assets/img/consultant-portrait.svg';
$heroTrustImageEnabled = array_key_exists('trust_image_enabled', $hero) ? !empty($hero['trust_image_enabled']) : true;
$heroTrustImageUrl = trim((string) ($hero['trust_image_url'] ?? ''));
$heroTrustImageAlt = trim((string) ($hero['trust_image_alt'] ?? '')) ?: 'Portrait eines Microsoft 365 Beraters';

// Optionaler globaler Fallback. Primär wird die URL aus der Terminbuchung-Section genutzt.
$bookingUrl = trim((string) ($page['booking_url'] ?? ''));

// Nur ECHTE, belegbare Angaben eintragen. Keine erfundenen Kundenzitate (UWG).
// Projektzahlen wie [X] und [Y] erst durch belegbare echte Werte ersetzen, wenn sie freigegeben sind.
$proof = [
    [
        'type' => 'credential',
        'label' => 'Erfahrung',
        'title' => '20+ Jahre Microsoft-Infrastruktur',
        'text' => 'Senior IT-Admin mit Schwerpunkt Microsoft 365, Azure, Exchange, PowerShell, IT-Security und Datenschutz/Compliance.',
        'meta' => 'Fokus: stabile, nachvollziehbare und sichere Betriebsmodelle.',
    ],
    [
        'type' => 'credential',
        'label' => 'Prüfung',
        'title' => 'IHK-Prüfer',
        'text' => 'Prüfungsperspektive aus Ausbildung und Praxis. Das hilft bei klaren Standards, verständlicher Übergabe und sauberer Dokumentation.',
        'meta' => 'Technik wird so erklärt, dass Admins und Entscheider damit arbeiten können.',
    ],
    [
        'type' => 'credential',
        'label' => 'Zertifizierung',
        'title' => 'Mehrfach zertifiziert',
        'text' => 'LPIC 1 & 2 sowie Microsoft-Zertifizierungen ergänzen die praktische Erfahrung aus Microsoft-Infrastruktur, Security und Automatisierung.',
        'meta' => 'Breite Basis für hybride Umgebungen und Microsoft-Cloud-Betrieb.',
    ],
    [
        'type' => 'outcome',
        'label' => 'Projektbeleg',
        'title' => 'Exchange-Migration zu Exchange Online',
        'text' => 'Echte Projektzahl eintragen: [X] Postfächer. Nur verwenden, wenn die Zahl belegbar und zur Veröffentlichung freigegeben ist.',
        'meta' => 'Editierbares Feld: [X] Postfächer.',
    ],
    [
        'type' => 'outcome',
        'label' => 'Projektbeleg',
        'title' => 'Copilot-Readiness für Mittelstand',
        'text' => 'Echte Projektgröße eintragen: [Y] MA. Geeignet für freigegebene, anonymisierte Projektergebnisse ohne Kundenzitat.',
        'meta' => 'Editierbares Feld: [Y] MA.',
    ],
    [
        'type' => 'network',
        'label' => 'Netzwerk',
        'title' => 'copilotberater.de Netzwerk',
        'text' => 'Beratung kann im passenden Netzwerk-Kontext eingeordnet werden, wenn Copilot, Governance, Suche und Enterprise AI zusammen gedacht werden müssen.',
        'meta' => 'Trust-Signal: fachlicher Austausch statt isolierter Einzelmeinung.',
    ],
];

// Hier echte LinkedIn-Empfehlungen / Kundenzitate eintragen. Leer lassen, bis echte Referenzen vorliegen.
$testimonials = [
    // Beispiel-Schema für echte Referenzen:
    // ['quote' => '', 'name' => '', 'role' => '', 'company' => ''],
];

$hasText = static function (mixed $value): bool {
    return trim((string) $value) !== '';
};

$defaultHeroTrustBadges = ['Ex-Microsoft MVP', '20+ Jahre', 'LPIC 1 & 2', 'Microsoft zertifiziert'];
$heroTrustBadgeValues = [];
if (array_key_exists('trust_badges', $hero) && is_array($hero['trust_badges'])) {
    $heroTrustBadgeValues = $hero['trust_badges'];
} elseif (array_key_exists('trust_badge_1', $hero) || array_key_exists('trust_badge_2', $hero) || array_key_exists('trust_badge_3', $hero) || array_key_exists('trust_badge_4', $hero)) {
    $heroTrustBadgeValues = [$hero['trust_badge_1'] ?? '', $hero['trust_badge_2'] ?? '', $hero['trust_badge_3'] ?? '', $hero['trust_badge_4'] ?? ''];
} else {
    $heroTrustBadgeValues = $defaultHeroTrustBadges;
}
$heroTrustBadges = array_values(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), array_slice($heroTrustBadgeValues, 0, 4)), static fn(string $value): bool => $value !== ''));
$partnerBandEnabled = !empty($hero['partner_band_enabled']);
$partnerBandText = trim((string) ($hero['partner_band_text'] ?? ''));
$partnerBandWebsiteLabel = trim((string) ($hero['partner_band_website_label'] ?? ''));
$partnerBandWebsiteUrl = trim((string) ($hero['partner_band_website_url'] ?? ''));
$partnerBandMapLabel = trim((string) ($hero['partner_band_map_label'] ?? ''));
$partnerBandMapUrl = trim((string) ($hero['partner_band_map_url'] ?? ''));
$partnerBandHasContent = $partnerBandEnabled && ($partnerBandText !== '' || ($partnerBandWebsiteLabel !== '' && $partnerBandWebsiteUrl !== '') || ($partnerBandMapLabel !== '' && $partnerBandMapUrl !== ''));
$anchorNavLayout = in_array((string) ($hero['anchor_nav_layout'] ?? 'pills'), ['pills', 'cards', 'goldbar', 'minimal', 'threegrid'], true) ? (string) ($hero['anchor_nav_layout'] ?? 'pills') : 'pills';
$tocRightDisplay = in_array((string) ($hero['toc_right_display'] ?? 'card'), ['off', 'card'], true) ? (string) ($hero['toc_right_display'] ?? 'card') : 'card';
$tocRightLayout = in_array((string) ($hero['toc_right_layout'] ?? 'card'), ['card', 'compact', 'outline'], true) ? (string) ($hero['toc_right_layout'] ?? 'card') : 'card';
$standaloneHeaderEnabled = $renderer::standalone_header_enabled($page);

$buttonClass = static function (string $style): string {
    return 'cms-beratung__btn cms-beratung__btn--' . preg_replace('/[^a-z0-9_-]/i', '', $style ?: 'primary');
};

$renderLinkButton = static function (string $label, string $url, string $style = 'primary') use ($renderer, $buttonClass, &$primaryCtaUsed): void {
    $label = trim($label);
    $url = trim($url);
    if ($label === '' || $url === '') {
        return;
    }
    $isMainCta = strcasecmp($label, 'Beratung anfragen') === 0 && !$primaryCtaUsed;
    $style = $isMainCta ? 'primary' : 'ghost';
    if ($isMainCta) {
        $primaryCtaUsed = true;
    }
    $external = preg_match('#^https?://#i', $url) === 1;
    echo '<a class="' . $renderer::esc($buttonClass($style)) . '" href="' . $renderer::esc($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $renderer::esc($label) . '</a>';
};

$renderButton = static function (array $button) use ($renderLinkButton): void {
    $renderLinkButton((string) ($button['text'] ?? ''), (string) ($button['target'] ?? ''), (string) ($button['style'] ?? 'primary'));
};

$renderSectionActions = static function (array $section) use ($renderLinkButton): void {
    if (empty($section['button_1_text']) && empty($section['button_2_text'])) {
        return;
    }
    echo '<div class="cms-beratung-module__actions">';
    $renderLinkButton((string) ($section['button_1_text'] ?? ''), (string) ($section['button_1_target'] ?? ''), (string) ($section['button_1_style'] ?? 'primary'));
    $renderLinkButton((string) ($section['button_2_text'] ?? ''), (string) ($section['button_2_target'] ?? ''), (string) ($section['button_2_style'] ?? 'ghost'));
    echo '</div>';
};

$renderCardButton = static function (array $card) use ($renderLinkButton): void {
    $renderLinkButton((string) ($card['button_label'] ?? ''), (string) ($card['button_url'] ?? ''), 'link');
};

$renderCard = static function (array $card, int $index, string $sectionType = 'card_grid') use ($renderer, $renderCardButton, $hasText): void {
    if (array_key_exists('enabled', $card) && empty($card['enabled'])) {
        return;
    }
    $type = $sectionType === 'steps' ? 'step' : (string) ($card['card_type'] ?? 'text');
    $hasButton = $hasText($card['button_label'] ?? '') && $hasText($card['button_url'] ?? '');
    $cardHasContent = match ($sectionType) {
        'technology' => $hasText($card['logo_url'] ?? '') || $hasText($card['icon'] ?? '') || $hasText($card['name'] ?? '') || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || $hasButton,
        'trust' => $hasText($card['metric'] ?? '') || $hasText($card['image_url'] ?? '') || $hasText($card['icon'] ?? '') || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || $hasButton,
        default => match ($type) {
            'image_label' => $hasText($card['image_url'] ?? '') || $hasText($card['icon'] ?? '') || $hasText($card['label'] ?? '') || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || $hasButton,
            'offer_icon_tab' => (!empty($card['tab_enabled']) && $hasText($card['icon'] ?? '')) || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || $hasButton,
            'step' => $hasText($card['icon'] ?? '') || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || $hasButton,
            'problem_solution' => $hasText($card['problem_title'] ?? '') || $hasText($card['problem_text'] ?? '') || $hasText($card['solution_title'] ?? '') || $hasText($card['solution_text'] ?? '') || $hasText($card['extra_text'] ?? '') || $hasText($card['recommendation'] ?? '') || $hasButton,
            default => $hasText($card['category'] ?? '') || $hasText($card['badge'] ?? '') || $hasText($card['image_url'] ?? '') || $hasText($card['icon'] ?? '') || $hasText($card['title'] ?? '') || $hasText($card['text'] ?? '') || ($sectionType !== 'proof' && $hasText($card['extra_text'] ?? '')) || $hasButton,
        },
    };
    if (!$cardHasContent) {
        return;
    }
    $classes = ['cms-beratung-card', 'cms-beratung-card--' . $type, 'cms-beratung-card--module-' . $sectionType];
    $cardLayout = in_array((string) ($card['card_layout'] ?? 'classic'), ['classic', 'media-left', 'compact', 'spotlight'], true) ? (string) ($card['card_layout'] ?? 'classic') : 'classic';
    $classes[] = 'cms-beratung-card--layout-' . $cardLayout;
    $cardTheme = in_array((string) ($card['card_theme'] ?? 'default'), ['default', 'experience', 'consulting', 'exchange', 'iamcp', 'projects', 'security', 'governance', 'microsoft', 'network'], true) ? (string) ($card['card_theme'] ?? 'default') : 'default';
    if ($cardTheme !== 'default') { $classes[] = 'cms-beratung-card--theme-' . $cardTheme; }
    $accentMap = ['experience' => 'erfahrung', 'exchange' => 'migration', 'network' => 'netzwerk'];
    $accentSlug = $accentMap[$cardTheme] ?? '';
    if ($accentSlug === '') {
        $accentSource = strtolower((string) ($card['category'] ?? '') . ' ' . (string) ($card['badge'] ?? '') . ' ' . (string) ($card['label'] ?? '') . ' ' . (string) ($card['title'] ?? ''));
        if (str_contains($accentSource, 'erfahrung')) {
            $accentSlug = 'erfahrung';
        } elseif (str_contains($accentSource, 'migration') || str_contains($accentSource, 'exchange')) {
            $accentSlug = 'migration';
        } elseif (str_contains($accentSource, 'netzwerk') || str_contains($accentSource, 'network')) {
            $accentSlug = 'netzwerk';
        }
    }
    if ($accentSlug !== '') { $classes[] = 'cms-beratung-card--accent-' . $accentSlug; }
    $iconTextLayout = in_array((string) ($card['icon_text_layout'] ?? 'below'), ['below', 'right'], true) ? (string) ($card['icon_text_layout'] ?? 'below') : 'below';
    $classes[] = 'cms-beratung-card--icon-text-' . $iconTextLayout;
    foreach (['featured' => 'is-featured', 'hover_enabled' => 'has-hover'] as $key => $class) {
        if (!empty($card[$key])) {
            $classes[] = $class;
        }
    }
    if (empty($card['border_enabled'])) { $classes[] = 'no-border'; }
    if (empty($card['shadow_enabled'])) { $classes[] = 'no-shadow'; }
    $style = '--card-bg:' . $renderer::esc((string) ($card['background_color'] ?? '#ffffff')) . ';--card-text:' . $renderer::esc((string) ($card['text_color'] ?? '#111827')) . ';--card-border:' . $renderer::esc((string) ($card['border_color'] ?? '#dbeafe')) . ';';
    echo '<article class="' . $renderer::esc(implode(' ', $classes)) . '" style="' . $style . '">';

    if ($sectionType === 'faq') {
        echo '</article>';
        return;
    }

    if ($sectionType === 'technology') {
        $technologyTitle = trim((string) ($card['name'] ?? $card['title'] ?? ''));
        if (!empty($card['logo_url'])) {
            echo '<img class="cms-beratung-card__logo" src="' . $renderer::esc((string) $card['logo_url']) . '" alt="' . $renderer::esc($technologyTitle) . '" loading="lazy">';
        } elseif (!empty($card['icon'])) {
            echo '<div class="cms-beratung-card__icon" aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</div>';
        }
        if ($technologyTitle !== '') { echo '<h3>' . $renderer::esc($technologyTitle) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        echo '</article>';
        return;
    }

    if ($sectionType === 'trust') {
        if (!empty($card['metric'])) { echo '<strong class="cms-beratung-card__metric">' . $renderer::esc((string) $card['metric']) . '</strong>'; }
        if (!empty($card['icon'])) { echo '<div class="cms-beratung-card__icon" aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</div>'; }
        if (!empty($card['title'])) { echo '<h3>' . $renderer::esc((string) $card['title']) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        $renderCardButton($card);
        echo '</article>';
        return;
    }

    if ($sectionType === 'services' && !empty($card['category'])) {
        echo '<span class="cms-beratung-card__category">' . $renderer::esc((string) $card['category']) . '</span>';
    }

    if ($type === 'image_label') {
        if (!empty($card['image_url'])) {
            echo '<div class="cms-beratung-card__image has-image" style="--card-image-height:' . (int) ($card['image_height'] ?? 230) . 'px;--card-image-fit:' . $renderer::esc((string) ($card['image_fit'] ?? 'cover')) . '"><img src="' . $renderer::esc((string) $card['image_url']) . '" alt="' . $renderer::esc((string) ($card['image_alt'] ?? $card['title'] ?? '')) . '" loading="lazy"></div>';
        } elseif (!empty($card['icon'])) {
            echo '<div class="cms-beratung-card__image is-placeholder" aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</div>';
        }
        if (!empty($card['label'])) {
            echo '<span class="cms-beratung-card__label cms-beratung-card__label--' . $renderer::esc((string) ($card['label_position'] ?? 'left')) . ' cms-beratung-card__label--' . $renderer::esc((string) ($card['label_style'] ?? 'filled')) . '">' . $renderer::esc((string) $card['label']) . '</span>';
        }
        echo '<div class="cms-beratung-card__body">';
        if (!empty($card['title'])) { echo '<h3>' . $renderer::esc((string) $card['title']) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        $renderCardButton($card);
        echo '</div>';
    } elseif ($type === 'offer_icon_tab') {
        if (!empty($card['tab_enabled']) && !empty($card['icon'])) {
            echo '<div class="cms-beratung-card__tab" style="--icon-bg:' . $renderer::esc((string) ($card['icon_background'] ?? 'var(--color-tint-blue)')) . ';--icon-color:' . $renderer::esc((string) ($card['icon_color'] ?? 'var(--color-primary)')) . '" aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</div>';
        }
        if (!empty($card['title'])) { echo '<h3>' . $renderer::esc((string) $card['title']) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        $renderCardButton($card);
    } elseif ($type === 'step') {
        $step = !empty($card['auto_number']) ? (string) ($index + 1) : (string) ($card['step_number'] ?? $index + 1);
        echo '<div class="cms-beratung-card__step"><span>' . $renderer::esc($step) . '</span>' . (!empty($card['icon']) ? '<i aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</i>' : '') . '</div>';
        if (!empty($card['connector'])) { echo '<span class="cms-beratung-card__connector" aria-hidden="true"></span>'; }
        if (!empty($card['title'])) { echo '<h3>' . $renderer::esc((string) $card['title']) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        $renderCardButton($card);
    } elseif ($type === 'problem_solution') {
        echo '<div class="cms-beratung-problem-solution"><div><span>Spalte 1</span>' . ($hasText($card['problem_title'] ?? '') ? '<h3>' . $renderer::esc((string) $card['problem_title']) . '</h3>' : '') . ($hasText($card['problem_text'] ?? '') ? '<p>' . $renderer::nl((string) $card['problem_text']) . '</p>' : '') . '</div><div><span>Spalte 2</span>' . ($hasText($card['solution_title'] ?? '') ? '<h3>' . $renderer::esc((string) $card['solution_title']) . '</h3>' : '') . ($hasText($card['solution_text'] ?? '') ? '<p>' . $renderer::nl((string) $card['solution_text']) . '</p>' : '') . '</div></div>';
        if ($hasText($card['extra_text'] ?? '') || $hasText($card['recommendation'] ?? '')) {
            echo '<div class="cms-beratung-card__risk"><p>' . $renderer::nl((string) ($card['extra_text'] ?? $card['recommendation'] ?? '')) . '</p></div>';
        }
        $renderCardButton($card);
    } else {
        if (!empty($card['badge'])) { echo '<span class="cms-beratung-card__badge">' . $renderer::esc((string) $card['badge']) . '</span>'; }
        if (!empty($card['image_url'])) {
            echo '<div class="cms-beratung-card__media has-image" style="--card-image-height:' . (int) ($card['image_height'] ?? 160) . 'px;--card-image-fit:' . $renderer::esc((string) ($card['image_fit'] ?? 'cover')) . '"><img src="' . $renderer::esc((string) $card['image_url']) . '" alt="' . $renderer::esc((string) ($card['image_alt'] ?? $card['title'] ?? '')) . '" loading="lazy"></div>';
        } elseif (!empty($card['icon'])) {
            echo '<div class="cms-beratung-card__icon" aria-hidden="true">' . $renderer::esc((string) $card['icon']) . '</div>';
        }
        if (!empty($card['title'])) { echo '<h3>' . $renderer::esc((string) $card['title']) . '</h3>'; }
        if ($hasText($card['text'] ?? '')) { echo '<p>' . $renderer::nl((string) $card['text']) . '</p>'; }
        if ($sectionType !== 'proof' && $hasText($card['extra_text'] ?? '')) { echo '<p class="cms-beratung-card__extra">' . $renderer::nl((string) $card['extra_text']) . '</p>'; }
        $renderCardButton($card);
    }
    echo '</article>';
};

$renderCardsGrid = static function (array $section, array $cards, int $columns) use ($renderCard): void {
    echo '<div class="cms-beratung__cards cms-beratung__cards--' . $columns . ' cms-beratung__cards--design-' . CMS_Beratung_Renderer::esc((string) ($section['card_design'] ?? 'standard')) . '">';
    foreach ($cards as $index => $card) {
        if (is_array($card)) {
            $renderCard($card, $index, (string) ($section['type'] ?? 'card_grid'));
        }
    }
    echo '</div>';
};

$expertName = static function (object $expert): string {
    $first = trim((string) ($expert->first_name ?? ''));
    $last = trim((string) ($expert->last_name ?? ''));
    $name = trim($first . ' ' . $last);
    return $name !== '' ? $name : 'Expert #' . (int) ($expert->id ?? 0);
};

$expertInitials = static function (object $expert) use ($expertName): string {
    $parts = preg_split('/\s+/', trim($expertName($expert))) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
    }
    $letters = strtoupper($letters);
    return $letters !== '' ? $letters : 'EX';
};

$expertExcerpt = static function (object $expert): string {
    $raw = trim((string) ($expert->biography ?? ''));
    if ($raw === '') {
        return '';
    }
    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }
    return function_exists('mb_substr') ? (string) mb_substr($text, 0, 150, 'UTF-8') : substr($text, 0, 150);
};

$normalizeExpertIds = static function (mixed $raw): array {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : (preg_split('/[\s,;]+/', $raw) ?: []);
    } elseif (is_int($raw) || is_float($raw)) {
        $raw = [$raw];
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
};

$loadCollaborationExperts = static function (mixed $ids) use ($normalizeExpertIds): array {
    if (!class_exists('CMS_365NET_Experts_And_Companie_Database')) {
        return [];
    }
    $database = CMS_365NET_Experts_And_Companie_Database::instance();
    $experts = [];
    foreach ($normalizeExpertIds($ids) as $id) {
        try {
            $expert = $database->getExpertPublicById($id);
            if (is_object($expert)) {
                $experts[] = $expert;
            }
        } catch (Throwable) {
            continue;
        }
    }
    return $experts;
};

$renderCollaboration = static function (array $section, int $columns) use ($renderer, $loadCollaborationExperts, $expertName, $expertInitials, $expertExcerpt): void {
    $experts = $loadCollaborationExperts($section['expert_ids'] ?? []);
    if ($experts === []) {
        return;
    }
    $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    echo '<div class="cms-beratung-collab cms-beratung-collab--cols-' . max(2, min(4, $columns)) . '">';
    foreach ($experts as $expert) {
        $id = (int) ($expert->id ?? 0);
        $name = $expertName($expert);
        $position = trim((string) ($expert->position ?? ''));
        $company = trim((string) ($expert->company ?? ''));
        $awards = trim((string) ($expert->awards ?? ''));
        $isMvp = $awards !== '' && stripos($awards, 'mvp') !== false;
        $photo = trim((string) ($expert->photo_url ?? ''));
        $detailUrl = $id > 0 && $base !== '' ? $base . '/experts/' . $id : '';
        $excerpt = $expertExcerpt($expert);
        echo '<article class="cms-beratung-collab-card' . ($isMvp ? ' is-mvp' : '') . '">';
        if ($isMvp) {
            echo '<span class="cms-beratung-collab-card__mvp">Microsoft MVP</span>';
        }
        echo '<div class="cms-beratung-collab-card__head"><div class="cms-beratung-collab-card__avatar">';
        if ($photo !== '') {
            echo '<img src="' . $renderer::esc($photo) . '" alt="' . $renderer::esc($name) . '" loading="lazy">';
        } else {
            echo '<span>' . $renderer::esc($expertInitials($expert)) . '</span>';
        }
        echo '</div><div><h3>' . $renderer::esc($name) . '</h3>';
        if ($position !== '') {
            echo '<p class="cms-beratung-collab-card__position">' . $renderer::esc($position) . '</p>';
        }
        echo '</div></div>';
        if ($company !== '') {
            echo '<span class="cms-beratung-collab-card__company">' . $renderer::esc($company) . '</span>';
        }
        if ($excerpt !== '') {
            echo '<p class="cms-beratung-collab-card__excerpt">' . $renderer::esc($excerpt) . '</p>';
        }
        if ($detailUrl !== '') {
            echo '<a class="cms-beratung-collab-card__link" href="' . $renderer::esc($detailUrl) . '">Expert Profil ansehen</a>';
        }
        echo '</article>';
    }
    echo '</div>';
    if (!empty($section['mvp_note_enabled']) && trim((string) ($section['mvp_note_text'] ?? '')) !== '') {
        echo '<p class="cms-beratung-collab__mvp-note"><span>★</span>' . $renderer::esc((string) $section['mvp_note_text']) . '</p>';
    }
};

$renderComparison = static function (array $section, array $cards, int $columns) use ($renderer, $renderCard, $hasText): void {
    if (!empty($section['title_band_enabled']) && !empty($section['title_band_text'])) {
        echo '<div class="cms-beratung-comparison__band" style="--band-bg:' . $renderer::esc((string) ($section['title_band_background_color'] ?? '#1e3a8a')) . ';--band-text:' . $renderer::esc((string) ($section['title_band_text_color'] ?? '#ffffff')) . '">' . $renderer::esc((string) $section['title_band_text']) . '</div>';
    }
    echo '<div class="cms-beratung-comparison cms-beratung-comparison--' . $columns . ' cms-beratung__cards cms-beratung__cards--' . $columns . ' cms-beratung__cards--design-' . $renderer::esc((string) ($section['card_design'] ?? 'standard')) . ' ' . (!empty($section['equal_height']) ? 'has-equal-cards' : '') . ' ' . (!empty($section['border_enabled']) ? 'has-border' : '') . ' ' . (!empty($section['shadow_enabled']) ? 'has-shadow' : '') . '">';
    foreach ($cards as $index => $card) {
        if (!is_array($card)) { continue; }
        $renderCard($card, $index, 'comparison');
    }
    echo '</div>';
};

$renderFaq = static function (array $section, array $cards, string $sectionId) use ($renderer, $hasText): void {
    echo '<div class="cms-beratung-faq" data-allow-multiple="' . (!empty($section['faq_allow_multiple']) ? '1' : '0') . '" style="--faq-q-bg:' . $renderer::esc((string) ($section['faq_question_background_color'] ?? '#ffffff')) . ';--faq-q-text:' . $renderer::esc((string) ($section['faq_question_text_color'] ?? '#111827')) . ';--faq-a-bg:' . $renderer::esc((string) ($section['faq_answer_background_color'] ?? '#f8fafc')) . '">';
    $openBehavior = (string) ($section['faq_open_behavior'] ?? 'none');
    foreach ($cards as $index => $card) {
        if (!is_array($card) || empty($card['enabled'])) { continue; }
        $question = trim((string) ($card['question'] ?? $card['title'] ?? ''));
        $answer = trim((string) ($card['answer'] ?? $card['text'] ?? ''));
        if ($question === '' && $answer === '') { continue; }
        $isOpen = ($openBehavior === 'first' && $index === 0) || ($openBehavior === 'custom' && !empty($card['default_open']));
        $buttonId = $sectionId . '-faq-button-' . $index;
        $panelId = $sectionId . '-faq-panel-' . $index;
        $icon = (string) ($section['faq_icon_style'] ?? 'plus');
        echo '<article class="cms-beratung-faq__item ' . ($isOpen ? 'is-open' : '') . '"><h3><button type="button" id="' . $renderer::esc($buttonId) . '" aria-controls="' . $renderer::esc($panelId) . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '"><span>' . $renderer::esc($question) . '</span><i data-icon-style="' . $renderer::esc($icon) . '" aria-hidden="true"></i></button></h3><div id="' . $renderer::esc($panelId) . '" role="region" aria-labelledby="' . $renderer::esc($buttonId) . '"' . ($isOpen ? '' : ' hidden') . '>' . ($answer !== '' ? '<p>' . $renderer::nl($answer) . '</p>' : '') . '</div></article>';
    }
    echo '</div>';
};

$renderDivider = static function (array $section) use ($renderer, $renderSectionActions, $hasText): void {
    $type = (string) ($section['divider_type'] ?? 'line');
    $layout = in_array((string) ($section['divider_layout'] ?? 'centered'), ['centered', 'image-left', 'image-right'], true) ? (string) ($section['divider_layout'] ?? 'centered') : 'centered';
    $imageUrl = trim((string) ($section['divider_image_url'] ?? ''));
    $imageAlt = trim((string) ($section['divider_image_alt'] ?? '')) ?: trim((string) ($section['divider_title'] ?? ''));
    echo '<div class="cms-beratung-divider cms-beratung-divider--' . $renderer::esc($type) . ' cms-beratung-divider--layout-' . $renderer::esc($layout) . ' cms-beratung-divider--mobile-' . $renderer::esc((string) ($section['divider_mobile_behavior'] ?? 'stack')) . '" style="--divider-bg:' . $renderer::esc((string) ($section['background_color'] ?? '#ffffff')) . ';--divider-text:' . $renderer::esc((string) ($section['text_color'] ?? '#111827')) . ';--divider-line:' . $renderer::esc((string) ($section['divider_line_color'] ?? '#dbeafe')) . ';--divider-width:' . (int) ($section['divider_width'] ?? 100) . '%;--divider-pl:' . max(0, min(180, (int) ($section['divider_padding_left'] ?? 20))) . 'px;--divider-pr:' . max(0, min(180, (int) ($section['divider_padding_right'] ?? 20))) . 'px;--divider-image-width:' . max(18, min(45, (int) ($section['divider_image_width'] ?? 32))) . '%;">';
    if ($type === 'spacer') { echo '<span aria-hidden="true"></span>'; }
    elseif ($type === 'wave') { echo '<svg viewBox="0 0 1440 90" aria-hidden="true" focusable="false"><path d="M0,48 C180,96 360,0 540,42 C720,84 900,84 1080,36 C1260,-12 1350,24 1440,54 L1440,90 L0,90 Z"></path></svg>'; }
    else {
        if ($imageUrl !== '') {
            echo '<figure class="cms-beratung-divider__media"><img src="' . $renderer::esc($imageUrl) . '" alt="' . $renderer::esc($imageAlt) . '" loading="lazy" decoding="async"></figure>';
        } elseif (!empty($section['divider_icon'])) {
            echo '<span class="cms-beratung-divider__icon">' . $renderer::esc((string) $section['divider_icon']) . '</span>';
        }
        echo '<div class="cms-beratung-divider__content">';
        if (!empty($section['divider_title'])) { echo '<h2>' . $renderer::esc((string) $section['divider_title']) . '</h2>'; }
        if ($hasText($section['divider_subtitle'] ?? '')) { echo '<p>' . $renderer::nl((string) $section['divider_subtitle']) . '</p>'; }
        if ($type === 'cta') { $renderSectionActions($section); }
        echo '</div>';
    }
    echo '</div>';
};

$renderProofSection = static function (array $proof, array $testimonials, array $section = []) use ($renderer, $renderCard): void {
    $sectionCards = is_array($section['cards'] ?? null) ? array_values(array_filter($section['cards'], 'is_array')) : [];
    $proof = $sectionCards !== [] ? $sectionCards : array_map(static function (array $item): array {
        $label = strtolower((string) ($item['label'] ?? '') . ' ' . (string) ($item['title'] ?? ''));
        $cardTheme = str_contains($label, 'erfahrung') ? 'experience' : (str_contains($label, 'migration') || str_contains($label, 'exchange') ? 'exchange' : (str_contains($label, 'netzwerk') || str_contains($label, 'network') ? 'network' : 'default'));
        return [
            'enabled' => true,
            'card_type' => 'text',
            'card_layout' => 'classic',
            'card_theme' => $cardTheme,
            'badge' => (string) ($item['label'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'text' => (string) ($item['text'] ?? ''),
            'extra_text' => '',
            'border_enabled' => true,
            'shadow_enabled' => true,
            'hover_enabled' => true,
            'background_color' => '#ffffff',
            'text_color' => '#111827',
            'border_color' => '#dbeafe',
        ];
    }, $proof);
    $proof = array_values(array_filter($proof, static fn(array $item): bool => trim((string) ($item['badge'] ?? $item['category'] ?? $item['label'] ?? $item['image_url'] ?? $item['icon'] ?? $item['title'] ?? $item['text'] ?? $item['button_label'] ?? '')) !== ''));
    $testimonials = array_values(array_filter($testimonials, static fn(array $item): bool => trim((string) ($item['quote'] ?? '')) !== '' && trim((string) ($item['name'] ?? '')) !== ''));
    if ($proof === [] && $testimonials === []) {
        return;
    }
    $eyebrow = trim((string) ($section['eyebrow'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $intro = trim((string) ($section['intro'] ?? ''));
    $sectionId = trim((string) ($section['anchor_id'] ?? $section['id'] ?? 'belegbare-grundlagen')) ?: 'belegbare-grundlagen';
    $proofClasses = ['cms-beratung-proof'];
    if (!empty($section['equal_height'])) { $proofClasses[] = 'has-equal-cards'; }
    $proofStyle = '--section-bg:' . $renderer::esc((string) ($section['background_color'] ?? '#ffffff')) . ';--section-text:' . $renderer::esc((string) ($section['text_color'] ?? '#111827')) . ';--section-pt:' . (int) ($section['padding_top'] ?? 56) . 'px;--section-pb:' . (int) ($section['padding_bottom'] ?? 56) . 'px;--section-width:' . (int) ($section['max_width'] ?? 1200) . 'px;--section-align:' . $renderer::esc((string) ($section['text_align'] ?? 'left')) . ';';
    if (!empty($section['background_image_url'])) {
        $proofStyle .= "--section-bg-image:url('" . $renderer::esc((string) $section['background_image_url']) . "');";
    }
    echo '<section class="' . $renderer::esc(implode(' ', $proofClasses)) . '" id="' . $renderer::esc($sectionId) . '" aria-labelledby="beratung-proof-title" style="' . $proofStyle . '"><div class="cms-beratung-proof__inner">' . ($eyebrow !== '' || $title !== '' || $intro !== '' ? '<div class="cms-beratung-proof__head">' . ($eyebrow !== '' ? '<span>' . $renderer::esc($eyebrow) . '</span>' : '') . ($title !== '' ? '<h2 id="beratung-proof-title">' . $renderer::esc($title) . '</h2>' : '') . ($intro !== '' ? '<p>' . $renderer::esc($intro) . '</p>' : '') . '</div>' : '');
    if ($proof !== []) {
        $columns = max(1, min(4, (int) ($section['columns'] ?? 3)));
        $cardDesign = preg_replace('/[^a-z0-9_-]/i', '', (string) ($section['card_design'] ?? 'accent')) ?: 'accent';
        echo '<div class="cms-beratung-proof__grid cms-beratung__cards cms-beratung__cards--' . $columns . ' cms-beratung__cards--design-' . $renderer::esc($cardDesign) . '">';
        foreach ($proof as $index => $item) {
            $renderCard($item, $index, 'proof');
        }
        echo '</div>';
    }
    if ($testimonials !== []) {
        echo '<div class="cms-beratung-proof__testimonials" aria-label="Echte Referenzen">';
        foreach ($testimonials as $item) {
            $person = trim((string) ($item['name'] ?? ''));
            $role = trim((string) ($item['role'] ?? ''));
            $company = trim((string) ($item['company'] ?? ''));
            $caption = trim(implode(', ', array_filter([$person, $role, $company], static fn(string $value): bool => $value !== '')));
            echo '<article class="cms-beratung-proof-testimonial"><blockquote>' . $renderer::esc((string) $item['quote']) . '</blockquote>' . ($caption !== '' ? '<footer>' . $renderer::esc($caption) . '</footer>' : '') . '</article>';
        }
        echo '</div>';
    }
    echo '</div></section>';
};

$renderBookingSection = static function (string $bookingUrl, array $section = []) use ($renderer): void {
    $bookingUrl = trim((string) ($section['booking_url'] ?? $bookingUrl));
    if ($bookingUrl !== '' && preg_match('#^https://#i', $bookingUrl) !== 1) {
        $bookingUrl = '';
    }
    $bookingDisplay = (string) ($section['booking_display'] ?? 'embed');
    if (!in_array($bookingDisplay, ['embed', 'link', 'button'], true)) {
        $bookingDisplay = 'embed';
    }
    $bookingButtonText = trim((string) ($section['booking_button_text'] ?? 'Termin buchen')) ?: 'Termin buchen';
    $eyebrow = trim((string) ($section['eyebrow'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $intro = trim((string) ($section['intro'] ?? ''));
    $sectionId = trim((string) ($section['anchor_id'] ?? $section['id'] ?? 'termin-buchen')) ?: 'termin-buchen';
    echo '<section class="cms-beratung-booking cms-beratung-booking--display-' . $renderer::esc($bookingDisplay) . '" id="' . $renderer::esc($sectionId) . '" aria-labelledby="beratung-booking-title"><div class="cms-beratung-booking__inner">' . ($eyebrow !== '' || $title !== '' || $intro !== '' ? '<div class="cms-beratung-booking__head">' . ($eyebrow !== '' ? '<span>' . $renderer::esc($eyebrow) . '</span>' : '') . ($title !== '' ? '<h2 id="beratung-booking-title">' . $renderer::esc($title) . '</h2>' : '') . ($intro !== '' ? '<p>' . $renderer::esc($intro) . '</p>' : '') . '<ul class="cms-beratung-booking__trust" aria-label="Termin Vorteile"><li>Kostenloses Erstgespräch</li><li>Antwort in 24h</li><li>Remote oder vor Ort</li></ul></div>' : '');
    if ($bookingDisplay === 'button') {
        if ($bookingUrl !== '') {
            echo '<div class="cms-beratung-booking-actions cms-beratung-module__actions"><a class="cms-beratung__btn cms-beratung__btn--primary" href="' . $renderer::esc($bookingUrl) . '" target="_blank" rel="noopener noreferrer">' . $renderer::esc($bookingButtonText) . '</a></div>';
        }
        echo '</div></section>';
        return;
    }
    echo '<div class="cms-beratung-booking-card">';
    if ($bookingUrl !== '' && $bookingDisplay === 'embed') {
        echo '<div class="cms-beratung-booking-card__embed"><iframe src="' . $renderer::esc($bookingUrl) . '" title="Microsoft Bookings Terminbuchung für Beratung" loading="lazy" allow="clipboard-write; fullscreen" sandbox="allow-forms allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"></iframe></div>';
    } elseif ($bookingUrl !== '') {
        echo '<div class="cms-beratung-booking-card__fallback is-connected"><span>Microsoft Bookings</span><h3>Direkt einen passenden Termin auswählen.</h3><p>Der Kalender ist verbunden. Öffne Microsoft Bookings und wähle den Slot, der am besten passt.</p><a class="cms-beratung__btn cms-beratung__btn--primary" href="' . $renderer::esc($bookingUrl) . '" target="_blank" rel="noopener noreferrer">' . $renderer::esc($bookingButtonText) . '</a></div>';
    } else {
        echo '<div class="cms-beratung-booking-card__fallback"><span>Terminbuchung wird vorbereitet</span><h3>Der direkte Kalender ist noch nicht verbunden.</h3><p>Bis der Microsoft Bookings Link hinterlegt ist, kannst du deine Anfrage über das Kontaktformular senden. Ich melde mich mit passenden Terminvorschlägen zurück.</p><a class="cms-beratung__btn cms-beratung__btn--primary" href="#kontakt">Beratung anfragen</a></div>';
    }
    echo '</div></div></section>';
};
?>
<main class="cms-beratung cms-beratung--<?php echo $renderer::esc((string) ($page['template'] ?? 'standard')); ?> <?php echo $standaloneHeaderEnabled ? 'cms-beratung--standalone-header' : ''; ?> <?php echo $renderer::esc($customClass); ?>" data-use-global-design="<?php echo $renderer::esc($design['use_global_design'] ?? '1'); ?>" style="--beratung-primary: <?php echo $renderer::esc($design['primary_color']); ?>; --beratung-secondary: <?php echo $renderer::esc($design['secondary_color']); ?>; --beratung-accent: <?php echo $renderer::esc($design['accent_color']); ?>; --beratung-bg: <?php echo $renderer::esc($design['background_color']); ?>; --beratung-text: <?php echo $renderer::esc($design['text_color']); ?>; --beratung-heading: <?php echo $renderer::esc($design['heading_color']); ?>; --beratung-button: <?php echo $renderer::esc($design['button_color']); ?>; --beratung-button-text: <?php echo $renderer::esc($design['button_text_color']); ?>; --beratung-card-bg: <?php echo $renderer::esc($design['card_background_color']); ?>; --beratung-card-border: <?php echo $renderer::esc($design['card_border_color']); ?>; --beratung-radius: <?php echo $renderer::esc($design['border_radius']); ?>; --beratung-spacing: <?php echo $renderer::esc($design['spacing']); ?>; --beratung-width: <?php echo $renderer::esc($design['content_width']); ?>; --beratung-card-shadow: <?php echo $renderer::esc($design['card_shadow']); ?>; --beratung-font-base: <?php echo $renderer::esc($design['font_size_base']); ?>; --beratung-font-hero: <?php echo $renderer::esc($design['font_size_hero']); ?>; --beratung-font-section-title: <?php echo $renderer::esc($design['font_size_section_title']); ?>; --beratung-font-card-title: <?php echo $renderer::esc($design['font_size_card_title']); ?>; --beratung-header-spacing: <?php echo $renderer::esc($design['header_spacing']); ?>; --beratung-footer-spacing: <?php echo $renderer::esc($design['footer_spacing']); ?>; --beratung-card-spacing: <?php echo $renderer::esc($design['card_spacing']); ?>; --beratung-section-content-spacing: <?php echo $renderer::esc($design['section_content_spacing']); ?>;">
    <?php if ($standaloneHeaderEnabled && empty($page['standalone_header_rendered_above_content'])): ?><?php $renderer::render_standalone_header($page); ?><?php endif; ?>
    <?php if (!empty($page['show_breadcrumb'])): ?>
        <nav class="cms-beratung__breadcrumb" aria-label="Breadcrumb"><a href="/">Startseite</a><span aria-hidden="true">/</span><span><?php echo $renderer::esc((string) ($page['public_title'] ?? 'Beratung')); ?></span></nav>
    <?php endif; ?>

    <?php if (!empty($hero['enabled'])): ?>
        <?php $heroTitle = trim((string) ($hero['title'] ?? '')); $heroButtons = array_values(array_filter([is_array($hero['button_1'] ?? null) ? $hero['button_1'] : [], is_array($hero['button_2'] ?? null) ? $hero['button_2'] : [], is_array($hero['button_3'] ?? null) ? $hero['button_3'] : []], static fn(array $button): bool => trim((string) ($button['text'] ?? '')) !== '' && trim((string) ($button['target'] ?? '')) !== '')); ?>
        <header class="cms-beratung-hero cms-beratung-hero--image-<?php echo $renderer::esc((string) ($hero['image_position'] ?? 'left')); ?> cms-beratung-hero--mobile-<?php echo $renderer::esc((string) ($hero['mobile_order'] ?? 'image-first')); ?> <?php echo !empty($hero['image_flush']) ? 'is-flush' : ''; ?>" style="--hero-bg:<?php echo $renderer::esc((string) ($hero['background_color'] ?? '#f8fafc')); ?>;--hero-text:<?php echo $renderer::esc((string) ($hero['text_color'] ?? '#111827')); ?>;--hero-image-height:<?php echo (int) ($hero['image_height'] ?? 520); ?>px;--hero-image-width:<?php echo (int) ($hero['image_width'] ?? 46); ?>%;--hero-image-fit:<?php echo $renderer::esc((string) ($hero['image_fit'] ?? 'cover')); ?>;--hero-align:<?php echo $renderer::esc((string) ($hero['vertical_align'] ?? 'center')); ?>;">
            <div class="cms-beratung-hero__image"><?php if (!empty($hero['image_url'])): ?><img src="<?php echo $renderer::esc((string) $hero['image_url']); ?>" alt="<?php echo $renderer::esc((string) ($hero['image_alt'] ?? $heroTitle)); ?>" loading="eager"><?php else: ?><div class="cms-beratung-hero__placeholder" aria-hidden="true">365</div><?php endif; ?></div>
            <div class="cms-beratung-hero__content">
                <?php if (!empty($hero['badge_show']) && !empty($hero['badge_text'])): ?><span class="cms-beratung__kicker"><?php echo $renderer::esc((string) $hero['badge_text']); ?></span><?php endif; ?>
                <?php if ($heroTitle !== ''): ?><h1><?php echo $renderer::esc($heroTitle); ?></h1><?php endif; ?>
                <?php if (!empty($hero['subtitle'])): ?><p class="cms-beratung-hero__subtitle"><?php echo $renderer::esc((string) $hero['subtitle']); ?></p><?php endif; ?>
                <?php if ($heroTrustBadges !== []): ?><div class="cms-beratung-hero__trust-row" aria-label="Trust Signale"><?php foreach ($heroTrustBadges as $trustBadge): ?><span><?php echo $renderer::esc($trustBadge); ?></span><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($hero['description'])): ?><p class="cms-beratung-hero__description"><?php echo $renderer::esc((string) $hero['description']); ?></p><?php endif; ?>
                <?php if ($heroButtons !== []): ?><div class="cms-beratung__hero-actions"><?php foreach ($heroButtons as $heroButton) { $renderButton($heroButton); } ?></div><?php endif; ?>
                <?php if (!empty($hero['trust_text'])): ?><p class="cms-beratung-hero__trust"><?php echo $renderer::esc((string) $hero['trust_text']); ?></p><?php endif; ?>
                <?php if ($heroTrustImageEnabled): ?>
                <figure class="cms-beratung-hero__portrait-slot">
                    <?php if ($heroTrustImageUrl !== ''): ?>
                        <img class="cms-beratung-hero__portrait-image" src="<?php echo $renderer::esc($heroTrustImageUrl); ?>" alt="<?php echo $renderer::esc($heroTrustImageAlt); ?>" loading="lazy" decoding="async">
                    <?php elseif (is_file($heroPortraitPath)): ?>
                        <img class="cms-beratung-hero__portrait-image" src="<?php echo $renderer::esc($heroPortraitUrl); ?>" alt="<?php echo $renderer::esc($heroTrustImageAlt); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span aria-label="Portrait Platzhalter">M365</span>
                    <?php endif; ?>
                </figure>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>

    <?php $visibleAnchors = array_values(array_filter($anchors, static fn(array $section): bool => trim((string) ($section['title'] ?? '')) !== '')); ?>
    <?php if (!empty($page['show_anchor_nav']) && $visibleAnchors !== []): ?>
        <nav class="cms-beratung__anchor-nav cms-beratung__anchor-nav--<?php echo $renderer::esc($anchorNavLayout); ?>" aria-label="Anker Navigation" id="beratung-inhalte">
            <span class="cms-beratung__anchor-nav-title">Inhalte</span>
            <?php foreach ($visibleAnchors as $navIndex => $section): ?><a href="#<?php echo $renderer::esc((string) ($section['anchor_id'] ?? $section['id'] ?? 'bereich')); ?>"><span class="cms-beratung__anchor-nav-index"><?php echo $renderer::esc(str_pad((string) ($navIndex + 1), 2, '0', STR_PAD_LEFT)); ?></span><span class="cms-beratung__anchor-nav-text"><?php echo $renderer::esc((string) $section['title']); ?></span></a><?php endforeach; ?>
            <a href="#kontakt"><span class="cms-beratung__anchor-nav-index"><?php echo $renderer::esc(str_pad((string) (count($visibleAnchors) + 1), 2, '0', STR_PAD_LEFT)); ?></span><span class="cms-beratung__anchor-nav-text">Kontakt</span></a>
        </nav>
    <?php endif; ?>

    <?php $rightTocEnabled = !empty($page['show_toc']) && $tocRightDisplay === 'card' && $visibleAnchors !== []; ?>
    <?php if ($rightTocEnabled): ?>
        <aside class="cms-beratung__toc-rail cms-beratung__toc-rail--<?php echo $renderer::esc($tocRightLayout); ?>" aria-label="Inhaltsverzeichnis" data-toc-state="expanded" data-toc-lock="expanded" data-toc-layout="<?php echo $renderer::esc($tocRightLayout); ?>">
            <button class="cms-beratung__toc-toggle" type="button" aria-expanded="true" aria-label="Inhaltsverzeichnis schließen"><span aria-hidden="true">☰</span><strong>Inhalte</strong></button>
            <nav class="cms-beratung__toc" aria-label="Abschnitte der Landingpage">
                <?php foreach ($visibleAnchors as $navIndex => $section): ?><a class="cms-beratung__toc-link" href="#<?php echo $renderer::esc((string) ($section['anchor_id'] ?? $section['id'] ?? 'bereich')); ?>" data-toc-target="<?php echo $renderer::esc((string) ($section['anchor_id'] ?? $section['id'] ?? 'bereich')); ?>"><span><?php echo $renderer::esc(str_pad((string) ($navIndex + 1), 2, '0', STR_PAD_LEFT)); ?></span><?php echo $renderer::esc((string) $section['title']); ?></a><?php endforeach; ?>
                <a class="cms-beratung__toc-link" href="#kontakt" data-toc-target="kontakt"><span><?php echo $renderer::esc(str_pad((string) (count($visibleAnchors) + 1), 2, '0', STR_PAD_LEFT)); ?></span>Kontakt</a>
            </nav>
        </aside>
    <?php endif; ?>

    <?php $sectionNumber = 0; foreach ($sections as $section): ?>
        <?php if (empty($section['enabled'])) { continue; } ?>
        <?php $columns = max(1, min(4, (int) ($section['columns'] ?? 3))); $sectionId = (string) ($section['anchor_id'] ?? $section['id'] ?? 'bereich'); $sectionType = (string) ($section['type'] ?? 'card_grid'); $cards = is_array($section['cards'] ?? null) ? $section['cards'] : []; ?>
        <?php if ($sectionType === 'faq') { continue; } ?>
        <?php if ($sectionType === 'partner_band'): ?>
            <?php $partnerTitle = trim((string) ($section['title'] ?? '')); $partnerIntro = trim((string) ($section['intro'] ?? '')); $partnerEyebrow = trim((string) ($section['eyebrow'] ?? '')); $partnerButton1Text = trim((string) ($section['button_1_text'] ?? '')); $partnerButton1Target = trim((string) ($section['button_1_target'] ?? '')); $partnerButton2Text = trim((string) ($section['button_2_text'] ?? '')); $partnerButton2Target = trim((string) ($section['button_2_target'] ?? '')); $partnerLayout = in_array((string) ($section['partner_layout'] ?? 'network-card'), ['network-card', 'split-panel', 'centered-badge', 'compact-strip'], true) ? (string) ($section['partner_layout'] ?? 'network-card') : 'network-card'; ?>
            <?php if ($partnerTitle !== '' || $partnerIntro !== '' || ($partnerButton1Text !== '' && $partnerButton1Target !== '') || ($partnerButton2Text !== '' && $partnerButton2Target !== '')): ?>
                <aside class="cms-beratung-partner-band cms-beratung-partner-band--<?php echo $renderer::esc($partnerLayout); ?>" id="<?php echo $renderer::esc($sectionId); ?>" aria-label="Partner Netzwerk">
                    <div class="cms-beratung-partner-band__content">
                        <?php if ($partnerEyebrow !== ''): ?><span class="cms-beratung-partner-band__eyebrow"><?php echo $renderer::esc($partnerEyebrow); ?></span><?php endif; ?>
                        <?php if ($partnerTitle !== ''): ?><strong><?php echo $renderer::esc($partnerTitle); ?></strong><?php endif; ?>
                    </div>
                    <span class="cms-beratung-partner-band__links">
                        <?php if ($partnerButton1Text !== '' && $partnerButton1Target !== ''): ?><a href="<?php echo $renderer::esc($partnerButton1Target); ?>" target="_blank" rel="noopener noreferrer"><?php echo $renderer::esc($partnerButton1Text); ?></a><?php endif; ?>
                        <?php if ($partnerButton2Text !== '' && $partnerButton2Target !== ''): ?><a href="<?php echo $renderer::esc($partnerButton2Target); ?>" target="_blank" rel="noopener noreferrer"><?php echo $renderer::esc($partnerButton2Text); ?></a><?php endif; ?>
                    </span>
                    <?php if ($partnerIntro !== ''): ?><p class="cms-beratung-partner-band__description"><?php echo $renderer::esc($partnerIntro); ?></p><?php endif; ?>
                </aside>
            <?php endif; ?>
            <?php continue; ?>
        <?php endif; ?>
        <?php if ($sectionType === 'proof') { $renderProofSection($proof, $testimonials, $section); continue; } ?>
        <?php if ($sectionType === 'booking') { $renderBookingSection($bookingUrl, $section); continue; } ?>
        <?php if ($sectionType === 'collaboration' && $normalizeExpertIds($section['expert_ids'] ?? []) === []) { continue; } ?>
        <?php $sectionNumber++; $rhythmClass = $sectionType === 'cta' ? 'cms-beratung__section--rhythm-accent' : (($sectionNumber % 2 === 0) ? 'cms-beratung__section--rhythm-soft' : 'cms-beratung__section--rhythm-white'); ?>
        <section class="cms-beratung__section cms-beratung__section--<?php echo $renderer::esc($sectionType); ?> cms-beratung__section--display-<?php echo $renderer::esc((string) ($section['display_style'] ?? 'cards')); ?> <?php echo $renderer::esc($rhythmClass); ?> <?php echo !empty($section['equal_height']) ? 'has-equal-cards' : ''; ?>" id="<?php echo $renderer::esc($sectionId); ?>" style="--section-bg: <?php echo $renderer::esc((string) ($section['background_color'] ?? '#ffffff')); ?>; --section-text: <?php echo $renderer::esc((string) ($section['text_color'] ?? '#111827')); ?>; --section-pt: <?php echo (int) ($section['padding_top'] ?? 56); ?>px; --section-pb: <?php echo (int) ($section['padding_bottom'] ?? 56); ?>px; --section-pl: <?php echo max(0, min(180, (int) ($section['divider_padding_left'] ?? 20))); ?>px; --section-pr: <?php echo max(0, min(180, (int) ($section['divider_padding_right'] ?? 20))); ?>px; --section-width: <?php echo (int) ($section['max_width'] ?? 1200); ?>px; --section-align: <?php echo $renderer::esc((string) ($section['text_align'] ?? 'left')); ?>; <?php if (!empty($section['background_image_url'])): ?>--section-bg-image:url('<?php echo $renderer::esc((string) $section['background_image_url']); ?>');<?php endif; ?><?php if ($sectionType === 'cta'): ?> background:linear-gradient(135deg,#112347 0%,#173A78 58%,#2256D6 100%) !important;color:#ffffff !important;border-color:#5f7fd6 !important;<?php endif; ?>">
            <div class="cms-beratung__section-inner">
                <?php $sectionHasHeader = $hasText($section['eyebrow'] ?? '') || $hasText($section['title'] ?? '') || $hasText($section['intro'] ?? ''); ?>
                <?php if (!in_array($sectionType, ['divider'], true) && $sectionHasHeader): ?>
                    <div class="cms-beratung__section-head"><?php if ($hasText($section['eyebrow'] ?? '')): ?><span><?php echo $renderer::esc((string) $section['eyebrow']); ?></span><?php endif; ?><?php if ($hasText($section['title'] ?? '')): ?><h2><?php echo $renderer::esc((string) $section['title']); ?></h2><?php endif; ?><?php if ($hasText($section['intro'] ?? '')): ?><p><?php echo $renderer::esc((string) $section['intro']); ?></p><?php endif; ?></div>
                <?php endif; ?>

                <?php if ($sectionType === 'divider'): ?><?php $renderDivider($section); ?>
                <?php elseif ($sectionType === 'html' && !empty($section['html'])): ?><div class="cms-beratung__html"><?php echo $renderer::safe_html((string) $section['html']); ?></div>
                <?php elseif ($sectionType === 'comparison'): ?><?php $renderComparison($section, $cards, $columns); ?>
                <?php elseif ($sectionType === 'faq'): ?><?php $renderFaq($section, $cards, $sectionId); ?>
                <?php elseif ($sectionType === 'collaboration'): ?><?php $renderCollaboration($section, $columns); ?>
                <?php elseif ($sectionType === 'cta'): ?><div class="cms-beratung-cta cms-beratung-cta--<?php echo $renderer::esc((string) ($section['display_style'] ?? 'large')); ?>"><?php $renderSectionActions($section); ?></div>
                <?php elseif ($cards !== []): ?><?php $renderCardsGrid($section, $cards, $columns); ?>
                <?php endif; ?>

                <?php if ($hasText($section['note_text'] ?? '')): ?><p class="cms-beratung-module__note"><?php echo $renderer::esc((string) $section['note_text']); ?></p><?php endif; ?>
                <?php if (!in_array($sectionType, ['cta', 'divider'], true)) { $renderSectionActions($section); } ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (!empty($m365Faq['enabled'])): ?>
        <?php $faqItems = is_array($m365Faq['items'] ?? null) ? $m365Faq['items'] : []; ?>
        <?php if ($faqItems !== []): ?>
            <?php $faqEyebrow = trim((string) ($m365Faq['eyebrow'] ?? '')); $faqTitle = trim((string) ($m365Faq['title'] ?? '')); $faqIntro = trim((string) ($m365Faq['intro'] ?? '')); ?>
            <?php $faqSection = ['faq_allow_multiple' => !empty($m365Faq['allow_multiple']), 'faq_icon_style' => (string) ($m365Faq['icon_style'] ?? 'plus'), 'faq_open_behavior' => (string) ($m365Faq['open_behavior'] ?? 'first'), 'faq_question_background_color' => '#ffffff', 'faq_question_text_color' => '#111827', 'faq_answer_background_color' => '#f8fafc']; ?>
            <section class="cms-beratung__section cms-beratung__section--faq" id="<?php echo $renderer::esc((string) ($m365Faq['anchor_id'] ?? 'faq')); ?>" aria-labelledby="beratung-faq-title" style="--section-bg: transparent; --section-text: var(--beratung-text); --section-pt: 25px; --section-pb: 25px; --section-width: 1160px; --section-align: left;">
                <div class="cms-beratung__section-inner">
                    <?php if ($faqEyebrow !== '' || $faqTitle !== '' || $faqIntro !== ''): ?><div class="cms-beratung__section-head"><?php if ($faqEyebrow !== ''): ?><span><?php echo $renderer::esc($faqEyebrow); ?></span><?php endif; ?><?php if ($faqTitle !== ''): ?><h2 id="beratung-faq-title"><?php echo $renderer::esc($faqTitle); ?></h2><?php endif; ?><?php if ($faqIntro !== ''): ?><p><?php echo $renderer::esc($faqIntro); ?></p><?php endif; ?></div><?php endif; ?>
                    <?php $renderFaq($faqSection, $faqItems, (string) ($m365Faq['anchor_id'] ?? 'faq')); ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (($contact['enabled'] ?? true) !== false): ?>
    <?php $contactId = (string) ($contact['anchor_id'] ?? 'kontakt'); $contactMode = (string) ($contact['mode'] ?? 'form'); $contactLayout = in_array((string) ($contact['layout'] ?? 'split-form'), ['split-form', 'form-left', 'centered-card', 'compact-band', 'image-left-flush'], true) ? (string) ($contact['layout'] ?? 'split-form') : 'split-form'; $contactHasImage = !empty($contact['image_url']); ?>
    <section class="cms-beratung__contact cms-beratung__contact--layout-<?php echo $renderer::esc($contactLayout); ?> <?php echo $contactHasImage ? 'cms-beratung__contact--has-image' : ''; ?>" id="<?php echo $renderer::esc($contactId); ?>" aria-labelledby="beratung-contact-title" style="--contact-bg:<?php echo $renderer::esc((string) ($contact['background_color'] ?? '#ffffff')); ?>;--contact-text:<?php echo $renderer::esc((string) ($contact['text_color'] ?? '#111827')); ?>;--contact-image-width:<?php echo max(25, min(60, (int) ($contact['image_width'] ?? 38))); ?>%;">
        <?php if ($contactLayout === 'image-left-flush' && $contactHasImage): ?><div class="cms-beratung__contact-image-panel"><img src="<?php echo $renderer::esc((string) $contact['image_url']); ?>" alt="<?php echo $renderer::esc((string) ($contact['title'] ?? '')); ?>" loading="lazy"></div><?php endif; ?>
        <div class="cms-beratung__contact-content"><?php if ($hasText($contact['eyebrow'] ?? '')): ?><span class="cms-beratung__kicker"><?php echo $renderer::esc((string) $contact['eyebrow']); ?></span><?php endif; ?><?php if ($hasText($contact['title'] ?? '')): ?><h2 id="beratung-contact-title"><?php echo $renderer::esc((string) $contact['title']); ?></h2><?php endif; ?><?php if ($hasText($contact['description'] ?? '')): ?><p><?php echo $renderer::esc((string) $contact['description']); ?></p><?php endif; ?><?php if ($contactLayout !== 'image-left-flush' && $contactHasImage): ?><img src="<?php echo $renderer::esc((string) $contact['image_url']); ?>" alt="<?php echo $renderer::esc((string) ($contact['title'] ?? '')); ?>" loading="lazy"><?php endif; ?><?php if ($hasText($contact['note_text'] ?? '')): ?><p class="cms-beratung-module__note"><?php echo $renderer::esc((string) $contact['note_text']); ?></p><?php endif; ?></div>
        <?php if ($contactMode === 'button'): ?>
            <div class="cms-beratung-module__actions"><?php $renderLinkButton((string) ($contact['button_text'] ?? ''), (string) ($contact['button_target'] ?? ''), (string) ($contact['button_style'] ?? 'primary')); ?><?php $renderLinkButton((string) ($contact['button_2_text'] ?? ''), (string) ($contact['button_2_target'] ?? ''), (string) ($contact['button_2_style'] ?? 'ghost')); ?></div>
        <?php else: ?>
        <form method="post" class="cms-beratung-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $renderer::esc($formGuardToken); ?>"><input type="hidden" name="beratung_csrf_token" value="<?php echo $renderer::esc($csrfToken); ?>"><input type="hidden" name="beratung_form_action" value="submit_request"><label class="cms-beratung-form__hp">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <?php if (($formResult['message'] ?? '') !== ''): ?><div class="cms-beratung-form__notice <?php echo !empty($formResult['success']) ? 'is-success' : 'is-error'; ?>" role="status"><?php echo $renderer::esc((string) $formResult['message']); ?></div><?php endif; ?>
            <div class="cms-beratung-form__grid"><label for="beratung-name">Name*<input id="beratung-name" name="sender_name" value="<?php echo $renderer::esc((string) ($formValues['sender_name'] ?? '')); ?>" required aria-invalid="<?php echo isset($formErrors['sender_name']) ? 'true' : 'false'; ?>"><?php if (isset($formErrors['sender_name'])): ?><small role="alert"><?php echo $renderer::esc((string) $formErrors['sender_name']); ?></small><?php endif; ?></label><label for="beratung-email">E-Mail*<input id="beratung-email" name="sender_email" type="email" value="<?php echo $renderer::esc((string) ($formValues['sender_email'] ?? '')); ?>" required aria-invalid="<?php echo isset($formErrors['sender_email']) ? 'true' : 'false'; ?>"><?php if (isset($formErrors['sender_email'])): ?><small role="alert"><?php echo $renderer::esc((string) $formErrors['sender_email']); ?></small><?php endif; ?></label><label for="beratung-phone">Telefon<input id="beratung-phone" name="phone" value="<?php echo $renderer::esc((string) ($formValues['phone'] ?? '')); ?>"></label><label for="beratung-company">Unternehmen<input id="beratung-company" name="company" value="<?php echo $renderer::esc((string) ($formValues['company'] ?? '')); ?>"></label></div>
            <label for="beratung-service">Wunschleistung<select id="beratung-service" name="desired_service"><option value="">Bitte wählen</option><?php foreach (CMS_Beratung_Forms::desired_services() as $serviceKey => $serviceLabel): ?><option value="<?php echo $renderer::esc($serviceKey); ?>"<?php echo (($formValues['desired_service'] ?? '') === $serviceKey) ? ' selected' : ''; ?>><?php echo $renderer::esc($serviceLabel); ?></option><?php endforeach; ?></select></label>
            <label for="beratung-topic">Thema<input id="beratung-topic" name="topic" value="<?php echo $renderer::esc((string) ($formValues['topic'] ?? '')); ?>" placeholder="z. B. Copilot Readiness, Security Workshop, Purview Compliance"></label><label for="beratung-message">Nachricht*<textarea id="beratung-message" name="message" rows="5" required aria-invalid="<?php echo isset($formErrors['message']) ? 'true' : 'false'; ?>"><?php echo $renderer::esc((string) ($formValues['message'] ?? '')); ?></textarea><?php if (isset($formErrors['message'])): ?><small role="alert"><?php echo $renderer::esc((string) $formErrors['message']); ?></small><?php endif; ?></label><label class="cms-beratung-form__check"><input type="checkbox" name="consent" value="1" required aria-invalid="<?php echo isset($formErrors['consent']) ? 'true' : 'false'; ?>"> <span><?php echo $renderer::esc((string) ($contact['privacy_text'] ?? $settings['privacy_text'] ?? 'Ich stimme der Verarbeitung meiner Angaben zu.')); ?></span></label><?php if (isset($formErrors['consent'])): ?><small class="cms-beratung-form__error" role="alert"><?php echo $renderer::esc((string) $formErrors['consent']); ?></small><?php endif; ?>
            <?php if (($settings['sender_copy_enabled'] ?? '0') === '1'): ?><label class="cms-beratung-form__check"><input type="checkbox" name="copy_to_sender" value="1"> <span>Kopie an mich senden</span></label><?php endif; ?>
            <button class="cms-beratung__btn cms-beratung__btn--form-submit" type="submit">Anfrage senden</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

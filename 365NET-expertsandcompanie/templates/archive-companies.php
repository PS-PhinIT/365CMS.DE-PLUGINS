<?php
/**
 * Public Archive Template – Companies.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('CMS_365NET_Experts_And_Companie')) {
    CMS_365NET_Experts_And_Companie::printInlineStyle('style.css', 'cms-excomp-public-inline');
}

$companies = array_values(array_filter(array_map(
    static fn($item): ?object => is_object($item) ? $item : (is_array($item) ? (object) $item : null),
    (array) ($companies ?? [])
)));

$filters = is_array($filters ?? null) ? $filters : [];
$q = trim((string) ($filters['q'] ?? ''));
$city = trim((string) ($filters['city'] ?? ''));
$settings = is_array($settings ?? null) ? $settings : [];
$pagination = is_array($pagination ?? null)
    ? $pagination
    : ['current' => 1, 'total' => count($companies), 'per_page' => 15, 'total_pages' => 1];

$base = rtrim((string) SITE_URL, '/');
$baseUrl = $base . '/companies';
$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$mediaUrl = static fn(mixed $value): string => class_exists('CMS_365NET_Experts_And_Companie') ? CMS_365NET_Experts_And_Companie::normalizeMediaUrl((string) $value) : trim((string) $value);
$currentPage = max(1, (int) ($pagination['current'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));

$paginationUrl = static function (int $page) use ($baseUrl, $q, $city): string {
    $query = [];
    if ($q !== '') {
        $query['q'] = $q;
    }
    if ($city !== '') {
        $query['city'] = $city;
    }
    if ($page > 1) {
        $query['page'] = (string) $page;
    }

    return $baseUrl . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
};

$setting = static function (string $key, string $default) use ($settings): string {
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$companiesHeaderTextEnabled = $setting('companies_header_text_enabled', '1') !== '0';
$companiesHeaderKickerEnabled = $setting('companies_header_kicker_enabled', '1') !== '0';
$companiesHeaderTitleEnabled = $setting('companies_header_title_enabled', '1') !== '0';
$companiesHeaderDescriptionEnabled = $setting('companies_header_description_enabled', '1') !== '0';
$companiesSearchPlaceholderEnabled = $setting('companies_search_placeholder_enabled', '1') !== '0';
$companiesHeaderHasTextContent = $companiesHeaderTextEnabled && ($companiesHeaderKickerEnabled || $companiesHeaderTitleEnabled || $companiesHeaderDescriptionEnabled);
$companiesHeaderKickerOnly = $companiesHeaderTextEnabled && $companiesHeaderKickerEnabled && !$companiesHeaderTitleEnabled && !$companiesHeaderDescriptionEnabled;
$isAllowedHeaderUrl = static function (string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    return str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1;
};
$companiesHeaderButtons = [];
for ($buttonIndex = 1; $buttonIndex <= 3; $buttonIndex++) {
    $text = trim($setting('companies_header_btn_' . $buttonIndex . '_text', ''));
    $url = trim($setting('companies_header_btn_' . $buttonIndex . '_url', ''));
    if ($text === '' || !$isAllowedHeaderUrl($url)) {
        continue;
    }

    $companiesHeaderButtons[] = ['text' => $text, 'url' => $url];
}

$designVars = [
    '--cms-excomp-page-pt' => $setting('layout_page_padding_top', '24px'),
    '--cms-excomp-page-pb' => $setting('layout_page_padding_bottom', '40px'),
    '--cms-excomp-content-max' => $setting('layout_content_max_width', '1160px'),
    '--cms-excomp-grid-gap' => $setting('layout_grid_gap', '18px'),
    '--cms-excomp-grid-gap-x' => $setting('layout_grid_gap_x', $setting('layout_grid_gap', '18px')),
    '--cms-excomp-grid-gap-y' => $setting('layout_grid_gap_y', $setting('layout_grid_gap', '18px')),
    '--cms-excomp-section-gap' => $setting('layout_section_gap', '20px'),
    '--cms-excomp-radius-card' => $setting('style_radius_card', '2px'),
    '--cms-excomp-radius-btn' => $setting('style_radius_button', '2px'),
    '--cms-excomp-radius-surface' => $setting('style_radius_surface', '4px'),
    '--cms-excomp-radius-hero' => $setting('style_radius_hero', '4px'),
    '--cms-excomp-color-bg' => $setting('color_bg', '#f8fafc'),
    '--cms-excomp-color-text' => $setting('color_text', '#0f172a'),
    '--cms-excomp-color-primary' => $setting('color_primary', '#1d4ed8'),
    '--cms-excomp-color-hero-start' => $setting('color_hero_start', '#172554'),
    '--cms-excomp-color-hero-end' => $setting('color_hero_end', '#1e40af'),
    '--cms-excomp-color-expert-accent' => $setting('color_expert_accent', '#f97316'),
    '--cms-excomp-color-company-accent' => $setting('color_company_accent', '#16a34a'),
    '--cms-excomp-color-card-bg' => $setting('color_card_bg', '#ffffff'),
    '--cms-excomp-color-border' => $setting('color_border', '#e2e8f0'),
];

$renderDesignVars = static function (array $vars): string {
    $parts = [];
    foreach ($vars as $name => $value) {
        $name = trim((string) $name);
        $value = trim((string) $value);
        if ($name === '' || $value === '') {
            continue;
        }

        $parts[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ':' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    if ($parts === []) {
        return '';
    }

    return '<style id="cms-excomp-design-vars" ' . \CMS\Security::instance()->nonceAttr() . '>:root{' . implode(';', $parts) . ';}</style>';
};

$companyCardExcerpt = static function (object $company): string {
    $raw = trim((string) ($company->description ?? ''));
    if ($raw === '') {
        return '';
    }

    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }

    return function_exists('mb_substr')
        ? (string) mb_substr($text, 0, 260, 'UTF-8')
        : substr($text, 0, 260);
};

$companyInitials = static function (object $company): string {
    $name = trim((string) ($company->name ?? ''));
    if ($name === '') {
        return 'CO';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        $letters .= strtoupper(substr((string) $part, 0, 1));
        if (strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? substr($letters, 0, 2) : 'CO';
};

$partnerLabel = static function (object $company): string {
    if ((int) ($company->is_sponsor ?? 0) === 1) {
        return 'Sponsor';
    }
    if ((int) ($company->is_top_partner ?? 0) === 1) {
        return 'Top-Partner';
    }
    if ((int) ($company->is_partner ?? 0) === 1) {
        return 'Partner';
    }

    return 'Unternehmen';
};
?>

<?= $renderDesignVars($designVars) ?>

<main class="cms-excomp-public cms-excomp-archive cms-excomp-archive--companies">
    <div class="cms-excomp-container">
        <section class="cms-excomp-hero cms-excomp-hero--compact cms-excomp-hero--companies<?= $companiesHeaderButtons !== [] ? ' cms-excomp-hero--has-actions' : '' ?><?= !$companiesHeaderHasTextContent ? ' cms-excomp-hero--no-text' : '' ?><?= $companiesHeaderKickerOnly ? ' cms-excomp-hero--kicker-only' : '' ?>" aria-label="Companies">
            <?php if ($companiesHeaderTextEnabled): ?>
                <?php if ($companiesHeaderKickerEnabled): ?><span class="cms-excomp-kicker"><?= $e($setting('companies_archive_kicker', '365 Network · Company Directory')) ?></span><?php endif; ?>
                <?php if ($companiesHeaderTitleEnabled): ?><h1><?= $e($setting('companies_archive_title', 'Companies')) ?></h1><?php endif; ?>
                <?php if ($companiesHeaderDescriptionEnabled): ?><p class="cms-excomp-hero__description"><?= $e($setting('companies_archive_description', 'Partner, Organisationen und Unternehmen mit direkten Verknüpfungen zu Experts und Speakern.')) ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($companiesHeaderButtons !== []): ?>
                <div class="cms-excomp-hero__actions" aria-label="Header-Aktionen">
                    <?php foreach ($companiesHeaderButtons as $button): ?>
                        <a class="cms-excomp-hero__action" href="<?= $e((string) $button['url']) ?>"><?= $e((string) $button['text']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <form method="GET" action="<?= $e($baseUrl) ?>" class="cms-excomp-search cms-excomp-search--header-card" role="search" aria-label="Companies Suche">
            <div class="cms-excomp-search__field">
                <input type="search" name="q" value="<?= $e($q) ?>" placeholder="<?= $companiesSearchPlaceholderEnabled ? $e($setting('companies_search_placeholder', 'Name, Branche, Beschreibung …')) : '' ?>" aria-label="Companies suchen">
            </div>
            <div class="cms-excomp-search__field">
                <input type="text" name="city" value="<?= $e($city) ?>" placeholder="Stadt …" aria-label="Stadt">
            </div>
            <div class="cms-excomp-search__actions">
                <button type="submit"><?= $e($setting('search_button_label', 'Suchen')) ?></button>
                <?php if ($q !== '' || $city !== ''): ?><a href="<?= $e($baseUrl) ?>"><?= $e($setting('reset_button_label', 'Zurücksetzen')) ?></a><?php endif; ?>
            </div>
        </form>

        <?php if ($companies === []): ?>
            <div class="cms-excomp-empty">Keine Companies für den aktuellen Filter gefunden.</div>
        <?php else: ?>
            <div class="cms-excomp-grid">
                <?php foreach ($companies as $company): ?>
                    <?php
                    $name = trim((string) ($company->name ?? ''));
                    $name = $name !== '' ? $name : 'Company #' . (int) ($company->id ?? 0);

                    $detailUrl = trim((string) ($company->detail_url ?? ''));
                    if ($detailUrl === '') {
                        $companyId = (int) ($company->id ?? 0);
                        $detailUrl = $companyId > 0 ? ($base . '/companies/' . $companyId) : $baseUrl;
                    }

                    $industry = trim((string) ($company->industry ?? ''));
                    $location = trim((string) ($company->location_city ?? $company->city ?? ''));
                    $logo = $mediaUrl($company->logo_url ?? '');
                    $description = $companyCardExcerpt($company);
                    $partnerStatus = $partnerLabel($company);
                    $hasPartnerCornerBadge = $partnerStatus !== 'Unternehmen';
                    $topPartnerCardClass = $partnerStatus === 'Top-Partner' ? ' cms-excomp-card--top-partner' : '';
                    $partnerCornerClass = match ($partnerStatus) {
                        'Sponsor' => 'is-sponsor',
                        'Top-Partner' => 'is-top-partner',
                        'Partner' => 'is-partner',
                        default => '',
                    };
                    $linkedExperts = is_array($company->linked_experts ?? null) ? $company->linked_experts : [];
                    $linkedSpeakers = is_array($company->linked_speakers ?? null) ? $company->linked_speakers : [];
                    $relationsCount = count($linkedExperts) + count($linkedSpeakers);
                    $hasMetaRow = $location !== '' || $relationsCount > 0;
                    ?>
                    <article class="cms-excomp-card cms-excomp-card--company<?= $topPartnerCardClass ?>">
                        <?php if ($hasPartnerCornerBadge): ?>
                            <span class="cms-excomp-card__corner-badge cms-excomp-card__corner-badge--company <?= $e($partnerCornerClass) ?>" title="<?= $e($partnerStatus) ?>">
                                <?= $e($partnerStatus) ?>
                            </span>
                        <?php endif; ?>

                        <div class="cms-excomp-card__head">
                            <div class="cms-excomp-card__media">
                                <?php if ($logo !== ''): ?>
                                    <img class="cms-excomp-avatar cms-excomp-avatar--company" src="<?= $e($logo) ?>" alt="<?= $e($name) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="cms-excomp-avatar cms-excomp-avatar--company cms-excomp-avatar--fallback"><?= $e($companyInitials($company)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="cms-excomp-card__titleblock">
                                <h2 class="cms-excomp-card__name"><a href="<?= $e($detailUrl) ?>"><?= $e($name) ?></a></h2>
                            </div>
                        </div>

                        <?php if ($hasMetaRow): ?>
                            <div class="cms-excomp-card__meta">
                                <?php if ($location !== ''): ?><span><?= $e($location) ?></span><?php endif; ?>
                                <?php if ($relationsCount > 0): ?><span><?= $relationsCount ?> Verknüpfungen</span><?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($linkedExperts !== [] || $linkedSpeakers !== []): ?>
                            <div class="cms-excomp-linked">
                                <?php foreach (array_slice($linkedExperts, 0, 2) as $linkedExpert): ?>
                                    <?php
                                    if (!is_object($linkedExpert)) {
                                        continue;
                                    }
                                    $linkedExpertId = (int) ($linkedExpert->id ?? 0);
                                    $linkedExpertName = trim((string) (($linkedExpert->first_name ?? '') . ' ' . ($linkedExpert->last_name ?? '')));
                                    if ($linkedExpertName === '') {
                                        $linkedExpertName = 'Expert #' . $linkedExpertId;
                                    }
                                    $linkedExpertUrl = $linkedExpertId > 0 ? ($base . '/experts/' . $linkedExpertId) : ($base . '/experts');
                                    ?>
                                    <a class="cms-excomp-linked-item" href="<?= $e($linkedExpertUrl) ?>">👤 <?= $e($linkedExpertName) ?></a>
                                <?php endforeach; ?>

                                <?php foreach (array_slice($linkedSpeakers, 0, 2) as $linkedSpeaker): ?>
                                    <?php
                                    if (!is_object($linkedSpeaker)) {
                                        continue;
                                    }
                                    $linkedSpeakerName = trim((string) ($linkedSpeaker->display_name ?? ''));
                                    if ($linkedSpeakerName === '') {
                                        $linkedSpeakerName = 'Speaker #' . (int) ($linkedSpeaker->id ?? 0);
                                    }
                                    $linkedSpeakerSlug = trim((string) ($linkedSpeaker->slug ?? ''));
                                    $linkedSpeakerUrl = $linkedSpeakerSlug !== ''
                                        ? ($base . '/speakers/' . rawurlencode($linkedSpeakerSlug))
                                        : ($base . '/speakers');
                                    ?>
                                    <a class="cms-excomp-linked-item" href="<?= $e($linkedSpeakerUrl) ?>">🎤 <?= $e($linkedSpeakerName) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($description !== ''): ?><p class="cms-excomp-card__excerpt"><?= $e($description) ?></p><?php endif; ?>

                        <footer class="cms-excomp-card__footer">
                            <span class="cms-excomp-card__badge"><?= $industry !== '' ? $e($industry) : 'Company' ?></span>
                            <a class="cms-excomp-card__more" href="<?= $e($detailUrl) ?>">Mehr Infos …</a>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="cms-excomp-pagination" aria-label="Companies-Seiten">
                    <a class="cms-excomp-pagination__link<?= $currentPage <= 1 ? ' is-disabled' : '' ?>" href="<?= $e($paginationUrl(max(1, $currentPage - 1))) ?>" aria-disabled="<?= $currentPage <= 1 ? 'true' : 'false' ?>">Zurück</a>
                    <div class="cms-excomp-pagination__pages">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 2): ?>
                                <a class="cms-excomp-pagination__page<?= $i === $currentPage ? ' is-active' : '' ?>" href="<?= $e($paginationUrl($i)) ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php elseif ($i === $currentPage - 3 || $i === $currentPage + 3): ?>
                                <span class="cms-excomp-pagination__ellipsis" aria-hidden="true">…</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <a class="cms-excomp-pagination__link<?= $currentPage >= $totalPages ? ' is-disabled' : '' ?>" href="<?= $e($paginationUrl(min($totalPages, $currentPage + 1))) ?>" aria-disabled="<?= $currentPage >= $totalPages ? 'true' : 'false' ?>">Weiter</a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

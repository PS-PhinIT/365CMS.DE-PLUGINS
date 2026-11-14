<?php
/**
 * Public Archive Template – Experts.
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

$experts = array_values(array_filter(array_map(
    static fn($item): ?object => is_object($item) ? $item : (is_array($item) ? (object) $item : null),
    (array) ($experts ?? [])
)));

$filters = is_array($filters ?? null) ? $filters : [];
$q = trim((string) ($filters['q'] ?? ''));
$city = trim((string) ($filters['city'] ?? ''));
$settings = is_array($settings ?? null) ? $settings : [];
$pagination = is_array($pagination ?? null)
    ? $pagination
    : ['current' => 1, 'total' => count($experts), 'per_page' => 15, 'total_pages' => 1];

$base = rtrim((string) SITE_URL, '/');
$baseUrl = $base . '/experts';
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

$expertsHeaderTextEnabled = $setting('experts_header_text_enabled', '1') !== '0';
$expertsHeaderKickerEnabled = $setting('experts_header_kicker_enabled', '1') !== '0';
$expertsHeaderTitleEnabled = $setting('experts_header_title_enabled', '1') !== '0';
$expertsHeaderDescriptionEnabled = $setting('experts_header_description_enabled', '1') !== '0';
$expertsSearchPlaceholderEnabled = $setting('experts_search_placeholder_enabled', '1') !== '0';
$expertsHeaderHasTextContent = $expertsHeaderTextEnabled && ($expertsHeaderKickerEnabled || $expertsHeaderTitleEnabled || $expertsHeaderDescriptionEnabled);
$expertsHeaderKickerOnly = $expertsHeaderTextEnabled && $expertsHeaderKickerEnabled && !$expertsHeaderTitleEnabled && !$expertsHeaderDescriptionEnabled;
$isAllowedHeaderUrl = static function (string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    return str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1;
};
$expertsHeaderButtons = [];
for ($buttonIndex = 1; $buttonIndex <= 3; $buttonIndex++) {
    $text = trim($setting('experts_header_btn_' . $buttonIndex . '_text', ''));
    $url = trim($setting('experts_header_btn_' . $buttonIndex . '_url', ''));
    if ($text === '' || !$isAllowedHeaderUrl($url)) {
        continue;
    }

    $expertsHeaderButtons[] = ['text' => $text, 'url' => $url];
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

$expertName = static function (object $expert): string {
    $first = trim((string) ($expert->first_name ?? ''));
    $last = trim((string) ($expert->last_name ?? ''));
    $full = trim($first . ' ' . $last);
    return $full !== '' ? $full : 'Expert #' . (int) ($expert->id ?? 0);
};

$expertInitials = static function (object $expert): string {
    $first = trim((string) ($expert->first_name ?? ''));
    $last = trim((string) ($expert->last_name ?? ''));
    $letters = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    return $letters !== '' ? $letters : 'EX';
};

$expertCardExcerpt = static function (object $expert): string {
    $raw = trim((string) ($expert->biography ?? ''));
    if ($raw === '') {
        return '';
    }

    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }

    return function_exists('mb_substr')
        ? (string) mb_substr($text, 0, 180, 'UTF-8')
        : substr($text, 0, 180);
};
?>

<?= $renderDesignVars($designVars) ?>

<main class="cms-excomp-public cms-excomp-archive cms-excomp-archive--experts">
    <div class="cms-excomp-container">
        <section class="cms-excomp-hero cms-excomp-hero--compact cms-excomp-hero--experts<?= $expertsHeaderButtons !== [] ? ' cms-excomp-hero--has-actions' : '' ?><?= !$expertsHeaderHasTextContent ? ' cms-excomp-hero--no-text' : '' ?><?= $expertsHeaderKickerOnly ? ' cms-excomp-hero--kicker-only' : '' ?>" aria-label="Experts">
            <?php if ($expertsHeaderTextEnabled): ?>
                <?php if ($expertsHeaderKickerEnabled): ?><span class="cms-excomp-kicker"><?= $e($setting('experts_archive_kicker', '365 Network · Expert Directory')) ?></span><?php endif; ?>
                <?php if ($expertsHeaderTitleEnabled): ?><h1><?= $e($setting('experts_archive_title', 'Experts')) ?></h1><?php endif; ?>
                <?php if ($expertsHeaderDescriptionEnabled): ?><p class="cms-excomp-hero__description"><?= $e($setting('experts_archive_description', 'Echte Profile mit Skills, Verfügbarkeit und direkten Verknüpfungen zu Company & Speaker.')) ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($expertsHeaderButtons !== []): ?>
                <div class="cms-excomp-hero__actions" aria-label="Header-Aktionen">
                    <?php foreach ($expertsHeaderButtons as $button): ?>
                        <a class="cms-excomp-hero__action" href="<?= $e((string) $button['url']) ?>"><?= $e((string) $button['text']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <form method="GET" action="<?= $e($baseUrl) ?>" class="cms-excomp-search cms-excomp-search--header-card" role="search" aria-label="Experts Suche">
            <div class="cms-excomp-search__field">
                <input type="search" name="q" value="<?= $e($q) ?>" placeholder="<?= $expertsSearchPlaceholderEnabled ? $e($setting('experts_search_placeholder', 'Name, Firma, Position, Skills …')) : '' ?>" aria-label="Experts suchen">
            </div>
            <div class="cms-excomp-search__field">
                <input type="text" name="city" value="<?= $e($city) ?>" placeholder="Stadt …" aria-label="Stadt">
            </div>
            <div class="cms-excomp-search__actions">
                <button type="submit"><?= $e($setting('search_button_label', 'Suchen')) ?></button>
                <?php if ($q !== '' || $city !== ''): ?><a href="<?= $e($baseUrl) ?>"><?= $e($setting('reset_button_label', 'Zurücksetzen')) ?></a><?php endif; ?>
            </div>
        </form>

        <?php if ($experts === []): ?>
            <div class="cms-excomp-empty">Keine Experts für den aktuellen Filter gefunden.</div>
        <?php else: ?>
            <div class="cms-excomp-grid">
                <?php foreach ($experts as $expert): ?>
                    <?php
                    $name = $expertName($expert);
                    $detailUrl = trim((string) ($expert->detail_url ?? ''));
                    if ($detailUrl === '') {
                        $expertId = (int) ($expert->id ?? 0);
                        $detailUrl = $expertId > 0 ? ($base . '/experts/' . $expertId) : $baseUrl;
                    }

                    $position = trim((string) ($expert->position ?? ''));
                    $company = trim((string) ($expert->company ?? ''));
                    $location = trim((string) ($expert->location_city ?? $expert->city ?? ''));
                    $awards = trim((string) ($expert->awards ?? ''));
                    $isMvp = $awards !== '' && stripos($awards, 'mvp') !== false;
                    $hasAwardBadge = $awards !== '';
                    $photo = $mediaUrl($expert->photo_url ?? '');
                    $bio = $expertCardExcerpt($expert);
                    $linkedSpeaker = is_object($expert->linked_speaker ?? null) ? $expert->linked_speaker : null;
                    $linkedCompany = is_object($expert->linked_company ?? null) ? $expert->linked_company : null;
                    ?>
                    <article class="cms-excomp-card cms-excomp-card--expert">
                        <?php if ($hasAwardBadge): ?>
                            <span class="cms-excomp-card__corner-badge <?= $isMvp ? 'is-mvp' : 'is-award' ?>" title="<?= $e($awards) ?>">
                                <?= $e($isMvp ? 'MVP' : 'Auszeichnung') ?>
                            </span>
                        <?php endif; ?>

                        <div class="cms-excomp-card__head">
                            <div class="cms-excomp-card__media">
                                <?php if ($photo !== ''): ?>
                                    <img class="cms-excomp-avatar" src="<?= $e($photo) ?>" alt="<?= $e($name) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="cms-excomp-avatar cms-excomp-avatar--fallback"><?= $e($expertInitials($expert)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="cms-excomp-card__titleblock">
                                <h2 class="cms-excomp-card__name"><a href="<?= $e($detailUrl) ?>"><?= $e($name) ?></a></h2>
                                <?php if ($position !== ''): ?><p class="cms-excomp-card__meta-line"><?= $e($position) ?></p><?php endif; ?>
                            </div>
                        </div>

                        <?php if ($location !== ''): ?>
                            <div class="cms-excomp-card__meta">
                                <span><?= $e($location) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($linkedSpeaker !== null || $linkedCompany !== null): ?>
                            <div class="cms-excomp-linked">
                                <?php if ($linkedSpeaker !== null): ?>
                                    <?php
                                    $speakerName = trim((string) ($linkedSpeaker->display_name ?? ''));
                                    $speakerSlug = trim((string) ($linkedSpeaker->slug ?? ''));
                                    $speakerUrl = $speakerSlug !== '' ? ($base . '/speakers/' . rawurlencode($speakerSlug)) : ($base . '/speakers');
                                    ?>
                                    <a class="cms-excomp-linked-item" href="<?= $e($speakerUrl) ?>">🎤 <?= $e($speakerName !== '' ? $speakerName : 'Speaker') ?></a>
                                <?php endif; ?>
                                <?php if ($linkedCompany !== null): ?>
                                    <?php
                                    $linkedCompanyId = (int) ($linkedCompany->id ?? 0);
                                    $companyUrl = $linkedCompanyId > 0 ? ($base . '/companies/' . $linkedCompanyId) : ($base . '/companies');
                                    $companyName = trim((string) ($linkedCompany->name ?? ''));
                                    ?>
                                    <a class="cms-excomp-linked-item" href="<?= $e($companyUrl) ?>">🏢 <?= $e($companyName !== '' ? $companyName : 'Company') ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($bio !== ''): ?><p class="cms-excomp-card__excerpt"><?= $e($bio) ?></p><?php endif; ?>

                        <footer class="cms-excomp-card__footer">
                            <span class="cms-excomp-card__badge"><?= $company !== '' ? $e($company) : 'Firma nicht hinterlegt' ?></span>
                            <a class="cms-excomp-card__more" href="<?= $e($detailUrl) ?>">Mehr Infos …</a>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="cms-excomp-pagination" aria-label="Experts-Seiten">
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

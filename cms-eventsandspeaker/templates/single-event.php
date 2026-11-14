<?php
/** @var object|null $event */
/** @var array<int, object> $speakers */
/** @var object|null $linkedCompany */
/** @var object|null $linkedExpert */
/** @var array<string, string> $settings */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
$base = rtrim((string) SITE_URL, '/');
$renderEditor = static function (mixed $json, mixed $fallback): string {
    $json = (string) $json;
    if ($json !== '' && class_exists('CMS\\Services\\EditorJsRenderer')) {
        $rendered = (string) CMS\Services\EditorJsRenderer::getInstance()->render($json);
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8')));
        $hasMediaBlocks = preg_match('/<(img|video|audio|iframe|table|ul|ol|blockquote|pre|h[1-6])\b/i', $rendered) === 1;
        if ($plain !== '' || $hasMediaBlocks) {
            return $rendered;
        }
    }
    $fallback = trim((string) $fallback);
    return $fallback !== '' ? '<div class="cms-events-copy">' . nl2br(htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8')) . '</div>' : '';
};
$badges = array_filter(array_map('trim', explode(',', (string) (($event->categories ?? '') ?: ($event->category ?? '')))));
$tags = array_filter(array_map('trim', explode(',', (string) ($event->tags ?? ''))));

$dateMonth = 'DATUM';
$dateDay = '--';
$dateYear = '----';

$startDate = null;
$startDateRaw = trim((string) ($event->start_date ?? ''));
if ($startDateRaw !== '') {
    try {
        $startDate = new DateTimeImmutable($startDateRaw);
    } catch (\Throwable $e) {
        $startDate = null;
    }
}

if (!$startDate instanceof DateTimeImmutable) {
    $dateLabelRaw = (string) ($event->date_label ?? '');
    if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/u', $dateLabelRaw, $dateParts) === 1) {
        $normalizedDate = sprintf('%04d-%02d-%02d', (int) $dateParts[3], (int) $dateParts[2], (int) $dateParts[1]);
        try {
            $startDate = new DateTimeImmutable($normalizedDate);
        } catch (\Throwable $e) {
            $startDate = null;
        }
    }
}

$endDate = null;
$endDateRaw = trim((string) ($event->end_date ?? ''));
if ($endDateRaw !== '') {
    try {
        $endDate = new DateTimeImmutable($endDateRaw);
    } catch (\Throwable $e) {
        $endDate = null;
    }
}

$dateLabelRaw = trim((string) ($event->date_label ?? ''));
$endDateLabelRaw = trim((string) ($event->end_date_label ?? ''));
if (!$endDate instanceof DateTimeImmutable && $endDateLabelRaw !== '' && preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/u', $endDateLabelRaw, $endDateParts) === 1) {
    $normalizedEndDate = sprintf('%04d-%02d-%02d', (int) $endDateParts[3], (int) $endDateParts[2], (int) $endDateParts[1]);
    try {
        $endDate = new DateTimeImmutable($normalizedEndDate);
    } catch (\Throwable $e) {
        $endDate = null;
    }
}

if ($startDate instanceof DateTimeImmutable) {
    $months = [
        1 => 'JAN', 2 => 'FEB', 3 => 'MÄR', 4 => 'APR', 5 => 'MAI', 6 => 'JUN',
        7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OKT', 11 => 'NOV', 12 => 'DEZ',
    ];
    $monthIndex = (int) $startDate->format('n');
    $dateMonth = $months[$monthIndex] ?? strtoupper((string) $startDate->format('M'));
    $dateDay = (string) $startDate->format('d');
    $dateYear = (string) $startDate->format('Y');
}

$metaInfo = [];
$dateMeta = $dateLabelRaw;
$isSameDateRange = $startDate instanceof DateTimeImmutable
    && $endDate instanceof DateTimeImmutable
    && $startDate->format('Y-m-d') === $endDate->format('Y-m-d');
if ($dateMeta !== '' && $endDateLabelRaw !== '' && $endDateLabelRaw !== $dateMeta && !$isSameDateRange) {
    $dateMeta .= ' – ' . $endDateLabelRaw;
}
if ($dateMeta !== '') {
    $metaInfo[] = $dateMeta;
}
if ((string) ($event->location ?? '') !== '') {
    $metaInfo[] = (string) $event->location;
}
if ((string) ($event->organizer ?? '') !== '') {
    $metaInfo[] = (string) $event->organizer;
}
if ((string) ($event->attendance_mode ?? '') !== '') {
    $metaInfo[] = (string) $event->attendance_mode;
}

$formatBadge = trim((string) (($event->event_format ?? '') ?: ($event->event_type ?? '')));
$aiBadgeSource = implode(' | ', array_filter(array_merge(
    $tags,
    $badges,
    [
        (string) ($event->title ?? ''),
        (string) ($event->excerpt ?? ''),
    ]
), static fn (mixed $value): bool => trim((string) $value) !== ''));
$hasAiBadge = $aiBadgeSource !== ''
    && preg_match('/\b(ai|genai|artificial intelligence|künstliche intelligenz)\b/ui', $aiBadgeSource) === 1;

$headerCategories = $badges;
if ($formatBadge !== '') {
    $headerCategories[] = $formatBadge;
}
if ($hasAiBadge) {
    $headerCategories[] = 'AI';
}
$headerCategories = array_values(array_unique(array_filter(array_map('trim', $headerCategories), static fn (string $badge): bool => $badge !== '')));
$headerCategories = array_slice($headerCategories, 0, 3);
$targetAudienceBadge = trim((string) ($event->target_audience ?? ''));
$hasHeaderImage = !empty($event->image_url);
$headerImageBgColor = '';
$rawHeaderImageBgColor = trim((string) ($event->image_bg_color ?? ''));
if (preg_match('/^#[0-9a-fA-F]{6}$/', $rawHeaderImageBgColor) === 1) {
    $headerImageBgColor = strtolower($rawHeaderImageBgColor);
}
$headerInlineStyle = $headerImageBgColor !== ''
    ? ' style="--cms-events-event-image-bg:' . htmlspecialchars($headerImageBgColor, ENT_QUOTES, 'UTF-8') . '"'
    : '';

$sidebarPrimaryLink = trim((string) (($event->website ?? '') ?: ($event->registration_url ?? '') ?: ($event->ticket_url ?? '')));
$sidebarPrimaryLabel = 'Zur Eventseite';
$sidebarContactLink = '';
$sidebarContactEmail = trim((string) (($event->contact_email ?? '') ?: ($event->contact_mail ?? '') ?: ($event->email ?? '')));
if ($sidebarContactEmail !== '' && filter_var($sidebarContactEmail, FILTER_VALIDATE_EMAIL)) {
    $sidebarContactLink = 'mailto:' . $sidebarContactEmail;
}
$sidebarSocialLinks = [];
$sidebarSocialMap = [
    ['field' => 'linkedin_url', 'label' => 'LinkedIn', 'icon' => 'in'],
    ['field' => 'x_url', 'label' => 'X', 'icon' => 'X'],
    ['field' => 'youtube_url', 'label' => 'YouTube', 'icon' => '▶'],
    ['field' => 'facebook_url', 'label' => 'Facebook', 'icon' => 'f'],
    ['field' => 'instagram_url', 'label' => 'Instagram', 'icon' => '◎'],
];
foreach ($sidebarSocialMap as $sidebarSocial) {
    $field = (string) ($sidebarSocial['field'] ?? '');
    if ($field === '' || empty($event->{$field})) {
        continue;
    }
    $sidebarSocialLinks[] = [
        'url' => (string) $event->{$field},
        'label' => (string) ($sidebarSocial['label'] ?? $field),
        'icon' => (string) ($sidebarSocial['icon'] ?? '↗'),
    ];
}
$hasSidebarLinks = $sidebarPrimaryLink !== '' || $sidebarContactLink !== '' || $sidebarSocialLinks !== [];

$addressRows = [];
$addressLocation = trim((string) ($event->location ?? ''));
if ($addressLocation !== '') {
    $addressRows[] = ['label' => 'Ort', 'value' => $addressLocation];
}
$addressVenue = trim((string) ($event->venue_name ?? ''));
if ($addressVenue !== '') {
    $addressRows[] = ['label' => 'Venue', 'value' => $addressVenue];
}
$addressStreet = trim((string) ($event->street ?? ''));
if ($addressStreet !== '') {
    $addressRows[] = ['label' => 'Straße', 'value' => $addressStreet];
}
$addressPostalCode = trim((string) ($event->postal_code ?? ''));
if ($addressPostalCode !== '') {
    $addressRows[] = ['label' => 'PLZ', 'value' => $addressPostalCode];
}
$addressCountry = trim((string) ($event->country ?? ''));
if ($addressCountry !== '') {
    $addressRows[] = ['label' => 'Land', 'value' => $addressCountry];
}

$detailRows = [];
$organizerValues = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($event->organizer ?? '')) ?: []), static fn(string $value): bool => $value !== ''));
$organizerValues = array_slice(array_values(array_unique($organizerValues)), 0, 8);
$detailDateLabel = '';
$detailDateLabelRaw = $dateLabelRaw;
$detailEndDateLabelRaw = $endDateLabelRaw;

if ($startDate instanceof DateTimeImmutable && $endDate instanceof DateTimeImmutable) {
    $detailDateLabel = $startDate->format('Y-m-d') === $endDate->format('Y-m-d')
        ? (string) $startDate->format('d.m.Y')
        : (string) $startDate->format('d.m.Y') . ' – ' . (string) $endDate->format('d.m.Y');
} elseif ($startDate instanceof DateTimeImmutable) {
    $detailDateLabel = (string) $startDate->format('d.m.Y');
} elseif ($detailDateLabelRaw !== '') {
    $detailDateLabel = $detailDateLabelRaw;
    if ($detailEndDateLabelRaw !== '' && $detailDateLabelRaw !== $detailEndDateLabelRaw) {
        $detailDateLabel .= ' – ' . $detailEndDateLabelRaw;
    }
}

if ($detailDateLabel !== '') {
    $detailRows[] = ['label' => 'Datum', 'value' => $detailDateLabel, 'class' => 'is-date'];
}

$detailStartTime = trim((string) ($event->start_time ?? ''));
$detailEndTime = trim((string) ($event->end_time ?? ''));
$detailTimeLabel = '';
if ($detailStartTime !== '') {
    $detailTimeLabel = $detailStartTime . ($detailEndTime !== '' ? ' – ' . $detailEndTime : '');
} elseif ($detailEndTime !== '') {
    $detailTimeLabel = $detailEndTime;
}
if ($detailTimeLabel !== '') {
    $detailRows[] = ['label' => 'Uhrzeit', 'value' => $detailTimeLabel];
}

if ($tags !== []) {
    $detailRows[] = [
        'label' => count($tags) > 1 ? 'Tags' : 'Tag',
        'value' => implode(', ', array_slice($tags, 0, 5)),
    ];
}

$detailFormat = trim((string) (($event->event_format ?? '') ?: ($event->event_type ?? '')));
if ($detailFormat !== '') {
    $detailRows[] = ['label' => 'Format', 'value' => $detailFormat];
}

if (!empty($event->attendance_mode)) {
    $detailRows[] = ['label' => 'Durchführung', 'value' => (string) $event->attendance_mode];
}
if ($organizerValues !== []) {
    $detailRows[] = [
        'label' => 'Veranstalter',
        'value' => implode(', ', $organizerValues),
        'class' => 'is-organizer',
    ];
} elseif (!empty($event->organizer)) {
    $detailRows[] = ['label' => 'Veranstalter', 'value' => (string) $event->organizer];
}
if (!empty($event->difficulty_level)) {
    $detailRows[] = ['label' => 'Level', 'value' => (string) $event->difficulty_level];
}
if (!empty($event->language)) {
    $detailRows[] = ['label' => 'Sprache', 'value' => (string) $event->language];
}
$detailPriceInfo = '';
$detailPriceMin = isset($event->price_min) ? (float) $event->price_min : 0.0;
$detailPriceMax = isset($event->price_max) ? (float) $event->price_max : 0.0;
$detailCurrency = trim((string) ($event->currency ?? 'EUR'));
$detailCurrencyCode = function_exists('mb_strtoupper') ? mb_strtoupper($detailCurrency, 'UTF-8') : strtoupper($detailCurrency);
$detailCurrencyPrefix = $detailCurrencyCode === 'EUR' ? '€' : ($detailCurrencyCode !== '' ? $detailCurrencyCode . ' ' : '');

if ($detailPriceMin > 0 || $detailPriceMax > 0) {
    if ($detailPriceMin > 0 && $detailPriceMax > 0) {
        if (abs($detailPriceMin - $detailPriceMax) < 0.0001) {
            $detailPriceInfo = $detailCurrencyPrefix . number_format($detailPriceMin, 2, ',', '.');
        } else {
            $detailPriceInfo = $detailCurrencyPrefix . number_format($detailPriceMin, 2, ',', '.')
                . ' – '
                . $detailCurrencyPrefix . number_format($detailPriceMax, 2, ',', '.');
        }
    } elseif ($detailPriceMin > 0) {
        $detailPriceInfo = 'ab ' . $detailCurrencyPrefix . number_format($detailPriceMin, 2, ',', '.');
    } else {
        $detailPriceInfo = 'bis ' . $detailCurrencyPrefix . number_format($detailPriceMax, 2, ',', '.');
    }
} else {
    $detailPriceLabel = trim((string) ($event->price ?? ''));
    if ($detailPriceLabel !== '' && preg_match('/\d|€|eur|usd|chf/iu', $detailPriceLabel) === 1) {
        $detailPriceInfo = $detailPriceLabel;
    }
}

$detailCostStatusRaw = trim((string) ($event->price_class ?? ''));
$detailCostStatus = '';
if ($detailCostStatusRaw !== '' && mb_strtolower($detailCostStatusRaw, 'UTF-8') !== 'bitte wählen') {
    $detailCostStatus = $detailCostStatusRaw;
}

if ($detailCostStatus === '') {
    $legacyPriceLabel = trim((string) ($event->price ?? ''));
    $legacyPriceLower = mb_strtolower($legacyPriceLabel, 'UTF-8');

    if ($legacyPriceLabel !== '') {
        if (preg_match('/\b(kostenfrei|kostenlos|free|frei)\b/ui', $legacyPriceLabel) === 1) {
            $detailCostStatus = 'Kostenfrei';
        } elseif (preg_match('/\b(kostenpflichtig|paid|bezahl|ticket|eintritt|gebühr)\b/ui', $legacyPriceLabel) === 1) {
            $detailCostStatus = 'Kostenpflichtig';
        } elseif (str_contains($legacyPriceLower, 'auf einladung')) {
            $detailCostStatus = 'Auf Einladung';
        } elseif (str_contains($legacyPriceLower, 'auf anfrage')) {
            $detailCostStatus = 'Auf Anfrage';
        } elseif (str_contains($legacyPriceLower, 'sponsorenfinanziert')) {
            $detailCostStatus = 'Sponsorenfinanziert';
        }
    }
}

if ($detailCostStatus === '') {
    $priceMin = isset($event->price_min) ? (float) $event->price_min : 0.0;
    $priceMax = isset($event->price_max) ? (float) $event->price_max : 0.0;
    $legacyPriceLabel = trim((string) ($event->price ?? ''));
    if ($priceMin > 0 || $priceMax > 0 || preg_match('/\d/', $legacyPriceLabel) === 1) {
        $detailCostStatus = 'Kostenpflichtig';
    }
}

if ($detailCostStatus !== '') {
    $detailCostStatusLower = function_exists('mb_strtolower')
        ? mb_strtolower($detailCostStatus, 'UTF-8')
        : strtolower($detailCostStatus);
    $detailCostClass = '';
    if (
        str_contains($detailCostStatusLower, 'kostenfrei')
        || str_contains($detailCostStatusLower, 'kostenlos')
        || $detailCostStatusLower === 'free'
        || $detailCostStatusLower === 'frei'
    ) {
        $detailCostClass = 'is-cost-free';
    } elseif (
        str_contains($detailCostStatusLower, 'kostenpflichtig')
        || str_contains($detailCostStatusLower, 'paid')
        || str_contains($detailCostStatusLower, 'bezahl')
        || str_contains($detailCostStatusLower, 'eintritt')
        || str_contains($detailCostStatusLower, 'gebühr')
    ) {
        $detailCostClass = 'is-cost-paid';
    }

    $detailRows[] = [
        'label' => 'Kosten',
        'value' => $detailCostStatus,
        'subvalue' => $detailCostClass === 'is-cost-paid' ? $detailPriceInfo : '',
        'class' => $detailCostClass,
    ];
}
if (!empty($event->capacity)) {
    $detailRows[] = ['label' => 'Kapazität', 'value' => (int) $event->capacity . ' Personen'];
}
?>
<main class="cms-events-public cms-events-detail">
    <div class="cms-events-container">
        <nav class="cms-events-breadcrumb"><a href="<?= htmlspecialchars($base . '/events', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($settings['detail_back_events_label'] ?? 'Events'), ENT_QUOTES, 'UTF-8') ?></a><span>/</span><span><?= htmlspecialchars((string) ($event->title ?? ''), ENT_QUOTES, 'UTF-8') ?></span></nav>
        <article class="cms-events-detail-layout">
            <header class="cms-events-detail-header<?= $hasHeaderImage ? ' cms-events-detail-header--has-image' : ' cms-events-detail-header--no-image' ?>"<?= $headerInlineStyle ?>>
                <div class="cms-events-detail-header__content">
                    <div class="cms-events-detail-header__intro">
                        <div class="cms-events-date-badge" aria-label="Eventdatum">
                            <span class="cms-events-date-badge__month"><?= htmlspecialchars($dateMonth, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="cms-events-date-badge__day"><?= htmlspecialchars($dateDay, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="cms-events-date-badge__year"><?= htmlspecialchars($dateYear, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="cms-events-detail-header__text">
                            <h1><?= htmlspecialchars((string) ($event->title ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                            <?php if ($metaInfo !== []): ?><div class="cms-events-detail-header__meta"><?php foreach (array_slice($metaInfo, 0, 5) as $meta): ?><span><?= htmlspecialchars((string) $meta, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($event->excerpt)): ?><p class="cms-events-detail-header__lead"><?= htmlspecialchars((string) $event->excerpt, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </div>
                <?php if ($hasHeaderImage): ?><figure class="cms-events-detail-header__media"><img src="<?= htmlspecialchars((string) $event->image_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($event->image_alt ?? $event->title ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="eager"></figure><?php endif; ?>
            </header>
            <div class="cms-events-detail-layout__body">
                <section class="cms-events-detail-main">
                    <?= $renderEditor($event->description_json ?? '', $event->description ?? '') ?>
                </section>
                <aside class="cms-events-sidebar">
                    <div class="cms-events-sidebar-stack">
                        <?php if ($headerCategories !== [] || $targetAudienceBadge !== ''): ?><div class="cms-events-sidecard cms-events-sidecard--categories"><div class="cms-events-sidecard__badges"><?php foreach ($headerCategories as $category): ?><span><?= htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?><?php if ($targetAudienceBadge !== ''): ?><span class="is-audience"><?= htmlspecialchars($targetAudienceBadge, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div></div><?php endif; ?>
                        <div class="cms-events-sidecard cms-events-sidecard--details<?= $hasSidebarLinks ? ' cms-events-sidecard--details-has-links' : '' ?>">
                            <dl class="cms-events-sidecard__meta">
                                <?php foreach ($detailRows as $detailRow): ?>
                                    <?php $rowClass = trim((string) ($detailRow['class'] ?? '')); ?>
                                    <?php $rowValue = (string) ($detailRow['value'] ?? ''); ?>
                                    <?php $rowSubvalue = trim((string) ($detailRow['subvalue'] ?? '')); ?>
                                    <div class="cms-events-sidecard__meta-row<?= $rowClass !== '' ? ' ' . htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') : '' ?>">
                                        <dt><?= htmlspecialchars((string) ($detailRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                                        <dd>
                                            <span class="cms-events-sidecard__meta-value-main"><?= htmlspecialchars($rowValue, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($rowSubvalue !== ''): ?><small class="cms-events-sidecard__meta-value-sub"><?= htmlspecialchars($rowSubvalue, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                        </dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                        <?php if ($hasSidebarLinks): ?><div class="cms-events-sidecard cms-events-sidecard--links"><?php if ($sidebarPrimaryLink !== '' || $sidebarContactLink !== ''): ?><div class="cms-events-side-actions cms-events-side-actions--primary-row"><?php if ($sidebarPrimaryLink !== ''): ?><a class="cms-events-side-action cms-events-side-action--primary" href="<?= htmlspecialchars((string) $sidebarPrimaryLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?>"><span class="cms-events-side-action__icon">🌐</span><span class="cms-events-side-action__label"><?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?></span></a><?php endif; ?><?php if ($sidebarContactLink !== ''): ?><a class="cms-events-side-action cms-events-side-action--primary" href="<?= htmlspecialchars($sidebarContactLink, ENT_QUOTES, 'UTF-8') ?>" aria-label="Kontakt" title="Kontakt"><span class="cms-events-side-action__icon">✉</span><span class="cms-events-side-action__label">Kontakt</span></a><?php endif; ?></div><?php endif; ?><?php if ($sidebarSocialLinks !== []): ?><div class="cms-events-side-actions cms-events-side-actions--social"><?php foreach ($sidebarSocialLinks as $sidebarSocialLink): ?><a class="cms-events-side-action cms-events-side-action--icon-only" href="<?= htmlspecialchars((string) $sidebarSocialLink['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars((string) $sidebarSocialLink['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $sidebarSocialLink['label'], ENT_QUOTES, 'UTF-8') ?>"><span class="cms-events-side-action__icon"><?= htmlspecialchars((string) $sidebarSocialLink['icon'], ENT_QUOTES, 'UTF-8') ?></span></a><?php endforeach; ?></div><?php endif; ?></div><?php endif; ?>
                    </div>
                    <?php if ($addressRows !== []): ?><section class="cms-events-address-block" aria-label="Adresse"><ul class="cms-events-address-block__list"><?php foreach ($addressRows as $addressRow): ?><li><span><?= htmlspecialchars((string) ($addressRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars((string) ($addressRow['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></li><?php endforeach; ?></ul></section><?php endif; ?>
                    <?php if (!empty($linkedCompany) || !empty($linkedExpert)): ?><div class="cms-events-sidecard"><h2>365CMS-Verknüpfung</h2><div class="cms-events-socials">
                        <?php if (!empty($linkedCompany)): ?><a class="cms-events-btn" href="<?= htmlspecialchars($base . '/companies/' . (int) $linkedCompany->id, ENT_QUOTES, 'UTF-8') ?>">🏢 <?= htmlspecialchars((string) ($linkedCompany->name ?? 'Firma'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
                        <?php if (!empty($linkedExpert)): ?><a class="cms-events-btn" href="<?= htmlspecialchars($base . '/experts/' . (int) $linkedExpert->id, ENT_QUOTES, 'UTF-8') ?>">👤 <?= htmlspecialchars(trim((string) ($linkedExpert->first_name ?? '') . ' ' . (string) ($linkedExpert->last_name ?? '')) ?: 'Expert', ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
                    </div></div><?php endif; ?>
                </aside>
            </div>
            <section class="cms-events-detail-main cms-events-detail-main--fullwidth">
                <section class="cms-events-section cms-events-section--fullwidth">
                    <h2><?= htmlspecialchars((string) ($settings['detail_speakers_heading'] ?? 'Speaker & Themen'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($speakers === []): ?>
                        <p><?= htmlspecialchars((string) ($settings['detail_no_speakers_text'] ?? 'Für dieses Event sind noch keine Speaker verknüpft.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <div class="cms-speaker-list">
                            <?php foreach ($speakers as $speaker): ?>
                                <?php
                                $speakerAvatarUrl = trim((string) ($speaker->avatar_url ?? ''));
                                $speakerAvatarAlt = trim((string) (($speaker->avatar_alt ?? '') ?: ($speaker->display_name ?? 'Speaker')));
                                ?>
                                <a class="cms-speaker-row" href="<?= htmlspecialchars($base . '/speakers/' . rawurlencode((string) $speaker->slug), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($speakerAvatarUrl !== ''): ?>
                                        <img class="cms-speaker-photo cms-speaker-photo--event" src="<?= htmlspecialchars($speakerAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($speakerAvatarAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="cms-speaker-avatar"><?= htmlspecialchars(strtoupper(substr((string) ($speaker->display_name ?? 'S'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <span><strong><?= htmlspecialchars((string) $speaker->display_name, ENT_QUOTES, 'UTF-8') ?></strong><?php if (!empty($speaker->relation_topic) || !empty($speaker->topic)): ?><small><?= htmlspecialchars((string) ($speaker->relation_topic ?: $speaker->topic), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        </article>
    </div>
</main>

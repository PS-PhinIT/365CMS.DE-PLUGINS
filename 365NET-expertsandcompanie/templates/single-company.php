<?php
/**
 * Public Detail Template – Company.
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

$company = is_object($company ?? null) ? $company : null;
if ($company === null) {
    return;
}
$settings = is_array($settings ?? null) ? $settings : [];

$base = rtrim((string) SITE_URL, '/');
$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$mediaUrl = static fn(mixed $value): string => class_exists('CMS_365NET_Experts_And_Companie') ? CMS_365NET_Experts_And_Companie::normalizeMediaUrl((string) $value) : trim((string) $value);

$setting = static function (string $key, string $default) use ($settings): string {
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

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

$renderEditor = static function (mixed $json, mixed $fallback): string {
    $json = (string) $json;
    if ($json !== '' && class_exists('CMS\\Services\\EditorJsRenderer')) {
        $rendered = (string) CMS\Services\EditorJsRenderer::getInstance()->render($json);
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $hasMediaBlocks = preg_match('/<(img|video|audio|iframe|table|ul|ol|blockquote|pre|h[1-6])\b/i', $rendered) === 1;
        if ($plain !== '' || $hasMediaBlocks) {
            return $rendered;
        }
    }

    $fallback = trim((string) $fallback);
    return $fallback !== ''
        ? '<div class="cms-excomp-copy">' . nl2br(htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8')) . '</div>'
        : '';
};

$name = trim((string) ($company->name ?? ''));
$name = $name !== '' ? $name : ('Company #' . (int) ($company->id ?? 0));
$industry = trim((string) ($company->industry ?? ''));
$city = trim((string) ($company->location_city ?? $company->city ?? ''));
$country = trim((string) ($company->country ?? ''));
$website = trim((string) ($company->website ?? ''));
$email = trim((string) ($company->email ?? ''));
$phone = trim((string) ($company->phone ?? ''));
$companySize = trim((string) ($company->company_size ?? ''));
$logoUrl = $mediaUrl($company->logo_url ?? '');

$partnerLabel = 'Unternehmen';
if ((int) ($company->is_sponsor ?? 0) === 1) {
    $partnerLabel = 'Sponsor';
} elseif ((int) ($company->is_top_partner ?? 0) === 1) {
    $partnerLabel = 'Top-Partner';
} elseif ((int) ($company->is_partner ?? 0) === 1) {
    $partnerLabel = 'Partner';
}

$linkedExpert = is_object($linkedExpert ?? null) ? $linkedExpert : null;
$linkedSpeaker = is_object($linkedSpeaker ?? null) ? $linkedSpeaker : null;
$linkedExperts = is_array($linkedExperts ?? null) ? $linkedExperts : [];
$linkedSpeakers = is_array($linkedSpeakers ?? null) ? $linkedSpeakers : [];

$detailRows = [];
if ($industry !== '') {
    $detailRows[] = ['label' => 'Branche', 'value' => $industry];
}
if ($city !== '') {
    $detailRows[] = ['label' => 'Ort', 'value' => $city];
}
if ($country !== '') {
    $detailRows[] = ['label' => 'Land', 'value' => $country];
}
if ($companySize !== '') {
    $detailRows[] = ['label' => 'Größe', 'value' => $companySize];
}
$detailRows[] = ['label' => 'Status', 'value' => $partnerLabel];

$companyInitials = 'CO';
$parts = preg_split('/\s+/', $name) ?: [];
$letters = '';
foreach ($parts as $part) {
    $letters .= strtoupper(substr((string) $part, 0, 1));
    if (strlen($letters) >= 2) {
        break;
    }
}
if ($letters !== '') {
    $companyInitials = substr($letters, 0, 2);
}

$sidebarContactLink = '';
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $sidebarContactLink = 'mailto:' . $email;
}

$hasSidebarPrimaryActions = $sidebarContactLink !== '' || $website !== '';
?>

<?= $renderDesignVars($designVars) ?>

<main class="cms-excomp-public cms-excomp-detail cms-excomp-detail--company">
    <div class="cms-excomp-container">
        <nav class="cms-excomp-breadcrumb">
            <a href="<?= $e($base . '/companies') ?>">Companies</a>
            <span>/</span>
            <span><?= $e($name) ?></span>
        </nav>

        <article class="cms-excomp-detail-layout">
            <header class="cms-excomp-detail-header cms-excomp-detail-header--company">
                <div class="cms-excomp-detail-header__content">
                    <div class="cms-excomp-detail-header__intro">
                        <div class="cms-excomp-detail-badge cms-excomp-detail-badge--company" aria-label="Companyprofil">
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?= $e($logoUrl) ?>" alt="<?= $e($name) ?>" loading="eager">
                            <?php else: ?>
                                <span class="cms-excomp-detail-badge__initial"><?= $e($companyInitials) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cms-excomp-detail-header__text">
                            <h1><?= $e($name) ?></h1>
                            <div class="cms-excomp-detail-header__meta">
                                <?php if ($industry !== ''): ?><span><?= $e($industry) ?></span><?php endif; ?>
                                <?php if ($city !== ''): ?><span><?= $e($city) ?></span><?php endif; ?>
                                <span><?= $e($partnerLabel) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cms-excomp-detail-layout__body">
                <section class="cms-excomp-detail-main">
                    <?= $renderEditor($company->description_json ?? '', $company->description ?? '') ?>
                </section>

                <aside class="cms-excomp-sidebar">
                    <div class="cms-excomp-sidebar-stack">
                        <div class="cms-excomp-sidecard cms-excomp-sidecard--details<?= $hasSidebarPrimaryActions ? ' cms-excomp-sidecard--details-has-links' : '' ?>">
                            <dl class="cms-excomp-sidecard__meta">
                                <?php foreach ($detailRows as $detailRow): ?>
                                    <div class="cms-excomp-sidecard__meta-row">
                                        <dt><?= $e((string) ($detailRow['label'] ?? '')) ?></dt>
                                        <dd><?= $e((string) ($detailRow['value'] ?? '')) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($email !== ''): ?><div class="cms-excomp-sidecard__meta-row"><dt>E-Mail</dt><dd><a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a></dd></div><?php endif; ?>
                                <?php if ($phone !== ''): ?><div class="cms-excomp-sidecard__meta-row"><dt>Telefon</dt><dd><?= $e($phone) ?></dd></div><?php endif; ?>
                            </dl>
                        </div>

                        <?php if ($hasSidebarPrimaryActions): ?>
                            <div class="cms-excomp-sidecard cms-excomp-sidecard--links">
                                <div class="cms-excomp-side-actions cms-excomp-side-actions--primary-row">
                                    <?php if ($sidebarContactLink !== ''): ?>
                                        <a class="cms-excomp-side-action cms-excomp-side-action--primary" href="<?= $e($sidebarContactLink) ?>" aria-label="Kontakt" title="Kontakt">
                                            <span class="cms-excomp-side-action__icon">✉</span>
                                            <span class="cms-excomp-side-action__label">Kontakt</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($website !== ''): ?>
                                        <a class="cms-excomp-side-action cms-excomp-side-action--primary" href="<?= $e($website) ?>" target="_blank" rel="noopener noreferrer" aria-label="Zur Website" title="Zur Website">
                                            <span class="cms-excomp-side-action__icon">🌐</span>
                                            <span class="cms-excomp-side-action__label"><?= $e($setting('website_button_label', 'Website')) ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($linkedExpert !== null || $linkedSpeaker !== null): ?>
                        <div class="cms-excomp-sidecard">
                            <h2>Direkt verknüpft</h2>
                            <div class="cms-excomp-linked">
                                <?php if ($linkedExpert !== null): ?>
                                    <?php
                                    $linkedExpertId = (int) ($linkedExpert->id ?? 0);
                                    $linkedExpertName = trim((string) (($linkedExpert->first_name ?? '') . ' ' . ($linkedExpert->last_name ?? '')));
                                    if ($linkedExpertName === '') {
                                        $linkedExpertName = 'Expert #' . $linkedExpertId;
                                    }
                                    ?>
                                    <a class="cms-excomp-btn" href="<?= $e($linkedExpertId > 0 ? ($base . '/experts/' . $linkedExpertId) : ($base . '/experts')) ?>">👤 <?= $e($linkedExpertName) ?></a>
                                <?php endif; ?>
                                <?php if ($linkedSpeaker !== null): ?>
                                    <?php
                                    $linkedSpeakerSlug = trim((string) ($linkedSpeaker->slug ?? ''));
                                    $linkedSpeakerName = trim((string) ($linkedSpeaker->display_name ?? ''));
                                    ?>
                                    <a class="cms-excomp-btn" href="<?= $e($linkedSpeakerSlug !== '' ? ($base . '/speakers/' . rawurlencode($linkedSpeakerSlug)) : ($base . '/speakers')) ?>">🎤 <?= $e($linkedSpeakerName !== '' ? $linkedSpeakerName : 'Speaker') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if ($linkedExperts !== [] || $linkedSpeakers !== []): ?>
                <section class="cms-excomp-section cms-excomp-section--fullwidth">
                    <?php if ($linkedExperts !== []): ?>
                        <div class="cms-excomp-related-block">
                            <h2>Expert:innen</h2>
                            <div class="cms-excomp-speaker-list">
                                <?php foreach ($linkedExperts as $expertItem): ?>
                                    <?php
                                    if (!is_object($expertItem)) {
                                        continue;
                                    }
                                    $expertId = (int) ($expertItem->id ?? 0);
                                    $expertName = trim((string) (($expertItem->first_name ?? '') . ' ' . ($expertItem->last_name ?? '')));
                                    if ($expertName === '') {
                                        $expertName = 'Expert #' . $expertId;
                                    }
                                    ?>
                                    <a class="cms-excomp-speaker-row" href="<?= $e($expertId > 0 ? ($base . '/experts/' . $expertId) : ($base . '/experts')) ?>">
                                        <span>👤</span>
                                        <span><strong><?= $e($expertName) ?></strong></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedSpeakers !== []): ?>
                        <div class="cms-excomp-related-block">
                            <h2>Speaker</h2>
                            <div class="cms-excomp-speaker-list">
                                <?php foreach ($linkedSpeakers as $speakerItem): ?>
                                    <?php
                                    if (!is_object($speakerItem)) {
                                        continue;
                                    }
                                    $speakerName = trim((string) ($speakerItem->display_name ?? ''));
                                    if ($speakerName === '') {
                                        $speakerName = 'Speaker #' . (int) ($speakerItem->id ?? 0);
                                    }
                                    $speakerSlug = trim((string) ($speakerItem->slug ?? ''));
                                    ?>
                                    <a class="cms-excomp-speaker-row" href="<?= $e($speakerSlug !== '' ? ($base . '/speakers/' . rawurlencode($speakerSlug)) : ($base . '/speakers')) ?>">
                                        <span>🎤</span>
                                        <span><strong><?= $e($speakerName) ?></strong></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </article>
    </div>
</main>

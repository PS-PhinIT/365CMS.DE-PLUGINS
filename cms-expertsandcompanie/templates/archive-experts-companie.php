<?php
/**
 * Public Archive Template – Experts & Companie Hub.
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
$companies = array_values(array_filter(array_map(
    static fn($item): ?object => is_object($item) ? $item : (is_array($item) ? (object) $item : null),
    (array) ($companies ?? [])
)));

$filters = is_array($filters ?? null) ? $filters : [];
$q = trim((string) ($filters['q'] ?? ''));
$city = trim((string) ($filters['city'] ?? ''));
$settings = is_array($settings ?? null) ? $settings : [];
$type = trim((string) ($filters['type'] ?? 'all'));
if (!in_array($type, ['all', 'experts', 'companies'], true)) {
    $type = 'all';
}

$showExperts = in_array($type, ['all', 'experts'], true);
$showCompanies = in_array($type, ['all', 'companies'], true);

$hubUrl = rtrim((string) SITE_URL, '/') . '/experts-companie';

$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$mediaUrl = static fn(mixed $value): string => class_exists('CMS_365NET_Experts_And_Companie') ? CMS_365NET_Experts_And_Companie::normalizeMediaUrl((string) $value) : trim((string) $value);

$setting = static function (string $key, string $default) use ($settings): string {
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$hubHeaderTextEnabled = $setting('hub_header_text_enabled', '1') !== '0';
$hubHeaderHasTextContent = $hubHeaderTextEnabled;
$isAllowedHeaderUrl = static function (string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    return str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1;
};
$hubHeaderButtons = [];
for ($buttonIndex = 1; $buttonIndex <= 3; $buttonIndex++) {
    $text = trim($setting('hub_header_btn_' . $buttonIndex . '_text', ''));
    $url = trim($setting('hub_header_btn_' . $buttonIndex . '_url', ''));
    if ($text === '' || !$isAllowedHeaderUrl($url)) {
        continue;
    }

    $hubHeaderButtons[] = ['text' => $text, 'url' => $url];
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

$expertInitials = static function (object $expert): string {
    $first = trim((string) ($expert->first_name ?? ''));
    $last = trim((string) ($expert->last_name ?? ''));
    $letters = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    return $letters !== '' ? $letters : 'EX';
};

$expertName = static function (object $expert): string {
    $first = trim((string) ($expert->first_name ?? ''));
    $last = trim((string) ($expert->last_name ?? ''));
    $full = trim($first . ' ' . $last);
    return $full !== '' ? $full : 'Expert #' . (int) ($expert->id ?? 0);
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

$companyPartnerLabel = static function (object $company): string {
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

<main class="cms-excomp-public">
    <div class="cms-excomp-container">
        <section class="cms-excomp-hero cms-excomp-hero--compact<?= $hubHeaderButtons !== [] ? ' cms-excomp-hero--has-actions' : '' ?><?= !$hubHeaderHasTextContent ? ' cms-excomp-hero--no-text' : '' ?>" aria-label="Experts and Companies Hub">
            <?php if ($hubHeaderTextEnabled): ?>
                <span class="cms-excomp-kicker"><?= $e($setting('hub_archive_kicker', '365 Network Hub')) ?></span>
                <h1><?= $e($setting('hub_archive_title', 'Experts & Companie')) ?></h1>
                <p class="cms-excomp-hero__description"><?= $e($setting('hub_archive_description', 'Gemeinsame Übersicht im Event-&-Speaker-Stil: Experten in Orange, Firmen in Grün. Datenquelle ist vollständig die integrierte Plugin-Datenbank.')) ?></p>
                <div class="cms-excomp-system-state">
                    <span class="cms-excomp-pill is-on">Standalone aktiv</span>
                    <span class="cms-excomp-pill is-on">Seed integriert</span>
                </div>
            <?php endif; ?>
            <?php if ($hubHeaderButtons !== []): ?>
                <div class="cms-excomp-hero__actions" aria-label="Header-Aktionen">
                    <?php foreach ($hubHeaderButtons as $button): ?>
                        <a class="cms-excomp-hero__action" href="<?= $e((string) $button['url']) ?>"><?= $e((string) $button['text']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <form method="GET" action="<?= $e($hubUrl) ?>" class="cms-excomp-search" role="search" aria-label="Experts and Companies Suche">
            <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Name, Firma, Branche, Position …">
            <input type="text" name="city" value="<?= $e($city) ?>" placeholder="Stadt …">
            <select name="type" aria-label="Typ wählen">
                <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>Alle</option>
                <option value="experts"<?= $type === 'experts' ? ' selected' : '' ?>>Nur Experts</option>
                <option value="companies"<?= $type === 'companies' ? ' selected' : '' ?>>Nur Companies</option>
            </select>
            <button type="submit"><?= $e($setting('search_button_label', 'Suchen')) ?></button>
            <?php if ($q !== '' || $city !== '' || $type !== 'all'): ?>
                <a href="<?= $e($hubUrl) ?>" class="cms-excomp-reset"><?= $e($setting('reset_button_label', 'Zurücksetzen')) ?></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="cms-excomp-container cms-excomp-content">
        <?php if ($showExperts): ?>
            <section class="cms-excomp-section cms-excomp-section--experts" aria-label="Experts">
                <header class="cms-excomp-section-head">
                    <h2>Experts</h2>
                    <span><?= count($experts) ?> Treffer</span>
                </header>

                <?php if (empty($experts)): ?>
                    <div class="cms-excomp-empty">
                        <p>Keine Experts für den aktuellen Filter gefunden.</p>
                    </div>
                <?php else: ?>
                    <div class="cms-excomp-grid">
                        <?php foreach ($experts as $expert): ?>
                            <?php
                            $name = $expertName($expert);
                            $detailUrl = trim((string) ($expert->detail_url ?? ''));
                            $availability = trim((string) ($expert->availability ?? ''));
                            $position = trim((string) ($expert->position ?? ''));
                            $company = trim((string) ($expert->company ?? ''));
                            $location = trim((string) ($expert->location_city ?? ''));
                            $photo = $mediaUrl($expert->photo_url ?? '');
                            $linkedSpeaker = is_object($expert->linked_speaker ?? null) ? $expert->linked_speaker : null;
                            $linkedCompany = is_object($expert->linked_company ?? null) ? $expert->linked_company : null;
                            $speakerSlug = trim((string) ($linkedSpeaker->slug ?? ''));
                            $speakerName = trim((string) ($linkedSpeaker->display_name ?? ''));
                            $linkedCompanyName = trim((string) ($linkedCompany->name ?? ''));
                            ?>
                            <article class="cms-excomp-card cms-excomp-card--expert">
                                <div class="cms-excomp-card-head">
                                    <?php if ($photo !== ''): ?>
                                        <img src="<?= $e($photo) ?>" alt="<?= $e($name) ?>" loading="lazy" class="cms-excomp-avatar">
                                    <?php else: ?>
                                        <span class="cms-excomp-avatar cms-excomp-avatar--fallback"><?= $e($expertInitials($expert)) ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <h3><?= $e($name) ?></h3>
                                        <?php if ($position !== ''): ?><p><?= $e($position) ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="cms-excomp-card-meta">
                                    <?php if ($company !== ''): ?><span><?= $e($company) ?></span><?php endif; ?>
                                    <?php if ($location !== ''): ?><span><?= $e($location) ?></span><?php endif; ?>
                                    <?php if ($availability !== ''): ?><span class="cms-excomp-badge"><?= $e($availability) ?></span><?php endif; ?>
                                </div>

                                <?php if ($linkedSpeaker !== null || $linkedCompany !== null): ?>
                                    <div class="cms-excomp-linked">
                                        <?php if ($linkedSpeaker !== null): ?>
                                            <a class="cms-excomp-linked-item" href="<?= $e($speakerSlug !== '' ? (rtrim((string) SITE_URL, '/') . '/speakers/' . rawurlencode($speakerSlug)) : (rtrim((string) SITE_URL, '/') . '/speakers')) ?>">🎤 <?= $e($speakerName !== '' ? $speakerName : 'Speaker') ?></a>
                                        <?php endif; ?>
                                        <?php if ($linkedCompany !== null): ?>
                                            <?php $linkedCompanyId = (int) ($linkedCompany->id ?? 0); ?>
                                            <a class="cms-excomp-linked-item" href="<?= $e($linkedCompanyId > 0 ? (rtrim((string) SITE_URL, '/') . '/companies/' . $linkedCompanyId) : (rtrim((string) SITE_URL, '/') . '/companies')) ?>">🏢 <?= $e($linkedCompanyName !== '' ? $linkedCompanyName : 'Company') ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($detailUrl !== ''): ?>
                                    <a class="cms-excomp-card-link" href="<?= $e($detailUrl) ?>">Profil öffnen</a>
                                <?php else: ?>
                                    <span class="cms-excomp-card-link is-disabled">Kein Profil verfügbar</span>
                                <?php endif; ?>

                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($showCompanies): ?>
            <section class="cms-excomp-section cms-excomp-section--companies" aria-label="Companies">
                <header class="cms-excomp-section-head">
                    <h2>Firmen</h2>
                    <span><?= count($companies) ?> Treffer</span>
                </header>

                <?php if (empty($companies)): ?>
                    <div class="cms-excomp-empty">
                        <p>Keine Firmen für den aktuellen Filter gefunden.</p>
                    </div>
                <?php else: ?>
                    <div class="cms-excomp-grid">
                        <?php foreach ($companies as $company): ?>
                            <?php
                            $name = trim((string) ($company->name ?? ''));
                            $name = $name !== '' ? $name : 'Company #' . (int) ($company->id ?? 0);
                            $detailUrl = trim((string) ($company->detail_url ?? ''));
                            $industry = trim((string) ($company->industry ?? ''));
                            $location = trim((string) ($company->location_city ?? ''));
                            $website = trim((string) ($company->website ?? ''));
                            $logo = $mediaUrl($company->logo_url ?? '');
                            $linkedExperts = is_array($company->linked_experts ?? null) ? $company->linked_experts : [];
                            $linkedSpeakers = is_array($company->linked_speakers ?? null) ? $company->linked_speakers : [];
                            ?>
                            <article class="cms-excomp-card cms-excomp-card--company">
                                <div class="cms-excomp-card-head">
                                    <?php if ($logo !== ''): ?>
                                        <img src="<?= $e($logo) ?>" alt="<?= $e($name) ?>" loading="lazy" class="cms-excomp-avatar cms-excomp-avatar--company">
                                    <?php else: ?>
                                        <span class="cms-excomp-avatar cms-excomp-avatar--fallback cms-excomp-avatar--company"><?= $e($companyInitials($company)) ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <h3><?= $e($name) ?></h3>
                                        <?php if ($industry !== ''): ?><p><?= $e($industry) ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="cms-excomp-card-meta">
                                    <span><?= $e($companyPartnerLabel($company)) ?></span>
                                    <?php if ($location !== ''): ?><span><?= $e($location) ?></span><?php endif; ?>
                                    <?php if ($website !== ''): ?><span><?= $e(parse_url($website, PHP_URL_HOST) ?: $website) ?></span><?php endif; ?>
                                </div>

                                <?php if ($linkedExperts !== [] || $linkedSpeakers !== []): ?>
                                    <div class="cms-excomp-linked">
                                        <?php foreach (array_slice($linkedExperts, 0, 2) as $linkedExpert): ?>
                                            <?php
                                            if (!is_object($linkedExpert)) {
                                                continue;
                                            }
                                            $linkedExpertName = trim((string) (($linkedExpert->first_name ?? '') . ' ' . ($linkedExpert->last_name ?? '')));
                                            if ($linkedExpertName === '') {
                                                $linkedExpertName = 'Expert #' . (int) ($linkedExpert->id ?? 0);
                                            }
                                            $linkedExpertId = (int) ($linkedExpert->id ?? 0);
                                            ?>
                                            <a class="cms-excomp-linked-item" href="<?= $e($linkedExpertId > 0 ? (rtrim((string) SITE_URL, '/') . '/experts/' . $linkedExpertId) : (rtrim((string) SITE_URL, '/') . '/experts')) ?>">👤 <?= $e($linkedExpertName) ?></a>
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
                                                ? (rtrim((string) SITE_URL, '/') . '/speakers/' . rawurlencode($linkedSpeakerSlug))
                                                : (rtrim((string) SITE_URL, '/') . '/speakers');
                                            ?>
                                            <a class="cms-excomp-linked-item" href="<?= $e($linkedSpeakerUrl) ?>">🎤 <?= $e($linkedSpeakerName) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($detailUrl !== ''): ?>
                                    <a class="cms-excomp-card-link" href="<?= $e($detailUrl) ?>">Company öffnen</a>
                                <?php else: ?>
                                    <span class="cms-excomp-card-link is-disabled">Kein Profil verfügbar</span>
                                <?php endif; ?>

                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

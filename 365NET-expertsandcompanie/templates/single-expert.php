<?php
/**
 * Public Detail Template – Expert.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Styles (style.css + Design-Variablen) bindet der Hook `head` als Stylesheet-Link ein – keine Inline-Styles (CSP).

$expert = is_object($expert ?? null) ? $expert : null;
if ($expert === null) {
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

$firstName = trim((string) ($expert->first_name ?? ''));
$lastName = trim((string) ($expert->last_name ?? ''));
$fullName = trim($firstName . ' ' . $lastName);
$fullName = $fullName !== '' ? $fullName : ('Expert #' . (int) ($expert->id ?? 0));
$profileImageUrl = $mediaUrl($expert->photo_url ?? '');
$profileImageAlt = $fullName;

$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
if ($initials === '') {
    $initials = 'EX';
}

$position = trim((string) ($expert->position ?? ''));
$company = trim((string) ($expert->company ?? ''));
$location = trim((string) ($expert->location_city ?? $expert->city ?? ''));
$country = trim((string) ($expert->country ?? ''));
$availability = trim((string) ($expert->availability ?? ''));
$website = trim((string) ($expert->website ?? ''));
$email = trim((string) ($expert->email ?? ''));
$phone = trim((string) ($expert->phone ?? ''));
$skillsGeneral = trim((string) ($expert->skills_general ?? ''));
$skillsTech = trim((string) ($expert->skills_tech ?? ''));
$skillsSoft = trim((string) ($expert->skills_soft ?? ''));

$detailRows = [];
if ($position !== '') {
    $detailRows[] = ['label' => 'Position', 'value' => $position];
}
if ($company !== '') {
    $detailRows[] = ['label' => 'Firma', 'value' => $company];
}
if ($location !== '') {
    $detailRows[] = ['label' => 'Ort', 'value' => $location];
}
if ($country !== '') {
    $detailRows[] = ['label' => 'Land', 'value' => $country];
}
if ($availability !== '') {
    $detailRows[] = ['label' => 'Verfügbarkeit', 'value' => $availability];
}
if ($skillsGeneral !== '') {
    $detailRows[] = ['label' => 'Skills', 'value' => $skillsGeneral];
}
if ($skillsTech !== '') {
    $detailRows[] = ['label' => 'Tech', 'value' => $skillsTech];
}
if ($skillsSoft !== '') {
    $detailRows[] = ['label' => 'Soft Skills', 'value' => $skillsSoft];
}
if ($detailRows === []) {
    $detailRows[] = ['label' => 'Hinweis', 'value' => 'Keine Details hinterlegt'];
}

$linkedCompany = is_object($linkedCompany ?? null) ? $linkedCompany : null;
$linkedSpeaker = is_object($linkedSpeaker ?? null) ? $linkedSpeaker : null;

$sidebarContactLink = '';
$sidebarContactIcon = '✉';
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $sidebarContactLink = 'mailto:' . $email;
} elseif ($phone !== '') {
    $phoneLink = preg_replace('/[^\d+]/', '', $phone);
    if (is_string($phoneLink) && $phoneLink !== '') {
        $sidebarContactLink = 'tel:' . $phoneLink;
        $sidebarContactIcon = '☎';
    }
}

$hasSidebarPrimaryActions = $sidebarContactLink !== '' || $website !== '';
?>

<main class="cms-excomp-public cms-excomp-detail cms-excomp-detail--expert">
    <div class="cms-excomp-container">
        <nav class="cms-excomp-breadcrumb">
            <a href="<?= $e($base . '/experts') ?>">Experts</a>
            <span>/</span>
            <span><?= $e($fullName) ?></span>
        </nav>

        <article class="cms-excomp-detail-layout">
            <header class="cms-excomp-detail-header cms-excomp-detail-header--expert">
                <div class="cms-excomp-detail-header__content">
                    <div class="cms-excomp-detail-header__intro">
                        <div class="cms-excomp-detail-badge" aria-label="Expertprofil">
                            <?php if ($profileImageUrl !== ''): ?>
                                <img src="<?= $e($profileImageUrl) ?>" alt="<?= $e($profileImageAlt) ?>" loading="eager">
                            <?php else: ?>
                                <span class="cms-excomp-detail-badge__initial"><?= $e($initials) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cms-excomp-detail-header__text">
                            <h1><?= $e($fullName) ?></h1>
                            <div class="cms-excomp-detail-header__meta">
                                <?php if ($position !== ''): ?><span><?= $e($position) ?></span><?php endif; ?>
                                <?php if ($company !== ''): ?><span><?= $e($company) ?></span><?php endif; ?>
                                <?php if ($location !== ''): ?><span><?= $e($location) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cms-excomp-detail-layout__body">
                <section class="cms-excomp-detail-main">
                    <?= $renderEditor($expert->biography_json ?? '', $expert->biography ?? '') ?>
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
                            </dl>
                        </div>

                        <?php if ($hasSidebarPrimaryActions): ?>
                            <div class="cms-excomp-sidecard cms-excomp-sidecard--links">
                                <div class="cms-excomp-side-actions cms-excomp-side-actions--primary-row">
                                    <?php if ($sidebarContactLink !== ''): ?>
                                        <a class="cms-excomp-side-action cms-excomp-side-action--primary" href="<?= $e($sidebarContactLink) ?>" aria-label="Kontakt" title="Kontakt">
                                            <span class="cms-excomp-side-action__icon"><?= $e($sidebarContactIcon) ?></span>
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

                    <?php if ($linkedCompany !== null || $linkedSpeaker !== null): ?>
                        <div class="cms-excomp-sidecard">
                            <h2>365CMS-Verknüpfung</h2>
                            <div class="cms-excomp-linked">
                                <?php if ($linkedCompany !== null): ?>
                                    <?php
                                    $linkedCompanyId = (int) ($linkedCompany->id ?? 0);
                                    $linkedCompanyName = trim((string) ($linkedCompany->name ?? ''));
                                    ?>
                                    <a class="cms-excomp-btn" href="<?= $e($linkedCompanyId > 0 ? ($base . '/companies/' . $linkedCompanyId) : ($base . '/companies')) ?>">🏢 <?= $e($linkedCompanyName !== '' ? $linkedCompanyName : 'Company') ?></a>
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
        </article>
    </div>
</main>

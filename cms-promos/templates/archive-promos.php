<?php
/**
 * Öffentliche Promo-Übersicht (/promos, /promos/placement/:slug).
 *
 * Erwartet aus CMS_Promos_Public_Controller::archive_page(): $this, $lang, $settings, $placements,
 * $currentPlacement, $placementNotFound, $promos.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var CMS_Promos_Public_Controller $this */
$lang = isset($lang) && $lang === 'en' ? 'en' : 'de';
$settings = is_array($settings ?? null) ? $settings : [];
$placements = is_array($placements ?? null) ? $placements : [];
$promos = is_array($promos ?? null) ? $promos : [];
$currentPlacement = is_array($currentPlacement ?? null) ? $currentPlacement : null;
$placementNotFound = !empty($placementNotFound);

$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$t = static function (string $key) use ($lang): string {
    $labels = [
        'kicker' => ['de' => '365CMS Promos', 'en' => '365CMS Promos'],
        'placement_nav_aria' => ['de' => 'Promo-Platzierungen', 'en' => 'Promo placements'],
        'placement_all' => ['de' => 'Alle Promos', 'en' => 'All promos'],
        'placement_current' => ['de' => 'Aktuelle Platzierung', 'en' => 'Current placement'],
        'empty_eyebrow' => ['de' => 'Noch leer', 'en' => 'Still empty'],
        'empty_title' => ['de' => 'Aktuell keine aktiven Promos', 'en' => 'No active promos currently'],
        'empty_text' => ['de' => 'Sobald Kampagnen aktiviert und einer Platzierung zugeordnet wurden, erscheinen sie hier automatisch.', 'en' => 'As soon as campaigns are activated and assigned to a placement, they will appear here automatically.'],
        'not_found_title' => ['de' => 'Platzierung nicht gefunden', 'en' => 'Placement not found'],
        'not_found_text' => ['de' => 'Diese Promo-Platzierung existiert nicht oder ist nicht aktiv.', 'en' => 'This promo placement does not exist or is not active.'],
        'generic_promo' => ['de' => 'Promo', 'en' => 'Promo'],
    ];

    return $labels[$key][$lang] ?? $labels[$key]['de'] ?? '';
};
$newTab = ($settings['default_target_behavior'] ?? 'same_tab') === 'new_tab';
$currentSlug = $currentPlacement !== null ? (string) ($currentPlacement['slug'] ?? '') : '';
$allActive = $currentSlug === '' && !$placementNotFound;
?>
<main class="promos-archive">
    <section class="promos-hero">
        <span class="promos-kicker"><?php echo $esc($t('kicker')); ?></span>
        <h1 class="promos-title"><?php echo $esc($this->i18n_value($settings, 'archive_title', $lang, 'Promotions & Highlights')); ?></h1>
        <?php $archiveLead = $this->i18n_value($settings, 'archive_description', $lang); ?>
        <?php if ($archiveLead !== ''): ?>
            <p class="promos-lead"><?php echo $esc($archiveLead); ?></p>
        <?php endif; ?>
        <?php if ($currentPlacement !== null): ?>
            <p class="promos-lead"><?php echo $esc($t('placement_current')); ?>: <strong><?php echo $esc($this->i18n_value($currentPlacement, 'name', $lang)); ?></strong></p>
        <?php endif; ?>
    </section>

    <?php if ($placements !== []): ?>
        <nav class="promos-placement-nav" aria-label="<?php echo $esc($t('placement_nav_aria')); ?>">
            <a class="promos-placement-link<?php echo $allActive ? ' is-active' : ''; ?>" href="<?php echo $esc($this->localized_path('promos', $lang)); ?>"<?php echo $allActive ? ' aria-current="page"' : ''; ?>>
                <strong><?php echo $esc($t('placement_all')); ?></strong>
            </a>
            <?php foreach ($placements as $placement): ?>
                <?php $isCurrent = $currentSlug !== '' && $currentSlug === (string) ($placement['slug'] ?? ''); ?>
                <a class="promos-placement-link<?php echo $isCurrent ? ' is-active' : ''; ?>" href="<?php echo $esc($this->localized_path('promos/placement/' . rawurlencode((string) ($placement['slug'] ?? '')), $lang)); ?>"<?php echo $isCurrent ? ' aria-current="page"' : ''; ?>>
                    <strong><?php echo $esc($this->i18n_value($placement, 'name', $lang)); ?></strong>
                    <?php $placementLead = $this->i18n_value($placement, 'description', $lang); ?>
                    <?php if ($placementLead !== ''): ?>
                        <span class="promos-placement-link__text"><?php echo $esc($placementLead); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <section class="promos-grid">
        <?php if ($placementNotFound): ?>
            <article class="promos-card" role="status">
                <h2 class="promos-card__title"><?php echo $esc($t('not_found_title')); ?></h2>
                <p><?php echo $esc($t('not_found_text')); ?></p>
            </article>
        <?php elseif ($promos === []): ?>
            <article class="promos-card" role="status">
                <span class="promos-card__eyebrow"><?php echo $esc($t('empty_eyebrow')); ?></span>
                <h2 class="promos-card__title"><?php echo $esc($t('empty_title')); ?></h2>
                <p><?php echo $esc($t('empty_text')); ?></p>
            </article>
        <?php else: ?>
            <?php foreach ($promos as $promo): ?>
                <?php
                $promoTitle = $this->i18n_value($promo, 'title', $lang, $t('generic_promo'));
                $promoImage = CMS_Promos_Sanitizer::link((string) ($promo['image_url'] ?? ''));
                $promoTeaser = $this->i18n_value($promo, 'teaser', $lang);
                $promoContent = $this->i18n_value($promo, 'content_html', $lang);
                $hasTarget = trim((string) ($promo['target_url'] ?? '')) !== '';
                ?>
                <article class="promos-card<?php echo !empty($promo['is_featured']) ? ' promos-card--featured' : ''; ?>" id="promo-<?php echo $esc((string) ($promo['slug'] ?? '')); ?>">
                    <?php if ($promoImage !== ''): ?>
                        <img class="promos-card__image" src="<?php echo $esc($promoImage); ?>" alt="<?php echo $esc($promoTitle); ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <span class="promos-card__eyebrow"><?php echo $esc($this->i18n_value($promo, 'placement_name', $lang, $t('generic_promo'))); ?></span>
                    <h2 class="promos-card__title"><?php echo $esc($promoTitle); ?></h2>
                    <?php if ($promoTeaser !== ''): ?>
                        <p><?php echo $esc($promoTeaser); ?></p>
                    <?php endif; ?>
                    <?php if ($promoContent !== ''): ?>
                        <div class="promos-card__body"><?php echo CMS_Promos_Sanitizer::html($promoContent); ?></div>
                    <?php endif; ?>
                    <?php if ($hasTarget): ?>
                        <p class="promos-card__actions">
                            <a class="promos-card__button" href="<?php echo $esc($this->localized_path('promo/click/' . rawurlencode((string) ($promo['slug'] ?? '')), $lang)); ?>" rel="sponsored noopener"<?php echo $newTab ? ' target="_blank"' : ''; ?>><?php echo $esc($this->button_label($promo, $settings, $lang)); ?></a>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

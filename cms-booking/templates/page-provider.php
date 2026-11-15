<?php
/**
 * Template: Provider-Übersicht
 * Zeigt alle buchbaren Leistungen eines Anbieters.
 *
 * Variablen: $provider, $services, $siteUrl
 *
 * @package CMS_Booking
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

$e = fn(?string $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$siteName = $siteName ?? (defined('SITE_NAME') ? SITE_NAME : '365CMS');
$lang = is_array($i18n ?? null) ? (string) ($i18n['lang'] ?? 'de') : 'de';
$providerTitle = is_array($provider ?? null) ? (string) ($provider['display_name'] ?? '') : '';
$providerBio = is_array($provider ?? null) ? (string) ($provider['bio'] ?? '') : '';
if (function_exists('cms_plugin_public_i18n_value')) {
    $providerTitle = cms_plugin_public_i18n_value($provider, 'display_name', $lang, $providerTitle);
    $providerBio = cms_plugin_public_i18n_value($provider, 'bio', $lang, $providerBio);
}
$bookingBasePath = is_array($i18n ?? null) ? (string) ($i18n['bookingBasePath'] ?? '/booking') : '/booking';
$providerEmptyTitle = is_array($i18n ?? null) ? (string) ($i18n['providerEmptyTitle'] ?? 'Aktuell keine Terminart verfügbar') : 'Aktuell keine Terminart verfügbar';
$providerEmptyText = is_array($i18n ?? null) ? (string) ($i18n['providerEmptyText'] ?? 'Bitte versuchen Sie es später erneut.') : 'Bitte versuchen Sie es später erneut.';
$providerServicesAria = is_array($i18n ?? null) ? (string) ($i18n['providerServicesAria'] ?? 'Buchbare Leistungen') : 'Buchbare Leistungen';
$providerCtaBookNow = is_array($i18n ?? null) ? (string) ($i18n['providerCtaBookNow'] ?? 'Jetzt buchen →') : 'Jetzt buchen →';
$locationLabels = [
    'online' => is_array($i18n ?? null) ? (string) ($i18n['locationOnline'] ?? 'Online') : 'Online',
    'onsite' => is_array($i18n ?? null) ? (string) ($i18n['locationOnsite'] ?? 'Vor Ort') : 'Vor Ort',
    'hybrid' => is_array($i18n ?? null) ? (string) ($i18n['locationHybrid'] ?? 'Hybrid') : 'Hybrid',
];
$freeLabel = is_array($i18n ?? null) ? (string) ($i18n['freeLabel'] ?? 'Kostenlos') : 'Kostenlos';
?>
<!DOCTYPE html>
<html lang="<?php echo $e($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($providerTitle); ?> – <?php echo $e($siteName); ?></title>
    <?php \CMS\Hooks::doAction('head'); ?>
</head>
<body class="booking-page booking-provider-page">
    <?php \CMS\Hooks::doAction('body_start'); ?>
    <?php \CMS\ThemeManager::instance()->render('header'); ?>
    <?php \CMS\Hooks::doAction('after_header'); ?>

    <main class="booking-main">
        <div class="booking-container">

            <!-- Provider-Header -->
            <header class="booking-provider-header">
                <h1><?php echo $e($providerTitle); ?></h1>
                <?php if ($providerBio !== ''): ?>
                <p class="booking-provider-description"><?php echo $e($providerBio); ?></p>
                <?php endif; ?>
                <?php if (!empty($provider['email'])): ?>
                <p class="booking-provider-meta">
                    <?php echo $e($provider['email']); ?>
                    <?php if (!empty($provider['phone'])): ?>
                    &nbsp;·&nbsp;<?php echo $e($provider['phone']); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </header>

            <!-- Services -->
            <?php if (empty($services)): ?>
            <div class="booking-empty-state">
                <p><strong><?php echo $e($providerEmptyTitle); ?></strong></p>
                <p class="text-muted"><?php echo $e($providerEmptyText); ?></p>
            </div>
            <?php else: ?>
            <section class="booking-services-grid" aria-label="<?php echo $e($providerServicesAria); ?>">
                <?php foreach ($services as $service): ?>
                <?php
                $serviceTitle = (string) ($service['title'] ?? '');
                $serviceDescription = (string) ($service['description'] ?? '');
                if (function_exists('cms_plugin_public_i18n_value')) {
                    $serviceTitle = cms_plugin_public_i18n_value($service, 'title', $lang, $serviceTitle);
                    $serviceDescription = cms_plugin_public_i18n_value($service, 'description', $lang, $serviceDescription);
                }
                ?>
                <article>
                    <a href="<?php echo $e($siteUrl . $bookingBasePath); ?>/<?php echo $e($provider['slug']); ?>/<?php echo $e($service['slug']); ?>"
                   class="booking-service-card">
                    <div class="booking-service-card__header">
                        <h3><?php echo $e($serviceTitle); ?></h3>
                        <?php $lt = $service['location_type'] ?? 'online'; ?>
                        <span class="booking-service-card__type">
                            <?php echo $e($locationLabels[$lt] ?? (string) $lt); ?>
                        </span>
                    </div>
                    <?php if ($serviceDescription !== ''): ?>
                    <p class="booking-service-card__desc"><?php echo $e($serviceDescription); ?></p>
                    <?php endif; ?>
                    <div class="booking-service-card__footer">
                        <span class="booking-service-card__duration">
                            <?php echo CMS_Booking_Services::format_duration((int) $service['duration_min']); ?>
                        </span>
                        <span class="booking-service-card__price">
                            <?php
                            $priceCents = (int) $service['price_cents'];
                            echo $priceCents > 0
                                ? CMS_Booking_Services::format_price($priceCents)
                                : $freeLabel;
                            ?>
                        </span>
                    </div>
                    <span class="booking-service-card__cta"><?php echo $e($providerCtaBookNow); ?></span>
                </a>
                </article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <?php \CMS\Hooks::doAction('before_footer'); ?>
    <?php \CMS\ThemeManager::instance()->render('footer'); ?>
    <?php \CMS\Hooks::doAction('body_end'); ?>
</body>
</html>

<?php
/**
 * Template: Buchungsformular
 * Datum-/Zeitwahl + Kundendaten für eine Leistung.
 *
 * Variablen: $provider, $service, $csrfToken, $availDates, $useContactForm,
 *            $error, $success, $old, $siteUrl
 *
 * @package CMS_Booking
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

$e = fn(?string $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$siteName = $siteName ?? (defined('SITE_NAME') ? SITE_NAME : '365CMS');
$old      = $old ?? [];
$val      = fn(string $key, string $default = ''): string => $e($old[$key] ?? $default);
$lang = is_array($i18n ?? null) ? (string) ($i18n['lang'] ?? 'de') : 'de';
$providerTitle = (string) ($provider['display_name'] ?? '');
$serviceTitle = (string) ($service['title'] ?? '');
$serviceDescription = (string) ($service['description'] ?? '');
if (function_exists('cms_plugin_public_i18n_value')) {
    $providerTitle = cms_plugin_public_i18n_value($provider, 'display_name', $lang, $providerTitle);
    $serviceTitle = cms_plugin_public_i18n_value($service, 'title', $lang, $serviceTitle);
    $serviceDescription = cms_plugin_public_i18n_value($service, 'description', $lang, $serviceDescription);
}
$bookingBasePath = is_array($i18n ?? null) ? (string) ($i18n['bookingBasePath'] ?? '/booking') : '/booking';
$slotsApiPath = is_array($i18n ?? null) ? (string) ($i18n['slotsApiPath'] ?? '/api/booking/slots') : '/api/booking/slots';
$freeLabel = is_array($i18n ?? null) ? (string) ($i18n['freeLabel'] ?? 'Kostenlos') : 'Kostenlos';
$locationOnlineAppointment = is_array($i18n ?? null) ? (string) ($i18n['locationOnlineAppointment'] ?? 'Online-Termin') : 'Online-Termin';
$locationOnsite = is_array($i18n ?? null) ? (string) ($i18n['locationOnsite'] ?? 'Vor Ort') : 'Vor Ort';
$locationOnlineOrOnsite = is_array($i18n ?? null) ? (string) ($i18n['locationOnlineOrOnsite'] ?? 'Online oder vor Ort') : 'Online oder vor Ort';
$bookingChooseDate = is_array($i18n ?? null) ? (string) ($i18n['bookingChooseDate'] ?? 'Datum wählen') : 'Datum wählen';
$bookingChooseTime = is_array($i18n ?? null) ? (string) ($i18n['bookingChooseTime'] ?? 'Uhrzeit wählen') : 'Uhrzeit wählen';
$bookingYourDetails = is_array($i18n ?? null) ? (string) ($i18n['bookingYourDetails'] ?? 'Ihre Daten') : 'Ihre Daten';
$bookingDurationLabel = is_array($i18n ?? null) ? (string) ($i18n['bookingDurationLabel'] ?? 'Dauer') : 'Dauer';
$bookingPriceLabel = is_array($i18n ?? null) ? (string) ($i18n['bookingPriceLabel'] ?? 'Preis') : 'Preis';
$bookingPhoneLabel = is_array($i18n ?? null) ? (string) ($i18n['bookingPhoneLabel'] ?? 'Telefon') : 'Telefon';
$bookingNotesLabel = is_array($i18n ?? null) ? (string) ($i18n['bookingNotesLabel'] ?? 'Nachricht / Anmerkungen') : 'Nachricht / Anmerkungen';
$bookingPlaceholderName = is_array($i18n ?? null) ? (string) ($i18n['bookingPlaceholderName'] ?? 'Max Mustermann') : 'Max Mustermann';
$bookingPlaceholderEmail = is_array($i18n ?? null) ? (string) ($i18n['bookingPlaceholderEmail'] ?? 'max@muster.de') : 'max@muster.de';
$bookingPlaceholderNotes = is_array($i18n ?? null) ? (string) ($i18n['bookingPlaceholderNotes'] ?? 'Haben Sie besondere Wünsche?') : 'Haben Sie besondere Wünsche?';
$bookingSubmit = is_array($i18n ?? null) ? (string) ($i18n['bookingSubmit'] ?? 'Verbindlich buchen') : 'Verbindlich buchen';
?>
<!DOCTYPE html>
<html lang="<?php echo $e($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($serviceTitle); ?> – <?php echo $e($providerTitle); ?> – <?php echo $e($siteName); ?></title>
    <?php \CMS\Hooks::doAction('head'); ?>
</head>
<body class="booking-page booking-form-page">
    <?php \CMS\Hooks::doAction('body_start'); ?>
    <?php \CMS\ThemeManager::instance()->render('header'); ?>
    <?php \CMS\Hooks::doAction('after_header'); ?>

    <main class="booking-main">
        <div class="booking-container">

            <!-- Breadcrumb -->
            <nav class="booking-breadcrumb">
                <a href="<?php echo $e($siteUrl . $bookingBasePath); ?>/<?php echo $e($provider['slug']); ?>">← <?php echo $e($providerTitle); ?></a>
            </nav>

            <div class="booking-form-layout">

                <!-- Linke Seite: Leistungsinfo -->
                <div class="booking-info-panel">
                    <div class="booking-info-card">
                        <h2><?php echo $e($serviceTitle); ?></h2>
                        <?php if ($serviceDescription !== ''): ?>
                        <p><?php echo $e($serviceDescription); ?></p>
                        <?php endif; ?>
                        <ul class="booking-info-list">
                            <li><?php echo $e($bookingDurationLabel); ?>: <?php echo CMS_Booking_Services::format_duration((int) $service['duration_min']); ?></li>
                            <li>
                                <?php
                                $priceCents = (int) $service['price_cents'];
                                echo $priceCents > 0
                                    ? $bookingPriceLabel . ': ' . CMS_Booking_Services::format_price($priceCents)
                                    : $freeLabel;
                                ?>
                            </li>
                            <?php
                            $typeLabels = ['online' => $locationOnlineAppointment, 'onsite' => $locationOnsite, 'hybrid' => $locationOnlineOrOnsite];
                            $lt = $service['location_type'] ?? 'online';
                            ?>
                            <li><?php echo $e((string) ($typeLabels[$lt] ?? $lt)); ?></li>
                        </ul>
                        <div class="booking-info-provider">
                            <strong><?php echo $e($providerTitle); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Rechte Seite: Formular -->
                <div class="booking-form-panel">

                    <?php if (!empty($success)): ?>
                    <div class="booking-alert booking-alert-success"><?php echo $e($success); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                    <div class="booking-alert booking-alert-error"><?php echo $e($error); ?></div>
                    <?php endif; ?>

                    <?php if (empty($success)): ?>
                    <form method="POST" class="booking-form" id="bookingForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                        <!-- Honeypot -->
                        <div class="booking-honeypot" aria-hidden="true">
                            <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- Schritt 1: Datum -->
                        <div class="booking-step" id="step-date">
                            <h3><?php echo $e($bookingChooseDate); ?></h3>
                            <div class="booking-calendar" id="bookingCalendar"
                                 data-provider-id="<?php echo (int) $provider['id']; ?>"
                                 data-service-id="<?php echo (int) $service['id']; ?>"
                                 data-available-dates='<?php echo htmlspecialchars((string) json_encode($availDates, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>'
                                 data-api-url="<?php echo $e($siteUrl . $slotsApiPath); ?>"
                                 data-i18n='<?php echo htmlspecialchars((string) json_encode([
                                     'monthNames' => $i18n['monthNames'] ?? [],
                                     'dayLabels' => $i18n['dayLabels'] ?? [],
                                     'loadingSlots' => $i18n['jsLoadingSlots'] ?? 'Zeitfenster werden geladen…',
                                     'noSlots' => $i18n['jsNoSlots'] ?? 'Keine freien Zeiten an diesem Tag.',
                                     'slotLoadError' => $i18n['jsSlotLoadError'] ?? 'Fehler beim Laden der Zeitfenster.',
                                     'chooseDateFirst' => $i18n['jsChooseDateFirst'] ?? 'Bitte zuerst ein Datum wählen.',
                                     'clockLabel' => $i18n['clockLabel'] ?? 'Uhr',
                                     'summaryAt' => $i18n['jsSummaryAt'] ?? 'um',
                                 ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>'>
                                <!-- JS-gesteuerter Kalender -->
                            </div>
                            <input type="hidden" name="booking_date" id="bookingDate" value="<?php echo $val('booking_date'); ?>" required>
                        </div>

                        <!-- Schritt 2: Uhrzeit -->
                        <div class="booking-step" id="step-time" hidden>
                            <h3><?php echo $e($bookingChooseTime); ?></h3>
                            <div class="booking-slots" id="bookingSlots">
                                <p class="text-muted"><?php echo $e($i18n['jsChooseDateFirst'] ?? 'Bitte zuerst ein Datum wählen.'); ?></p>
                            </div>
                            <input type="hidden" name="start_time" id="startTime" value="<?php echo $val('start_time'); ?>" required>
                        </div>

                        <!-- Schritt 3: Kontaktdaten -->
                        <div class="booking-step" id="step-contact" hidden>
                            <h3><?php echo $e($bookingYourDetails); ?></h3>

                            <div class="booking-field">
                                <label for="customer_name"><?php echo $e($lang === 'en' ? 'Name' : 'Name'); ?> <span class="required">*</span></label>
                                <input type="text" id="customer_name" name="customer_name" class="booking-input"
                                       value="<?php echo $val('customer_name'); ?>" required
                                       placeholder="<?php echo $e($bookingPlaceholderName); ?>" autocomplete="name">
                            </div>

                            <div class="booking-field">
                                <label for="customer_email">E-Mail <span class="required">*</span></label>
                                <input type="email" id="customer_email" name="customer_email" class="booking-input"
                                       value="<?php echo $val('customer_email'); ?>" required
                                       placeholder="<?php echo $e($bookingPlaceholderEmail); ?>" autocomplete="email" inputmode="email">
                            </div>

                            <div class="booking-field">
                                <label for="customer_phone"><?php echo $e($bookingPhoneLabel); ?></label>
                                <input type="tel" id="customer_phone" name="customer_phone" class="booking-input"
                                       value="<?php echo $val('customer_phone'); ?>"
                                       placeholder="+49 123 456789" autocomplete="tel" inputmode="tel">
                            </div>

                            <div class="booking-field">
                                <label for="notes"><?php echo $e($bookingNotesLabel); ?></label>
                                <textarea id="notes" name="notes" class="booking-input booking-textarea"
                                          placeholder="<?php echo $e($bookingPlaceholderNotes); ?>"><?php echo $val('notes'); ?></textarea>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="booking-actions" id="step-submit" hidden>
                            <div class="booking-summary" id="bookingSummary"></div>
                            <button type="submit" class="booking-btn booking-btn-primary">
                                <?php echo $e($bookingSubmit); ?>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <?php \CMS\Hooks::doAction('before_footer'); ?>
    <?php \CMS\ThemeManager::instance()->render('footer'); ?>
    <?php \CMS\Hooks::doAction('body_end'); ?>
</body>
</html>

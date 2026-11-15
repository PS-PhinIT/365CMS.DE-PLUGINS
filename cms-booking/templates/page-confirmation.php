<?php
/**
 * Template: Buchungsbestätigung
 * Zeigt Zusammenfassung + Kalender-Links nach erfolgreicher Buchung.
 *
 * Variablen: $booking, $googleUrl, $siteUrl
 *
 * @package CMS_Booking
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

$e = fn(?string $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$siteName = $siteName ?? (defined('SITE_NAME') ? SITE_NAME : '365CMS');
$lang = is_array($i18n ?? null) ? (string) ($i18n['lang'] ?? 'de') : 'de';
$clockLabel = is_array($i18n ?? null) ? (string) ($i18n['clockLabel'] ?? 'Uhr') : 'Uhr';
$minutesShort = is_array($i18n ?? null) ? (string) ($i18n['minutesShort'] ?? 'Min.') : 'Min.';
$locationOnlineAppointment = is_array($i18n ?? null) ? (string) ($i18n['locationOnlineAppointment'] ?? 'Online-Termin') : 'Online-Termin';
$locationOnsite = is_array($i18n ?? null) ? (string) ($i18n['locationOnsite'] ?? 'Vor Ort') : 'Vor Ort';
$locationOnlineSlashOnsite = is_array($i18n ?? null) ? (string) ($i18n['locationOnlineSlashOnsite'] ?? 'Online / Vor Ort') : 'Online / Vor Ort';
$bookingBasePath = is_array($i18n ?? null) ? (string) ($i18n['bookingBasePath'] ?? '/booking') : '/booking';
$confirmationPageTitle = is_array($i18n ?? null) ? (string) ($i18n['confirmationPageTitle'] ?? 'Buchung bestätigt') : 'Buchung bestätigt';
$confirmationThankYou = is_array($i18n ?? null) ? (string) ($i18n['confirmationThankYou'] ?? 'Vielen Dank für Ihre Buchung!') : 'Vielen Dank für Ihre Buchung!';
$confirmationSubtitleConfirmed = is_array($i18n ?? null) ? (string) ($i18n['confirmationSubtitleConfirmed'] ?? 'Ihr Termin wurde bestätigt.') : 'Ihr Termin wurde bestätigt.';
$confirmationSubtitlePending = is_array($i18n ?? null) ? (string) ($i18n['confirmationSubtitlePending'] ?? 'Ihre Buchung ist eingegangen und wird in Kürze bestätigt.') : 'Ihre Buchung ist eingegangen und wird in Kürze bestätigt.';
$confirmationBookingNo = is_array($i18n ?? null) ? (string) ($i18n['confirmationBookingNo'] ?? 'Buchungs-Nr.') : 'Buchungs-Nr.';
$confirmationService = is_array($i18n ?? null) ? (string) ($i18n['confirmationService'] ?? 'Leistung') : 'Leistung';
$confirmationProvider = is_array($i18n ?? null) ? (string) ($i18n['confirmationProvider'] ?? 'Anbieter') : 'Anbieter';
$confirmationDate = is_array($i18n ?? null) ? (string) ($i18n['confirmationDate'] ?? 'Datum') : 'Datum';
$confirmationTime = is_array($i18n ?? null) ? (string) ($i18n['confirmationTime'] ?? 'Uhrzeit') : 'Uhrzeit';
$confirmationLocation = is_array($i18n ?? null) ? (string) ($i18n['confirmationLocation'] ?? 'Ort') : 'Ort';
$confirmationPrice = is_array($i18n ?? null) ? (string) ($i18n['confirmationPrice'] ?? 'Preis') : 'Preis';
$confirmationAddToCalendar = is_array($i18n ?? null) ? (string) ($i18n['confirmationAddToCalendar'] ?? 'Zum Kalender hinzufügen:') : 'Zum Kalender hinzufügen:';
$confirmationGoogleCalendar = is_array($i18n ?? null) ? (string) ($i18n['confirmationGoogleCalendar'] ?? 'Google Kalender') : 'Google Kalender';
$confirmationPendingNotice = is_array($i18n ?? null) ? (string) ($i18n['confirmationPendingNotice'] ?? 'Sie erhalten eine E-Mail, sobald Ihre Buchung bestätigt wurde.') : 'Sie erhalten eine E-Mail, sobald Ihre Buchung bestätigt wurde.';
$confirmationSentPrefix = is_array($i18n ?? null) ? (string) ($i18n['confirmationSentPrefix'] ?? 'Eine Bestätigung wurde an') : 'Eine Bestätigung wurde an';
$confirmationSentSuffix = is_array($i18n ?? null) ? (string) ($i18n['confirmationSentSuffix'] ?? 'gesendet.') : 'gesendet.';
$confirmationBackHome = is_array($i18n ?? null) ? (string) ($i18n['confirmationBackHome'] ?? '← Zur Startseite') : '← Zur Startseite';
$confirmationOpenMeetingLink = is_array($i18n ?? null) ? (string) ($i18n['confirmationOpenMeetingLink'] ?? 'Meeting-Link öffnen ↗') : 'Meeting-Link öffnen ↗';

$statusLabels = CMS_Booking_Bookings::status_labels();
$statusLabelsEn = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'no_show' => 'No show',
];
$statusClass  = CMS_Booking_Bookings::status_badge_class((string) $booking['status']);
$meetingUrl   = '';
if (!empty($booking['meeting_url']) && filter_var($booking['meeting_url'], FILTER_VALIDATE_URL)) {
    $meetingScheme = strtolower((string) parse_url($booking['meeting_url'], PHP_URL_SCHEME));
    if (in_array($meetingScheme, ['http', 'https'], true)) {
        $meetingUrl = (string) $booking['meeting_url'];
    }
}
try {
    $bookingDate = new DateTime((string) $booking['booking_date']);
    $dayNames = is_array($i18n ?? null) && isset($i18n['weekdayNames']) && is_array($i18n['weekdayNames'])
        ? $i18n['weekdayNames']
        : ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    $formattedDate = $dayNames[(int) $bookingDate->format('w')] . ', ' . $bookingDate->format('d.m.Y');
} catch (\Throwable $e) {
    $formattedDate = (string) ($booking['booking_date'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $e($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($confirmationPageTitle); ?> – <?php echo $e($siteName); ?></title>
    <?php \CMS\Hooks::doAction('head'); ?>
</head>
<body class="booking-page booking-confirmation-page">
    <?php \CMS\Hooks::doAction('body_start'); ?>
    <?php \CMS\ThemeManager::instance()->render('header'); ?>
    <?php \CMS\Hooks::doAction('after_header'); ?>

    <main class="booking-main">
        <div class="booking-container booking-container--narrow">

            <div class="booking-confirmation-card">
                <h1><?php echo $e($confirmationThankYou); ?></h1>
                <p class="booking-confirmation-subtitle">
                    <?php if ($booking['status'] === 'confirmed'): ?>
                        <?php echo $e($confirmationSubtitleConfirmed); ?>
                    <?php else: ?>
                        <?php echo $e($confirmationSubtitlePending); ?>
                    <?php endif; ?>
                </p>

                <!-- Zusammenfassung -->
                <div class="booking-confirmation-summary">
                    <table class="booking-summary-table">
                        <tr>
                            <th><?php echo $e($confirmationBookingNo); ?></th>
                            <td>#<?php echo (int) $booking['id']; ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $e($lang === 'en' ? 'Status' : 'Status'); ?></th>
                            <td>
                                <span class="booking-badge booking-badge--<?php echo $e($statusClass); ?>">
                                    <?php
                                    $statusKey = (string) ($booking['status'] ?? '');
                                    $statusText = $lang === 'en'
                                        ? ($statusLabelsEn[$statusKey] ?? $statusKey)
                                        : ($statusLabels[$statusKey] ?? $statusKey);
                                    echo $e($statusText);
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($booking['service_title'])): ?>
                        <tr>
                            <th><?php echo $e($confirmationService); ?></th>
                            <td><?php echo $e($booking['service_title']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($booking['provider_name'])): ?>
                        <tr>
                            <th><?php echo $e($confirmationProvider); ?></th>
                            <td><?php echo $e($booking['provider_name']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php echo $e($confirmationDate); ?></th>
                            <td>
                                <?php echo $e($formattedDate); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo $e($confirmationTime); ?></th>
                            <td>
                                <?php echo $e(substr((string) $booking['start_time'], 0, 5)); ?> – <?php echo $e(substr((string) $booking['end_time'], 0, 5)); ?> <?php echo $e($clockLabel); ?>
                                (<?php echo (int) $booking['duration_min']; ?> <?php echo $e($minutesShort); ?>)
                            </td>
                        </tr>
                        <?php if (!empty($booking['location_type'])): ?>
                        <tr>
                            <th><?php echo $e($confirmationLocation); ?></th>
                            <td>
                                <?php
                                $locLabels = ['online' => $locationOnlineAppointment, 'onsite' => $locationOnsite, 'hybrid' => $locationOnlineSlashOnsite];
                                echo $e($locLabels[$booking['location_type']] ?? $booking['location_type']);
                                ?>
                                <?php if ($meetingUrl !== ''): ?>
                                <br><a href="<?php echo $e($meetingUrl); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo $e($confirmationOpenMeetingLink); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ((int) $booking['price_cents'] > 0): ?>
                        <tr>
                            <th><?php echo $e($confirmationPrice); ?></th>
                            <td><?php echo CMS_Booking_Services::format_price((int) $booking['price_cents']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Kalender-Links -->
                <div class="booking-confirmation-actions">
                    <p class="booking-confirmation-actions-label"><?php echo $e($confirmationAddToCalendar); ?></p>
                    <div class="booking-confirmation-btns">
                        <a href="<?php echo $e($siteUrl . $bookingBasePath); ?>/ical/<?php echo (int) $booking['id']; ?>?token=<?php echo $e($accessToken ?? ''); ?>"
                           class="booking-btn booking-btn-secondary" download>
                            iCal / Outlook
                        </a>
                        <a href="<?php echo $e($googleUrl); ?>"
                           class="booking-btn booking-btn-secondary" target="_blank" rel="noopener noreferrer">
                            <?php echo $e($confirmationGoogleCalendar); ?>
                        </a>
                    </div>
                </div>

                <!-- Hinweise -->
                <div class="booking-confirmation-notes">
                    <?php if ($booking['status'] === 'pending'): ?>
                    <p><?php echo $e($confirmationPendingNotice); ?></p>
                    <?php endif; ?>
                    <p><?php echo $e($confirmationSentPrefix); ?> <strong><?php echo $e($booking['customer_email']); ?></strong> <?php echo $e($confirmationSentSuffix); ?></p>
                </div>

                <!-- Zurück -->
                <div class="booking-confirmation-back">
                    <a href="<?php echo $e($siteUrl); ?>" class="booking-btn booking-btn-outline"><?php echo $e($confirmationBackHome); ?></a>
                </div>
            </div>

        </div>
    </main>

    <?php \CMS\Hooks::doAction('before_footer'); ?>
    <?php \CMS\ThemeManager::instance()->render('footer'); ?>
    <?php \CMS\Hooks::doAction('body_end'); ?>
</body>
</html>

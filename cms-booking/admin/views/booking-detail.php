<?php
/**
 * Buchung – Detailansicht mit Statusaktionen und interner Notiz
 *
 * @var array<string, mixed>  $booking
 * @var array<string, string> $meta
 * @var array<string, string> $statusLabels
 * @var string $csrfToken, $error
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = (int) $booking['id'];
$status = (string) $booking['status'];
$locations = ['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Online / Vor Ort'];
$meetingUrl = (string) ($booking['meeting_url'] ?? '');
$meetingOk = $meetingUrl !== '' && filter_var($meetingUrl, FILTER_VALIDATE_URL) !== false
    && in_array(strtolower((string) parse_url($meetingUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
$publicUrl = '/booking/confirm/' . $id . '?token=' . rawurlencode(CMS_Booking_Bookings::access_token($id, (string) ($booking['ical_uid'] ?? '')));
$rows = [
    'Status'      => $statusLabels[$status] ?? $status,
    'Datum'       => date('d.m.Y', (int) strtotime((string) $booking['booking_date'])) . ', ' . substr((string) $booking['start_time'], 0, 5) . ' – ' . substr((string) $booking['end_time'], 0, 5) . ' Uhr',
    'Dauer'       => CMS_Booking_Services::format_duration((int) $booking['duration_min']),
    'Anbieter'    => (string) ($booking['provider_name'] ?? '—'),
    'Leistung'    => (string) ($booking['service_title'] ?? '—'),
    'Ort'         => $locations[(string) $booking['location_type']] ?? (string) $booking['location_type'],
    'Preis'       => CMS_Booking_Services::format_price((int) $booking['price_cents'], (string) ($booking['currency'] ?? '')),
    'Kunde'       => (string) $booking['customer_name'],
    'E-Mail'      => (string) $booking['customer_email'],
    'Telefon'     => (string) ($booking['customer_phone'] ?? ''),
    'Kundenkonto' => !empty($booking['user_id']) ? 'Benutzer #' . (int) $booking['user_id'] : 'Gast',
    'Erinnerung'  => !empty($booking['reminder_sent']) ? 'versendet' : 'nicht versendet',
    'Angelegt'    => (string) ($booking['created_at'] ?? ''),
    'Bestätigt'   => (string) ($booking['confirmed_at'] ?? ''),
    'Storniert'   => (string) ($booking['cancelled_at'] ?? ''),
    'Abgeschlossen' => (string) ($booking['completed_at'] ?? ''),
];
?>
<div class="admin-page-header">
    <div>
        <h2>Buchung #<?php echo $id; ?></h2>
        <p><?php echo $e($booking['customer_name']); ?> · <?php echo $e($booking['service_title'] ?? 'Termin'); ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        <a class="btn btn-secondary" href="<?php echo $e($publicUrl); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i> Kundenansicht</a>
    </div>
</div>

<?php self::render_alerts($error); ?>

<div class="booking-admin-grid">
    <section class="admin-card" aria-labelledby="booking-detail-data">
        <h3 id="booking-detail-data">Daten</h3>
        <dl class="booking-admin-dl">
            <?php foreach ($rows as $label => $value): ?>
            <?php if (trim((string) $value) === '') continue; ?>
            <dt><?php echo $e($label); ?></dt>
            <dd><?php echo $e($value); ?></dd>
            <?php endforeach; ?>
            <?php if ($meetingOk): ?>
            <dt>Meeting-Link</dt>
            <dd><a href="<?php echo $e($meetingUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($meetingUrl); ?></a></dd>
            <?php endif; ?>
        </dl>
        <?php if (!empty($booking['notes'])): ?>
        <h4>Nachricht des Kunden</h4>
        <p class="booking-admin-pre"><?php echo $e($booking['notes']); ?></p>
        <?php endif; ?>
        <?php if (!empty($meta)): ?>
        <h4>Zusatzdaten</h4>
        <dl class="booking-admin-dl">
            <?php foreach ($meta as $key => $value): ?>
            <dt><?php echo $e($key); ?></dt>
            <dd><?php echo $e($value); ?></dd>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </section>

    <div class="booking-admin-stack">
        <section class="admin-card" aria-labelledby="booking-detail-actions">
            <h3 id="booking-detail-actions">Status ändern</h3>
            <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['view' => 'detail', 'id' => $id])); ?>" class="booking-admin-actions">
                <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                <input type="hidden" name="return_detail" value="1">
                <?php if ($status !== 'confirmed'): ?>
                <button type="submit" name="row_action" value="confirm:<?php echo $id; ?>" class="btn btn-primary"><i class="ti ti-check" aria-hidden="true"></i> Bestätigen</button>
                <?php endif; ?>
                <?php if ($status !== 'completed'): ?>
                <button type="submit" name="row_action" value="complete:<?php echo $id; ?>" class="btn btn-secondary">Abgeschlossen</button>
                <?php endif; ?>
                <?php if ($status !== 'no_show'): ?>
                <button type="submit" name="row_action" value="no_show:<?php echo $id; ?>" class="btn btn-secondary">Nicht erschienen</button>
                <?php endif; ?>
                <?php if ($status !== 'cancelled'): ?>
                <button type="submit" name="row_action" value="cancel:<?php echo $id; ?>" class="btn btn-secondary"
                        data-cms-confirm="Buchung stornieren? Der Kunde wird benachrichtigt.">Stornieren</button>
                <?php endif; ?>
                <button type="submit" name="row_action" value="delete:<?php echo $id; ?>" class="btn btn-danger"
                        data-cms-confirm="Buchung #<?php echo $id; ?> endgültig löschen?"><i class="ti ti-trash" aria-hidden="true"></i> Löschen</button>
            </form>
        </section>

        <section class="admin-card" aria-labelledby="booking-detail-notes">
            <h3 id="booking-detail-notes">Interne Notiz</h3>
            <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['view' => 'detail', 'id' => $id])); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                <div class="form-group">
                    <label class="form-label visually-hidden" for="booking-internal-notes">Interne Notiz</label>
                    <textarea id="booking-internal-notes" name="internal_notes" class="form-control" rows="5" maxlength="5000"><?php echo $e($booking['internal_notes'] ?? ''); ?></textarea>
                    <small class="form-text">Nur im Admin sichtbar, nicht für Kunden oder Anbieter.</small>
                </div>
                <button type="submit" name="save_notes" value="1" class="btn btn-primary">Notiz speichern</button>
            </form>
        </section>
    </div>
</div>

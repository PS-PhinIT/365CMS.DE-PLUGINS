<?php
/**
 * Buchungsdetail mit Statusaktionen und interner Notiz.
 *
 * @var array<string,mixed> $detail
 * @var array<string,string> $meta
 * @var string $csrfToken
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = (int) $detail['id'];
$status = (string) $detail['status'];
$labels = CMS_Booking_Bookings::status_labels();
$locations = ['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Hybrid'];
$transitions = match ($status) {
    'pending' => ['confirm' => 'Bestätigen', 'cancel' => 'Ablehnen'],
    'confirmed' => ['complete' => 'Abschließen', 'no_show' => 'Nicht erschienen', 'cancel' => 'Stornieren'],
    default => [],
};
$rows = [
    'Termin' => CMS_Booking_Admin_Pages::format_date((string) $detail['booking_date']) . ', ' . substr((string) $detail['start_time'], 0, 5) . '–' . substr((string) $detail['end_time'], 0, 5) . ' (' . (int) $detail['duration_min'] . ' Min.)',
    'Leistung' => (string) ($detail['service_title'] ?? '–'),
    'Anbieter' => (string) ($detail['provider_name'] ?? '–'),
    'Ort' => $locations[(string) $detail['location_type']] ?? (string) $detail['location_type'],
    'Preis' => CMS_Booking_Services::format_price((int) $detail['price_cents'], (string) $detail['currency']),
    'Kunde' => (string) $detail['customer_name'],
    'E-Mail' => (string) $detail['customer_email'],
    'Telefon' => (string) ($detail['customer_phone'] ?? '') !== '' ? (string) $detail['customer_phone'] : '–',
    'Erstellt' => (string) $detail['created_at'],
    'Erinnerung' => !empty($detail['reminder_sent']) ? 'versendet' : 'ausstehend',
];
?>
<div class="admin-page-header">
    <div>
        <h2>Buchung #<?php echo $id; ?> <span class="bk-badge bk-badge--<?php echo $e(CMS_Booking_Admin_Pages::status_tone($status)); ?>"><?php echo $e($labels[$status] ?? $status); ?></span></h2>
        <p><?php echo $e($detail['customer_name']); ?> · <?php echo $e($rows['Termin']); ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('bookings')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Alle Buchungen</a>
    </div>
</div>

<div class="bk-split">
    <section class="admin-card">
        <div class="bk-panel-header"><h3>Details</h3></div>
        <dl class="bk-dl">
            <?php foreach ($rows as $label => $value): ?>
                <dt><?php echo $e($label); ?></dt><dd><?php echo $e($value); ?></dd>
            <?php endforeach; ?>
            <?php if (!empty($detail['meeting_url'])): ?>
                <dt>Meeting</dt><dd><a href="<?php echo $e($detail['meeting_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($detail['meeting_url']); ?></a></dd>
            <?php endif; ?>
            <?php foreach ($meta as $key => $value): ?>
                <dt><?php echo $e($key); ?></dt><dd><?php echo $e($value); ?></dd>
            <?php endforeach; ?>
        </dl>
        <?php if (!empty($detail['notes'])): ?>
            <h4 class="bk-subheading">Nachricht des Kunden</h4>
            <p class="bk-prewrap"><?php echo $e($detail['notes']); ?></p>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <div class="bk-panel-header"><h3>Aktionen</h3></div>
        <?php if ($transitions === []): ?>
            <p class="bk-muted">Für den Status „<?php echo $e($labels[$status] ?? $status); ?>“ sind keine weiteren Statuswechsel vorgesehen.</p>
        <?php else: ?>
            <div class="bk-actions bk-actions--start">
                <?php foreach ($transitions as $action => $label): ?>
                    <form method="post"<?php echo $action === 'cancel' ? ' data-cms-confirm="Buchung wirklich ' . $e(strtolower($label)) . '? Der Kunde wird benachrichtigt."' : ''; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                        <input type="hidden" name="booking_admin_action" value="booking_status">
                        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="status" value="<?php echo $e($action); ?>">
                        <input type="hidden" name="return_view" value="1">
                        <button type="submit" class="btn <?php echo $action === 'cancel' ? 'btn-outline-danger' : ($action === 'confirm' || $action === 'complete' ? 'btn-primary' : 'btn-outline-secondary'); ?>"><?php echo $e($label); ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-form bk-notes">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="booking_notes">
            <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
            <label class="form-label" for="bk-internal-notes">Interne Notiz</label>
            <textarea id="bk-internal-notes" name="internal_notes" class="form-control" rows="5" maxlength="5000"><?php echo $e($detail['internal_notes'] ?? ''); ?></textarea>
            <div class="form-hint">Nur im Admin sichtbar; wird nicht an Kunden gesendet.</div>
            <button type="submit" class="btn btn-secondary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Notiz speichern</button>
        </form>

        <form method="post" class="bk-delete" data-cms-confirm="Buchung #<?php echo $id; ?> endgültig löschen? Es wird keine E-Mail versendet.">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="booking_status">
            <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
            <input type="hidden" name="status" value="delete">
            <button type="submit" class="btn btn-outline-danger"><i class="ti ti-trash" aria-hidden="true"></i> Buchung löschen</button>
        </form>
    </section>
</div>

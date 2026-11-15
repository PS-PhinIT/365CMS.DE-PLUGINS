<?php
/**
 * Übersicht – Zusatzinhalt unter cms_plugin_dashboard(): nächste Termine.
 *
 * @var list<array<string, mixed>> $upcoming
 * @var array<string, string>      $statusLabels
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="admin-card booking-admin-section" aria-labelledby="booking-upcoming-heading">
    <div class="booking-admin-section__head">
        <h3 id="booking-upcoming-heading">Nächste Termine</h3>
        <a class="btn btn-secondary btn-sm" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['date_from' => date('Y-m-d')])); ?>">Alle anstehenden</a>
    </div>
    <?php if (empty($upcoming)): ?>
        <p class="booking-admin-empty">Keine anstehenden Termine.</p>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th scope="col">Datum</th>
                        <th scope="col">Kunde</th>
                        <th scope="col">Anbieter / Leistung</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $item): ?>
                    <?php $status = (string) $item['status']; ?>
                    <tr>
                        <td>
                            <?php echo $e(date('d.m.Y', (int) strtotime((string) $item['booking_date']))); ?>
                            <div class="booking-admin-muted"><?php echo $e(substr((string) $item['start_time'], 0, 5)); ?> – <?php echo $e(substr((string) $item['end_time'], 0, 5)); ?></div>
                        </td>
                        <td><a href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['view' => 'detail', 'id' => (int) $item['id']])); ?>"><?php echo $e($item['customer_name']); ?></a></td>
                        <td>
                            <?php echo $e($item['provider_name'] ?? '—'); ?>
                            <div class="booking-admin-muted"><?php echo $e($item['service_title'] ?? '—'); ?></div>
                        </td>
                        <td><span class="status-badge <?php echo $e(CMS_Booking_Bookings::status_badge_class($status)); ?>"><?php echo $e($statusLabels[$status] ?? $status); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

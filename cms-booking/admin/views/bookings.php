<?php
/**
 * Buchungen – Header → Filter → Sammelaktionen/Tabelle → Paginierung
 *
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $filters
 * @var array<string, string|int>  $query
 * @var array<string, string>      $statusLabels
 * @var list<array<string, mixed>> $providers
 * @var string $csrfToken, $exportToken, $error
 * @var int    $page, $pages, $total
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$hiddenFilters = static function (array $filters) use ($e): string {
    $html = '';
    foreach (['status' => $filters['status'], 'q' => $filters['search'], 'provider_id' => $filters['provider_id'] ?: '', 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']] as $name => $value) {
        $html .= '<input type="hidden" name="' . $e($name) . '" value="' . $e($value) . '">';
    }
    return $html;
};
?>
<div class="admin-page-header">
    <div>
        <h2>Buchungen</h2>
        <p>Anfragen bestätigen, Termine verwalten und exportieren (<?php echo (int) $total; ?> Treffer).</p>
    </div>
    <div class="header-actions">
        <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings')); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $e($exportToken); ?>">
            <input type="hidden" name="export_csv" value="1">
            <?php echo $hiddenFilters($filters); ?>
            <button type="submit" class="btn btn-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV exportieren</button>
        </form>
    </div>
</div>

<?php self::render_alerts($error); ?>

<form method="get" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings')); ?>" class="admin-card booking-admin-toolbar" role="search">
    <div class="form-group">
        <label class="form-label" for="booking-filter-status">Status</label>
        <select id="booking-filter-status" name="status" class="form-control" data-booking-autosubmit>
            <option value="">Alle</option>
            <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?php echo $e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="booking-filter-provider">Anbieter</label>
        <select id="booking-filter-provider" name="provider_id" class="form-control" data-booking-autosubmit>
            <option value="">Alle Anbieter</option>
            <?php foreach ($providers as $prov): ?>
            <option value="<?php echo (int) $prov['id']; ?>" <?php echo (int) $filters['provider_id'] === (int) $prov['id'] ? 'selected' : ''; ?>><?php echo $e($prov['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="booking-filter-from">Von</label>
        <input id="booking-filter-from" type="date" name="date_from" class="form-control" value="<?php echo $e($filters['date_from']); ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="booking-filter-to">Bis</label>
        <input id="booking-filter-to" type="date" name="date_to" class="form-control" value="<?php echo $e($filters['date_to']); ?>">
    </div>
    <div class="form-group booking-admin-toolbar__grow">
        <label class="form-label" for="booking-filter-q">Suche</label>
        <input id="booking-filter-q" type="search" name="q" class="form-control" placeholder="Name, E-Mail, Telefon, Nr." value="<?php echo $e($filters['search']); ?>">
    </div>
    <div class="booking-admin-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <a class="btn btn-link" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings')); ?>">Zurücksetzen</a>
    </div>
</form>

<div class="admin-card">
    <?php if (empty($items)): ?>
        <p class="booking-admin-empty">Keine Buchungen gefunden. Passen Sie die Filter an oder warten Sie auf neue Buchungen.</p>
    <?php else: ?>
    <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', $query + ['paged' => $page])); ?>" data-booking-bulk-form>
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <?php echo $hiddenFilters($filters); ?>
        <div class="booking-admin-bulkbar">
            <label class="visually-hidden" for="booking-bulk-action">Sammelaktion</label>
            <select id="booking-bulk-action" name="bulk_action" class="form-control">
                <option value="">Sammelaktion wählen …</option>
                <option value="confirm">Bestätigen</option>
                <option value="complete">Als abgeschlossen markieren</option>
                <option value="no_show">Als „Nicht erschienen“ markieren</option>
                <option value="cancel">Stornieren</option>
                <option value="pending">Auf „Offen“ zurücksetzen</option>
                <option value="delete">Löschen</option>
            </select>
            <button type="submit" name="apply_bulk" value="1" class="btn btn-secondary" data-booking-bulk-submit
                    data-cms-confirm="Sammelaktion auf alle ausgewählten Buchungen anwenden?">Anwenden</button>
            <span class="booking-admin-muted" data-booking-selected-count aria-live="polite"></span>
        </div>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th scope="col" class="booking-admin-col-check"><input type="checkbox" data-booking-check-all aria-label="Alle auswählen"></th>
                        <th scope="col">#</th>
                        <th scope="col">Kunde</th>
                        <th scope="col">Anbieter / Leistung</th>
                        <th scope="col">Datum / Zeit</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php $id = (int) $item['id']; $status = (string) $item['status']; ?>
                    <tr>
                        <td class="booking-admin-col-check"><input type="checkbox" name="ids[]" value="<?php echo $id; ?>" data-booking-check aria-label="Buchung #<?php echo $id; ?> auswählen"></td>
                        <td><a href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['view' => 'detail', 'id' => $id])); ?>">#<?php echo $id; ?></a></td>
                        <td>
                            <strong><?php echo $e($item['customer_name']); ?></strong>
                            <div class="booking-admin-muted"><?php echo $e($item['customer_email']); ?></div>
                            <?php if (!empty($item['customer_phone'])): ?>
                            <div class="booking-admin-muted"><?php echo $e($item['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $e($item['provider_name'] ?? '—'); ?>
                            <div class="booking-admin-muted"><?php echo $e($item['service_title'] ?? '—'); ?></div>
                        </td>
                        <td>
                            <?php echo $e(date('d.m.Y', (int) strtotime((string) $item['booking_date']))); ?>
                            <div class="booking-admin-muted"><?php echo $e(substr((string) $item['start_time'], 0, 5)); ?> – <?php echo $e(substr((string) $item['end_time'], 0, 5)); ?></div>
                        </td>
                        <td><span class="status-badge <?php echo $e(CMS_Booking_Bookings::status_badge_class($status)); ?>"><?php echo $e($statusLabels[$status] ?? $status); ?></span></td>
                        <td>
                            <div class="booking-admin-actions">
                                <a class="btn btn-sm btn-secondary" title="Details" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('bookings', ['view' => 'detail', 'id' => $id])); ?>"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Details</span></a>
                                <?php if ($status === 'pending'): ?>
                                <button type="submit" name="row_action" value="confirm:<?php echo $id; ?>" class="btn btn-sm btn-primary" title="Bestätigen"><i class="ti ti-check" aria-hidden="true"></i><span class="visually-hidden">Bestätigen</span></button>
                                <?php endif; ?>
                                <?php if ($status === 'confirmed'): ?>
                                <button type="submit" name="row_action" value="complete:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="Abschließen"><i class="ti ti-circle-check" aria-hidden="true"></i><span class="visually-hidden">Abschließen</span></button>
                                <?php endif; ?>
                                <?php if (in_array($status, ['pending', 'confirmed'], true)): ?>
                                <button type="submit" name="row_action" value="cancel:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="Stornieren"
                                        data-cms-confirm="Buchung #<?php echo $id; ?> stornieren? Der Kunde wird benachrichtigt."><i class="ti ti-x" aria-hidden="true"></i><span class="visually-hidden">Stornieren</span></button>
                                <?php endif; ?>
                                <button type="submit" name="row_action" value="delete:<?php echo $id; ?>" class="btn btn-sm btn-danger" title="Löschen"
                                        data-cms-confirm="Buchung #<?php echo $id; ?> endgültig löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php self::render_pagination('bookings', $query, $page, $pages); ?>
    <?php endif; ?>
</div>

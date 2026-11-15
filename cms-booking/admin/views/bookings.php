<?php
/**
 * Buchungen: Filter-Toolbar, Tabelle, Statusaktionen, CSV-Export.
 *
 * @var list<array<string,mixed>> $items
 * @var array{status:string,q:string,provider_id:int} $filters
 * @var int $paged
 * @var int $pages
 * @var int $total
 * @var array<string,string> $statusLabels
 * @var list<array<string,mixed>> $providers
 * @var string $csrfToken
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Booking_Admin_Pages::url('bookings');
$filtered = $filters['status'] !== '' || $filters['q'] !== '' || $filters['provider_id'] > 0;
$actionsFor = static fn (string $status): array => match ($status) {
    'pending' => ['confirm' => ['Bestätigen', 'ti ti-check', 'btn-outline-success'], 'cancel' => ['Ablehnen', 'ti ti-x', 'btn-outline-danger']],
    'confirmed' => ['complete' => ['Abschließen', 'ti ti-circle-check', 'btn-outline-success'], 'no_show' => ['Nicht erschienen', 'ti ti-user-off', 'btn-outline-secondary'], 'cancel' => ['Stornieren', 'ti ti-x', 'btn-outline-danger']],
    default => [],
};
?>
<div class="admin-page-header">
    <div>
        <h2>Buchungen</h2>
        <p><?php echo (int) $total; ?> Buchung(en)<?php echo $filtered ? ' im aktuellen Filter' : ''; ?>. Bestätigen, abschließen oder stornieren – Kunden werden per E-Mail informiert.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::export_url(['status' => $filters['status'], 'q' => $filters['q'], 'provider_id' => $filters['provider_id']])); ?>"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV-Export</a>
    </div>
</div>

<form method="get" action="<?php echo $e($listUrl); ?>" class="bk-toolbar" role="search" aria-label="Buchungen filtern">
    <div class="bk-toolbar__field">
        <label class="form-label" for="bk-q">Suche</label>
        <input id="bk-q" type="search" name="q" class="form-control" value="<?php echo $e($filters['q']); ?>" placeholder="Name, E-Mail, Anbieter">
    </div>
    <div class="bk-toolbar__field">
        <label class="form-label" for="bk-status">Status</label>
        <select id="bk-status" name="status" class="form-select">
            <option value="">Alle</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bk-toolbar__field">
        <label class="form-label" for="bk-provider">Anbieter</label>
        <select id="bk-provider" name="provider_id" class="form-select">
            <option value="0">Alle</option>
            <?php foreach ($providers as $provider): ?>
                <option value="<?php echo (int) $provider['id']; ?>" <?php echo $filters['provider_id'] === (int) $provider['id'] ? 'selected' : ''; ?>><?php echo $e($provider['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bk-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filtered): ?><a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Zurücksetzen</a><?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($items === []): ?>
        <div class="empty-state"><p><strong>Keine Buchungen gefunden</strong></p><p class="bk-muted"><?php echo $filtered ? 'Passen Sie die Filter an.' : 'Neue Buchungen erscheinen hier automatisch.'; ?></p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">#</th><th scope="col">Termin</th><th scope="col">Kunde</th><th scope="col">Anbieter / Leistung</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $id = (int) $item['id']; $status = (string) $item['status']; ?>
                    <tr>
                        <td><a class="bk-link" href="<?php echo $e(CMS_Booking_Admin_Pages::url('bookings', ['view' => $id])); ?>"><?php echo $id; ?></a></td>
                        <td class="bk-nowrap"><?php echo $e(CMS_Booking_Admin_Pages::format_date((string) $item['booking_date'])); ?><div class="bk-muted"><?php echo $e(substr((string) $item['start_time'], 0, 5) . '–' . substr((string) $item['end_time'], 0, 5)); ?></div></td>
                        <td><?php echo $e($item['customer_name']); ?><div class="bk-muted"><?php echo $e($item['customer_email']); ?></div></td>
                        <td><?php echo $e($item['provider_name'] ?? '–'); ?><div class="bk-muted"><?php echo $e($item['service_title'] ?? '–'); ?></div></td>
                        <td><span class="bk-badge bk-badge--<?php echo $e(CMS_Booking_Admin_Pages::status_tone($status)); ?>"><?php echo $e($statusLabels[$status] ?? $status); ?></span></td>
                        <td>
                            <div class="bk-actions">
                                <?php foreach ($actionsFor($status) as $action => [$label, $icon, $class]): ?>
                                    <form method="post"<?php echo $action === 'cancel' ? ' data-cms-confirm="Buchung #' . $id . ' wirklich ' . ($status === 'pending' ? 'ablehnen' : 'stornieren') . '? Der Kunde wird benachrichtigt."' : ''; ?>>
                                        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                        <input type="hidden" name="booking_admin_action" value="booking_status">
                                        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="status" value="<?php echo $e($action); ?>">
                                        <?php foreach (['status' => $filters['status'], 'q' => $filters['q'], 'provider_id' => $filters['provider_id'], 'paged' => $paged] as $k => $v): ?>
                                            <input type="hidden" name="return_<?php echo $e($k); ?>" value="<?php echo $e($v); ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-sm <?php echo $e($class); ?>" title="<?php echo $e($label); ?>"><i class="<?php echo $e($icon); ?>" aria-hidden="true"></i><span class="visually-hidden"><?php echo $e($label); ?></span></button>
                                    </form>
                                <?php endforeach; ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('bookings', ['view' => $id])); ?>" title="Details"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Details</span></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="bk-pagination" aria-label="Seiten">
                <?php $base = array_filter(['status' => $filters['status'], 'q' => $filters['q'], 'provider_id' => $filters['provider_id']]); ?>
                <?php if ($paged > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('bookings', $base + ['paged' => $paged - 1])); ?>">Zurück</a><?php endif; ?>
                <span class="bk-muted">Seite <?php echo $paged; ?> von <?php echo $pages; ?></span>
                <?php if ($paged < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('bookings', $base + ['paged' => $paged + 1])); ?>">Weiter</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

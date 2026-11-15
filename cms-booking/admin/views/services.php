<?php
/**
 * Leistungen – Header → Filter → Sammelaktionen/Tabelle → Paginierung
 *
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $providers
 * @var array<string, string|int>  $query
 * @var string $csrfToken, $error, $search, $status
 * @var int    $page, $pages, $total, $providerId
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$locationLabels = ['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Hybrid'];
$typeLabels = ['confirmation' => 'Bestätigung', 'instant' => 'Sofort', 'request' => 'Anfrage'];
?>
<div class="admin-page-header">
    <div>
        <h2>Leistungen</h2>
        <p>Buchbare Leistungen je Anbieter mit Dauer, Puffer, Preis und Buchungsart (<?php echo (int) $total; ?>).</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', ['view' => 'edit', 'provider_id' => $providerId])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Leistung</a>
    </div>
</div>

<?php self::render_alerts($error); ?>

<form method="get" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services')); ?>" class="admin-card booking-admin-toolbar" role="search">
    <div class="form-group">
        <label class="form-label" for="service-filter-provider">Anbieter</label>
        <select id="service-filter-provider" name="provider_id" class="form-control" data-booking-autosubmit>
            <option value="">Alle Anbieter</option>
            <?php foreach ($providers as $prov): ?>
            <option value="<?php echo (int) $prov['id']; ?>" <?php echo $providerId === (int) $prov['id'] ? 'selected' : ''; ?>><?php echo $e($prov['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="service-filter-status">Status</label>
        <select id="service-filter-status" name="status" class="form-control" data-booking-autosubmit>
            <option value="">Alle</option>
            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktiv</option>
            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inaktiv</option>
        </select>
    </div>
    <div class="form-group booking-admin-toolbar__grow">
        <label class="form-label" for="service-filter-q">Suche</label>
        <input id="service-filter-q" type="search" name="q" class="form-control" placeholder="Titel oder Anbieter" value="<?php echo $e($search); ?>">
    </div>
    <div class="booking-admin-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <a class="btn btn-link" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services')); ?>">Zurücksetzen</a>
    </div>
</form>

<div class="admin-card">
    <?php if (empty($items)): ?>
        <p class="booking-admin-empty">Keine Leistungen gefunden. Legen Sie über „Neue Leistung“ die erste buchbare Leistung an.</p>
    <?php else: ?>
    <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', $query + ['paged' => $page])); ?>" data-booking-bulk-form>
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="q_filter" value="<?php echo $e($search); ?>">
        <input type="hidden" name="provider_filter" value="<?php echo (int) $providerId; ?>">
        <div class="booking-admin-bulkbar">
            <label class="visually-hidden" for="service-bulk-action">Sammelaktion</label>
            <select id="service-bulk-action" name="bulk_action" class="form-control">
                <option value="">Sammelaktion wählen …</option>
                <option value="activate">Aktivieren</option>
                <option value="deactivate">Deaktivieren</option>
                <option value="delete">Löschen</option>
            </select>
            <button type="submit" name="apply_bulk" value="1" class="btn btn-secondary" data-booking-bulk-submit
                    data-cms-confirm="Sammelaktion auf alle ausgewählten Leistungen anwenden?">Anwenden</button>
            <span class="booking-admin-muted" data-booking-selected-count aria-live="polite"></span>
        </div>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th scope="col" class="booking-admin-col-check"><input type="checkbox" data-booking-check-all aria-label="Alle auswählen"></th>
                        <th scope="col">Leistung</th>
                        <th scope="col">Anbieter</th>
                        <th scope="col">Dauer</th>
                        <th scope="col">Preis</th>
                        <th scope="col">Ort / Art</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php $id = (int) $item['id']; $active = (string) $item['status'] === 'active'; ?>
                    <tr>
                        <td class="booking-admin-col-check"><input type="checkbox" name="ids[]" value="<?php echo $id; ?>" data-booking-check aria-label="<?php echo $e($item['title']); ?> auswählen"></td>
                        <td>
                            <a href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', ['view' => 'edit', 'id' => $id])); ?>"><strong><?php echo $e($item['title']); ?></strong></a>
                            <?php if (!empty($item['description'])): ?>
                            <div class="booking-admin-muted booking-admin-truncate"><?php echo $e(mb_substr(strip_tags((string) $item['description']), 0, 120)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $e($item['provider_name'] ?? '—'); ?></td>
                        <td><?php echo $e(CMS_Booking_Services::format_duration((int) $item['duration_min'])); ?><div class="booking-admin-muted">+ <?php echo (int) $item['buffer_min']; ?> Min. Puffer</div></td>
                        <td><?php echo $e(CMS_Booking_Services::format_price((int) $item['price_cents'], (string) ($item['provider_currency'] ?? ''))); ?></td>
                        <td><?php echo $e($locationLabels[(string) $item['location_type']] ?? $item['location_type']); ?><div class="booking-admin-muted"><?php echo $e($typeLabels[(string) $item['booking_type']] ?? $item['booking_type']); ?></div></td>
                        <td><span class="status-badge <?php echo $active ? 'active' : 'inactive'; ?>"><?php echo $active ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                        <td>
                            <div class="booking-admin-actions">
                                <a class="btn btn-sm btn-secondary" title="Bearbeiten" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', ['view' => 'edit', 'id' => $id])); ?>"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <button type="submit" name="row_action" value="<?php echo $active ? 'deactivate' : 'activate'; ?>:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="<?php echo $active ? 'Deaktivieren' : 'Aktivieren'; ?>"><i class="ti <?php echo $active ? 'ti-player-pause' : 'ti-player-play'; ?>" aria-hidden="true"></i><span class="visually-hidden"><?php echo $active ? 'Deaktivieren' : 'Aktivieren'; ?></span></button>
                                <?php if (!empty($item['provider_slug'])): ?>
                                <a class="btn btn-sm btn-secondary" title="Buchungsseite" href="/booking/<?php echo $e(rawurlencode((string) $item['provider_slug'])); ?>/<?php echo $e(rawurlencode((string) $item['slug'])); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i><span class="visually-hidden">Buchungsseite</span></a>
                                <?php endif; ?>
                                <button type="submit" name="row_action" value="delete:<?php echo $id; ?>" class="btn btn-sm btn-danger" title="Löschen"
                                        data-cms-confirm="Leistung „<?php echo $e($item['title']); ?>“ löschen? Bestehende Buchungen bleiben erhalten."><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php self::render_pagination('services', $query, $page, $pages); ?>
    <?php endif; ?>
</div>

<?php
/**
 * Anbieter – Header → Filter → Sammelaktionen/Tabelle → Paginierung
 *
 * @var list<array<string, mixed>> $items
 * @var array<string, array>       $types
 * @var array<string, string>      $query
 * @var string $csrfToken, $error, $status, $search
 * @var int    $page, $pages, $total
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusNames = ['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'pending' => 'Ausstehend'];
?>
<div class="admin-page-header">
    <div>
        <h2>Anbieter</h2>
        <p>Buchbare Personen und Unternehmen – manuell oder aus integrierten Plugins (<?php echo (int) $total; ?>).</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'edit'])); ?>"><i class="ti ti-user-plus" aria-hidden="true"></i> Anbieter anlegen</a>
    </div>
</div>

<?php self::render_alerts($error); ?>

<form method="get" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers')); ?>" class="admin-card booking-admin-toolbar" role="search">
    <div class="form-group">
        <label class="form-label" for="provider-filter-status">Status</label>
        <select id="provider-filter-status" name="status" class="form-control" data-booking-autosubmit>
            <option value="">Alle</option>
            <?php foreach ($statusNames as $key => $label): ?>
            <option value="<?php echo $e($key); ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group booking-admin-toolbar__grow">
        <label class="form-label" for="provider-filter-q">Suche</label>
        <input id="provider-filter-q" type="search" name="q" class="form-control" placeholder="Name, E-Mail, Slug" value="<?php echo $e($search); ?>">
    </div>
    <div class="booking-admin-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <a class="btn btn-link" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers')); ?>">Zurücksetzen</a>
    </div>
</form>

<div class="admin-card">
    <?php if (empty($items)): ?>
        <p class="booking-admin-empty">Keine Anbieter gefunden. Legen Sie einen Anbieter an oder aktivieren Sie ein integriertes Plugin.</p>
    <?php else: ?>
    <form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', $query + ['paged' => $page])); ?>" data-booking-bulk-form>
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="status_filter" value="<?php echo $e($status); ?>">
        <input type="hidden" name="q_filter" value="<?php echo $e($search); ?>">
        <div class="booking-admin-bulkbar">
            <label class="visually-hidden" for="provider-bulk-action">Sammelaktion</label>
            <select id="provider-bulk-action" name="bulk_action" class="form-control">
                <option value="">Sammelaktion wählen …</option>
                <option value="activate">Aktivieren</option>
                <option value="deactivate">Deaktivieren</option>
                <option value="delete">Löschen (inkl. Leistungen und Buchungen)</option>
            </select>
            <button type="submit" name="apply_bulk" value="1" class="btn btn-secondary" data-booking-bulk-submit
                    data-cms-confirm="Sammelaktion auf alle ausgewählten Anbieter anwenden? Beim Löschen werden auch Leistungen und Buchungen entfernt.">Anwenden</button>
            <span class="booking-admin-muted" data-booking-selected-count aria-live="polite"></span>
        </div>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th scope="col" class="booking-admin-col-check"><input type="checkbox" data-booking-check-all aria-label="Alle auswählen"></th>
                        <th scope="col">Anbieter</th>
                        <th scope="col">Herkunft</th>
                        <th scope="col">E-Mail</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php
                    $id = (int) $item['id'];
                    $source = (string) $item['source_plugin'];
                    $isManual = $source === CMS_Booking_Providers::SOURCE_MANUAL;
                    $typeLabel = $isManual ? 'Manuell' : (string) ($types[$source]['label'] ?? $source);
                    $itemStatus = (string) $item['status'];
                    ?>
                    <tr>
                        <td class="booking-admin-col-check"><input type="checkbox" name="ids[]" value="<?php echo $id; ?>" data-booking-check aria-label="<?php echo $e($item['display_name']); ?> auswählen"></td>
                        <td>
                            <a href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'edit', 'id' => $id])); ?>"><strong><?php echo $e($item['display_name']); ?></strong></a>
                            <div class="booking-admin-muted"><code>/booking/<?php echo $e($item['slug']); ?></code></div>
                        </td>
                        <td><?php echo $e($typeLabel); ?></td>
                        <td><?php echo $e($item['email'] ?? '—'); ?></td>
                        <td><span class="status-badge <?php echo $e($itemStatus === 'active' ? 'active' : 'inactive'); ?>"><?php echo $e($statusNames[$itemStatus] ?? $itemStatus); ?></span></td>
                        <td>
                            <div class="booking-admin-actions">
                                <a class="btn btn-sm btn-secondary" title="Bearbeiten" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'edit', 'id' => $id])); ?>"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <a class="btn btn-sm btn-secondary" title="Verfügbarkeit" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'availability', 'id' => $id])); ?>"><i class="ti ti-calendar-time" aria-hidden="true"></i><span class="visually-hidden">Verfügbarkeit</span></a>
                                <?php if (!$isManual && CMS_Booking_Integration::is_registered($source)): ?>
                                <button type="submit" name="row_action" value="sync:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="Synchronisieren"><i class="ti ti-refresh" aria-hidden="true"></i><span class="visually-hidden">Synchronisieren</span></button>
                                <?php endif; ?>
                                <?php if ($itemStatus === 'active'): ?>
                                <button type="submit" name="row_action" value="deactivate:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="Deaktivieren"><i class="ti ti-player-pause" aria-hidden="true"></i><span class="visually-hidden">Deaktivieren</span></button>
                                <?php else: ?>
                                <button type="submit" name="row_action" value="activate:<?php echo $id; ?>" class="btn btn-sm btn-secondary" title="Aktivieren"><i class="ti ti-player-play" aria-hidden="true"></i><span class="visually-hidden">Aktivieren</span></button>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-secondary" title="Buchungsseite" href="/booking/<?php echo $e(rawurlencode((string) $item['slug'])); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i><span class="visually-hidden">Buchungsseite</span></a>
                                <button type="submit" name="row_action" value="delete:<?php echo $id; ?>" class="btn btn-sm btn-danger" title="Löschen"
                                        data-cms-confirm="Anbieter „<?php echo $e($item['display_name']); ?>“ samt Leistungen, Verfügbarkeiten und Buchungen löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php self::render_pagination('providers', $query, $page, $pages); ?>
    <?php endif; ?>
</div>

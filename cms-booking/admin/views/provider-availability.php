<?php
/**
 * Anbieter – Verfügbarkeit (Wochenplan + Ausnahmen)
 *
 * @var array<string, mixed>                          $provider
 * @var array<int, list<array{0: string, 1: string}>> $weekly
 * @var list<array<string, mixed>>                    $overrides
 * @var string $csrfToken, $error
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$providerId = (int) $provider['id'];
$action = CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'availability', 'id' => $providerId]);
?>
<div class="admin-page-header">
    <div>
        <h2>Verfügbarkeit: <?php echo $e($provider['display_name']); ?></h2>
        <p>Wochenplan (bis zu zwei Zeitfenster je Tag) und Ausnahmen wie Urlaub oder Sondertermine – Zeitzone <?php echo $e($provider['timezone'] ?? ''); ?>.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'edit', 'id' => $providerId])); ?>"><i class="ti ti-pencil" aria-hidden="true"></i> Stammdaten</a>
    </div>
</div>

<?php self::render_alerts($error); ?>

<form method="post" action="<?php echo $e($action); ?>" class="admin-card admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="provider_id" value="<?php echo $providerId; ?>">
    <h3>Wochenplan</h3>
    <div class="users-table-container">
        <table class="users-table booking-admin-weekly">
            <thead>
                <tr>
                    <th scope="col">Tag</th>
                    <th scope="col">Zeitfenster 1</th>
                    <th scope="col">Zeitfenster 2 (optional)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (CMS_Booking_Availability::day_labels() as $dow => $label): ?>
                <?php $ranges = $weekly[$dow] ?? []; ?>
                <tr>
                    <td>
                        <label class="booking-admin-check">
                            <input type="checkbox" name="weekly[<?php echo (int) $dow; ?>][enabled]" value="1" <?php echo $ranges !== [] ? 'checked' : ''; ?>>
                            <span><?php echo $e($label); ?></span>
                        </label>
                    </td>
                    <?php foreach ([1, 2] as $slotNo): ?>
                    <?php $range = $ranges[$slotNo - 1] ?? ['', '']; ?>
                    <td>
                        <div class="booking-admin-timerange">
                            <label class="visually-hidden" for="w-<?php echo (int) $dow; ?>-s<?php echo $slotNo; ?>"><?php echo $e($label); ?> Beginn <?php echo $slotNo; ?></label>
                            <input id="w-<?php echo (int) $dow; ?>-s<?php echo $slotNo; ?>" type="time" name="weekly[<?php echo (int) $dow; ?>][start<?php echo $slotNo; ?>]" class="form-control" value="<?php echo $e($range[0]); ?>">
                            <span aria-hidden="true">–</span>
                            <label class="visually-hidden" for="w-<?php echo (int) $dow; ?>-e<?php echo $slotNo; ?>"><?php echo $e($label); ?> Ende <?php echo $slotNo; ?></label>
                            <input id="w-<?php echo (int) $dow; ?>-e<?php echo $slotNo; ?>" type="time" name="weekly[<?php echo (int) $dow; ?>][end<?php echo $slotNo; ?>]" class="form-control" value="<?php echo $e($range[1]); ?>">
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions">
        <button type="submit" name="save_weekly" value="1" class="btn btn-primary">Wochenplan speichern</button>
    </div>
</form>

<div class="booking-admin-grid">
    <form method="post" action="<?php echo $e($action); ?>" class="admin-card admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="provider_id" value="<?php echo $providerId; ?>">
        <h3>Ausnahme hinzufügen</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="override-date">Datum</label>
                <input id="override-date" type="date" name="override_date" class="form-control" required min="<?php echo $e(date('Y-m-d')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="override-type">Art</label>
                <select id="override-type" name="override_type" class="form-control">
                    <option value="blocked">Nicht verfügbar (Urlaub, Termin)</option>
                    <option value="extra">Zusätzlich verfügbar</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="override-start">Von</label>
                <input id="override-start" type="time" name="override_start" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label" for="override-end">Bis</label>
                <input id="override-end" type="time" name="override_end" class="form-control">
            </div>
        </div>
        <small class="form-text">Ohne Uhrzeit wird bei „Nicht verfügbar“ der ganze Tag gesperrt.</small>
        <div class="form-actions">
            <button type="submit" name="add_override" value="1" class="btn btn-primary">Hinzufügen</button>
        </div>
    </form>

    <section class="admin-card" aria-labelledby="override-list-heading">
        <h3 id="override-list-heading">Anstehende Ausnahmen</h3>
        <?php if (empty($overrides)): ?>
            <p class="booking-admin-empty">Keine Ausnahmen eingetragen.</p>
        <?php else: ?>
        <form method="post" action="<?php echo $e($action); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="provider_id" value="<?php echo $providerId; ?>">
            <ul class="booking-admin-list">
                <?php foreach ($overrides as $ov): ?>
                <li class="booking-admin-list__item">
                    <span>
                        <strong><?php echo $e(date('d.m.Y', (int) strtotime((string) $ov['specific_date']))); ?></strong>
                        <?php echo $e(substr((string) $ov['start_time'], 0, 5)); ?> – <?php echo $e(substr((string) $ov['end_time'], 0, 5)); ?>
                        <span class="status-badge <?php echo (int) $ov['is_blocked'] === 1 ? 'inactive' : 'active'; ?>"><?php echo (int) $ov['is_blocked'] === 1 ? 'gesperrt' : 'zusätzlich'; ?></span>
                    </span>
                    <button type="submit" name="delete_override" value="<?php echo (int) $ov['id']; ?>" class="btn btn-sm btn-danger" title="Löschen"
                            data-cms-confirm="Ausnahme löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                </li>
                <?php endforeach; ?>
            </ul>
        </form>
        <?php endif; ?>
    </section>
</div>

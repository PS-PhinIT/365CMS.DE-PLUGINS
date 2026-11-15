<?php
/**
 * Verfügbarkeit: Wochenplan (bis zu zwei Zeitfenster je Tag) und Abwesenheiten je Anbieter.
 *
 * @var list<array<string,mixed>> $providers
 * @var int $providerId
 * @var array<string,mixed>|null $provider
 * @var list<array<string,mixed>> $weekly
 * @var list<array<string,mixed>> $overrides
 * @var array<int,string> $dayLabels
 * @var string $csrfToken
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$byDay = [];
foreach ($weekly as $slot) {
    $byDay[(int) $slot['day_of_week']][] = [substr((string) $slot['start_time'], 0, 5), substr((string) $slot['end_time'], 0, 5)];
}
?>
<div class="admin-page-header">
    <div>
        <h2>Verfügbarkeit</h2>
        <p>Wochenplan und Abwesenheiten bestimmen die buchbaren Zeitfenster. Leistungsdauer und Puffer werden bei der Slot-Berechnung berücksichtigt.</p>
    </div>
</div>

<?php if ($providers === []): ?>
    <div class="alert alert-info" role="status">Noch keine Anbieter vorhanden. <a href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', ['action' => 'new'])); ?>">Anbieter anlegen</a></div>
<?php else: ?>
<form method="get" action="<?php echo $e(CMS_Booking_Admin_Pages::url('availability')); ?>" class="bk-toolbar" aria-label="Anbieter wählen">
    <div class="bk-toolbar__field">
        <label class="form-label" for="ba-provider">Anbieter</label>
        <select id="ba-provider" name="provider_id" class="form-select">
            <?php foreach ($providers as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo $providerId === (int) $item['id'] ? 'selected' : ''; ?>><?php echo $e($item['display_name']); ?><?php echo $item['status'] !== 'active' ? ' (inaktiv)' : ''; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bk-toolbar__actions"><button type="submit" class="btn btn-secondary">Anzeigen</button></div>
</form>

<?php if ($provider !== null): ?>
<div class="bk-split">
    <section class="admin-card">
        <div class="bk-panel-header"><h3>Wochenplan – <?php echo $e($provider['display_name']); ?></h3><span class="bk-muted">Zeitzone <?php echo $e($provider['timezone'] ?? 'Europe/Berlin'); ?></span></div>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="availability_weekly">
            <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">
            <div class="users-table-container">
                <table class="users-table bk-week">
                    <thead><tr><th scope="col">Tag</th><th scope="col">Zeitfenster 1</th><th scope="col">Zeitfenster 2 (optional)</th></tr></thead>
                    <tbody>
                    <?php foreach ($dayLabels as $day => $label): ?>
                        <?php $ranges = $byDay[$day] ?? []; ?>
                        <tr>
                            <th scope="row"><label class="form-check"><input class="form-check-input" type="checkbox" name="weekly[<?php echo (int) $day; ?>][enabled]" value="1" <?php echo $ranges !== [] ? 'checked' : ''; ?>> <span class="form-check-label"><?php echo $e($label); ?></span></label></th>
                            <?php foreach ([1, 2] as $n): ?>
                                <td class="bk-nowrap">
                                    <input class="form-control bk-time" type="time" name="weekly[<?php echo (int) $day; ?>][start<?php echo $n; ?>]" value="<?php echo $e($ranges[$n - 1][0] ?? ($n === 1 && $day < 5 && $ranges === [] ? '09:00' : '')); ?>" aria-label="<?php echo $e($label . ' Beginn ' . $n); ?>">
                                    –
                                    <input class="form-control bk-time" type="time" name="weekly[<?php echo (int) $day; ?>][end<?php echo $n; ?>]" value="<?php echo $e($ranges[$n - 1][1] ?? ($n === 1 && $day < 5 && $ranges === [] ? '17:00' : '')); ?>" aria-label="<?php echo $e($label . ' Ende ' . $n); ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-hint">Nur angehakte Tage sind buchbar. Zwei Zeitfenster z. B. für eine Mittagspause.</div>
            <div class="bk-form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Wochenplan speichern</button></div>
        </form>
    </section>

    <section class="admin-card">
        <div class="bk-panel-header"><h3>Abwesenheiten</h3><span class="bk-muted">nächste 12 Monate</span></div>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="availability_block">
            <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">
            <div class="bk-form-grid">
                <div class="form-group"><label class="form-label required" for="ba-from">Von</label><input id="ba-from" class="form-control" type="date" name="date_from" required min="<?php echo $e(date('Y-m-d')); ?>"></div>
                <div class="form-group"><label class="form-label" for="ba-to">Bis</label><input id="ba-to" class="form-control" type="date" name="date_to" min="<?php echo $e(date('Y-m-d')); ?>"></div>
                <div class="form-group"><label class="form-label" for="ba-start">Ab Uhrzeit</label><input id="ba-start" class="form-control" type="time" name="start_time" value="00:00"></div>
                <div class="form-group"><label class="form-label" for="ba-end">Bis Uhrzeit</label><input id="ba-end" class="form-control" type="time" name="end_time" value="23:59"></div>
            </div>
            <div class="bk-form-actions"><button type="submit" class="btn btn-secondary"><i class="ti ti-calendar-off" aria-hidden="true"></i> Abwesenheit eintragen</button></div>
        </form>

        <?php $blocked = array_values(array_filter($overrides, static fn (array $o): bool => !empty($o['is_blocked']))); ?>
        <?php if ($blocked === []): ?>
            <p class="bk-muted">Keine Abwesenheiten eingetragen.</p>
        <?php else: ?>
            <ul class="bk-list">
                <?php foreach ($blocked as $item): ?>
                    <li>
                        <span><?php echo $e(CMS_Booking_Admin_Pages::format_date((string) $item['specific_date'])); ?> <span class="bk-muted"><?php echo $e(substr((string) $item['start_time'], 0, 5) . '–' . substr((string) $item['end_time'], 0, 5)); ?></span></span>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                            <input type="hidden" name="booking_admin_action" value="availability_delete">
                            <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">
                            <input type="hidden" name="availability_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Entfernen"><i class="ti ti-x" aria-hidden="true"></i><span class="visually-hidden">Abwesenheit entfernen</span></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
<?php endif; ?>

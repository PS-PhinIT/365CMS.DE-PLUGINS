<?php
/**
 * Einstellungen von CMS Booking.
 *
 * @var array<string,string> $settings
 * @var int $retentionDue
 * @var string $csrfToken
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($settings[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$on = static fn (string $key): bool => ($settings[$key] ?? '0') === '1';
$timezones = ['Europe/Berlin' => 'Europa/Berlin', 'Europe/Vienna' => 'Europa/Wien', 'Europe/Zurich' => 'Europa/Zürich', 'Europe/London' => 'Europa/London', 'UTC' => 'UTC'];
$currencies = ['EUR' => 'Euro (EUR)', 'CHF' => 'Schweizer Franken (CHF)', 'USD' => 'US-Dollar (USD)', 'GBP' => 'Britisches Pfund (GBP)'];
?>
<div class="admin-page-header">
    <div>
        <h2>Einstellungen</h2>
        <p>Benachrichtigungen, Standardwerte, Buchungsregeln, Erinnerungen, Darstellung und Datenaufbewahrung.</p>
    </div>
</div>

<form method="post" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="booking_admin_action" value="settings_save">

    <div class="admin-card">
        <div class="bk-panel-header"><h3>E-Mail</h3><span class="bk-muted">Versand über System › E-Mail (SMTP/Graph)</span></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bset-admin">Admin-Benachrichtigung an</label><input id="bset-admin" class="form-control" type="email" name="admin_email" value="<?php echo $v('admin_email'); ?>" placeholder="admin@example.com"></div>
            <div class="form-group"><label class="form-label" for="bset-from-name">Absendername</label><input id="bset-from-name" class="form-control" type="text" name="from_name" maxlength="120" value="<?php echo $v('from_name'); ?>"></div>
            <div class="form-group"><label class="form-label" for="bset-from">Antwortadresse (Reply-To)</label><input id="bset-from" class="form-control" type="email" name="from_email" value="<?php echo $v('from_email'); ?>" placeholder="termine@example.com"></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="bk-panel-header"><h3>Standardwerte</h3></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bset-duration">Dauer (Min.)</label><input id="bset-duration" class="form-control" type="number" name="default_duration" min="5" max="1440" step="5" value="<?php echo $v('default_duration', '60'); ?>"></div>
            <div class="form-group"><label class="form-label" for="bset-buffer">Puffer (Min.)</label><input id="bset-buffer" class="form-control" type="number" name="default_buffer" min="0" max="240" step="5" value="<?php echo $v('default_buffer', '15'); ?>"></div>
            <div class="form-group">
                <label class="form-label" for="bset-tz">Zeitzone</label>
                <select id="bset-tz" class="form-select" name="default_timezone">
                    <?php foreach ($timezones as $tz => $label): ?><option value="<?php echo $e($tz); ?>" <?php echo ($settings['default_timezone'] ?? 'Europe/Berlin') === $tz ? 'selected' : ''; ?>><?php echo $e($label); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="bset-cur">Währung</label>
                <select id="bset-cur" class="form-select" name="default_currency">
                    <?php foreach ($currencies as $code => $label): ?><option value="<?php echo $e($code); ?>" <?php echo ($settings['default_currency'] ?? 'EUR') === $code ? 'selected' : ''; ?>><?php echo $e($label); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="bk-panel-header"><h3>Buchungsregeln</h3></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bset-adv-min">Min. Vorlauf (Tage)</label><input id="bset-adv-min" class="form-control" type="number" name="booking_advance_min" min="0" max="365" value="<?php echo $v('booking_advance_min', '1'); ?>"></div>
            <div class="form-group"><label class="form-label" for="bset-adv-max">Buchungshorizont (Tage)</label><input id="bset-adv-max" class="form-control" type="number" name="booking_advance_max" min="1" max="730" value="<?php echo $v('booking_advance_max', '90'); ?>"></div>
            <div class="form-group"><label class="form-label" for="bset-notice">Min. Vorlauf (Std.)</label><input id="bset-notice" class="form-control" type="number" name="booking_min_notice_hours" min="0" max="720" value="<?php echo $v('booking_min_notice_hours', '0'); ?>"><div class="form-hint">0 = aus</div></div>
            <div class="form-group"><label class="form-label" for="bset-cancel">Stornofrist (Std.)</label><input id="bset-cancel" class="form-control" type="number" name="cancellation_hours" min="0" max="720" value="<?php echo $v('cancellation_hours', '24'); ?>"></div>
            <div class="form-group"><label class="form-label" for="bset-daily">Tageslimit je Anbieter</label><input id="bset-daily" class="form-control" type="number" name="booking_daily_limit" min="0" max="1000" value="<?php echo $v('booking_daily_limit', '0'); ?>"><div class="form-hint">0 = unbegrenzt</div></div>
        </div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_confirm" value="1" <?php echo $on('auto_confirm') ? 'checked' : ''; ?>> <span class="form-check-label">Buchungen automatisch bestätigen (ohne manuelle Freigabe)</span></label>
    </div>

    <div class="admin-card">
        <div class="bk-panel-header"><h3>Erinnerungen</h3><span class="bk-muted">stündlich über den Core-Cron</span></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-check"><input class="form-check-input" type="checkbox" name="send_reminders" value="1" <?php echo $on('send_reminders') ? 'checked' : ''; ?>> <span class="form-check-label">Terminerinnerungen an Kunden senden</span></label></div>
            <div class="form-group"><label class="form-label" for="bset-reminder">Erinnerung (Std. vorher)</label><input id="bset-reminder" class="form-control" type="number" name="reminder_hours" min="1" max="336" value="<?php echo $v('reminder_hours', '24'); ?>"></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="bk-panel-header"><h3>Darstellung &amp; Datenschutz</h3></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bset-color">Primärfarbe der Buchungsseiten</label><input id="bset-color" class="form-control form-control-color" type="color" name="primary_color" value="<?php echo $v('primary_color') !== '' ? $v('primary_color') : '#3b82f6'; ?>"><div class="form-hint">Überschreibt die Theme-Akzentfarbe auf /booking (Standardblau = Theme-Farbe verwenden).</div></div>
            <div class="form-group"><label class="form-label" for="bset-width-fallback">Fallback-Inhaltsbreite (px)</label><input id="bset-width-fallback" class="form-control" type="number" name="content_width_fallback" min="600" max="1920" value="<?php echo $v('content_width_fallback', '1100'); ?>"><div class="form-hint">Die Buchungsseiten übernehmen die Inhaltsbreite des Themes. Dieser Wert gilt nur, wenn das Theme keine Breite liefert (weder <code>--tv4-max</code> noch <code>--container-max-width</code>).</div></div>
            <div class="form-group"><label class="form-label" for="bset-retention">Kundendaten anonymisieren nach (Tagen)</label><input id="bset-retention" class="form-control" type="number" name="retention_days" min="0" max="3650" value="<?php echo $v('retention_days', '730'); ?>"><div class="form-hint">Gilt für abgeschlossene, stornierte und nicht wahrgenommene Termine; 0 = nie. Ausführung über System › Datenaufbewahrung.</div></div>
        </div>
    </div>

    <div class="bk-form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button></div>
</form>

<div class="admin-card bk-danger-zone">
    <div class="bk-panel-header"><h3>Wartung</h3></div>
    <div class="bk-actions bk-actions--start">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="run_reminders">
            <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-bell" aria-hidden="true"></i> Fällige Erinnerungen jetzt senden</button>
        </form>
        <?php if ($retentionDue > 0): ?>
            <form method="post" data-cms-confirm="<?php echo $e(sprintf('%d alte Buchung(en) jetzt anonymisieren?', $retentionDue)); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                <input type="hidden" name="booking_admin_action" value="run_anonymize">
                <button type="submit" class="btn btn-outline-danger"><i class="ti ti-user-x" aria-hidden="true"></i> <?php echo (int) $retentionDue; ?> Buchung(en) anonymisieren</button>
            </form>
        <?php endif; ?>
        <form method="post" data-cms-confirm="Alle Booking-Einstellungen auf die Standardwerte zurücksetzen?">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="booking_admin_action" value="settings_reset">
            <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-restore" aria-hidden="true"></i> Einstellungen zurücksetzen</button>
        </form>
    </div>
</div>

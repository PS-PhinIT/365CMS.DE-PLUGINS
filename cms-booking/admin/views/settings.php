<?php
/**
 * Einstellungen
 *
 * @var array<string, string> $settings
 * @var string $csrfToken, $error
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s = static fn (string $key, string $default = ''): string => htmlspecialchars((string) ($settings[$key] ?? $default), ENT_QUOTES, 'UTF-8');
$on = static fn (string $key): string => (($settings[$key] ?? '0') === '1') ? 'checked' : '';
$tzOptions = [
    'Europe/Berlin' => 'Europa/Berlin (MEZ)',
    'Europe/Zurich' => 'Europa/Zürich',
    'Europe/Vienna' => 'Europa/Wien',
    'Europe/London' => 'Europa/London (GMT)',
    'UTC'           => 'UTC',
];
$currentTz = (string) ($settings['default_timezone'] ?? 'Europe/Berlin');
if (!isset($tzOptions[$currentTz])) {
    $tzOptions[$currentTz] = $currentTz;
}
$currencies = ['EUR' => 'Euro (EUR)', 'CHF' => 'Schweizer Franken (CHF)', 'USD' => 'US-Dollar (USD)', 'GBP' => 'Britisches Pfund (GBP)'];
$currentCur = (string) ($settings['default_currency'] ?? 'EUR');
?>
<div class="admin-page-header">
    <div>
        <h2>Einstellungen</h2>
        <p>Benachrichtigungen, Buchungsregeln, Datenschutz und Darstellung.</p>
    </div>
</div>

<?php self::render_alerts($error); ?>

<form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('settings')); ?>" class="admin-form booking-admin-stack">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">

    <section class="admin-card" aria-labelledby="settings-mail">
        <h3 id="settings-mail">E-Mail</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="set-admin-email">Admin-E-Mail</label>
                <input id="set-admin-email" type="email" name="admin_email" class="form-control" maxlength="255" value="<?php echo $s('admin_email'); ?>" placeholder="admin@example.com">
                <small class="form-text">Empfänger der Admin-Benachrichtigung bei neuen Buchungen (leer = keine).</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-from-name">Absendername</label>
                <input id="set-from-name" type="text" name="from_name" class="form-control" maxlength="100" value="<?php echo $s('from_name', 'Buchungssystem'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="set-from-email">Absender-E-Mail</label>
                <input id="set-from-email" type="email" name="from_email" class="form-control" maxlength="255" value="<?php echo $s('from_email'); ?>" placeholder="noreply@example.com">
                <small class="form-text">Leer = Absender aus den Core-Mail-Einstellungen.</small>
            </div>
        </div>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="booking-admin-check"><input type="checkbox" name="send_reminders" value="1" <?php echo $on('send_reminders'); ?>> <span>Erinnerungen an Kunden versenden</span></label>
                <small class="form-text">Stündlicher Cron (`cms_cron_hourly`), nur bestätigte Termine.</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-reminder-hours">Erinnerung (Stunden vorher)</label>
                <input id="set-reminder-hours" type="number" name="reminder_hours" class="form-control" min="1" max="168" value="<?php echo $s('reminder_hours', '24'); ?>">
            </div>
        </div>
    </section>

    <section class="admin-card" aria-labelledby="settings-defaults">
        <h3 id="settings-defaults">Standardwerte</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="set-duration">Dauer neuer Leistungen (Min.)</label>
                <input id="set-duration" type="number" name="default_duration" class="form-control" min="5" max="1440" step="5" value="<?php echo $s('default_duration', '60'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="set-buffer">Puffer neuer Leistungen (Min.)</label>
                <input id="set-buffer" type="number" name="default_buffer" class="form-control" min="0" max="480" step="5" value="<?php echo $s('default_buffer', '15'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="set-timezone">Zeitzone</label>
                <select id="set-timezone" name="default_timezone" class="form-control">
                    <?php foreach ($tzOptions as $tz => $label): ?>
                    <option value="<?php echo $e($tz); ?>" <?php echo $currentTz === $tz ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-currency">Währung</label>
                <select id="set-currency" name="default_currency" class="form-control">
                    <?php foreach ($currencies as $code => $label): ?>
                    <option value="<?php echo $e($code); ?>" <?php echo $currentCur === $code ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="admin-card" aria-labelledby="settings-rules">
        <h3 id="settings-rules">Buchungsregeln</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="set-adv-min">Min. Vorlauf (Tage)</label>
                <input id="set-adv-min" type="number" name="booking_advance_min" class="form-control" min="0" max="365" value="<?php echo $s('booking_advance_min', '1'); ?>">
                <small class="form-text">0 = Buchung am selben Tag möglich</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-adv-max">Max. Vorlauf (Tage)</label>
                <input id="set-adv-max" type="number" name="booking_advance_max" class="form-control" min="1" max="366" value="<?php echo $s('booking_advance_max', '90'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="set-notice">Min. Vorlauf (Stunden)</label>
                <input id="set-notice" type="number" name="booking_min_notice_hours" class="form-control" min="0" max="720" value="<?php echo $s('booking_min_notice_hours', '0'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="set-daily">Tageslimit je Anbieter</label>
                <input id="set-daily" type="number" name="booking_daily_limit" class="form-control" min="0" max="1000" value="<?php echo $s('booking_daily_limit', '0'); ?>">
                <small class="form-text">0 = unbegrenzt</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-rate">Buchungen je Stunde und IP</label>
                <input id="set-rate" type="number" name="booking_rate_limit" class="form-control" min="0" max="1000" value="<?php echo $s('booking_rate_limit', '10'); ?>">
                <small class="form-text">Spam-Schutz des öffentlichen Formulars (0 = aus)</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-cancel">Stornierungsfrist (Stunden)</label>
                <input id="set-cancel" type="number" name="cancellation_hours" class="form-control" min="0" max="720" value="<?php echo $s('cancellation_hours', '24'); ?>">
                <small class="form-text">Bis wann Kunden selbst stornieren können</small>
            </div>
        </div>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="booking-admin-check"><input type="checkbox" name="auto_confirm" value="1" <?php echo $on('auto_confirm'); ?>> <span>Buchungen automatisch bestätigen</span></label>
            </div>
            <div class="form-group">
                <label class="booking-admin-check"><input type="checkbox" name="allow_customer_cancel" value="1" <?php echo $on('allow_customer_cancel'); ?>> <span>Selbst-Stornierung erlauben (Bestätigungsseite, Mitgliederbereich)</span></label>
            </div>
        </div>
    </section>

    <section class="admin-card" aria-labelledby="settings-privacy">
        <h3 id="settings-privacy">Datenschutz</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="set-retention">Löschfrist (Tage nach dem Termin)</label>
                <input id="set-retention" type="number" name="retention_days" class="form-control" min="0" max="3650" value="<?php echo $s('retention_days', '0'); ?>">
                <small class="form-text">0 = nie löschen. Die Löschung übernimmt der Core unter System → Datenaufbewahrung (täglicher Cron).</small>
            </div>
        </div>
    </section>

    <section class="admin-card" aria-labelledby="settings-design">
        <h3 id="settings-design">Darstellung</h3>
        <div class="booking-admin-form-grid">
            <div class="form-group">
                <label class="booking-admin-check"><input type="checkbox" name="primary_color_custom" value="1" <?php echo $on('primary_color_custom'); ?>> <span>Eigene Primärfarbe auf den Buchungsseiten</span></label>
                <small class="form-text">Ohne Haken gilt die Akzentfarbe des Themes.</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="set-color">Primärfarbe</label>
                <div class="booking-admin-color">
                    <input id="set-color" type="color" name="primary_color" data-booking-color-preview="set-color-value" value="<?php echo $s('primary_color', '#3b82f6'); ?>">
                    <input id="set-color-value" type="text" class="form-control" readonly aria-label="Farbwert" value="<?php echo $s('primary_color', '#3b82f6'); ?>">
                </div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" name="save_settings" value="1" class="btn btn-primary">Einstellungen speichern</button>
    </div>
</form>

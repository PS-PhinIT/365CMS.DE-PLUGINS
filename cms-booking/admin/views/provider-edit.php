<?php
/**
 * Anbieter anlegen / bearbeiten
 *
 * @var array<string, mixed>|null $provider
 * @var array<string, mixed>      $settings
 * @var array<string, array>      $types
 * @var string $csrfToken, $error
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isNew = $provider === null;
$p = $provider ?? [];
$timezones = ['Europe/Berlin' => 'Europa/Berlin', 'Europe/Vienna' => 'Europa/Wien', 'Europe/Zurich' => 'Europa/Zürich', 'Europe/London' => 'Europa/London', 'UTC' => 'UTC'];
$currentTz = (string) ($p['timezone'] ?? CMS_Booking_Settings::timezone());
if (!isset($timezones[$currentTz])) {
    $timezones[$currentTz] = $currentTz;
}
$currentCur = (string) ($p['currency'] ?? CMS_Booking_Settings::currency());
$source = (string) ($p['source_plugin'] ?? CMS_Booking_Providers::SOURCE_MANUAL);
$synced = !$isNew && $source !== CMS_Booking_Providers::SOURCE_MANUAL;
?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $isNew ? 'Anbieter anlegen' : 'Anbieter bearbeiten'; ?></h2>
        <p><?php echo $isNew ? 'Manueller Anbieter ohne Quell-Plugin.' : $e($p['display_name'] ?? ''); ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        <?php if (!$isNew): ?>
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'availability', 'id' => (int) $p['id']])); ?>"><i class="ti ti-calendar-time" aria-hidden="true"></i> Verfügbarkeit</a>
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', ['provider_id' => (int) $p['id']])); ?>"><i class="ti ti-briefcase" aria-hidden="true"></i> Leistungen</a>
        <?php endif; ?>
    </div>
</div>

<?php self::render_alerts($error); ?>

<?php if ($synced): ?>
<div class="alert alert-info" role="note">Dieser Anbieter stammt aus „<?php echo $e($types[$source]['label'] ?? $source); ?>“. Name, E-Mail und Konto werden bei einer Synchronisation aus dem Quell-Plugin übernommen; die übrigen Felder bleiben erhalten.</div>
<?php endif; ?>

<form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', $isNew ? ['view' => 'edit'] : ['view' => 'edit', 'id' => (int) $p['id']])); ?>" class="admin-card admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="provider_id" value="<?php echo (int) ($p['id'] ?? 0); ?>">
    <div class="booking-admin-form-grid">
        <div class="form-group">
            <label class="form-label" for="provider-name">Name <span class="booking-admin-required" aria-hidden="true">*</span></label>
            <input id="provider-name" type="text" name="display_name" class="form-control" required maxlength="255" value="<?php echo $e($p['display_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-email">E-Mail (Benachrichtigungen)</label>
            <input id="provider-email" type="email" name="email" class="form-control" maxlength="255" value="<?php echo $e($p['email'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-phone">Telefon</label>
            <input id="provider-phone" type="tel" name="phone" class="form-control" maxlength="50" value="<?php echo $e($p['phone'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-status">Status</label>
            <select id="provider-status" name="status" class="form-control">
                <?php foreach (['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'pending' => 'Ausstehend'] as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo ($p['status'] ?? 'active') === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-timezone">Zeitzone</label>
            <select id="provider-timezone" name="timezone" class="form-control">
                <?php foreach ($timezones as $tz => $label): ?>
                <option value="<?php echo $e($tz); ?>" <?php echo $currentTz === $tz ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-currency">Währung</label>
            <select id="provider-currency" name="currency" class="form-control">
                <?php foreach (['EUR', 'CHF', 'USD', 'GBP'] as $code): ?>
                <option value="<?php echo $e($code); ?>" <?php echo $currentCur === $code ? 'selected' : ''; ?>><?php echo $e($code); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="provider-limit">Tageslimit</label>
            <input id="provider-limit" type="number" name="daily_booking_limit" class="form-control" min="0" max="1000" value="<?php echo (int) ($settings['daily_booking_limit'] ?? 0); ?>">
            <small class="form-text">Max. aktive Buchungen pro Tag (0 = unbegrenzt)</small>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="provider-bio">Beschreibung (öffentliche Anbieterseite)</label>
        <textarea id="provider-bio" name="bio" class="form-control" rows="4" maxlength="5000"><?php echo $e($p['bio'] ?? ''); ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" name="save_provider" value="1" class="btn btn-primary"><?php echo $isNew ? 'Anbieter anlegen' : 'Speichern'; ?></button>
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers')); ?>">Abbrechen</a>
    </div>
</form>

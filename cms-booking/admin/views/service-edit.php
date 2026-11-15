<?php
/**
 * Leistung anlegen / bearbeiten
 *
 * @var array<string, mixed>|null  $service
 * @var array<string, mixed>       $serviceSettings
 * @var list<array<string, mixed>> $providers
 * @var string $csrfToken, $error
 * @var int    $presetProvider
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isNew = $service === null;
$s = $service ?? [
    'duration_min' => CMS_Booking_Settings::int('default_duration', 60),
    'buffer_min'   => CMS_Booking_Settings::int('default_buffer', 15),
    'price_cents'  => 0,
    'status'       => 'active',
    'location_type' => 'online',
    'booking_type' => 'confirmation',
    'sort_order'   => 0,
];
$selectedProvider = (int) ($s['provider_id'] ?? $presetProvider);
?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $isNew ? 'Neue Leistung' : 'Leistung bearbeiten'; ?></h2>
        <p><?php echo $isNew ? 'Buchbare Leistung für einen Anbieter anlegen.' : $e($s['title'] ?? ''); ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services')); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
    </div>
</div>

<?php self::render_alerts($error); ?>

<?php if ($isNew && empty($providers)): ?>
<div class="alert alert-info" role="note">Es gibt noch keinen Anbieter. <a href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('providers', ['view' => 'edit'])); ?>">Zuerst einen Anbieter anlegen</a>.</div>
<?php endif; ?>

<form method="post" action="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services', $isNew ? ['view' => 'edit'] : ['view' => 'edit', 'id' => (int) $s['id']])); ?>" class="admin-card admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="service_id" value="<?php echo (int) ($s['id'] ?? 0); ?>">
    <div class="booking-admin-form-grid">
        <div class="form-group">
            <label class="form-label" for="service-provider">Anbieter <span class="booking-admin-required" aria-hidden="true">*</span></label>
            <select id="service-provider" name="provider_id" class="form-control" <?php echo $isNew ? 'required' : 'disabled'; ?>>
                <option value="">– Bitte wählen –</option>
                <?php foreach ($providers as $prov): ?>
                <option value="<?php echo (int) $prov['id']; ?>" <?php echo $selectedProvider === (int) $prov['id'] ? 'selected' : ''; ?>><?php echo $e($prov['display_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isNew): ?><small class="form-text">Der Anbieter einer bestehenden Leistung ist fest.</small><?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-title">Titel <span class="booking-admin-required" aria-hidden="true">*</span></label>
            <input id="service-title" type="text" name="title" class="form-control" required maxlength="255" value="<?php echo $e($s['title'] ?? ''); ?>" placeholder="z. B. Erstgespräch 60 Min.">
        </div>
        <div class="form-group">
            <label class="form-label" for="service-status">Status</label>
            <select id="service-status" name="status" class="form-control">
                <option value="active" <?php echo ($s['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                <option value="inactive" <?php echo ($s['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inaktiv</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-duration">Dauer (Min.)</label>
            <input id="service-duration" type="number" name="duration_min" class="form-control" min="5" max="1440" step="5" value="<?php echo (int) $s['duration_min']; ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="service-buffer">Puffer danach (Min.)</label>
            <input id="service-buffer" type="number" name="buffer_min" class="form-control" min="0" max="480" step="5" value="<?php echo (int) $s['buffer_min']; ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="service-price">Preis</label>
            <input id="service-price" type="number" name="price" class="form-control" min="0" step="0.01" value="<?php echo $e(number_format(((int) $s['price_cents']) / 100, 2, '.', '')); ?>">
            <small class="form-text">0 = kostenlos; Währung des Anbieters</small>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-location">Ort</label>
            <select id="service-location" name="location_type" class="form-control">
                <?php foreach (['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Hybrid'] as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo ($s['location_type'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-type">Buchungsart</label>
            <select id="service-type" name="booking_type" class="form-control">
                <?php foreach (['confirmation' => 'Bestätigung erforderlich', 'instant' => 'Sofortbuchung', 'request' => 'Anfrage'] as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo ($s['booking_type'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-limit">Tageslimit</label>
            <input id="service-limit" type="number" name="daily_booking_limit" class="form-control" min="0" max="1000" value="<?php echo (int) ($serviceSettings['daily_booking_limit'] ?? 0); ?>">
            <small class="form-text">Max. aktive Buchungen pro Tag (0 = unbegrenzt)</small>
        </div>
        <div class="form-group">
            <label class="form-label" for="service-sort">Reihenfolge</label>
            <input id="service-sort" type="number" name="sort_order" class="form-control" min="0" max="9999" value="<?php echo (int) ($s['sort_order'] ?? 0); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="service-meeting">Meeting-URL</label>
            <input id="service-meeting" type="url" name="meeting_url" class="form-control" maxlength="500" value="<?php echo $e($s['meeting_url'] ?? ''); ?>" placeholder="https://…">
        </div>
        <div class="form-group">
            <label class="form-label" for="service-contact">Kontakt-Vorlage (optional)</label>
            <input id="service-contact" type="text" name="contact_template" class="form-control" maxlength="50" value="<?php echo $e($s['contact_template'] ?? ''); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="service-description">Beschreibung</label>
        <textarea id="service-description" name="description" class="form-control" rows="4" maxlength="5000"><?php echo $e($s['description'] ?? ''); ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" name="save_service" value="1" class="btn btn-primary"><?php echo $isNew ? 'Leistung anlegen' : 'Speichern'; ?></button>
        <a class="btn btn-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::admin_url('services')); ?>">Abbrechen</a>
    </div>
</form>

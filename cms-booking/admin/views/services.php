<?php
/**
 * Leistungen: Liste mit Suche bzw. Formular (anlegen/bearbeiten).
 *
 * @var bool $showForm
 * @var array<string,mixed>|null $editService
 * @var list<array<string,mixed>> $items
 * @var string $search
 * @var int $paged
 * @var int $pages
 * @var int $total
 * @var list<array<string,mixed>> $providers
 * @var array<string,string> $settings
 * @var string $csrfToken
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Booking_Admin_Pages::url('services');
$locations = ['online' => 'Online', 'onsite' => 'Vor Ort', 'hybrid' => 'Hybrid'];
$bookingTypes = ['confirmation' => 'Mit Bestätigung', 'instant' => 'Sofort bestätigt', 'request' => 'Anfrage (ohne festen Slot)'];
?>
<?php if (!$showForm): ?>
<div class="admin-page-header">
    <div>
        <h2>Leistungen</h2>
        <p>Buchbare Angebote je Anbieter mit Dauer, Puffer, Kapazität, Preis und Buchungsart.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('services', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Leistung</a>
    </div>
</div>

<form method="get" action="<?php echo $e($listUrl); ?>" class="bk-toolbar" role="search" aria-label="Leistungen durchsuchen">
    <div class="bk-toolbar__field">
        <label class="form-label" for="bs-q">Suche</label>
        <input id="bs-q" type="search" name="q" class="form-control" value="<?php echo $e($search); ?>" placeholder="Titel oder Anbieter">
    </div>
    <div class="bk-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-search" aria-hidden="true"></i> Suchen</button>
        <?php if ($search !== ''): ?><a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Zurücksetzen</a><?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($items === []): ?>
        <div class="empty-state"><p><strong>Keine Leistungen gefunden</strong></p><p class="bk-muted">Eine Leistung benötigt einen Anbieter mit Wochenplan.</p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Leistung</th><th scope="col">Anbieter</th><th scope="col">Dauer / Puffer</th><th scope="col" class="bk-num">Preis</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $id = (int) $item['id']; ?>
                    <tr>
                        <td><a class="bk-link" href="<?php echo $e(CMS_Booking_Admin_Pages::url('services', ['edit' => $id])); ?>"><?php echo $e($item['title']); ?></a><div class="bk-muted"><?php echo $e($locations[$item['location_type']] ?? $item['location_type']); ?> · <?php echo $e($bookingTypes[$item['booking_type']] ?? $item['booking_type']); ?></div></td>
                        <td><?php echo $e($item['provider_name'] ?? '–'); ?></td>
                        <td class="bk-nowrap"><?php echo $e(CMS_Booking_Services::format_duration((int) $item['duration_min'])); ?> / <?php echo (int) $item['buffer_min']; ?> Min.</td>
                        <td class="bk-num"><?php echo $e(CMS_Booking_Services::format_price((int) $item['price_cents'], (string) ($settings['default_currency'] ?? 'EUR'))); ?></td>
                        <td><span class="bk-badge bk-badge--<?php echo $item['status'] === 'active' ? 'success' : 'muted'; ?>"><?php echo $item['status'] === 'active' ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                        <td>
                            <div class="bk-actions">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('services', ['edit' => $id])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <form method="post" data-cms-confirm="Leistung „<?php echo $e($item['title']); ?>“ löschen? Bestehende Buchungen bleiben ohne Leistungszuordnung erhalten.">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="booking_admin_action" value="service_delete">
                                    <input type="hidden" name="service_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="bk-pagination" aria-label="Seiten">
                <?php $base = array_filter(['q' => $search]); ?>
                <?php if ($paged > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('services', $base + ['paged' => $paged - 1])); ?>">Zurück</a><?php endif; ?>
                <span class="bk-muted">Seite <?php echo $paged; ?> von <?php echo $pages; ?></span>
                <?php if ($paged < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('services', $base + ['paged' => $paged + 1])); ?>">Weiter</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<?php
$s = $editService ?? [
    'duration_min' => (int) ($settings['default_duration'] ?? 60),
    'buffer_min' => (int) ($settings['default_buffer'] ?? 15),
    'max_bookings' => 1, 'price_cents' => 0, 'location_type' => 'online', 'booking_type' => 'confirmation', 'status' => 'active',
];
$serviceSettings = json_decode((string) ($s['settings_json'] ?? ''), true);
$dailyLimit = is_array($serviceSettings) ? (int) ($serviceSettings['daily_booking_limit'] ?? 0) : 0;
?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $editService !== null ? 'Leistung bearbeiten' : 'Neue Leistung'; ?></h2>
        <p>Öffentliche Buchungsseite: /booking/&lt;anbieter&gt;/&lt;leistung&gt;</p>
    </div>
    <div class="header-actions"><a class="btn btn-outline-secondary" href="<?php echo $e($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a></div>
</div>

<?php if ($providers === []): ?>
    <div class="alert alert-info" role="status">Bitte zuerst einen Anbieter anlegen. <a href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', ['action' => 'new'])); ?>">Anbieter anlegen</a></div>
<?php else: ?>
<div class="admin-card">
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="booking_admin_action" value="service_save">
        <input type="hidden" name="service_id" value="<?php echo (int) ($s['id'] ?? 0); ?>">
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label required" for="bs-title">Titel</label><input id="bs-title" class="form-control" type="text" name="title" maxlength="255" required value="<?php echo $e($s['title'] ?? ''); ?>"></div>
            <div class="form-group">
                <label class="form-label required" for="bs-provider">Anbieter</label>
                <select id="bs-provider" class="form-select" name="provider_id" required <?php echo $editService !== null ? 'disabled' : ''; ?>>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?php echo (int) $provider['id']; ?>" <?php echo (int) ($s['provider_id'] ?? 0) === (int) $provider['id'] ? 'selected' : ''; ?>><?php echo $e($provider['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editService !== null): ?><div class="form-hint">Der Anbieter einer bestehenden Leistung kann nicht geändert werden.</div><?php endif; ?>
            </div>
        </div>
        <div class="form-group"><label class="form-label" for="bs-description">Beschreibung</label><textarea id="bs-description" class="form-control" name="description" rows="4" maxlength="10000"><?php echo $e($s['description'] ?? ''); ?></textarea><div class="form-hint">Erlaubt: p, a, strong, em, ul, ol, li, br.</div></div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bs-duration">Dauer (Min.)</label><input id="bs-duration" class="form-control" type="number" name="duration_min" min="5" max="1440" step="5" value="<?php echo (int) $s['duration_min']; ?>"></div>
            <div class="form-group"><label class="form-label" for="bs-buffer">Puffer danach (Min.)</label><input id="bs-buffer" class="form-control" type="number" name="buffer_min" min="0" max="240" step="5" value="<?php echo (int) $s['buffer_min']; ?>"></div>
            <div class="form-group"><label class="form-label" for="bs-max">Parallele Buchungen</label><input id="bs-max" class="form-control" type="number" name="max_bookings" min="1" max="500" value="<?php echo (int) $s['max_bookings']; ?>"></div>
            <div class="form-group"><label class="form-label" for="bs-daily">Tageslimit</label><input id="bs-daily" class="form-control" type="number" name="daily_booking_limit" min="0" max="1000" value="<?php echo $dailyLimit; ?>"><div class="form-hint">0 = unbegrenzt</div></div>
            <div class="form-group"><label class="form-label" for="bs-price">Preis</label><input id="bs-price" class="form-control" type="number" name="price" min="0" step="0.01" value="<?php echo $e(number_format(((int) $s['price_cents']) / 100, 2, '.', '')); ?>"></div>
            <div class="form-group">
                <label class="form-label" for="bs-location">Ort</label>
                <select id="bs-location" class="form-select" name="location_type">
                    <?php foreach ($locations as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo $s['location_type'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="bs-type">Buchungsart</label>
                <select id="bs-type" class="form-select" name="booking_type">
                    <?php foreach ($bookingTypes as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo $s['booking_type'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($editService !== null): ?>
                <div class="form-group">
                    <label class="form-label" for="bs-status">Status</label>
                    <select id="bs-status" class="form-select" name="status">
                        <option value="active" <?php echo $s['status'] === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="inactive" <?php echo $s['status'] === 'inactive' ? 'selected' : ''; ?>>Inaktiv</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label" for="bs-meeting">Meeting-Link</label><input id="bs-meeting" class="form-control" type="url" name="meeting_url" maxlength="500" placeholder="https://" value="<?php echo $e($s['meeting_url'] ?? ''); ?>"><div class="form-hint">Wird bestätigten Online-Terminen beigefügt.</div></div>
            <div class="form-group"><label class="form-label" for="bs-template">Kontakt-Vorlage (optional)</label><input id="bs-template" class="form-control" type="text" name="contact_template" maxlength="50" value="<?php echo $e($s['contact_template'] ?? ''); ?>"></div>
        </div>
        <div class="bk-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Leistung speichern</button>
            <a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Abbrechen</a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>

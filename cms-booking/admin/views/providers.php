<?php
/**
 * Anbieter: Liste mit Filter bzw. Formular (manuell anlegen/bearbeiten).
 *
 * @var bool $showForm
 * @var array<string,mixed>|null $editProvider
 * @var list<array<string,mixed>> $items
 * @var array{status:string,q:string} $filters
 * @var int $paged
 * @var int $pages
 * @var int $total
 * @var array<string,array<string,mixed>> $types
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
$listUrl = CMS_Booking_Admin_Pages::url('providers');
$statusLabels = ['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'pending' => 'Ausstehend'];
$sourceLabel = static function (string $source) use ($types): string {
    if ($source === CMS_Booking_Providers::MANUAL_SOURCE) {
        return 'Manuell';
    }
    return (string) ($types[$source]['label'] ?? $source);
};
?>
<?php if (!$showForm): ?>
<div class="admin-page-header">
    <div>
        <h2>Anbieter</h2>
        <p>Buchbare Personen oder Ressourcen – manuell angelegt oder aus integrierten Plugins synchronisiert.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', ['action' => 'new'])); ?>"><i class="ti ti-user-plus" aria-hidden="true"></i> Neuer Anbieter</a>
    </div>
</div>

<form method="get" action="<?php echo $e($listUrl); ?>" class="bk-toolbar" role="search" aria-label="Anbieter filtern">
    <div class="bk-toolbar__field">
        <label class="form-label" for="bp-q">Suche</label>
        <input id="bp-q" type="search" name="q" class="form-control" value="<?php echo $e($filters['q']); ?>" placeholder="Name, E-Mail, Slug">
    </div>
    <div class="bk-toolbar__field">
        <label class="form-label" for="bp-status">Status</label>
        <select id="bp-status" name="status" class="form-select">
            <option value="">Alle</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bk-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?><a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Zurücksetzen</a><?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($items === []): ?>
        <div class="empty-state"><p><strong>Keine Anbieter gefunden</strong></p><p class="bk-muted">Lege einen Anbieter an oder aktiviere ein Plugin, das Anbieter registriert.</p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Anbieter</th><th scope="col">Quelle</th><th scope="col">Öffentliche Seite</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $id = (int) $item['id']; $active = $item['status'] === 'active'; $manual = (string) $item['source_plugin'] === CMS_Booking_Providers::MANUAL_SOURCE; ?>
                    <tr>
                        <td><a class="bk-link" href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', ['edit' => $id])); ?>"><?php echo $e($item['display_name']); ?></a><div class="bk-muted"><?php echo $e($item['email'] ?? ''); ?></div></td>
                        <td><?php echo $e($sourceLabel((string) $item['source_plugin'])); ?><?php echo $manual ? '' : '<div class="bk-muted">#' . (int) $item['source_id'] . '</div>'; ?></td>
                        <td><a href="<?php echo $e('/booking/' . rawurlencode((string) $item['slug'])); ?>" target="_blank" rel="noopener noreferrer">/booking/<?php echo $e($item['slug']); ?></a></td>
                        <td><span class="bk-badge bk-badge--<?php echo $active ? 'success' : 'muted'; ?>"><?php echo $e($statusLabels[$item['status']] ?? $item['status']); ?></span></td>
                        <td>
                            <div class="bk-actions">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', ['edit' => $id])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('availability', ['provider_id' => $id])); ?>" title="Verfügbarkeit"><i class="ti ti-clock-hour-4" aria-hidden="true"></i><span class="visually-hidden">Verfügbarkeit</span></a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="booking_admin_action" value="provider_status">
                                    <input type="hidden" name="provider_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="status" value="<?php echo $active ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $active ? 'Deaktivieren' : 'Aktivieren'; ?>"><i class="ti <?php echo $active ? 'ti-player-pause' : 'ti-player-play'; ?>" aria-hidden="true"></i><span class="visually-hidden"><?php echo $active ? 'Deaktivieren' : 'Aktivieren'; ?></span></button>
                                </form>
                                <?php if (!$manual): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                        <input type="hidden" name="booking_admin_action" value="provider_sync">
                                        <input type="hidden" name="provider_id" value="<?php echo $id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Aus Quell-Plugin synchronisieren"><i class="ti ti-refresh" aria-hidden="true"></i><span class="visually-hidden">Synchronisieren</span></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" data-cms-confirm="Anbieter „<?php echo $e($item['display_name']); ?>“ löschen? Leistungen, Wochenplan und alle Buchungen dieses Anbieters werden ebenfalls entfernt.">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="booking_admin_action" value="provider_delete">
                                    <input type="hidden" name="provider_id" value="<?php echo $id; ?>">
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
                <?php $base = array_filter(['status' => $filters['status'], 'q' => $filters['q']]); ?>
                <?php if ($paged > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', $base + ['paged' => $paged - 1])); ?>">Zurück</a><?php endif; ?>
                <span class="bk-muted">Seite <?php echo $paged; ?> von <?php echo $pages; ?></span>
                <?php if ($paged < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('providers', $base + ['paged' => $paged + 1])); ?>">Weiter</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<?php
$p = $editProvider ?? ['status' => 'active', 'timezone' => $settings['default_timezone'] ?? 'Europe/Berlin', 'currency' => $settings['default_currency'] ?? 'EUR', 'source_plugin' => CMS_Booking_Providers::MANUAL_SOURCE];
$synced = $editProvider !== null && (string) $editProvider['source_plugin'] !== CMS_Booking_Providers::MANUAL_SOURCE;
$timezones = ['Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich', 'Europe/London', 'UTC'];
?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $editProvider !== null ? 'Anbieter bearbeiten' : 'Neuer Anbieter'; ?></h2>
        <p><?php echo $synced ? 'Name, Bild und Biografie stammen aus „' . $e($sourceLabel((string) $p['source_plugin'])) . '“ und werden dort gepflegt.' : 'Anbieter ohne Quell-Plugin; anschließend Wochenplan und Leistungen ergänzen.'; ?></p>
    </div>
    <div class="header-actions">
        <?php if ($editProvider !== null): ?>
            <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Booking_Admin_Pages::url('availability', ['provider_id' => (int) $p['id']])); ?>"><i class="ti ti-clock-hour-4" aria-hidden="true"></i> Verfügbarkeit</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?php echo $e($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
    </div>
</div>

<div class="admin-card">
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="booking_admin_action" value="provider_save">
        <input type="hidden" name="provider_id" value="<?php echo (int) ($p['id'] ?? 0); ?>">
        <div class="bk-form-grid">
            <div class="form-group"><label class="form-label required" for="bp-name">Name</label><input id="bp-name" class="form-control" type="text" name="display_name" maxlength="255" required value="<?php echo $e($p['display_name'] ?? ''); ?>" <?php echo $synced ? 'readonly' : ''; ?>></div>
            <div class="form-group"><label class="form-label" for="bp-email">E-Mail (Benachrichtigungen)</label><input id="bp-email" class="form-control" type="email" name="email" maxlength="255" value="<?php echo $e($p['email'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="bp-phone">Telefon</label><input id="bp-phone" class="form-control" type="tel" name="phone" maxlength="50" value="<?php echo $e($p['phone'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="bp-avatar">Bild-URL</label><input id="bp-avatar" class="form-control" type="text" name="avatar_url" maxlength="500" value="<?php echo $e($p['avatar_url'] ?? ''); ?>" placeholder="/uploads/… oder https://…" <?php echo $synced ? 'readonly' : ''; ?>></div>
            <div class="form-group">
                <label class="form-label" for="bp-tz">Zeitzone</label>
                <select id="bp-tz" class="form-select" name="timezone">
                    <?php foreach (array_unique([...$timezones, (string) ($p['timezone'] ?? 'Europe/Berlin')]) as $tz): ?>
                        <option value="<?php echo $e($tz); ?>" <?php echo ($p['timezone'] ?? '') === $tz ? 'selected' : ''; ?>><?php echo $e($tz); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="bp-currency">Währung</label>
                <select id="bp-currency" class="form-select" name="currency">
                    <?php foreach (['EUR', 'CHF', 'USD', 'GBP'] as $cur): ?>
                        <option value="<?php echo $e($cur); ?>" <?php echo ($p['currency'] ?? 'EUR') === $cur ? 'selected' : ''; ?>><?php echo $e($cur); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="bp-status">Status</label>
                <select id="bp-status" class="form-select" name="status">
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo ($p['status'] ?? 'active') === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label" for="bp-bio">Kurzbiografie</label><textarea id="bp-bio" class="form-control" name="bio" rows="4" maxlength="5000" <?php echo $synced ? 'readonly' : ''; ?>><?php echo $e($p['bio'] ?? ''); ?></textarea></div>
        <div class="bk-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Anbieter speichern</button>
            <a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Abbrechen</a>
        </div>
    </form>
</div>
<?php endif; ?>

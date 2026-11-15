<?php
/**
 * Einstellungen von CMS Promos.
 *
 * @var array<string,string> $settings
 * @var string               $csrfToken
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-page-header">
    <div>
        <h2>Einstellungen</h2>
        <p>Standardtexte, Archivdarstellung, Zielverhalten und optionaler Klick-Export.</p>
    </div>
</div>

<form method="post" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="action" value="save_settings">

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Öffentliches Archiv (/promos)</h3></div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label" for="ps-archive-title">Archiv-Titel</label><input id="ps-archive-title" type="text" name="archive_title" class="form-control" maxlength="255" value="<?php echo $e($settings['archive_title'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="ps-archive-title-en">Archiv-Titel (EN)</label><input id="ps-archive-title-en" type="text" name="archive_title_en" class="form-control" maxlength="255" value="<?php echo $e($settings['archive_title_en'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="ps-archive-desc">Archiv-Beschreibung</label><textarea id="ps-archive-desc" name="archive_description" class="form-control" rows="3" maxlength="2000"><?php echo $e($settings['archive_description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label class="form-label" for="ps-archive-desc-en">Archiv-Beschreibung (EN)</label><textarea id="ps-archive-desc-en" name="archive_description_en" class="form-control" rows="3" maxlength="2000"><?php echo $e($settings['archive_description_en'] ?? ''); ?></textarea></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Standardwerte für Promos</h3></div>
        <div class="pr-form-grid pr-form-grid--3">
            <div class="form-group"><label class="form-label" for="ps-button">Button-Label</label><input id="ps-button" type="text" name="default_button_label" class="form-control" maxlength="80" value="<?php echo $e($settings['default_button_label'] ?? 'Mehr erfahren'); ?>"></div>
            <div class="form-group"><label class="form-label" for="ps-button-en">Button-Label (EN)</label><input id="ps-button-en" type="text" name="default_button_label_en" class="form-control" maxlength="80" value="<?php echo $e($settings['default_button_label_en'] ?? ''); ?>"></div>
            <div class="form-group">
                <label class="form-label" for="ps-target">Zielverhalten</label>
                <select id="ps-target" name="default_target_behavior" class="form-select">
                    <option value="same_tab" <?php echo (($settings['default_target_behavior'] ?? 'same_tab') === 'same_tab') ? 'selected' : ''; ?>>Im gleichen Tab öffnen</option>
                    <option value="new_tab" <?php echo (($settings['default_target_behavior'] ?? '') === 'new_tab') ? 'selected' : ''; ?>>In neuem Tab öffnen</option>
                </select>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Klick-Export</h3></div>
        <p class="pr-muted">Jeder Klick löst die Action <code>cms_promos_click_export</code> aus (GA4-freundliches Payload). Optional wird das Payload zusätzlich per <code>POST</code> an einen öffentlichen Webhook gesendet – über den Core-HTTP-Client mit SSRF-Schutz (keine privaten oder lokalen Ziele).</p>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="click_export_enabled" value="1" <?php echo (($settings['click_export_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>> <span class="form-check-label">Webhook-Export aktivieren</span></label>
        <div class="form-group"><label class="form-label" for="ps-webhook">Webhook-URL</label><input id="ps-webhook" type="url" name="click_export_webhook_url" class="form-control" value="<?php echo $e($settings['click_export_webhook_url'] ?? ''); ?>" placeholder="https://example.com/webhooks/promo-click"></div>
    </div>

    <div class="pr-form-actions">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button>
    </div>
</form>

<div class="admin-card pr-danger-zone">
    <div class="pr-panel-header"><h3>Zurücksetzen</h3></div>
    <div class="pr-action-group">
        <form method="post" data-cms-confirm="Alle Einstellungen auf die Standardwerte zurücksetzen?">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="action" value="reset_settings">
            <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-restore" aria-hidden="true"></i> Einstellungen zurücksetzen</button>
        </form>
        <form method="post" data-cms-confirm="Impressions und Klicks ALLER Promos auf 0 setzen? Das lässt sich nicht rückgängig machen.">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="action" value="reset_all_stats">
            <button type="submit" class="btn btn-outline-danger"><i class="ti ti-chart-bar-off" aria-hidden="true"></i> Statistik aller Promos zurücksetzen</button>
        </form>
    </div>
</div>

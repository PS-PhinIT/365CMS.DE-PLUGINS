<?php
/**
 * Einstellungen: Aufbewahrung des Audit-Logs, Sitemap und Suche.
 *
 * @var callable $e
 * @var array{audit_retention_days:int,sitemap_enabled:bool,search_enabled:bool} $settings
 * @var int $auditCount
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo $this->pageHeader('Einstellungen', 'Aufbewahrung des Audit-Logs sowie Einbindung öffentlicher Projekte in Sitemap und Seitensuche.');
?>
<section class="admin-card cp-admin-section">
    <form method="post" action="<?php echo $e($this->url(self::PAGE_SETTINGS)); ?>" class="cp-form">
        <?php echo $this->hiddenFields('save_settings'); ?>
        <fieldset class="cp-form-section">
            <legend class="cp-form-section__title">Audit-Log</legend>
            <label>
                <span class="form-label">Aufbewahrungsfrist in Tagen</span>
                <input type="number" class="form-control cp-input-narrow" name="audit_retention_days" min="0" max="3650" value="<?php echo (int) $settings['audit_retention_days']; ?>">
                <small class="cp-field-hint">0 = unbegrenzt. Ältere Einträge löscht der tägliche Lauf der Datenaufbewahrung (System → Datenaufbewahrung). Aktuell gespeichert: <?php echo (int) $auditCount; ?> Einträge.</small>
            </label>
        </fieldset>
        <fieldset class="cp-form-section">
            <legend class="cp-form-section__title">Öffentliche Projekte</legend>
            <label class="form-check"><input type="checkbox" class="form-check-input" name="sitemap_enabled" value="1"<?php echo $settings['sitemap_enabled'] ? ' checked' : ''; ?>><span class="form-check-label">In der Sitemap melden (/projects und /projects/&lt;slug&gt;)</span></label>
            <label class="form-check"><input type="checkbox" class="form-check-input" name="search_enabled" value="1"<?php echo $settings['search_enabled'] ? ' checked' : ''; ?>><span class="form-check-label">In der Seitensuche (/search) finden</span></label>
            <small class="cp-field-hint">Berücksichtigt werden nur aktive Projekte mit Sichtbarkeit „Öffentlich“.</small>
        </fieldset>
        <div class="cp-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button>
        </div>
    </form>
</section>

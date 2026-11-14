<?php
/**
 * Einstellungen von CMS Projects.
 *
 * @var int $retentionDays
 * @var int $auditCount
 * @var int $auditDue
 * @var string $schemaVersion
 * @var string $csrfToken
 *
 * @package CMS_Projects
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
        <p>Löschfrist des Audit-Protokolls und Datenbank-Wartung.</p>
    </div>
</div>

<div class="admin-card">
    <div class="cp-panel-header"><h3>Audit-Protokoll</h3></div>
    <p class="cp-muted">Das Protokoll speichert den Benutzernamen je Änderung. Die Frist gilt auch für <em>System › Datenaufbewahrung</em> (Regel „Projekte: Audit-Protokoll“), die der tägliche Cron ausführt. Aktuell <?php echo number_format($auditCount, 0, ',', '.'); ?> Einträge, davon <?php echo number_format($auditDue, 0, ',', '.'); ?> älter als die Frist.</p>
    <form method="post" class="admin-form cp-inline-form-row">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="cms_projects_action" value="save_settings">
        <div class="form-group">
            <label class="form-label" for="cp-retention">Aufbewahrung (Tage)</label>
            <input id="cp-retention" class="form-control" type="number" name="audit_retention_days" min="0" max="3650" value="<?php echo $retentionDays; ?>">
            <div class="form-hint">0 = unbegrenzt aufbewahren (nicht empfohlen).</div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button>
    </form>
    <?php if ($retentionDays > 0 && $auditDue > 0): ?>
        <form method="post" data-cms-confirm="<?php echo $e(sprintf('%d Audit-Einträge älter als %d Tage jetzt löschen?', $auditDue, $retentionDays)); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="cms_projects_action" value="prune_audit">
            <button type="submit" class="btn btn-outline-danger"><i class="ti ti-trash" aria-hidden="true"></i> Fällige Einträge jetzt löschen</button>
        </form>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="cp-panel-header"><h3>Datenbank</h3></div>
    <p class="cp-muted">Installierte Schema-Version: <strong><?php echo $e($schemaVersion !== '' ? $schemaVersion : 'unbekannt'); ?></strong> (erwartet <?php echo $e(CMS_Projects_Service::DB_VERSION); ?>). Die Prüfung legt fehlende Tabellen und Indizes an; vorhandene Daten bleiben unverändert.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="cms_projects_action" value="repair_schema">
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-database-cog" aria-hidden="true"></i> Tabellen prüfen &amp; reparieren</button>
    </form>
</div>

<?php
/**
 * Einstellungen: Archiv, Standardtexte, Klick-Export, Löschfrist, Export/Import.
 *
 * @var array<string,string> $settings
 * @var array{type:string,message:string}|null $notice
 * @var string $csrfToken
 * @var string $exportToken
 * @var int $retentionDays
 * @var int $retentionDue
 * @var int $maxImportMb
 * @var bool $webhookStored
 * @var bool $webhookEncrypted
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$pageUrl = CMS_Promos_Admin_Pages::url('promos-settings');
$checked = static fn (string $key) => (($settings[$key] ?? '0') === '1' || ($settings[$key] ?? '') === 'on') ? ' checked' : '';
?>
<div class="admin-page-header">
    <div>
        <h2>Einstellungen</h2>
        <p>Archivseite, Standardtexte, Klick-Export, Löschfrist sowie Export und Import der Konfiguration.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="/promos" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i> Archiv ansehen</a>
    </div>
</div>

<?php if ($notice !== null): ?>
    <div class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo $esc($notice['message']); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo $esc($pageUrl); ?>" class="admin-form pr-form">
    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
    <input type="hidden" name="action" value="save_settings">

    <section class="admin-card pr-section">
        <div class="pr-section__head"><div><h3>Archivseite /promos</h3><p>Überschrift und Einleitung der öffentlichen Übersicht.</p></div></div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label" for="set-archive-title">Titel</label><input id="set-archive-title" type="text" name="archive_title" class="form-control" maxlength="180" value="<?php echo $esc($settings['archive_title'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="set-archive-title-en">Titel (EN)</label><input id="set-archive-title-en" type="text" name="archive_title_en" class="form-control" maxlength="180" value="<?php echo $esc($settings['archive_title_en'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="set-archive-desc">Beschreibung</label><textarea id="set-archive-desc" name="archive_description" class="form-control" rows="3" maxlength="2000"><?php echo $esc($settings['archive_description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label class="form-label" for="set-archive-desc-en">Beschreibung (EN)</label><textarea id="set-archive-desc-en" name="archive_description_en" class="form-control" rows="3" maxlength="2000"><?php echo $esc($settings['archive_description_en'] ?? ''); ?></textarea></div>
        </div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="archive_indexable" value="1"<?php echo $checked('archive_indexable'); ?>> <span class="form-check-label">Archivseite von Suchmaschinen indexieren lassen und in die Sitemap aufnehmen (nur wenn Promos laufen)</span></label>
    </section>

    <section class="admin-card pr-section">
        <div class="pr-section__head"><div><h3>Standard für Buttons</h3><p>Gilt für Promos ohne eigenen Button-Text.</p></div></div>
        <div class="pr-form-grid pr-form-grid--3">
            <div class="form-group"><label class="form-label" for="set-button">Button-Text</label><input id="set-button" type="text" name="default_button_label" class="form-control" maxlength="80" value="<?php echo $esc($settings['default_button_label'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="set-button-en">Button-Text (EN)</label><input id="set-button-en" type="text" name="default_button_label_en" class="form-control" maxlength="80" value="<?php echo $esc($settings['default_button_label_en'] ?? ''); ?>"></div>
            <div class="form-group">
                <label class="form-label" for="set-target">Ziel öffnen</label>
                <select id="set-target" name="default_target_behavior" class="form-select">
                    <option value="same_tab"<?php echo ($settings['default_target_behavior'] ?? 'same_tab') === 'same_tab' ? ' selected' : ''; ?>>Im gleichen Tab</option>
                    <option value="new_tab"<?php echo ($settings['default_target_behavior'] ?? '') === 'new_tab' ? ' selected' : ''; ?>>In neuem Tab</option>
                </select>
            </div>
        </div>
    </section>

    <section class="admin-card pr-section">
        <div class="pr-section__head"><div><h3>Klick-Export</h3><p>Jeder gezählte Klick löst den Hook <code>cms_promos_click_export</code> aus; optional zusätzlich ein JSON-POST an einen Webhook (GA4-/Pipeline-freundlich, ohne personenbezogene Daten).</p></div></div>
        <div class="form-group">
            <label class="form-label" for="set-webhook">Webhook-URL</label>
            <input id="set-webhook" type="url" name="click_export_webhook_url" class="form-control" maxlength="2048" autocomplete="off" placeholder="<?php echo $esc($webhookStored ? 'gespeichert – leer lassen zum Beibehalten' : 'https://example.com/webhooks/promo-click'); ?>" value="">
            <div class="form-help">Nur öffentliche http(s)-Adressen; Aufruf mit 2 s Timeout über den Core-HTTP-Client. Die URL wird <?php echo $webhookEncrypted || !$webhookStored ? 'verschlüsselt' : 'beim nächsten Speichern verschlüsselt'; ?> gespeichert und hier nicht angezeigt.</div>
        </div>
        <?php if ($webhookStored): ?>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="click_export_webhook_remove" value="1"> <span class="form-check-label">Webhook-URL entfernen</span></label>
        <?php endif; ?>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="click_export_enabled" value="1"<?php echo $checked('click_export_enabled'); ?>> <span class="form-check-label">Webhook-Export aktivieren</span></label>
    </section>

    <section class="admin-card pr-section">
        <div class="pr-section__head"><div><h3>Datenaufbewahrung</h3><p>Abgelaufene und archivierte Promos nach einer Frist automatisch löschen (täglicher Cron, Übersicht unter System → Datenaufbewahrung).</p></div></div>
        <div class="pr-form-grid pr-form-grid--3">
            <div class="form-group"><label class="form-label" for="set-retention">Löschfrist in Tagen</label><input id="set-retention" type="number" name="retention_days" class="form-control" min="0" max="3650" value="<?php echo max(0, (int) ($settings['retention_days'] ?? 0)); ?>"><div class="form-help">0 = nie löschen (Standard).</div></div>
        </div>
        <?php if ($retentionDays > 0): ?>
            <p class="pr-meta pr-meta--block">Aktuell würden <?php echo $retentionDue; ?> Promo(s) gelöscht.</p>
        <?php endif; ?>
    </section>

    <div class="pr-form-actions">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button>
    </div>
</form>

<?php if ($retentionDays > 0 && $retentionDue > 0): ?>
    <form method="post" action="<?php echo $esc($pageUrl); ?>" class="pr-danger-zone">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="action" value="run_retention">
        <button type="submit" class="btn btn-outline-danger" data-cms-confirm="<?php echo $esc($retentionDue . ' abgelaufene/archivierte Promo(s) jetzt endgültig löschen?'); ?>"><i class="ti ti-trash" aria-hidden="true"></i> Jetzt bereinigen (<?php echo $retentionDue; ?>)</button>
    </form>
<?php endif; ?>

<section class="admin-card pr-section">
    <div class="pr-section__head"><div><h3>Export &amp; Import</h3><p>Promos als CSV (Auswertung) oder die komplette Konfiguration als JSON (Sicherung, Umzug).</p></div></div>
    <div class="pr-split">
        <div class="pr-split__col">
            <h4>Export</h4>
            <div class="pr-button-row">
                <form method="post" action="<?php echo $esc($pageUrl); ?>" class="pr-inline-form">
                    <input type="hidden" name="export_token" value="<?php echo $esc($exportToken); ?>">
                    <input type="hidden" name="action" value="export_csv">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> Promos als CSV</button>
                </form>
                <form method="post" action="<?php echo $esc($pageUrl); ?>" class="pr-inline-form">
                    <input type="hidden" name="export_token" value="<?php echo $esc($exportToken); ?>">
                    <input type="hidden" name="action" value="export_json">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-file-code" aria-hidden="true"></i> Konfiguration als JSON</button>
                </form>
            </div>
            <p class="pr-meta pr-meta--block">Der JSON-Export enthält keine Zähler und keine Webhook-URL.</p>
        </div>
        <div class="pr-split__col">
            <h4>Import</h4>
            <form method="post" action="<?php echo $esc($pageUrl); ?>" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="action" value="import_json">
                <div class="form-group">
                    <label class="form-label" for="set-import">JSON-Datei aus dem Promo-Export</label>
                    <input id="set-import" type="file" name="import_file" class="form-control" accept=".json,application/json" required>
                    <div class="form-help">Max. <?php echo $maxImportMb; ?> MB. Platzierungen und Promos werden über den Slug abgeglichen (neu anlegen oder aktualisieren); Zähler bleiben unverändert.</div>
                </div>
                <button type="submit" class="btn btn-outline-primary" data-cms-confirm="Konfiguration importieren? Vorhandene Einträge mit gleichem Slug werden überschrieben."><i class="ti ti-upload" aria-hidden="true"></i> Importieren</button>
            </form>
        </div>
    </div>
</section>

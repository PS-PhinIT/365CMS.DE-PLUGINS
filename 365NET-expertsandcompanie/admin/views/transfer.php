<?php
/**
 * Unterseite „Import & Export“: Header → Meldung/Ergebnis → Export, Import, Spaltenreferenz, DSGVO-Auskunftsdateien.
 *
 * @var array{type:string,text:string}|null $notice
 * @var array<string, mixed>|null $importResult
 * @var string $csrfExport
 * @var string $csrfTransfer
 * @var string $action
 * @var int $maxBytes
 * @var int $maxRows
 * @var array{experts:list<string>,companies:list<string>} $columns
 * @var list<array{name:string,size:int,created_at:string}> $privacyFiles
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$entityOptions = ['experts' => 'Experts', 'companies' => 'Companies'];
?>
<div class="admin-page-header">
    <div>
        <h2>Import &amp; Export</h2>
        <p>Experts und Companies als CSV (UTF-8, Semikolon) sichern, in Excel bearbeiten und wieder einspielen.</p>
    </div>
</div>

<?php include __DIR__ . '/_notice.php'; ?>

<?php if (is_array($importResult)): ?>
    <section class="admin-card cms-excomp-card cms-excomp-result">
        <h3>Ergebnis des letzten Imports (<?= $e(($importResult['entity'] ?? '') === 'companies' ? 'Companies' : 'Experts') ?>)</h3>
        <ul class="cms-excomp-result__stats">
            <li><strong><?= (int) ($importResult['created'] ?? 0) ?></strong> angelegt</li>
            <li><strong><?= (int) ($importResult['updated'] ?? 0) ?></strong> aktualisiert</li>
            <li><strong><?= (int) ($importResult['skipped'] ?? 0) ?></strong> übersprungen</li>
        </ul>
        <?php if (!empty($importResult['errors']) && is_array($importResult['errors'])): ?>
            <ul class="cms-excomp-result__errors">
                <?php foreach (array_slice($importResult['errors'], 0, 25) as $error): ?>
                    <li><?= $e((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="cms-excomp-transfer-grid">
    <section class="admin-card cms-excomp-card">
        <h3><i class="ti ti-file-export" aria-hidden="true"></i> Export</h3>
        <p class="cms-excomp-muted">CSV mit allen Feldern außer der Editor-Formatierung. Zellen, die mit = + - @ beginnen, werden gegen Formel-Injektion mit ' maskiert.</p>
        <form method="post" action="<?= $e($action) ?>" class="cms-excomp-form-grid">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfExport) ?>">
            <?= CMS_365NET_Experts_And_Companie_Admin::selectField('entity', 'Bereich', 'experts', $entityOptions) ?>
            <?= CMS_365NET_Experts_And_Companie_Admin::selectField('status', 'Status', '', ['' => 'Alle', 'active' => 'Nur aktive', 'inactive' => 'Nur inaktive']) ?>
            <div class="cms-excomp-form-actions cms-excomp-field--wide">
                <button type="submit" name="excomp_action" value="template" class="btn btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> Leere Vorlage</button>
                <button type="submit" name="excomp_action" value="export" class="btn btn-primary"><i class="ti ti-download" aria-hidden="true"></i> CSV exportieren</button>
            </div>
        </form>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3><i class="ti ti-file-import" aria-hidden="true"></i> Import</h3>
        <p class="cms-excomp-muted">CSV-Datei (.csv, höchstens <?= $e(number_format($maxBytes / 1048576, 0, ',', '.')) ?> MB, <?= $e(number_format($maxRows, 0, ',', '.')) ?> Zeilen) im Format des Exports bzw. von <code>defaults/*.csv</code>. Trennzeichen ; oder , werden erkannt.</p>
        <form method="post" action="<?= $e($action) ?>" enctype="multipart/form-data" class="cms-excomp-form-grid" data-cms-confirm="CSV-Datei jetzt importieren?">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfTransfer) ?>">
            <input type="hidden" name="excomp_action" value="import">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) $maxBytes ?>">
            <?= CMS_365NET_Experts_And_Companie_Admin::selectField('entity', 'Bereich', 'experts', $entityOptions) ?>
            <div class="cms-excomp-field">
                <label class="form-label" for="excomp-csv-file">CSV-Datei</label>
                <input class="form-control" type="file" id="excomp-csv-file" name="csv_file" accept=".csv,text/csv" required>
            </div>
            <div class="cms-excomp-field--wide">
                <?= CMS_365NET_Experts_And_Companie_Admin::checkField('update_existing', 'Zeilen mit vorhandener ID aktualisieren (sonst als neue Einträge anlegen)', true) ?>
            </div>
            <div class="cms-excomp-form-actions cms-excomp-field--wide">
                <button type="submit" class="btn btn-primary"><i class="ti ti-upload" aria-hidden="true"></i> Importieren</button>
            </div>
        </form>
    </section>
</div>

<section class="admin-card cms-excomp-card">
    <details class="cms-excomp-details">
        <summary>Spalten der CSV-Dateien</summary>
        <p class="cms-excomp-muted">Pflichtspalten: FIRST_NAME und LAST_NAME (Experts) bzw. NAME (Companies). Fehlende Spalten behalten beim Aktualisieren ihren bisherigen Wert; ID, UPDATED_AT und unbekannte Spalten werden beim Anlegen ignoriert. Ja/Nein-Felder: 1/0, ja/nein, true/false.</p>
        <?php foreach ($columns as $entity => $headers): ?>
            <h4><?= $e($entityOptions[$entity] ?? $entity) ?></h4>
            <p class="cms-excomp-columns"><code><?= $e(implode(';', $headers)) ?></code></p>
        <?php endforeach; ?>
    </details>
</section>

<section class="admin-card cms-excomp-card">
    <h3><i class="ti ti-shield-lock" aria-hidden="true"></i> DSGVO-Auskunftsdateien</h3>
    <p class="cms-excomp-muted">Bei „Auskunft erteilen“ unter Recht &amp; Datenschutz → DSGVO-Anfragen schreibt das Plugin die Experts- und Company-Einträge mit der E-Mail-Adresse der Person als JSON in den privaten Speicher. Dateien werden nach 30 Tagen automatisch entfernt.</p>
    <?php if ($privacyFiles === []): ?>
        <p class="cms-excomp-muted">Keine Auskunftsdateien vorhanden.</p>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Datei</th><th scope="col">Erstellt</th><th scope="col">Größe</th><th scope="col" class="cms-excomp-table__actions">Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($privacyFiles as $file): ?>
                    <tr>
                        <td><code><?= $e($file['name']) ?></code></td>
                        <td><?= $e($file['created_at']) ?></td>
                        <td><?= $e(number_format($file['size'] / 1024, 1, ',', '.')) ?> KB</td>
                        <td class="cms-excomp-table__actions">
                            <form method="post" action="<?= $e($action) ?>" class="cms-excomp-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfExport) ?>">
                                <input type="hidden" name="excomp_action" value="privacy_download">
                                <input type="hidden" name="file" value="<?= $e($file['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Herunterladen"><i class="ti ti-download" aria-hidden="true"></i><span class="visually-hidden">Herunterladen</span></button>
                            </form>
                            <form method="post" action="<?= $e($action) ?>" class="cms-excomp-inline-form" data-cms-confirm="Auskunftsdatei endgültig löschen?">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfTransfer) ?>">
                                <input type="hidden" name="excomp_action" value="privacy_delete">
                                <input type="hidden" name="file" value="<?= $e($file['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

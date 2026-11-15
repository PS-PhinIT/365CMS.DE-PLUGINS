<?php
/**
 * Audit-Log mit Filter, Pagination und CSV-Export.
 *
 * @var callable $e
 * @var array $filters
 * @var array $pageData
 * @var list<string> $entityTypes
 * @var CMS_Projects_Service $service
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo $this->pageHeader('Audit-Log', 'Protokoll aller Änderungen an Projekten, Boards, Widgets, Tickets und Einstellungen.');
$entityLabels = ['project' => 'Projekt', 'board' => 'Board', 'widget' => 'Widget', 'task' => 'Ticket', 'settings' => 'Einstellungen'];
$query = array_filter([
    'q' => $filters['q'],
    'result' => $filters['result'],
    'entity_type' => $filters['entity_type'],
], static fn (string $value): bool => $value !== '');
?>
<form method="get" action="<?php echo $e($this->url(self::PAGE_AUDIT)); ?>" class="cp-toolbar toolbar">
    <label class="cp-toolbar__field cp-toolbar__field--grow">
        <span class="form-label">Suche</span>
        <input type="search" class="form-control" name="q" maxlength="80" value="<?php echo $e($filters['q']); ?>" placeholder="Aktion oder Benutzer">
    </label>
    <label class="cp-toolbar__field">
        <span class="form-label">Bereich</span>
        <select class="form-select" name="entity_type">
            <option value="">Alle</option>
            <?php foreach ($entityTypes as $type): ?>
                <option value="<?php echo $e($type); ?>"<?php echo $filters['entity_type'] === $type ? ' selected' : ''; ?>><?php echo $e($entityLabels[$type] ?? $type); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="cp-toolbar__field">
        <span class="form-label">Ergebnis</span>
        <select class="form-select" name="result">
            <option value="">Alle</option>
            <option value="success"<?php echo $filters['result'] === 'success' ? ' selected' : ''; ?>>Erfolgreich</option>
            <option value="failure"<?php echo $filters['result'] === 'failure' ? ' selected' : ''; ?>>Fehlgeschlagen</option>
        </select>
    </label>
    <div class="cp-toolbar__actions">
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
    </div>
</form>

<section class="admin-card cp-admin-section">
    <div class="cp-panel-header cp-panel-header--split">
        <h3><?php echo (int) $pageData['total']; ?> Einträge</h3>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_AUDIT, $query)); ?>">
            <?php echo $this->hiddenFields('export_audit'); ?>
            <?php foreach (['q', 'result', 'entity_type'] as $key): ?>
                <input type="hidden" name="<?php echo $e($key); ?>" value="<?php echo $e($filters[$key]); ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV exportieren</button>
        </form>
    </div>
    <?php if ($pageData['items'] === []): ?>
        <p class="cms-plugin-empty">Keine Einträge für diese Auswahl.</p>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr><th>Zeitpunkt</th><th>Benutzer</th><th>Aktion</th><th>Bereich</th><th>Projekt</th><th>Ergebnis</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pageData['items'] as $log): ?>
                        <tr>
                            <td><?php echo $e($this->formatDateTime((string) $log['created_at'])); ?></td>
                            <td><?php echo $e($log['actor_name'] !== '' ? $log['actor_name'] : 'system'); ?></td>
                            <td><?php echo $e($service->describeAuditAction((string) $log['action'])); ?> <code class="cp-muted"><?php echo $e($log['action']); ?></code></td>
                            <td><?php echo $e($entityLabels[$log['entity_type']] ?? $log['entity_type']); ?><?php echo (int) $log['entity_id'] > 0 ? ' #' . (int) $log['entity_id'] : ''; ?></td>
                            <td>
                                <?php if ((int) $log['project_id'] > 0): ?>
                                    <a href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['project_id' => (int) $log['project_id']])); ?>">#<?php echo (int) $log['project_id']; ?></a>
                                <?php else: ?>–<?php endif; ?>
                            </td>
                            <td><span class="cp-badge cp-badge--<?php echo $log['result'] === 'success' ? 'success' : 'danger'; ?>"><?php echo $log['result'] === 'success' ? 'Erfolgreich' : 'Fehlgeschlagen'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo $this->pagination($pageData, self::PAGE_AUDIT, $query); ?>
    <?php endif; ?>
</section>

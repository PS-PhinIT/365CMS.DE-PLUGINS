<?php
/**
 * Audit-Log der Projektverwaltung.
 *
 * @var list<array<string,mixed>> $logs
 * @var list<array<string,mixed>> $projects
 * @var array{project:int,result:string,entity:string} $filters
 * @var int $retentionDays
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$auditUrl = CMS_Projects_Admin::pageUrl('cms-projects-audit');
$projectNames = array_column($projects, 'name', 'id');
$entities = ['project' => 'Projekt', 'board' => 'Board', 'widget' => 'Widget', 'task' => 'Ticket'];
$filtered = $filters['project'] > 0 || $filters['result'] !== '' || $filters['entity'] !== '';
?>
<div class="admin-page-header">
    <div>
        <h2>Audit-Log</h2>
        <p>Alle Änderungen an Projekten, Boards, Widgets und Tickets (neueste 200). <?php echo $retentionDays > 0 ? $e(sprintf('Einträge werden nach %d Tagen automatisch gelöscht.', $retentionDays)) : 'Keine automatische Löschung eingestellt.'; ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::exportUrl('audit_csv', ['project' => $filters['project'], 'result' => $filters['result'], 'entity' => $filters['entity']])); ?>"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV-Export</a>
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::pageUrl('cms-projects-settings')); ?>"><i class="ti ti-clock-cog" aria-hidden="true"></i> Löschfrist</a>
    </div>
</div>

<form method="get" action="<?php echo $e($auditUrl); ?>" class="cp-toolbar" aria-label="Audit-Log filtern">
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-audit-project">Projekt</label>
        <select id="cp-audit-project" name="project" class="form-select">
            <option value="0">Alle</option>
            <?php foreach ($projects as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo $filters['project'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo $e($item['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-audit-entity">Bereich</label>
        <select id="cp-audit-entity" name="entity" class="form-select">
            <option value="">Alle</option>
            <?php foreach ($entities as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['entity'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cp-toolbar__field">
        <label class="form-label" for="cp-audit-result">Ergebnis</label>
        <select id="cp-audit-result" name="result" class="form-select">
            <option value="">Alle</option>
            <option value="success" <?php echo $filters['result'] === 'success' ? 'selected' : ''; ?>>Erfolgreich</option>
            <option value="failure" <?php echo $filters['result'] === 'failure' ? 'selected' : ''; ?>>Fehlgeschlagen</option>
        </select>
    </div>
    <div class="cp-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filtered): ?><a class="btn btn-link" href="<?php echo $e($auditUrl); ?>">Zurücksetzen</a><?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($logs === []): ?>
        <div class="empty-state"><p><strong>Keine Audit-Einträge</strong></p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Zeitpunkt</th><th scope="col">Benutzer</th><th scope="col">Aktion</th><th scope="col">Bereich</th><th scope="col">Projekt</th><th scope="col">Ergebnis</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $pid = (int) ($log['project_id'] ?? 0); ?>
                    <tr>
                        <td class="cp-nowrap"><?php echo $e(date('d.m.Y H:i', (int) strtotime((string) $log['created_at']))); ?></td>
                        <td><?php echo $e(($log['actor_name'] ?? '') !== '' ? $log['actor_name'] : 'system'); ?></td>
                        <td><code><?php echo $e($log['action']); ?></code></td>
                        <td><?php echo $e($entities[$log['entity_type']] ?? $log['entity_type']); ?><?php echo (int) $log['entity_id'] > 0 ? ' #' . (int) $log['entity_id'] : ''; ?></td>
                        <td><?php if ($pid > 0 && isset($projectNames[$pid])): ?><a href="<?php echo $e(CMS_Projects_Admin::projectUrl($pid)); ?>"><?php echo $e($projectNames[$pid]); ?></a><?php else: ?><?php echo $pid > 0 ? '#' . $pid . ' (gelöscht)' : '–'; ?><?php endif; ?></td>
                        <td><span class="cp-badge cp-badge--<?php echo ($log['result'] ?? '') === 'success' ? 'active' : 'overdue'; ?>"><?php echo ($log['result'] ?? '') === 'success' ? 'OK' : 'Fehler'; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

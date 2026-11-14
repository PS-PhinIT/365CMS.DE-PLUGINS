<?php
/**
 * Widgets eines Projekts: Liste mit Vorschau, Bearbeiten/Löschen, Formular.
 *
 * @var CMS_Projects_Service $service
 * @var array<string,mixed> $project
 * @var int $projectId
 * @var string $csrfToken
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$projectBoards = [];
require __DIR__ . '/helpers.php';

$projectWidgets = $service->getProjectWidgets($projectId, 'admin');
$widgetTypes = $service->getWidgetTypes();
$widgetScopes = $service->getWidgetScopes();
$editWidget = null;
$editId = max(0, (int) ($_GET['widget_id'] ?? 0));
if ($editId > 0) {
    $candidate = $service->findWidget($editId);
    $editWidget = $candidate !== null && (int) $candidate['project_id'] === $projectId ? $candidate : null;
}
$payloadJson = $editWidget !== null
    ? (string) json_encode($editWidget['payload_data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : "{\n  \"items\": [\n    { \"label\": \"Fortschritt\", \"value\": \"68%\" }\n  ]\n}";
?>
<div class="cp-split">
    <section class="admin-card">
        <div class="cp-panel-header"><h3>Widgets (<?php echo count($projectWidgets); ?>)</h3></div>
        <?php if ($projectWidgets === []): ?>
            <div class="empty-state"><p><strong>Noch keine Widgets</strong></p><p class="cp-muted">Widgets ergänzen Dashboards um Text, Checklisten, Links, Kennzahlen oder Zeitleisten.</p></div>
        <?php else: ?>
            <div class="cp-widget-grid">
                <?php foreach ($projectWidgets as $widget): ?>
                    <article class="cp-widget-card">
                        <div class="cp-item-card__head">
                            <div>
                                <strong><?php echo $e($widget['title']); ?></strong>
                                <span class="cp-badge"><?php echo $e($widget['widget_label'] ?? ''); ?></span>
                                <span class="cp-badge"><?php echo $e($widgetScopes[$widget['scope'] ?? 'both'] ?? ($widget['scope'] ?? 'both')); ?></span>
                                <?php if (empty($widget['is_active'])): ?><span class="cp-badge cp-badge--archived">inaktiv</span><?php endif; ?>
                            </div>
                            <div class="cp-action-group">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'widgets', ['widget_id' => (int) $widget['id']])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Widget bearbeiten</span></a>
                                <form method="post" data-cms-confirm="Widget „<?php echo $e($widget['title']); ?>“ löschen?">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="cms_projects_action" value="delete_widget">
                                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                    <input type="hidden" name="widget_id" value="<?php echo (int) $widget['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Widget löschen</span></button>
                                </form>
                            </div>
                        </div>
                        <?php echo $renderWidgetPreview($widget); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card" id="cp-widget-form">
        <div class="cp-panel-header"><h3><?php echo $editWidget !== null ? 'Widget bearbeiten' : 'Widget anlegen'; ?></h3></div>
        <form method="post" class="admin-form cp-form">
            <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
            <input type="hidden" name="cms_projects_action" value="save_widget">
            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
            <input type="hidden" name="widget_id" value="<?php echo (int) ($editWidget['id'] ?? 0); ?>">
            <div class="form-group"><label class="form-label required" for="cp-widget-title">Titel</label><input id="cp-widget-title" class="form-control" type="text" name="title" maxlength="190" required placeholder="z. B. KPI Snapshot" value="<?php echo $e($editWidget['title'] ?? ''); ?>"></div>
            <div class="cp-form-grid">
                <div class="form-group">
                    <label class="form-label" for="cp-widget-type">Widget-Typ</label>
                    <select id="cp-widget-type" class="form-select" name="widget_type">
                        <?php foreach ($widgetTypes as $key => $config): ?>
                            <option value="<?php echo $e($key); ?>" <?php echo (($editWidget['widget_type'] ?? 'text') === $key) ? 'selected' : ''; ?>><?php echo $e($config['label'] ?? $key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cp-widget-scope">Anzeige</label>
                    <select id="cp-widget-scope" class="form-select" name="scope">
                        <?php foreach ($widgetScopes as $key => $label): ?>
                            <option value="<?php echo $e($key); ?>" <?php echo (($editWidget['scope'] ?? 'both') === $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="cp-widget-position">Position</label><input id="cp-widget-position" class="form-control" type="number" name="position" min="0" max="9999" value="<?php echo (int) ($editWidget['position'] ?? count($projectWidgets)); ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="cp-widget-content">Text</label><textarea id="cp-widget-content" class="form-control" name="content" rows="4" maxlength="20000"><?php echo $e($editWidget['content'] ?? ''); ?></textarea></div>
            <div class="form-group"><label class="form-label" for="cp-widget-payload">Daten (JSON)</label><textarea id="cp-widget-payload" class="form-control cp-code-input" name="payload" rows="10" spellcheck="false" autocomplete="off"><?php echo $e($payloadJson); ?></textarea><div class="form-hint"><code>items</code>: checklist <code>{label, done}</code>, links <code>{label, url}</code> (nur http/https), stats <code>{label, value}</code>, timeline <code>{date, label}</code>.</div></div>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo $editWidget === null || !empty($editWidget['is_active']) ? 'checked' : ''; ?>> <span class="form-check-label">Widget aktiv</span></label>
            <div class="cp-form-actions">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Widget speichern</button>
                <?php if ($editWidget !== null): ?><a class="btn btn-link" href="<?php echo $e(CMS_Projects_Admin::projectUrl($projectId, 'widgets')); ?>">Neues Widget</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>

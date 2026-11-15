<?php
/**
 * Widgets eines Projekts: Liste mit Sammelaktionen, Formular und Vorschau.
 *
 * @var callable $e
 * @var array $projectOptions
 * @var array|null $project
 * @var int $projectId
 * @var array $widgetTypes
 * @var array $widgetScopes
 * @var array $widgets
 * @var array $widgetFormValues
 * @var int $editWidgetId
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo $this->pageHeader('Widgets', 'Text-, Checklisten-, Link-, Kennzahlen- und Timeline-Widgets für Mitglieder- und öffentliche Ansicht.', $project !== null ? [
    ['label' => 'Neues Widget', 'url' => $this->url(self::PAGE_WIDGETS, ['project_id' => $projectId]) . '#cp-widget-form', 'icon' => 'ti ti-plus', 'primary' => true],
] : []);

if ($projectOptions === []): ?>
    <section class="admin-card cp-admin-section">
        <p class="cms-plugin-empty">Noch keine Projekte vorhanden. <a href="<?php echo $e($this->url(self::PAGE_PROJECTS, ['action' => 'new'])); ?>">Erstes Projekt anlegen</a></p>
    </section>
    <?php return; ?>
<?php endif;

echo $this->projectSwitcher(self::PAGE_WIDGETS, $projectOptions, $projectId);

if ($project === null) {
    return;
}
?>
<section class="admin-card cp-admin-section">
    <div class="cp-panel-header"><h3>Widgets von „<?php echo $e($project['name']); ?>“</h3></div>
    <?php if ($widgets === []): ?>
        <p class="cms-plugin-empty">Für dieses Projekt gibt es noch keine Widgets.</p>
    <?php else: ?>
        <form method="post" action="<?php echo $e($this->url(self::PAGE_WIDGETS, ['project_id' => $projectId])); ?>" data-cms-confirm="Sammelaktion auf die ausgewählten Widgets anwenden?">
            <?php echo $this->hiddenFields('bulk_widgets', $projectId); ?>
            <div class="cp-bulkbar">
                <label class="cp-toolbar__field">
                    <span class="visually-hidden">Sammelaktion</span>
                    <select class="form-select" name="bulk_operation" required>
                        <option value="">Sammelaktion wählen</option>
                        <option value="activate">Aktivieren</option>
                        <option value="deactivate">Deaktivieren</option>
                        <option value="delete">Löschen</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-outline-secondary">Anwenden</button>
            </div>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th class="cp-col-check"><input type="checkbox" class="form-check-input" data-cp-check-all aria-label="Alle auswählen"></th>
                            <th>Titel</th><th>Typ</th><th>Bereich</th><th>Position</th><th>Status</th><th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($widgets as $widget): ?>
                            <tr>
                                <td class="cp-col-check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo (int) $widget['id']; ?>" aria-label="Widget auswählen"></td>
                                <td><strong><?php echo $e($widget['title']); ?></strong></td>
                                <td><?php echo $e($widget['widget_label']); ?></td>
                                <td><?php echo $e($widgetScopes[$widget['scope']] ?? $widget['scope']); ?></td>
                                <td><?php echo (int) $widget['position']; ?></td>
                                <td><span class="cp-badge cp-badge--<?php echo !empty($widget['is_active']) ? 'success' : 'muted'; ?>"><?php echo !empty($widget['is_active']) ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                                <td class="cp-row-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($this->url(self::PAGE_WIDGETS, ['project_id' => $projectId, 'widget_id' => (int) $widget['id']]) . '#cp-widget-form'); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="admin-card cp-admin-section" id="cp-widget-form">
    <div class="cp-panel-header">
        <h3><?php echo $editWidgetId > 0 ? 'Widget bearbeiten' : 'Neues Widget'; ?></h3>
        <p>Strukturierte Inhalte als JSON (<code>items</code>); Links nur mit http/https und öffentlichen Hosts.</p>
    </div>
    <form method="post" action="<?php echo $e($this->url(self::PAGE_WIDGETS, ['project_id' => $projectId])); ?>" class="cp-form">
        <?php echo $this->hiddenFields('save_widget', $projectId); ?>
        <input type="hidden" name="widget_id" value="<?php echo $editWidgetId; ?>">
        <div class="cp-form-grid-2">
            <label><span class="form-label">Titel</span><input type="text" class="form-control" name="title" maxlength="190" required placeholder="z. B. KPI-Überblick" value="<?php echo $e($widgetFormValues['title'] ?? ''); ?>"></label>
            <label>
                <span class="form-label">Widget-Typ</span>
                <select class="form-select" name="widget_type">
                    <?php foreach ($widgetTypes as $key => $config): ?>
                        <option value="<?php echo $e($key); ?>"<?php echo ($widgetFormValues['widget_type'] ?? 'stats') === $key ? ' selected' : ''; ?>><?php echo $e($config['label']); ?> – <?php echo $e($config['description']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="cp-form-grid-2">
            <label>
                <span class="form-label">Bereich</span>
                <select class="form-select" name="scope">
                    <?php foreach ($widgetScopes as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>"<?php echo ($widgetFormValues['scope'] ?? 'both') === $key ? ' selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span class="form-label">Position</span><input type="number" class="form-control" name="position" min="0" max="9999" value="<?php echo (int) ($widgetFormValues['position'] ?? 0); ?>"></label>
        </div>
        <label><span class="form-label">Textinhalt</span><textarea class="form-control" name="content" rows="3" maxlength="20000" placeholder="Freitext (Text-Widget) oder Einleitung für strukturierte Widgets."><?php echo $e($widgetFormValues['content'] ?? ''); ?></textarea></label>
        <label>
            <span class="form-label">Einträge (JSON)</span>
            <textarea class="form-control cp-code-input" name="payload" rows="10" spellcheck="false" autocomplete="off"><?php echo $e($widgetFormValues['payload_json'] ?? ''); ?></textarea>
            <small class="cp-field-hint">Checkliste: <code>label</code>, <code>done</code> · Links: <code>label</code>, <code>url</code> · Kennzahlen: <code>label</code>, <code>value</code> · Timeline: <code>date</code>, <code>label</code>. Leer lassen für die Vorlage des Typs.</small>
        </label>
        <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1"<?php echo !empty($widgetFormValues['is_active']) ? ' checked' : ''; ?>><span class="form-check-label">Widget aktiv</span></label>
        <div class="cp-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> <?php echo $editWidgetId > 0 ? 'Widget speichern' : 'Widget anlegen'; ?></button>
            <?php if ($editWidgetId > 0): ?>
                <a class="btn btn-link" href="<?php echo $e($this->url(self::PAGE_WIDGETS, ['project_id' => $projectId]) . '#cp-widget-form'); ?>">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($widgets !== []): ?>
    <section class="admin-card cp-admin-section">
        <div class="cp-panel-header"><h3>Vorschau</h3></div>
        <div class="cp-widget-grid">
            <?php foreach ($widgets as $widget): ?>
                <?php echo CMS_Projects_View::renderWidget($widget); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

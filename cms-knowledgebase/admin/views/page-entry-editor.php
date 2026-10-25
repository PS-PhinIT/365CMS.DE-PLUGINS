<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="kb-admin-shell">
    <div class="admin-page-header">
        <div>
            <h2><?php echo $entry !== null ? 'Knowledgebase-Eintrag bearbeiten' : 'Knowledgebase-Eintrag anlegen'; ?></h2>
            <p>Pflege Fokusbegriff, Synonyme, strukturierte Inhaltsbereiche und Verlinkungslogik auf einer separaten Editor-Seite.</p>
        </div>
        <div class="header-actions">
            <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-entries" class="btn btn-secondary btn-sm">← Zur Liste</a>
            <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-categories" class="btn btn-secondary btn-sm">Kategorien</a>
            <?php if ($entry !== null && !empty($entry['slug'])): ?>
                <a href="/kb/<?php echo rawurlencode((string) $entry['slug']); ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener noreferrer">Öffnen</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($notice)): ?>
        <div class="alert alert-<?php echo htmlspecialchars((string) ($notice['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="admin-card kb-form-card kb-form-card--single">
        <div class="kb-panel-header">
            <div>
                <h3><?php echo $entry !== null ? 'Eintrag bearbeiten' : 'Neuen Eintrag anlegen'; ?></h3>
                <p>Ein Fokusbegriff bildet die Hauptverlinkung. Synonyme erweitern die Trefferbasis und die Inhaltsbereiche bilden die KB-Zielseite.</p>
            </div>
        </div>

        <form method="post" class="kb-settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save_entry">
            <input type="hidden" name="entry_id" value="<?php echo (int) ($entry['id'] ?? 0); ?>">

            <section class="kb-settings-group kb-settings-group--full">
                <h3>Basisdaten</h3>
                <div class="kb-form-grid">
                    <label>
                        <span>Titel</span>
                        <input type="text" name="title" value="<?php echo htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>

                    <label>
                        <span>Fokusbegriff</span>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars((string) ($entry['keyword'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>

                    <label>
                        <span>Slug</span>
                        <input type="text" name="slug" value="<?php echo htmlspecialchars((string) ($entry['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="wird automatisch erzeugt">
                    </label>

                    <label>
                        <span>Kategorie</span>
                        <?php $currentCategory = (string) ($entry['category'] ?? ''); ?>
                        <select name="category">
                            <option value="">– Keine Kategorie –</option>
                            <?php foreach ($categories as $categoryItem): ?>
                                <?php $categoryName = (string) ($categoryItem['category'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $categoryName === $currentCategory ? ' selected' : ''; ?>><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($categories)): ?>
                            <small>Noch keine Kategorien vorhanden – bitte zuerst unter <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-categories">Kategorien</a> anlegen.</small>
                        <?php endif; ?>
                    </label>

                    <label>
                        <span>Priorität</span>
                        <input type="number" name="priority" min="1" max="9999" value="<?php echo (int) ($entry['priority'] ?? 100); ?>">
                    </label>

                    <label>
                        <span>Max. Links pro Seite</span>
                        <input type="number" name="max_links_per_page" min="1" max="20" value="<?php echo (int) ($entry['max_links_per_page'] ?? 1); ?>">
                    </label>
                </div>
            </section>

            <section class="kb-settings-group kb-settings-group--full">
                <h3>Kurzinhalte & Verlinkung</h3>
                <div class="kb-form-grid">
                    <label class="kb-form-grid__full">
                        <span>Kurzbeschreibung / Excerpt</span>
                        <textarea name="excerpt" rows="3"><?php echo htmlspecialchars((string) ($entry['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <label class="kb-form-grid__full">
                        <span>Tooltip-Text</span>
                        <textarea name="tooltip_text" rows="3" placeholder="Kurze Erklärung für Hover/Fokus auf dem Link"><?php echo htmlspecialchars((string) ($entry['tooltip_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <label class="kb-form-grid__full">
                        <span>Synonyme</span>
                        <textarea name="synonyms" rows="4" placeholder="Ein Begriff pro Zeile oder kommasepariert"><?php echo htmlspecialchars((string) ($entry['synonyms'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="kb-settings-group kb-settings-group--full">
                <h3>Inhaltsbereiche</h3>
                <p class="kb-field-help">Jeder Bereich hat einen eigenen Block-Editor und erscheint auf der KB-Seite als eigener Abschnitt mit fester Überschrift. Leere Bereiche werden public ausgeblendet. Tabellen-Shortcodes wie <code>[site-table id="1"]</code> und Präfix-Platzhalter wie <code>{{cms_prefix}}</code> werden public weiterhin aufgelöst.</p>

                <div class="kb-section-editors">
                    <?php foreach (\CmsKnowledgebase\Support\ContentSections::definitions() as $sectionKey => $sectionDefinition): ?>
                        <?php $sectionColumn = $sectionDefinition['column']; ?>
                        <div class="kb-section-editor" id="kb-section-<?php echo htmlspecialchars($sectionKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="kb-section-editor__head">
                                <strong class="kb-section-editor__title"><?php echo htmlspecialchars($sectionDefinition['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="kb-section-editor__help"><?php echo htmlspecialchars($sectionDefinition['help'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php echo \CmsKnowledgebase\Admin\Pages::renderSectionEditor(
                                $sectionColumn,
                                (string) ($entry[$sectionColumn] ?? ''),
                                $sectionDefinition['label'],
                                (int) $sectionDefinition['height']
                            ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="kb-settings-group kb-settings-group--full">
                <h3>Status & Verlinkung</h3>
                <div class="kb-checkbox-row kb-form-grid__full">
                    <label><input type="checkbox" name="is_active" value="1" <?php echo ((int) ($entry['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>> Aktiv</label>
                    <label><input type="checkbox" name="is_whole_word" value="1" <?php echo ((int) ($entry['is_whole_word'] ?? 1) === 1) ? 'checked' : ''; ?>> Nur ganze Wörter verlinken</label>
                    <label><input type="checkbox" name="is_case_sensitive" value="1" <?php echo ((int) ($entry['is_case_sensitive'] ?? 0) === 1) ? 'checked' : ''; ?>> Groß-/Kleinschreibung beachten</label>
                </div>
            </section>

            <div class="kb-form-actions kb-form-grid__full">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <?php if ($entry !== null): ?>
                    <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-entry-editor" class="btn btn-secondary">Neuen Eintrag anlegen</a>
                <?php endif; ?>
                <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-entries" class="btn btn-secondary">Zurück zur Liste</a>
            </div>
        </form>
    </div>
</div>

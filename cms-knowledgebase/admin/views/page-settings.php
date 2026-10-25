<?php declare(strict_types=1); if (!defined('ABSPATH')) exit; ?>

<div class="kb-admin-shell">
    <div class="admin-page-header">
        <div>
            <h2>Knowledgebase-Einstellungen</h2>
            <p>Steuere Logik, Design und öffentliche Darstellung der Knowledgebase zentral an einer Stelle.</p>
        </div>
    </div>

    <?php if (!empty($notice)): ?>
        <div class="alert alert-<?php echo ($notice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid kb-settings-summary-grid">
        <div class="kb-note-card">
            <span class="kb-note-card__eyebrow">Auto-Linking</span>
            <span class="kb-note-card__title"><?php echo ($settings['enable_autolink'] ?? '0') === '1' ? 'Aktiv' : 'Inaktiv'; ?></span>
            <span class="kb-note-card__text"><?php echo number_format((int) ($stats['active_entries'] ?? 0)); ?> aktive Begriffe stehen aktuell für Verlinkungen bereit.</span>
        </div>
        <div class="kb-note-card">
            <span class="kb-note-card__eyebrow">Design-Akzent</span>
            <span class="kb-note-card__title"><?php echo htmlspecialchars((string) ($settings['design_accent_color'] ?? '#0D9488'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="kb-note-card__text">Wird für KB-Links, Fokus-States und das Theme-Styling der öffentlichen Seiten genutzt.</span>
        </div>
        <div class="kb-note-card">
            <span class="kb-note-card__eyebrow">Content-Breite</span>
            <span class="kb-note-card__title"><?php echo (int) ($settings['content_max_width'] ?? 1200); ?>px</span>
            <span class="kb-note-card__text">Diese Breite landet direkt als CSS-Variable im Frontend und wird vom Theme ausgewertet.</span>
        </div>
    </div>

    <div class="admin-card kb-settings-card">
        <form method="post" class="admin-form kb-settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="redirect_page" value="<?php echo htmlspecialchars((string) ($activeSettingsPage ?? 'knowledgebase-settings-general'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect_section" value="<?php echo htmlspecialchars((string) ($settingsSection ?? 'general'), ENT_QUOTES, 'UTF-8'); ?>">

            <?php if (($settingsSection ?? 'general') === 'general'): ?>
                <h3>Allgemeine Einstellungen</h3>
                <div class="alert alert-info">Diese Optionen steuern Logik, Navigation und Sichtbarkeit im öffentlichen Bereich.</div>

                <div class="kb-settings-grid kb-settings-grid--cards kb-settings-grid--general">
                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Auto-Linking</h3>
                        <p class="kb-settings-group__intro">Regelt, wie Begriffe automatisch verlinkt und technisch ausgegeben werden.</p>
                        <label class="kb-setting-toggle"><input type="checkbox" name="enable_autolink" value="1" <?php echo ($settings['enable_autolink'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Auto-Linking aktivieren</span></label>
                        <label class="kb-setting-toggle"><input type="checkbox" name="enable_output_buffer" value="1" <?php echo ($settings['enable_output_buffer'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Output-Buffer-Fallback aktivieren</span></label>
                        <label class="kb-setting-toggle"><input type="checkbox" name="nofollow_links" value="1" <?php echo ($settings['nofollow_links'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Links mit <code>nofollow</code> markieren</span></label>
                        <label class="kb-setting-toggle"><input type="checkbox" name="open_links_new_tab" value="1" <?php echo ($settings['open_links_new_tab'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Links in neuem Tab öffnen</span></label>
                        <label class="kb-setting-field">
                            <span>Max. Links pro Seite</span>
                            <input type="number" name="max_links_per_page" min="1" max="25" value="<?php echo (int) ($settings['max_links_per_page'] ?? 6); ?>">
                        </label>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Navigation & Tooltips</h3>
                        <p class="kb-settings-group__intro">Steuert Tooltip-Verhalten und den Link zur Knowledgebase in der Hauptnavigation.</p>
                        <label class="kb-setting-toggle"><input type="checkbox" name="enable_tooltips" value="1" <?php echo ($settings['enable_tooltips'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Tooltip-Vorschau aktivieren</span></label>
                        <label class="kb-setting-toggle"><input type="checkbox" name="show_nav_link" value="1" <?php echo ($settings['show_nav_link'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Link in Hauptnavigation ausgeben</span></label>
                        <label class="kb-setting-field">
                            <span>Label für Navigation</span>
                            <input type="text" name="nav_label" maxlength="40" value="<?php echo htmlspecialchars((string) ($settings['nav_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Knowledgebase-Seite</h3>
                        <p class="kb-settings-group__intro">Texte und Module für die öffentliche Übersicht unter <code>/kb</code>.</p>
                        <label class="kb-setting-field">
                            <span>Archiv-Titel</span>
                            <input type="text" name="archive_title" maxlength="120" value="<?php echo htmlspecialchars((string) ($settings['archive_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="kb-setting-field kb-setting-field--textarea">
                            <span>Archiv-Einleitung</span>
                            <textarea name="archive_intro" rows="4"><?php echo htmlspecialchars((string) ($settings['archive_intro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <div class="kb-settings-toggles">
                            <label class="kb-setting-toggle"><input type="checkbox" name="show_search" value="1" <?php echo ($settings['show_search'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Suchformular im Archiv anzeigen</span></label>
                            <label class="kb-setting-toggle"><input type="checkbox" name="show_category_sidebar" value="1" <?php echo ($settings['show_category_sidebar'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Kategorien-Sidebar anzeigen</span></label>
                            <label class="kb-setting-toggle"><input type="checkbox" name="show_keyword_badges" value="1" <?php echo ($settings['show_keyword_badges'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Keyword-Hinweise anzeigen</span></label>
                            <label class="kb-setting-toggle"><input type="checkbox" name="show_related_entries" value="1" <?php echo ($settings['show_related_entries'] ?? '0') === '1' ? 'checked' : ''; ?>> <span>Verwandte Einträge anzeigen</span></label>
                        </div>
                        <label class="kb-setting-field">
                            <span>Anzahl verwandter 365CMS-Artikel</span>
                            <input type="number" name="related_posts_limit" min="3" max="6" value="<?php echo (int) ($settings['related_posts_limit'] ?? 4); ?>">
                        </label>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Glossar-Seite</h3>
                        <p class="kb-settings-group__intro">Eigene Texte für die kompaktere Übersicht unter <code>/glossar</code>.</p>
                        <label class="kb-setting-field">
                            <span>Glossar-Titel</span>
                            <input type="text" name="glossary_title" maxlength="120" value="<?php echo htmlspecialchars((string) ($settings['glossary_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="kb-setting-field kb-setting-field--textarea">
                            <span>Glossar-Einleitung</span>
                            <textarea name="glossary_intro" rows="4"><?php echo htmlspecialchars((string) ($settings['glossary_intro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </section>
                </div>
            <?php elseif (($settingsSection ?? 'general') === 'design'): ?>
                <h3>Design-Einstellungen</h3>
                <div class="alert alert-info">Diese Werte werden als CSS-Variablen im Frontend ausgegeben und von Tooltip sowie `cms-phinit` direkt verwendet.</div>

                <div class="kb-settings-grid kb-settings-grid--cards kb-settings-grid--design">
                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Layout</h3>
                        <p class="kb-settings-group__intro">Grundmaße für Content, Sidebar und Rundungen der öffentlichen Knowledgebase-Seiten.</p>
                        <div class="kb-settings-inline-grid">
                            <label class="kb-setting-field">
                                <span>Content-Breite</span>
                                <input type="number" name="content_max_width" min="720" max="1600" value="<?php echo (int) ($settings['content_max_width'] ?? 1200); ?>">
                            </label>
                            <label class="kb-setting-field">
                                <span>Sidebar-Breite</span>
                                <input type="number" name="sidebar_width" min="220" max="420" value="<?php echo (int) ($settings['sidebar_width'] ?? 300); ?>">
                            </label>
                            <label class="kb-setting-field">
                                <span>Border-Radius</span>
                                <input type="number" name="design_border_radius" min="0" max="32" value="<?php echo (int) ($settings['design_border_radius'] ?? 14); ?>">
                            </label>
                        </div>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Knowledgebase-Farben</h3>
                        <p class="kb-settings-group__intro">Akzent-, Flächen- und Rahmenfarben für Archive, Karten und Call-to-Actions.</p>
                        <div class="kb-color-grid">
                            <label class="kb-setting-field">
                                <span>Akzent</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="design_accent_color" value="<?php echo htmlspecialchars((string) ($settings['design_accent_color'] ?? '#0D9488'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="design_accent_color_text" value="<?php echo htmlspecialchars((string) ($settings['design_accent_color'] ?? '#0D9488'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                            <label class="kb-setting-field">
                                <span>Akzent Hover</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="design_accent_hover_color" value="<?php echo htmlspecialchars((string) ($settings['design_accent_hover_color'] ?? '#0F766E'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="design_accent_hover_color_text" value="<?php echo htmlspecialchars((string) ($settings['design_accent_hover_color'] ?? '#0F766E'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                            <label class="kb-setting-field">
                                <span>Fläche</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="design_surface_color" value="<?php echo htmlspecialchars((string) ($settings['design_surface_color'] ?? '#FFFFFF'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="design_surface_color_text" value="<?php echo htmlspecialchars((string) ($settings['design_surface_color'] ?? '#FFFFFF'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                            <label class="kb-setting-field">
                                <span>Rahmen</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="design_border_color" value="<?php echo htmlspecialchars((string) ($settings['design_border_color'] ?? '#DBE1EA'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="design_border_color_text" value="<?php echo htmlspecialchars((string) ($settings['design_border_color'] ?? '#DBE1EA'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                        </div>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Tooltip-Farben</h3>
                        <p class="kb-settings-group__intro">Kontrast und Lesbarkeit der Hover- und Fokus-Tooltips für automatische Begriffslinks.</p>
                        <div class="kb-color-grid">
                            <label class="kb-setting-field">
                                <span>Hintergrund</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="tooltip_background_color" value="<?php echo htmlspecialchars((string) ($settings['tooltip_background_color'] ?? '#111827'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="tooltip_background_color_text" value="<?php echo htmlspecialchars((string) ($settings['tooltip_background_color'] ?? '#111827'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                            <label class="kb-setting-field">
                                <span>Text</span>
                                <div class="kb-color-field" data-color-sync>
                                    <input type="color" name="tooltip_text_color" value="<?php echo htmlspecialchars((string) ($settings['tooltip_text_color'] ?? '#F8FAFC'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="tooltip_text_color_text" value="<?php echo htmlspecialchars((string) ($settings['tooltip_text_color'] ?? '#F8FAFC'), ENT_QUOTES, 'UTF-8'); ?>" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                                </div>
                            </label>
                        </div>
                    </section>

                    <?php
                    $kbDesignChoices = \CmsKnowledgebase\Support\Defaults::designChoices();
                    $kbRenderSelect = static function (string $name, string $label) use ($kbDesignChoices, $settings): void {
                        $current = \CmsKnowledgebase\Support\Defaults::designChoice($settings, $name);
                        echo '<label class="kb-setting-field"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
                        foreach ($kbDesignChoices[$name] ?? [] as $value => $optionLabel) {
                            echo '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . ((string) $value === $current ? ' selected' : '') . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                        }
                        echo '</select></label>';
                    };
                    $kbRenderColor = static function (string $name, string $label, string $fallback) use ($settings): void {
                        $value = htmlspecialchars(strtoupper((string) ($settings[$name] ?? $fallback)), ENT_QUOTES, 'UTF-8');
                        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                        echo '<label class="kb-setting-field"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><div class="kb-color-field" data-color-sync>'
                            . '<input type="color" name="' . $safeName . '" value="' . $value . '">'
                            . '<input type="text" name="' . $safeName . '_text" value="' . $value . '" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">'
                            . '</div></label>';
                    };
                    ?>

                    <section class="kb-settings-group kb-settings-group--card">
                        <h3>Übersichtsseite</h3>
                        <p class="kb-settings-group__intro">Kopfbereich, Raster und Karten der Übersicht unter <code>/kb</code> (Akzentleiste und Textkürzung gelten auch für <code>/glossar</code>).</p>
                        <div class="kb-settings-choice-grid">
                            <?php $kbRenderSelect('archive_hero_style', 'Kopfbereich'); ?>
                            <?php $kbRenderSelect('archive_columns', 'Spalten (Desktop)'); ?>
                            <?php $kbRenderSelect('archive_card_style', 'Karten-Stil'); ?>
                            <label class="kb-setting-field">
                                <span>Kurztext-Zeilen (0 = ungekürzt)</span>
                                <input type="number" name="archive_excerpt_lines" min="0" max="8" value="<?php echo (int) ($settings['archive_excerpt_lines'] ?? 3); ?>">
                            </label>
                            <?php $kbRenderColor('archive_card_bg_color', 'Karten-Hintergrund', '#FFFFFF'); ?>
                        </div>
                        <label class="kb-setting-toggle"><input type="checkbox" name="archive_card_accent_bar" value="1" <?php echo ($settings['archive_card_accent_bar'] ?? '1') === '1' ? 'checked' : ''; ?>> <span>Akzentleiste links an Karten anzeigen</span></label>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card kb-settings-group--span-2">
                        <h3>Detailseite</h3>
                        <p class="kb-settings-group__intro">Kopfbereich, Inhaltsbereiche und Sidebar der Eintragsseiten unter <code>/kb/{slug}</code>.</p>
                        <div class="kb-settings-choice-grid kb-settings-choice-grid--wide">
                            <?php $kbRenderSelect('single_hero_style', 'Kopfbereich'); ?>
                            <?php $kbRenderSelect('single_section_style', 'Darstellung der Inhaltsbereiche'); ?>
                            <?php $kbRenderColor('single_section_bg_color', 'Hintergrund Inhaltsbereiche', '#FFFFFF'); ?>
                            <?php $kbRenderSelect('single_sidebar_style', 'Sidebar-Stil'); ?>
                            <?php $kbRenderSelect('single_sidebar_position', 'Sidebar-Position'); ?>
                            <?php $kbRenderColor('single_sidebar_bg_color', 'Sidebar-Hintergrund (Karten-Stile)', '#F8FAFC'); ?>
                        </div>
                        <div class="kb-settings-toggles">
                            <label class="kb-setting-toggle"><input type="checkbox" name="single_section_tints" value="1" <?php echo ($settings['single_section_tints'] ?? '1') === '1' ? 'checked' : ''; ?>> <span>Inhaltsbereiche nach Typ einfärben (Vorteile grün, Hinweise bernstein, Fazit Akzent …)</span></label>
                            <label class="kb-setting-toggle"><input type="checkbox" name="single_section_icons" value="1" <?php echo ($settings['single_section_icons'] ?? '1') === '1' ? 'checked' : ''; ?>> <span>Icons vor den Bereichsüberschriften anzeigen</span></label>
                            <label class="kb-setting-toggle"><input type="checkbox" name="single_sidebar_sticky" value="1" <?php echo ($settings['single_sidebar_sticky'] ?? '1') === '1' ? 'checked' : ''; ?>> <span>Sidebar beim Scrollen mitführen (Desktop)</span></label>
                        </div>
                    </section>

                    <section class="kb-settings-group kb-settings-group--card kb-settings-group--full kb-settings-group--preview">
                        <h3>Live-Vorschau</h3>
                        <p class="kb-settings-group__intro">Zeigt direkt, wie Karten, CTA und Tooltip mit den aktuellen Werten zusammen wirken.</p>
                        <div class="kb-design-preview" style="--kb-preview-accent: <?php echo htmlspecialchars((string) ($designTokens['--kb-accent'] ?? '#0D9488'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-accent-hover: <?php echo htmlspecialchars((string) ($designTokens['--kb-accent-hover'] ?? '#0F766E'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-surface: <?php echo htmlspecialchars((string) ($designTokens['--kb-surface'] ?? '#FFFFFF'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-border: <?php echo htmlspecialchars((string) ($designTokens['--kb-border'] ?? '#DBE1EA'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-radius: <?php echo htmlspecialchars((string) ($designTokens['--kb-radius'] ?? '14px'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-tooltip-bg: <?php echo htmlspecialchars((string) ($designTokens['--kb-tooltip-bg'] ?? '#111827'), ENT_QUOTES, 'UTF-8'); ?>; --kb-preview-tooltip-text: <?php echo htmlspecialchars((string) ($designTokens['--kb-tooltip-text'] ?? '#F8FAFC'), ENT_QUOTES, 'UTF-8'); ?>;">
                            <div class="kb-design-preview__card">
                                <p class="kb-design-preview__eyebrow">Knowledgebase</p>
                                <h4>Beispiel-Eintrag</h4>
                                <p>So wirken Flächen, Rahmen und Akzentfarbe auf der öffentlichen Seite.</p>
                                <a href="#">Artikel lesen</a>
                            </div>
                            <div class="kb-design-preview__tooltip">
                                <strong>Tooltip-Vorschau</strong>
                                <span>Farben werden direkt auf die Tooltip-Box der automatischen Begriffslinks angewendet.</span>
                            </div>
                        </div>
                    </section>
                </div>
            <?php elseif (($settingsSection ?? 'general') === 'import'): ?>
                <?php
                $packageCount = count($standardPackages ?? []);
                $starterEntryCount = array_sum(array_map(static fn(array $package): int => (int) ($package['entry_count'] ?? 0), $standardPackages ?? []));
                ?>
                <h3>CSV-Import & Standardpakete</h3>
                <div class="alert alert-info">Hier liegen alle automatisch erkannten <code>*_Glossar.csv</code>-Quellen des Plugins. Imports synchronisieren bestehende Einträge anhand des Slugs und ergänzen neue Begriffe direkt.</div>

                <?php if (!empty($csvFilenameWarnings) && is_array($csvFilenameWarnings)): ?>
                    <div class="alert alert-error">
                        <strong>Hinweis zu Glossar-Dateinamen:</strong>
                        <ul>
                            <?php foreach ($csvFilenameWarnings as $warning): ?>
                                <li><?php echo htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="dashboard-grid kb-admin-dashboard-grid kb-admin-dashboard-grid--compact">
                    <div class="stat-card"><div class="stat-icon"></div><div class="stat-number"><?php echo number_format($packageCount); ?></div><div class="stat-label">Erkannte CSV-Pakete</div></div>
                    <div class="stat-card"><div class="stat-icon"></div><div class="stat-number"><?php echo number_format($starterEntryCount); ?></div><div class="stat-label">Einträge aus CSVs</div></div>
                    <div class="stat-card"><div class="stat-icon"></div><div class="stat-number"><?php echo number_format((int) ($systemInfo['csv_last_import_count'] ?? 0)); ?></div><div class="stat-label">Zuletzt importiert</div></div>
                </div>

                <section class="kb-settings-group kb-settings-group--card kb-settings-group--full">
                    <div class="kb-panel-header">
                        <div>
                            <h3>Standardpakete aus CSV</h3>
                            <p>Neue Glossar-Dateien werden automatisch erkannt, solange sie sauber auf <code>_Glossar.csv</code> enden. Unterstriche im Präfix werden im Kategorienamen als <code>&amp;</code> dargestellt.</p>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="create_all_standard_packages">
                            <input type="hidden" name="redirect_page" value="knowledgebase-settings-import">
                            <input type="hidden" name="redirect_section" value="import">
                            <button type="submit" class="btn btn-primary btn-sm">Alle CSV-Pakete importieren / synchronisieren</button>
                        </form>
                    </div>

                    <div class="kb-package-grid">
                        <?php foreach ($standardPackages as $package): ?>
                            <article class="kb-package-card" style="--kb-package-accent: <?php echo htmlspecialchars((string) ($package['accent'] ?? '#0d9488'), ENT_QUOTES, 'UTF-8'); ?>;">
                                <div class="kb-package-card__head">
                                    <div>
                                        <p class="kb-package-card__eyebrow">Bereich</p>
                                        <h4><?php echo htmlspecialchars((string) ($package['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h4>
                                    </div>
                                    <span class="kb-package-card__count"><?php echo (int) ($package['entry_count'] ?? 0); ?>+</span>
                                </div>

                                <p class="kb-package-card__text"><?php echo htmlspecialchars((string) ($package['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($package['file_name'])): ?>
                                    <p class="kb-package-card__text"><strong>CSV:</strong> <code><?php echo htmlspecialchars((string) $package['file_name'], ENT_QUOTES, 'UTF-8'); ?></code></p>
                                <?php endif; ?>

                                <?php if (!empty($package['sample_terms']) && is_array($package['sample_terms'])): ?>
                                    <div class="kb-meta-pills kb-meta-pills--wrap">
                                        <?php foreach ($package['sample_terms'] as $term): ?>
                                            <span class="kb-meta-pill kb-meta-pill--soft"><?php echo htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" class="kb-package-card__action">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="create_standard_package">
                                    <input type="hidden" name="package_key" value="<?php echo htmlspecialchars((string) ($package['key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="redirect_page" value="knowledgebase-settings-import">
                                    <input type="hidden" name="redirect_section" value="import">
                                    <button type="submit" class="btn btn-secondary btn-sm">CSV-Paket importieren / synchronisieren</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="kb-settings-group kb-settings-group--card kb-settings-group--full">
                    <div class="kb-panel-header">
                        <div>
                            <h3>Beispiel-CSV mit allen Feldern</h3>
                            <p><code>csv_kb/<?php echo htmlspecialchars((string) ($csvTemplateFileName ?? ''), ENT_QUOTES, 'UTF-8'); ?></code> enthält alle Felder aus „Eintrag anlegen“ mit drei Beispieleinträgen. Zum Import kopieren, befüllen und als <code>Kategorie_Glossar.csv</code> in <code>csv_kb</code> ablegen – die Vorlage selbst wird nicht importiert.</p>
                        </div>
                        <?php if (!empty($csvTemplateColumns)): ?>
                            <a href="/admin/plugins/knowledgebase-dashboard/knowledgebase-settings-import?kb_download=csv-template" class="btn btn-secondary btn-sm">Beispiel-CSV herunterladen</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($csvTemplateColumns) && is_array($csvTemplateColumns)): ?>
                        <div class="kb-meta-pills kb-meta-pills--wrap">
                            <?php foreach ($csvTemplateColumns as $templateColumn): ?>
                                <span class="kb-meta-pill kb-meta-pill--soft"><?php echo htmlspecialchars((string) $templateColumn, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-error">Die Beispiel-CSV fehlt im Plugin-Ordner <code>csv_kb</code>.</div>
                    <?php endif; ?>

                    <ul class="kb-system-list">
                        <li><strong>Pflicht</strong> ist nur „Titel“. Alle anderen Spalten sind optional, die Reihenfolge ist egal. Trennzeichen <code>;</code>, <code>,</code> oder Tab, Kodierung UTF-8.</li>
                        <li><strong>Leere Zellen:</strong> Neue Einträge erhalten den Standardwert (Fokusbegriff = Titel, Slug automatisch, Kategorie aus dem Dateinamen, aktiv, nur ganze Wörter). Bestehende Einträge behalten ihren Wert – auch im Editor ergänzte Inhalte.</li>
                        <li><strong>Ja/Nein-Spalten:</strong> <code>ja</code>/<code>nein</code>, <code>1</code>/<code>0</code> oder <code>x</code>.</li>
                        <li><strong>Inhaltsbereiche:</strong> Jede Zeile wird ein Absatz, Zeilen mit <code>- </code> eine Aufzählung, mit <code>1. </code> eine nummerierte Liste (Zeilenumbruch in Excel: Alt+Enter).</li>
                        <li><strong>Weiterführende Links:</strong> <code>Linktext: https://…</code>, mehrere per Zeilenumbruch oder <code>|</code> getrennt. Ohne Linktext wird er aus der Domain abgeleitet.</li>
                        <li><strong>Abgleich:</strong> Bestehende Einträge werden über den Slug erkannt (ohne Slug-Spalte über den Titel).</li>
                    </ul>
                </section>
            <?php else: ?>
                <h3>System-Informationen</h3>
                <div class="alert alert-info">Dieser Bereich bündelt technische Eckdaten, aktive Frontend-Werte und Hinweise zur aktuellen Knowledgebase-Konfiguration.</div>
                <div class="kb-settings-grid kb-settings-grid--cards kb-settings-grid--system">
                    <section class="kb-settings-group kb-settings-group--card kb-system-card">
                        <h4>Plugin</h4>
                        <p class="kb-settings-group__intro">Basisdaten zur installierten Version und zu den öffentlichen Knowledgebase-Routen.</p>
                        <ul class="kb-system-list">
                            <li><strong>Version:</strong> <?php echo htmlspecialchars((string) $systemInfo['plugin_version'], ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><strong>KB-Route:</strong> <code><?php echo htmlspecialchars((string) $systemInfo['public_route'], ENT_QUOTES, 'UTF-8'); ?></code></li>
                            <li><strong>Glossar:</strong> <code>/glossar</code></li>
                            <li><strong>Glossar-Sitemap:</strong> <a href="<?php echo htmlspecialchars((string) ($systemInfo['glossary_sitemap_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string) ($systemInfo['glossary_sitemap_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li><strong>Aktive Begriffe:</strong> <?php echo number_format((int) ($stats['active_entries'] ?? 0)); ?></li>
                        </ul>
                    </section>
                    <section class="kb-settings-group kb-settings-group--card kb-system-card">
                        <h4>Frontend</h4>
                        <p class="kb-settings-group__intro">Die aktuell gespeicherten Werte, die im öffentlichen Bereich als Layout- und Darstellungsregeln ankommen.</p>
                        <ul class="kb-system-list">
                            <li><strong>Content-Breite:</strong> <?php echo htmlspecialchars((string) $systemInfo['content_max_width'], ENT_QUOTES, 'UTF-8'); ?>px</li>
                            <li><strong>Sidebar:</strong> <?php echo htmlspecialchars((string) $systemInfo['sidebar_width'], ENT_QUOTES, 'UTF-8'); ?>px</li>
                            <li><strong>Output-Buffer:</strong> <?php echo htmlspecialchars((string) $systemInfo['output_buffer'], ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><strong>Auto-Linking:</strong> <?php echo htmlspecialchars((string) $systemInfo['autolink_enabled'], ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><strong>Akzentfarbe:</strong> <code><?php echo htmlspecialchars((string) ($settings['design_accent_color'] ?? '#0D9488'), ENT_QUOTES, 'UTF-8'); ?></code></li>
                        </ul>
                    </section>
                    <section class="kb-settings-group kb-settings-group--card kb-system-card">
                        <h4>CSV-Importstatus</h4>
                        <p class="kb-settings-group__intro">Zeigt den letzten synchronisierten CSV-Stand und die aktuelle Größe der Knowledgebase.</p>
                        <ul class="kb-system-list">
                            <li><strong>Letzter CSV-Import:</strong> <?php echo htmlspecialchars((string) ($systemInfo['csv_last_import_at'] ?? 'Noch kein CSV-Import'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><strong>Anzahl importierter Einträge:</strong> <?php echo number_format((int) ($systemInfo['csv_last_import_count'] ?? 0)); ?></li>
                            <li><strong>Anzahl aktueller KB-Einträge:</strong> <?php echo number_format((int) ($systemInfo['current_entry_count'] ?? 0)); ?></li>
                            <li><strong>Importquelle:</strong> <?php echo htmlspecialchars((string) ($systemInfo['csv_last_import_scope'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></li>
                        </ul>
                    </section>
                    <section class="kb-settings-group kb-settings-group--card kb-system-card">
                        <h4>Hinweise</h4>
                        <p class="kb-settings-group__intro">Kurz erklärt, wie sich Änderungen auf Admin, Vorschau und Public-Frontend auswirken.</p>
                        <ul class="kb-system-list">
                            <li>Design-Werte werden direkt als CSS-Variablen im Frontend ausgegeben.</li>
                            <li>Knowledgebase und Glossar können eigene Titel und Einleitungen nutzen.</li>
                            <li>Nach dem Speichern wird der Tab neu geladen und zeigt die aktuellen Werte.</li>
                            <li>CSV-Importe synchronisieren bestehende Einträge jetzt immer erneut statt nur Platzhalter zu ersetzen.</li>
                        </ul>
                    </section>
                    <section class="kb-settings-group kb-settings-group--card kb-system-card">
                        <h4>Hardreset</h4>
                        <p class="kb-settings-group__intro">Entfernt wirklich alle vorhandenen KB-Einträge. Anschließend kannst du die CSV-Pakete sauber neu importieren.</p>
                        <ul class="kb-system-list">
                            <li><strong>Achtung:</strong> Dieser Schritt löscht alle bestehenden Knowledgebase-Einträge unwiderruflich.</li>
                            <li>Danach empfiehlt sich direkt der CSV-Import über den Einstellungen-Tab „Import“.</li>
                        </ul>
                        <div class="kb-form-actions kb-form-actions--stack">
                            <form method="post" data-cms-confirm="Wirklich alle Knowledgebase-Einträge löschen? Dieser Hardreset kann nicht rückgängig gemacht werden.">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="hard_reset_entries">
                                <input type="hidden" name="redirect_page" value="knowledgebase-settings-system">
                                <input type="hidden" name="redirect_section" value="system">
                                <button type="submit" class="btn btn-danger">Alle Einträge löschen (Hardreset)</button>
                            </form>

                            <form method="post" data-cms-confirm="Wirklich alle Knowledgebase-Einträge löschen und direkt alle CSV-Pakete neu importieren? Dieser Vorgang kann nicht rückgängig gemacht werden.">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="hard_reset_and_import_all">
                                <input type="hidden" name="redirect_page" value="knowledgebase-settings-system">
                                <input type="hidden" name="redirect_section" value="system">
                                <button type="submit" class="btn btn-primary">Hardreset + alle CSVs neu importieren</button>
                            </form>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if (($settingsSection ?? 'general') !== 'system'): ?>
                <div class="kb-form-actions kb-settings-group--full">
                    <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

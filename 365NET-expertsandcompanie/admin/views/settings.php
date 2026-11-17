<?php
/**
 * Unterseite „Einstellungen“: Header → Meldung → Formular (Navigation, Texte, Layout, Rundungen, Farben).
 *
 * @var array<string, string> $settings
 * @var list<string> $rejected
 * @var array{type:string,text:string}|null $notice
 * @var string $csrf
 * @var string $action
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$A = CMS_365NET_Experts_And_Companie_Admin::class;
$s = static fn (string $key): string => trim((string) ($settings[$key] ?? ''));
$on = static fn (string $key): bool => ($settings[$key] ?? '1') === '1';
$field = static function (string $key, string $label, string $type = 'text', string $attrs = '') use ($A, $s, $rejected): string {
    $html = $A::textField($key, $label, $s($key), $type, $attrs . (in_array($key, $rejected, true) ? ' aria-invalid="true"' : ''));
    if (in_array($key, $rejected, true)) {
        $html = str_replace('class="form-control"', 'class="form-control is-invalid"', $html);
        $html = str_replace('</div>', '<div class="invalid-feedback">Ungültiger Wert – nicht übernommen.</div></div>', $html);
    }

    return $html;
};
$buttons = static function (string $prefix, string $label) use ($field): string {
    $html = '';
    for ($i = 1; $i <= 3; $i++) {
        $html .= $field($prefix . '_header_btn_' . $i . '_text', $label . '-Button ' . $i . ': Text');
        $html .= $field($prefix . '_header_btn_' . $i . '_url', $label . '-Button ' . $i . ': Ziel (/pfad oder https://…)');
    }

    return $html;
};
$lengthAttrs = 'pattern="(0|[0-9]{1,4}(\.[0-9]{1,3})?(px|rem|em|%|vw|vh|ch))" placeholder="z. B. 24px"';
?>
<div class="admin-page-header">
    <div>
        <h2>Einstellungen</h2>
        <p>Navigation, Texte, Abstände, Rundungen und Farben der Publicseiten /experts, /companies und /experts-companie.</p>
    </div>
    <div class="header-actions">
        <button type="submit" form="excomp-settings-form" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button>
    </div>
</div>

<?php include __DIR__ . '/_notice.php'; ?>

<form method="post" action="<?= $e($action) ?>" class="cms-excomp-form" id="excomp-settings-form">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="excomp_action" value="save_settings">

    <section class="admin-card cms-excomp-card">
        <h3>Navigation</h3>
        <div class="cms-excomp-form-checks">
            <?= $A::checkField('show_nav_link', 'Link im Hauptmenü der Website anzeigen (Ziel /experts)', ($settings['show_nav_link'] ?? '0') === '1') ?>
        </div>
        <div class="cms-excomp-form-grid">
            <?= $field('nav_label', 'Beschriftung im Hauptmenü', 'text', 'maxlength="80"') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Texte – Experts-Verzeichnis</h3>
        <div class="cms-excomp-form-checks">
            <?= $A::checkField('experts_header_text_enabled', 'Headertexte anzeigen', $on('experts_header_text_enabled')) ?>
            <?= $A::checkField('experts_header_kicker_enabled', 'Kicker', $on('experts_header_kicker_enabled')) ?>
            <?= $A::checkField('experts_header_title_enabled', 'Titel', $on('experts_header_title_enabled')) ?>
            <?= $A::checkField('experts_header_description_enabled', 'Beschreibung', $on('experts_header_description_enabled')) ?>
            <?= $A::checkField('experts_search_placeholder_enabled', 'Such-Platzhalter', $on('experts_search_placeholder_enabled')) ?>
        </div>
        <div class="cms-excomp-form-grid">
            <?= $field('experts_archive_kicker', 'Kicker') ?>
            <?= $field('experts_archive_title', 'Titel') ?>
            <?= $field('experts_search_placeholder', 'Such-Platzhalter') ?>
            <?= $A::textAreaField('experts_archive_description', 'Beschreibung', $s('experts_archive_description'), 2) ?>
            <?= $buttons('experts', 'Header') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Texte – Companies-Verzeichnis</h3>
        <div class="cms-excomp-form-checks">
            <?= $A::checkField('companies_header_text_enabled', 'Headertexte anzeigen', $on('companies_header_text_enabled')) ?>
            <?= $A::checkField('companies_header_kicker_enabled', 'Kicker', $on('companies_header_kicker_enabled')) ?>
            <?= $A::checkField('companies_header_title_enabled', 'Titel', $on('companies_header_title_enabled')) ?>
            <?= $A::checkField('companies_header_description_enabled', 'Beschreibung', $on('companies_header_description_enabled')) ?>
            <?= $A::checkField('companies_search_placeholder_enabled', 'Such-Platzhalter', $on('companies_search_placeholder_enabled')) ?>
        </div>
        <div class="cms-excomp-form-grid">
            <?= $field('companies_archive_kicker', 'Kicker') ?>
            <?= $field('companies_archive_title', 'Titel') ?>
            <?= $field('companies_search_placeholder', 'Such-Platzhalter') ?>
            <?= $A::textAreaField('companies_archive_description', 'Beschreibung', $s('companies_archive_description'), 2) ?>
            <?= $buttons('companies', 'Header') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Texte – gemeinsamer Hub</h3>
        <div class="cms-excomp-form-checks">
            <?= $A::checkField('hub_header_text_enabled', 'Headertexte anzeigen', $on('hub_header_text_enabled')) ?>
        </div>
        <div class="cms-excomp-form-grid">
            <?= $field('hub_archive_kicker', 'Kicker') ?>
            <?= $field('hub_archive_title', 'Titel') ?>
            <?= $A::textAreaField('hub_archive_description', 'Beschreibung', $s('hub_archive_description'), 2) ?>
            <?= $buttons('hub', 'Header') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Texte – Schaltflächen</h3>
        <div class="cms-excomp-form-grid">
            <?= $field('search_button_label', 'Such-Button') ?>
            <?= $field('reset_button_label', 'Zurücksetzen-Button') ?>
            <?= $field('website_button_label', 'Website-Button (Detailseite)') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Layout &amp; Abstände</h3>
        <p class="cms-excomp-muted">Erlaubt sind Längen mit px, rem, em, %, vw, vh oder ch (z. B. <code>24px</code>, <code>1.5rem</code>).</p>
        <div class="cms-excomp-form-grid">
            <?= $field('layout_content_max_width', 'Content-Breite (leer = Breite des Themes)', 'text', str_replace('z. B. 24px', 'leer = Theme', $lengthAttrs)) ?>
            <?= $field('layout_content_max_width_fallback', 'Fallback-Breite (wenn das Theme keine Breite liefert)', 'text', str_replace('z. B. 24px', 'z. B. 1160px', $lengthAttrs)) ?>
            <?= $field('layout_page_padding_top', 'Abstand Header → Content', 'text', $lengthAttrs) ?>
            <?= $field('layout_page_padding_bottom', 'Abstand Content → Footer', 'text', $lengthAttrs) ?>
            <?= $field('layout_grid_gap_x', 'Grid-Abstand horizontal', 'text', $lengthAttrs) ?>
            <?= $field('layout_grid_gap_y', 'Grid-Abstand vertikal', 'text', $lengthAttrs) ?>
            <?= $field('layout_section_gap', 'Abschnitts-Abstand', 'text', $lengthAttrs) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Rundungen</h3>
        <p class="cms-excomp-muted">Empfohlen: 2px, höchstens 4px.</p>
        <div class="cms-excomp-form-grid">
            <?= $field('style_radius_card', 'Karten', 'text', $lengthAttrs) ?>
            <?= $field('style_radius_button', 'Buttons', 'text', $lengthAttrs) ?>
            <?= $field('style_radius_surface', 'Container/Flächen', 'text', $lengthAttrs) ?>
            <?= $field('style_radius_hero', 'Header', 'text', $lengthAttrs) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Farben</h3>
        <p class="cms-excomp-muted">Hex-Werte (<code>#1d4ed8</code>) oder <code>rgb()</code>/<code>hsl()</code>.</p>
        <div class="cms-excomp-form-grid">
            <?php foreach ([
                'color_bg' => 'Hintergrund', 'color_text' => 'Text', 'color_primary' => 'Primärfarbe',
                'color_hero_start' => 'Header-Verlauf Start', 'color_hero_end' => 'Header-Verlauf Ende',
                'color_expert_accent' => 'Akzent Experts', 'color_company_accent' => 'Akzent Companies',
                'color_card_bg' => 'Karten-Hintergrund', 'color_border' => 'Rahmen',
            ] as $key => $label): ?>
                <?= $field($key, $label, 'text', 'maxlength="64" placeholder="#rrggbb"') ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="cms-excomp-form-actions">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button>
    </div>
</form>

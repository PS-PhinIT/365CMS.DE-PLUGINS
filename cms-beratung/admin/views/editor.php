<?php
/**
 * CMS Beratung – Landingpage-Editor (Formular + Builder-Mounts für assets/js/admin-builder.js).
 *
 * @var array<string,mixed> $page
 * @var string $formAction
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$statuses = CMS_Beratung_Settings::statuses();
$templates = CMS_Beratung_Settings::templates();
$presets = CMS_Beratung_Storage::instance()->all_presets();
$heroJson = json_encode($page['hero'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
$heroSettings = is_array($page['hero'] ?? null) ? $page['hero'] : [];
$contactJson = json_encode($page['contact'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
$editableSections = array_values(array_filter(is_array($page['sections'] ?? null) ? $page['sections'] : [], static fn($section): bool => is_array($section) && ($section['type'] ?? '') !== 'faq'));
$sectionsJson = json_encode($editableSections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$designJson = json_encode($page['design'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
$admin::render_media_config();
$admin::render_expert_options_config();
echo '<form method="post" action="' . $admin::esc($formAction) . '" class="beratung-editor"><input type="hidden" name="csrf_token" value="' . $admin::esc($admin::nonce('beratung_save_landingpage')) . '"><input type="hidden" name="beratung_admin_action" value="save_landingpage"><input type="hidden" name="id" value="' . (int) ($page['id'] ?? 0) . '">';
echo '<section class="beratung-card beratung-general-settings" data-always-open="1"><h2>Allgemeine Einstellungen</h2>';
$admin::field('Interner Titel', 'internal_title', (string) ($page['internal_title'] ?? ''));
$admin::field('Öffentlicher Titel / Website-Titel', 'public_title', (string) ($page['public_title'] ?? ''), 'text', 'Dieser Titel wird für die Public-Landingpage verwendet: Website-Titel, Breadcrumb, Header-Fallback und öffentliche Listenansicht.');
$admin::field('URL Slug', 'slug', (string) ($page['slug'] ?? ''));
$admin::field('Meta Title', 'meta_title', (string) ($page['meta_title'] ?? ''), 'text', 'Optionaler SEO-Titel. Leer lassen, wenn der öffentliche Website-Titel auch als Meta-/Browser-Titel ausgegeben werden soll.');
$admin::textarea('Meta Description', 'meta_description', (string) ($page['meta_description'] ?? ''), 3);
$admin::field('Fokus Keyword', 'focus_keyword', (string) ($page['focus_keyword'] ?? ''));
$admin::select('Template Auswahl', 'template', (string) ($page['template'] ?? 'standard'), $templates);
$admin::field('Maximale Inhaltsbreite', 'max_content_width', (string) ($page['max_content_width'] ?? '1160'), 'number');
$admin::field('Canonical URL', 'canonical_url', (string) ($page['canonical_url'] ?? ''));
$admin::field('Eigene CSS Klasse', 'custom_css_class', (string) ($page['custom_css_class'] ?? ''));
if ((int) ($page['id'] ?? 0) > 0) {
    $publicUrl = ((string) ($page['status'] ?? 'draft')) === 'published' ? $admin::public_landingpage_url((string) ($page['slug'] ?? '')) : '';
    $standaloneUrl = ((string) ($page['status'] ?? 'draft')) === 'published' && !empty($heroSettings['standalone_header_enabled']) && !empty($heroSettings['standalone_header_slug']) ? $admin::public_landingpage_url((string) $heroSettings['standalone_header_slug']) : '';
    echo '<p class="beratung-general-settings__links"><a class="beratung-link" target="_blank" rel="noopener noreferrer" href="' . $admin::esc($admin::preview_url((int) $page['id'])) . '">Entwurfs-Vorschau öffnen</a>';
    if ($publicUrl !== '') {
        echo '<a class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer" href="' . $admin::esc($publicUrl) . '">Publicseite mit Theme öffnen</a>';
    }
    if ($standaloneUrl !== '') {
        echo '<a class="beratung-link" target="_blank" rel="noopener noreferrer" href="' . $admin::esc($standaloneUrl) . '">Standalone Publicsite öffnen</a>';
    }
    echo '</p>';
}
echo '</section><section class="beratung-card beratung-publicsite-card"><h2>Publicsite Anzeige</h2><p>Hier steuerst du pro Landingpage die normale Publicsite mit Theme-Rahmen und optional eine zusätzliche Standalone-Publicsite ohne Theme Header und Footer.</p><div class="beratung-grid-2"><div><h2>Aktivierung</h2>';
$admin::select('Publicsite Status', 'status', (string) ($page['status'] ?? 'draft'), $statuses);
echo '<p class="description">Nur veröffentlichte Publicsites sind unter <code>/beratung/{slug}</code> erreichbar. Entwürfe bleiben im Admin und in der Vorschau.</p></div><div><h2>Standard Publicsite</h2><input type="hidden" name="show_header" value="1"><input type="hidden" name="show_footer" value="1">';
$standaloneSlug = (string) ($heroSettings['standalone_header_slug'] ?? '');
echo '<p class="description">Der normale Publicsite-Slug <code>/beratung/' . $admin::esc((string) ($page['slug'] ?? 'slug')) . '</code> rendert immer mit Theme Header und Theme Footer. Die zusätzliche Standalone-Variante nutzt einen eigenen Slug, z. B. <code>/beratung/' . $admin::esc($standaloneSlug !== '' ? $standaloneSlug : 'eigener-standalone-slug') . '</code>.</p></div></div><div class="beratung-publicsite-standalone"><h2>Zusätzliche Standalone-Publicsite</h2><p>Diese zweite Publicsite nutzt keinen Theme Header und keinen Theme Footer, lädt aber weiterhin die Standard-Assets des aktiven Themes und alle Plugin-Styles. Der eigene Slug ist Pflicht und muss sich vom normalen Publicsite-Slug unterscheiden.</p>';
$admin::checkbox('Zusätzliche Standalone-Publicsite aktivieren', 'standalone_header_enabled', !empty($heroSettings['standalone_header_enabled']));
$admin::field('Eigener Standalone Slug', 'standalone_header_slug', $standaloneSlug, 'text', 'Pflicht bei aktiver Standalone-Publicsite. Beispiel: microsoft-365-beratung-clean. Erreichbar unter /beratung/{standalone-slug}.');
echo '<div class="beratung-grid-2"><label class="beratung-field"><span>Blog Logo</span><input data-media-field name="standalone_header_blog_logo_url" value="' . $admin::esc((string) ($heroSettings['standalone_header_blog_logo_url'] ?? '')) . '" placeholder="/uploads/beratung/... oder https://..."></label><label class="beratung-field"><span>Partner Logo</span><input data-media-field name="standalone_header_partner_logo_url" value="' . $admin::esc((string) ($heroSettings['standalone_header_partner_logo_url'] ?? '')) . '" placeholder="/uploads/beratung/... oder https://..."></label></div>';
$admin::field('Header Titel', 'standalone_header_title', (string) ($heroSettings['standalone_header_title'] ?? ''), 'text', 'Wird nur angezeigt, wenn ein eigener Titel hinterlegt ist.');
$admin::textarea('Header Untertitel', 'standalone_header_subtitle', (string) ($heroSettings['standalone_header_subtitle'] ?? ''), 2);
$admin::checkbox('Menüband im eigenen Header anzeigen', 'standalone_header_menu_enabled', array_key_exists('standalone_header_menu_enabled', $heroSettings) ? !empty($heroSettings['standalone_header_menu_enabled']) : true);
$standaloneMenuItems = is_array($heroSettings['standalone_header_menu_items'] ?? null) ? array_values($heroSettings['standalone_header_menu_items']) : [];
echo '<div class="beratung-publicsite-menu-items"><h3>Menüband Links</h3>';
for ($i = 1; $i <= 4; $i++) {
    $item = is_array($standaloneMenuItems[$i - 1] ?? null) ? $standaloneMenuItems[$i - 1] : [];
    echo '<div class="beratung-publicsite-menu-row"><label class="beratung-field"><span>Menü Label ' . $i . '</span><input name="standalone_header_menu_label_' . $i . '" value="' . $admin::esc((string) ($item['label'] ?? '')) . '" placeholder="z. B. Leistungen"></label><label class="beratung-field"><span>Menü Ziel ' . $i . '</span><input name="standalone_header_menu_target_' . $i . '" value="' . $admin::esc((string) ($item['target'] ?? '')) . '" placeholder="#leistungen oder https://..."></label></div>';
}
echo '</div></div><div class="beratung-grid-2"><div><h2>Navigation</h2>';
$admin::checkbox('Breadcrumb anzeigen', 'show_breadcrumb', !empty($page['show_breadcrumb']));
$admin::checkbox('Inhaltsverzeichnis anzeigen', 'show_toc', !empty($page['show_toc']));
echo '</div><div><h2>Suchmaschinen</h2>';
$admin::checkbox('Noindex aktivieren', 'noindex', !empty($page['noindex']));
$admin::checkbox('Nofollow aktivieren', 'nofollow', !empty($page['nofollow']));
echo '</div></div></section><section class="beratung-card"><h2>Design und System Optionen</h2>';
foreach (['custom_design_enabled' => 'Individuelles Design aktivieren', 'use_global_settings' => 'Globale Plugin Einstellungen verwenden', 'use_global_design' => 'Globales Design Modul verwenden'] as $name => $label) {
    $admin::checkbox($label, $name, !empty($page[$name]));
}
echo '</section>';
echo '<section class="beratung-card"><h2>Hero / Content Header</h2><p>Bild links oder rechts, nahtloser Bildrand, Badge, Titel, Text, bis zu 3 Buttons und Trust-Hinweis.</p><div id="beratung-hero-builder" data-target="hero_json"></div><textarea id="hero_json" name="hero_json" rows="12" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . $admin::esc($heroJson) . '</textarea></section>';
echo '<section class="beratung-card"><h2>Anker Navigation / Navigation</h2><p>Die Navigation zwischen Content Header und den Inhaltsbereichen ist hier direkt einstellbar.</p>';
$admin::checkbox('Anker Navigation unter dem Content Header anzeigen', 'show_anchor_nav', !empty($page['show_anchor_nav']));
echo '<div id="beratung-anchor-nav-builder" data-target="hero_json"></div></section>';
echo '<section class="beratung-card"><h2>Kontaktbereich</h2><p>Der Anfragebereich am Ende der Landingpage kann hier direkt gepflegt werden: Texte, Bild, Farben, Buttons, Datenschutz und Captcha.</p>';
$admin::checkbox('Kontaktbereich anzeigen', 'contact_enabled', ($page['contact']['enabled'] ?? true) !== false);
echo '<div id="beratung-contact-builder" data-target="contact_json"></div><textarea id="contact_json" name="contact_json" rows="10" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . $admin::esc($contactJson) . '</textarea></section>';
echo '<section class="beratung-card beratung-root-builder-intro"><h2>Landingpage Bereiche nach Content Header</h2><p>Jeder Bereich nach dem Content Header erscheint darunter als eigener, auf-/zuklappbarer Root-Abschnitt. Es gibt keinen großen Sammelbereich mehr: Partnerband, Belegbare Grundlagen, Terminbuchung, Zusammenarbeit, Cards, CTA, Trenner und weitere Inhaltsbereiche stehen jeweils eigenständig auf Root-Ebene.</p></section><div id="beratung-builder" data-target="sections_json"></div><textarea id="sections_json" name="sections_json" rows="16" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . $admin::esc($sectionsJson) . '</textarea>';
echo '<section class="beratung-card"><h2>Design Preset und individuelles Design</h2><p>Preset wählen und Farben direkt über die Felder anpassen.</p><label class="beratung-field"><span>Design Preset anwenden</span><select id="beratung-design-preset"><option value="">Bitte wählen</option>';
foreach ($presets as $preset) { echo '<option value="' . $admin::esc((string) ($preset['slug'] ?? '')) . '" data-design="' . $admin::esc((string) ($preset['design_json'] ?? '{}')) . '">' . $admin::esc((string) ($preset['name'] ?? 'Preset')) . '</option>'; }
echo '</select></label><div id="beratung-design-builder" data-target="design_json"></div><textarea id="design_json" name="design_json" rows="8" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . $admin::esc($designJson) . '</textarea></section>';
echo '<div class="beratung-form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Landingpage speichern</button> <a class="btn btn-outline-secondary" href="' . $admin::esc($admin::admin_url('cms-beratung-landingpages')) . '">Zur Übersicht</a></div></form>';

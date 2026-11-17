<?php
/**
 * Einstellungen: öffentliche Texte, Layout, Google-Suche und Backfill-Werkzeuge.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var array<string, string> $settings
 * @var array{api_key_env:bool,api_key_stored:bool,cse_id_env:bool,cse_id:string,crypto:bool} $google
 * @var bool $googleConfigured
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$this->pageHeader(
    'Einstellungen',
    'Texte, Layout, Farben und Abstände der öffentlichen Eventseiten sowie Werkzeuge für Beschreibungen und Speaker-Profile.',
    $this->button('Öffentlich prüfen', '/events', 'ti-world', 'btn-secondary', true)
);
$this->flash();
?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_SETTINGS)) ?>" class="cms365-form" novalidate>
    <?= $this->csrfField('365net_settings_form') ?>
    <input type="hidden" name="cms365_action" value="save">

    <section class="admin-card cms365-section"><h3>1. Navigation &amp; Archivtexte</h3><div class="cms365-form-grid">
        <?= $this->checkbox('show_nav_link', 'Im Hauptmenü anzeigen', ($settings['show_nav_link'] ?? '0') === '1') ?>
        <?= $this->checkbox('events_header_text_enabled', 'Event-Headertexte anzeigen', ($settings['events_header_text_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('events_header_kicker_enabled', 'Event-Kicker anzeigen', ($settings['events_header_kicker_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('events_header_title_enabled', 'Event-Titel anzeigen', ($settings['events_header_title_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('events_header_description_enabled', 'Event-Beschreibung anzeigen', ($settings['events_header_description_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('events_search_placeholder_enabled', 'Event-Such-Platzhalter anzeigen', ($settings['events_search_placeholder_enabled'] ?? '1') === '1') ?>
        <?= $this->field('nav_label', 'Navigationslabel', $settings['nav_label'] ?? 'Events') ?>
        <?= $this->field('archive_kicker', 'Event-Archiv Kicker', $settings['archive_kicker'] ?? '') ?>
        <?= $this->field('archive_title', 'Event-Archiv Titel', $settings['archive_title'] ?? '') ?>
        <?= $this->field('archive_description', 'Event-Archiv Beschreibung', $settings['archive_description'] ?? '') ?>
        <?= $this->field('archive_current_month_label', 'Label zukünftige Events', $settings['archive_current_month_label'] ?? '') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>2. Event-Header-Buttons (unten rechts)</h3><div class="cms365-form-grid">
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <?= $this->field('events_header_btn_' . $i . '_text', 'Button ' . $i . ' Text', $settings['events_header_btn_' . $i . '_text'] ?? '') ?>
            <?= $this->field('events_header_btn_' . $i . '_url', 'Button ' . $i . ' URL', $settings['events_header_btn_' . $i . '_url'] ?? '') ?>
        <?php endfor; ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>3. Suche, Buttons &amp; Leerzustände</h3><div class="cms365-form-grid">
        <?= $this->field('archive_search_placeholder', 'Suchfeld-Platzhalter', $settings['archive_search_placeholder'] ?? '') ?>
        <?= $this->field('archive_search_button', 'Suchbutton', $settings['archive_search_button'] ?? '') ?>
        <?= $this->field('archive_reset_label', 'Zurücksetzen-Text', $settings['archive_reset_label'] ?? '') ?>
        <?= $this->field('archive_past_button', 'Button vergangene Events', $settings['archive_past_button'] ?? '') ?>
        <?= $this->field('archive_current_button', 'Button zukünftige Events', $settings['archive_current_button'] ?? '') ?>
        <?= $this->field('archive_empty_current', 'Leertext zukünftige Events', $settings['archive_empty_current'] ?? '') ?>
        <?= $this->field('archive_empty_past', 'Leertext vergangene Events', $settings['archive_empty_past'] ?? '') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>4. Speaker- und Detailtexte</h3><div class="cms365-form-grid">
        <?= $this->checkbox('speakers_header_text_enabled', 'Speaker-Headertexte anzeigen', ($settings['speakers_header_text_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('speakers_header_kicker_enabled', 'Speaker-Kicker anzeigen', ($settings['speakers_header_kicker_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('speakers_header_title_enabled', 'Speaker-Titel anzeigen', ($settings['speakers_header_title_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('speakers_header_description_enabled', 'Speaker-Beschreibung anzeigen', ($settings['speakers_header_description_enabled'] ?? '1') === '1') ?>
        <?= $this->checkbox('speakers_search_placeholder_enabled', 'Speaker-Such-Platzhalter anzeigen', ($settings['speakers_search_placeholder_enabled'] ?? '1') === '1') ?>
        <?= $this->field('speaker_archive_kicker', 'Speaker-Kicker', $settings['speaker_archive_kicker'] ?? '') ?>
        <?= $this->field('speaker_archive_title', 'Speaker-Titel', $settings['speaker_archive_title'] ?? '') ?>
        <?= $this->field('speaker_archive_description', 'Speaker-Beschreibung', $settings['speaker_archive_description'] ?? '') ?>
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <?= $this->field('speakers_header_btn_' . $i . '_text', 'Speaker-Button ' . $i . ' Text', $settings['speakers_header_btn_' . $i . '_text'] ?? '') ?>
            <?= $this->field('speakers_header_btn_' . $i . '_url', 'Speaker-Button ' . $i . ' URL', $settings['speakers_header_btn_' . $i . '_url'] ?? '') ?>
        <?php endfor; ?>
        <?= $this->field('speaker_search_placeholder', 'Speaker-Suche Platzhalter', $settings['speaker_search_placeholder'] ?? '') ?>
        <?= $this->field('detail_back_events_label', 'Brotkrume Events', $settings['detail_back_events_label'] ?? '') ?>
        <?= $this->field('detail_speakers_heading', 'Detailseite: Überschrift Speaker', $settings['detail_speakers_heading'] ?? '') ?>
        <?= $this->field('detail_no_speakers_text', 'Detailseite: Leertext Speaker', $settings['detail_no_speakers_text'] ?? '') ?>
        <?= $this->field('detail_register_label', 'Registrierungsbutton', $settings['detail_register_label'] ?? '') ?>
        <?= $this->field('detail_website_label', 'Websitebutton', $settings['detail_website_label'] ?? '') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>5. Layout, Farben &amp; Abstände (öffentliche Seiten)</h3><p class="cms365-muted">Der öffentliche Bereich hat keinen eigenen Seitenhintergrund; diese Werte steuern Karten, Buttons, Rundungen und Innenabstände.</p><div class="cms365-form-grid">
        <?= $this->field('layout_primary_color', 'Primärfarbe', $settings['layout_primary_color'] ?? '#1d4ed8', false, 'color') ?>
        <?= $this->field('layout_accent_color', 'Akzentfarbe', $settings['layout_accent_color'] ?? '#f59e0b', false, 'color') ?>
        <?= $this->field('layout_event_card_top_border_color', 'Event-Karte oberer Rand', $settings['layout_event_card_top_border_color'] ?? ($settings['layout_accent_color'] ?? '#f59e0b'), false, 'color') ?>
        <?= $this->field('layout_speaker_card_top_border_color', 'Speaker-Karte oberer Rand', $settings['layout_speaker_card_top_border_color'] ?? '#8b5cf6', false, 'color') ?>
        <?= $this->field('layout_text_color', 'Textfarbe', $settings['layout_text_color'] ?? '#0f172a', false, 'color') ?>
        <?= $this->field('layout_card_background', 'Kartenhintergrund', $settings['layout_card_background'] ?? '#ffffff', false, 'color') ?>
        <?= $this->field('layout_card_border', 'Kartenrahmen', $settings['layout_card_border'] ?? '#e2e8f0', false, 'color') ?>
        <?= $this->field('layout_radius', 'Hero-/Box-Rundung px', $settings['layout_radius'] ?? '24', false, 'number') ?>
        <?= $this->field('layout_card_radius', 'Karten-Rundung px', $settings['layout_card_radius'] ?? '20', false, 'number') ?>
        <?= $this->field('layout_gap', 'Grid-Abstand horizontal px', $settings['layout_gap'] ?? '18', false, 'number') ?>
        <?= $this->field('layout_gap_y', 'Grid-Abstand vertikal px', $settings['layout_gap_y'] ?? ($settings['layout_gap'] ?? '18'), false, 'number') ?>
        <?= $this->field('layout_top_spacing', 'Abstand zum Header px', $settings['layout_top_spacing'] ?? '32', false, 'number') ?>
        <?= $this->field('layout_bottom_spacing', 'Abstand zum Footer px', $settings['layout_bottom_spacing'] ?? '56', false, 'number') ?>
        <?= $this->field('layout_container_width', 'Containerbreite px (0 = Breite des Themes)', $settings['layout_container_width'] ?? '0', false, 'number') ?>
        <?= $this->field('layout_container_width_fallback', 'Fallback-Breite px (wenn das Theme keine Breite liefert)', $settings['layout_container_width_fallback'] ?? '1160', false, 'number') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>6. Google-Suche für den Speaker-Backfill (optional)</h3>
        <p class="cms365-muted">Google Programmable Search (Custom Search JSON API). Umgebungsvariablen <code>CMS_365NETEVENTS_GOOGLE_API_KEY</code> / <code>CMS_365NETEVENTS_GOOGLE_CSE_ID</code> haben Vorrang. Der API-Key wird verschlüsselt gespeichert und nicht wieder angezeigt.</p>
        <?php if (!$google['crypto']): ?><div class="alert alert-warning" role="status">Die Verschlüsselung (CryptoService) ist nicht verfügbar – ein API-Key kann deshalb nur als Umgebungsvariable hinterlegt werden.</div><?php endif; ?>
        <div class="cms365-form-grid">
            <label class="form-group"><span>API-Key</span>
                <input class="form-control" type="password" name="google_api_key" value="" autocomplete="new-password" placeholder="<?= $this->e($google['api_key_env'] ? 'aus Umgebungsvariable' : ($google['api_key_stored'] ? 'gespeichert – leer lassen, um ihn zu behalten' : 'nicht gesetzt')) ?>">
            </label>
            <?php if ($google['api_key_stored']): ?><?= $this->checkbox('google_api_key_clear', 'Gespeicherten API-Key entfernen', false) ?><?php endif; ?>
            <label class="form-group"><span>Suchmaschinen-ID (cx)</span>
                <input class="form-control" type="text" name="google_cse_id" value="<?= $this->e($google['cse_id']) ?>" maxlength="200" placeholder="<?= $this->e($google['cse_id_env'] ? 'aus Umgebungsvariable' : '') ?>">
            </label>
        </div>
        <p class="cms365-muted">Status: <?= $googleConfigured ? 'konfiguriert' : 'nicht konfiguriert' ?>.</p>
    </section>

    <div class="admin-card cms365-actions-card">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Einstellungen speichern</button>
        <a class="btn btn-secondary" href="<?= $this->e(self::url(self::MENU_SLUG)) ?>">Abbrechen</a>
    </div>
</form>

<div class="cms365-tools">
    <form method="post" action="<?= $this->e(self::url(self::PAGE_SETTINGS)) ?>" class="admin-card cms365-section" data-cms-confirm="Beschreibungen aus den Seed-Daten in bestehende Events übernehmen? Vorhandene Beschreibungen dieser Events werden überschrieben.">
        <?= $this->csrfField('365net_settings_backfill') ?>
        <input type="hidden" name="cms365_action" value="backfill_descriptions">
        <h3>Seed-Beschreibungen einspielen</h3>
        <p class="cms365-muted">Übernimmt die mitgelieferten Beschreibungen anhand <code>NR/source_nr</code>, Titel, Datum und Ort in vorhandene Events (inkl. Kurzbeschreibung und SEO-Beschreibung).</p>
        <button type="submit" class="btn btn-secondary"><i class="ti ti-file-text" aria-hidden="true"></i> Beschreibungen einspielen</button>
    </form>
    <form method="post" action="<?= $this->e(self::url(self::PAGE_SETTINGS)) ?>" class="admin-card cms365-section" data-cms-confirm="Speaker-Profile über die Google-Suche ergänzen? Es werden bis zu 25 Speaker je Lauf abgefragt.">
        <?= $this->csrfField('365net_settings_speaker_backfill') ?>
        <input type="hidden" name="cms365_action" value="backfill_speakers">
        <h3>Speaker-Profile ergänzen</h3>
        <p class="cms365-muted">Sucht für veröffentlichte Speaker ohne Website oder LinkedIn-Profil öffentliche Treffer und ergänzt fehlende Links sowie eine Kurz-Bio. Je Lauf bis zu 25 Speaker; der nächste Lauf setzt fort.</p>
        <button type="submit" class="btn btn-secondary"<?= $googleConfigured ? '' : ' disabled aria-disabled="true" title="Zuerst API-Key und Suchmaschinen-ID hinterlegen"' ?>><i class="ti ti-world-search" aria-hidden="true"></i> Backfill starten</button>
        <?php if (!$googleConfigured): ?><p class="form-hint">Erst verfügbar, wenn API-Key und Suchmaschinen-ID hinterlegt sind (Abschnitt 6).</p><?php endif; ?>
    </form>
</div>

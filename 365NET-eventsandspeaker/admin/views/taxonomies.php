<?php
/**
 * Dropdown-Werte (Kategorien, Arten, Preisklassen, Tags).
 *
 * @var CMS_365NET_Events_Admin $this
 * @var array<string, string> $settings
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$this->pageHeader('Kategorien & Tags', 'Dropdown-Werte für die Event- und Speaker-Formulare. Ein Eintrag pro Zeile; Duplikate werden beim Speichern entfernt.');
$this->flash();
?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_TAXONOMIES)) ?>" class="cms365-form" novalidate>
    <?= $this->csrfField('365net_taxonomies_form') ?>
    <input type="hidden" name="cms365_action" value="save">
    <section class="admin-card cms365-section"><h3>Events</h3><div class="cms365-form-grid">
        <?= $this->textareaSetting('taxonomy_event_categories', 'Event-Kategorien / Hauptkategorie', $settings['taxonomy_event_categories'] ?? '') ?>
        <?= $this->textareaSetting('taxonomy_event_types', 'Event-Arten / Formate', $settings['taxonomy_event_types'] ?? '') ?>
        <?= $this->textareaSetting('taxonomy_event_tags', 'Event-Tags', $settings['taxonomy_event_tags'] ?? '') ?>
    </div><p class="cms365-muted">Die Preisklassen (Kostenfrei, Kostenpflichtig, Auf Einladung, Auf Anfrage, Sponsorenfinanziert bzw. Pro-bono) sind fest vorgegeben.</p></section>
    <section class="admin-card cms365-section"><h3>Speaker</h3><div class="cms365-form-grid">
        <?= $this->textareaSetting('taxonomy_speaker_categories', 'Speaker-Kategorien / Themen', $settings['taxonomy_speaker_categories'] ?? '') ?>
        <?= $this->textareaSetting('taxonomy_speaker_types', 'Speaker-Typen', $settings['taxonomy_speaker_types'] ?? '') ?>
        <?= $this->textareaSetting('taxonomy_speaker_tags', 'Speaker-Tags', $settings['taxonomy_speaker_tags'] ?? '') ?>
    </div></section>
    <div class="admin-card cms365-actions-card">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Listen speichern</button>
        <a class="btn btn-secondary" href="<?= $this->e(self::url(self::MENU_SLUG)) ?>">Abbrechen</a>
    </div>
</form>

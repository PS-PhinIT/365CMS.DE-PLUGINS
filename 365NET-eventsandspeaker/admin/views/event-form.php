<?php
/**
 * Event anlegen/bearbeiten.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var object|null $event
 * @var array<int, object> $allSpeakers
 * @var array<int, object> $assignedSpeakers
 * @var array<int, object> $companies
 * @var array<int, object> $experts
 * @var array<string, array<int, string>> $taxonomies
 * @var bool $excompActive
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$isEdit = $event !== null;
$assignedIds = array_map(static fn(object $speaker): int => (int) ($speaker->id ?? 0), $assignedSpeakers);
$actions = $this->button('Zurück zur Liste', self::url(self::PAGE_EVENTS), 'ti-arrow-left');
if ($isEdit && (string) ($event->status ?? '') === 'published') {
    $actions .= $this->button('Öffentlich ansehen', '/events/' . rawurlencode((string) ($event->slug ?? '')), 'ti-eye', 'btn-secondary', true);
}

$this->pageHeader(
    $isEdit ? 'Event bearbeiten' : 'Neues Event',
    $isEdit ? 'ID #' . (int) $event->id . ' · ' . (string) ($event->title ?? '') : 'Event, Messe oder Webinar anlegen und Speaker zuordnen.',
    $actions
);
$this->flash();
?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>" class="cms365-form" novalidate>
    <?= $this->csrfField('365net_event_form') ?>
    <input type="hidden" name="cms365_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $event->id ?>"><input type="hidden" name="unique_id" value="<?= $this->e($event->unique_id ?? '') ?>"><?php endif; ?>

    <section class="admin-card cms365-section"><h3>1. Basisdaten &amp; Veröffentlichung</h3><p class="cms365-muted">Pflichtdaten, Slug, Status und redaktionelle Hervorhebung.</p><div class="cms365-form-grid">
        <?= $this->field('title', 'Titel *', $event->title ?? '', true) ?>
        <?= $this->field('slug', 'Slug', $event->slug ?? '') ?>
        <?= $this->field('excerpt', 'Kurzbeschreibung / Teaser', $event->excerpt ?? '') ?>
        <?= $this->field('source_column', 'Spalte1 / Zusatzquelle', $event->source_column ?? '') ?>
        <?= $this->statusSelect((string) ($event->status ?? 'published')) ?>
        <?= $this->checkbox('featured', 'Als Highlight anzeigen', !empty($event->featured)) ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>2. Beschreibung</h3><p class="cms365-muted">Strukturierter Block-Inhalt mit Medien, Tabellen, Checklisten und Linkkarten.</p>
        <?= $this->editor('description_json', (string) ($event->description_json ?? ''), (string) ($event->description ?? ''), 'Event-Beschreibung') ?>
    </section>

    <section class="admin-card cms365-section"><h3>3. Bild &amp; Medien</h3><div class="cms365-form-grid">
        <?= $this->imageField('image_url', 'Event-Bild / Hero-URL', $event->image_url ?? '', 'image_alt', $event->image_alt ?? '') ?>
        <?= $this->field('image_bg_color', 'Bild-Hintergrund (rechter Header, HEX oder RGB)', $event->image_bg_color ?? '#eef2f7') ?>
        <?= $this->mediaUrlField('og_image_url', 'Social-Sharing-Bild', $event->og_image_url ?? '') ?>
    </div><p class="cms365-muted">Die Farbe hinter dem Event-Bild wird nur auf der Detailseite genutzt.</p>
        <?= $this->galleryField('gallery_json', 'Galerie (Mediathek oder je Zeile ein Bild)', $event->gallery_json ?? '') ?>
    </section>

    <section class="admin-card cms365-section"><h3>4. Datum, Zeit &amp; Zeitzone</h3><div class="cms365-form-grid">
        <?= $this->field('start_date', 'Startdatum', $event->start_date ?? '', false, 'date') ?>
        <?= $this->field('end_date', 'Enddatum', $event->end_date ?? '', false, 'date') ?>
        <?= $this->field('start_time', 'Startzeit', $event->start_time ?? '', false, 'time') ?>
        <?= $this->field('end_time', 'Endzeit', $event->end_time ?? '', false, 'time') ?>
        <?= $this->field('date_label', 'Datumslabel (Anzeige)', $event->date_label ?? '') ?>
        <?= $this->field('end_date_label', 'Bis-Label (Anzeige)', $event->end_date_label ?? '') ?>
        <?= $this->field('timezone', 'Zeitzone', $event->timezone ?? 'Europe/Berlin') ?>
        <?= $this->field('early_bird_until', 'Early-Bird bis', $event->early_bird_until ?? '', false, 'date') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>5. Ort, Online &amp; Hybrid</h3><div class="cms365-form-grid">
        <?= $this->field('location', 'Ort', $event->location ?? '') ?>
        <?= $this->field('venue_name', 'Venue / Locationname', $event->venue_name ?? '') ?>
        <?= $this->field('street', 'Straße', $event->street ?? '') ?>
        <?= $this->field('postal_code', 'PLZ', $event->postal_code ?? '') ?>
        <?= $this->field('city', 'Stadt', $event->city ?? '') ?>
        <?= $this->field('country', 'Land', $event->country ?? 'Deutschland') ?>
        <?= $this->select('attendance_mode', 'Durchführung', (string) ($event->attendance_mode ?? ''), ['' => 'Bitte wählen', 'Vor Ort' => 'Vor Ort', 'Online' => 'Online', 'Hybrid' => 'Hybrid']) ?>
        <?= $this->field('online_url', 'Online-/Stream-Link', $event->online_url ?? '', false, 'url') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>6. Veranstalter &amp; Kontakt</h3><div class="cms365-form-grid">
        <?= $this->field('organizer', 'Veranstalter', $event->organizer ?? '') ?>
        <?= $this->field('contact_name', 'Kontaktperson', $event->contact_name ?? '') ?>
        <?= $this->field('contact_email', 'Kontakt-E-Mail', $event->contact_email ?? '', false, 'email') ?>
        <?= $this->field('contact_phone', 'Kontakt-Telefon', $event->contact_phone ?? '') ?>
        <?= $this->field('website', 'Website', $event->website ?? '', false, 'url') ?>
    </div><label class="form-group cms365-form-group--full"><span>Sponsoren / Partner</span><textarea name="sponsors" class="form-control" rows="3"><?= $this->e($event->sponsors ?? '') ?></textarea></label></section>

    <section class="admin-card cms365-section"><h3>7. 365CMS-Verknüpfung</h3>
        <?php if ($excompActive): ?>
        <p class="cms365-muted">Optional manuell setzen. Ohne Auswahl versucht das Plugin, Veranstalter/Kontakt/Website mit bestehenden Firmen oder Experten abzugleichen.</p>
        <?php else: ?>
        <p class="cms365-muted">„365NET Experts &amp; Companies“ ist nicht aktiv – Firmen und Experten stehen nur zur Auswahl, wenn das Plugin aktiv ist.</p>
        <?php endif; ?>
        <div class="cms365-form-grid">
        <?= $this->select('linked_company_id', 'Vorhandene Firma verknüpfen', (string) ($event->linked_company_id ?? ''), $this->companyOptions($companies)) ?>
        <?= $this->select('linked_expert_id', 'Vorhandenen Experten verknüpfen', (string) ($event->linked_expert_id ?? ''), $this->expertOptions($experts)) ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>8. Kategorien, Tags &amp; Zielgruppe</h3><p class="cms365-muted">Mehrfachauswahl ist bei Kategorien, Tags, Zielgruppe und Sprache möglich.</p><div class="cms365-form-grid">
        <?= $this->select('category', 'Hauptkategorie', (string) ($event->category ?? ''), $this->optionMap($taxonomies['event_categories'] ?? [])) ?>
        <?= $this->multiSelect('categories', 'Weitere Kategorien', (string) ($event->categories ?? ''), $taxonomies['event_categories'] ?? []) ?>
        <?= $this->multiSelect('tags', 'Tags / Schlagwörter', (string) ($event->tags ?? ''), $taxonomies['event_tags'] ?? [], 8) ?>
        <?= $this->multiSelect('target_audience', 'Zielgruppe', (string) ($event->target_audience ?? ''), $this->targetAudienceOptions(), 8) ?>
        <?= $this->select('event_format', 'Format', (string) ($event->event_format ?? $event->event_type ?? ''), $this->optionMap($taxonomies['event_types'] ?? [])) ?>
        <?= $this->select('difficulty_level', 'Level', (string) ($event->difficulty_level ?? ''), ['' => 'Bitte wählen', 'Einsteiger' => 'Einsteiger', 'Fortgeschritten' => 'Fortgeschritten', 'Expert' => 'Expert', 'Business' => 'Business', 'Technisch' => 'Technisch']) ?>
        <?= $this->multiSelect('language', 'Sprache', (string) ($event->language ?? 'Deutsch'), $this->languageOptions(), 6) ?>
        <?= $this->field('event_type', 'Event-Art (Altfeld)', $event->event_type ?? '') ?>
    </div><label class="form-group cms365-form-group--full"><span>Barrierefreiheit / Hinweise</span><textarea name="accessibility" class="form-control" rows="3"><?= $this->e($event->accessibility ?? '') ?></textarea></label></section>

    <section class="admin-card cms365-section"><h3>9. Preise, Tickets &amp; Registrierung</h3><div class="cms365-form-grid">
        <?= $this->select('price_class', 'Preisklasse', (string) ($event->price_class ?? ''), $this->optionMap($taxonomies['event_price_classes'] ?? [])) ?>
        <?= $this->field('price', 'Preislabel', $event->price ?? '') ?>
        <?= $this->field('price_min', 'Preis min.', $event->price_min ?? '', false, 'number') ?>
        <?= $this->field('price_max', 'Preis max.', $event->price_max ?? '', false, 'number') ?>
        <?= $this->field('currency', 'Währung', $event->currency ?? 'EUR') ?>
        <?= $this->field('capacity', 'Kapazität', $event->capacity ?? '', false, 'number') ?>
        <?= $this->field('registration_url', 'Registrierung', $event->registration_url ?? '', false, 'url') ?>
        <?= $this->field('ticket_url', 'Ticket-Link', $event->ticket_url ?? '', false, 'url') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>10. Speaker-Zuordnung</h3><p class="cms365-muted">Mehrfachauswahl möglich. Neue Speaker unter „Speaker“ anlegen und hier verknüpfen.</p>
        <label class="form-group cms365-form-group--full"><span class="visually-hidden">Speaker</span><select name="speaker_ids[]" class="form-control cms365-multiselect" multiple size="12">
            <?php foreach ($allSpeakers as $speaker): ?><option value="<?= (int) $speaker->id ?>"<?= in_array((int) $speaker->id, $assignedIds, true) ? ' selected' : '' ?>><?= $this->e($speaker->display_name ?? '') ?><?= !empty($speaker->topic) ? ' – ' . $this->e($speaker->topic) : '' ?></option><?php endforeach; ?>
        </select></label>
    </section>

    <section class="admin-card cms365-section"><h3>11. SEO &amp; Social</h3><p class="cms365-muted">Leer: Titel bzw. Kurzbeschreibung des Events werden verwendet.</p><div class="cms365-form-grid">
        <?= $this->field('seo_title', 'SEO-Titel', $event->seo_title ?? '') ?>
        <?= $this->field('seo_description', 'SEO-Beschreibung', $event->seo_description ?? '') ?>
    </div></section>

    <div class="admin-card cms365-actions-card">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button>
        <a class="btn btn-secondary" href="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>">Abbrechen</a>
    </div>
</form>

<?php if ($isEdit): ?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>" class="cms365-danger-zone" data-cms-confirm="Dieses Event endgültig löschen?">
    <?= $this->csrfField('365net_admin') ?>
    <input type="hidden" name="cms365_action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $event->id ?>">
    <button type="submit" class="btn btn-danger"><i class="ti ti-trash" aria-hidden="true"></i> Event löschen</button>
</form>
<?php endif; ?>

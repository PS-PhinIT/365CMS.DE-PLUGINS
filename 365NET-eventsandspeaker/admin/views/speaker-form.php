<?php
/**
 * Speaker anlegen/bearbeiten.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var object|null $speaker
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

$isEdit = $speaker !== null;
$actions = $this->button('Zurück zur Liste', self::url(self::PAGE_SPEAKERS), 'ti-arrow-left');
if ($isEdit && (string) ($speaker->status ?? '') === 'published') {
    $actions .= $this->button('Öffentlich ansehen', '/speakers/' . rawurlencode((string) ($speaker->slug ?? '')), 'ti-eye', 'btn-secondary', true);
}

$this->pageHeader(
    $isEdit ? 'Speaker bearbeiten' : 'Neuer Speaker',
    $isEdit ? 'ID #' . (int) $speaker->id . ' · ' . (string) ($speaker->display_name ?? '') : 'Nur echte Personen als Speaker anlegen. Firmen werden über Experts & Companies verknüpft.',
    $actions
);
$this->flash();
?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_SPEAKERS)) ?>" class="cms365-form" novalidate>
    <?= $this->csrfField('365net_speaker_form') ?>
    <input type="hidden" name="cms365_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $speaker->id ?>"><input type="hidden" name="unique_id" value="<?= $this->e($speaker->unique_id ?? '') ?>"><?php endif; ?>

    <section class="admin-card cms365-section"><h3>1. Profil &amp; Veröffentlichung</h3><div class="cms365-form-grid">
        <?= $this->field('first_name', 'Vorname', $speaker->first_name ?? '') ?>
        <?= $this->field('last_name', 'Nachname', $speaker->last_name ?? '') ?>
        <?= $this->field('display_name', 'Anzeigename (optional, sonst Vor- + Nachname)', $speaker->display_name ?? '') ?>
        <?= $this->field('slug', 'Slug', $speaker->slug ?? '') ?>
        <?= $this->field('location', 'Standort', $speaker->location ?? '') ?>
        <?= $this->select('speaker_type', 'Speaker-Typ', (string) ($speaker->speaker_type ?? ''), $this->optionMap($taxonomies['speaker_types'] ?? [])) ?>
        <?= $this->statusSelect((string) ($speaker->status ?? 'published')) ?>
        <?= $this->checkbox('featured', 'Als Highlight anzeigen', !empty($speaker->featured)) ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>2. Bio</h3>
        <?= $this->editor('bio_json', (string) ($speaker->bio_json ?? ''), (string) ($speaker->bio ?? ''), 'Speaker-Bio') ?>
    </section>

    <section class="admin-card cms365-section"><h3>3. Bild &amp; Kontakt</h3><div class="cms365-form-grid">
        <?= $this->imageField('avatar_url', 'Profilbild-URL', $speaker->avatar_url ?? '', 'avatar_alt', $speaker->avatar_alt ?? '') ?>
        <?= $this->imageField('theme_image_url', 'Themenbild-URL (Header rechts)', $speaker->theme_image_url ?? '', 'theme_image_alt', $speaker->theme_image_alt ?? '') ?>
        <?= $this->field('email', 'E-Mail (nicht öffentlich)', $speaker->email ?? '', false, 'email') ?>
        <?= $this->field('phone', 'Telefon (nicht öffentlich)', $speaker->phone ?? '') ?>
        <?= $this->field('website', 'Website', $speaker->website ?? '', false, 'url') ?>
    </div><p class="cms365-muted">Pfad-Vorgaben: Profilbild bevorzugt unter <code>/uploads/speaker/…</code>, Themenbild unter <code>/uploads/speakerthemen/…</code>.</p></section>

    <section class="admin-card cms365-section"><h3>4. 365CMS-Verknüpfung</h3>
        <p class="cms365-muted"><?= $excompActive
            ? 'Optional vorhandenen Experten-/Firmen-Datensatz auswählen. Firmeninformationen bleiben in Experts &amp; Companies; am Speaker wird nur die Verknüpfung gespeichert.'
            : '„365NET Experts &amp; Companies“ ist nicht aktiv – Verknüpfungen sind nur mit aktivem Plugin möglich.' ?></p>
        <div class="cms365-form-grid">
        <?= $this->select('linked_expert_id', 'Vorhandenen Experten verknüpfen', (string) ($speaker->linked_expert_id ?? ''), $this->expertOptions($experts)) ?>
        <?= $this->select('linked_company_id', 'Vorhandene Firma verknüpfen', (string) ($speaker->linked_company_id ?? ''), $this->companyOptions($companies)) ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>5. Themen, Kategorien &amp; Tags</h3><div class="cms365-form-grid">
        <?= $this->select('topic', 'Thema/Kategorie', (string) ($speaker->topic ?? ''), $this->optionMap($taxonomies['speaker_categories'] ?? [])) ?>
        <?= $this->field('award', 'MVP/Auszeichnung', $speaker->award ?? '') ?>
        <?= $this->multiSelect('categories', 'Kategorien', (string) ($speaker->categories ?? ''), $taxonomies['speaker_categories'] ?? []) ?>
        <?= $this->multiSelect('tags', 'Tags', (string) ($speaker->tags ?? ''), $taxonomies['speaker_tags'] ?? [], 8) ?>
        <?= $this->field('specializations', 'Spezialisierungen', $speaker->specializations ?? '') ?>
        <?= $this->field('languages', 'Sprachen', $speaker->languages ?? 'Deutsch, Englisch') ?>
        <?= $this->field('speaking_formats', 'Formate', $speaker->speaking_formats ?? 'Keynote, Session, Workshop') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>6. Preisklasse &amp; Verfügbarkeit</h3><div class="cms365-form-grid">
        <?= $this->select('price_class', 'Preisklasse', (string) ($speaker->price_class ?? ''), $this->optionMap($taxonomies['speaker_price_classes'] ?? [])) ?>
        <?= $this->field('fee_min', 'Honorar min.', $speaker->fee_min ?? '', false, 'number') ?>
        <?= $this->field('fee_max', 'Honorar max.', $speaker->fee_max ?? '', false, 'number') ?>
        <?= $this->field('currency', 'Währung', $speaker->currency ?? 'EUR') ?>
        <?= $this->field('availability', 'Verfügbarkeit', $speaker->availability ?? '') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>7. Social Links</h3><div class="cms365-form-grid">
        <?= $this->field('linkedin_url', 'LinkedIn', $speaker->linkedin_url ?? '', false, 'url') ?>
        <?= $this->field('x_url', 'X / Twitter', $speaker->x_url ?? '', false, 'url') ?>
        <?= $this->field('youtube_url', 'YouTube', $speaker->youtube_url ?? '', false, 'url') ?>
        <?= $this->field('github_url', 'GitHub', $speaker->github_url ?? '', false, 'url') ?>
    </div></section>

    <section class="admin-card cms365-section"><h3>8. SEO &amp; Social</h3><p class="cms365-muted">Leer: Name bzw. Bio werden verwendet.</p><div class="cms365-form-grid">
        <?= $this->field('seo_title', 'SEO-Titel', $speaker->seo_title ?? '') ?>
        <?= $this->field('seo_description', 'SEO-Beschreibung', $speaker->seo_description ?? '') ?>
        <?= $this->mediaUrlField('og_image_url', 'Social-Sharing-Bild', $speaker->og_image_url ?? '') ?>
    </div></section>

    <div class="admin-card cms365-actions-card">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button>
        <a class="btn btn-secondary" href="<?= $this->e(self::url(self::PAGE_SPEAKERS)) ?>">Abbrechen</a>
    </div>
</form>

<?php if ($isEdit): ?>
<form method="post" action="<?= $this->e(self::url(self::PAGE_SPEAKERS)) ?>" class="cms365-danger-zone" data-cms-confirm="Diesen Speaker endgültig löschen? Die Zuordnungen zu Events werden entfernt.">
    <?= $this->csrfField('365net_admin') ?>
    <input type="hidden" name="cms365_action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $speaker->id ?>">
    <button type="submit" class="btn btn-danger"><i class="ti ti-trash" aria-hidden="true"></i> Speaker löschen</button>
</form>
<?php endif; ?>

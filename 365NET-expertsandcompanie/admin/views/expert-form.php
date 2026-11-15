<?php
/**
 * Unterseite „Expert anlegen/bearbeiten“: Header → Meldung → Formular (Profil, Verknüpfungen, Skills, Biografie).
 *
 * @var object|null $item
 * @var array{type:string,text:string}|null $notice
 * @var string $csrf
 * @var string $csrfList
 * @var string $action
 * @var string $listUrl
 * @var array<int, object> $speakers
 * @var array<int, object> $companies
 * @var string $siteUrl
 * @var callable(mixed):string $e
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$A = CMS_365NET_Experts_And_Companie_Admin::class;
$isEdit = $item !== null;
$id = (int) ($item->id ?? 0);
$v = static fn (string $key, string $default = ''): string => (string) ($item->{$key} ?? $default);
$name = trim($v('first_name') . ' ' . $v('last_name'));
$linkedCompanyId = (int) ($item->linked_company_id ?? 0);
$linkedSpeakerId = (int) ($item->linked_speaker_id ?? 0);
?>
<div class="admin-page-header">
    <div>
        <h2><?= $isEdit ? $e('Expert bearbeiten: ' . ($name !== '' ? $name : '#' . $id)) : 'Expert anlegen' ?></h2>
        <p>Ist die Biografie kürzer als 250 Wörter, ergänzt das Plugin beim Speichern eine ausführliche Fassung.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?= $e($listUrl) ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        <?php if ($isEdit && $v('status', 'active') === 'active'): ?>
            <a class="btn btn-outline-secondary" href="<?= $e($siteUrl . '/experts/' . $id) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link" aria-hidden="true"></i> Öffentliches Profil</a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/_notice.php'; ?>

<form method="post" action="<?= $e($action) ?>" class="cms-excomp-form" id="excomp-expert-form">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="excomp_action" value="save">
    <input type="hidden" name="id" value="<?= $id ?>">

    <section class="admin-card cms-excomp-card">
        <h3>Profil</h3>
        <div class="cms-excomp-form-grid">
            <?= $A::textField('first_name', 'Vorname *', $v('first_name'), 'text', 'required maxlength="120"') ?>
            <?= $A::textField('last_name', 'Nachname *', $v('last_name'), 'text', 'required maxlength="120"') ?>
            <?= $A::textField('email', 'E-Mail (erscheint als Kontakt-Button)', $v('email'), 'email', 'maxlength="180"') ?>
            <?= $A::textField('position', 'Position', $v('position')) ?>
            <?= $A::textField('company', 'Unternehmen (Freitext)', $v('company')) ?>
            <?= $A::textField('website', 'Website', $v('website'), 'url', 'placeholder="https://…"') ?>
            <?= $A::textField('city', 'Stadt', (string) ($item->location_city ?? $item->city ?? '')) ?>
            <?= $A::textField('country', 'Land', $v('country')) ?>
            <?= $A::mediaField('photo_url', 'Profilbild', $v('photo_url'), 'Profilbild hochladen oder aus der Mediathek auswählen.') ?>
            <?= $A::selectField('status', 'Status', $v('status', 'active') === 'inactive' ? 'inactive' : 'active', ['active' => 'Aktiv (öffentlich sichtbar)', 'inactive' => 'Inaktiv (ausgeblendet)']) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Verfügbarkeit &amp; Konditionen</h3>
        <div class="cms-excomp-form-grid">
            <?= $A::textField('availability', 'Verfügbarkeit', $v('availability', 'available')) ?>
            <?= $A::textField('experience_years', 'Erfahrung (Jahre)', $v('experience_years'), 'number', 'min="0" max="80" step="1"') ?>
            <?= $A::textField('hourly_rate', 'Stundensatz (EUR)', $v('hourly_rate'), 'text', 'inputmode="decimal"') ?>
            <?= $A::textField('daily_rate', 'Tagessatz (EUR)', $v('daily_rate'), 'text', 'inputmode="decimal"') ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Verknüpfungen</h3>
        <div class="cms-excomp-form-grid">
            <div class="cms-excomp-field">
                <label class="form-label" for="excomp-linked-company-id">Verknüpfte Company (Verzeichnis)</label>
                <select class="form-select" id="excomp-linked-company-id" name="linked_company_id">
                    <option value="">– keine –</option>
                    <?php foreach ($companies as $company): ?>
                        <?php $companyId = (int) ($company->id ?? 0); if ($companyId <= 0) { continue; } ?>
                        <option value="<?= $companyId ?>"<?= $linkedCompanyId === $companyId ? ' selected' : '' ?>><?= $e(trim((string) ($company->name ?? '')) !== '' ? (string) $company->name : 'Company #' . $companyId) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cms-excomp-field">
                <label class="form-label" for="excomp-linked-speaker-id">Verknüpfter Speaker (Events &amp; Speaker)</label>
                <select class="form-select" id="excomp-linked-speaker-id" name="linked_speaker_id"<?= $speakers === [] ? ' disabled' : '' ?>>
                    <option value=""><?= $speakers === [] ? '– Plugin Events & Speaker nicht aktiv –' : '– keiner –' ?></option>
                    <?php foreach ($speakers as $speaker): ?>
                        <?php
                        $speakerId = (int) ($speaker->id ?? 0);
                        if ($speakerId <= 0) {
                            continue;
                        }
                        $speakerName = trim((string) ($speaker->display_name ?? '')) ?: 'Speaker #' . $speakerId;
                        $topic = trim((string) ($speaker->topic ?? ''));
                        ?>
                        <option value="<?= $speakerId ?>"<?= $linkedSpeakerId === $speakerId ? ' selected' : '' ?>><?= $e($topic !== '' ? $speakerName . ' – ' . $topic : $speakerName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Skills &amp; Qualifikationen</h3>
        <div class="cms-excomp-form-grid">
            <?= $A::textAreaField('skills_general', 'Skills (allgemein)', $v('skills_general'), 3) ?>
            <?= $A::textAreaField('skills_tech', 'Skills (Technik)', $v('skills_tech'), 3) ?>
            <?= $A::textAreaField('skills_soft', 'Skills (Soft Skills)', $v('skills_soft'), 3) ?>
            <?= $A::textAreaField('awards', 'Auszeichnungen', $v('awards'), 2) ?>
            <?= $A::textAreaField('certifications', 'Zertifizierungen', $v('certifications'), 2) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <?= $A::editor('biography_json', $v('biography_json'), $v('biography'), 'Biografie') ?>
    </section>

    <div class="cms-excomp-form-actions">
        <?php if ($isEdit && $csrfList !== ''): ?>
            <button type="submit" form="excomp-delete-form" class="btn btn-outline-danger" data-cms-confirm="<?= $e('Expert „' . $name . '“ endgültig löschen?') ?>"><i class="ti ti-trash" aria-hidden="true"></i> Löschen</button>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Speichern</button>
    </div>
</form>

<?php if ($isEdit && $csrfList !== ''): ?>
    <form method="post" action="<?= $e($action) ?>" id="excomp-delete-form" hidden>
        <input type="hidden" name="csrf_token" value="<?= $e($csrfList) ?>">
        <input type="hidden" name="excomp_action" value="delete">
        <input type="hidden" name="ids[]" value="<?= $id ?>">
    </form>
<?php endif; ?>

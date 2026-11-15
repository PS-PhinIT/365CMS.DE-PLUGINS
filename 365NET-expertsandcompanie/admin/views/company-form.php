<?php
/**
 * Unterseite „Company anlegen/bearbeiten“: Header → Meldung → Formular (Stammdaten, Kontakt, Verknüpfungen, Beschreibung).
 *
 * @var object|null $item
 * @var array{type:string,text:string}|null $notice
 * @var string $csrf
 * @var string $csrfList
 * @var string $action
 * @var string $listUrl
 * @var array<int, object> $speakers
 * @var array<int, object> $companies
 * @var array<int, object> $experts
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
$name = trim($v('name'));
$parentId = (int) ($item->parent_company_id ?? 0);
$linkedExpertId = (int) ($item->linked_expert_id ?? 0);
$linkedSpeakerId = (int) ($item->linked_speaker_id ?? 0);
?>
<div class="admin-page-header">
    <div>
        <h2><?= $isEdit ? $e('Company bearbeiten: ' . ($name !== '' ? $name : '#' . $id)) : 'Company anlegen' ?></h2>
        <p>Ist die Beschreibung kürzer als 250 Wörter, ergänzt das Plugin beim Speichern eine ausführliche Fassung.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?= $e($listUrl) ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        <?php if ($isEdit && $v('status', 'active') === 'active'): ?>
            <a class="btn btn-outline-secondary" href="<?= $e($siteUrl . '/companies/' . $id) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link" aria-hidden="true"></i> Öffentliches Profil</a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/_notice.php'; ?>

<form method="post" action="<?= $e($action) ?>" class="cms-excomp-form" id="excomp-company-form">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="excomp_action" value="save">
    <input type="hidden" name="id" value="<?= $id ?>">

    <section class="admin-card cms-excomp-card">
        <h3>Stammdaten</h3>
        <div class="cms-excomp-form-grid">
            <?= $A::textField('name', 'Name *', $v('name'), 'text', 'required maxlength="255"') ?>
            <?= $A::textField('industry', 'Branche', $v('industry')) ?>
            <?= $A::textField('company_size', 'Firmengröße', $v('company_size')) ?>
            <?= $A::textField('employee_count', 'Mitarbeitende', $v('employee_count'), 'number', 'min="0" step="1"') ?>
            <?= $A::textField('founded_year', 'Gründungsjahr', $v('founded_year'), 'number', 'min="1000" max="2100" step="1"') ?>
            <?= $A::textField('website', 'Website', $v('website'), 'url', 'placeholder="https://…"') ?>
            <?= $A::mediaField('logo_url', 'Logo / Profilbild', $v('logo_url'), 'Logo hochladen oder aus der Mediathek auswählen.') ?>
            <?= $A::selectField('status', 'Status', $v('status', 'active') === 'inactive' ? 'inactive' : 'active', ['active' => 'Aktiv (öffentlich sichtbar)', 'inactive' => 'Inaktiv (ausgeblendet)']) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Kontakt &amp; Standort</h3>
        <p class="cms-excomp-muted">E-Mail und Telefon erscheinen öffentlich auf der Detailseite. Für personenbezogene Adressen gelten DSGVO-Auskunft und -Löschung.</p>
        <div class="cms-excomp-form-grid">
            <?= $A::textField('email', 'E-Mail', $v('email'), 'email', 'maxlength="180"') ?>
            <?= $A::textField('phone', 'Telefon', $v('phone'), 'tel', 'maxlength="80"') ?>
            <?= $A::textField('city', 'Stadt', (string) ($item->location_city ?? $item->city ?? '')) ?>
            <?= $A::textField('zip', 'PLZ', $v('zip'), 'text', 'maxlength="20"') ?>
            <?= $A::textField('country', 'Land', $v('country')) ?>
        </div>
    </section>

    <section class="admin-card cms-excomp-card">
        <h3>Partner-Level &amp; Verknüpfungen</h3>
        <div class="cms-excomp-form-checks">
            <?= $A::checkField('is_partner', 'Partner', (int) ($item->is_partner ?? 0) === 1) ?>
            <?= $A::checkField('is_top_partner', 'Top-Partner', (int) ($item->is_top_partner ?? 0) === 1) ?>
            <?= $A::checkField('is_sponsor', 'Sponsor', (int) ($item->is_sponsor ?? 0) === 1) ?>
        </div>
        <div class="cms-excomp-form-grid">
            <div class="cms-excomp-field">
                <label class="form-label" for="excomp-parent-company-id">Mutterkonzern / Mutterfirma</label>
                <select class="form-select" id="excomp-parent-company-id" name="parent_company_id">
                    <option value="">– keine –</option>
                    <?php foreach ($companies as $company): ?>
                        <?php
                        $companyId = (int) ($company->id ?? 0);
                        if ($companyId <= 0 || $companyId === $id) {
                            continue;
                        }
                        $label = trim((string) ($company->name ?? '')) ?: 'Company #' . $companyId;
                        $city = trim((string) ($company->location_city ?? $company->city ?? ''));
                        ?>
                        <option value="<?= $companyId ?>"<?= $parentId === $companyId ? ' selected' : '' ?>><?= $e($city !== '' ? $label . ' – ' . $city : $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cms-excomp-field">
                <label class="form-label" for="excomp-linked-expert-id">Verknüpfter Expert</label>
                <select class="form-select" id="excomp-linked-expert-id" name="linked_expert_id">
                    <option value="">– keiner –</option>
                    <?php foreach ($experts as $expert): ?>
                        <?php
                        $expertId = (int) ($expert->id ?? 0);
                        if ($expertId <= 0) {
                            continue;
                        }
                        $label = trim((string) ($expert->first_name ?? '') . ' ' . (string) ($expert->last_name ?? '')) ?: 'Expert #' . $expertId;
                        $companyName = trim((string) ($expert->company ?? ''));
                        ?>
                        <option value="<?= $expertId ?>"<?= $linkedExpertId === $expertId ? ' selected' : '' ?>><?= $e($companyName !== '' ? $label . ' – ' . $companyName : $label) ?></option>
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
        <?= $A::editor('description_json', $v('description_json'), $v('description'), 'Beschreibung') ?>
    </section>

    <div class="cms-excomp-form-actions">
        <?php if ($isEdit && $csrfList !== ''): ?>
            <button type="submit" form="excomp-delete-form" class="btn btn-outline-danger" data-cms-confirm="<?= $e('Company „' . $name . '“ endgültig löschen? Verknüpfte Experts und Speaker bleiben erhalten.') ?>"><i class="ti ti-trash" aria-hidden="true"></i> Löschen</button>
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

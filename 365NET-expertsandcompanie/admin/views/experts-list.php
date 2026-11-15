<?php
/**
 * Unterseite „Experts“: Header → Toolbar/Filter → Sammelaktionen + Tabelle → Seiten.
 *
 * @var array<int, object> $rows
 * @var array{search:string,status:string,sort:string} $filter
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array{type:string,text:string}|null $notice
 * @var string $csrf
 * @var string $csrfExport
 * @var string $pageSlug
 * @var string $listUrl
 * @var string $newUrl
 * @var string $transferUrl
 * @var string $siteUrl
 * @var callable(mixed):string $e
 * @var callable(string, array<string, scalar>):string $url
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$A = CMS_365NET_Experts_And_Companie_Admin::class;
$hasFilter = $filter['search'] !== '' || $filter['status'] !== '';
?>
<div class="admin-page-header">
    <div>
        <h2>Experts</h2>
        <p><?= $e(number_format($total, 0, ',', '.')) ?> <?= $total === 1 ? 'Eintrag' : 'Einträge' ?><?= $hasFilter ? ' (gefiltert)' : '' ?> · Profile für /experts und den gemeinsamen Hub.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?= $e($newUrl) ?>"><i class="ti ti-user-plus" aria-hidden="true"></i> Neuer Expert</a>
        <button type="submit" form="excomp-export-form" class="btn btn-outline-secondary"><i class="ti ti-file-export" aria-hidden="true"></i> CSV exportieren</button>
        <a class="btn btn-outline-secondary" href="<?= $e($transferUrl) ?>"><i class="ti ti-file-import" aria-hidden="true"></i> Import</a>
    </div>
</div>

<?php include __DIR__ . '/_notice.php'; ?>

<form method="get" action="<?= $e($listUrl) ?>" class="cms-excomp-toolbar toolbar" role="search">
    <input type="hidden" name="view" value="<?= $e($pageSlug) ?>">
    <div class="cms-excomp-toolbar__search">
        <label class="visually-hidden" for="excomp-filter-q">Suche</label>
        <input class="form-control" type="search" id="excomp-filter-q" name="q" value="<?= $e($filter['search']) ?>" placeholder="Name, Firma, Position, Skills …">
    </div>
    <label class="visually-hidden" for="excomp-filter-status">Status</label>
    <select class="form-select" id="excomp-filter-status" name="status">
        <option value="">Alle Status</option>
        <option value="active"<?= $filter['status'] === 'active' ? ' selected' : '' ?>>Aktiv</option>
        <option value="inactive"<?= $filter['status'] === 'inactive' ? ' selected' : '' ?>>Inaktiv</option>
    </select>
    <label class="visually-hidden" for="excomp-filter-sort">Sortierung</label>
    <select class="form-select" id="excomp-filter-sort" name="sort">
        <?php foreach (['az' => 'Name A–Z', 'za' => 'Name Z–A', 'date_new_old' => 'Zuletzt geändert', 'date_old_new' => 'Älteste Änderung'] as $key => $label): ?>
            <option value="<?= $e($key) ?>"<?= $filter['sort'] === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline-primary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
    <?php if ($hasFilter || $filter['sort'] !== 'az'): ?>
        <a class="btn btn-link" href="<?= $e($listUrl) ?>">Zurücksetzen</a>
    <?php endif; ?>
</form>

<form method="post" action="<?= $e($listUrl) ?>" id="excomp-export-form" hidden>
    <input type="hidden" name="csrf_token" value="<?= $e($csrfExport) ?>">
    <input type="hidden" name="excomp_action" value="export">
    <input type="hidden" name="q" value="<?= $e($filter['search']) ?>">
    <input type="hidden" name="status" value="<?= $e($filter['status']) ?>">
</form>
<form method="post" action="<?= $e($listUrl) ?>" id="excomp-row-form" hidden>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="excomp_action" value="delete">
</form>

<?php if ($rows === []): ?>
    <div class="admin-card empty-state cms-excomp-empty">
        <i class="ti ti-user-search" aria-hidden="true"></i>
        <p><?= $hasFilter ? 'Keine Experts passen zum Filter.' : 'Noch keine Experts vorhanden.' ?></p>
        <a class="btn btn-primary" href="<?= $e($newUrl) ?>"><i class="ti ti-user-plus" aria-hidden="true"></i> Neuer Expert</a>
    </div>
<?php else: ?>
    <form method="post" action="<?= $e($listUrl) ?>" class="admin-card cms-excomp-list" data-cms-confirm="Ausgewählte Aktion auf die markierten Experts anwenden?">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <input type="hidden" name="excomp_action" value="bulk">
        <div class="cms-excomp-bulk">
            <label class="visually-hidden" for="excomp-bulk-action">Sammelaktion</label>
            <select class="form-select" id="excomp-bulk-action" name="bulk_action">
                <option value="">Sammelaktion wählen</option>
                <option value="activate">Aktivieren (öffentlich)</option>
                <option value="deactivate">Deaktivieren (ausblenden)</option>
                <option value="delete">Löschen</option>
            </select>
            <button type="submit" class="btn btn-outline-secondary">Anwenden</button>
        </div>
        <div class="users-table-container">
            <table class="users-table cms-excomp-table">
                <thead>
                <tr>
                    <th scope="col" class="cms-excomp-table__check"><input class="form-check-input" type="checkbox" data-excomp-select-all aria-label="Alle auswählen"></th>
                    <th scope="col">Name</th>
                    <th scope="col">Position / Firma</th>
                    <th scope="col">Standort</th>
                    <th scope="col">Status</th>
                    <th scope="col">Geändert</th>
                    <th scope="col" class="cms-excomp-table__actions">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $id = (int) ($row->id ?? 0);
                    $name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
                    $status = (string) ($row->status ?? 'active');
                    $updated = strtotime((string) ($row->updated_at ?? ''));
                    $editUrl = $url($pageSlug, ['action' => 'edit', 'id' => $id]);
                    ?>
                    <tr>
                        <td class="cms-excomp-table__check"><input class="form-check-input" type="checkbox" name="ids[]" value="<?= $id ?>" aria-label="<?= $e($name) ?> auswählen" data-excomp-select-item></td>
                        <td><a class="cms-excomp-table__title" href="<?= $e($editUrl) ?>"><?= $e($name !== '' ? $name : '#' . $id) ?></a></td>
                        <td>
                            <div><?= $e((string) ($row->position ?? '')) ?></div>
                            <small class="cms-excomp-muted"><?= $e((string) ($row->company ?? '')) ?></small>
                        </td>
                        <td><?= $e((string) ($row->location_city ?? $row->city ?? '')) ?></td>
                        <td><?= $A::statusBadge($status) ?></td>
                        <td><small><?= $updated !== false ? $e(date('d.m.Y', $updated)) : '–' ?></small></td>
                        <td class="cms-excomp-table__actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= $e($editUrl) ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                            <?php if ($status === 'active'): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $e($siteUrl . '/experts/' . $id) ?>" target="_blank" rel="noopener" title="Öffentliches Profil"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Ansehen</span></a>
                            <?php endif; ?>
                            <button type="submit" form="excomp-row-form" name="ids[]" value="<?= $id ?>" class="btn btn-sm btn-outline-danger" title="Löschen" data-cms-confirm="<?= $e('Expert „' . ($name !== '' ? $name : '#' . $id) . '“ endgültig löschen?') ?>"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?= $A::pagination($pageSlug, $filter, $page, $pages) ?>
<?php endif; ?>

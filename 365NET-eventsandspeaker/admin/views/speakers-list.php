<?php
/**
 * Speakerliste: Header → Filter/Sortierung → Sammelaktionen/Tabelle → Seitenwechsel.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var array<int, object> $speakers
 * @var array{q: string, status: string, sort: string, args: array<string, mixed>} $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$query = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'sort' => $filters['sort'] !== 'az' ? $filters['sort'] : ''], static fn(string $v): bool => $v !== '');
$exportUrl = self::url(self::PAGE_SPEAKERS, $query + ['action' => 'export', 'token' => CMS\Security::instance()->generateToken('365net_export')]);
$sortOptions = ['az' => 'Name A–Z', 'za' => 'Name Z–A', 'date_new_old' => 'Zuletzt geändert', 'date_old_new' => 'Älteste Änderung'];
$adminToken = CMS\Security::instance()->generateToken('365net_admin');

$this->pageHeader(
    'Speaker',
    'Speaker-Profile echter Personen pflegen; Firmen werden über Experts & Companies verknüpft.',
    $this->button('CSV-Export', $exportUrl, 'ti-download')
    . $this->button('Öffentlich', '/speakers', 'ti-world', 'btn-secondary', true)
    . $this->button('Neuer Speaker', self::url(self::PAGE_SPEAKERS, ['action' => 'new']), 'ti-plus', 'btn-primary')
);
$this->flash();

echo $this->getFormOpen(self::PAGE_SPEAKERS);
?>
    <label class="cms365-toolbar__search"><span class="visually-hidden">Suche</span><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" placeholder="Speaker, Thema, Auszeichnung oder Tag …" class="form-control"></label>
    <label><span class="visually-hidden">Status</span><select name="status" class="form-select">
        <option value="">Alle Status</option>
        <option value="published"<?= $filters['status'] === 'published' ? ' selected' : '' ?>>Veröffentlicht</option>
        <option value="draft"<?= $filters['status'] === 'draft' ? ' selected' : '' ?>>Entwurf</option>
    </select></label>
    <label><span class="visually-hidden">Sortierung</span><select name="sort" class="form-select">
        <?php foreach ($sortOptions as $key => $label): ?><option value="<?= $this->e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary" type="submit"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
    <?php if ($query !== []): ?><a class="btn btn-secondary" href="<?= $this->e(self::url(self::PAGE_SPEAKERS)) ?>">Zurücksetzen</a><?php endif; ?>
</form>

<form method="post" action="<?= $this->e(self::url(self::PAGE_SPEAKERS, $query + ($page > 1 ? ['p' => $page] : []))) ?>" class="admin-card cms365-card">
    <input type="hidden" name="csrf_token" value="<?= $this->e($adminToken) ?>">
    <input type="hidden" name="cms365_action" value="bulk">
    <div class="cms365-card__head">
        <h3>Speaker (<?= (int) $total ?>)</h3>
        <div class="cms365-bulk">
            <label><span class="visually-hidden">Sammelaktion</span><select name="bulk_action" class="form-select form-select-sm">
                <option value="">Sammelaktion …</option>
                <option value="publish">Veröffentlichen</option>
                <option value="draft">Als Entwurf</option>
                <option value="delete">Löschen</option>
            </select></label>
            <button class="btn btn-sm btn-secondary" type="submit" data-cms-confirm="Sammelaktion auf die markierten Speaker anwenden?">Anwenden</button>
        </div>
    </div>
    <?php if ($speakers === []): ?>
        <div class="empty-state"><p>Keine Speaker für diesen Filter gefunden.</p></div>
    <?php else: ?>
    <div class="users-table-container"><table class="users-table cms365-table">
        <thead><tr>
            <th scope="col" class="cms365-col-check"><input type="checkbox" data-cms365-check-all aria-label="Alle markieren"></th>
            <th scope="col">Speaker</th><th scope="col">Thema</th><th scope="col">Events</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($speakers as $speaker): $id = (int) ($speaker->id ?? 0); ?>
            <tr>
                <td class="cms365-col-check"><input type="checkbox" name="ids[]" value="<?= $id ?>" aria-label="<?= $this->e('Markieren: ' . (string) ($speaker->display_name ?? '')) ?>"></td>
                <td><a href="<?= $this->e(self::url(self::PAGE_SPEAKERS, ['action' => 'edit', 'id' => $id])) ?>"><strong><?= $this->e($speaker->display_name ?? '') ?></strong></a><?php if (!empty($speaker->award)): ?><br><small class="cms365-muted"><?= $this->e($speaker->award) ?></small><?php endif; ?></td>
                <td><?= $this->e($speaker->topic ?? '') ?></td>
                <td><?= (int) ($speaker->event_count ?? 0) ?></td>
                <td><?= $this->statusBadge((string) ($speaker->status ?? '')) ?></td>
                <td class="cms365-row-actions">
                    <a class="btn btn-sm btn-secondary" href="<?= $this->e(self::url(self::PAGE_SPEAKERS, ['action' => 'edit', 'id' => $id])) ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                    <?php if (($speaker->status ?? '') === 'published'): ?><a class="btn btn-sm btn-secondary" href="<?= $this->e('/speakers/' . rawurlencode((string) ($speaker->slug ?? ''))) ?>" target="_blank" rel="noopener noreferrer" title="Ansehen"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Ansehen</span></a><?php endif; ?>
                    <button class="btn btn-sm btn-danger" type="submit" name="row_delete" value="<?= $id ?>" title="Löschen" data-cms-confirm="Diesen Speaker löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?= $this->pagination(self::PAGE_SPEAKERS, $page, $pages, $query) ?>
</form>

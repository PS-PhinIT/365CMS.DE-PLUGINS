<?php
/**
 * Eventliste: Header → Filter → Sammelaktionen/Tabelle → Seitenwechsel.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var array<int, object> $events
 * @var array{q: string, status: string, time: string, args: array<string, mixed>} $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$query = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'time' => $filters['time']], static fn(string $v): bool => $v !== '');
$exportUrl = self::url(self::PAGE_EVENTS, $query + ['action' => 'export', 'token' => CMS\Security::instance()->generateToken('365net_export')]);
$timeLabels = ['upcoming' => 'Anstehend', 'past' => 'Vergangen', 'undated' => 'Ohne Datum', 'all' => 'Alle'];
$adminToken = CMS\Security::instance()->generateToken('365net_admin');

$this->pageHeader(
    'Events',
    'Events, Messen und Webinare verwalten und mit Speakern verknüpfen.',
    $this->button('CSV-Export', $exportUrl, 'ti-download')
    . $this->button('Öffentlich', '/events', 'ti-world', 'btn-secondary', true)
    . $this->button('Neues Event', self::url(self::PAGE_EVENTS, ['action' => 'new']), 'ti-plus', 'btn-primary')
);
$this->flash();

echo $this->getFormOpen(self::PAGE_EVENTS);
?>
    <label class="cms365-toolbar__search"><span class="visually-hidden">Suche</span><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" placeholder="Titel, Ort, Veranstalter oder Kategorie …" class="form-control"></label>
    <label><span class="visually-hidden">Zeitraum</span><select name="time" class="form-select">
        <?php foreach ($timeLabels as $key => $label): ?><option value="<?= $this->e($key) ?>"<?= $filters['time'] === $key ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?>
    </select></label>
    <label><span class="visually-hidden">Status</span><select name="status" class="form-select">
        <option value="">Alle Status</option>
        <option value="published"<?= $filters['status'] === 'published' ? ' selected' : '' ?>>Veröffentlicht</option>
        <option value="draft"<?= $filters['status'] === 'draft' ? ' selected' : '' ?>>Entwurf</option>
    </select></label>
    <button class="btn btn-primary" type="submit"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['time'] !== 'upcoming'): ?><a class="btn btn-secondary" href="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>">Zurücksetzen</a><?php endif; ?>
</form>

<form method="post" action="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>" class="cms365-sync-form">
    <input type="hidden" name="csrf_token" value="<?= $this->e($adminToken) ?>">
    <input type="hidden" name="cms365_action" value="link_sync">
    <button class="btn btn-sm btn-secondary" type="submit"><i class="ti ti-link" aria-hidden="true"></i> Verknüpfungen zu Firmen/Experten aktualisieren</button>
</form>

<form method="post" action="<?= $this->e(self::url(self::PAGE_EVENTS, $query + ($page > 1 ? ['p' => $page] : []))) ?>" class="admin-card cms365-card">
    <input type="hidden" name="csrf_token" value="<?= $this->e($adminToken) ?>">
    <input type="hidden" name="cms365_action" value="bulk">
    <div class="cms365-card__head">
        <h3><?= $this->e($timeLabels[$filters['time']] ?? 'Events') ?> (<?= (int) $total ?>)</h3>
        <div class="cms365-bulk">
            <label><span class="visually-hidden">Sammelaktion</span><select name="bulk_action" class="form-select form-select-sm">
                <option value="">Sammelaktion …</option>
                <option value="publish">Veröffentlichen</option>
                <option value="draft">Als Entwurf</option>
                <option value="delete">Löschen</option>
            </select></label>
            <button class="btn btn-sm btn-secondary" type="submit" data-cms-confirm="Sammelaktion auf die markierten Events anwenden?">Anwenden</button>
        </div>
    </div>
    <?php if ($events === []): ?>
        <div class="empty-state"><p>Keine Events für diesen Filter gefunden.</p></div>
    <?php else: ?>
    <div class="users-table-container"><table class="users-table cms365-table">
        <thead><tr>
            <th scope="col" class="cms365-col-check"><input type="checkbox" data-cms365-check-all aria-label="Alle markieren"></th>
            <th scope="col">Event</th><th scope="col">Datum</th><th scope="col">Ort</th><th scope="col">Speaker</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $event): $id = (int) ($event->id ?? 0); ?>
            <tr>
                <td class="cms365-col-check"><input type="checkbox" name="ids[]" value="<?= $id ?>" aria-label="<?= $this->e('Markieren: ' . (string) ($event->title ?? '')) ?>"></td>
                <td><a href="<?= $this->e(self::url(self::PAGE_EVENTS, ['action' => 'edit', 'id' => $id])) ?>"><strong><?= $this->e($event->title ?? '') ?></strong></a><?php if (!empty($event->organizer)): ?><br><small class="cms365-muted"><?= $this->e($event->organizer) ?></small><?php endif; ?></td>
                <td><?= $this->e($event->date_label ?? '') ?><?= ($event->end_date_label ?? '') !== '' ? ' – ' . $this->e($event->end_date_label) : '' ?></td>
                <td><?= $this->e($event->location ?? '') ?></td>
                <td><?= (int) ($event->speaker_count ?? 0) ?></td>
                <td><?= $this->statusBadge((string) ($event->status ?? '')) ?></td>
                <td class="cms365-row-actions">
                    <a class="btn btn-sm btn-secondary" href="<?= $this->e(self::url(self::PAGE_EVENTS, ['action' => 'edit', 'id' => $id])) ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                    <?php if (($event->status ?? '') === 'published'): ?><a class="btn btn-sm btn-secondary" href="<?= $this->e('/events/' . rawurlencode((string) ($event->slug ?? ''))) ?>" target="_blank" rel="noopener noreferrer" title="Ansehen"><i class="ti ti-eye" aria-hidden="true"></i><span class="visually-hidden">Ansehen</span></a><?php endif; ?>
                    <button class="btn btn-sm btn-danger" type="submit" name="row_delete" value="<?= $id ?>" title="Löschen" data-cms-confirm="Dieses Event löschen?"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?= $this->pagination(self::PAGE_EVENTS, $page, $pages, $query) ?>
</form>

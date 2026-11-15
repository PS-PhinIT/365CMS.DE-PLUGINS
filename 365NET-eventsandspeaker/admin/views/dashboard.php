<?php
/**
 * Übersicht: Zusatzinhalt unter Schnellaktionen/System-Check – die nächsten fünf Events.
 *
 * @var CMS_365NET_Events_Admin $this
 * @var array<int, object> $upcomingEvents
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="admin-card cms365-card">
    <h3>Nächste Events</h3>
    <?php if ($upcomingEvents === []): ?>
        <p class="cms365-muted">Keine anstehenden Events vorhanden.</p>
    <?php else: ?>
        <div class="users-table-container"><table class="users-table cms365-table">
            <thead><tr><th scope="col">Event</th><th scope="col">Datum</th><th scope="col">Ort</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
            <tbody>
            <?php foreach ($upcomingEvents as $event): ?>
                <tr>
                    <td><strong><?= $this->e($event->title ?? '') ?></strong></td>
                    <td><?= $this->e($event->date_label ?? '') ?><?= ($event->end_date_label ?? '') !== '' ? ' – ' . $this->e($event->end_date_label) : '' ?></td>
                    <td><?= $this->e($event->location ?? '') ?></td>
                    <td><?= $this->statusBadge((string) ($event->status ?? '')) ?></td>
                    <td class="cms365-row-actions"><a class="btn btn-sm btn-secondary" href="<?= $this->e(self::url(self::PAGE_EVENTS, ['action' => 'edit', 'id' => (int) ($event->id ?? 0)])) ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="cms365-card__footer"><a class="btn btn-secondary" href="<?= $this->e(self::url(self::PAGE_EVENTS)) ?>">Alle anstehenden Events</a></p>
    <?php endif; ?>
</div>

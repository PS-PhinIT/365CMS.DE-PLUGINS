<?php
/**
 * Platzierungen: Liste (Filter, Sammelaktionen) bzw. Formular (?action=new / ?edit=ID).
 *
 * @var CMS_Promos_Repository $repository
 * @var array<string,string> $hookOptions
 * @var array{type:string,message:string}|null $notice
 * @var string $csrfToken
 * @var bool $isForm
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Promos_Admin_Pages::url('promos-placements');
?>

<?php if ($isForm): ?>
    <?php
    $placement = is_array($placement ?? null) ? $placement : [];
    $placementId = (int) ($placement['id'] ?? 0);
    $selectedHook = (string) ($placement['theme_hook'] ?? 'manual');
    ?>
    <div class="admin-page-header">
        <div>
            <h2><?php echo $placementId > 0 ? 'Platzierung bearbeiten' : 'Neue Platzierung'; ?></h2>
            <p>Einbauort, Kapazität und automatische Ausgabe über einen Theme-Hook festlegen.</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-outline-secondary" href="<?php echo $esc($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        </div>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo $esc($notice['message']); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements', $placementId > 0 ? ['edit' => $placementId] : ['action' => 'new'])); ?>" class="admin-form pr-form">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="action" value="save_placement">
        <input type="hidden" name="placement_id" value="<?php echo $placementId; ?>">

        <section class="admin-card pr-section">
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="placement-name">Name</label><input id="placement-name" type="text" name="name" class="form-control" required maxlength="180" value="<?php echo $esc($placement['name'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="placement-slug">Slug</label><input id="placement-slug" type="text" name="slug" class="form-control" maxlength="120" pattern="[a-z0-9\-]*" value="<?php echo $esc($placement['slug'] ?? ''); ?>"><div class="form-help">Leer lassen, um ihn aus dem Namen zu erzeugen. Öffentlich unter <code>/promos/placement/&lt;slug&gt;</code>.</div></div>
            </div>
            <div class="form-group"><label class="form-label" for="placement-description">Beschreibung</label><textarea id="placement-description" name="description" class="form-control" rows="3" maxlength="2000"><?php echo $esc($placement['description'] ?? ''); ?></textarea></div>
            <div class="pr-form-grid pr-form-grid--3">
                <div class="form-group">
                    <label class="form-label" for="placement-status">Status</label>
                    <select id="placement-status" name="status" class="form-select">
                        <option value="active"<?php echo ($placement['status'] ?? 'active') === 'active' ? ' selected' : ''; ?>>Aktiv</option>
                        <option value="inactive"<?php echo ($placement['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>>Inaktiv</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="placement-max">Maximale Promos</label><input id="placement-max" type="number" name="max_items" class="form-control" min="1" max="50" value="<?php echo max(1, (int) ($placement['max_items'] ?? 3)); ?>"></div>
                <div class="form-group"><label class="form-label" for="placement-priority">Hook-Priorität</label><input id="placement-priority" type="number" name="hook_priority" class="form-control" min="-1000" max="1000" value="<?php echo (int) ($placement['hook_priority'] ?? 10); ?>"><div class="form-help">Kleinere Werte werden im selben Hook zuerst ausgegeben.</div></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="placement-hook">Theme-Hook</label>
                <select id="placement-hook" name="theme_hook" class="form-select">
                    <?php foreach ($hookOptions as $key => $label): ?>
                        <option value="<?php echo $esc($key); ?>"<?php echo $selectedHook === $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">Aktive Platzierungen mit Hook werden automatisch im Theme ausgegeben; „manuell“ erscheint nur im Archiv.</div>
            </div>
        </section>

        <div class="pr-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Platzierung speichern</button>
            <a class="btn btn-outline-secondary" href="<?php echo $esc($listUrl); ?>">Abbrechen</a>
        </div>
    </form>

    <?php if ($placementId > 0): ?>
        <form method="post" action="<?php echo $esc($listUrl); ?>" class="pr-danger-zone">
            <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
            <input type="hidden" name="action" value="bulk_placements">
            <button type="submit" name="single_delete" value="<?php echo $placementId; ?>" class="btn btn-outline-danger" data-cms-confirm="Platzierung löschen? Zugeordnete Promos bleiben ohne feste Platzierung erhalten."><i class="ti ti-trash" aria-hidden="true"></i> Platzierung löschen</button>
        </form>
    <?php endif; ?>

<?php else: ?>
    <?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $placements = is_array($placements ?? null) ? $placements : [];
    $hasFilter = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['hook'] ?? '') !== '';
    ?>
    <div class="admin-page-header">
        <div>
            <h2>Platzierungen</h2>
            <p>Einbauorte wie Hero, Sidebar oder CTA-Zonen und ihre automatische Ausgabe.</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-primary" href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Platzierung</a>
        </div>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo $esc($notice['message']); ?></div>
    <?php endif; ?>

    <form method="get" action="<?php echo $esc($listUrl); ?>" class="pr-toolbar toolbar" role="search">
        <label class="visually-hidden" for="placement-filter-q">Suche</label>
        <input id="placement-filter-q" type="search" name="q" class="form-control" placeholder="Name, Slug, Beschreibung …" value="<?php echo $esc($filters['q'] ?? ''); ?>">
        <label class="visually-hidden" for="placement-filter-status">Status</label>
        <select id="placement-filter-status" name="status" class="form-select">
            <option value="">Alle Status</option>
            <option value="active"<?php echo ($filters['status'] ?? '') === 'active' ? ' selected' : ''; ?>>Aktiv</option>
            <option value="inactive"<?php echo ($filters['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>>Inaktiv</option>
        </select>
        <label class="visually-hidden" for="placement-filter-hook">Theme-Hook</label>
        <select id="placement-filter-hook" name="hook" class="form-select">
            <option value="">Alle Hooks</option>
            <?php foreach ($hookOptions as $key => $label): ?>
                <option value="<?php echo $esc($key); ?>"<?php echo ($filters['hook'] ?? '') === $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($hasFilter): ?>
            <a class="btn btn-link" href="<?php echo $esc($listUrl); ?>">Zurücksetzen</a>
        <?php endif; ?>
    </form>

    <section class="admin-card pr-section">
        <?php if ($placements === []): ?>
            <div class="empty-state"><p><strong><?php echo $hasFilter ? 'Keine Platzierungen für diese Filter' : 'Noch keine Platzierungen vorhanden'; ?></strong></p></div>
        <?php else: ?>
            <form method="post" action="<?php echo $esc($listUrl); ?>" class="pr-bulk-form" data-pr-bulk>
                <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="action" value="bulk_placements">
                <input type="hidden" name="return_query" value="<?php echo $esc(CMS_Promos_Admin_Pages::list_query($filters)); ?>">

                <div class="pr-bulk-bar">
                    <label class="visually-hidden" for="placement-bulk-action">Sammelaktion</label>
                    <select id="placement-bulk-action" name="bulk_action" class="form-select form-select-sm">
                        <option value="">Sammelaktion wählen …</option>
                        <option value="activate">Aktivieren</option>
                        <option value="deactivate">Deaktivieren</option>
                        <option value="delete">Löschen</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary" data-pr-bulk-apply data-cms-confirm="Sammelaktion auf die markierten Platzierungen anwenden?">Anwenden</button>
                    <span class="pr-meta"><?php echo count($placements); ?> Platzierung(en)</span>
                </div>

                <div class="users-table-container">
                    <table class="users-table pr-table">
                        <thead>
                        <tr>
                            <th scope="col" class="pr-table__check"><input type="checkbox" class="form-check-input" data-pr-select-all aria-label="Alle markieren"></th>
                            <th scope="col">Name</th>
                            <th scope="col">Theme-Hook</th>
                            <th scope="col" class="pr-num">Max.</th>
                            <th scope="col" class="pr-num">Laufend / gesamt</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="pr-table__actions">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($placements as $item): ?>
                            <?php $id = (int) $item['id']; $active = ($item['status'] ?? '') === 'active'; ?>
                            <tr>
                                <td class="pr-table__check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo $id; ?>" data-pr-select aria-label="<?php echo $esc('Platzierung „' . (string) ($item['name'] ?? '') . '“ markieren'); ?>"></td>
                                <td>
                                    <a href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements', ['edit' => $id])); ?>"><?php echo $esc($item['name'] ?? ''); ?></a>
                                    <span class="pr-meta"><?php echo $esc($item['slug'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <?php echo $esc($repository->get_theme_hook_label((string) ($item['theme_hook'] ?? 'manual'))); ?>
                                    <span class="pr-meta">Priorität <?php echo (int) ($item['hook_priority'] ?? 10); ?></span>
                                </td>
                                <td class="pr-num"><?php echo (int) ($item['max_items'] ?? 0); ?></td>
                                <td class="pr-num"><?php echo (int) ($item['live_count'] ?? 0); ?> / <?php echo (int) ($item['promo_count'] ?? 0); ?></td>
                                <td><span class="badge bg-<?php echo $active ? 'green' : 'secondary'; ?>-lt"><?php echo $active ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                                <td class="pr-table__actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-placements', ['edit' => $id])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc('/promos/placement/' . rawurlencode((string) ($item['slug'] ?? ''))); ?>" target="_blank" rel="noopener noreferrer" title="Öffentlich ansehen"><i class="ti ti-external-link" aria-hidden="true"></i><span class="visually-hidden">Öffentlich ansehen</span></a>
                                    <button type="submit" name="single_delete" value="<?php echo $id; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" data-cms-confirm="<?php echo $esc('Platzierung „' . (string) ($item['name'] ?? '') . '“ löschen? Zugeordnete Promos bleiben erhalten.'); ?>"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

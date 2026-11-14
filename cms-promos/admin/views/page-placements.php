<?php
/**
 * Platzierungen: Liste bzw. Bearbeitungsformular.
 *
 * @var CMS_Promos_Repository     $repository
 * @var array<string,mixed>|null  $placement
 * @var bool                      $showForm
 * @var list<array<string,mixed>> $placements
 * @var array<string,string>      $hookOptions
 * @var string                    $csrfToken
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Promos_Admin_Pages::page_url('promos-placements');
?>
<?php if (!$showForm): ?>
<div class="admin-page-header">
    <div>
        <h2>Platzierungen</h2>
        <p>Einbauorte wie Hero, Content-Highlights oder Footer-Banner. Aktive Platzierungen mit Theme-Hook spielen ihre Promos automatisch aus.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-primary" href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-placements', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Platzierung</a>
    </div>
</div>

<div class="admin-card">
    <?php if ($placements === []): ?>
        <div class="empty-state"><p><strong>Keine Platzierungen vorhanden</strong></p><p class="pr-muted">Ohne Platzierung erscheinen Promos nur im Archiv /promos.</p></div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Theme-Hook</th><th scope="col" class="pr-num">Max.</th><th scope="col" class="pr-num">Promos (aktiv)</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($placements as $item): ?>
                    <?php $id = (int) $item['id']; $active = ($item['status'] ?? 'active') === 'active'; ?>
                    <tr>
                        <td>
                            <a href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-placements', ['edit' => $id])); ?>" class="pr-admin-link"><?php echo $e($item['name']); ?></a>
                            <div class="pr-table-meta"><?php echo $e($item['slug']); ?></div>
                        </td>
                        <td><?php echo $e($repository->get_theme_hook_label((string) ($item['theme_hook'] ?? 'manual'))); ?><div class="pr-table-meta">Priorität <?php echo (int) ($item['hook_priority'] ?? 10); ?></div></td>
                        <td class="pr-num"><?php echo (int) ($item['max_items'] ?? 0); ?></td>
                        <td class="pr-num"><a href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-items', ['placement' => $id])); ?>"><?php echo (int) ($item['promo_count'] ?? 0); ?> (<?php echo (int) ($item['active_count'] ?? 0); ?>)</a></td>
                        <td><span class="pr-soft-badge pr-soft-badge--<?php echo $active ? 'success' : 'muted'; ?>"><?php echo $active ? 'Aktiv' : 'Inaktiv'; ?></span></td>
                        <td>
                            <div class="pr-action-group">
                                <a href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-placements', ['edit' => $id])); ?>" class="btn btn-sm btn-outline-secondary" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <a href="<?php echo $e('/promos/placement/' . rawurlencode((string) $item['slug'])); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" title="Öffentlich ansehen"><i class="ti ti-external-link" aria-hidden="true"></i><span class="visually-hidden">Öffentlich ansehen (neuer Tab)</span></a>
                                <form method="post" data-cms-confirm="Platzierung „<?php echo $e($item['name']); ?>“ löschen? Zugeordnete Promos bleiben ohne Platzierung erhalten.">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_placement">
                                    <input type="hidden" name="placement_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="admin-page-header">
    <div>
        <h2><?php echo $placement ? 'Platzierung bearbeiten' : 'Neue Platzierung'; ?></h2>
        <p>Slug, Beschreibung, Kapazität und automatische Einbindung über einen Theme-Hook.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?php echo $e($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
    </div>
</div>

<div class="admin-card">
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="action" value="save_placement">
        <input type="hidden" name="placement_id" value="<?php echo (int) ($placement['id'] ?? 0); ?>">

        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label required" for="pl-name">Name</label><input id="pl-name" type="text" name="name" class="form-control" required maxlength="180" value="<?php echo $e($placement['name'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="pl-slug">Slug</label><input id="pl-slug" type="text" name="slug" class="form-control" maxlength="120" pattern="[a-z0-9\-]*" value="<?php echo $e($placement['slug'] ?? ''); ?>"></div>
        </div>
        <div class="form-group"><label class="form-label" for="pl-description">Beschreibung</label><textarea id="pl-description" name="description" class="form-control" rows="3" maxlength="2000"><?php echo $e($placement['description'] ?? ''); ?></textarea><div class="form-hint">Wird öffentlich als Einleitung der Fläche angezeigt.</div></div>
        <div class="pr-form-grid pr-form-grid--3">
            <div class="form-group">
                <label class="form-label" for="pl-hook">Theme-Hook</label>
                <select id="pl-hook" name="theme_hook" class="form-select">
                    <?php foreach ($hookOptions as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo (($placement['theme_hook'] ?? 'manual') === $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label" for="pl-priority">Hook-Priorität</label><input id="pl-priority" type="number" name="hook_priority" class="form-control" min="-100" max="1000" value="<?php echo (int) ($placement['hook_priority'] ?? 10); ?>"><div class="form-hint">Kleinere Werte zuerst.</div></div>
            <div class="form-group"><label class="form-label" for="pl-max">Maximale Promos</label><input id="pl-max" type="number" name="max_items" class="form-control" min="1" max="24" value="<?php echo (int) ($placement['max_items'] ?? 3); ?>"></div>
            <div class="form-group">
                <label class="form-label" for="pl-status">Status</label>
                <select id="pl-status" name="status" class="form-select">
                    <option value="active" <?php echo (($placement['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Aktiv</option>
                    <option value="inactive" <?php echo (($placement['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inaktiv</option>
                </select>
            </div>
        </div>

        <div class="pr-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Platzierung speichern</button>
            <a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Abbrechen</a>
        </div>
    </form>
</div>
<?php endif; ?>

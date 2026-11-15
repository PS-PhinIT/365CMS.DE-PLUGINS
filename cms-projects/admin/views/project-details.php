<?php
/**
 * Projekt-Stammdaten (Anlegen/Bearbeiten).
 *
 * @var CMS_Projects_Service $service
 * @var array<string,mixed>|null $project
 * @var string $csrfToken
 *
 * @package CMS_Projects
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$values = $project ?? $service->getProjectDefaults();
$projectStatuses = $service->getProjectStatuses();
$projectVisibilities = $service->getProjectVisibilities();
?>
<form method="post" class="admin-form cp-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="cms_projects_action" value="save_project">
    <input type="hidden" name="project_id" value="<?php echo (int) ($project['id'] ?? 0); ?>">

    <div class="admin-card">
        <div class="cp-panel-header"><h3>Projektkern</h3></div>
        <div class="cp-form-grid">
            <div class="form-group"><label class="form-label required" for="cp-name">Name</label><input id="cp-name" class="form-control" type="text" name="name" maxlength="190" required placeholder="z. B. Kundenportal 2026" value="<?php echo $e($values['name'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="cp-slug">Slug</label><input id="cp-slug" class="form-control" type="text" name="slug" maxlength="120" pattern="[a-z0-9\-_]*" placeholder="kundenportal-2026" value="<?php echo $e($values['slug'] ?? ''); ?>"><div class="form-hint">Leer lassen, um ihn aus dem Namen zu erzeugen. Öffentliche URL: /projects/&lt;slug&gt;</div></div>
            <div class="form-group"><label class="form-label" for="cp-owner">Owner / Lead</label><input id="cp-owner" class="form-control" type="text" name="owner_name" maxlength="190" placeholder="Projektleitung oder Team" value="<?php echo $e($values['owner_name'] ?? ''); ?>"></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="cp-panel-header"><h3>Status &amp; Darstellung</h3></div>
        <div class="cp-form-grid">
            <div class="form-group">
                <label class="form-label" for="cp-status">Status</label>
                <select id="cp-status" class="form-select" name="status">
                    <?php foreach ($projectStatuses as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo (($values['status'] ?? 'active') === $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="cp-visibility">Sichtbarkeit</label>
                <select id="cp-visibility" class="form-select" name="visibility">
                    <?php foreach ($projectVisibilities as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo (($values['visibility'] ?? 'member') === $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Öffentlich = Member + /projects; Member = nur angemeldete Mitglieder; Privat = nur Admin.</div>
            </div>
            <div class="form-group"><label class="form-label" for="cp-accent">Akzentfarbe</label><input id="cp-accent" class="form-control form-control-color" type="color" name="accent_color" value="<?php echo $e($values['accent_color'] ?? '#2563eb'); ?>"><div class="form-hint">Für Hero, Badges und Kartenakzente.</div></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="cp-panel-header"><h3>Headlines &amp; Inhalt</h3></div>
        <div class="cp-form-grid">
            <div class="form-group"><label class="form-label" for="cp-member-headline">Member-Headline</label><input id="cp-member-headline" class="form-control" type="text" name="member_headline" maxlength="190" value="<?php echo $e($values['member_headline'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="cp-public-headline">Public-Headline</label><input id="cp-public-headline" class="form-control" type="text" name="public_headline" maxlength="190" value="<?php echo $e($values['public_headline'] ?? ''); ?>"></div>
        </div>
        <div class="form-group"><label class="form-label" for="cp-summary">Zusammenfassung</label><textarea id="cp-summary" class="form-control" name="summary" rows="3" maxlength="1200" placeholder="Kurze Executive Summary für Listen, Suche und Hero."><?php echo $e($values['summary'] ?? ''); ?></textarea></div>
        <div class="form-group"><label class="form-label" for="cp-description">Beschreibung</label><textarea id="cp-description" class="form-control" name="description" rows="7" maxlength="20000" placeholder="Projektkontext, Ziele, Status und nächste Schritte."><?php echo $e($values['description'] ?? ''); ?></textarea></div>
    </div>

    <div class="cp-form-actions">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Projekt speichern</button>
        <a class="btn btn-link" href="<?php echo $e(CMS_Projects_Admin::pageUrl('cms-projects-projects')); ?>">Abbrechen</a>
    </div>
</form>

<?php if ($project !== null): ?>
<div class="admin-card cp-danger-zone">
    <div class="cp-panel-header"><h3>Projekt löschen</h3></div>
    <p class="cp-muted">Entfernt das Projekt mit <?php echo (int) ($project['board_count'] ?? 0); ?> Board(s), <?php echo (int) ($project['widget_count'] ?? 0); ?> Widget(s) und <?php echo (int) ($project['task_count'] ?? 0); ?> Ticket(s). Vorher ggf. als JSON exportieren.</p>
    <form method="post" data-cms-confirm="Projekt „<?php echo $e($project['name']); ?>“ inkl. aller Inhalte endgültig löschen?">
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="cms_projects_action" value="delete_project">
        <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="ti ti-trash" aria-hidden="true"></i> Projekt löschen</button>
    </form>
</div>
<?php endif; ?>

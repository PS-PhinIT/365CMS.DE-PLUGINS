<?php
/**
 * Promos: Liste mit Filter-Toolbar bzw. Bearbeitungsformular.
 *
 * @var CMS_Promos_Repository             $repository
 * @var array<string,mixed>|null          $promo
 * @var bool                              $showForm
 * @var list<array<string,mixed>>         $promos
 * @var list<array<string,mixed>>         $placements
 * @var array<string,string>              $settings
 * @var array{status:string,q:string,placement:int} $filters
 * @var array<string,string>              $statusLabels
 * @var string                            $csrfToken
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dt = static function (mixed $value): string {
    $timestamp = !empty($value) ? strtotime((string) $value) : false;
    return $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : '';
};
$listUrl = CMS_Promos_Admin_Pages::page_url('promos-items');
?>
<?php if (!$showForm): ?>
<div class="admin-page-header">
    <div>
        <h2>Promos</h2>
        <p>CTA-Kacheln, Banner und Kampagnenziele mit Priorität, Laufzeit, Frequency-Cap und UTM-Parametern.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Promos_Admin_Pages::export_url('csv')); ?>"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV</a>
        <a class="btn btn-outline-secondary" href="<?php echo $e(CMS_Promos_Admin_Pages::export_url('json')); ?>"><i class="ti ti-braces" aria-hidden="true"></i> JSON</a>
        <a class="btn btn-primary" href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-items', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Promo</a>
    </div>
</div>

<form method="get" action="<?php echo $e($listUrl); ?>" class="pr-toolbar" role="search" aria-label="Promos filtern">
    <div class="pr-toolbar__field">
        <label class="form-label" for="pr-filter-q">Suche</label>
        <input id="pr-filter-q" type="search" name="q" class="form-control" value="<?php echo $e($filters['q']); ?>" placeholder="Titel, Slug oder Teaser">
    </div>
    <div class="pr-toolbar__field">
        <label class="form-label" for="pr-filter-status">Status</label>
        <select id="pr-filter-status" name="status" class="form-select">
            <option value="">Alle</option>
            <option value="live" <?php echo $filters['status'] === 'live' ? 'selected' : ''; ?>>Aktuell live</option>
            <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>Abgelaufen (noch aktiv)</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?php echo $e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pr-toolbar__field">
        <label class="form-label" for="pr-filter-placement">Platzierung</label>
        <select id="pr-filter-placement" name="placement" class="form-select">
            <option value="0">Alle</option>
            <?php foreach ($placements as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo $filters['placement'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo $e($item['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pr-toolbar__actions">
        <button type="submit" class="btn btn-secondary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['placement'] > 0): ?>
            <a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Zurücksetzen</a>
        <?php endif; ?>
    </div>
</form>

<div class="admin-card">
    <?php if ($promos === []): ?>
        <div class="empty-state">
            <p><strong><?php echo ($filters['q'] !== '' || $filters['status'] !== '' || $filters['placement'] > 0) ? 'Keine Promos für diesen Filter' : 'Noch keine Promos vorhanden'; ?></strong></p>
            <p class="pr-muted">Lege eine Promo mit Ziel-URL und Platzierung an.</p>
        </div>
    <?php else: ?>
        <div class="users-table-container">
            <table class="users-table">
                <thead><tr><th scope="col">Titel</th><th scope="col">Platzierung</th><th scope="col">Status</th><th scope="col">Laufzeit</th><th scope="col" class="pr-num">Impr. / Klicks</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead>
                <tbody>
                <?php foreach ($promos as $item): ?>
                    <?php
                    $id = (int) $item['id'];
                    $status = (string) ($item['status'] ?? 'draft');
                    $expired = $status === 'active' && !empty($item['end_at']) && strtotime((string) $item['end_at']) < time();
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-items', ['edit' => $id])); ?>" class="pr-admin-link"><?php echo $e($item['title']); ?></a>
                            <?php if (!empty($item['is_featured'])): ?><span class="pr-soft-badge pr-soft-badge--featured">Featured</span><?php endif; ?>
                            <div class="pr-table-meta"><?php echo $e($item['slug']); ?> · Priorität <?php echo (int) ($item['priority'] ?? 0); ?></div>
                        </td>
                        <td>
                            <?php echo $e($item['placement_name'] ?? '–'); ?>
                            <?php if (!empty($item['placement_theme_hook'])): ?>
                                <div class="pr-table-meta"><?php echo $e($repository->get_theme_hook_label((string) $item['placement_theme_hook'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pr-soft-badge pr-soft-badge--<?php echo $e(CMS_Promos_Admin_Pages::status_tone($status)); ?>"><?php echo $e($repository->status_label($status)); ?></span>
                            <?php if ($expired): ?><span class="pr-soft-badge pr-soft-badge--warning">abgelaufen</span><?php endif; ?>
                        </td>
                        <td class="pr-table-meta"><?php echo $e(!empty($item['start_at']) ? CMS_Promos_Admin_Pages::format_date((string) $item['start_at']) : 'sofort'); ?> – <?php echo $e(!empty($item['end_at']) ? CMS_Promos_Admin_Pages::format_date((string) $item['end_at']) : 'offen'); ?></td>
                        <td class="pr-num"><?php echo number_format((int) ($item['impression_count'] ?? 0), 0, ',', '.'); ?> / <?php echo number_format((int) ($item['click_count'] ?? 0), 0, ',', '.'); ?></td>
                        <td>
                            <div class="pr-action-group">
                                <a href="<?php echo $e(CMS_Promos_Admin_Pages::page_url('promos-items', ['edit' => $id])); ?>" class="btn btn-sm btn-outline-secondary" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="action" value="duplicate_promo">
                                    <input type="hidden" name="promo_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Duplizieren"><i class="ti ti-copy" aria-hidden="true"></i><span class="visually-hidden">Duplizieren</span></button>
                                </form>
                                <form method="post" data-cms-confirm="Promo „<?php echo $e($item['title']); ?>“ endgültig löschen?">
                                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_promo">
                                    <input type="hidden" name="promo_id" value="<?php echo $id; ?>">
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
        <h2><?php echo $promo ? 'Promo bearbeiten' : 'Neue Promo'; ?></h2>
        <p>Titel, CTA-Ziel, Platzierung, Laufzeit und Tracking in einem Schritt festlegen.</p>
    </div>
    <div class="header-actions">
        <?php if ($promo): ?>
            <form method="post" data-cms-confirm="Impressions und Klicks dieser Promo auf 0 setzen?">
                <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                <input type="hidden" name="action" value="reset_promo_stats">
                <input type="hidden" name="promo_id" value="<?php echo (int) $promo['id']; ?>">
                <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-refresh" aria-hidden="true"></i> Statistik zurücksetzen</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?php echo $e($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
    </div>
</div>

<form method="post" class="admin-form" id="promo-form">
    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
    <input type="hidden" name="action" value="save_promo">
    <input type="hidden" name="promo_id" value="<?php echo (int) ($promo['id'] ?? 0); ?>">

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Inhalt</h3></div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label required" for="pr-title">Titel</label><input id="pr-title" type="text" name="title" class="form-control" required maxlength="180" value="<?php echo $e($promo['title'] ?? ''); ?>"></div>
            <div class="form-group"><label class="form-label" for="pr-title-en">Titel (EN)</label><input id="pr-title-en" type="text" name="title_en" class="form-control" maxlength="180" value="<?php echo $e($promo['title_en'] ?? ''); ?>"></div>
        </div>
        <div class="form-group"><label class="form-label" for="pr-slug">Slug</label><input id="pr-slug" type="text" name="slug" class="form-control" maxlength="120" pattern="[a-z0-9\-]*" value="<?php echo $e($promo['slug'] ?? ''); ?>"><div class="form-hint">Leer lassen, um ihn aus dem Titel zu erzeugen. Klick-URL: /promo/click/&lt;slug&gt;</div></div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label" for="pr-teaser">Teaser</label><textarea id="pr-teaser" name="teaser" class="form-control" rows="3" maxlength="255"><?php echo $e($promo['teaser'] ?? ''); ?></textarea></div>
            <div class="form-group"><label class="form-label" for="pr-teaser-en">Teaser (EN)</label><textarea id="pr-teaser-en" name="teaser_en" class="form-control" rows="3" maxlength="255"><?php echo $e($promo['teaser_en'] ?? ''); ?></textarea></div>
        </div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label" for="pr-content">Zusatztext</label><textarea id="pr-content" name="content_html" class="form-control" rows="5"><?php echo $e($promo['content_html'] ?? ''); ?></textarea><div class="form-hint">Erlaubt: p, a, strong, em, ul, ol, li, br, h2–h4, span.</div></div>
            <div class="form-group"><label class="form-label" for="pr-content-en">Zusatztext (EN)</label><textarea id="pr-content-en" name="content_html_en" class="form-control" rows="5"><?php echo $e($promo['content_html_en'] ?? ''); ?></textarea></div>
        </div>
        <div class="form-group"><label class="form-label" for="pr-image">Bild-URL</label><input id="pr-image" type="url" name="image_url" class="form-control" value="<?php echo $e($promo['image_url'] ?? ''); ?>" placeholder="https://"></div>
    </div>

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Ziel &amp; Tracking</h3></div>
        <div class="form-group"><label class="form-label" for="promo-target-url">Ziel-URL</label><input id="promo-target-url" type="url" name="target_url" class="form-control" value="<?php echo $e($promo['target_url'] ?? ''); ?>" placeholder="https://"><div class="form-hint">Nur öffentliche http(s)-Adressen. Klicks werden über /promo/click/&lt;slug&gt; gezählt.</div></div>
        <div class="pr-form-grid">
            <div class="form-group"><label class="form-label" for="pr-button">Button-Label</label><input id="pr-button" type="text" name="button_label" class="form-control" maxlength="80" value="<?php echo $e($promo['button_label'] ?? ($settings['default_button_label'] ?? 'Mehr erfahren')); ?>"></div>
            <div class="form-group"><label class="form-label" for="pr-button-en">Button-Label (EN)</label><input id="pr-button-en" type="text" name="button_label_en" class="form-control" maxlength="80" value="<?php echo $e($promo['button_label_en'] ?? ($settings['default_button_label_en'] ?? '')); ?>"></div>
        </div>
        <fieldset class="pr-fieldset">
            <legend>UTM-Parameter</legend>
            <div class="pr-form-grid pr-form-grid--3">
                <div class="form-group"><label class="form-label" for="promo-utm-source">Source</label><input id="promo-utm-source" type="text" name="utm_source" class="form-control" maxlength="80" value="<?php echo $e($promo['utm_source'] ?? ''); ?>" placeholder="newsletter"></div>
                <div class="form-group"><label class="form-label" for="promo-utm-medium">Medium</label><input id="promo-utm-medium" type="text" name="utm_medium" class="form-control" maxlength="80" value="<?php echo $e($promo['utm_medium'] ?? ''); ?>" placeholder="email"></div>
                <div class="form-group"><label class="form-label" for="promo-utm-campaign">Campaign</label><input id="promo-utm-campaign" type="text" name="utm_campaign" class="form-control" maxlength="120" value="<?php echo $e($promo['utm_campaign'] ?? ''); ?>" placeholder="sommer-aktion"></div>
                <div class="form-group"><label class="form-label" for="promo-utm-term">Term</label><input id="promo-utm-term" type="text" name="utm_term" class="form-control" maxlength="120" value="<?php echo $e($promo['utm_term'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="promo-utm-content">Content</label><input id="promo-utm-content" type="text" name="utm_content" class="form-control" maxlength="120" value="<?php echo $e($promo['utm_content'] ?? ''); ?>" placeholder="cta-button"></div>
            </div>
            <div class="pr-utm-preview">
                <button id="promo-build-utm" type="button" class="btn btn-sm btn-outline-secondary"><i class="ti ti-link" aria-hidden="true"></i> Vorschau der Ziel-URL</button>
                <button id="promo-copy-utm" type="button" class="btn btn-sm btn-outline-secondary" hidden><i class="ti ti-copy" aria-hidden="true"></i> Kopieren</button>
                <output id="promo-utm-output" class="pr-utm-preview__output" for="promo-target-url promo-utm-source promo-utm-medium promo-utm-campaign promo-utm-term promo-utm-content"></output>
            </div>
            <div class="form-hint">Die UTM-Werte werden beim Klick automatisch an die Ziel-URL angehängt; bereits vorhandene Parameter haben Vorrang.</div>
        </fieldset>
    </div>

    <div class="admin-card">
        <div class="pr-panel-header"><h3>Ausspielung</h3></div>
        <div class="pr-form-grid">
            <div class="form-group">
                <label class="form-label" for="pr-placement">Platzierung</label>
                <select id="pr-placement" name="placement_id" class="form-select">
                    <option value="0">Keine feste Platzierung (nur Archiv)</option>
                    <?php foreach ($placements as $item): ?>
                        <option value="<?php echo (int) $item['id']; ?>" <?php echo ((int) ($promo['placement_id'] ?? 0) === (int) $item['id']) ? 'selected' : ''; ?>><?php echo $e($item['name']); ?> – <?php echo $e($repository->get_theme_hook_label((string) ($item['theme_hook'] ?? 'manual'))); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="pr-status">Status</label>
                <select id="pr-status" name="status" class="form-select">
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?php echo $e($key); ?>" <?php echo (($promo['status'] ?? 'draft') === $key) ? 'selected' : ''; ?>><?php echo $e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="pr-form-grid pr-form-grid--3">
            <div class="form-group"><label class="form-label" for="pr-start">Start</label><input id="pr-start" type="datetime-local" name="start_at" class="form-control" value="<?php echo $e($dt($promo['start_at'] ?? '')); ?>"></div>
            <div class="form-group"><label class="form-label" for="pr-end">Ende</label><input id="pr-end" type="datetime-local" name="end_at" class="form-control" value="<?php echo $e($dt($promo['end_at'] ?? '')); ?>"></div>
            <div class="form-group"><label class="form-label" for="pr-priority">Priorität</label><input id="pr-priority" type="number" name="priority" class="form-control" min="0" max="9999" value="<?php echo (int) ($promo['priority'] ?? 0); ?>"></div>
            <div class="form-group"><label class="form-label" for="pr-cap">Frequency-Cap je Besucher</label><input id="pr-cap" type="number" name="frequency_cap" class="form-control" min="0" max="50" value="<?php echo (int) ($promo['frequency_cap'] ?? 0); ?>"><div class="form-hint">0 = unbegrenzt</div></div>
            <div class="form-group"><label class="form-label" for="pr-window">Cap-Zeitfenster (Stunden)</label><input id="pr-window" type="number" name="frequency_window_hours" class="form-control" min="1" max="336" value="<?php echo max(1, (int) ($promo['frequency_window_hours'] ?? 24)); ?>"></div>
        </div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" <?php echo !empty($promo['is_featured']) ? 'checked' : ''; ?>> <span class="form-check-label">Als Featured-Promo hervorheben (wird zuerst ausgespielt)</span></label>
    </div>

    <div class="pr-form-actions">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Promo speichern</button>
        <a class="btn btn-link" href="<?php echo $e($listUrl); ?>">Abbrechen</a>
    </div>
</form>
<?php endif; ?>

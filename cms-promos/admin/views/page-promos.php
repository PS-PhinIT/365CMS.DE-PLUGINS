<?php
/**
 * Promos: Liste (Filter, Sammelaktionen, Pagination) bzw. Formular (?action=new / ?edit=ID).
 *
 * @var CMS_Promos_Repository $repository
 * @var list<array<string,mixed>> $placements
 * @var array{type:string,message:string}|null $notice
 * @var string $csrfToken
 * @var string $exportToken
 * @var array<string,string> $statusLabels
 * @var bool $isForm
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$esc = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$listUrl = CMS_Promos_Admin_Pages::url('promos-items');
$dateValue = static function (mixed $value): string {
    $value = is_scalar($value) ? trim((string) $value) : '';
    $ts = $value !== '' ? strtotime($value) : false;

    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
};
?>

<?php if ($isForm): ?>
    <?php
    $promo = is_array($promo ?? null) ? $promo : [];
    $promoId = (int) ($promo['id'] ?? 0);
    $settings = is_array($settings ?? null) ? $settings : [];
    $selectedStatus = (string) ($promo['status'] ?? 'draft');
    $selectedPlacement = (int) ($promo['placement_id'] ?? 0);
    ?>
    <div class="admin-page-header">
        <div>
            <h2><?php echo $promoId > 0 ? 'Promo bearbeiten' : 'Neue Promo'; ?></h2>
            <p>Titel, CTA-Ziel, Platzierung und Laufzeit einer Kampagne festlegen.</p>
        </div>
        <div class="header-actions">
            <a class="btn btn-outline-secondary" href="<?php echo $esc($listUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Zur Liste</a>
        </div>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo $esc($notice['message']); ?></div>
    <?php endif; ?>

    <?php if ($promoId > 0 && isset($promo['impression_count'])): ?>
        <p class="pr-meta pr-meta--block">
            Slug: <code><?php echo $esc($promo['slug'] ?? ''); ?></code> ·
            <?php echo number_format((int) ($promo['impression_count'] ?? 0), 0, ',', '.'); ?> Impressions ·
            <?php echo number_format((int) ($promo['click_count'] ?? 0), 0, ',', '.'); ?> Klicks ·
            zuletzt geändert <?php echo $esc(CMS_Promos_Admin_Pages::format_datetime((string) ($promo['updated_at'] ?? ''))); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-items', $promoId > 0 ? ['edit' => $promoId] : ['action' => 'new'])); ?>" class="admin-form pr-form">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="action" value="save_promo">
        <input type="hidden" name="promo_id" value="<?php echo $promoId; ?>">

        <section class="admin-card pr-section">
            <div class="pr-section__head"><div><h3>Inhalt</h3><p>Texte für Deutsch und optional Englisch (leere EN-Felder nutzen den deutschen Text).</p></div></div>
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="promo-title">Titel</label><input id="promo-title" type="text" name="title" class="form-control" required maxlength="180" value="<?php echo $esc($promo['title'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="promo-title-en">Titel (EN)</label><input id="promo-title-en" type="text" name="title_en" class="form-control" maxlength="180" value="<?php echo $esc($promo['title_en'] ?? ''); ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="promo-slug">Slug</label><input id="promo-slug" type="text" name="slug" class="form-control" maxlength="120" pattern="[a-z0-9\-]*" value="<?php echo $esc($promo['slug'] ?? ''); ?>"><div class="form-help">Leer lassen, um ihn aus dem Titel zu erzeugen. Teil der Klick-URL <code>/promo/click/&lt;slug&gt;</code>.</div></div>
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="promo-teaser">Teaser</label><textarea id="promo-teaser" name="teaser" class="form-control" rows="3" maxlength="255"><?php echo $esc($promo['teaser'] ?? ''); ?></textarea></div>
                <div class="form-group"><label class="form-label" for="promo-teaser-en">Teaser (EN)</label><textarea id="promo-teaser-en" name="teaser_en" class="form-control" rows="3" maxlength="255"><?php echo $esc($promo['teaser_en'] ?? ''); ?></textarea></div>
            </div>
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="promo-content">Zusatztext</label><textarea id="promo-content" name="content_html" class="form-control" rows="6"><?php echo $esc($promo['content_html'] ?? ''); ?></textarea></div>
                <div class="form-group"><label class="form-label" for="promo-content-en">Zusatztext (EN)</label><textarea id="promo-content-en" name="content_html_en" class="form-control" rows="6"><?php echo $esc($promo['content_html_en'] ?? ''); ?></textarea></div>
            </div>
            <div class="form-help">Erlaubt: &lt;p&gt;, &lt;a href&gt;, &lt;strong&gt;, &lt;em&gt;, Listen, &lt;br&gt;, &lt;h2&gt;–&lt;h4&gt;, &lt;span&gt;. Weitere Attribute werden entfernt.</div>
        </section>

        <section class="admin-card pr-section">
            <div class="pr-section__head"><div><h3>Ziel &amp; Darstellung</h3><p>Der Button führt über die Klick-URL (Zählung, UTM-Parameter) auf das Ziel.</p></div></div>
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="promo-target-url">Ziel-URL</label><input id="promo-target-url" type="url" name="target_url" class="form-control" maxlength="500" placeholder="https://" value="<?php echo $esc($promo['target_url'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="promo-image">Bild-URL</label><input id="promo-image" type="text" name="image_url" class="form-control" inputmode="url" maxlength="500" placeholder="https://… oder /uploads/…" value="<?php echo $esc($promo['image_url'] ?? ''); ?>"></div>
            </div>
            <div class="pr-form-grid">
                <div class="form-group"><label class="form-label" for="promo-button">Button-Text</label><input id="promo-button" type="text" name="button_label" class="form-control" maxlength="80" placeholder="<?php echo $esc($settings['default_button_label'] ?? 'Mehr erfahren'); ?>" value="<?php echo $esc($promo['button_label'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label" for="promo-button-en">Button-Text (EN)</label><input id="promo-button-en" type="text" name="button_label_en" class="form-control" maxlength="80" placeholder="<?php echo $esc($settings['default_button_label_en'] ?? 'Learn more'); ?>" value="<?php echo $esc($promo['button_label_en'] ?? ''); ?>"></div>
            </div>

            <fieldset class="pr-fieldset">
                <legend>UTM-Parameter</legend>
                <p class="pr-meta">Werden beim Klick an die Ziel-URL angehängt, sofern dort nicht bereits gesetzt.</p>
                <div class="pr-form-grid pr-form-grid--3">
                    <div class="form-group"><label class="form-label" for="promo-utm-source">Source</label><input id="promo-utm-source" type="text" name="utm_source" class="form-control" maxlength="80" placeholder="newsletter" value="<?php echo $esc($promo['utm_source'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label" for="promo-utm-medium">Medium</label><input id="promo-utm-medium" type="text" name="utm_medium" class="form-control" maxlength="80" placeholder="banner" value="<?php echo $esc($promo['utm_medium'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label" for="promo-utm-campaign">Campaign</label><input id="promo-utm-campaign" type="text" name="utm_campaign" class="form-control" maxlength="120" placeholder="sommer-aktion-2026" value="<?php echo $esc($promo['utm_campaign'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label" for="promo-utm-term">Term</label><input id="promo-utm-term" type="text" name="utm_term" class="form-control" maxlength="120" placeholder="optional" value="<?php echo $esc($promo['utm_term'] ?? ''); ?>"></div>
                    <div class="form-group"><label class="form-label" for="promo-utm-content">Content</label><input id="promo-utm-content" type="text" name="utm_content" class="form-control" maxlength="120" placeholder="cta-button" value="<?php echo $esc($promo['utm_content'] ?? ''); ?>"></div>
                    <div class="form-group pr-form-group--end"><button id="promo-build-utm" type="button" class="btn btn-outline-secondary"><i class="ti ti-link" aria-hidden="true"></i> In Ziel-URL übernehmen</button></div>
                </div>
            </fieldset>
        </section>

        <section class="admin-card pr-section">
            <div class="pr-section__head"><div><h3>Ausspielung</h3><p>Status, Platzierung, Laufzeit und Anzeige-Limit je Besucher.</p></div></div>
            <div class="pr-form-grid pr-form-grid--3">
                <div class="form-group">
                    <label class="form-label" for="promo-status">Status</label>
                    <select id="promo-status" name="status" class="form-select">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $esc($key); ?>"<?php echo $selectedStatus === $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="promo-placement">Platzierung</label>
                    <select id="promo-placement" name="placement_id" class="form-select">
                        <option value="0">Keine feste Platzierung (nur Archiv)</option>
                        <?php foreach ($placements as $placement): ?>
                            <option value="<?php echo (int) $placement['id']; ?>"<?php echo $selectedPlacement === (int) $placement['id'] ? ' selected' : ''; ?>><?php echo $esc($placement['name'] ?? ''); ?> – <?php echo $esc($repository->get_theme_hook_label((string) ($placement['theme_hook'] ?? 'manual'))); ?><?php echo ($placement['status'] ?? '') !== 'active' ? ' (inaktiv)' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="promo-priority">Priorität</label><input id="promo-priority" type="number" name="priority" class="form-control" min="0" max="100000" value="<?php echo (int) ($promo['priority'] ?? 0); ?>"><div class="form-help">Höhere Werte erscheinen zuerst.</div></div>
                <div class="form-group"><label class="form-label" for="promo-start">Start</label><input id="promo-start" type="datetime-local" name="start_at" class="form-control" value="<?php echo $esc($dateValue($promo['start_at'] ?? '')); ?>"></div>
                <div class="form-group"><label class="form-label" for="promo-end">Ende</label><input id="promo-end" type="datetime-local" name="end_at" class="form-control" value="<?php echo $esc($dateValue($promo['end_at'] ?? '')); ?>"></div>
                <div class="form-group pr-form-group--end"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1"<?php echo !empty($promo['is_featured']) ? ' checked' : ''; ?>> <span class="form-check-label">Als Featured hervorheben</span></label></div>
                <div class="form-group"><label class="form-label" for="promo-cap">Anzeige-Limit je Besucher</label><input id="promo-cap" type="number" name="frequency_cap" class="form-control" min="0" max="50" value="<?php echo (int) ($promo['frequency_cap'] ?? 0); ?>"><div class="form-help">0 = unbegrenzt.</div></div>
                <div class="form-group"><label class="form-label" for="promo-window">Zeitfenster (Stunden)</label><input id="promo-window" type="number" name="frequency_window_hours" class="form-control" min="1" max="336" value="<?php echo max(1, (int) ($promo['frequency_window_hours'] ?? 24)); ?>"></div>
            </div>
        </section>

        <div class="pr-form-actions">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> Promo speichern</button>
            <a class="btn btn-outline-secondary" href="<?php echo $esc($listUrl); ?>">Abbrechen</a>
        </div>
    </form>

    <?php if ($promoId > 0): ?>
        <form method="post" action="<?php echo $esc($listUrl); ?>" class="pr-danger-zone">
            <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
            <input type="hidden" name="action" value="bulk_promos">
            <button type="submit" name="single_delete" value="<?php echo $promoId; ?>" class="btn btn-outline-danger" data-cms-confirm="Diese Promo endgültig löschen?"><i class="ti ti-trash" aria-hidden="true"></i> Promo löschen</button>
        </form>
    <?php endif; ?>

<?php else: ?>
    <?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $list = is_array($list ?? null) ? $list : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    $returnQuery = CMS_Promos_Admin_Pages::list_query($filters, (int) $list['page']);
    $statusFilterOptions = ['' => 'Alle Status', 'live' => 'Läuft gerade', 'expired' => 'Laufzeit abgelaufen'] + $statusLabels;
    $sortOptions = ['' => 'Priorität', 'updated' => 'Zuletzt geändert', 'title' => 'Titel A–Z', 'clicks' => 'Meiste Klicks', 'impressions' => 'Meiste Impressions', 'end' => 'Endet zuerst'];
    $hasFilter = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['placement'] ?? '') !== '';
    ?>
    <div class="admin-page-header">
        <div>
            <h2>Promos</h2>
            <p>CTA-Kacheln, Banner und Kampagnen mit Status, Laufzeit und Leistung.</p>
        </div>
        <div class="header-actions">
            <form method="post" action="<?php echo $esc($listUrl); ?>" class="pr-inline-form">
                <input type="hidden" name="export_token" value="<?php echo $esc($exportToken); ?>">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i> CSV-Export</button>
            </form>
            <a class="btn btn-primary" href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-items', ['action' => 'new'])); ?>"><i class="ti ti-plus" aria-hidden="true"></i> Neue Promo</a>
        </div>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo $esc($notice['message']); ?></div>
    <?php endif; ?>

    <form method="get" action="<?php echo $esc($listUrl); ?>" class="pr-toolbar toolbar" role="search">
        <label class="visually-hidden" for="promo-filter-q">Suche</label>
        <input id="promo-filter-q" type="search" name="q" class="form-control" placeholder="Titel, Slug, Kampagne …" value="<?php echo $esc($filters['q'] ?? ''); ?>">
        <label class="visually-hidden" for="promo-filter-status">Status</label>
        <select id="promo-filter-status" name="status" class="form-select">
            <?php foreach ($statusFilterOptions as $key => $label): ?>
                <option value="<?php echo $esc($key); ?>"<?php echo ($filters['status'] ?? '') === (string) $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
            <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="promo-filter-placement">Platzierung</label>
        <select id="promo-filter-placement" name="placement" class="form-select">
            <option value="">Alle Platzierungen</option>
            <option value="none"<?php echo ($filters['placement'] ?? '') === 'none' ? ' selected' : ''; ?>>Ohne Platzierung</option>
            <?php foreach ($placements as $placement): ?>
                <option value="<?php echo (int) $placement['id']; ?>"<?php echo ($filters['placement'] ?? '') === (string) (int) $placement['id'] ? ' selected' : ''; ?>><?php echo $esc($placement['name'] ?? ''); ?></option>
            <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="promo-filter-sort">Sortierung</label>
        <select id="promo-filter-sort" name="sort" class="form-select">
            <?php foreach ($sortOptions as $key => $label): ?>
                <option value="<?php echo $esc($key); ?>"<?php echo ($filters['sort'] ?? '') === (string) $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary"><i class="ti ti-filter" aria-hidden="true"></i> Filtern</button>
        <?php if ($hasFilter): ?>
            <a class="btn btn-link" href="<?php echo $esc($listUrl); ?>">Zurücksetzen</a>
        <?php endif; ?>
    </form>

    <section class="admin-card pr-section">
        <?php if ($list['items'] === []): ?>
            <div class="empty-state">
                <p><strong><?php echo $hasFilter ? 'Keine Promos für diese Filter' : 'Noch keine Promos vorhanden'; ?></strong></p>
                <p><?php echo $hasFilter ? 'Filter anpassen oder zurücksetzen.' : 'Lege die erste Promo mit Ziel-URL und Platzierung an.'; ?></p>
            </div>
        <?php else: ?>
            <form method="post" action="<?php echo $esc($listUrl); ?>" class="pr-bulk-form" data-pr-bulk>
                <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
                <input type="hidden" name="action" value="bulk_promos">
                <input type="hidden" name="return_query" value="<?php echo $esc($returnQuery); ?>">

                <div class="pr-bulk-bar">
                    <label class="visually-hidden" for="promo-bulk-action">Sammelaktion</label>
                    <select id="promo-bulk-action" name="bulk_action" class="form-select form-select-sm">
                        <option value="">Sammelaktion wählen …</option>
                        <option value="activate">Aktivieren</option>
                        <option value="pause">Pausieren</option>
                        <option value="archive">Archivieren</option>
                        <option value="draft">Als Entwurf</option>
                        <option value="reset_stats">Zähler zurücksetzen</option>
                        <option value="delete">Löschen</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary" data-pr-bulk-apply data-cms-confirm="Sammelaktion auf die markierten Promos anwenden?">Anwenden</button>
                    <span class="pr-meta"><?php echo number_format((int) $list['total'], 0, ',', '.'); ?> Promo(s)</span>
                </div>

                <div class="users-table-container">
                    <table class="users-table pr-table">
                        <thead>
                        <tr>
                            <th scope="col" class="pr-table__check"><input type="checkbox" class="form-check-input" data-pr-select-all aria-label="Alle auf dieser Seite markieren"></th>
                            <th scope="col">Titel</th>
                            <th scope="col">Platzierung</th>
                            <th scope="col">Status</th>
                            <th scope="col">Laufzeit</th>
                            <th scope="col" class="pr-num">Prio.</th>
                            <th scope="col" class="pr-num">Impr. / Klicks</th>
                            <th scope="col" class="pr-table__actions">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list['items'] as $item): ?>
                            <?php
                            $id = (int) $item['id'];
                            $status = (string) ($item['status'] ?? 'draft');
                            $live = (int) ($item['is_live'] ?? 0) === 1;
                            $impressions = (int) ($item['impression_count'] ?? 0);
                            $clicks = (int) ($item['click_count'] ?? 0);
                            $start = CMS_Promos_Admin_Pages::format_datetime((string) ($item['start_at'] ?? ''));
                            $end = CMS_Promos_Admin_Pages::format_datetime((string) ($item['end_at'] ?? ''));
                            ?>
                            <tr>
                                <td class="pr-table__check"><input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo $id; ?>" data-pr-select aria-label="<?php echo $esc('Promo „' . (string) ($item['title'] ?? '') . '“ markieren'); ?>"></td>
                                <td>
                                    <a href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-items', ['edit' => $id])); ?>"><?php echo $esc($item['title'] ?? ''); ?></a>
                                    <?php if (!empty($item['is_featured'])): ?><span class="badge bg-purple-lt">Featured</span><?php endif; ?>
                                    <span class="pr-meta"><?php echo $esc($item['slug'] ?? ''); ?><?php echo trim((string) ($item['target_url'] ?? '')) === '' ? ' · ohne Ziel-URL' : ''; ?></span>
                                </td>
                                <td>
                                    <?php echo $esc((string) ($item['placement_name'] ?? '') !== '' ? $item['placement_name'] : '–'); ?>
                                    <?php if (!empty($item['placement_theme_hook'])): ?><span class="pr-meta"><?php echo $esc($repository->get_theme_hook_label((string) $item['placement_theme_hook'])); ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $esc(CMS_Promos_Admin_Pages::status_tone($status, $live)); ?>-lt"><?php echo $esc($statusLabels[$status] ?? $status); ?></span>
                                    <?php if ($status === 'active' && !$live): ?><span class="pr-meta">außerhalb der Laufzeit</span><?php endif; ?>
                                </td>
                                <td><span class="pr-meta pr-meta--inline"><?php echo $esc($start !== '' ? $start : 'sofort'); ?> – <?php echo $esc($end !== '' ? $end : 'offen'); ?></span></td>
                                <td class="pr-num"><?php echo (int) ($item['priority'] ?? 0); ?></td>
                                <td class="pr-num">
                                    <?php echo number_format($impressions, 0, ',', '.'); ?> / <?php echo number_format($clicks, 0, ',', '.'); ?>
                                    <span class="pr-meta">CTR <?php echo $impressions > 0 ? number_format($clicks / $impressions * 100, 1, ',', '.') : '0,0'; ?> %</span>
                                </td>
                                <td class="pr-table__actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc(CMS_Promos_Admin_Pages::url('promos-items', ['edit' => $id])); ?>" title="Bearbeiten"><i class="ti ti-pencil" aria-hidden="true"></i><span class="visually-hidden">Bearbeiten</span></a>
                                    <button type="submit" name="single_delete" value="<?php echo $id; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" data-cms-confirm="<?php echo $esc('Promo „' . (string) ($item['title'] ?? '') . '“ endgültig löschen?'); ?>"><i class="ti ti-trash" aria-hidden="true"></i><span class="visually-hidden">Löschen</span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ((int) $list['pages'] > 1): ?>
                <nav class="pr-pagination" aria-label="Seiten">
                    <?php for ($i = 1; $i <= (int) $list['pages']; $i++): ?>
                        <?php if ($i === (int) $list['page']): ?>
                            <span class="pr-pagination__item is-current" aria-current="page"><?php echo $i; ?></span>
                        <?php elseif ($i === 1 || $i === (int) $list['pages'] || abs($i - (int) $list['page']) <= 2): ?>
                            <?php $pageQuery = CMS_Promos_Admin_Pages::list_query($filters, $i); ?>
                            <a class="pr-pagination__item" href="<?php echo $esc($listUrl . ($pageQuery !== '' ? '?' . $pageQuery : '')); ?>"><?php echo $i; ?></a>
                        <?php elseif (abs($i - (int) $list['page']) === 3): ?>
                            <span class="pr-pagination__gap" aria-hidden="true">…</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

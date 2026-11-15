<?php
/**
 * CMS Beratung – zentrale M365-FAQs.
 *
 * @var array<string,mixed> $config
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$admin = CMS_Beratung_Admin_Pages::class;
$items = is_array($config['items'] ?? null) ? array_values($config['items']) : [];
$count = count($items);
$items[] = ['enabled' => true, 'question' => '', 'answer' => '', 'default_open' => false, 'sort_order' => ($count + 1) * 10];

echo '<form method="post" action="' . $admin::esc($admin::admin_url('cms-beratung-faqs')) . '" class="beratung-faq-admin">'
    . $admin::hidden_token('beratung_m365_faqs') . '<input type="hidden" name="beratung_admin_action" value="save_m365_faqs">'
    . '<div class="beratung-grid-2"><section class="admin-card"><h3>Anzeige</h3>';
$admin::checkbox('FAQ-Bereich auf Landingpages anzeigen', 'enabled', !empty($config['enabled']));
$admin::field('Oberzeile', 'eyebrow', (string) ($config['eyebrow'] ?? 'FAQ'));
$admin::field('Titel', 'title', (string) ($config['title'] ?? 'Häufige Fragen'));
$admin::textarea('Einleitung', 'intro', (string) ($config['intro'] ?? ''), 3);
$admin::field('Anker-ID', 'anchor_id', (string) ($config['anchor_id'] ?? 'faq'));
echo '</section><section class="admin-card"><h3>Verhalten</h3>';
$admin::checkbox('Mehrere Einträge gleichzeitig geöffnet erlauben', 'allow_multiple', !empty($config['allow_multiple']));
$admin::select('Standard-Öffnung', 'open_behavior', (string) ($config['open_behavior'] ?? 'first'), ['none' => 'Kein Eintrag offen', 'first' => 'Erster Eintrag offen', 'custom' => 'Individuell pro Frage']);
$admin::select('Icon-Stil', 'icon_style', (string) ($config['icon_style'] ?? 'plus'), ['plus' => 'Plus', 'chevron' => 'Chevron', 'question' => 'Fragezeichen']);
$admin::checkbox('FAQ-Schema (JSON-LD) ausgeben', 'schema_enabled', !empty($config['schema_enabled']));
echo '</section></div><section class="admin-card"><h3>Fragen und Antworten <span class="beratung-muted">(' . $count . ')</span></h3><p class="beratung-muted">Leere Fragen werden beim Speichern entfernt; die letzte Zeile legt eine neue Frage an.</p><div class="beratung-faq-list">';
foreach ($items as $index => $item) {
    $isNew = $index === $count;
    echo '<article class="beratung-faq-row' . ($isNew ? ' is-new' : '') . '"><div class="beratung-faq-row__meta"><strong>' . ($isNew ? 'Neue Frage' : 'FAQ ' . ($index + 1)) . '</strong>'
        . '<label class="beratung-field beratung-field--inline"><span>Sortierung</span><input type="number" name="faq_sort_order[' . $index . ']" value="' . (int) ($item['sort_order'] ?? (($index + 1) * 10)) . '"></label>'
        . '<label class="beratung-check"><input type="checkbox" name="faq_enabled[' . $index . ']" value="1"' . (!empty($item['enabled']) ? ' checked' : '') . '> <span>Aktiv</span></label>'
        . '<label class="beratung-check"><input type="checkbox" name="faq_default_open[' . $index . ']" value="1"' . (!empty($item['default_open']) ? ' checked' : '') . '> <span>Geöffnet</span></label></div>'
        . '<label class="beratung-field"><span>Frage</span><input name="faq_question[' . $index . ']" value="' . $admin::esc((string) ($item['question'] ?? '')) . '" maxlength="255"></label>'
        . '<label class="beratung-field"><span>Antwort</span><textarea name="faq_answer[' . $index . ']" rows="3" maxlength="1800">' . $admin::esc((string) ($item['answer'] ?? '')) . '</textarea></label></article>';
}
echo '</div></section><div class="beratung-form-actions"><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy" aria-hidden="true"></i> FAQs speichern</button></div></form>';

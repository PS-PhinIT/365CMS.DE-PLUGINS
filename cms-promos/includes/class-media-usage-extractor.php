<?php
/**
 * Extraktor für den Core-`MediaUsageIndex` (nur geladen, wenn die 4.1-Schnittstelle existiert).
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CMS_Promos_Media_Usage_Extractor')) {
    final class CMS_Promos_Media_Usage_Extractor implements \CMS\Services\Media\MediaReferenceExtractor
    {
        /**
         * @param array{label:string, table:string, title:list<string>, columns:array<string,string>, edit_url:string} $config
         */
        public function __construct(private readonly string $type, private readonly array $config)
        {
        }

        public function contentType(): string
        {
            return $this->type;
        }

        public function label(): string
        {
            return $this->config['label'];
        }

        public function ids(int $afterId, int $limit): array
        {
            $db = \CMS\Database::instance();
            $table = $db->getPrefix() . $this->config['table'];

            return array_map('intval', $db->get_col(
                "SELECT id FROM {$table} WHERE id > ? ORDER BY id ASC LIMIT " . max(1, min(1000, $limit)),
                [$afterId]
            ));
        }

        public function load(int $id): ?array
        {
            $db = \CMS\Database::instance();
            $table = $db->getPrefix() . $this->config['table'];
            // Spaltennamen stammen ausschließlich aus der festen Konfiguration der Plugin-Klasse.
            $columns = array_values(array_unique(array_merge(['id'], $this->config['title'], array_keys($this->config['columns']))));
            $row = $db->get_row(
                'SELECT ' . implode(', ', $columns) . " FROM {$table} WHERE id = ? LIMIT 1",
                [$id]
            );
            if ($row === null) {
                return null;
            }

            $title = '';
            foreach ($this->config['title'] as $column) {
                $title = trim($title . ' ' . (string) ($row->{$column} ?? ''));
                if ($column !== 'first_name' && $title !== '') {
                    break;
                }
            }

            $values = [];
            foreach ($this->config['columns'] as $column => $context) {
                $value = (string) ($row->{$column} ?? '');
                if ($value !== '') {
                    $values[] = ['context' => $context, 'value' => $value];
                }
            }

            return [
                'title' => $title !== '' ? $title : $this->config['label'] . ' #' . $id,
                'edit_url' => str_replace('{id}', (string) $id, $this->config['edit_url']),
                'values' => $values,
            ];
        }
    }
}

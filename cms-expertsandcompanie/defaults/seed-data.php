<?php
/**
 * Seed-Daten für Experts & Companie.
 *
 * Keine Upload- oder Import-Funktion: Die CSV-Dateien liegen fest im Plugin.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$parseCsv = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }

    $content = (string) file_get_contents($path);
    if ($content === '') {
        return [];
    }

    $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
    if ($lines === []) {
        return [];
    }

    $header = str_getcsv((string) array_shift($lines), ';', '"', '') ?: [];
    $normalizeHeader = static function (string $value): string {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß', '/', ' ', '-'], ['ae', 'oe', 'ue', 'ss', '_', '_', '_'], $value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    };

    $headers = array_map($normalizeHeader, $header);
    $rows = [];

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }

        $values = str_getcsv($line, ';', '"', '') ?: [];
        $row = [];
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = trim((string) ($values[$idx] ?? ''));
        }

        if ($row !== []) {
            $rows[] = $row;
        }
    }

    return $rows;
};

$baseDir = __DIR__ . DIRECTORY_SEPARATOR;

return [
    'experts' => $parseCsv($baseDir . 'experts.csv'),
    'companies' => $parseCsv($baseDir . 'companies.csv'),
];

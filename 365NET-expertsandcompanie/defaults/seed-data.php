<?php
/**
 * Seed-Daten für Experts & Companies.
 *
 * Die CSV-Dateien liegen fest im Plugin und haben dasselbe Format wie der CSV-Import
 * (Admin → Import & Export); geparst wird mit demselben Parser.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$baseDir = __DIR__ . DIRECTORY_SEPARATOR;
if (!class_exists('CMS_365NET_Experts_And_Companie_Transfer', false)) {
    return ['experts' => [], 'companies' => []];
}

return [
    'experts' => CMS_365NET_Experts_And_Companie_Transfer::parseCsvFile($baseDir . 'experts.csv'),
    'companies' => CMS_365NET_Experts_And_Companie_Transfer::parseCsvFile($baseDir . 'companies.csv'),
];

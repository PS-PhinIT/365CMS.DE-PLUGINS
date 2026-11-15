<?php
/**
 * CSV-Import/-Export für Experts und Companies.
 *
 * Format = Seed-Dateien `defaults/experts.csv` / `defaults/companies.csv` (UTF-8, `;`, Kopfzeile in Großbuchstaben),
 * ergänzt um ID und die Bild-/Verknüpfungsfelder. Export: UTF-8 mit BOM, Formel-Injektionsschutz. Import:
 * Größen-/Zeilenlimit, Endungs- und MIME-Prüfung, Validierung über die Speicherlogik der Datenbankklasse.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Experts_And_Companie_Transfer
{
    public const MAX_BYTES = 2097152;
    public const MAX_ROWS = 5000;

    /** Spalte (CSV-Kopf, klein) → Feld des Speicher-Payloads. */
    private const EXPERT_MAP = [
        'id' => 'id', 'first_name' => 'first_name', 'last_name' => 'last_name', 'company' => 'company',
        'position' => 'position', 'biography' => 'biography', 'website' => 'website', 'city' => 'city',
        'country' => 'country', 'availability' => 'availability', 'experience_years' => 'experience_years',
        'hourly_rate' => 'hourly_rate', 'daily_rate' => 'daily_rate', 'skills_general' => 'skills_general',
        'skills_tech' => 'skills_tech', 'skills_soft' => 'skills_soft', 'awards' => 'awards',
        'certifications' => 'certifications', 'status' => 'status', 'email' => 'email', 'photo_url' => 'photo_url',
        'linked_company_id' => 'linked_company_id', 'linked_speaker_id' => 'linked_speaker_id', 'updated_at' => 'updated_at',
    ];

    private const COMPANY_MAP = [
        'id' => 'id', 'name' => 'name', 'email' => 'email', 'phone' => 'phone', 'industry' => 'industry',
        'company_size' => 'company_size', 'description' => 'description', 'website' => 'website', 'city' => 'city',
        'zip' => 'zip', 'country' => 'country', 'founded_year' => 'founded_year', 'employee_count' => 'employee_count',
        'is_partner' => 'is_partner', 'is_top_partner' => 'is_top_partner', 'is_sponsor' => 'is_sponsor',
        'status' => 'status', 'logo_url' => 'logo_url', 'parent_company_id' => 'parent_company_id',
        'linked_expert_id' => 'linked_expert_id', 'linked_speaker_id' => 'linked_speaker_id', 'updated_at' => 'updated_at',
    ];

    private const BOOLEAN_FIELDS = ['is_partner', 'is_top_partner', 'is_sponsor'];
    private const IGNORED_ON_IMPORT = ['id', 'updated_at'];
    private const ALLOWED_MIME = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'text/comma-separated-values', 'application/octet-stream'];

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
    }

    /** @return list<string> Kopfzeile (Großbuchstaben) in Export-Reihenfolge */
    public static function headers(string $entity): array
    {
        return array_map('strtoupper', array_keys($entity === 'companies' ? self::COMPANY_MAP : self::EXPERT_MAP));
    }

    /* ---------------------------------------------------------------------
     * Export
     * ------------------------------------------------------------------- */

    /** @param array{search?:string,status?:string} $filter */
    public function streamExport(string $entity, array $filter): never
    {
        $entity = $entity === 'companies' ? 'companies' : 'experts';
        $db = CMS_365NET_Experts_And_Companie_Database::instance();
        $map = $entity === 'companies' ? self::COMPANY_MAP : self::EXPERT_MAP;
        $out = $this->openDownload($entity . '-' . date('Y-m-d') . '.csv');
        $this->writeRow($out, self::headers($entity));

        $offset = 0;
        $batch = 500;
        do {
            $rows = $db->getExportRows($entity, $filter, $batch, $offset);
            foreach ($rows as $row) {
                $line = [];
                foreach (array_keys($map) as $column) {
                    $value = $column === 'city' ? ($row->location_city ?? $row->city ?? '') : ($row->{$column} ?? '');
                    $line[] = (string) $value;
                }
                $this->writeRow($out, $line);
            }
            $offset += $batch;
        } while (count($rows) === $batch && $offset < 100000);

        fclose($out);
        exit;
    }

    /** Leere Vorlage (Kopfzeile + Beispielzeile). */
    public function streamTemplate(string $entity): never
    {
        $entity = $entity === 'companies' ? 'companies' : 'experts';
        $out = $this->openDownload($entity . '-vorlage.csv');
        $this->writeRow($out, self::headers($entity));
        $example = $entity === 'companies'
            ? ['', 'Beispiel GmbH', 'info@example.com', '+49 30 123456', 'IT-Beratung', 'sme', 'Kurzbeschreibung des Unternehmens.', 'https://www.example.com/', 'Berlin', '10115', 'Deutschland', '2010', '45', '1', '0', '0', 'active', '', '', '', '', '']
            : ['', 'Erika', 'Musterfrau', 'Beispiel GmbH', 'Cloud Architect', 'Kurzbiografie.', 'https://www.example.com/', 'Berlin', 'Deutschland', 'available', '12', '', '', 'Beratung', 'Azure, Microsoft 365', 'Kommunikation', '', '', 'active', '', '', '', '', ''];
        $this->writeRow($out, $example);
        fclose($out);
        exit;
    }

    /** @return resource */
    private function openDownload(string $filename)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $filename = preg_replace('/[^a-z0-9._-]+/i', '-', $filename) ?: 'export.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");

        return $out;
    }

    /**
     * @param resource $out
     * @param list<string> $values
     */
    private function writeRow($out, array $values): void
    {
        fputcsv($out, array_map([self::class, 'protectCell'], $values), ';', '"', '');
    }

    /** Formel-Injektion (Excel/LibreOffice) verhindern. */
    public static function protectCell(string $value): string
    {
        $value = str_replace("\0", '', $value);

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /* ---------------------------------------------------------------------
     * Import
     * ------------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $file Eintrag aus $_FILES
     * @return array{ok:bool,created:int,updated:int,skipped:int,errors:list<string>}
     */
    public function import(string $entity, array $file, bool $updateExisting): array
    {
        $entity = $entity === 'companies' ? 'companies' : 'experts';
        $result = ['ok' => false, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $error = $this->validateUpload($file);
        if ($error !== null) {
            $result['errors'][] = $error;
            return $result;
        }

        $content = (string) file_get_contents((string) $file['tmp_name'], false, null, 0, self::MAX_BYTES + 1);
        $rows = self::parseCsvString($content);
        if ($rows === []) {
            $result['errors'][] = 'Die Datei enthält keine Datenzeilen.';
            return $result;
        }
        if (count($rows) > self::MAX_ROWS) {
            $result['errors'][] = 'Die Datei enthält mehr als ' . self::MAX_ROWS . ' Zeilen. Bitte aufteilen.';
            return $result;
        }

        $map = $entity === 'companies' ? self::COMPANY_MAP : self::EXPERT_MAP;
        $required = $entity === 'companies' ? ['name'] : ['first_name', 'last_name'];
        $headers = array_keys($rows[0]);
        $missing = array_diff($required, $headers);
        if ($missing !== []) {
            $result['errors'][] = 'Pflichtspalten fehlen: ' . strtoupper(implode(', ', $missing)) . '.';
            return $result;
        }
        if (array_intersect($headers, array_keys($map)) === []) {
            $result['errors'][] = 'Keine bekannte Spalte gefunden – passt der Bereich (Experts/Companies)?';
            return $result;
        }

        $db = CMS_365NET_Experts_And_Companie_Database::instance();
        $existing = $updateExisting && in_array('id', $headers, true)
            ? $db->existingIds($entity, array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows))
            : [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = [];
            foreach ($map as $column => $field) {
                if (array_key_exists($column, $row) && !in_array($column, self::IGNORED_ON_IMPORT, true)) {
                    $values[$field] = self::unprotectCell((string) $row[$column]);
                }
            }
            foreach (self::BOOLEAN_FIELDS as $field) {
                if (array_key_exists($field, $values)) {
                    $values[$field] = in_array(strtolower(trim((string) $values[$field])), ['1', 'ja', 'yes', 'true', 'x', 'wahr'], true) ? 1 : 0;
                }
            }
            if (array_key_exists('status', $values)) {
                $status = strtolower(trim((string) $values['status']));
                $values['status'] = in_array($status, ['inactive', 'inaktiv', '0', 'nein'], true) ? 'inactive' : 'active';
            }

            $id = (int) ($row['id'] ?? 0);
            $isUpdate = $id > 0 && isset($existing[$id]);
            try {
                if ($isUpdate) {
                    $current = $entity === 'companies' ? $db->getCompanyById($id) : $db->getExpertById($id);
                    $payload = array_merge($this->currentPayload($entity, $current), $values, ['id' => $id]);
                    if (array_key_exists('biography', $values)) {
                        $payload['biography_json'] = '';
                    }
                    if (array_key_exists('description', $values)) {
                        $payload['description_json'] = '';
                    }
                } else {
                    $payload = $values + ['id' => 0, 'status' => 'active'];
                }

                $saved = $entity === 'companies' ? $db->saveCompany($payload) : $db->saveExpert($payload);
            } catch (\Throwable $e) {
                error_log('CMS 365NET Experts & Companies [import]: ' . $e->getMessage());
                $saved = false;
            }

            if ($saved === false) {
                $result['skipped']++;
                if (count($result['errors']) < 50) {
                    $result['errors'][] = 'Zeile ' . $line . ': nicht übernommen (' . ($entity === 'companies' ? 'Name fehlt oder ungültig' : 'Vor- oder Nachname fehlt') . ').';
                }
                continue;
            }
            $isUpdate ? $result['updated']++ : $result['created']++;
        }

        $result['ok'] = ($result['created'] + $result['updated']) > 0;

        return $result;
    }

    /** @param array<string, mixed> $file */
    private function validateUpload(array $file): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'Die Datei ist größer als ' . (int) (self::MAX_BYTES / 1048576) . ' MB.';
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return 'Es wurde keine gültige Datei hochgeladen.';
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES || (int) filesize($tmp) > self::MAX_BYTES) {
            return 'Die Datei ist leer oder größer als ' . (int) (self::MAX_BYTES / 1048576) . ' MB.';
        }
        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return 'Nur CSV-Dateien (.csv) sind erlaubt.';
        }
        if (class_exists('finfo')) {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if ($mime !== '' && !in_array(strtolower($mime), self::ALLOWED_MIME, true)) {
                return 'Die Datei ist keine Textdatei (erkannt: ' . $mime . ').';
            }
        }

        return null;
    }

    /** @param object|null $row @return array<string, mixed> Bisherige Werte im Payload-Format (Aktualisieren) */
    private function currentPayload(string $entity, ?object $row): array
    {
        if ($row === null) {
            return [];
        }
        $data = (array) $row;
        $data['city'] = (string) ($row->location_city ?? $row->city ?? '');
        unset($data['seed_key'], $data['created_at'], $data['updated_at'], $data['location_city']);
        foreach ($data as $key => $value) {
            $data[$key] = $value === null ? '' : $value;
        }

        return $data;
    }

    /** Maskierung aus dem Export (') wieder entfernen. */
    private static function unprotectCell(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        if (strlen($value) > 1 && $value[0] === "'" && in_array($value[1], ['=', '+', '-', '@'], true)) {
            $value = substr($value, 1);
        }

        return $value;
    }

    /* ---------------------------------------------------------------------
     * Parser (Import und Seed-Dateien)
     * ------------------------------------------------------------------- */

    /** @return list<array<string, string>> */
    public static function parseCsvFile(string $path): array
    {
        if (!is_file($path) || (int) filesize($path) > 8 * self::MAX_BYTES) {
            return [];
        }

        return self::parseCsvString((string) file_get_contents($path));
    }

    /**
     * CSV-Text → Zeilen (Schlüssel = normalisierte Kopfzeile). Erkennt BOM, Kodierung (UTF-8/Windows-1252),
     * Trennzeichen (; , Tab) und Zeilenumbrüche in Anführungszeichen.
     *
     * @return list<array<string, string>>
     */
    public static function parseCsvString(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if ($content === '' || trim($content) === '') {
            return [];
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $content = (string) mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ';';
        $best = substr_count($firstLine, ';');
        foreach ([',', "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > $best) {
                $best = substr_count($firstLine, $candidate);
                $delimiter = $candidate;
            }
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $content);
        rewind($stream);

        $headers = null;
        $rows = [];
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }
            if ($headers === null) {
                $headers = array_map([self::class, 'normalizeHeader'], array_map('strval', $values));
                continue;
            }
            $row = [];
            foreach ($headers as $index => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($values[$index] ?? ''));
                }
            }
            if ($row !== [] && implode('', $row) !== '') {
                $rows[] = $row;
            }
            if (count($rows) > self::MAX_ROWS * 2) {
                break;
            }
        }
        fclose($stream);

        return $rows;
    }

    public static function normalizeHeader(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß', '/', ' ', '-'], ['ae', 'oe', 'ue', 'ss', '_', '_', '_'], $value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';

        return trim($value, '_');
    }
}

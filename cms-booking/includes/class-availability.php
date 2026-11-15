<?php
/**
 * CMS Booking – Verfügbarkeiten
 *
 * Wochenplan- und Einzeltag-Verwaltung, Slot-Berechnung.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Availability
{
    private static ?self $instance = null;
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $weeklyCache = [];
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $overrideCache = [];
    /** @var array<string, array<int, string>> */
    private array $slotCache = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /* ================================================================== */
    /*  CRUD                                                               */
    /* ================================================================== */

    /**
     * Verfügbarkeits-Slot anlegen.
     */
    public function create(array $data): int
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $fields = [
            'provider_id'   => (int) ($data['provider_id']),
            'day_of_week'   => isset($data['day_of_week']) ? (int) $data['day_of_week'] : null,
            'specific_date' => isset($data['specific_date']) ? (string) $data['specific_date'] : null,
            'start_time'    => (string) $data['start_time'],
            'end_time'      => (string) $data['end_time'],
            'is_blocked'    => (int) ($data['is_blocked'] ?? 0),
        ];

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO {$p}booking_availability ({$cols}) VALUES ({$ph})")
            ->execute(array_values($fields));
        $this->clear_provider_cache((int) ($data['provider_id'] ?? 0));

        return (int) $db->getPdo()->lastInsertId();
    }

    /**
     * Slot löschen.
     */
    public function delete(int $id): bool
    {
        $db   = \CMS\Database::instance();
        $providerId = $this->provider_id_by_availability_id($id);
        $stmt = $db->prepare("DELETE FROM {$db->getPrefix()}booking_availability WHERE id = ?");
        $stmt->execute([$id]);
        if ($providerId > 0) {
            $this->clear_provider_cache($providerId);
        }
        return $stmt->rowCount() > 0;
    }

    /**
     * Alle Slots eines Anbieters löschen (Wochenplan).
     */
    public function delete_weekly(int $providerId): bool
    {
        $db = \CMS\Database::instance();
        $stmt = $db->prepare(
            "DELETE FROM {$db->getPrefix()}booking_availability
             WHERE provider_id = ? AND specific_date IS NULL"
        );
        $stmt->execute([$providerId]);
        $this->clear_provider_cache($providerId);
        return $stmt->rowCount() > 0;
    }

    /**
     * Wochenplan ersetzen (alle alten löschen, neue einfügen).
     *
     * @param  int      $providerId
     * @param  array    $weekly  [['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'], …]
     */
    public function replace_weekly(int $providerId, array $weekly): void
    {
        $this->delete_weekly($providerId);

        foreach ($weekly as $slot) {
            $this->create([
                'provider_id' => $providerId,
                'day_of_week' => (int) $slot['day_of_week'],
                'start_time'  => (string) $slot['start_time'],
                'end_time'    => (string) $slot['end_time'],
                'is_blocked'  => 0,
            ]);
        }
    }

    /**
     * Wochenplan laden.
     */
    public function get_weekly(int $providerId): array
    {
        if (isset($this->weeklyCache[$providerId])) {
            return $this->weeklyCache[$providerId];
        }

        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id, provider_id, day_of_week, specific_date, start_time, end_time, is_blocked, created_at FROM {$db->getPrefix()}booking_availability
             WHERE provider_id = ? AND specific_date IS NULL AND is_blocked = 0
             ORDER BY day_of_week ASC, start_time ASC"
        );
        $stmt->execute([$providerId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->weeklyCache[$providerId] = $rows;
        return $rows;
    }

    /**
     * Spezifische Datums-Einträge laden (Urlaub / Sondertermine).
     */
    public function get_date_overrides(int $providerId, string $from, string $to): array
    {
        $cacheKey = $providerId . '|' . $from . '|' . $to;
        if (isset($this->overrideCache[$cacheKey])) {
            return $this->overrideCache[$cacheKey];
        }

        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id, provider_id, day_of_week, specific_date, start_time, end_time, is_blocked, created_at FROM {$db->getPrefix()}booking_availability
             WHERE provider_id = ? AND specific_date BETWEEN ? AND ?
             ORDER BY specific_date ASC, start_time ASC"
        );
        $stmt->execute([$providerId, $from, $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->overrideCache[$cacheKey] = $rows;
        return $rows;
    }

    /**
     * Abwesenheit / Blockierung für ein spezifisches Datum anlegen.
     */
    public function block_date(int $providerId, string $date, string $startTime = '00:00', string $endTime = '23:59'): int
    {
        return $this->create([
            'provider_id'   => $providerId,
            'specific_date' => $date,
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'is_blocked'    => 1,
        ]);
    }

    /* ================================================================== */
    /*  Slot-Berechnung                                                    */
    /* ================================================================== */

    /**
     * Verfügbare Zeitslots für einen Anbieter an einem bestimmten Datum berechnen.
     *
     * @param  int     $providerId
     * @param  string  $date         Y-m-d
     * @param  int     $durationMin  Dauer je Slot in Minuten
     * @param  int     $bufferMin    Puffer danach
     * @return array   ['09:00', '10:15', …]
     */
    public function get_available_slots(
        int    $providerId,
        string $date,
        int    $durationMin = 60,
        int    $bufferMin   = 15
    ): array {
        $slotCacheKey = implode('|', [$providerId, $date, $durationMin, $bufferMin]);
        if (isset($this->slotCache[$slotCacheKey])) {
            return $this->slotCache[$slotCacheKey];
        }

        // 1. Wochentag bestimmen (0=Mo … 6=So)
        $ts  = strtotime($date);
        $dow = ((int) date('N', $ts)) - 1; // 1=Mo → 0

        // 2. Basis-Slots aus Wochenplan
        $weekly = $this->get_weekly($providerId);
        $ranges = [];
        foreach ($weekly as $slot) {
            if ((int) $slot['day_of_week'] === $dow) {
                $ranges[] = [
                    'start' => $slot['start_time'],
                    'end'   => $slot['end_time'],
                ];
            }
        }

        // 3. Datum-Overrides anwenden
        $overrides = $this->get_date_overrides($providerId, $date, $date);
        foreach ($overrides as $ov) {
            if ((int) $ov['is_blocked'] === 1) {
                // Range entfernen / einschränken
                $ranges = $this->subtract_range($ranges, $ov['start_time'], $ov['end_time']);
            } else {
                // Zusätzliche Verfügbarkeit
                $ranges[] = [
                    'start' => $ov['start_time'],
                    'end'   => $ov['end_time'],
                ];
            }
        }

        // 4. Bereits gebuchte Zeiten subtrahieren
        if (class_exists('CMS_Booking_Bookings')) {
            $booked = CMS_Booking_Bookings::instance()->get_booked_ranges($providerId, $date);
            foreach ($booked as $b) {
                $ranges = $this->subtract_range($ranges, $b['start'], $b['end']);
            }
        }

        // 5. Slots generieren
        $stepMin = $durationMin + $bufferMin;
        $slots   = [];

        foreach ($ranges as $range) {
            $current = strtotime("{$date} {$range['start']}");
            $end     = strtotime("{$date} {$range['end']}");

            while (($current + $durationMin * 60) <= $end) {
                $slots[] = date('H:i', $current);
                $current += $stepMin * 60;
            }
        }

        sort($slots);
        $result = array_values(array_unique($slots));
        $this->slotCache[$slotCacheKey] = $result;
        return $result;
    }

    /**
     * Prüfe ob ein bestimmter Slot verfügbar ist.
     */
    public function is_slot_available(
        int    $providerId,
        string $date,
        string $startTime,
        int    $durationMin = 60,
        int    $bufferMin   = 15
    ): bool {
        $available = $this->get_available_slots($providerId, $date, $durationMin, $bufferMin);
        return in_array($startTime, $available, true);
    }

    /**
     * Nächste verfügbare Tage (für Kalender).
     *
     * @return array  ['2026-02-01', '2026-02-02', …]
     */
    public function get_available_dates(int $providerId, int $durationMin = 60, int $days = 30): array
    {
        $dates = [];
        $start = new \DateTime('today');
        $end   = (new \DateTime('today'))->modify("+{$days} days");

        while ($start <= $end) {
            $date  = $start->format('Y-m-d');
            $slots = $this->get_available_slots($providerId, $date, $durationMin, 0);
            if (!empty($slots)) {
                $dates[] = $date;
            }
            $start->modify('+1 day');
        }

        return $dates;
    }

    /* ================================================================== */
    /*  Range-Arithmetik                                                   */
    /* ================================================================== */

    /**
     * Subtrahiere einen Zeitraum von einer Liste an Ranges.
     *
     * @param  array   $ranges  [['start' => 'HH:MM', 'end' => 'HH:MM'], …]
     * @param  string  $subStart
     * @param  string  $subEnd
     * @return array
     */
    private function subtract_range(array $ranges, string $subStart, string $subEnd): array
    {
        $result = [];
        foreach ($ranges as $r) {
            if ($subEnd <= $r['start'] || $subStart >= $r['end']) {
                $result[] = $r;
                continue;
            }
            if ($subStart > $r['start']) {
                $result[] = ['start' => $r['start'], 'end' => $subStart];
            }
            if ($subEnd < $r['end']) {
                $result[] = ['start' => $subEnd, 'end' => $r['end']];
            }
        }
        return $result;
    }

    /* ================================================================== */
    /*  Wochentag-Labels                                                   */
    /* ================================================================== */

    public static function day_labels(): array
    {
        return [
            0 => 'Montag',
            1 => 'Dienstag',
            2 => 'Mittwoch',
            3 => 'Donnerstag',
            4 => 'Freitag',
            5 => 'Samstag',
            6 => 'Sonntag',
        ];
    }

    public static function day_label(int $day): string
    {
        return self::day_labels()[$day] ?? '';
    }

    private function provider_id_by_availability_id(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }

        $db = \CMS\Database::instance();
        $stmt = $db->prepare("SELECT provider_id FROM {$db->getPrefix()}booking_availability WHERE id = ?");
        $stmt->execute([$id]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function clear_provider_cache(int $providerId): void
    {
        if ($providerId <= 0) {
            $this->weeklyCache = [];
            $this->overrideCache = [];
            $this->slotCache = [];
            return;
        }

        unset($this->weeklyCache[$providerId]);

        $prefix = $providerId . '|';
        foreach (array_keys($this->overrideCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->overrideCache[$key]);
            }
        }
        foreach (array_keys($this->slotCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->slotCache[$key]);
            }
        }
    }
}

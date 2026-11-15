<?php
/**
 * CMS Booking – Buchungen
 *
 * CRUD, Statusübergänge, Sammelaktionen, Batch-Abfragen für die Slot-Berechnung, DSGVO und Löschfrist.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Bookings
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /* ================================================================== */
    /*  Status-Konstanten                                                  */
    /* ================================================================== */

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW   = 'no_show';

    public static function status_labels(): array
    {
        return [
            self::STATUS_PENDING   => 'Offen',
            self::STATUS_CONFIRMED => 'Bestätigt',
            self::STATUS_CANCELLED => 'Storniert',
            self::STATUS_COMPLETED => 'Abgeschlossen',
            self::STATUS_NO_SHOW   => 'Nicht erschienen',
        ];
    }

    public static function status_badge_class(string $status): string
    {
        return match ($status) {
            self::STATUS_CONFIRMED => 'active',
            self::STATUS_COMPLETED => 'active',
            self::STATUS_CANCELLED => 'inactive',
            self::STATUS_NO_SHOW   => 'inactive',
            default                => 'pending',
        };
    }

    /** Spalten der Buchungstabelle, die Listen, Detailansicht und Export benötigen. */
    private const BOOKING_COLUMNS = 'b.id, b.provider_id, b.service_id, b.user_id, b.contact_submission_id,
        b.customer_name, b.customer_email, b.customer_phone, b.booking_date, b.start_time, b.end_time,
        b.duration_min, b.location_type, b.meeting_url, b.price_cents, b.currency, b.status, b.notes,
        b.internal_notes, b.ical_uid, b.reminder_sent, b.cancelled_at, b.confirmed_at, b.completed_at,
        b.created_at, b.updated_at';

    /** Status, die einen Zeitraum belegen. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /* ================================================================== */
    /*  CRUD                                                               */
    /* ================================================================== */

    public function create(array $data): int
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        if (empty($data['provider_id']) || empty($data['customer_name']) || empty($data['customer_email'])) {
            throw new \InvalidArgumentException('provider_id, customer_name und customer_email sind Pflicht.');
        }

        $status = (string) ($data['status'] ?? self::STATUS_PENDING);
        if (!in_array($status, self::ACTIVE_STATUSES, true)) {
            $status = self::STATUS_PENDING;
        }

        $icalUid = $this->generate_ical_uid();

        $fields = [
            'provider_id'           => (int) $data['provider_id'],
            'service_id'            => isset($data['service_id'])  ? (int) $data['service_id']  : null,
            'user_id'               => isset($data['user_id'])     ? (int) $data['user_id']     : null,
            'contact_submission_id' => isset($data['contact_submission_id']) ? (int) $data['contact_submission_id'] : null,
            'customer_name'         => mb_substr((string) $data['customer_name'], 0, 255),
            'customer_email'        => mb_substr((string) $data['customer_email'], 0, 255),
            'customer_phone'        => isset($data['customer_phone']) && $data['customer_phone'] !== '' ? mb_substr((string) $data['customer_phone'], 0, 50) : null,
            'booking_date'          => (string) $data['booking_date'],
            'start_time'            => (string) $data['start_time'],
            'end_time'              => (string) $data['end_time'],
            'duration_min'          => (int) ($data['duration_min'] ?? 60),
            'location_type'         => (string) ($data['location_type'] ?? 'online'),
            'meeting_url'           => isset($data['meeting_url']) && $data['meeting_url'] !== '' ? (string) $data['meeting_url'] : null,
            'price_cents'           => (int) ($data['price_cents'] ?? 0),
            'currency'              => (string) ($data['currency'] ?? 'EUR'),
            'status'                => $status,
            'notes'                 => isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            'internal_notes'        => isset($data['internal_notes']) ? (string) $data['internal_notes'] : null,
            'ical_uid'              => $icalUid,
        ];

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($fields), '?'));
        $sqlExtraCols = $status === self::STATUS_CONFIRMED ? ', confirmed_at' : '';
        $sqlExtraVals = $status === self::STATUS_CONFIRMED ? ', NOW()' : '';
        $db->prepare("INSERT INTO {$p}bookings ({$cols}{$sqlExtraCols}) VALUES ({$ph}{$sqlExtraVals})")
            ->execute(array_values($fields));

        $bookingId = (int) $db->getPdo()->lastInsertId();

        // Meta-Daten speichern
        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $this->set_meta($bookingId, (string) $key, is_scalar($value) ? (string) $value : null);
            }
        }

        // Hook auslösen (Benachrichtigungen, Integrationen)
        if (class_exists('CMS\Hooks')) {
            \CMS\Hooks::doAction('booking_created', $bookingId, $fields);
        }

        return $bookingId;
    }

    public function update(int $id, array $data): bool
    {
        $db      = \CMS\Database::instance();
        $p       = $db->getPrefix();
        $allowed = [
            'customer_name', 'customer_email', 'customer_phone',
            'booking_date', 'start_time', 'end_time', 'duration_min',
            'location_type', 'meeting_url', 'price_cents', 'currency',
            'notes', 'internal_notes',
        ];

        $sets   = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[]   = "{$col} = ?";
            $params[] = $data[$col];
        }
        if (empty($sets)) {
            return true;
        }

        $params[] = $id;
        $db->prepare("UPDATE {$p}bookings SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($params);
        return true;
    }

    public function get(int $id): ?array
    {
        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $stmt = $db->prepare(
            'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bp.slug AS provider_slug,
                    bp.email AS provider_email, bp.source_plugin, bp.timezone AS provider_timezone,
                    bs.title AS service_title, bs.slug AS service_slug
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Gemeinsame WHERE-Bedingungen für Liste, Zählung und Export.
     *
     * @param array<string, mixed> $filters status, search, provider_id, date_from, date_to
     * @return array{0: string, 1: list<mixed>}
     */
    private function build_filter_sql(array $filters): array
    {
        $sql    = ' WHERE 1=1';
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && array_key_exists($status, self::status_labels())) {
            $sql     .= ' AND b.status = ?';
            $params[] = $status;
        }
        $providerId = (int) ($filters['provider_id'] ?? 0);
        if ($providerId > 0) {
            $sql     .= ' AND b.provider_id = ?';
            $params[] = $providerId;
        }
        $dateFrom = (string) ($filters['date_from'] ?? '');
        if (self::is_date($dateFrom)) {
            $sql     .= ' AND b.booking_date >= ?';
            $params[] = $dateFrom;
        }
        $dateTo = (string) ($filters['date_to'] ?? '');
        if (self::is_date($dateTo)) {
            $sql     .= ' AND b.booking_date <= ?';
            $params[] = $dateTo;
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.customer_phone LIKE ? OR bp.display_name LIKE ?';
            array_push($params, $like, $like, $like, $like);
            if (ctype_digit($search)) {
                $sql     .= ' OR b.id = ?';
                $params[] = (int) $search;
            }
            $sql .= ')';
        }

        return [$sql, $params];
    }

    public static function is_date(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    /**
     * Buchungen paginiert laden.
     *
     * Signatur kompatibel zu 3.x (Positionsparameter); zusätzlich Datumsbereich.
     */
    public function get_all(
        int    $offset = 0,
        int    $limit  = 20,
        string $status = '',
        string $search = '',
        int    $providerId = 0,
        string $dateFrom = '',
        string $dateTo   = ''
    ): array {
        return $this->query_list([
            'status' => $status,
            'search' => $search,
            'provider_id' => $providerId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], $offset, $limit);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function query_list(array $filters, int $offset = 0, int $limit = 20, string $order = 'date_desc'): array
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        [$where, $params] = $this->build_filter_sql($filters);

        $orderSql = match ($order) {
            'date_asc'     => 'b.booking_date ASC, b.start_time ASC',
            'created_desc' => 'b.created_at DESC, b.id DESC',
            default        => 'b.booking_date DESC, b.start_time DESC',
        };

        $sql = 'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bp.slug AS provider_slug,
                       bs.title AS service_title
                FROM {$p}bookings b
                LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
                LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id"
            . $where
            . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . max(1, min(5000, $limit)) . ' OFFSET ' . max(0, $offset);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function count(string $status = '', string $search = '', int $providerId = 0, string $dateFrom = '', string $dateTo = ''): int
    {
        return $this->count_filtered([
            'status' => $status,
            'search' => $search,
            'provider_id' => $providerId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count_filtered(array $filters): int
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        [$where, $params] = $this->build_filter_sql($filters);

        $join = trim((string) ($filters['search'] ?? '')) !== ''
            ? " LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id"
            : '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$p}bookings b{$join}{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Anzahl offener Anfragen (für Badge, KPI und Systemhinweis; nutzt idx_status_date). */
    public function count_pending(): int
    {
        try {
            $db   = \CMS\Database::instance();
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$db->getPrefix()}bookings WHERE status = ?");
            $stmt->execute([self::STATUS_PENDING]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* ================================================================== */
    /*  Status-Übergänge                                                   */
    /* ================================================================== */

    public function confirm(int $id): bool
    {
        return $this->change_status($id, self::STATUS_CONFIRMED, 'confirmed_at');
    }

    public function cancel(int $id): bool
    {
        return $this->change_status($id, self::STATUS_CANCELLED, 'cancelled_at');
    }

    public function complete(int $id): bool
    {
        return $this->change_status($id, self::STATUS_COMPLETED, 'completed_at');
    }

    public function no_show(int $id): bool
    {
        return $this->change_status($id, self::STATUS_NO_SHOW);
    }

    /**
     * Status setzen. Hook `booking_status_<status>` (Benachrichtigungen) nur bei echter Änderung,
     * damit wiederholte Klicks bzw. Sammelaktionen keine doppelten Mails auslösen.
     */
    public function set_status(int $id, string $newStatus): bool
    {
        $tsColumn = match ($newStatus) {
            self::STATUS_CONFIRMED => 'confirmed_at',
            self::STATUS_CANCELLED => 'cancelled_at',
            self::STATUS_COMPLETED => 'completed_at',
            default                => null,
        };

        if (!array_key_exists($newStatus, self::status_labels())) {
            return false;
        }

        return $this->change_status($id, $newStatus, $tsColumn);
    }

    private function change_status(int $id, string $newStatus, ?string $tsColumn = null): bool
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $allowedTs = ['confirmed_at', 'cancelled_at', 'completed_at'];
        $sql = "UPDATE {$p}bookings SET status = ?";
        if ($tsColumn !== null && in_array($tsColumn, $allowedTs, true)) {
            $sql .= ", {$tsColumn} = NOW()";
        }
        $sql .= ' WHERE id = ? AND status <> ?';

        $stmt = $db->prepare($sql);
        $stmt->execute([$newStatus, $id, $newStatus]);
        $changed = $stmt->rowCount() > 0;

        if ($changed && class_exists('CMS\Hooks')) {
            \CMS\Hooks::doAction("booking_status_{$newStatus}", $id);
        }

        return $changed;
    }

    public function delete(int $id): bool
    {
        return $this->delete_many([$id]) > 0;
    }

    /**
     * Mehrere Buchungen samt Meta-Daten löschen.
     *
     * @param list<int> $ids
     */
    public function delete_many(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $db->prepare("DELETE FROM {$p}booking_meta WHERE booking_id IN ({$placeholders})")->execute($ids);
        $stmt = $db->prepare("DELETE FROM {$p}bookings WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    public function update_internal_notes(int $id, string $notes): bool
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare("UPDATE {$db->getPrefix()}bookings SET internal_notes = ? WHERE id = ?");
        $stmt->execute([$notes !== '' ? $notes : null, $id]);

        return true;
    }

    /* ================================================================== */
    /*  Meta                                                               */
    /* ================================================================== */

    public function set_meta(int $bookingId, string $key, ?string $value): void
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $db->prepare(
            "INSERT INTO {$p}booking_meta (booking_id, meta_key, meta_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
        )->execute([$bookingId, $key, $value]);
    }

    public function get_meta(int $bookingId, string $key, string $default = ''): string
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT meta_value FROM {$db->getPrefix()}booking_meta WHERE booking_id = ? AND meta_key = ?"
        );
        $stmt->execute([$bookingId, $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : $default;
    }

    public function get_all_meta(int $bookingId): array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT meta_key, meta_value FROM {$db->getPrefix()}booking_meta WHERE booking_id = ?"
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /* ================================================================== */
    /*  Gebuchte Zeiträume (für Availability-Check)                        */
    /* ================================================================== */

    /**
     * Bereits gebuchte Zeiträume an einem Tag.
     *
     * @return array  [['start' => 'HH:MM', 'end' => 'HH:MM'], …]
     */
    public function get_booked_ranges(int $providerId, string $date): array
    {
        return $this->get_booked_ranges_between($providerId, $date, $date)[$date] ?? [];
    }

    /**
     * Gebuchte Zeiträume eines Zeitraums in einer Abfrage (statt einer Abfrage je Tag).
     *
     * @return array<string, list<array{start: string, end: string}>> Datum => Zeiträume
     */
    public function get_booked_ranges_between(int $providerId, string $from, string $to): array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT booking_date, start_time, end_time
             FROM {$db->getPrefix()}bookings
             WHERE provider_id = ? AND booking_date BETWEEN ? AND ? AND status IN (?, ?)
             ORDER BY booking_date ASC, start_time ASC"
        );
        $stmt->execute([$providerId, $from, $to, self::STATUS_PENDING, self::STATUS_CONFIRMED]);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(string) $row['booking_date']][] = [
                'start' => substr((string) $row['start_time'], 0, 5),
                'end'   => substr((string) $row['end_time'], 0, 5),
            ];
        }

        return $map;
    }

    public function count_active_for_date(int $providerId, string $date, int $serviceId = 0): int
    {
        return $this->count_active_between($providerId, $date, $date, $serviceId)[$date] ?? 0;
    }

    /**
     * Aktive Buchungen je Tag in einer Abfrage.
     *
     * @return array<string, int> Datum => Anzahl
     */
    public function count_active_between(int $providerId, string $from, string $to, int $serviceId = 0): array
    {
        $db = \CMS\Database::instance();
        $sql = "SELECT booking_date, COUNT(*) AS cnt
                FROM {$db->getPrefix()}bookings
                WHERE provider_id = ? AND booking_date BETWEEN ? AND ? AND status IN (?, ?)";
        $params = [$providerId, $from, $to, self::STATUS_PENDING, self::STATUS_CONFIRMED];

        if ($serviceId > 0) {
            $sql .= ' AND service_id = ?';
            $params[] = $serviceId;
        }
        $sql .= ' GROUP BY booking_date';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(string) $row['booking_date']] = (int) $row['cnt'];
        }

        return $map;
    }

    /* ================================================================== */
    /*  Statistiken                                                        */
    /* ================================================================== */

    public function get_stats(): array
    {
        static $stats = null;
        if (is_array($stats)) {
            return $stats;
        }

        $row = [];
        try {
            $db = \CMS\Database::instance();
            $p  = $db->getPrefix();
            $stmt = $db->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = ? AND booking_date >= CURDATE() THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN booking_date = CURDATE() AND status IN (?, ?) THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN booking_date >= CURDATE() AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status IN (?, ?) THEN 1 ELSE 0 END) AS week
                 FROM {$p}bookings"
            );
            $stmt->execute([
                self::STATUS_PENDING, self::STATUS_CONFIRMED,
                self::STATUS_PENDING, self::STATUS_CONFIRMED,
                self::STATUS_PENDING, self::STATUS_CONFIRMED,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $row = [];
        }

        return $stats = [
            'total'     => (int) ($row['total'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'today'     => (int) ($row['today'] ?? 0),
            'week'      => (int) ($row['week'] ?? 0),
        ];
    }

    /**
     * Nächste anstehende Termine (Dashboard).
     *
     * @return list<array<string, mixed>>
     */
    public function get_upcoming(int $limit = 8): array
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT b.id, b.customer_name, b.booking_date, b.start_time, b.end_time, b.status,
                    bp.display_name AS provider_name, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.booking_date >= CURDATE() AND b.status IN (?, ?)
             ORDER BY b.booking_date ASC, b.start_time ASC
             LIMIT " . max(1, min(50, $limit))
        );
        $stmt->execute([self::STATUS_PENDING, self::STATUS_CONFIRMED]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buchungen eines Users (für Member-Bereich).
     */
    public function get_by_user(int $userId, int $offset = 0, int $limit = 20): array
    {
        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $stmt = $db->prepare(
            'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bp.timezone AS provider_timezone,
                    bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.user_id = ?
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT " . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buchungen, bei denen der User als Provider hinterlegt ist.
     */
    public function get_by_provider_user(int $userId, int $offset = 0, int $limit = 20, string $status = ''): array
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        $sql = 'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bs.title AS service_title
                FROM {$p}bookings b
                INNER JOIN {$p}booking_providers bp ON bp.id = b.provider_id AND bp.user_id = ?
                LEFT JOIN {$p}booking_services bs ON bs.id = b.service_id
                WHERE 1=1";
        $params = [$userId];

        if ($status !== '' && array_key_exists($status, self::status_labels())) {
            $sql    .= ' AND b.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY b.booking_date DESC, b.start_time DESC LIMIT ' . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /* ================================================================== */
    /*  Erinnerungen                                                       */
    /* ================================================================== */

    /**
     * Bestätigte Termine der nächsten Tage ohne versendete Erinnerung (Feinfilter nach Uhrzeit im Aufrufer).
     *
     * @return list<array<string, mixed>>
     */
    public function get_reminder_candidates(int $hours, int $limit = 200): array
    {
        $days = max(1, (int) ceil($hours / 24) + 1);
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bp.email AS provider_email,
                    bp.timezone AS provider_timezone, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.reminder_sent = 0 AND b.status = ?
               AND b.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$days} DAY)
             ORDER BY b.booking_date ASC, b.start_time ASC
             LIMIT " . max(1, min(1000, $limit))
        );
        $stmt->execute([self::STATUS_CONFIRMED]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function mark_reminder_sent(int $id): void
    {
        $db = \CMS\Database::instance();
        $db->prepare("UPDATE {$db->getPrefix()}bookings SET reminder_sent = 1 WHERE id = ?")->execute([$id]);
    }

    /* ================================================================== */
    /*  Löschfrist (DataRetentionService)                                  */
    /* ================================================================== */

    /** Buchungen, deren Termin mehr als $days Tage zurückliegt. */
    public function count_older_than(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$db->getPrefix()}bookings WHERE booking_date < ?");
        $stmt->execute([date('Y-m-d', strtotime('-' . $days . ' days'))]);

        return (int) $stmt->fetchColumn();
    }

    /** Löscht abgelaufene Buchungen in Blöcken zu 500 (samt Meta). */
    public function prune_older_than(int $days, int $maxBatches = 20): int
    {
        if ($days <= 0) {
            return 0;
        }
        $db     = \CMS\Database::instance();
        $cutoff = date('Y-m-d', strtotime('-' . $days . ' days'));
        $total  = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $stmt = $db->prepare("SELECT id FROM {$db->getPrefix()}bookings WHERE booking_date < ? ORDER BY id ASC LIMIT 500");
            $stmt->execute([$cutoff]);
            $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                break;
            }
            $total += $this->delete_many($ids);
            if (count($ids) < 500) {
                break;
            }
        }

        return $total;
    }

    /* ================================================================== */
    /*  DSGVO                                                              */
    /* ================================================================== */

    /**
     * Alle Buchungen einer Person (Konto oder E-Mail-Adresse).
     *
     * @return list<array<string, mixed>>
     */
    public function find_by_subject(int $userId, string $email): array
    {
        $email = trim($email);
        if ($userId <= 0 && $email === '') {
            return [];
        }

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $conditions = [];
        $params = [];
        if ($userId > 0) {
            $conditions[] = 'b.user_id = ?';
            $params[] = $userId;
        }
        if ($email !== '') {
            $conditions[] = 'b.customer_email = ?';
            $params[] = $email;
        }

        $stmt = $db->prepare(
            'SELECT ' . self::BOOKING_COLUMNS . ", bp.display_name AS provider_name, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE " . implode(' OR ', $conditions) . '
             ORDER BY b.booking_date DESC, b.id DESC
             LIMIT 5000'
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Personenbezogene Daten einer Person anonymisieren (Buchungen bleiben für Statistik/Abrechnung erhalten).
     */
    public function anonymize_subject(int $userId, string $email): int
    {
        $rows = $this->find_by_subject($userId, $email);
        $ids  = array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        if ($ids === []) {
            return 0;
        }

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $db->prepare(
            "UPDATE {$p}bookings SET
                customer_name = '[gelöscht]',
                customer_email = '',
                customer_phone = NULL,
                notes = NULL,
                internal_notes = NULL,
                user_id = NULL
             WHERE id IN ({$placeholders})"
        )->execute($ids);

        $db->prepare("DELETE FROM {$p}booking_meta WHERE booking_id IN ({$placeholders})")->execute($ids);

        return count($ids);
    }

    /** Kompatibilität 3.x – Auskunft läuft über CMS_Booking_Privacy. */
    public function export_user_data(mixed $userId = null, mixed $email = null): void
    {
        if (class_exists('CMS_Booking_Privacy')) {
            CMS_Booking_Privacy::handle_export($userId, $email);
        }
    }

    /** Kompatibilität 3.x – Löschung läuft über CMS_Booking_Privacy. */
    public function delete_user_data(mixed $userId = null, mixed $email = null): void
    {
        if (class_exists('CMS_Booking_Privacy')) {
            CMS_Booking_Privacy::handle_delete($userId, $email);
        }
    }

    /* ================================================================== */
    /*  Helfer                                                             */
    /* ================================================================== */

    /**
     * Nicht erratbarer Zugangs-Token für Bestätigungsseite, ICS-Download und Selbst-Stornierung.
     * HMAC über ID und zufällige iCal-UID mit dem Core-Schlüssel (AUTH_KEY).
     */
    public static function access_token(int $bookingId, string $icalUid): string
    {
        return hash_hmac('sha256', $bookingId . ':' . $icalUid, self::token_secret());
    }

    public static function verify_access_token(int $bookingId, string $icalUid, mixed $token): bool
    {
        if (!is_string($token) || $token === '' || $icalUid === '') {
            return false;
        }

        if (hash_equals(self::access_token($bookingId, $icalUid), $token)) {
            return true;
        }

        // Links aus 3.x (Schlüssel aus ABSPATH/SITE_URL) bleiben gültig.
        $legacySecret = hash('sha256', (defined('ABSPATH') ? (string) ABSPATH : __DIR__) . '|' . (defined('SITE_URL') ? (string) SITE_URL : ''));

        return hash_equals(hash_hmac('sha256', $bookingId . ':' . $icalUid, $legacySecret), $token);
    }

    private static function token_secret(): string
    {
        foreach (['CMS_SECRET_KEY', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'NONCE_KEY'] as $constant) {
            if (defined($constant)) {
                $value = (string) constant($constant);
                if (strlen($value) >= 16) {
                    return 'cms-booking|' . $value;
                }
            }
        }

        return hash('sha256', (defined('ABSPATH') ? (string) ABSPATH : __DIR__) . '|' . (defined('SITE_URL') ? (string) SITE_URL : ''));
    }

    private function generate_ical_uid(): string
    {
        $host = defined('SITE_URL') ? (string) (parse_url((string) SITE_URL, PHP_URL_HOST) ?: 'localhost') : 'localhost';
        return bin2hex(random_bytes(16)) . '@' . $host;
    }
}

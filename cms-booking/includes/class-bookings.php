<?php
/**
 * CMS Booking – Buchungen
 *
 * CRUD, Statusübergänge, DSGVO-Export/-Löschung.
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

    /** Platzhalter-Adresse anonymisierter Buchungen. */
    public const ANONYMIZED_EMAIL = 'anonymisiert@invalid';

    /** Spalten einer Buchung (statt b.*). */
    private const COLUMNS = 'b.id, b.provider_id, b.service_id, b.user_id, b.contact_submission_id, b.customer_name, b.customer_email, b.customer_phone, b.booking_date, b.start_time, b.end_time, b.duration_min, b.location_type, b.meeting_url, b.price_cents, b.currency, b.status, b.notes, b.internal_notes, b.ical_uid, b.reminder_sent, b.cancelled_at, b.confirmed_at, b.completed_at, b.created_at, b.updated_at';

    private function columns(): string
    {
        return self::COLUMNS;
    }

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

        $icalUid = $this->generate_ical_uid();

        $fields = [
            'provider_id'           => (int) $data['provider_id'],
            'service_id'            => isset($data['service_id'])  ? (int) $data['service_id']  : null,
            'user_id'               => isset($data['user_id'])     ? (int) $data['user_id']     : null,
            'contact_submission_id' => isset($data['contact_submission_id']) ? (int) $data['contact_submission_id'] : null,
            'customer_name'         => (string) $data['customer_name'],
            'customer_email'        => (string) $data['customer_email'],
            'customer_phone'        => isset($data['customer_phone']) ? (string) $data['customer_phone'] : null,
            'booking_date'          => (string) $data['booking_date'],
            'start_time'            => (string) $data['start_time'],
            'end_time'              => (string) $data['end_time'],
            'duration_min'          => (int) ($data['duration_min'] ?? 60),
            'location_type'         => (string) ($data['location_type'] ?? 'online'),
            'meeting_url'           => isset($data['meeting_url']) ? (string) $data['meeting_url'] : null,
            'price_cents'           => (int) ($data['price_cents'] ?? 0),
            'currency'              => (string) ($data['currency'] ?? 'EUR'),
            'status'                => self::STATUS_PENDING,
            'notes'                 => isset($data['notes']) ? (string) $data['notes'] : null,
            'internal_notes'        => isset($data['internal_notes']) ? (string) $data['internal_notes'] : null,
            'ical_uid'              => $icalUid,
        ];

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO {$p}bookings ({$cols}) VALUES ({$ph})")
            ->execute(array_values($fields));

        $bookingId = (int) $db->getPdo()->lastInsertId();

        // Meta-Daten speichern
        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $this->set_meta($bookingId, $key, $value);
            }
        }

        // Hook auslösen
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
            "SELECT {$this->columns()}, bp.display_name AS provider_name, bp.slug AS provider_slug,
                    bp.email AS provider_email, bp.source_plugin,
                    bs.title AS service_title
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
     * Buchungen paginiert laden.
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
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        $sql = "SELECT {$this->columns()}, bp.display_name AS provider_name, bp.slug AS provider_slug,
                       bs.title AS service_title
                FROM {$p}bookings b
                LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
                LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
                WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql    .= ' AND b.status = ?';
            $params[] = $status;
        }
        if ($providerId > 0) {
            $sql    .= ' AND b.provider_id = ?';
            $params[] = $providerId;
        }
        if ($dateFrom !== '') {
            $sql    .= ' AND b.booking_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql    .= ' AND b.booking_date <= ?';
            $params[] = $dateTo;
        }
        if ($search !== '') {
            $sql    .= ' AND (b.customer_name LIKE ? OR b.customer_email LIKE ? OR bp.display_name LIKE ?)';
            $like     = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY b.booking_date DESC, b.start_time DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(string $status = '', string $search = '', int $providerId = 0): int
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        $sql = "SELECT COUNT(*) FROM {$p}bookings b LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql    .= ' AND b.status = ?';
            $params[] = $status;
        }
        if ($providerId > 0) {
            $sql    .= ' AND b.provider_id = ?';
            $params[] = $providerId;
        }
        if ($search !== '') {
            $sql    .= ' AND (b.customer_name LIKE ? OR b.customer_email LIKE ? OR bp.display_name LIKE ?)';
            $like     = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
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

    private function change_status(int $id, string $newStatus, ?string $tsColumn = null): bool
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $sql    = "UPDATE {$p}bookings SET status = ?";
        $params = [$newStatus];

        if ($tsColumn !== null) {
            $sql    .= ", {$tsColumn} = NOW()";
        }
        $sql    .= ' WHERE id = ?';
        $params[] = $id;

        $db->prepare($sql)->execute($params);

        if (class_exists('CMS\Hooks')) {
            \CMS\Hooks::doAction("booking_status_{$newStatus}", $id);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare("DELETE FROM {$db->getPrefix()}bookings WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
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
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT start_time, end_time
             FROM {$db->getPrefix()}bookings
             WHERE provider_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed')"
        );
        $stmt->execute([$providerId, $date]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn($r) => [
            'start' => substr($r['start_time'], 0, 5),
            'end'   => substr($r['end_time'], 0, 5),
        ], $rows);
    }

    public function count_active_for_date(int $providerId, string $date, int $serviceId = 0): int
    {
        $db = \CMS\Database::instance();
        $sql = "SELECT COUNT(*)
                FROM {$db->getPrefix()}bookings
                WHERE provider_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed')";
        $params = [$providerId, $date];

        if ($serviceId > 0) {
            $sql .= ' AND service_id = ?';
            $params[] = $serviceId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /* ================================================================== */
    /*  Statistiken                                                        */
    /* ================================================================== */

    /* ================================================================== */
    /*  Erinnerungen, Aufbewahrung, Export (4.0.0)                         */
    /* ================================================================== */

    /**
     * Bestätigte Termine, die innerhalb der nächsten $hours Stunden beginnen und noch keine Erinnerung erhalten haben.
     *
     * @return list<array<string,mixed>>
     */
    public function get_due_reminders(int $hours, int $limit = 100): array
    {
        $hours = max(1, min(336, $hours));
        $limit = max(1, min(500, $limit));
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT {$this->columns()}, bp.display_name AS provider_name, bp.email AS provider_email, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.status = ? AND b.reminder_sent = 0
               AND TIMESTAMP(b.booking_date, b.start_time) > NOW()
               AND TIMESTAMP(b.booking_date, b.start_time) <= (NOW() + INTERVAL ? HOUR)
             ORDER BY b.booking_date ASC, b.start_time ASC
             LIMIT {$limit}"
        );
        $stmt->execute([self::STATUS_CONFIRMED, $hours]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function mark_reminder_sent(int $id): void
    {
        $db = \CMS\Database::instance();
        $db->prepare("UPDATE {$db->getPrefix()}bookings SET reminder_sent = 1 WHERE id = ?")->execute([$id]);
    }

    /** Anstehende bestätigte bzw. offene Termine (Dashboard). */
    public function get_upcoming(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT {$this->columns()}, bp.display_name AS provider_name, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.status IN (?, ?) AND b.booking_date >= CURDATE()
             ORDER BY b.booking_date ASC, b.start_time ASC
             LIMIT {$limit}"
        );
        $stmt->execute([self::STATUS_PENDING, self::STATUS_CONFIRMED]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Offene Anfragen, deren Termin bereits begonnen hat (Systemhinweis). */
    public function count_stale_pending(): int
    {
        $db = \CMS\Database::instance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$db->getPrefix()}bookings WHERE status = ? AND booking_date < CURDATE()");
        $stmt->execute([self::STATUS_PENDING]);

        return (int) $stmt->fetchColumn();
    }

    /** Abgeschlossene/stornierte Buchungen älter als $days Tage, die noch Kundendaten enthalten. */
    public function count_anonymizable(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $db = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM {$db->getPrefix()}bookings
             WHERE booking_date < (CURDATE() - INTERVAL ? DAY) AND status IN (?, ?, ?) AND customer_email <> ?"
        );
        $stmt->execute([$days, self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_NO_SHOW, self::ANONYMIZED_EMAIL]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Anonymisiert Kundendaten alter, abgeschlossener Buchungen (Statistik bleibt erhalten).
     * Liefert die Anzahl anonymisierter Buchungen.
     */
    public function anonymize_older_than(int $days, int $batch = 500): int
    {
        if ($days <= 0) {
            return 0;
        }
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $batch = max(50, min(2000, $batch));
        $total = 0;
        for ($i = 0; $i < 40; $i++) {
            $select = $db->prepare(
                "SELECT id FROM {$p}bookings
                 WHERE booking_date < (CURDATE() - INTERVAL ? DAY) AND status IN (?, ?, ?) AND customer_email <> ?
                 LIMIT {$batch}"
            );
            $select->execute([$days, self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_NO_SHOW, self::ANONYMIZED_EMAIL]);
            $ids = array_map('intval', $select->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                break;
            }
            $in = implode(', ', array_fill(0, count($ids), '?'));
            $db->prepare(
                "UPDATE {$p}bookings SET customer_name = ?, customer_email = ?, customer_phone = NULL, notes = NULL, user_id = NULL
                 WHERE id IN ({$in})"
            )->execute(array_merge(['Anonymisiert', self::ANONYMIZED_EMAIL], $ids));
            $db->prepare("DELETE FROM {$p}booking_meta WHERE booking_id IN ({$in})")->execute($ids);
            $total += count($ids);
            if (count($ids) < $batch) {
                break;
            }
        }

        return $total;
    }

    /**
     * Export-Zeilen (gefiltert) für CSV.
     *
     * @return list<array<string,mixed>>
     */
    public function export_rows(string $status = '', string $search = '', int $providerId = 0, int $limit = 5000): array
    {
        return $this->get_all(0, max(1, min(20000, $limit)), $status, $search, $providerId);
    }

    public function get_stats(): array
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN booking_date = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN booking_date >= CURDATE() AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week
             FROM {$p}bookings"
        );
        $stmt->execute([self::STATUS_PENDING, self::STATUS_CONFIRMED]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'today'     => (int) ($row['today'] ?? 0),
            'week'      => (int) ($row['week'] ?? 0),
        ];
    }

    /**
     * Buchungen eines Users (für Member-Bereich).
     */
    public function get_by_user(int $userId, int $offset = 0, int $limit = 20): array
    {
        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT {$this->columns()}, bp.display_name AS provider_name, bs.title AS service_title
             FROM {$p}bookings b
             LEFT JOIN {$p}booking_providers bp ON bp.id = b.provider_id
             LEFT JOIN {$p}booking_services  bs ON bs.id = b.service_id
             WHERE b.user_id = ?
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Buchungen, bei denen der User als Provider hinterlegt ist.
     */
    public function get_by_provider_user(int $userId, int $offset = 0, int $limit = 20, string $status = ''): array
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        $sql = "SELECT {$this->columns()}, bp.display_name AS provider_name, bs.title AS service_title
                FROM {$p}bookings b
                INNER JOIN {$p}booking_providers bp ON bp.id = b.provider_id AND bp.user_id = ?
                LEFT JOIN {$p}booking_services bs ON bs.id = b.service_id
                WHERE 1=1";
        $params = [$userId];

        if ($status !== '') {
            $sql    .= ' AND b.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY b.booking_date DESC, b.start_time DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ================================================================== */
    /*  DSGVO                                                              */
    /* ================================================================== */

    public function export_user_data(mixed $userId = null): void
    {
        // Datenschutzanfragen ohne Konto liefern user_id = NULL (Hook: user_id, email).
        $userId = is_numeric($userId) ? (int) $userId : 0;
        if ($userId <= 0) {
            return;
        }

        $bookings = $this->get_by_user($userId, 0, 999);
        if (empty($bookings)) {
            return;
        }

        $export = ['bookings' => []];
        foreach ($bookings as $b) {
            $export['bookings'][] = [
                'id'             => $b['id'],
                'provider'       => $b['provider_name'] ?? '',
                'service'        => $b['service_title'] ?? '',
                'date'           => $b['booking_date'],
                'time'           => $b['start_time'] . ' – ' . $b['end_time'],
                'status'         => $b['status'],
                'customer_name'  => $b['customer_name'],
                'customer_email' => $b['customer_email'],
                'created_at'     => $b['created_at'],
            ];
        }

        if (class_exists('CMS\Hooks')) {
            \CMS\Hooks::doAction('dsgvo_export_data_booking', $userId, $export);
        }
    }

    public function delete_user_data(mixed $userId = null): void
    {
        // Datenschutzanfragen ohne Konto liefern user_id = NULL (Hook: user_id, email).
        $userId = is_numeric($userId) ? (int) $userId : 0;
        if ($userId <= 0) {
            return;
        }

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        // Booking-IDs des Users sammeln BEVOR wir anonymisieren
        $stmt = $db->prepare("SELECT id FROM {$p}bookings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $bookingIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Buchungen des Users anonymisieren
        $db->prepare(
            "UPDATE {$p}bookings SET
                customer_name = '[gelöscht]',
                customer_email = '',
                customer_phone = NULL,
                notes = NULL,
                user_id = NULL
             WHERE user_id = ?"
        )->execute([$userId]);

        // Meta gezielt für die Buchungen des Users löschen
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $db->prepare(
                "DELETE FROM {$p}booking_meta WHERE booking_id IN ({$placeholders})"
            )->execute($bookingIds);
        }
    }

    /* ================================================================== */
    /*  Helfer                                                             */
    /* ================================================================== */

    private function generate_ical_uid(): string
    {
        $host = defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : 'localhost';
        return bin2hex(random_bytes(16)) . '@' . $host;
    }
}

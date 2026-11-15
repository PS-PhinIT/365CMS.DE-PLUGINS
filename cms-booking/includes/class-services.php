<?php
/**
 * CMS Booking – Service-Verwaltung
 *
 * CRUD für buchbare Leistungen je Anbieter.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Services
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /** Alle Spalten der Leistungs-Tabelle (explizit statt SELECT *). */
    private const COLUMNS = 'id, provider_id, title, slug, description, duration_min, buffer_min, max_bookings, price_cents,
        location_type, meeting_url, booking_type, contact_template, status, sort_order, settings_json, created_at, updated_at';

    public const STATUSES = ['active', 'inactive'];

    /* ================================================================== */
    /*  CRUD                                                               */
    /* ================================================================== */

    public function create(array $data): int
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        if (empty($data['provider_id']) || empty($data['title'])) {
            throw new \InvalidArgumentException('provider_id und title sind Pflichtfelder.');
        }

        $slug = $this->generate_slug(
            (string) $data['title'],
            (int) $data['provider_id']
        );

        $fields = [
            'provider_id'      => (int) $data['provider_id'],
            'title'            => (string) $data['title'],
            'slug'             => $slug,
            'description'      => (string) ($data['description']      ?? ''),
            'duration_min'     => (int) ($data['duration_min']        ?? 60),
            'buffer_min'       => (int) ($data['buffer_min']          ?? 15),
            'max_bookings'     => (int) ($data['max_bookings']        ?? 1),
            'price_cents'      => (int) ($data['price_cents']         ?? 0),
            'location_type'    => (string) ($data['location_type']    ?? 'online'),
            'meeting_url'      => isset($data['meeting_url'])         ? (string) $data['meeting_url'] : null,
            'booking_type'     => (string) ($data['booking_type']     ?? 'confirmation'),
            'contact_template' => isset($data['contact_template'])    ? (string) $data['contact_template'] : null,
            'status'           => (string) ($data['status']           ?? 'active'),
            'sort_order'       => (int) ($data['sort_order']          ?? 0),
            'settings_json'    => isset($data['settings_json'])       ? (string) $data['settings_json'] : null,
        ];

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO {$p}booking_services ({$cols}) VALUES ({$ph})")
            ->execute(array_values($fields));

        return (int) $db->getPdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $existing = $this->get($id);
        if (!$existing) {
            return false;
        }

        $allowed = [
            'title', 'description', 'duration_min', 'buffer_min', 'max_bookings',
            'price_cents', 'location_type', 'meeting_url', 'booking_type',
            'contact_template', 'status', 'sort_order', 'settings_json',
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

        if (isset($data['title']) && $data['title'] !== $existing['title']) {
            $slug     = $this->generate_slug($data['title'], (int) $existing['provider_id'], $id);
            $sets[]   = 'slug = ?';
            $params[] = $slug;
        }

        if (empty($sets)) {
            return true;
        }

        $params[] = $id;
        $db->prepare("UPDATE {$p}booking_services SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($params);
        return true;
    }

    public function get(int $id): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare('SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_services WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function get_by_slug(int $providerId, string $slug): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_services WHERE provider_id = ? AND slug = ?"
        );
        $stmt->execute([$providerId, $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alle Services eines Anbieters.
     */
    public function get_by_provider(int $providerId, string $status = 'active'): array
    {
        $db  = \CMS\Database::instance();
        $sql = 'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_services WHERE provider_id = ?";
        $params = [$providerId];

        if ($status !== '') {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function build_filter_sql(string $search, int $providerId, string $status): array
    {
        $sql    = ' WHERE 1=1';
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (s.title LIKE ? OR bp.display_name LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($providerId > 0) {
            $sql     .= ' AND s.provider_id = ?';
            $params[] = $providerId;
        }
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $sql     .= ' AND s.status = ?';
            $params[] = $status;
        }

        return [$sql, $params];
    }

    /**
     * Alle Services (paginiert, Admin).
     */
    public function get_all(int $offset = 0, int $limit = 20, string $search = '', int $providerId = 0, string $status = ''): array
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        [$where, $params] = $this->build_filter_sql($search, $providerId, $status);

        $stmt = $db->prepare(
            "SELECT s.id, s.provider_id, s.title, s.slug, s.description, s.duration_min, s.buffer_min, s.price_cents,
                    s.location_type, s.booking_type, s.status, s.sort_order, bp.display_name AS provider_name,
                    bp.slug AS provider_slug, bp.currency AS provider_currency
             FROM {$p}booking_services s
             LEFT JOIN {$p}booking_providers bp ON bp.id = s.provider_id{$where}
             ORDER BY bp.display_name ASC, s.sort_order ASC, s.title ASC
             LIMIT " . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function count(string $search = '', int $providerId = 0, string $status = ''): int
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        [$where, $params] = $this->build_filter_sql($search, $providerId, $status);

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM {$p}booking_services s
             LEFT JOIN {$p}booking_providers bp ON bp.id = s.provider_id{$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        return $this->delete_many([$id]) > 0;
    }

    /**
     * Leistungen löschen; Buchungen bleiben erhalten (service_id = NULL, wie FK ON DELETE SET NULL).
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
        $db->prepare("UPDATE {$p}bookings SET service_id = NULL WHERE service_id IN ({$placeholders})")->execute($ids);
        $stmt = $db->prepare("DELETE FROM {$p}booking_services WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * @param list<int> $ids
     */
    public function set_status_many(array $ids, string $status): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || !in_array($status, self::STATUSES, true)) {
            return 0;
        }
        $db = \CMS\Database::instance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE {$db->getPrefix()}booking_services SET status = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$status], $ids));

        return $stmt->rowCount();
    }

    /**
     * Öffentliche Leistungen aktiver Anbieter, deren Titel/Beschreibung/Anbietername alle Wörter enthalten.
     *
     * @param list<string> $words
     * @return list<array<string, mixed>>
     */
    public function search_public(array $words, int $limit = 10): array
    {
        $words = array_values(array_filter(array_map('trim', $words), static fn (string $w): bool => $w !== ''));
        if ($words === []) {
            return [];
        }

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $conditions = [];
        $params = [];
        foreach (array_slice($words, 0, 8) as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $conditions[] = '(s.title LIKE ? OR s.description LIKE ? OR bp.display_name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $stmt = $db->prepare(
            "SELECT s.id, s.title, s.slug, s.description, s.updated_at, bp.display_name AS provider_name, bp.slug AS provider_slug
             FROM {$p}booking_services s
             INNER JOIN {$p}booking_providers bp ON bp.id = s.provider_id AND bp.status = 'active'
             WHERE s.status = 'active' AND " . implode(' AND ', $conditions) . '
             ORDER BY s.updated_at DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Preisformatierung.
     */
    public static function format_price(int $cents, string $currency = ''): string
    {
        if ($cents === 0) {
            return 'Kostenlos';
        }
        if ($currency === '' || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $currency = class_exists('CMS_Booking_Settings') ? CMS_Booking_Settings::currency() : 'EUR';
        }
        $amount = number_format($cents / 100, 2, ',', '.');
        return "{$amount} {$currency}";
    }

    /**
     * Dauer als lesbarer String.
     */
    public static function format_duration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} Min.";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h} Std. {$m} Min." : "{$h} Std.";
    }

    /* ================================================================== */
    /*  Slug                                                               */
    /* ================================================================== */

    private function generate_slug(string $title, int $providerId, int $excludeId = 0): string
    {
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'],
            $slug
        );
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = (string) preg_replace('/-{2,}/', '-', $slug);
        if ($slug === '') {
            $slug = 'service';
        }

        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $base = $slug;
        $i    = 1;

        while (true) {
            $sql    = "SELECT COUNT(*) FROM {$p}booking_services WHERE provider_id = ? AND slug = ?";
            $params = [$providerId, $slug];
            if ($excludeId > 0) {
                $sql    .= ' AND id != ?';
                $params[] = $excludeId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() === 0) {
                break;
            }
            $slug = "{$base}-{$i}";
            $i++;
            if ($i > 500) {
                // Schutz vor Endlosschleife: zufälliges Suffix ist praktisch kollisionsfrei.
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $slug;
    }
}

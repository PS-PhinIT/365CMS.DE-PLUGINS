<?php
/**
 * CMS Booking – Provider-Verwaltung
 *
 * CRUD-Operationen für buchbare Anbieter (Experten, Speaker, Unternehmen …).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Providers
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    /** Alle Spalten der Anbieter-Tabelle (explizit statt SELECT *). */
    private const COLUMNS = 'id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio,
        contact_form_id, timezone, currency, status, settings_json, created_at, updated_at';

    /** Quell-Kennung manuell im Admin angelegter Anbieter. */
    public const SOURCE_MANUAL = 'cms-booking';

    public const STATUSES = ['active', 'inactive', 'pending'];

    /* ================================================================== */
    /*  CRUD                                                               */
    /* ================================================================== */

    /**
     * Anbieter anlegen oder aktualisieren (Upsert auf Basis von source_plugin + source_id).
     *
     * Beim Aktualisieren werden nur übergebene Felder geschrieben – ein Sync aus einem Quell-Plugin
     * überschreibt damit keine im Admin gepflegten Werte (Bio, Telefon, Zeitzone, Tageslimit …) mit NULL.
     *
     * @param  array  $data  Anbieter-Daten
     * @return int    Provider-ID
     */
    public function upsert(array $data): int
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        $required = ['source_plugin', 'source_id', 'display_name'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                throw new \InvalidArgumentException("Pflichtfeld fehlt: {$key}");
            }
        }

        // Prüfen ob bereits vorhanden
        $existing = $this->get_by_source(
            (string) $data['source_plugin'],
            (int)    $data['source_id']
        );

        $fields = $this->normalize_fields($data);
        $fields['slug'] = $this->generate_slug((string) $data['display_name'], $existing ? (int) $existing['id'] : 0);

        if ($existing) {
            $sets   = [];
            $params = [];
            foreach ($fields as $col => $val) {
                if ($col !== 'slug' && !array_key_exists($col, $data)) {
                    continue;
                }
                $sets[]   = "{$col} = ?";
                $params[] = $val;
            }
            $params[] = (int) $existing['id'];
            $db->prepare(
                "UPDATE {$p}booking_providers SET " . implode(', ', $sets) . " WHERE id = ?"
            )->execute($params);
            return (int) $existing['id'];
        }

        if (!array_key_exists('timezone', $data) && class_exists('CMS_Booking_Settings')) {
            $fields['timezone'] = CMS_Booking_Settings::timezone();
        }
        if (!array_key_exists('currency', $data) && class_exists('CMS_Booking_Settings')) {
            $fields['currency'] = CMS_Booking_Settings::currency();
        }

        $cols   = implode(', ', array_keys($fields));
        $ph     = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare(
            "INSERT INTO {$p}booking_providers ({$cols}) VALUES ({$ph})"
        )->execute(array_values($fields));
        return (int) $db->getPdo()->lastInsertId();
    }

    /**
     * Manuellen Anbieter anlegen (ohne Quell-Plugin).
     */
    public function create_manual(array $data): int
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare("SELECT COALESCE(MAX(source_id), 0) + 1 FROM {$db->getPrefix()}booking_providers WHERE source_plugin = ?");
        $stmt->execute([self::SOURCE_MANUAL]);
        $nextId = max(1, (int) $stmt->fetchColumn());

        return $this->upsert(array_merge($data, [
            'source_plugin' => self::SOURCE_MANUAL,
            'source_id'     => $nextId,
        ]));
    }

    /**
     * Admin-Bearbeitung eines Anbieters (nur übergebene Felder).
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->get($id);
        if (!$existing) {
            return false;
        }

        $fields = $this->normalize_fields($data);
        unset($fields['source_plugin'], $fields['source_id']);
        $sets   = [];
        $params = [];
        foreach ($fields as $col => $val) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[]   = "{$col} = ?";
            $params[] = $val;
        }
        if (isset($data['display_name']) && (string) $data['display_name'] !== (string) $existing['display_name']) {
            $sets[]   = 'slug = ?';
            $params[] = $this->generate_slug((string) $data['display_name'], $id);
        }
        if ($sets === []) {
            return true;
        }

        $params[] = $id;
        $db = \CMS\Database::instance();
        $db->prepare("UPDATE {$db->getPrefix()}booking_providers SET " . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize_fields(array $data): array
    {
        $status = (string) ($data['status'] ?? 'active');

        return [
            'user_id'         => isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null,
            'source_plugin'   => (string) ($data['source_plugin'] ?? ''),
            'source_id'       => (int) ($data['source_id'] ?? 0),
            'display_name'    => mb_substr((string) ($data['display_name'] ?? ''), 0, 255),
            'email'           => isset($data['email']) && $data['email'] !== '' ? mb_substr((string) $data['email'], 0, 255) : null,
            'phone'           => isset($data['phone']) && $data['phone'] !== '' ? mb_substr((string) $data['phone'], 0, 50) : null,
            'avatar_url'      => isset($data['avatar_url']) && $data['avatar_url'] !== '' ? mb_substr((string) $data['avatar_url'], 0, 500) : null,
            'bio'             => isset($data['bio']) && $data['bio'] !== '' ? (string) $data['bio'] : null,
            'contact_form_id' => isset($data['contact_form_id']) && (int) $data['contact_form_id'] > 0 ? (int) $data['contact_form_id'] : null,
            'timezone'        => (string) ($data['timezone'] ?? 'Europe/Berlin'),
            'currency'        => (string) ($data['currency'] ?? 'EUR'),
            'status'          => in_array($status, self::STATUSES, true) ? $status : 'active',
            'settings_json'   => isset($data['settings_json']) ? (string) $data['settings_json'] : null,
        ];
    }

    /**
     * Anbieter per ID laden.
     */
    public function get(int $id): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_providers WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Anbieter per Slug laden.
     */
    public function get_by_slug(string $slug): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_providers WHERE slug = ?"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Anbieter per Quell-Plugin + Quell-ID laden.
     */
    public function get_by_source(string $sourcePlugin, int $sourceId): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_providers
             WHERE source_plugin = ? AND source_id = ?"
        );
        $stmt->execute([$sourcePlugin, $sourceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alle Anbieter eines Quell-Plugins.
     */
    public function get_by_plugin(string $sourcePlugin, string $status = ''): array
    {
        $db  = \CMS\Database::instance();
        $sql = 'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_providers WHERE source_plugin = ?";
        $params = [$sourcePlugin];

        if ($status !== '') {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY display_name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function build_filter_sql(string $status, string $search, string $source = ''): array
    {
        $sql    = ' WHERE 1=1';
        $params = [];

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $sql     .= ' AND status = ?';
            $params[] = $status;
        }
        if ($source !== '') {
            $sql     .= ' AND source_plugin = ?';
            $params[] = $source;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (display_name LIKE ? OR email LIKE ? OR slug LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return [$sql, $params];
    }

    /**
     * Alle Anbieter (paginiert).
     */
    public function get_all(int $offset = 0, int $limit = 20, string $status = '', string $search = '', string $source = ''): array
    {
        $db = \CMS\Database::instance();
        [$where, $params] = $this->build_filter_sql($status, $search, $source);

        $stmt = $db->prepare(
            'SELECT ' . self::COLUMNS . " FROM {$db->getPrefix()}booking_providers{$where}
             ORDER BY display_name ASC LIMIT " . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Kompakte Auswahlliste (id, display_name) für Filter und Formulare.
     *
     * @return list<array{id: int, display_name: string, status: string}>
     */
    public function get_options(string $status = ''): array
    {
        $db  = \CMS\Database::instance();
        $sql = "SELECT id, display_name, status FROM {$db->getPrefix()}booking_providers";
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $stmt = $db->prepare($sql . ' ORDER BY display_name ASC LIMIT 1000');
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'display_name' => (string) $row['display_name'],
            'status' => (string) $row['status'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Anzahl Anbieter.
     */
    public function count(string $status = '', string $search = '', string $source = ''): int
    {
        $db = \CMS\Database::instance();
        [$where, $params] = $this->build_filter_sql($status, $search, $source);

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$db->getPrefix()}booking_providers{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Status ändern.
     */
    public function set_status(int $id, string $status): bool
    {
        return $this->set_status_many([$id], $status) > 0;
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
        $stmt = $db->prepare(
            "UPDATE {$db->getPrefix()}booking_providers SET status = ? WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$status], $ids));
        return $stmt->rowCount();
    }

    /**
     * Anbieter löschen.
     */
    public function delete(int $id): bool
    {
        return $this->delete_many([$id]) > 0;
    }

    /**
     * Anbieter samt Leistungen, Verfügbarkeiten, Buchungen und Buchungs-Meta löschen
     * (explizit, damit es auch ohne Foreign Keys konsistent bleibt).
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

        $db->prepare(
            "DELETE m FROM {$p}booking_meta m INNER JOIN {$p}bookings b ON b.id = m.booking_id WHERE b.provider_id IN ({$placeholders})"
        )->execute($ids);
        $db->prepare("DELETE FROM {$p}bookings WHERE provider_id IN ({$placeholders})")->execute($ids);
        $db->prepare("DELETE FROM {$p}booking_availability WHERE provider_id IN ({$placeholders})")->execute($ids);
        $db->prepare("DELETE FROM {$p}booking_services WHERE provider_id IN ({$placeholders})")->execute($ids);
        $stmt = $db->prepare("DELETE FROM {$p}booking_providers WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    /**
     * Öffentlich buchbare Anbieter mit mindestens einer aktiven Leistung (Sitemap).
     *
     * @return list<array{id: int, slug: string, display_name: string, updated_at: string}>
     */
    public function get_public_index(int $limit = 2000): array
    {
        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();
        $stmt = $db->prepare(
            "SELECT bp.id, bp.slug, bp.display_name, GREATEST(bp.updated_at, COALESCE(MAX(s.updated_at), bp.updated_at)) AS updated_at
             FROM {$p}booking_providers bp
             INNER JOIN {$p}booking_services s ON s.provider_id = bp.id AND s.status = 'active'
             WHERE bp.status = 'active'
             GROUP BY bp.id, bp.slug, bp.display_name, bp.updated_at
             ORDER BY bp.display_name ASC
             LIMIT " . max(1, min(10000, $limit))
        );
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /* ================================================================== */
    /*  Slug-Generierung                                                   */
    /* ================================================================== */

    private function generate_slug(string $name, int $excludeId = 0): string
    {
        $slug = mb_strtolower($name, 'UTF-8');

        // Deutsche Umlaute
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'],
            $slug
        );

        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = (string) preg_replace('/-{2,}/', '-', $slug);

        if ($slug === '') {
            $slug = 'provider';
        }

        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $base = $slug;
        $i    = 1;

        while (true) {
            $sql    = "SELECT COUNT(*) FROM {$p}booking_providers WHERE slug = ?";
            $params = [$slug];
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

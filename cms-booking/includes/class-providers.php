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

    /* ================================================================== */
    /*  CRUD                                                               */
    /* ================================================================== */

    /**
     * Anbieter anlegen oder aktualisieren (Upsert auf Basis von source_plugin + source_id).
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

        $slug = $this->generate_slug($data['display_name'], $existing ? (int) $existing['id'] : 0);

        $fields = [
            'user_id'         => isset($data['user_id'])         ? (int) $data['user_id']                      : null,
            'source_plugin'   => (string) $data['source_plugin'],
            'source_id'       => (int)    $data['source_id'],
            'display_name'    => (string) $data['display_name'],
            'slug'            => $slug,
            'email'           => isset($data['email']) ? (string) $data['email'] : null,
            'phone'           => isset($data['phone']) ? (string) $data['phone'] : null,
            'avatar_url'      => isset($data['avatar_url']) ? (string) $data['avatar_url'] : null,
            'bio'             => isset($data['bio']) ? (string) $data['bio'] : null,
            'contact_form_id' => isset($data['contact_form_id']) ? (int) $data['contact_form_id'] : null,
            'timezone'        => (string) ($data['timezone'] ?? 'Europe/Berlin'),
            'currency'        => (string) ($data['currency'] ?? 'EUR'),
            'status'          => (string) ($data['status']   ?? 'active'),
            'settings_json'   => isset($data['settings_json']) ? (string) $data['settings_json'] : null,
        ];

        if ($existing) {
            // Synchronisieren aktualisiert nur die Felder, die das Quell-Plugin liefert. Status, Zeitzone,
            // Währung, Einstellungen (Tageslimit) und der Slug bleiben in der Hand des Booking-Admins:
            // bisher aktivierte jeder Sync deaktivierte Anbieter wieder, leerte die Einstellungen und
            // änderte den Slug (öffentliche Buchungs-URL).
            $sourceOwned = ['user_id', 'display_name', 'email', 'phone', 'avatar_url', 'bio'];
            $fields = array_filter(
                $fields,
                static fn (string $col): bool => in_array($col, $sourceOwned, true) && array_key_exists($col, $data),
                ARRAY_FILTER_USE_KEY
            );
            if ($fields === []) {
                return (int) $existing['id'];
            }
            $sets   = [];
            $params = [];
            foreach ($fields as $col => $val) {
                $sets[]   = "{$col} = ?";
                $params[] = $val;
            }
            $params[] = (int) $existing['id'];
            $db->prepare(
                "UPDATE {$p}booking_providers SET " . implode(', ', $sets) . " WHERE id = ?"
            )->execute($params);
            return (int) $existing['id'];
        }

        $cols   = implode(', ', array_keys($fields));
        $ph     = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare(
            "INSERT INTO {$p}booking_providers ({$cols}) VALUES ({$ph})"
        )->execute(array_values($fields));
        return (int) $db->getPdo()->lastInsertId();
    }

    /**
     * Anbieter per ID laden.
     */
    public function get(int $id): ?array
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio, contact_form_id, timezone, currency, status, settings_json, created_at, updated_at FROM {$db->getPrefix()}booking_providers WHERE id = ?"
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
            "SELECT id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio, contact_form_id, timezone, currency, status, settings_json, created_at, updated_at FROM {$db->getPrefix()}booking_providers WHERE slug = ?"
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
            "SELECT id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio, contact_form_id, timezone, currency, status, settings_json, created_at, updated_at FROM {$db->getPrefix()}booking_providers
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
        $sql = "SELECT id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio, contact_form_id, timezone, currency, status, settings_json, created_at, updated_at FROM {$db->getPrefix()}booking_providers WHERE source_plugin = ?";
        $params = [$sourcePlugin];

        if ($status !== '') {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY display_name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Alle Anbieter (paginiert).
     */
    public function get_all(int $offset = 0, int $limit = 20, string $status = '', string $search = ''): array
    {
        $db  = \CMS\Database::instance();
        $p   = $db->getPrefix();
        $sql = "SELECT id, user_id, source_plugin, source_id, display_name, slug, email, phone, avatar_url, bio, contact_form_id, timezone, currency, status, settings_json, created_at, updated_at FROM {$p}booking_providers WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql    .= ' AND (display_name LIKE ? OR email LIKE ? OR slug LIKE ?)';
            $like     = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY display_name ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Anzahl Anbieter.
     */
    public function count(string $status = '', string $search = ''): int
    {
        $db  = \CMS\Database::instance();
        $sql = "SELECT COUNT(*) FROM {$db->getPrefix()}booking_providers WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql    .= ' AND (display_name LIKE ? OR email LIKE ? OR slug LIKE ?)';
            $like     = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Status ändern.
     */
    public function set_status(int $id, string $status): bool
    {
        $allowed = ['active', 'inactive', 'pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $db = \CMS\Database::instance();
        $stmt = $db->prepare(
            "UPDATE {$db->getPrefix()}booking_providers SET status = ? WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Anbieter löschen.
     */
    public function delete(int $id): bool
    {
        $db   = \CMS\Database::instance();
        $stmt = $db->prepare(
            "DELETE FROM {$db->getPrefix()}booking_providers WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** Quelle manuell im Booking-Admin angelegter Anbieter. */
    public const MANUAL_SOURCE = 'cms-booking';

    /**
     * Manuell gepflegten Anbieter anlegen oder aktualisieren (ohne Quell-Plugin).
     * Für synchronisierte Anbieter werden nur Status, Zeitzone, Währung und Kontaktfelder überschrieben.
     *
     * @param array<string,mixed> $data
     */
    public function save_manual(array $data, int $id = 0): int
    {
        $name = trim(strip_tags((string) ($data['display_name'] ?? '')));
        if ($name === '') {
            throw new \InvalidArgumentException('Bitte einen Namen angeben.');
        }

        $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
        $avatar = trim((string) ($data['avatar_url'] ?? ''));
        $isLocalPath = str_starts_with($avatar, '/') && !str_starts_with($avatar, '//') && !str_contains($avatar, '\\');
        $isHttpUrl = preg_match('#^https?://#i', $avatar) === 1 && filter_var($avatar, FILTER_VALIDATE_URL) !== false;
        $avatar = $isLocalPath || $isHttpUrl ? $avatar : null;
        $timezone = in_array((string) ($data['timezone'] ?? ''), timezone_identifiers_list(), true) ? (string) $data['timezone'] : 'Europe/Berlin';
        $currency = in_array((string) ($data['currency'] ?? ''), ['EUR', 'CHF', 'USD', 'GBP'], true) ? (string) $data['currency'] : 'EUR';
        $status = in_array((string) ($data['status'] ?? ''), ['active', 'inactive', 'pending'], true) ? (string) $data['status'] : 'active';
        $fields = [
            'display_name' => mb_substr($name, 0, 255),
            'email' => $email,
            'phone' => mb_substr(preg_replace('/[^0-9+()\/\s.-]/', '', (string) ($data['phone'] ?? '')) ?? '', 0, 50) ?: null,
            'avatar_url' => $avatar !== null ? mb_substr($avatar, 0, 500) : null,
            'bio' => mb_substr(trim(strip_tags((string) ($data['bio'] ?? ''))), 0, 5000) ?: null,
            'timezone' => $timezone,
            'currency' => $currency,
            'status' => $status,
        ];

        $db = \CMS\Database::instance();
        $p  = $db->getPrefix();

        if ($id > 0) {
            $existing = $this->get($id);
            if ($existing === null) {
                throw new \InvalidArgumentException('Anbieter nicht gefunden.');
            }
            if ((string) $existing['source_plugin'] === self::MANUAL_SOURCE) {
                $fields['slug'] = $this->generate_slug($name, $id);
            } else {
                // Name/Biografie/Bild stammen aus dem Quell-Plugin und werden dort gepflegt.
                unset($fields['display_name'], $fields['bio'], $fields['avatar_url']);
            }
            $sets = implode(', ', array_map(static fn (string $col): string => "{$col} = ?", array_keys($fields)));
            $db->prepare("UPDATE {$p}booking_providers SET {$sets} WHERE id = ?")->execute([...array_values($fields), $id]);

            return $id;
        }

        $nextSource = $db->prepare("SELECT COALESCE(MAX(source_id), 0) + 1 FROM {$p}booking_providers WHERE source_plugin = ?");
        $nextSource->execute([self::MANUAL_SOURCE]);
        $fields['source_plugin'] = self::MANUAL_SOURCE;
        $fields['source_id'] = (int) $nextSource->fetchColumn();
        $fields['slug'] = $this->generate_slug($name);

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO {$p}booking_providers ({$cols}) VALUES ({$ph})")->execute(array_values($fields));

        return (int) $db->getPdo()->lastInsertId();
    }

    /** Anzahl Anbieter je Status (Kennzahlen). */
    public function count_by_status(): array
    {
        $db = \CMS\Database::instance();
        $stmt = $db->prepare("SELECT status, COUNT(*) FROM {$db->getPrefix()}booking_providers GROUP BY status");
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []);
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
        }

        return $slug;
    }
}

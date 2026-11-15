<?php
/**
 * Datenzugriff für Promos, Platzierungen und Einstellungen.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Repository
{
    private static ?self $instance = null;

    /** @var array<string,string> */
    public const THEME_HOOK_OPTIONS = [
        'manual' => 'Nur manuell / Archiv',
        'body_start' => 'body_start – direkt nach <body>',
        'after_header' => 'after_header – unter dem Header',
        'home_content' => 'home_content – im Startseiten-Content',
        'before_footer' => 'before_footer – oberhalb des Footers',
    ];

    /** @var array<string,string> */
    public const STATUS_LABELS = [
        'draft' => 'Entwurf',
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
        'archived' => 'Archiviert',
    ];

    /** Alle Spalten einer Promo (Formular, Export). */
    private const PROMO_COLUMNS = 'pr.id, pr.placement_id, pr.title, pr.title_en, pr.slug, pr.teaser, pr.teaser_en, pr.content_html, pr.content_html_en,'
        . ' pr.target_url, pr.button_label, pr.button_label_en, pr.image_url, pr.status, pr.start_at, pr.end_at, pr.priority, pr.is_featured,'
        . ' pr.frequency_cap, pr.frequency_window_hours, pr.utm_source, pr.utm_medium, pr.utm_campaign, pr.utm_term, pr.utm_content,'
        . ' pr.impression_count, pr.click_count, pr.created_at, pr.updated_at';

    /** Spalten für Admin-Listen (ohne Langtexte). */
    private const PROMO_LIST_COLUMNS = 'pr.id, pr.placement_id, pr.title, pr.slug, pr.status, pr.start_at, pr.end_at, pr.priority, pr.is_featured,'
        . ' pr.target_url, pr.impression_count, pr.click_count, pr.updated_at';

    /** Spalten für die öffentliche Ausspielung. */
    private const PROMO_PUBLIC_COLUMNS = 'pr.id, pr.placement_id, pr.title, pr.title_en, pr.slug, pr.teaser, pr.teaser_en, pr.content_html, pr.content_html_en,'
        . ' pr.target_url, pr.button_label, pr.button_label_en, pr.image_url, pr.priority, pr.is_featured, pr.frequency_cap,'
        . ' pr.frequency_window_hours, pr.updated_at';

    private const PLACEMENT_COLUMNS = 'p.id, p.name, p.slug, p.description, p.status, p.theme_hook, p.hook_priority, p.max_items, p.created_at, p.updated_at';

    /** Bedingung „läuft gerade“ (Status aktiv und innerhalb der Laufzeit). */
    private const LIVE_CONDITION = "pr.status = 'active' AND (pr.start_at IS NULL OR pr.start_at <= NOW()) AND (pr.end_at IS NULL OR pr.end_at >= NOW())";

    /** Erlaubte Sortierungen der Promo-Liste (Whitelist für ORDER BY). */
    public const PROMO_SORTS = [
        'priority' => 'pr.priority DESC, pr.updated_at DESC, pr.id DESC',
        'updated' => 'pr.updated_at DESC, pr.id DESC',
        'title' => 'pr.title ASC, pr.id ASC',
        'clicks' => 'pr.click_count DESC, pr.id DESC',
        'impressions' => 'pr.impression_count DESC, pr.id DESC',
        'end' => 'pr.end_at IS NULL, pr.end_at ASC, pr.id DESC',
    ];

    /** @var array<string,string> */
    private const DEFAULT_SETTINGS = [
        'archive_title' => 'Promotions & Highlights',
        'archive_title_en' => 'Promotions & Highlights',
        'archive_description' => 'Zentrale Übersicht aktiver Kampagnen, CTA-Flächen und Teaser-Aktionen.',
        'archive_description_en' => 'Central overview of active campaigns, CTA slots, and teaser actions.',
        'default_button_label' => 'Mehr erfahren',
        'default_button_label_en' => 'Learn more',
        'default_target_behavior' => 'same_tab',
        'click_export_enabled' => '0',
        'click_export_webhook_url' => '',
        'archive_indexable' => '1',
        'retention_days' => '0',
    ];

    private \CMS\Database $db;
    private string $prefix;
    private ?array $settingsCache = null;
    private ?array $placementsCache = null;
    /** @var array<string,list<array<string,mixed>>>|null */
    private ?array $hookPlacementMap = null;
    /** @var array<string,list<array<string,mixed>>> */
    private array $activePromosCache = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->db = \CMS\Database::instance();
        $this->prefix = $this->db->getPrefix();
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    // ── Installation ────────────────────────────────────────────────────────

    public function seed_defaults(): void
    {
        $rows = [];
        $params = [];
        foreach (self::DEFAULT_SETTINGS as $key => $value) {
            $rows[] = '(?, ?)';
            $params[] = $key;
            $params[] = $value;
        }
        $stmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}promo_settings (setting_key, setting_value) VALUES " . implode(', ', $rows));
        $stmt->execute($params);

        $placementStmt = $this->db->prepare("SELECT id FROM {$this->prefix}promo_placements LIMIT 1");
        $placementStmt->execute();
        if ($placementStmt->fetchColumn() === false) {
            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promo_placements (name, slug, description, status, theme_hook, hook_priority, max_items) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Homepage Hero', 'homepage-hero', 'Prominente Hero-Fläche auf der Startseite.', 'active', 'after_header', 5, 1]);
            $stmt->execute(['Sidebar CTA', 'sidebar-cta', 'Seitliche CTA-Boxen und Conversion-Elemente.', 'active', 'before_footer', 20, 3]);
            $stmt->execute(['Home Content Highlights', 'home-content-highlights', 'Promo-Karten im Inhaltsbereich der Startseite.', 'active', 'home_content', 15, 2]);
        }

        $this->invalidate_read_caches();
    }

    // ── Kennzahlen ──────────────────────────────────────────────────────────

    /**
     * @return array<string,int>
     */
    public function get_dashboard_stats(): array
    {
        $empty = [
            'promos' => 0, 'active_promos' => 0, 'live_promos' => 0, 'featured_promos' => 0, 'expired_active' => 0,
            'active_without_target' => 0, 'drafts' => 0, 'placements' => 0, 'active_hook_placements' => 0,
            'impressions' => 0, 'clicks' => 0,
        ];

        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS promos,
                        COALESCE(SUM(pr.status = 'active'), 0) AS active_promos,
                        COALESCE(SUM(" . self::LIVE_CONDITION . "), 0) AS live_promos,
                        COALESCE(SUM(pr.is_featured = 1), 0) AS featured_promos,
                        COALESCE(SUM(pr.status = 'active' AND pr.end_at IS NOT NULL AND pr.end_at < NOW()), 0) AS expired_active,
                        COALESCE(SUM(pr.status = 'active' AND (pr.target_url IS NULL OR pr.target_url = '')), 0) AS active_without_target,
                        COALESCE(SUM(pr.status = 'draft'), 0) AS drafts,
                        COALESCE(SUM(pr.impression_count), 0) AS impressions,
                        COALESCE(SUM(pr.click_count), 0) AS clicks
                 FROM {$this->prefix}promos pr"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS placements, COALESCE(SUM(p.status = 'active' AND p.theme_hook <> 'manual'), 0) AS active_hook_placements
                 FROM {$this->prefix}promo_placements p"
            );
            $stmt->execute();
            $row += $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stats = [];
            foreach ($empty as $key => $default) {
                $stats[$key] = (int) ($row[$key] ?? $default);
            }

            return $stats;
        } catch (\Throwable $e) {
            $this->log_error('Failed to load dashboard stats', $e);
            return $empty;
        }
    }

    // ── Einstellungen ───────────────────────────────────────────────────────

    /**
     * @return array<string,string>
     */
    public function get_settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM {$this->prefix}promo_settings");
            $stmt->execute();
            $settings = self::DEFAULT_SETTINGS;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
            return $this->settingsCache = $settings;
        } catch (\Throwable $e) {
            $this->log_error('Failed to load settings', $e);
            return self::DEFAULT_SETTINGS;
        }
    }

    /** Bindung (Associated Data) des Chiffrats an genau diese Einstellung. */
    private const WEBHOOK_SECRET_BINDING = 'cms_promos.webhook_url';

    /**
     * Entschlüsselte Webhook-URL. Klartext-Altwerte (vor 4.0.0) bleiben lesbar; nicht entschlüsselbare
     * Werte (z. B. Schlüssel gewechselt) ergeben '' und werden protokolliert.
     */
    public function get_webhook_url(): string
    {
        $stored = trim((string) ($this->get_settings()['click_export_webhook_url'] ?? ''));
        if ($stored === '') {
            return '';
        }

        if (class_exists('CMS\\Services\\CryptoService')) {
            try {
                $crypto = \CMS\Services\CryptoService::getInstance();
                if ($crypto->isEncrypted($stored)) {
                    return CMS_Promos_Sanitizer::public_url(
                        $crypto->decrypt($stored, \CMS\Services\CryptoService::CONTEXT_SETTINGS, self::WEBHOOK_SECRET_BINDING)
                    );
                }
            } catch (\Throwable $e) {
                $this->log_error('Failed to decrypt webhook URL', $e);
                return '';
            }
        }

        return CMS_Promos_Sanitizer::public_url($stored);
    }

    public function has_webhook_url(): bool
    {
        return trim((string) ($this->get_settings()['click_export_webhook_url'] ?? '')) !== '';
    }

    public function is_webhook_encrypted(): bool
    {
        $stored = trim((string) ($this->get_settings()['click_export_webhook_url'] ?? ''));
        if ($stored === '' || !class_exists('CMS\\Services\\CryptoService')) {
            return false;
        }

        try {
            return \CMS\Services\CryptoService::getInstance()->isEncrypted($stored);
        } catch (\Throwable) {
            return false;
        }
    }

    private function encrypt_secret(string $plain): string
    {
        if (!class_exists('CMS\\Services\\CryptoService')) {
            return $plain;
        }

        $crypto = \CMS\Services\CryptoService::getInstance();
        if (!$crypto->isAvailable()) {
            return $plain;
        }

        return $crypto->encrypt($plain, \CMS\Services\CryptoService::CONTEXT_SETTINGS, self::WEBHOOK_SECRET_BINDING);
    }

    public function get_retention_days(): int
    {
        return max(0, min(3650, (int) ($this->get_settings()['retention_days'] ?? 0)));
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,message?:string,error?:string}
     */
    public function save_settings(array $post): array
    {
        // Webhook-URL (kann ein Token enthalten): leer = gespeicherten Wert behalten, Checkbox = entfernen.
        $webhookInput = trim((string) ($post['click_export_webhook_url'] ?? ''));
        if (!empty($post['click_export_webhook_remove'])) {
            $effectiveWebhook = '';
        } elseif ($webhookInput !== '') {
            $effectiveWebhook = CMS_Promos_Sanitizer::public_url($webhookInput);
            if ($effectiveWebhook === '') {
                return ['success' => false, 'error' => 'Die Webhook-URL muss eine öffentliche http(s)-Adresse sein.'];
            }
        } else {
            $effectiveWebhook = $this->get_webhook_url();
        }
        $exportEnabled = !empty($post['click_export_enabled']);
        if ($exportEnabled && $effectiveWebhook === '') {
            return ['success' => false, 'error' => 'Für den Klick-Export bitte eine Webhook-URL angeben oder den Export deaktivieren.'];
        }
        try {
            // Klartext-Altwerte werden hier beim nächsten Speichern verschlüsselt.
            $storedWebhook = $effectiveWebhook !== '' ? $this->encrypt_secret($effectiveWebhook) : '';
        } catch (\Throwable $e) {
            $this->log_error('Failed to encrypt webhook URL', $e);
            return ['success' => false, 'error' => 'Die Webhook-URL konnte nicht verschlüsselt gespeichert werden.'];
        }

        $behavior = (string) ($post['default_target_behavior'] ?? 'same_tab');
        $settings = [
            'archive_title' => CMS_Promos_Sanitizer::text($post['archive_title'] ?? '', 180),
            'archive_title_en' => CMS_Promos_Sanitizer::text($post['archive_title_en'] ?? '', 180),
            'archive_description' => CMS_Promos_Sanitizer::textarea($post['archive_description'] ?? ''),
            'archive_description_en' => CMS_Promos_Sanitizer::textarea($post['archive_description_en'] ?? ''),
            'default_button_label' => CMS_Promos_Sanitizer::text($post['default_button_label'] ?? '', 80),
            'default_button_label_en' => CMS_Promos_Sanitizer::text($post['default_button_label_en'] ?? '', 80),
            'default_target_behavior' => in_array($behavior, ['same_tab', 'new_tab'], true) ? $behavior : 'same_tab',
            'click_export_enabled' => $exportEnabled ? '1' : '0',
            'click_export_webhook_url' => $storedWebhook,
            'archive_indexable' => !empty($post['archive_indexable']) ? '1' : '0',
            'retention_days' => (string) max(0, min(3650, (int) ($post['retention_days'] ?? 0))),
        ];

        try {
            $this->upsert_settings($settings);
            return ['success' => true, 'message' => 'Promo-Einstellungen gespeichert.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save settings', $e);
            return ['success' => false, 'error' => 'Die Einstellungen konnten nicht gespeichert werden.'];
        }
    }

    /**
     * @param array<string,string> $settings
     */
    private function upsert_settings(array $settings): void
    {
        if ($settings === []) {
            return;
        }

        $rows = [];
        $params = [];
        foreach ($settings as $key => $value) {
            $rows[] = '(?, ?)';
            $params[] = $key;
            $params[] = $value;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->prefix}promo_settings (setting_key, setting_value) VALUES " . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute($params);
        $this->settingsCache = null;
    }

    // ── Platzierungen ───────────────────────────────────────────────────────

    /**
     * Alle Platzierungen mit Promo-Zählern (ein Join statt Unterabfrage je Zeile).
     *
     * @return list<array<string,mixed>>
     */
    public function get_placements(): array
    {
        if ($this->placementsCache !== null) {
            return $this->placementsCache;
        }

        try {
            $sql = 'SELECT ' . self::PLACEMENT_COLUMNS . ', COALESCE(c.promo_count, 0) AS promo_count, COALESCE(c.live_count, 0) AS live_count'
                . " FROM {$this->prefix}promo_placements p"
                . ' LEFT JOIN (SELECT pr.placement_id, COUNT(*) AS promo_count, SUM(' . self::LIVE_CONDITION . ') AS live_count'
                . "            FROM {$this->prefix}promos pr WHERE pr.placement_id IS NOT NULL GROUP BY pr.placement_id) c ON c.placement_id = p.id"
                . " ORDER BY CASE WHEN p.theme_hook = 'manual' THEN 1 ELSE 0 END ASC, p.theme_hook ASC, p.hook_priority ASC, p.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $this->placementsCache = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load placements', $e);
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function get_active_placements(): array
    {
        return array_values(array_filter($this->get_placements(), static fn (array $p): bool => ($p['status'] ?? '') === 'active'));
    }

    /**
     * @param array{q?:string,status?:string,hook?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function filter_placements(array $filters): array
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $status = (string) ($filters['status'] ?? '');
        $hook = (string) ($filters['hook'] ?? '');

        return array_values(array_filter($this->get_placements(), static function (array $p) use ($q, $status, $hook): bool {
            if ($status !== '' && ($p['status'] ?? '') !== $status) {
                return false;
            }
            if ($hook !== '' && ($p['theme_hook'] ?? '') !== $hook) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower((string) ($p['name'] ?? '') . ' ' . (string) ($p['slug'] ?? '') . ' ' . (string) ($p['description'] ?? ''));
                return str_contains($haystack, $q);
            }
            return true;
        }));
    }

    public function get_theme_hook_options(): array
    {
        return self::THEME_HOOK_OPTIONS;
    }

    public function get_theme_hook_label(?string $hook): string
    {
        return self::THEME_HOOK_OPTIONS[(string) ($hook ?? 'manual')] ?? self::THEME_HOOK_OPTIONS['manual'];
    }

    public function get_placement(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PLACEMENT_COLUMNS . " FROM {$this->prefix}promo_placements p WHERE p.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function get_placement_by_slug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PLACEMENT_COLUMNS . " FROM {$this->prefix}promo_placements p WHERE p.slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,message?:string,error?:string,id?:int}
     */
    public function save_placement(array $post): array
    {
        try {
            $id = max(0, (int) ($post['placement_id'] ?? 0));
            $name = CMS_Promos_Sanitizer::text($post['name'] ?? '', 180);
            if ($name === '') {
                return ['success' => false, 'error' => 'Bitte einen Platzierungsnamen angeben.'];
            }
            if ($id > 0 && $this->get_placement($id) === null) {
                return ['success' => false, 'error' => 'Die Platzierung existiert nicht mehr.'];
            }

            $slugInput = trim((string) ($post['slug'] ?? ''));
            $slug = $this->ensure_unique_slug('promo_placements', CMS_Promos_Sanitizer::slug($slugInput !== '' ? $slugInput : $name, 'platzierung'), $id);
            $status = ($post['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $values = [
                $name,
                $slug,
                CMS_Promos_Sanitizer::textarea($post['description'] ?? ''),
                $status,
                $this->normalize_theme_hook((string) ($post['theme_hook'] ?? 'manual')),
                max(-1000, min(1000, (int) ($post['hook_priority'] ?? 10))),
                max(1, min(50, (int) ($post['max_items'] ?? 3))),
            ];

            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promo_placements SET name = ?, slug = ?, description = ?, status = ?, theme_hook = ?, hook_priority = ?, max_items = ? WHERE id = ?");
                $stmt->execute([...$values, $id]);
                $this->invalidate_read_caches();
                return ['success' => true, 'message' => 'Platzierung aktualisiert.', 'id' => $id];
            }

            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promo_placements (name, slug, description, status, theme_hook, hook_priority, max_items) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Platzierung angelegt.', 'id' => $this->db->lastInsertId()];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save placement', $e);
            return ['success' => false, 'error' => 'Die Platzierung konnte nicht gespeichert werden.'];
        }
    }

    /**
     * Sammelaktion für Platzierungen: delete | activate | deactivate.
     *
     * @param list<int> $ids
     * @return array{success:bool,message?:string,error?:string}
     */
    public function bulk_placements(string $action, array $ids): array
    {
        if ($action === '') {
            return ['success' => false, 'error' => 'Bitte eine Sammelaktion wählen.'];
        }
        $ids = $this->clean_ids($ids);
        if ($ids === []) {
            return ['success' => false, 'error' => 'Bitte mindestens eine Platzierung auswählen.'];
        }

        $in = implode(', ', array_fill(0, count($ids), '?'));
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET placement_id = NULL WHERE placement_id IN ({$in})");
                    $stmt->execute($ids);
                    $stmt = $this->db->prepare("DELETE FROM {$this->prefix}promo_placements WHERE id IN ({$in})");
                    $stmt->execute($ids);
                    $message = sprintf('%d Platzierung(en) gelöscht; zugeordnete Promos sind jetzt ohne feste Platzierung.', $stmt->rowCount());
                    break;
                case 'activate':
                case 'deactivate':
                    $stmt = $this->db->prepare("UPDATE {$this->prefix}promo_placements SET status = ? WHERE id IN ({$in})");
                    $stmt->execute([$action === 'activate' ? 'active' : 'inactive', ...$ids]);
                    $message = sprintf('%d Platzierung(en) %s.', count($ids), $action === 'activate' ? 'aktiviert' : 'deaktiviert');
                    break;
                default:
                    return ['success' => false, 'error' => 'Unbekannte Sammelaktion.'];
            }
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => $message];
        } catch (\Throwable $e) {
            $this->log_error('Failed to run placement bulk action', $e);
            return ['success' => false, 'error' => 'Die Sammelaktion konnte nicht ausgeführt werden.'];
        }
    }

    public function delete_placement(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Ungültige Platzierung.'];
        }

        $result = $this->bulk_placements('delete', [$id]);
        return $result['success'] ? ['success' => true, 'message' => 'Platzierung gelöscht.'] : $result;
    }

    // ── Promos (Admin) ──────────────────────────────────────────────────────

    /**
     * Gefilterte, paginierte Promo-Liste.
     *
     * @param array{q?:string,status?:string,placement?:int|string,sort?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function list_promos(array $filters, int $page = 1, int $perPage = 25): array
    {
        $perPage = max(5, min(200, $perPage));
        [$where, $params] = $this->promo_filter_sql($filters);
        $sort = self::PROMO_SORTS[(string) ($filters['sort'] ?? '')] ?? self::PROMO_SORTS['priority'];

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->prefix}promos pr {$where}");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
            $pages = max(1, (int) ceil($total / $perPage));
            $page = max(1, min($pages, $page));
            $offset = ($page - 1) * $perPage;

            $sql = 'SELECT ' . self::PROMO_LIST_COLUMNS . ', p.name AS placement_name, p.theme_hook AS placement_theme_hook,'
                . ' (' . self::LIVE_CONDITION . ') AS is_live'
                . " FROM {$this->prefix}promos pr LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id"
                . " {$where} ORDER BY {$sort}"
                . sprintf(' LIMIT %d OFFSET %d', $perPage, $offset);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return ['items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
        } catch (\Throwable $e) {
            $this->log_error('Failed to list promos', $e);
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $perPage];
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function promo_filter_sql(array $filters): array
    {
        $conditions = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'live') {
            $conditions[] = '(' . self::LIVE_CONDITION . ')';
        } elseif ($status === 'expired') {
            $conditions[] = "(pr.end_at IS NOT NULL AND pr.end_at < NOW())";
        } elseif (isset(self::STATUS_LABELS[$status])) {
            $conditions[] = 'pr.status = ?';
            $params[] = $status;
        }

        $placement = (string) ($filters['placement'] ?? '');
        if ($placement === 'none') {
            $conditions[] = 'pr.placement_id IS NULL';
        } elseif ((int) $placement > 0) {
            $conditions[] = 'pr.placement_id = ?';
            $params[] = (int) $placement;
        }

        foreach ($this->search_words((string) ($filters['q'] ?? '')) as $word) {
            $like = '%' . $this->escape_like($word) . '%';
            $conditions[] = '(pr.title LIKE ? OR pr.title_en LIKE ? OR pr.slug LIKE ? OR pr.teaser LIKE ? OR pr.utm_campaign LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function get_recent_promos(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $stmt = $this->db->prepare('SELECT ' . self::PROMO_LIST_COLUMNS . ', p.name AS placement_name, (' . self::LIVE_CONDITION . ') AS is_live'
                . " FROM {$this->prefix}promos pr LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id"
                . ' ORDER BY pr.updated_at DESC, pr.id DESC' . sprintf(' LIMIT %d', $limit));
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load recent promos', $e);
            return [];
        }
    }

    public function get_promo(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PROMO_COLUMNS . " FROM {$this->prefix}promos pr WHERE pr.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Für den Klick-Redirect: nur die benötigten Felder plus „läuft gerade“.
     */
    public function get_promo_for_click(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.id, pr.placement_id, pr.title, pr.title_en, pr.slug, pr.status, pr.target_url,'
            . ' pr.utm_source, pr.utm_medium, pr.utm_campaign, pr.utm_term, pr.utm_content, (' . self::LIVE_CONDITION . ') AS is_live'
            . " FROM {$this->prefix}promos pr WHERE pr.slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,message?:string,error?:string,id?:int}
     */
    public function save_promo(array $post): array
    {
        try {
            $id = max(0, (int) ($post['promo_id'] ?? 0));
            $title = CMS_Promos_Sanitizer::text($post['title'] ?? '', 180);
            if ($title === '') {
                return ['success' => false, 'error' => 'Bitte einen Promo-Titel angeben.'];
            }
            if ($id > 0 && $this->get_promo($id) === null) {
                return ['success' => false, 'error' => 'Die Promo existiert nicht mehr.'];
            }

            $status = (string) ($post['status'] ?? 'draft');
            if (!isset(self::STATUS_LABELS[$status])) {
                $status = 'draft';
            }

            $placementId = max(0, (int) ($post['placement_id'] ?? 0));
            if ($placementId > 0 && $this->get_placement($placementId) === null) {
                return ['success' => false, 'error' => 'Die gewählte Platzierung existiert nicht.'];
            }

            $targetInput = trim((string) ($post['target_url'] ?? ''));
            $targetUrl = CMS_Promos_Sanitizer::public_url($targetInput);
            if ($targetInput !== '' && $targetUrl === '') {
                return ['success' => false, 'error' => 'Die Ziel-URL muss eine öffentliche http(s)-Adresse sein.'];
            }

            $imageInput = trim((string) ($post['image_url'] ?? ''));
            $imageUrl = CMS_Promos_Sanitizer::link($imageInput);
            if ($imageInput !== '' && $imageUrl === '') {
                return ['success' => false, 'error' => 'Die Bild-URL muss eine http(s)-Adresse oder ein lokaler Pfad (/uploads/…) sein.'];
            }

            $startAt = CMS_Promos_Sanitizer::datetime($post['start_at'] ?? '');
            $endAt = CMS_Promos_Sanitizer::datetime($post['end_at'] ?? '');
            if ($startAt !== null && $endAt !== null && strtotime($endAt) < strtotime($startAt)) {
                return ['success' => false, 'error' => 'Das Enddatum darf nicht vor dem Startdatum liegen.'];
            }

            $slugInput = trim((string) ($post['slug'] ?? ''));
            $slug = $this->ensure_unique_slug('promos', CMS_Promos_Sanitizer::slug($slugInput !== '' ? $slugInput : $title), $id);

            $values = [
                $placementId > 0 ? $placementId : null,
                $title,
                CMS_Promos_Sanitizer::text($post['title_en'] ?? '', 180),
                $slug,
                CMS_Promos_Sanitizer::text($post['teaser'] ?? '', 255),
                CMS_Promos_Sanitizer::text($post['teaser_en'] ?? '', 255),
                CMS_Promos_Sanitizer::html($post['content_html'] ?? ''),
                CMS_Promos_Sanitizer::html($post['content_html_en'] ?? ''),
                $targetUrl,
                CMS_Promos_Sanitizer::text($post['button_label'] ?? '', 80),
                CMS_Promos_Sanitizer::text($post['button_label_en'] ?? '', 80),
                $imageUrl,
                $status,
                $startAt,
                $endAt,
                max(0, min(100000, (int) ($post['priority'] ?? 0))),
                !empty($post['is_featured']) ? 1 : 0,
                max(0, min(50, (int) ($post['frequency_cap'] ?? 0))),
                max(1, min(24 * 14, (int) ($post['frequency_window_hours'] ?? 24))),
                CMS_Promos_Sanitizer::utm_token($post['utm_source'] ?? '', 80),
                CMS_Promos_Sanitizer::utm_token($post['utm_medium'] ?? '', 80),
                CMS_Promos_Sanitizer::utm_token($post['utm_campaign'] ?? '', 120),
                CMS_Promos_Sanitizer::utm_token($post['utm_term'] ?? '', 120),
                CMS_Promos_Sanitizer::utm_token($post['utm_content'] ?? '', 120),
            ];

            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET placement_id = ?, title = ?, title_en = ?, slug = ?, teaser = ?, teaser_en = ?, content_html = ?, content_html_en = ?, target_url = ?, button_label = ?, button_label_en = ?, image_url = ?, status = ?, start_at = ?, end_at = ?, priority = ?, is_featured = ?, frequency_cap = ?, frequency_window_hours = ?, utm_source = ?, utm_medium = ?, utm_campaign = ?, utm_term = ?, utm_content = ? WHERE id = ?");
                $stmt->execute([...$values, $id]);
                $this->invalidate_read_caches();
                return ['success' => true, 'message' => 'Promo aktualisiert.', 'id' => $id];
            }

            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promos (placement_id, title, title_en, slug, teaser, teaser_en, content_html, content_html_en, target_url, button_label, button_label_en, image_url, status, start_at, end_at, priority, is_featured, frequency_cap, frequency_window_hours, utm_source, utm_medium, utm_campaign, utm_term, utm_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Promo angelegt.', 'id' => $this->db->lastInsertId()];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save promo', $e);
            return ['success' => false, 'error' => 'Die Promo konnte nicht gespeichert werden.'];
        }
    }

    /**
     * Sammelaktion für Promos: delete | activate | pause | archive | draft | reset_stats.
     *
     * @param list<int> $ids
     * @return array{success:bool,message?:string,error?:string}
     */
    public function bulk_promos(string $action, array $ids): array
    {
        if ($action === '') {
            return ['success' => false, 'error' => 'Bitte eine Sammelaktion wählen.'];
        }
        $ids = $this->clean_ids($ids);
        if ($ids === []) {
            return ['success' => false, 'error' => 'Bitte mindestens eine Promo auswählen.'];
        }

        $in = implode(', ', array_fill(0, count($ids), '?'));
        $statusMap = ['activate' => 'active', 'pause' => 'paused', 'archive' => 'archived', 'draft' => 'draft'];

        try {
            if ($action === 'delete') {
                $stmt = $this->db->prepare("DELETE FROM {$this->prefix}promos WHERE id IN ({$in})");
                $stmt->execute($ids);
                $message = sprintf('%d Promo(s) gelöscht.', $stmt->rowCount());
            } elseif ($action === 'reset_stats') {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET impression_count = 0, click_count = 0 WHERE id IN ({$in})");
                $stmt->execute($ids);
                $message = sprintf('Zähler von %d Promo(s) zurückgesetzt.', count($ids));
            } elseif (isset($statusMap[$action])) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET status = ? WHERE id IN ({$in})");
                $stmt->execute([$statusMap[$action], ...$ids]);
                $message = sprintf('%d Promo(s) auf „%s“ gesetzt.', count($ids), self::STATUS_LABELS[$statusMap[$action]]);
            } else {
                return ['success' => false, 'error' => 'Unbekannte Sammelaktion.'];
            }

            $this->invalidate_read_caches();
            return ['success' => true, 'message' => $message];
        } catch (\Throwable $e) {
            $this->log_error('Failed to run promo bulk action', $e);
            return ['success' => false, 'error' => 'Die Sammelaktion konnte nicht ausgeführt werden.'];
        }
    }

    public function delete_promo(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Ungültige Promo.'];
        }

        $result = $this->bulk_promos('delete', [$id]);
        return $result['success'] ? ['success' => true, 'message' => 'Promo gelöscht.'] : $result;
    }

    // ── Tracking ────────────────────────────────────────────────────────────

    public function increment_click(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET click_count = click_count + 1, updated_at = updated_at WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            $this->log_error('Failed to increment click count', $e);
        }
    }

    /**
     * Eine UPDATE-Anweisung für alle im Request ausgespielten Promos.
     *
     * @param array<int,mixed> $ids
     */
    public function increment_impressions(array $ids): void
    {
        $ids = $this->clean_ids($ids);
        if ($ids === []) {
            return;
        }

        try {
            $in = implode(', ', array_fill(0, count($ids), '?'));
            // updated_at = updated_at: Zähler ändern nicht den „zuletzt bearbeitet“-Zeitpunkt (Sitemap-lastmod, Aktivität).
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET impression_count = impression_count + 1, updated_at = updated_at WHERE id IN ({$in})");
            $stmt->execute($ids);
        } catch (\Throwable $e) {
            $this->log_error('Failed to increment impressions', $e);
        }
    }

    // ── Öffentliche Ausspielung ─────────────────────────────────────────────

    /**
     * Aktive Platzierungen mit Theme-Hook, gruppiert nach Hook – eine Abfrage pro Request.
     *
     * @return array<string,list<array<string,mixed>>>
     */
    public function get_hook_placement_map(): array
    {
        if ($this->hookPlacementMap !== null) {
            return $this->hookPlacementMap;
        }

        $map = [];
        try {
            $stmt = $this->db->prepare('SELECT ' . self::PLACEMENT_COLUMNS . " FROM {$this->prefix}promo_placements p"
                . " WHERE p.status = 'active' AND p.theme_hook <> 'manual' ORDER BY p.hook_priority ASC, p.name ASC");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $hook = (string) ($row['theme_hook'] ?? '');
                if (isset(self::THEME_HOOK_OPTIONS[$hook])) {
                    $map[$hook][] = $row;
                }
            }
        } catch (\Throwable $e) {
            $this->log_error('Failed to load hook placements', $e);
        }

        return $this->hookPlacementMap = $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function get_hook_placements(string $themeHook): array
    {
        return $this->get_hook_placement_map()[$themeHook] ?? [];
    }

    /**
     * Laufende Promos mehrerer Platzierungen in einer Abfrage, gruppiert nach Platzierung.
     *
     * @param list<int> $placementIds
     * @return array<int,list<array<string,mixed>>>
     */
    public function get_live_promos_for_placements(array $placementIds): array
    {
        $placementIds = $this->clean_ids($placementIds);
        if ($placementIds === []) {
            return [];
        }

        $in = implode(', ', array_fill(0, count($placementIds), '?'));
        $grouped = [];
        try {
            $stmt = $this->db->prepare('SELECT ' . self::PROMO_PUBLIC_COLUMNS . ', p.name AS placement_name, p.slug AS placement_slug'
                . " FROM {$this->prefix}promos pr INNER JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id"
                . " WHERE pr.placement_id IN ({$in}) AND " . self::LIVE_CONDITION
                . ' ORDER BY pr.is_featured DESC, pr.priority DESC, pr.updated_at DESC, pr.id DESC');
            $stmt->execute($placementIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $grouped[(int) $row['placement_id']][] = $row;
            }
        } catch (\Throwable $e) {
            $this->log_error('Failed to load placement promos', $e);
        }

        return $grouped;
    }

    /**
     * Laufende Promos für das Archiv (optional nur einer aktiven Platzierung).
     *
     * @return list<array<string,mixed>>
     */
    public function get_active_promos(?string $placementSlug = null): array
    {
        $cacheKey = $placementSlug ?? '__all__';
        if (array_key_exists($cacheKey, $this->activePromosCache)) {
            return $this->activePromosCache[$cacheKey];
        }

        $sql = 'SELECT ' . self::PROMO_PUBLIC_COLUMNS . ', p.name AS placement_name, p.slug AS placement_slug'
            . " FROM {$this->prefix}promos pr LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id"
            . ' WHERE ' . self::LIVE_CONDITION;
        $params = [];
        if ($placementSlug !== null && $placementSlug !== '') {
            $sql .= " AND p.slug = ? AND p.status = 'active'";
            $params[] = $placementSlug;
        }
        $sql .= ' ORDER BY pr.is_featured DESC, pr.priority DESC, pr.updated_at DESC, pr.id DESC LIMIT 200';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->activePromosCache[$cacheKey] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load active promos', $e);
            return [];
        }
    }

    /**
     * Anzahl laufender Promos und jüngste Änderung (Sitemap).
     *
     * @return array{count:int,lastmod:string}
     */
    public function get_live_summary(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt, MAX(pr.updated_at) AS lastmod FROM {$this->prefix}promos pr WHERE " . self::LIVE_CONDITION);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            return ['count' => (int) ($row['cnt'] ?? 0), 'lastmod' => (string) ($row['lastmod'] ?? '')];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load live summary', $e);
            return ['count' => 0, 'lastmod' => ''];
        }
    }

    /**
     * Seitensuche: laufende Promos, die alle Suchwörter enthalten.
     *
     * @param list<string> $words
     * @return list<array<string,mixed>>
     */
    public function search_live_promos(array $words, int $limit): array
    {
        $words = array_slice(array_values(array_filter($words, static fn (string $w): bool => $w !== '')), 0, 8);
        if ($words === []) {
            return [];
        }

        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $like = '%' . $this->escape_like($word) . '%';
            $conditions[] = '(pr.title LIKE ? OR pr.title_en LIKE ? OR pr.teaser LIKE ? OR pr.teaser_en LIKE ? OR pr.content_html LIKE ? OR pr.content_html_en LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $stmt = $this->db->prepare('SELECT pr.id, pr.slug, pr.title, pr.title_en, pr.teaser, pr.teaser_en, pr.content_html, pr.content_html_en, pr.updated_at'
            . " FROM {$this->prefix}promos pr WHERE " . self::LIVE_CONDITION . ' AND ' . implode(' AND ', $conditions)
            . ' ORDER BY pr.is_featured DESC, pr.priority DESC, pr.updated_at DESC' . sprintf(' LIMIT %d', max(1, min(50, $limit))));
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // ── Export / Import ─────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    public function get_export_promos(): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PROMO_COLUMNS . ', p.slug AS placement_slug, p.name AS placement_name'
            . " FROM {$this->prefix}promos pr LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id ORDER BY pr.id ASC");
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Konfiguration als JSON-fähiges Array (Einstellungen, Platzierungen, Promos ohne Zähler).
     *
     * @return array<string,mixed>
     */
    public function build_config_export(): array
    {
        $settings = $this->get_settings();
        // Der Webhook kann ein Token in der URL tragen und wird deshalb nicht exportiert.
        unset($settings['click_export_webhook_url']);
        $settings['click_export_enabled'] = '0';

        $placements = array_map(static fn (array $p): array => [
            'name' => (string) $p['name'],
            'slug' => (string) $p['slug'],
            'description' => (string) ($p['description'] ?? ''),
            'status' => (string) $p['status'],
            'theme_hook' => (string) $p['theme_hook'],
            'hook_priority' => (int) $p['hook_priority'],
            'max_items' => (int) $p['max_items'],
        ], $this->get_placements());

        $fields = ['title', 'title_en', 'slug', 'teaser', 'teaser_en', 'content_html', 'content_html_en', 'target_url', 'button_label',
            'button_label_en', 'image_url', 'status', 'start_at', 'end_at', 'priority', 'is_featured', 'frequency_cap', 'frequency_window_hours',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'placement_slug'];
        $promos = [];
        foreach ($this->get_export_promos() as $row) {
            $item = [];
            foreach ($fields as $field) {
                $item[$field] = $row[$field] ?? null;
            }
            $promos[] = $item;
        }

        return [
            'format' => 'cms-promos-config',
            'version' => 1,
            'plugin_version' => CMS_PROMOS_VERSION,
            'exported_at' => gmdate('c'),
            'settings' => $settings,
            'placements' => $placements,
            'promos' => $promos,
        ];
    }

    /**
     * Importiert eine Konfiguration (Upsert über den Slug). Zähler bleiben unberührt.
     *
     * @param array<string,mixed> $data
     * @return array{success:bool,message?:string,error?:string}
     */
    public function import_config(array $data): array
    {
        if (($data['format'] ?? '') !== 'cms-promos-config' || !is_array($data['placements'] ?? null) || !is_array($data['promos'] ?? null)) {
            return ['success' => false, 'error' => 'Die Datei ist kein gültiger CMS-Promos-Export.'];
        }
        if (count($data['placements']) > 500 || count($data['promos']) > 5000) {
            return ['success' => false, 'error' => 'Die Datei enthält zu viele Einträge (max. 500 Platzierungen / 5000 Promos).'];
        }

        $summary = ['placements_created' => 0, 'placements_updated' => 0, 'promos_created' => 0, 'promos_updated' => 0, 'skipped' => 0];

        try {
            if (is_array($data['settings'] ?? null)) {
                $current = $this->get_settings();
                $incoming = [];
                foreach ($data['settings'] as $key => $value) {
                    if (is_string($key) && array_key_exists($key, self::DEFAULT_SETTINGS) && $key !== 'click_export_webhook_url' && is_scalar($value)) {
                        $incoming[$key] = (string) $value;
                    }
                }
                $merged = array_replace($current, $incoming);
                // Webhook wird nie importiert: leer übergeben = gespeicherten (verschlüsselten) Wert behalten.
                $merged['click_export_webhook_url'] = '';
                $merged['click_export_enabled'] = $this->has_webhook_url() ? $merged['click_export_enabled'] : '0';
                $result = $this->save_settings($merged);
                if (!$result['success']) {
                    return $result;
                }
            }

            $placementIds = [];
            foreach ($this->get_placements() as $placement) {
                $placementIds[(string) $placement['slug']] = (int) $placement['id'];
            }

            foreach ($data['placements'] as $placement) {
                if (!is_array($placement)) {
                    $summary['skipped']++;
                    continue;
                }
                $slug = CMS_Promos_Sanitizer::slug($placement['slug'] ?? ($placement['name'] ?? ''), '');
                $existingId = $slug !== '' ? ($placementIds[$slug] ?? 0) : 0;
                $result = $this->save_placement(array_merge($placement, ['placement_id' => $existingId, 'slug' => $slug]));
                if (!$result['success']) {
                    $summary['skipped']++;
                    continue;
                }
                $summary[$existingId > 0 ? 'placements_updated' : 'placements_created']++;
                if ($slug !== '') {
                    $placementIds[$slug] = (int) ($result['id'] ?? $existingId);
                }
            }

            $promoIds = [];
            $stmt = $this->db->prepare("SELECT id, slug FROM {$this->prefix}promos");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $promoIds[(string) $row['slug']] = (int) $row['id'];
            }

            foreach ($data['promos'] as $promo) {
                if (!is_array($promo)) {
                    $summary['skipped']++;
                    continue;
                }
                $slug = CMS_Promos_Sanitizer::slug($promo['slug'] ?? ($promo['title'] ?? ''), '');
                $existingId = $slug !== '' ? ($promoIds[$slug] ?? 0) : 0;
                $placementSlug = (string) ($promo['placement_slug'] ?? '');
                $promo['placement_id'] = $placementSlug !== '' ? ($placementIds[$placementSlug] ?? 0) : 0;
                $promo['promo_id'] = $existingId;
                $promo['slug'] = $slug;
                foreach (['start_at', 'end_at'] as $dateField) {
                    $promo[$dateField] = is_scalar($promo[$dateField] ?? null) ? (string) $promo[$dateField] : '';
                }
                $result = $this->save_promo($promo);
                if (!$result['success']) {
                    $summary['skipped']++;
                    continue;
                }
                $summary[$existingId > 0 ? 'promos_updated' : 'promos_created']++;
                if ($slug !== '') {
                    $promoIds[$slug] = (int) ($result['id'] ?? $existingId);
                }
            }
        } catch (\Throwable $e) {
            $this->log_error('Failed to import config', $e);
            return ['success' => false, 'error' => 'Der Import wurde wegen eines Fehlers abgebrochen.'];
        }

        $this->invalidate_read_caches();

        return ['success' => true, 'message' => sprintf(
            'Import abgeschlossen: %d Platzierung(en) neu, %d aktualisiert; %d Promo(s) neu, %d aktualisiert; %d übersprungen.',
            $summary['placements_created'],
            $summary['placements_updated'],
            $summary['promos_created'],
            $summary['promos_updated'],
            $summary['skipped']
        )];
    }

    // ── Datenaufbewahrung ───────────────────────────────────────────────────

    /** Promos, die seit mehr als $days Tagen abgelaufen oder archiviert sind. */
    private function retention_condition(): string
    {
        // Ohne Tabellen-Alias, weil DELETE … LIMIT nur in der Ein-Tabellen-Syntax erlaubt ist.
        return "((end_at IS NOT NULL AND end_at < DATE_SUB(NOW(), INTERVAL ? DAY))"
            . " OR (status = 'archived' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)))";
    }

    public function count_retention_due(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->prefix}promos WHERE " . $this->retention_condition());
            $stmt->execute([$days, $days]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->log_error('Failed to count retention candidates', $e);
            return 0;
        }
    }

    public function prune_retention(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $deleted = 0;
        try {
            // In Blöcken löschen, damit große Bestände keine langen Sperren erzeugen.
            for ($round = 0; $round < 20; $round++) {
                $stmt = $this->db->prepare("DELETE FROM {$this->prefix}promos WHERE " . $this->retention_condition() . ' LIMIT 500');
                $stmt->execute([$days, $days]);
                $count = $stmt->rowCount();
                $deleted += $count;
                if ($count < 500) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->log_error('Failed to prune promos', $e);
        }

        if ($deleted > 0) {
            $this->invalidate_read_caches();
        }

        return $deleted;
    }

    // ── Hilfen ──────────────────────────────────────────────────────────────

    /**
     * Suchbegriff in Wörter zerlegen (max. 8, je 2–80 Zeichen).
     *
     * @return list<string>
     */
    public function search_words(string $query): array
    {
        $parts = preg_split('/\s+/u', trim($query)) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $part = mb_substr(trim($part), 0, 80);
            if (mb_strlen($part) >= 2) {
                $words[] = $part;
            }
        }

        return array_slice(array_values(array_unique($words)), 0, 8);
    }

    private function escape_like(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * @param array<int,mixed> $ids
     * @return list<int>
     */
    private function clean_ids(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = is_scalar($id) ? (int) $id : 0;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_slice(array_values($clean), 0, 1000);
    }

    private function normalize_theme_hook(string $value): string
    {
        $value = trim($value);
        return array_key_exists($value, self::THEME_HOOK_OPTIONS) ? $value : 'manual';
    }

    /**
     * Eindeutiger Slug: eine Abfrage für alle Varianten `slug`, `slug-2`, … statt einer je Versuch.
     */
    private function ensure_unique_slug(string $table, string $slug, int $ignoreId = 0): string
    {
        $table = $table === 'promo_placements' ? 'promo_placements' : 'promos';
        $stmt = $this->db->prepare("SELECT slug FROM {$this->prefix}{$table} WHERE (slug = ? OR slug LIKE ?) AND id <> ?");
        $stmt->execute([$slug, $this->escape_like($slug) . '-%', $ignoreId]);
        $taken = array_flip(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []));

        if (!isset($taken[$slug])) {
            return $slug;
        }

        $base = mb_substr($slug, 0, 112);
        for ($i = 2; $i < 10000; $i++) {
            $candidate = $base . '-' . $i;
            if (!isset($taken[$candidate])) {
                return $candidate;
            }
        }

        return $base . '-' . bin2hex(random_bytes(3));
    }

    private function invalidate_read_caches(): void
    {
        $this->settingsCache = null;
        $this->placementsCache = null;
        $this->hookPlacementMap = null;
        $this->activePromosCache = [];
    }

    private function log_error(string $message, \Throwable $e): void
    {
        error_log('[cms-promos] ' . $message . ': ' . $e->getMessage());
    }
}

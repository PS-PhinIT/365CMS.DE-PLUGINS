<?php
/**
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

    /** Spalten einer Promo (statt SELECT *). */
    private const PROMO_COLUMNS = 'pr.id, pr.placement_id, pr.title, pr.title_en, pr.slug, pr.teaser, pr.teaser_en, pr.content_html, pr.content_html_en, pr.target_url, pr.button_label, pr.button_label_en, pr.image_url, pr.status, pr.start_at, pr.end_at, pr.priority, pr.is_featured, pr.frequency_cap, pr.frequency_window_hours, pr.utm_source, pr.utm_medium, pr.utm_campaign, pr.utm_term, pr.utm_content, pr.impression_count, pr.click_count, pr.created_at, pr.updated_at';

    /** Spalten einer Platzierung (statt SELECT *). */
    private const PLACEMENT_COLUMNS = 'p.id, p.name, p.slug, p.description, p.status, p.theme_hook, p.hook_priority, p.max_items, p.created_at, p.updated_at';

    /** @var array<string,string> */
    public const STATUS_LABELS = [
        'draft' => 'Entwurf',
        'active' => 'Aktiv',
        'paused' => 'Pausiert',
        'archived' => 'Archiviert',
    ];

    private \CMS\Database $db;
    private string $prefix;
    private ?array $settingsCache = null;
    private ?array $placementsCache = null;
    private ?array $promosCache = null;
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $activePromosCache = [];
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $hookPlacementsCache = [];
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $placementPromosCache = [];

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
    ];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->db = \CMS\Database::instance();
        $this->prefix = $this->db->getPrefix();
    }

    public function seed_defaults(): void
    {
        // Fehlende Standardwerte in einem Statement ergänzen, vorhandene Werte bleiben unverändert.
        $stmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}promo_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach (self::DEFAULT_SETTINGS as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $placementStmt = $this->db->prepare("SELECT id FROM {$this->prefix}promo_placements LIMIT 1");
        $placementStmt->execute();
        if ($placementStmt->fetchColumn() === false) {
            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promo_placements (name, slug, description, status, theme_hook, hook_priority, max_items) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Homepage Hero', 'homepage-hero', 'Prominente Hero-Fläche auf der Startseite.', 'active', 'after_header', 5, 1]);
            $stmt->execute(['Sidebar CTA', 'sidebar-cta', 'Seitliche CTA-Boxen und Conversion-Elemente.', 'active', 'before_footer', 20, 3]);
            $stmt->execute(['Home Content Highlights', 'home-content-highlights', 'Promo-Karten im Inhaltsbereich der Startseite.', 'active', 'home_content', 15, 2]);
        }

        $this->assign_default_theme_hooks();
    }

    public function get_dashboard_stats(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) AS promos,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_promos,
                    COALESCE(SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END), 0) AS featured_promos,
                    COALESCE(SUM(impression_count), 0) AS impressions,
                    COALESCE(SUM(click_count), 0) AS clicks
                FROM {$this->prefix}promos"
            );
            $stmt->execute();
            $promoStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            return [
                'promos' => (int) ($promoStats['promos'] ?? 0),
                'active_promos' => (int) ($promoStats['active_promos'] ?? 0),
                'featured_promos' => (int) ($promoStats['featured_promos'] ?? 0),
                'placements' => (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->prefix}promo_placements"),
                'impressions' => (int) ($promoStats['impressions'] ?? 0),
                'clicks' => (int) ($promoStats['clicks'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load dashboard stats', $e);
            return [
                'promos' => 0,
                'active_promos' => 0,
                'featured_promos' => 0,
                'placements' => 0,
                'impressions' => 0,
                'clicks' => 0,
            ];
        }
    }

    public function get_settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM {$this->prefix}promo_settings");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $settings = self::DEFAULT_SETTINGS;
            foreach ($rows as $row) {
                $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
            $this->settingsCache = $settings;
            return $this->settingsCache;
        } catch (\Throwable $e) {
            $this->log_error('Failed to load settings', $e);
            return self::DEFAULT_SETTINGS;
        }
    }

    public function save_settings(array $post): array
    {
        try {
            $settings = [
                'archive_title' => $this->clean_text($post['archive_title'] ?? ''),
                'archive_title_en' => $this->clean_text($post['archive_title_en'] ?? ''),
                'archive_description' => $this->clean_textarea($post['archive_description'] ?? ''),
                'archive_description_en' => $this->clean_textarea($post['archive_description_en'] ?? ''),
                'default_button_label' => $this->clean_text($post['default_button_label'] ?? 'Mehr erfahren'),
                'default_button_label_en' => $this->clean_text($post['default_button_label_en'] ?? ''),
                'default_target_behavior' => in_array(($post['default_target_behavior'] ?? 'same_tab'), ['same_tab', 'new_tab'], true) ? (string) $post['default_target_behavior'] : 'same_tab',
                'click_export_enabled' => !empty($post['click_export_enabled']) ? '1' : '0',
                'click_export_webhook_url' => $this->clean_url($post['click_export_webhook_url'] ?? ''),
            ];

            if ($settings['default_button_label'] === '') {
                $settings['default_button_label'] = self::DEFAULT_SETTINGS['default_button_label'];
            }
            if ($settings['click_export_enabled'] === '1' && $settings['click_export_webhook_url'] === '') {
                return ['success' => false, 'error' => 'Für den Klick-Export wird eine gültige öffentliche https-Webhook-URL benötigt.'];
            }

            $this->write_settings($settings);
            return ['success' => true, 'message' => 'Promo-Einstellungen gespeichert.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save settings', $e);
            return ['success' => false, 'error' => 'Die Einstellungen konnten nicht gespeichert werden.'];
        }
    }

    /**
     * Setzt alle Einstellungen auf die Standardwerte zurück.
     *
     * @return array{success:bool,message?:string,error?:string}
     */
    public function reset_settings(): array
    {
        try {
            $this->write_settings(self::DEFAULT_SETTINGS);
            return ['success' => true, 'message' => 'Einstellungen auf Standardwerte zurückgesetzt.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to reset settings', $e);
            return ['success' => false, 'error' => 'Die Einstellungen konnten nicht zurückgesetzt werden.'];
        }
    }

    /**
     * @param array<string,string> $settings
     */
    private function write_settings(array $settings): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->prefix}promo_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $this->settingsCache = null;
    }

    public function get_placements(): array
    {
        if ($this->placementsCache !== null) {
            return $this->placementsCache;
        }

        try {
            $sql = 'SELECT ' . self::PLACEMENT_COLUMNS . ", COALESCE(c.promo_count, 0) AS promo_count, COALESCE(c.active_count, 0) AS active_count
                    FROM {$this->prefix}promo_placements p
                    LEFT JOIN (
                        SELECT placement_id, COUNT(*) AS promo_count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count
                        FROM {$this->prefix}promos
                        WHERE placement_id IS NOT NULL
                        GROUP BY placement_id
                    ) c ON c.placement_id = p.id
                    ORDER BY CASE WHEN p.theme_hook = 'manual' THEN 1 ELSE 0 END ASC, p.theme_hook ASC, p.hook_priority ASC, p.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $this->placementsCache = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $this->placementsCache;
        } catch (\Throwable $e) {
            $this->log_error('Failed to load placements', $e);
            return [];
        }
    }

    public function get_theme_hook_options(): array
    {
        return self::THEME_HOOK_OPTIONS;
    }

    public function get_theme_hook_label(?string $hook): string
    {
        $hook = (string) ($hook ?? 'manual');
        return self::THEME_HOOK_OPTIONS[$hook] ?? self::THEME_HOOK_OPTIONS['manual'];
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

    public function save_placement(array $post): array
    {
        try {
            $id = (int) ($post['placement_id'] ?? 0);
            $name = $this->clean_text($post['name'] ?? '');
            if ($name === '') {
                return ['success' => false, 'error' => 'Bitte einen Platzierungsnamen angeben.'];
            }

            $slug = $this->ensure_unique_placement_slug($this->slugify($post['slug'] ?? $name), $id);
            $status = ($post['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $themeHook = $this->normalize_theme_hook($post['theme_hook'] ?? 'manual');
            $values = [$name, $slug, $this->clean_textarea($post['description'] ?? ''), $status, $themeHook, max(-100, min(1000, (int) ($post['hook_priority'] ?? 10))), max(1, min(24, (int) ($post['max_items'] ?? 3)))];

            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promo_placements SET name = ?, slug = ?, description = ?, status = ?, theme_hook = ?, hook_priority = ?, max_items = ? WHERE id = ?");
                $stmt->execute([...$values, $id]);
                $this->invalidate_read_caches();
                return ['success' => true, 'message' => 'Platzierung aktualisiert.'];
            }

            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promo_placements (name, slug, description, status, theme_hook, hook_priority, max_items) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Platzierung angelegt.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save placement', $e);
            return ['success' => false, 'error' => 'Die Platzierung konnte nicht gespeichert werden.'];
        }
    }

    public function delete_placement(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Ungueltige Platzierung.'];
        }

        try {
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET placement_id = NULL WHERE placement_id = ?");
            $stmt->execute([$id]);
            $stmt = $this->db->prepare("DELETE FROM {$this->prefix}promo_placements WHERE id = ?");
            $stmt->execute([$id]);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Platzierung gelöscht.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to delete placement', $e);
            return ['success' => false, 'error' => 'Die Platzierung konnte nicht gelöscht werden.'];
        }
    }

    public function get_promos(): array
    {
        if ($this->promosCache !== null) {
            return $this->promosCache;
        }

        try {
            $sql = 'SELECT ' . self::PROMO_COLUMNS . ", p.name AS placement_name, p.slug AS placement_slug, p.theme_hook AS placement_theme_hook
                    FROM {$this->prefix}promos pr
                    LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id
                    ORDER BY pr.priority DESC, pr.updated_at DESC, pr.id DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $this->promosCache = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $this->promosCache;
        } catch (\Throwable $e) {
            $this->log_error('Failed to load promos', $e);
            return [];
        }
    }

    public function get_active_promos(?string $placementSlug = null): array
    {
        $cacheKey = $placementSlug ?? '__all__';
        if (array_key_exists($cacheKey, $this->activePromosCache)) {
            return $this->activePromosCache[$cacheKey];
        }

        $sql = 'SELECT ' . self::PROMO_COLUMNS . ", p.name AS placement_name, p.slug AS placement_slug, p.max_items
                FROM {$this->prefix}promos pr
                LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id
                WHERE pr.status = 'active'
                  AND (pr.start_at IS NULL OR pr.start_at <= NOW())
                  AND (pr.end_at IS NULL OR pr.end_at >= NOW())";
        $params = [];
        if ($placementSlug !== null && $placementSlug !== '') {
            $sql .= ' AND p.slug = ?';
            $params[] = $placementSlug;
        }
        $sql .= ' ORDER BY pr.is_featured DESC, pr.priority DESC, pr.updated_at DESC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $this->activePromosCache[$cacheKey] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $this->activePromosCache[$cacheKey];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load active promos', $e);
            return [];
        }
    }

    public function get_hook_placements(string $themeHook): array
    {
        $themeHook = $this->normalize_theme_hook($themeHook);
        if ($themeHook === 'manual') {
            return [];
        }
        if (array_key_exists($themeHook, $this->hookPlacementsCache)) {
            return $this->hookPlacementsCache[$themeHook];
        }

        $sql = 'SELECT ' . self::PLACEMENT_COLUMNS . "
                FROM {$this->prefix}promo_placements p
                WHERE p.status = 'active' AND p.theme_hook = ?
                ORDER BY p.hook_priority ASC, p.name ASC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$themeHook]);
            $this->hookPlacementsCache[$themeHook] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $this->hookPlacementsCache[$themeHook];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load hook placements', $e);
            return [];
        }
    }

    public function get_active_promos_for_placement(int $placementId, int $limit = 0): array
    {
        $safeLimit = max(0, min(100, $limit));
        $cacheKey = $placementId . ':' . $safeLimit;
        if (array_key_exists($cacheKey, $this->placementPromosCache)) {
            return $this->placementPromosCache[$cacheKey];
        }

        $sql = 'SELECT ' . self::PROMO_COLUMNS . ", p.name AS placement_name, p.slug AS placement_slug, p.theme_hook AS placement_theme_hook
                FROM {$this->prefix}promos pr
                INNER JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id
                WHERE pr.placement_id = ?
                  AND pr.status = 'active'
                  AND p.status = 'active'
                  AND (pr.start_at IS NULL OR pr.start_at <= NOW())
                  AND (pr.end_at IS NULL OR pr.end_at >= NOW())
                ORDER BY pr.is_featured DESC, pr.priority DESC, pr.updated_at DESC, pr.id DESC";

        if ($safeLimit > 0) {
            $sql .= sprintf(' LIMIT %d', $safeLimit);
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$placementId]);
            $this->placementPromosCache[$cacheKey] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $this->placementPromosCache[$cacheKey];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load placement promos', $e);
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

    public function get_promo_by_slug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PROMO_COLUMNS . " FROM {$this->prefix}promos pr WHERE pr.slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function save_promo(array $post): array
    {
        try {
            $id = (int) ($post['promo_id'] ?? 0);
            $title = $this->clean_text($post['title'] ?? '');
            if ($title === '') {
                return ['success' => false, 'error' => 'Bitte einen Promo-Titel angeben.'];
            }

            $slug = $this->ensure_unique_promo_slug($this->slugify($post['slug'] ?? $title), $id);
            $status = (string) ($post['status'] ?? 'draft');
            if (!in_array($status, ['draft', 'active', 'paused', 'archived'], true)) {
                $status = 'draft';
            }

            $placementId = max(0, (int) ($post['placement_id'] ?? 0));
            if ($placementId === 0) {
                $placementId = null;
            }

            $startAt = $this->normalize_datetime((string) ($post['start_at'] ?? ''));
            $endAt = $this->normalize_datetime((string) ($post['end_at'] ?? ''));
            if ($startAt !== null && $endAt !== null && strtotime($endAt) < strtotime($startAt)) {
                return ['success' => false, 'error' => 'Das Enddatum darf nicht vor dem Startdatum liegen.'];
            }

            $values = [
                $placementId,
                $title,
                $this->clean_text($post['title_en'] ?? ''),
                $slug,
                $this->clean_text($post['teaser'] ?? ''),
                $this->clean_text($post['teaser_en'] ?? ''),
                $this->clean_html($post['content_html'] ?? ''),
                $this->clean_html($post['content_html_en'] ?? ''),
                $this->clean_url($post['target_url'] ?? ''),
                $this->clean_text($post['button_label'] ?? 'Mehr erfahren'),
                $this->clean_text($post['button_label_en'] ?? ''),
                $this->clean_url($post['image_url'] ?? ''),
                $status,
                $startAt,
                $endAt,
                max(0, min(9999, (int) ($post['priority'] ?? 0))),
                !empty($post['is_featured']) ? 1 : 0,
                max(0, min(50, (int) ($post['frequency_cap'] ?? 0))),
                max(1, min(24 * 14, (int) ($post['frequency_window_hours'] ?? 24))),
                $this->clean_utm_token($post['utm_source'] ?? '', 80),
                $this->clean_utm_token($post['utm_medium'] ?? '', 80),
                $this->clean_utm_token($post['utm_campaign'] ?? '', 120),
                $this->clean_utm_token($post['utm_term'] ?? '', 120),
                $this->clean_utm_token($post['utm_content'] ?? '', 120),
            ];

            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET placement_id = ?, title = ?, title_en = ?, slug = ?, teaser = ?, teaser_en = ?, content_html = ?, content_html_en = ?, target_url = ?, button_label = ?, button_label_en = ?, image_url = ?, status = ?, start_at = ?, end_at = ?, priority = ?, is_featured = ?, frequency_cap = ?, frequency_window_hours = ?, utm_source = ?, utm_medium = ?, utm_campaign = ?, utm_term = ?, utm_content = ? WHERE id = ?");
                $stmt->execute([...$values, $id]);
                $this->invalidate_read_caches();
                return ['success' => true, 'message' => 'Promo aktualisiert.'];
            }

            $stmt = $this->db->prepare("INSERT INTO {$this->prefix}promos (placement_id, title, title_en, slug, teaser, teaser_en, content_html, content_html_en, target_url, button_label, button_label_en, image_url, status, start_at, end_at, priority, is_featured, frequency_cap, frequency_window_hours, utm_source, utm_medium, utm_campaign, utm_term, utm_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($values);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Promo angelegt.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to save promo', $e);
            return ['success' => false, 'error' => 'Die Promo konnte nicht gespeichert werden.'];
        }
    }

    public function delete_promo(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Ungueltige Promo.'];
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->prefix}promos WHERE id = ?");
            $stmt->execute([$id]);
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => 'Promo gelöscht.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to delete promo', $e);
            return ['success' => false, 'error' => 'Die Promo konnte nicht gelöscht werden.'];
        }
    }

    public function increment_click(int $id): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET click_count = click_count + 1 WHERE id = ?");
            $stmt->execute([$id]);
            $this->invalidate_read_caches();
        } catch (\Throwable $e) {
            $this->log_error('Failed to increment click count', $e);
        }
    }

    public function increment_impression(int $id): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET impression_count = impression_count + 1 WHERE id = ?");
            $stmt->execute([$id]);
            $this->invalidate_read_caches();
        } catch (\Throwable $e) {
            $this->log_error('Failed to increment impression count', $e);
        }
    }

    /**
     * @param array<int,mixed> $ids
     */
    public function increment_impressions(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET impression_count = impression_count + 1 WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $this->invalidate_read_caches();
        } catch (\Throwable $e) {
            $this->log_error('Failed to increment impressions', $e);
        }
    }

    /**
     * Promo-Liste mit Status-/Suchfilter für die Verwaltung.
     *
     * @return list<array<string,mixed>>
     */
    public function find_promos(string $status = '', string $search = '', int $placementId = 0): array
    {
        $where = [];
        $params = [];
        if ($status === 'expired') {
            $where[] = "pr.status = 'active' AND pr.end_at IS NOT NULL AND pr.end_at < NOW()";
        } elseif ($status === 'live') {
            $where[] = "pr.status = 'active' AND (pr.start_at IS NULL OR pr.start_at <= NOW()) AND (pr.end_at IS NULL OR pr.end_at >= NOW())";
        } elseif (array_key_exists($status, self::STATUS_LABELS)) {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
            $where[] = '(pr.title LIKE ? OR pr.slug LIKE ? OR pr.teaser LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($placementId > 0) {
            $where[] = 'pr.placement_id = ?';
            $params[] = $placementId;
        }

        $sql = 'SELECT ' . self::PROMO_COLUMNS . ", p.name AS placement_name, p.slug AS placement_slug, p.theme_hook AS placement_theme_hook
                FROM {$this->prefix}promos pr
                LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY pr.priority DESC, pr.updated_at DESC, pr.id DESC LIMIT 500';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->log_error('Failed to filter promos', $e);
            return [];
        }
    }

    /**
     * Zuletzt geänderte Promos für das Dashboard.
     *
     * @return list<array<string,mixed>>
     */
    public function get_recent_promos(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $stmt = $this->db->prepare(
                "SELECT pr.id, pr.title, pr.status, pr.end_at, pr.impression_count, pr.click_count, pr.updated_at, p.name AS placement_name
                 FROM {$this->prefix}promos pr
                 LEFT JOIN {$this->prefix}promo_placements p ON p.id = pr.placement_id
                 ORDER BY pr.updated_at DESC, pr.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->log_error('Failed to load recent promos', $e);
            return [];
        }
    }

    /** Aktive Promos mit überschrittenem Enddatum. */
    public function count_expired_active(): int
    {
        try {
            return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->prefix}promos WHERE status = 'active' AND end_at IS NOT NULL AND end_at < NOW()");
        } catch (\Throwable $e) {
            $this->log_error('Failed to count expired promos', $e);
            return 0;
        }
    }

    /** Aktuell ausgespielte Promos (aktiv und innerhalb der Laufzeit). */
    public function count_live_promos(): int
    {
        try {
            return (int) $this->db->get_var(
                "SELECT COUNT(*) FROM {$this->prefix}promos
                 WHERE status = 'active' AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW())"
            );
        } catch (\Throwable $e) {
            $this->log_error('Failed to count live promos', $e);
            return 0;
        }
    }

    /** Letzte Änderung einer Promo (für die Sitemap). */
    public function get_last_modified(): string
    {
        try {
            $value = (string) $this->db->get_var("SELECT MAX(updated_at) FROM {$this->prefix}promos WHERE status = 'active'");
            return $value !== '' ? $value : date('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * true, wenn mindestens eine aktive Hook-Platzierung eine ausspielbare Promo hat
     * (steuert das Laden des öffentlichen Stylesheets; eine Abfrage pro Request).
     */
    public function has_live_hook_placements(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        try {
            $value = $this->db->get_var(
                "SELECT 1 FROM {$this->prefix}promo_placements p
                 INNER JOIN {$this->prefix}promos pr ON pr.placement_id = p.id
                 WHERE p.status = 'active' AND p.theme_hook <> 'manual' AND pr.status = 'active'
                   AND (pr.start_at IS NULL OR pr.start_at <= NOW()) AND (pr.end_at IS NULL OR pr.end_at >= NOW())
                 LIMIT 1"
            );
            $result = $value !== null && $value !== false;
        } catch (\Throwable $e) {
            $this->log_error('Failed to check hook placements', $e);
            $result = false;
        }

        return $result;
    }

    /**
     * Dupliziert eine Promo als Entwurf (Zähler auf 0).
     *
     * @return array{success:bool,message?:string,error?:string,id?:int}
     */
    public function duplicate_promo(int $id): array
    {
        $promo = $id > 0 ? $this->get_promo($id) : null;
        if ($promo === null) {
            return ['success' => false, 'error' => 'Promo nicht gefunden.'];
        }

        $copy = $promo;
        $copy['promo_id'] = 0;
        $copy['title'] = mb_substr((string) $promo['title'] . ' (Kopie)', 0, 180);
        $copy['slug'] = (string) $promo['slug'] . '-kopie';
        $copy['status'] = 'draft';
        foreach (['start_at', 'end_at'] as $key) {
            $copy[$key] = !empty($promo[$key]) ? date('Y-m-d\\TH:i', (int) strtotime((string) $promo[$key])) : '';
        }

        $result = $this->save_promo($copy);
        if (!empty($result['success'])) {
            $result['message'] = 'Promo als Entwurf dupliziert.';
            $result['id'] = (int) $this->db->lastInsertId();
        }

        return $result;
    }

    /**
     * Setzt Impressions und Klicks zurück (einzelne Promo oder alle).
     *
     * @return array{success:bool,message?:string,error?:string}
     */
    public function reset_stats(int $id = 0): array
    {
        try {
            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE {$this->prefix}promos SET impression_count = 0, click_count = 0 WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $this->db->query("UPDATE {$this->prefix}promos SET impression_count = 0, click_count = 0");
            }
            $this->invalidate_read_caches();
            return ['success' => true, 'message' => $id > 0 ? 'Statistik der Promo zurückgesetzt.' : 'Statistik aller Promos zurückgesetzt.'];
        } catch (\Throwable $e) {
            $this->log_error('Failed to reset stats', $e);
            return ['success' => false, 'error' => 'Die Statistik konnte nicht zurückgesetzt werden.'];
        }
    }

    /**
     * Export-Zeilen (Leistungsdaten) für CSV/JSON.
     *
     * @return list<array<string,mixed>>
     */
    public function get_export_rows(): array
    {
        $rows = [];
        foreach ($this->get_promos() as $promo) {
            $impressions = (int) ($promo['impression_count'] ?? 0);
            $clicks = (int) ($promo['click_count'] ?? 0);
            $rows[] = [
                'id' => (int) $promo['id'],
                'title' => (string) $promo['title'],
                'slug' => (string) $promo['slug'],
                'status' => (string) $promo['status'],
                'placement' => (string) ($promo['placement_name'] ?? ''),
                'target_url' => (string) ($promo['target_url'] ?? ''),
                'start_at' => (string) ($promo['start_at'] ?? ''),
                'end_at' => (string) ($promo['end_at'] ?? ''),
                'priority' => (int) ($promo['priority'] ?? 0),
                'featured' => (int) ($promo['is_featured'] ?? 0),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr_percent' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
                'updated_at' => (string) ($promo['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    public function status_label(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    private function normalize_datetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);

        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i:s') : null;
    }

    private function clean_text(string $value): string
    {
        return mb_substr(trim(strip_tags($value)), 0, 255);
    }

    private function clean_textarea(string $value): string
    {
        return mb_substr(trim(strip_tags($value)), 0, 2000);
    }

    private function clean_html(string $value): string
    {
        $html = trim(strip_tags($value, '<p><a><strong><em><ul><ol><li><br><h2><h3><h4><span>'));
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace_callback('/\s+href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', function (array $matches): string {
            $href = html_entity_decode((string) ($matches[2] ?? $matches[3] ?? $matches[4] ?? ''), ENT_QUOTES, 'UTF-8');
            $safeHref = $this->clean_url($href);
            return $safeHref !== '' ? ' href="' . htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') . '"' : '';
        }, $html) ?? '';

        return mb_substr($html, 0, 20000);
    }

    private function clean_url(string $value): string
    {
        $url = trim($value);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]]/', $url) === 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            return '';
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return '';
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        return $url;
    }

    private function clean_utm_token(string $value, int $maxLength = 120): string
    {
        $token = strtolower(trim(strip_tags($value)));
        $token = preg_replace('/\s+/', '-', $token) ?? '';
        $token = preg_replace('/[^a-z0-9._~-]+/', '-', $token) ?? '';
        $token = trim($token, '-');

        return mb_substr($token, 0, max(1, $maxLength));
    }

    private function normalize_theme_hook(string $value): string
    {
        $value = trim($value);
        return array_key_exists($value, self::THEME_HOOK_OPTIONS) ? $value : 'manual';
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(strip_tags($value)));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        return mb_substr(trim($slug, '-') ?: 'promo', 0, 120);
    }

    private function ensure_unique_placement_slug(string $slug, int $ignoreId = 0): string
    {
        $base = $slug;
        $i = 2;
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM {$this->prefix}promo_placements WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $ignoreId]);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }

    private function assign_default_theme_hooks(): void
    {
        $defaults = [
            'homepage-hero' => ['after_header', 5],
            'sidebar-cta' => ['before_footer', 20],
            'home-content-highlights' => ['home_content', 15],
        ];

        foreach ($defaults as $slug => [$themeHook, $priority]) {
            $stmt = $this->db->prepare("UPDATE {$this->prefix}promo_placements SET theme_hook = ?, hook_priority = ? WHERE slug = ? AND (theme_hook IS NULL OR theme_hook = '' OR theme_hook = 'manual')");
            $stmt->execute([$themeHook, $priority, $slug]);
        }
    }

    private function ensure_unique_promo_slug(string $slug, int $ignoreId = 0): string
    {
        $base = $slug;
        $i = 2;
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM {$this->prefix}promos WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $ignoreId]);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }

    private function invalidate_read_caches(): void
    {
        $this->settingsCache = null;
        $this->placementsCache = null;
        $this->promosCache = null;
        $this->activePromosCache = [];
        $this->hookPlacementsCache = [];
        $this->placementPromosCache = [];
    }

    private function log_error(string $message, \Throwable $e): void
    {
        error_log('[cms-promos] ' . $message . ': ' . $e->getMessage());
    }
}

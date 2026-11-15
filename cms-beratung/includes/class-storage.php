<?php
/**
 * CMS Beratung – data access for landingpages, presets and submissions.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Storage
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;
    private string $prefix = 'cms_';

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        if (class_exists('CMS\\Database')) {
            $db = \CMS\Database::instance();
            $this->pdo = $db->getPdo();
            $this->prefix = method_exists($db, 'getPrefix') ? $db->getPrefix() : (method_exists($db, 'prefix') ? $db->prefix() : 'cms_');
        }
    }

    /** Spalten der Listenansichten (ohne die großen JSON-Felder außer hero_json für die Standalone-Variante). */
    private const LIST_COLUMNS = 'id, internal_title, public_title, slug, status, template, noindex, hero_json, created_by, created_at, updated_at';

    /** Alle Spalten einer Landingpage (Editor, Export, Rendering). */
    private const PAGE_COLUMNS = 'id, tenant_id, internal_title, public_title, slug, meta_title, meta_description, focus_keyword, status, template, max_content_width, custom_design_enabled, use_global_settings, use_global_design, show_header, show_footer, show_breadcrumb, show_toc, show_anchor_nav, noindex, nofollow, canonical_url, custom_css_class, hero_json, contact_json, seo_json, design_json, sections_json, tracking_enabled, created_by, updated_by, created_at, updated_at';

    private const SUBMISSION_COLUMNS = 's.id, s.landingpage_id, s.sender_name, s.sender_email, s.phone, s.company, s.topic, s.desired_service, s.message, s.internal_note, s.consent, s.copy_to_sender, s.ip_address, s.user_agent, s.status, s.is_spam, s.created_at, l.public_title AS landingpage_title, l.slug AS landingpage_slug';

    /** @var array<int,array{slug:string,standalone_slug:string}>|null */
    private ?array $routeCache = null;

    public function is_available(): bool
    {
        return $this->pdo !== null;
    }

    /** @return array<int,array<string,mixed>> */
    public function all_landingpages(): array
    {
        if ($this->pdo === null) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare('SELECT ' . self::LIST_COLUMNS . " FROM {$this->prefix}beratung_landingpages ORDER BY updated_at DESC, id DESC");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('CMS Beratung landingpages: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gefilterte, paginierte Liste.
     *
     * @param array{status?:string,search?:string,sort?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function list_landingpages(array $filters, int $page = 1, int $perPage = 20): array
    {
        if ($this->pdo === null) {
            return ['rows' => [], 'total' => 0];
        }
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && array_key_exists($status, CMS_Beratung_Settings::statuses())) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        foreach (self::search_words((string) ($filters['search'] ?? '')) as $word) {
            $where[] = '(internal_title LIKE ? OR public_title LIKE ? OR slug LIKE ?)';
            $like = '%' . self::escape_like($word) . '%';
            array_push($params, $like, $like, $like);
        }
        $orderMap = ['updated' => 'updated_at DESC, id DESC', 'title' => 'internal_title ASC, id ASC', 'status' => 'status ASC, updated_at DESC'];
        $order = $orderMap[(string) ($filters['sort'] ?? 'updated')] ?? $orderMap['updated'];
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $perPage = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        try {
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}beratung_landingpages{$whereSql}");
            $count->execute($params);
            $stmt = $this->pdo->prepare('SELECT ' . self::LIST_COLUMNS . " FROM {$this->prefix}beratung_landingpages{$whereSql} ORDER BY {$order} LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset);
            $stmt->execute($params);
            return ['rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
        } catch (\Throwable $e) {
            error_log('CMS Beratung landingpage list: ' . $e->getMessage());
            return ['rows' => [], 'total' => 0];
        }
    }

    /** @return array<string,int> Anzahl je Status plus 'total' */
    public function landingpage_counts(): array
    {
        $counts = ['total' => 0];
        foreach (array_keys(CMS_Beratung_Settings::statuses()) as $status) {
            $counts[$status] = 0;
        }
        if ($this->pdo === null) {
            return $counts;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM {$this->prefix}beratung_landingpages GROUP BY status");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $counts[(string) $row['status']] = (int) $row['cnt'];
                $counts['total'] += (int) $row['cnt'];
            }
        } catch (\Throwable) {
            // Tabelle fehlt: Zähler bleiben 0, der System-Check meldet es.
        }
        return $counts;
    }

    /** @return array<int,array<string,mixed>> zuletzt geänderte Landingpages */
    public function recent_landingpages(int $limit = 6): array
    {
        if ($this->pdo === null) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare("SELECT id, internal_title, public_title, slug, status, updated_at FROM {$this->prefix}beratung_landingpages ORDER BY updated_at DESC, id DESC LIMIT " . max(1, min(50, $limit)));
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Veröffentlichte Slugs samt optionalem Standalone-Slug (für Routen, Sitemap und Seitenauflösung).
     * Einmal pro Request; liest hero_json nur für die Standalone-Angaben.
     *
     * @return array<int,array{id:int,slug:string,standalone_slug:string,noindex:bool,updated_at:string,title:string}>
     */
    public function published_routes(): array
    {
        if ($this->routeCache !== null) {
            return $this->routeCache;
        }
        $this->routeCache = [];
        if ($this->pdo === null) {
            return $this->routeCache;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT id, slug, noindex, updated_at, public_title, hero_json FROM {$this->prefix}beratung_landingpages WHERE status = 'published' ORDER BY id ASC");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $hero = json_decode((string) ($row['hero_json'] ?? '{}'), true);
                $hero = is_array($hero) ? $hero : [];
                $standalone = !empty($hero['standalone_header_enabled']) ? trim((string) ($hero['standalone_header_slug'] ?? ''), '/') : '';
                $slug = trim((string) ($row['slug'] ?? ''), '/');
                if ($slug === '') {
                    continue;
                }
                $this->routeCache[] = [
                    'id' => (int) $row['id'],
                    'slug' => $slug,
                    'standalone_slug' => $standalone !== $slug ? $standalone : '',
                    'noindex' => !empty($row['noindex']),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                    'title' => (string) ($row['public_title'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS Beratung routes: ' . $e->getMessage());
        }
        return $this->routeCache;
    }

    /** Slug der veröffentlichten Landingpage, deren Standalone-Variante unter $standaloneSlug liegt. */
    public function slug_for_standalone(string $standaloneSlug): ?string
    {
        foreach ($this->published_routes() as $route) {
            if ($route['standalone_slug'] !== '' && $route['standalone_slug'] === $standaloneSlug) {
                return $route['slug'];
            }
        }
        return null;
    }

    /** @return list<string> */
    public static function search_words(string $query): array
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', strip_tags($query)));
        if ($query === '') {
            return [];
        }
        $words = array_values(array_filter(array_unique(explode(' ', function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query))), static fn(string $word): bool => $word !== ''));
        return array_slice($words, 0, 8);
    }

    public static function escape_like(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function get_landingpage(int $id): ?array
    {
        if ($this->pdo === null || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT ' . self::PAGE_COLUMNS . " FROM {$this->prefix}beratung_landingpages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode_landingpage($row) : null;
    }

    public function get_landingpage_by_slug(string $slug, bool $publicOnly = true): ?array
    {
        if ($this->pdo === null || $slug === '') {
            return null;
        }

        $sql = 'SELECT ' . self::PAGE_COLUMNS . " FROM {$this->prefix}beratung_landingpages WHERE slug = ?";
        $params = [$slug];
        if ($publicOnly) {
            $sql .= " AND status = 'published'";
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode_landingpage($row) : null;
    }

    /** @param array<string,mixed> $data */
    public function save_landingpage(array $data): int
    {
        if ($this->pdo === null) {
            return 0;
        }

        $id = (int) ($data['id'] ?? 0);
        $payload = CMS_Beratung_Import_Export::sanitize_landingpage_payload($data);
        if ($payload['slug'] === '') {
            $payload['slug'] = CMS_Beratung_Settings::slug($payload['public_title'] ?: $payload['internal_title'], 'beratung');
        }
        $payload['slug'] = $this->unique_slug((string) $payload['slug'], $id);
        $hero = is_array($payload['hero'] ?? null) ? $payload['hero'] : [];
        if (!empty($hero['standalone_header_enabled'])) {
            $standaloneSlug = trim((string) ($hero['standalone_header_slug'] ?? ''));
            if ($standaloneSlug === '') {
                throw new \InvalidArgumentException('Für die zusätzliche Standalone-Publicsite muss ein eigener Slug hinterlegt werden.');
            }
            if ($standaloneSlug === (string) $payload['slug']) {
                throw new \InvalidArgumentException('Der Standalone-Slug muss sich vom normalen Publicsite-Slug unterscheiden.');
            }
            $slugStmt = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_landingpages WHERE slug = ? AND id <> ? LIMIT 1");
            $slugStmt->execute([$standaloneSlug, $id]);
            if ($slugStmt->fetch()) {
                throw new \InvalidArgumentException('Der Standalone-Slug wird bereits als normaler Publicsite-Slug verwendet.');
            }
            $heroStmt = $this->pdo->prepare("SELECT id, hero_json FROM {$this->prefix}beratung_landingpages WHERE id <> ? AND hero_json LIKE ?");
            $heroStmt->execute([$id, '%' . self::escape_like($standaloneSlug) . '%']);
            foreach ($heroStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $existingPage) {
                $existingHero = json_decode((string) ($existingPage['hero_json'] ?? '{}'), true);
                if (is_array($existingHero) && !empty($existingHero['standalone_header_enabled']) && (string) ($existingHero['standalone_header_slug'] ?? '') === $standaloneSlug) {
                    throw new \InvalidArgumentException('Der Standalone-Slug wird bereits von einer anderen Standalone-Publicsite verwendet.');
                }
            }
        }

        $currentUser = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $payload['updated_by'] = $currentUser;
        if ($id <= 0) {
            $payload['created_by'] = $currentUser;
        }
        $this->routeCache = null;

        $columns = [
            'tenant_id', 'internal_title', 'public_title', 'slug', 'meta_title', 'meta_description', 'focus_keyword', 'status', 'template',
            'max_content_width', 'custom_design_enabled', 'use_global_settings', 'use_global_design', 'show_header', 'show_footer', 'show_breadcrumb', 'show_toc',
            'show_anchor_nav', 'noindex', 'nofollow', 'canonical_url', 'custom_css_class', 'hero_json', 'contact_json', 'seo_json', 'design_json', 'sections_json', 'tracking_enabled', 'created_by', 'updated_by',
        ];

        if ($id > 0) {
            $assignments = implode(', ', array_map(static fn(string $col): string => $col . ' = ?', array_filter($columns, static fn(string $col): bool => $col !== 'created_by')));
            $values = [];
            foreach (array_filter($columns, static fn(string $col): bool => $col !== 'created_by') as $column) {
                $values[] = $payload[$column] ?? null;
            }
            $values[] = $id;
            $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_landingpages SET {$assignments} WHERE id = ?");
            $stmt->execute($values);
            return $id;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_landingpages (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
        $values = [];
        foreach ($columns as $column) {
            $values[] = $payload[$column] ?? null;
        }
        $stmt->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function update_status(int $id, string $status): bool
    {
        if ($this->pdo === null || !array_key_exists($status, CMS_Beratung_Settings::statuses())) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_landingpages SET status = ? WHERE id = ?");
        $this->routeCache = null;
        return $stmt->execute([$status, $id]);
    }

    /** @param array<int,int> $ids */
    public function update_status_bulk(array $ids, string $status): int
    {
        $ids = self::clean_ids($ids);
        if ($this->pdo === null || $ids === [] || !array_key_exists($status, CMS_Beratung_Settings::statuses())) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_landingpages SET status = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$status], $ids));
        $this->routeCache = null;
        return $stmt->rowCount();
    }

    /** @param array<int,int> $ids */
    public function delete_landingpages(array $ids): int
    {
        $ids = self::clean_ids($ids);
        if ($this->pdo === null || $ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_landingpages WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $this->routeCache = null;
        return $stmt->rowCount();
    }

    /** @param array<int,mixed> $ids @return list<int> */
    public static function clean_ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static fn(mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids), static fn(int $id): bool => $id > 0)));
    }

    public function duplicate_landingpage(int $id): int
    {
        $page = $this->get_landingpage($id);
        if ($page === null) {
            return 0;
        }
        unset($page['id'], $page['created_at'], $page['updated_at']);
        $page['internal_title'] = (string) $page['internal_title'] . ' Kopie';
        $page['public_title'] = (string) $page['public_title'] . ' Kopie';
        $page['slug'] = (string) $page['slug'] . '-kopie';
        $page['status'] = 'draft';
        return $this->save_landingpage($page);
    }

    public function suggest_unique_slug(string $slug, int $ignoreId = 0): string
    {
        return $this->unique_slug($slug, $ignoreId);
    }

    public function delete_landingpage(int $id): bool
    {
        if ($this->pdo === null || $id <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_landingpages WHERE id = ?");
        $this->routeCache = null;
        return $stmt->execute([$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function all_presets(): array
    {
        if ($this->pdo === null) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, slug, description, design_json, is_system, updated_at FROM {$this->prefix}beratung_design_presets ORDER BY is_system DESC, name ASC");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Eigenes Design-Preset anlegen.
     *
     * @param array<string,string> $colors
     */
    public function create_custom_preset(string $name, string $description, array $colors): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $name = mb_substr(CMS_Beratung_Settings::text($name), 0, 160);
        if ($name === '') {
            throw new \InvalidArgumentException('Bitte einen Namen für das Preset angeben.');
        }
        $design = [];
        foreach (self::preset_color_keys() as $key) {
            $design[$key] = CMS_Beratung_Settings::color((string) ($colors[$key] ?? ''), CMS_Beratung_Settings::defaults()[$key] ?? '#ffffff');
        }
        $base = CMS_Beratung_Settings::slug($name, 'preset');
        $slug = $base;
        $exists = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_design_presets WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        for ($i = 2; $i < 200; $i++) {
            $exists->execute([$slug]);
            if (!$exists->fetch()) {
                break;
            }
            $slug = $base . '-' . $i;
        }
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_design_presets (tenant_id, name, slug, description, design_json, is_system) VALUES (NULL, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $slug, mb_substr(CMS_Beratung_Settings::text($description), 0, 500), json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Nur eigene (nicht System-)Presets lassen sich löschen. */
    public function delete_custom_preset(int $id): bool
    {
        if ($this->pdo === null || $id <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_design_presets WHERE id = ? AND is_system = 0");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<string> */
    public static function preset_color_keys(): array
    {
        return ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'heading_color', 'button_color', 'button_text_color', 'card_background_color', 'card_border_color'];
    }

    /** @param array<string,mixed> $preset */
    public function create_preset_if_missing(array $preset): void
    {
        if ($this->pdo === null) {
            return;
        }
        $slug = CMS_Beratung_Settings::slug((string) ($preset['slug'] ?? ''), 'preset');
        $exists = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_design_presets WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        $exists->execute([$slug]);
        if ($exists->fetch()) {
            return;
        }
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_design_presets (tenant_id, name, slug, description, design_json, is_system) VALUES (NULL, ?, ?, ?, ?, ?)");
        $stmt->execute([
            CMS_Beratung_Settings::text((string) ($preset['name'] ?? $slug)),
            $slug,
            CMS_Beratung_Settings::text((string) ($preset['description'] ?? '')),
            (string) ($preset['design_json'] ?? '{}'),
            (int) ($preset['is_system'] ?? 0),
        ]);
    }

    /** @return array<string,mixed> */
    public function m365_faq_config(): array
    {
        $defaults = self::default_m365_faq_config();
        if ($this->pdo === null) {
            return $defaults;
        }

        $config = $defaults;
        foreach (['enabled', 'eyebrow', 'title', 'intro', 'anchor_id', 'allow_multiple', 'open_behavior', 'icon_style', 'schema_enabled'] as $key) {
            $value = $this->setting_value('m365_faq_' . $key);
            if ($value !== null) {
                $config[$key] = in_array($key, ['enabled', 'allow_multiple', 'schema_enabled'], true) ? ($value === '1') : $value;
            }
        }
        $itemsJson = $this->setting_value('m365_faq_items_json');
        $items = is_string($itemsJson) ? json_decode($itemsJson, true) : null;
        $config['items'] = self::sanitize_m365_faq_items(is_array($items) ? $items : $defaults['items']);
        return $config;
    }

    /** @param array<string,mixed> $data */
    public function save_m365_faq_config(array $data): void
    {
        if ($this->pdo === null) {
            return;
        }
        $config = [
            'enabled' => !empty($data['enabled']),
            'eyebrow' => CMS_Beratung_Settings::text((string) ($data['eyebrow'] ?? 'FAQ')),
            'title' => CMS_Beratung_Settings::text((string) ($data['title'] ?? 'Häufige Fragen zu Microsoft 365 Beratung')),
            'intro' => CMS_Beratung_Settings::text((string) ($data['intro'] ?? '')),
            'anchor_id' => CMS_Beratung_Settings::slug((string) ($data['anchor_id'] ?? 'faq'), 'faq'),
            'allow_multiple' => !empty($data['allow_multiple']),
            'open_behavior' => in_array((string) ($data['open_behavior'] ?? 'first'), ['none', 'first', 'custom'], true) ? (string) $data['open_behavior'] : 'first',
            'icon_style' => in_array((string) ($data['icon_style'] ?? 'plus'), ['plus', 'chevron', 'question'], true) ? (string) $data['icon_style'] : 'plus',
            'schema_enabled' => !empty($data['schema_enabled']),
            'items' => self::sanitize_m365_faq_items($this->faq_items_from_post($data)),
        ];

        foreach (['enabled', 'eyebrow', 'title', 'intro', 'anchor_id', 'allow_multiple', 'open_behavior', 'icon_style', 'schema_enabled'] as $key) {
            $value = in_array($key, ['enabled', 'allow_multiple', 'schema_enabled'], true) ? (!empty($config[$key]) ? '1' : '0') : (string) $config[$key];
            $this->save_setting('m365_faq_' . $key, $value);
        }
        $this->save_setting('m365_faq_items_json', json_encode($config['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    public function seed_m365_faqs_if_missing(): void
    {
        if ($this->pdo === null || $this->setting_value('m365_faq_items_json') !== null) {
            return;
        }
        $this->save_m365_faq_config(self::default_m365_faq_config());
    }

    /** @return array<string,mixed> */
    public static function default_m365_faq_config(): array
    {
        return [
            'enabled' => true,
            'eyebrow' => 'FAQ',
            'title' => 'Häufige Fragen zu Microsoft 365, Copilot und Security Beratung',
            'intro' => 'Antworten auf typische Fragen vor einem Microsoft 365 Beratungsprojekt, Copilot Readiness Check oder Security Review.',
            'anchor_id' => 'faq',
            'allow_multiple' => false,
            'open_behavior' => 'first',
            'icon_style' => 'plus',
            'schema_enabled' => true,
            'items' => [
                ['enabled' => true, 'question' => 'Was ist Microsoft 365 Beratung?', 'answer' => 'Microsoft 365 Beratung unterstützt bei Planung, Bewertung, Konfiguration und Optimierung von Diensten wie Entra ID, Exchange Online, SharePoint, OneDrive, Teams, Purview, Defender und Copilot.', 'default_open' => true, 'sort_order' => 10],
                ['enabled' => true, 'question' => 'Für wen eignet sich eine Microsoft 365 Beratung?', 'answer' => 'Sie eignet sich für Unternehmen, IT-Abteilungen und Admin-Teams, die Microsoft 365 sicherer, strukturierter und effizienter nutzen möchten oder vor größeren Änderungen wie Copilot, Security Reviews oder Governance-Projekten stehen.', 'default_open' => false, 'sort_order' => 20],
                ['enabled' => true, 'question' => 'Was ist Microsoft 365 Copilot?', 'answer' => 'Microsoft 365 Copilot verbindet KI-Funktionen mit Microsoft 365 Apps und Unternehmensdaten. Der Nutzen hängt stark davon ab, ob Berechtigungen, Datenqualität, Compliance und Governance sauber vorbereitet sind.', 'default_open' => false, 'sort_order' => 30],
                ['enabled' => true, 'question' => 'Warum ist ein Copilot Readiness Check sinnvoll?', 'answer' => 'Ein Readiness Check zeigt, ob Datenzugriffe, SharePoint-Strukturen, Sensitivity Labels, DLP, Audit, Identitäten und Pilotprozesse bereit für einen kontrollierten Copilot-Einsatz sind.', 'default_open' => false, 'sort_order' => 40],
                ['enabled' => true, 'question' => 'Ist Microsoft Copilot DSGVO-konform einsetzbar?', 'answer' => 'Ein DSGVO-konformer Einsatz hängt von Konfiguration, Datenklassifizierung, Berechtigungen, Aufbewahrung, organisatorischen Regeln und rechtlicher Bewertung ab. Die Beratung liefert die technische Grundlage für diese Einordnung.', 'default_open' => false, 'sort_order' => 50],
                ['enabled' => true, 'question' => 'Welche Voraussetzungen braucht ein Microsoft 365 Tenant für Copilot?', 'answer' => 'Wichtig sind saubere Identitäten, passende Lizenzen, kontrollierte Berechtigungen, gepflegte SharePoint- und OneDrive-Inhalte, Purview-Konfigurationen, Auditierbarkeit und klare Governance-Regeln.', 'default_open' => false, 'sort_order' => 60],
                ['enabled' => true, 'question' => 'Was wird bei einem Microsoft 365 Security Review geprüft?', 'answer' => 'Geprüft werden unter anderem Entra ID, MFA, Conditional Access, Adminrollen, Break-Glass-Konten, Gastzugriffe, Defender-Konfigurationen, Secure Score, Mail-Schutz und relevante Betriebsprozesse.', 'default_open' => false, 'sort_order' => 70],
                ['enabled' => true, 'question' => 'Was ist Entra ID und warum ist es wichtig?', 'answer' => 'Microsoft Entra ID ist der zentrale Identitätsdienst für Microsoft 365. Rollen, Anmeldeschutz, MFA, Conditional Access und Gastzugriffe beeinflussen direkt die Sicherheit der gesamten Umgebung.', 'default_open' => false, 'sort_order' => 80],
                ['enabled' => true, 'question' => 'Was ist Conditional Access?', 'answer' => 'Conditional Access steuert Zugriffe anhand von Bedingungen wie Benutzer, Gerät, Standort, Risiko, App und Authentifizierungsstärke. Richtig konfiguriert ist es ein zentraler Sicherheitsbaustein.', 'default_open' => false, 'sort_order' => 90],
                ['enabled' => true, 'question' => 'Was umfasst SharePoint und OneDrive Governance?', 'answer' => 'Governance umfasst Struktur, Site- und Team-Erstellung, Berechtigungen, externe Freigaben, Namenskonzepte, Lifecycle, Verantwortlichkeiten und Regeln für den Umgang mit Dateien und Informationen.', 'default_open' => false, 'sort_order' => 100],
                ['enabled' => true, 'question' => 'Warum sind Berechtigungen vor Copilot besonders wichtig?', 'answer' => 'Copilot berücksichtigt vorhandene Berechtigungen. Wenn zu viele Personen Zugriff auf sensible Inhalte haben, können diese Inhalte auch leichter in Antworten und Suchergebnissen sichtbar werden.', 'default_open' => false, 'sort_order' => 110],
                ['enabled' => true, 'question' => 'Was ist Microsoft Purview?', 'answer' => 'Microsoft Purview bündelt Funktionen für Informationsschutz, Sensitivity Labels, DLP, Aufbewahrung, eDiscovery, Audit und Compliance. Es hilft, Daten in Microsoft 365 kontrolliert zu schützen.', 'default_open' => false, 'sort_order' => 120],
                ['enabled' => true, 'question' => 'Was ist Microsoft Defender im Microsoft 365 Umfeld?', 'answer' => 'Microsoft Defender umfasst Schutzfunktionen für Identitäten, Endpunkte, E-Mail, Cloud Apps und Bedrohungserkennung. Die konkrete Ausprägung hängt von Lizenzen und Konfiguration ab.', 'default_open' => false, 'sort_order' => 130],
                ['enabled' => true, 'question' => 'Kann die Beratung remote durchgeführt werden?', 'answer' => 'Ja, viele Reviews, Workshops und Readiness Checks können remote durchgeführt werden. Je nach Umfang werden Ergebnisse, Empfehlungen und nächste Schritte dokumentiert.', 'default_open' => false, 'sort_order' => 140],
                ['enabled' => true, 'question' => 'Wie läuft ein typisches Beratungsprojekt ab?', 'answer' => 'Typisch sind Erstgespräch, Zielklärung, technische Analyse, Bewertung, priorisierter Maßnahmenplan, optional begleitete Umsetzung sowie Dokumentation und Übergabe an das Admin-Team.', 'default_open' => false, 'sort_order' => 150],
                ['enabled' => true, 'question' => 'Gibt es nach der Beratung eine Dokumentation?', 'answer' => 'Ja, Ergebnisse, Findings, Entscheidungen, Risiken und empfohlene Maßnahmen werden verständlich dokumentiert, damit Admin- und Projektteams damit weiterarbeiten können.', 'default_open' => false, 'sort_order' => 160],
                ['enabled' => true, 'question' => 'Können Admin-Workshops durchgeführt werden?', 'answer' => 'Ja, Workshops für Admin-Teams können Themen wie Entra ID, Security, SharePoint Governance, Exchange Online, Teams, Purview, Copilot Readiness und PowerShell-Automatisierung abdecken.', 'default_open' => false, 'sort_order' => 170],
                ['enabled' => true, 'question' => 'Was kostet eine Microsoft 365 Beratung?', 'answer' => 'Die Kosten hängen von Umfang, Ziel, gewünschter Tiefe und Anzahl der betroffenen Microsoft 365 Bereiche ab. Nach einem Erstgespräch lässt sich der Aufwand realistisch eingrenzen.', 'default_open' => false, 'sort_order' => 180],
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    public static function sanitize_m365_faq_items(array $items): array
    {
        $clean = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = CMS_Beratung_Settings::text((string) ($item['question'] ?? ''));
            $answer = CMS_Beratung_Settings::text((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $clean[] = [
                'enabled' => array_key_exists('enabled', $item) ? !empty($item['enabled']) : true,
                'question' => substr($question, 0, 255),
                'answer' => substr($answer, 0, 1800),
                'default_open' => !empty($item['default_open']),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
        }
        usort($clean, static fn(array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
        return $clean;
    }

    /** @param array<string,mixed> $data @return array<int,array<string,mixed>> */
    private function faq_items_from_post(array $data): array
    {
        $questions = is_array($data['faq_question'] ?? null) ? $data['faq_question'] : [];
        $answers = is_array($data['faq_answer'] ?? null) ? $data['faq_answer'] : [];
        $orders = is_array($data['faq_sort_order'] ?? null) ? $data['faq_sort_order'] : [];
        $enabled = is_array($data['faq_enabled'] ?? null) ? $data['faq_enabled'] : [];
        $defaultOpen = is_array($data['faq_default_open'] ?? null) ? $data['faq_default_open'] : [];
        $items = [];
        foreach (array_keys($questions) as $index) {
            $items[] = [
                'enabled' => array_key_exists((string) $index, $enabled) || array_key_exists((int) $index, $enabled),
                'question' => is_scalar($questions[$index] ?? null) ? (string) $questions[$index] : '',
                'answer' => is_scalar($answers[$index] ?? null) ? (string) $answers[$index] : '',
                'default_open' => array_key_exists((string) $index, $defaultOpen) || array_key_exists((int) $index, $defaultOpen),
                'sort_order' => is_scalar($orders[$index] ?? null) ? (int) $orders[$index] : 0,
            ];
        }
        return $items;
    }

    private function setting_value(string $key): ?string
    {
        return $this->pdo === null ? null : CMS_Beratung_Settings::raw($key);
    }

    private function save_setting(string $key, string $value): void
    {
        if ($this->pdo === null) {
            return;
        }
        try {
            $exists = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_settings WHERE tenant_id IS NULL AND setting_key = ? LIMIT 1");
            $exists->execute([$key]);
            if ($exists->fetch()) {
                $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_settings SET setting_value = ? WHERE tenant_id IS NULL AND setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
                $stmt->execute([$key, $value]);
            }
            CMS_Beratung_Settings::flush();
        } catch (\Throwable $e) {
            error_log('CMS Beratung FAQ setting save failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function submissions(int $limit = 100): array
    {
        return $this->list_submissions([], 1, max(1, min(500, $limit)))['rows'];
    }

    /**
     * Gefilterte, paginierte Anfragen.
     *
     * @param array{status?:string,landingpage_id?:int,search?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function list_submissions(array $filters, int $page = 1, int $perPage = 25): array
    {
        if ($this->pdo === null) {
            return ['rows' => [], 'total' => 0];
        }
        [$whereSql, $params] = $this->submission_where($filters);
        $perPage = max(1, min(500, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        try {
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}beratung_form_submissions s{$whereSql}");
            $count->execute($params);
            $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . " FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id{$whereSql} ORDER BY s.created_at DESC, s.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset);
            $stmt->execute($params);
            return ['rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
        } catch (\Throwable $e) {
            error_log('CMS Beratung submissions: ' . $e->getMessage());
            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * Alle Anfragen eines Filters für den CSV-Export (blockweise, höchstens 20.000).
     *
     * @param array{status?:string,landingpage_id?:int,search?:string} $filters
     * @return \Generator<int,array<string,mixed>>
     */
    public function iterate_submissions(array $filters): \Generator
    {
        $page = 1;
        do {
            $rows = $this->list_submissions($filters, $page, 500)['rows'];
            foreach ($rows as $row) {
                yield $row;
            }
            $page++;
        } while (count($rows) === 500 && $page <= 40);
    }

    /** @param array{status?:string,landingpage_id?:int,search?:string} $filters @return array{0:string,1:list<mixed>} */
    private function submission_where(array $filters): array
    {
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && array_key_exists($status, self::submission_statuses())) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        $landingpageId = (int) ($filters['landingpage_id'] ?? 0);
        if ($landingpageId > 0) {
            $where[] = 's.landingpage_id = ?';
            $params[] = $landingpageId;
        }
        foreach (self::search_words((string) ($filters['search'] ?? '')) as $word) {
            $like = '%' . self::escape_like($word) . '%';
            $where[] = '(s.sender_name LIKE ? OR s.sender_email LIKE ? OR s.company LIKE ? OR s.topic LIKE ? OR s.message LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /** @return array<string,int> Anzahl je Status plus 'total' */
    public function submission_counts(): array
    {
        $counts = ['total' => 0];
        foreach (array_keys(self::submission_statuses()) as $status) {
            $counts[$status] = 0;
        }
        if ($this->pdo === null) {
            return $counts;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM {$this->prefix}beratung_form_submissions GROUP BY status");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $counts[(string) $row['status']] = (int) $row['cnt'];
                $counts['total'] += (int) $row['cnt'];
            }
        } catch (\Throwable) {
            // Tabelle fehlt.
        }
        return $counts;
    }

    public function count_new_submissions(): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}beratung_form_submissions WHERE status = 'new'");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function submission(int $id): ?array
    {
        if ($this->pdo === null || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . ", s.payload_json FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** Status einer ungelesenen Anfrage beim Öffnen auf „Gelesen“ setzen. */
    public function mark_submission_read(int $id): void
    {
        if ($this->pdo === null || $id <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_form_submissions SET status = 'read' WHERE id = ? AND status = 'new'");
        $stmt->execute([$id]);
    }

    /** @param array<int,int> $ids */
    public function update_submission_status_bulk(array $ids, string $status): int
    {
        $ids = self::clean_ids($ids);
        if ($this->pdo === null || $ids === [] || !array_key_exists($status, self::submission_statuses())) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_form_submissions SET status = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$status], $ids));
        return $stmt->rowCount();
    }

    /** @return array<int,array<string,mixed>> Anfragen einer Person (DSGVO), Abgleich über die E-Mail-Adresse */
    public function submissions_for_email(string $email): array
    {
        $email = trim($email);
        if ($this->pdo === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . " FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id WHERE s.sender_email = ? ORDER BY s.created_at ASC LIMIT 1000");
            $stmt->execute([$email]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function delete_submissions_for_email(string $email): int
    {
        $email = trim($email);
        if ($this->pdo === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_form_submissions WHERE sender_email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount();
    }

    /** Anzahl der Anfragen, die älter als $days Tage sind (Trockenlauf der Löschfrist). */
    public function count_submissions_older_than(int $days): int
    {
        if ($this->pdo === null || $days <= 0) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}beratung_form_submissions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->bindValue(1, max(1, min(3650, $days)), \PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param array<string,mixed> $data */
    public function create_submission(array $data): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $settings = CMS_Beratung_Settings::all();
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_form_submissions (landingpage_id, tenant_id, sender_name, sender_email, phone, company, topic, desired_service, message, internal_note, consent, copy_to_sender, payload_json, ip_address, user_agent, status, is_spam) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, 'new', ?)");
        $stmt->execute([
            (int) ($data['landingpage_id'] ?? 0) ?: null,
            CMS_Beratung_Settings::text((string) ($data['sender_name'] ?? '')),
            filter_var((string) ($data['sender_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $data['sender_email'] : null,
            CMS_Beratung_Settings::text((string) ($data['phone'] ?? '')),
            CMS_Beratung_Settings::text((string) ($data['company'] ?? '')),
            CMS_Beratung_Settings::text((string) ($data['topic'] ?? '')),
            CMS_Beratung_Settings::text((string) ($data['desired_service'] ?? '')),
            mb_substr(CMS_Beratung_Settings::text((string) ($data['message'] ?? '')), 0, 10000),
            !empty($data['consent']) ? 1 : 0,
            !empty($data['copy_to_sender']) ? 1 : 0,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}',
            ($settings['store_ip_enabled'] ?? '0') === '1' ? substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) : '',
            ($settings['store_user_agent_enabled'] ?? '0') === '1' ? substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) : '',
            !empty($data['is_spam']) ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update_submission(int $id, string $status, string $note): bool
    {
        if ($this->pdo === null || $id <= 0 || !array_key_exists($status, self::submission_statuses())) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_form_submissions SET status = ?, internal_note = ? WHERE id = ?");
        return $stmt->execute([$status, CMS_Beratung_Settings::text($note), $id]);
    }

    /** @param array<int,int> $ids */
    public function delete_submissions(array $ids): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_form_submissions WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public function purge_old_submissions(int $days): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $days = max(1, min(3650, $days));
        $deleted = 0;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_form_submissions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 500");
        for ($round = 0; $round < 200; $round++) {
            $stmt->bindValue(1, $days, \PDO::PARAM_INT);
            $stmt->execute();
            $affected = $stmt->rowCount();
            $deleted += $affected;
            if ($affected < 500) {
                break;
            }
        }
        return $deleted;
    }

    /** @return array<string,string> */
    public static function submission_statuses(): array
    {
        return [
            'new' => 'Neu',
            'read' => 'Gelesen',
            'in_progress' => 'In Bearbeitung',
            'answered' => 'Beantwortet',
            'done' => 'Erledigt',
            'archived' => 'Archiviert',
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decode_landingpage(array $row): array
    {
        foreach (['hero_json', 'contact_json', 'seo_json', 'design_json', 'sections_json'] as $key) {
            $decoded = json_decode((string) ($row[$key] ?? ''), true);
            $row[str_replace('_json', '', $key)] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    private function unique_slug(string $slug, int $ignoreId = 0): string
    {
        if ($this->pdo === null) {
            return $slug;
        }
        $base = CMS_Beratung_Settings::slug($slug, 'beratung');
        $candidate = $base;
        $i = 2;
        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_landingpages WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$candidate, $ignoreId]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $base . '-' . $i++;
        }
    }
}

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
    /** Explizite Spaltenlisten (keine Wildcard-Selects). */
    private const LANDINGPAGE_COLUMNS = 'id, tenant_id, internal_title, public_title, slug, meta_title, meta_description, focus_keyword, status, template, max_content_width, custom_design_enabled, use_global_settings, use_global_design, show_header, show_footer, show_breadcrumb, show_toc, show_anchor_nav, noindex, nofollow, canonical_url, custom_css_class, hero_json, contact_json, seo_json, design_json, sections_json, tracking_enabled, created_by, updated_by, created_at, updated_at';
    private const SUBMISSION_COLUMNS = 's.id, s.landingpage_id, s.tenant_id, s.sender_name, s.sender_email, s.phone, s.company, s.topic, s.desired_service, s.message, s.internal_note, s.consent, s.copy_to_sender, s.payload_json, s.ip_address, s.user_agent, s.status, s.is_spam, s.created_at';
    private const PRESET_COLUMNS = 'id, tenant_id, name, slug, description, design_json, is_system, created_at, updated_at';

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

    /** @return array<int,array<string,mixed>> */
    public function all_landingpages(): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT ' . self::LANDINGPAGE_COLUMNS . " FROM {$this->prefix}beratung_landingpages ORDER BY updated_at DESC, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function get_landingpage(int $id): ?array
    {
        if ($this->pdo === null || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT ' . self::LANDINGPAGE_COLUMNS . " FROM {$this->prefix}beratung_landingpages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode_landingpage($row) : null;
    }

    public function get_landingpage_by_slug(string $slug, bool $publicOnly = true): ?array
    {
        if ($this->pdo === null || $slug === '') {
            return null;
        }

        $sql = 'SELECT ' . self::LANDINGPAGE_COLUMNS . " FROM {$this->prefix}beratung_landingpages WHERE slug = ?";
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

        CMS_Beratung_Installer::ensure_for_admin_save();
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
            foreach ($this->all_landingpages() as $existingPage) {
                if ((int) ($existingPage['id'] ?? 0) === $id) {
                    continue;
                }
                $existingHero = json_decode((string) ($existingPage['hero_json'] ?? '{}'), true);
                if (is_array($existingHero) && !empty($existingHero['standalone_header_enabled']) && (string) ($existingHero['standalone_header_slug'] ?? '') === $standaloneSlug) {
                    throw new \InvalidArgumentException('Der Standalone-Slug wird bereits von einer anderen Standalone-Publicsite verwendet.');
                }
            }
        }

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
            $this->invalidate_sitemaps();
            return $id;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_landingpages (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
        $values = [];
        foreach ($columns as $column) {
            $values[] = $payload[$column] ?? null;
        }
        $stmt->execute($values);
        $newId = (int) $this->pdo->lastInsertId();
        $this->invalidate_sitemaps();
        return $newId;
    }

    public function update_status(int $id, string $status): bool
    {
        if ($this->pdo === null || !array_key_exists($status, CMS_Beratung_Settings::statuses())) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_landingpages SET status = ? WHERE id = ?");
        $ok = $stmt->execute([$status, $id]);
        $this->invalidate_sitemaps();
        return $ok;
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
        $ok = $stmt->execute([$id]);
        $this->invalidate_sitemaps();
        return $ok;
    }

    /** Gespeichertes Sitemap-Bundle verwerfen, damit plugins.xml Veröffentlichungen sofort zeigt. */
    private function invalidate_sitemaps(): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }
        try {
            \CMS\Services\SEOService::getInstance()->invalidateSavedSitemaps();
        } catch (\Throwable) {
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function all_presets(): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::PRESET_COLUMNS . " FROM {$this->prefix}beratung_design_presets ORDER BY is_system DESC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
        CMS_Beratung_Installer::ensure_for_admin_save();
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
        if ($this->pdo === null) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM {$this->prefix}beratung_settings WHERE tenant_id IS NULL AND setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return is_scalar($value) ? (string) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
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
                return;
            }
            $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
            $stmt->execute([$key, $value]);
        } catch (\Throwable $e) {
            error_log('CMS Beratung FAQ setting save failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function submissions(int $limit = 100): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . ", l.public_title AS landingpage_title, l.slug AS landingpage_slug FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id ORDER BY s.created_at DESC LIMIT {$limit}");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function submission(int $id): ?array
    {
        if ($this->pdo === null || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . ", l.public_title AS landingpage_title, l.slug AS landingpage_slug FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
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
            CMS_Beratung_Settings::text((string) ($data['message'] ?? '')),
            !empty($data['consent']) ? 1 : 0,
            !empty($data['copy_to_sender']) ? 1 : 0,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ($settings['store_ip_enabled'] ?? '0') === '1' ? substr(CMS_Beratung_Forms::client_ip(), 0, 45) : '',
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

    /**
     * Status mehrerer Anfragen setzen (Sammelaktion); interne Notizen bleiben unverändert.
     *
     * @param array<int,int|string> $ids
     */
    public function set_submissions_status(array $ids, string $status): int
    {
        if ($this->pdo === null || !array_key_exists($status, self::submission_statuses())) {
            return 0;
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0))), 0, 500);
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_form_submissions SET status = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$status], $ids));
        return $stmt->rowCount();
    }

    public function purge_old_submissions(int $days): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $days = max(1, min(3650, $days));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_form_submissions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    /** @return array<string,string> */
    /**
     * Öffentlichen Slug auflösen: eigene Landingpage oder Standalone-Variante einer veröffentlichten Seite.
     *
     * @return array{slug:string,standalone:bool}|null
     */
    public function resolve_public_slug(string $slug): ?array
    {
        if ($this->pdo === null || preg_match('/^[a-z0-9_-]{1,160}$/', $slug) !== 1) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT slug FROM {$this->prefix}beratung_landingpages WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() !== false) {
            return ['slug' => $slug, 'standalone' => false];
        }

        $stmt = $this->pdo->prepare(
            "SELECT slug FROM {$this->prefix}beratung_landingpages
             WHERE status = 'published'
               AND JSON_UNQUOTE(JSON_EXTRACT(hero_json, '$.standalone_header_slug')) = ?
               AND JSON_EXTRACT(hero_json, '$.standalone_header_enabled') IN (1, true, '1')
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $parent = $stmt->fetchColumn();

        return is_string($parent) && $parent !== '' && $parent !== $slug ? ['slug' => $parent, 'standalone' => true] : null;
    }

    /**
     * Veröffentlichte Landingpages (leichtgewichtig) für Sitemap und Suche.
     *
     * @return list<array{id:int,slug:string,public_title:string,meta_description:string,updated_at:string}>
     */
    public function published_index(int $limit = 500): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $limit = max(1, min(1000, $limit));
        $stmt = $this->pdo->prepare("SELECT id, slug, public_title, meta_description, updated_at FROM {$this->prefix}beratung_landingpages WHERE status = 'published' AND noindex = 0 ORDER BY updated_at DESC LIMIT {$limit}");
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Veröffentlichte Landingpages, die alle Suchwörter in Titel, Meta-Beschreibung oder Fokus-Keyword enthalten.
     *
     * @param list<string> $words
     * @return list<array<string,mixed>>
     */
    public function search_published(array $words, int $limit = 10): array
    {
        $words = array_slice(array_values(array_filter(array_map('trim', $words), static fn (string $w): bool => $w !== '')), 0, 8);
        if ($this->pdo === null || $words === []) {
            return [];
        }
        $where = ["status = 'published'", 'noindex = 0'];
        $params = [];
        foreach ($words as $word) {
            $like = '%' . addcslashes(mb_substr($word, 0, 60), '%_\\') . '%';
            $where[] = '(public_title LIKE ? OR meta_description LIKE ? OR focus_keyword LIKE ? OR sections_json LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("SELECT id, slug, public_title, meta_description, updated_at FROM {$this->prefix}beratung_landingpages WHERE " . implode(' AND ', $where) . " ORDER BY updated_at DESC LIMIT {$limit}");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{total:int,published:int,draft:int} */
    public function landingpage_counts(): array
    {
        if ($this->pdo === null) {
            return ['total' => 0, 'published' => 0, 'draft' => 0];
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'published') AS published, SUM(status = 'draft') AS draft FROM {$this->prefix}beratung_landingpages");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return ['total' => (int) ($row['total'] ?? 0), 'published' => (int) ($row['published'] ?? 0), 'draft' => (int) ($row['draft'] ?? 0)];
    }

    /**
     * Formular-Anfragen mit Filter (Status, Landingpage, Suche).
     *
     * @return list<array<string,mixed>>
     */
    /** @return list<array<string,mixed>> Anfragen einer Person (DSGVO-Auskunft), höchstens 1000. */
    public function submissions_for_email(string $email): array
    {
        $email = trim($email);
        if ($this->pdo === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::SUBMISSION_COLUMNS . ", l.public_title AS landingpage_title FROM {$this->prefix}beratung_form_submissions s LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id WHERE s.sender_email = ? ORDER BY s.created_at ASC, s.id ASC LIMIT 1000");
        $stmt->execute([$email]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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

    public function find_submissions(string $status = '', int $landingpageId = 0, string $search = '', int $limit = 200, bool $includeSpam = false): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $where = [];
        $params = [];
        if ($status !== '' && array_key_exists($status, self::submission_statuses())) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ($landingpageId > 0) {
            $where[] = 's.landingpage_id = ?';
            $params[] = $landingpageId;
        }
        if (!$includeSpam) {
            $where[] = 's.is_spam = 0';
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
            $where[] = '(s.sender_name LIKE ? OR s.sender_email LIKE ? OR s.company LIKE ? OR s.topic LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $limit = max(1, min(5000, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::SUBMISSION_COLUMNS . ", l.public_title AS landingpage_title, l.slug AS landingpage_slug
             FROM {$this->prefix}beratung_form_submissions s
             LEFT JOIN {$this->prefix}beratung_landingpages l ON l.id = s.landingpage_id"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY s.created_at DESC, s.id DESC LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,int> Anzahl je Status (ohne Spam) */
    public function submission_counts(): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) FROM {$this->prefix}beratung_form_submissions WHERE is_spam = 0 GROUP BY status");
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []);
    }

    public function count_submissions_older_than(int $days): int
    {
        if ($this->pdo === null || $days <= 0) {
            return 0;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}beratung_form_submissions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Eigenes Design-Preset anlegen bzw. aktualisieren (System-Presets sind schreibgeschützt).
     *
     * @param array<string,string> $design
     */
    public function save_preset(int $id, string $name, string $description, array $design): int
    {
        if ($this->pdo === null) {
            return 0;
        }
        $name = CMS_Beratung_Settings::text($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Bitte einen Namen für das Preset angeben.');
        }
        $json = (string) json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($id > 0) {
            $stmt = $this->pdo->prepare("UPDATE {$this->prefix}beratung_design_presets SET name = ?, description = ?, design_json = ? WHERE id = ? AND is_system = 0");
            $stmt->execute([$name, CMS_Beratung_Settings::text($description), $json, $id]);
            if ($stmt->rowCount() === 0 && $this->preset($id) === null) {
                throw new \InvalidArgumentException('Preset nicht gefunden oder schreibgeschützt.');
            }
            return $id;
        }

        $base = CMS_Beratung_Settings::slug($name, 'preset');
        $slug = $base;
        $check = $this->pdo->prepare("SELECT id FROM {$this->prefix}beratung_design_presets WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        for ($i = 2; $i < 100; $i++) {
            $check->execute([$slug]);
            if ($check->fetchColumn() === false) {
                break;
            }
            $slug = $base . '-' . $i;
        }
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}beratung_design_presets (tenant_id, name, slug, description, design_json, is_system) VALUES (NULL, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $slug, CMS_Beratung_Settings::text($description), $json]);

        return (int) $this->pdo->lastInsertId();
    }

    public function preset(int $id): ?array
    {
        if ($this->pdo === null || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::PRESET_COLUMNS . " FROM {$this->prefix}beratung_design_presets WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function delete_preset(int $id): bool
    {
        if ($this->pdo === null || $id <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}beratung_design_presets WHERE id = ? AND is_system = 0");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

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

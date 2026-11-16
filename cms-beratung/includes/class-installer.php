<?php
/**
 * CMS Beratung – Installer and versioned data model.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Installer
{
    public static function install(): void
    {
        self::create_tables();
        self::seed_defaults();
        self::store_db_version(CMS_BERATUNG_DB_VERSION);
    }

    public static function maybe_install(): void
    {
        if (self::get_stored_version() === CMS_BERATUNG_DB_VERSION) {
            return;
        }

        self::install();
    }

    /** Deinstallation: alle Plugin-Tabellen und die Versionsoption entfernen. */
    public static function uninstall(): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }
        $db = \CMS\Database::instance();
        $pdo = $db->getPdo();
        $prefix = self::prefix($db);
        foreach (['beratung_form_submissions', 'beratung_design_presets', 'beratung_settings', 'beratung_landingpages'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . self::quote_identifier(self::validated_table_name($prefix . $table)));
        }
        $stmt = $db->prepare("DELETE FROM {$prefix}settings WHERE option_name = ?");
        $stmt->execute(['beratung_db_version']);
        if (class_exists('CMS_Beratung_Privacy')) {
            CMS_Beratung_Privacy::delete_all_exports();
        }
    }

    public static function stored_version(): string
    {
        return self::get_stored_version();
    }

    private static bool $ensuring = false;

    public static function ensure_for_admin_save(): void
    {
        // seed_defaults() → seed_m365_faqs_if_missing() → save_m365_faq_config() ruft diese Methode erneut auf.
        // Ohne Sperre endet das in einer Endlosrekursion, solange m365_faq_items_json noch fehlt.
        if (self::$ensuring) {
            return;
        }
        self::$ensuring = true;
        try {
            self::create_tables();
            self::seed_defaults();
        } finally {
            self::$ensuring = false;
        }
    }

    private static function create_tables(): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $pdo = $db->getPdo();
        $prefix = self::prefix($db);

        $landingpages = self::quote_identifier(self::validated_table_name($prefix . 'beratung_landingpages'));
        $settings = self::quote_identifier(self::validated_table_name($prefix . 'beratung_settings'));
        $presets = self::quote_identifier(self::validated_table_name($prefix . 'beratung_design_presets'));
        $submissions = self::quote_identifier(self::validated_table_name($prefix . 'beratung_form_submissions'));

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$landingpages} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            internal_title VARCHAR(255) NOT NULL,
            public_title VARCHAR(255) NOT NULL,
            slug VARCHAR(160) NOT NULL,
            meta_title VARCHAR(255) DEFAULT NULL,
            meta_description VARCHAR(500) DEFAULT NULL,
            focus_keyword VARCHAR(160) DEFAULT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            template VARCHAR(64) NOT NULL DEFAULT 'standard',
            max_content_width INT UNSIGNED NOT NULL DEFAULT 1160,
            custom_design_enabled TINYINT(1) NOT NULL DEFAULT 0,
            use_global_settings TINYINT(1) NOT NULL DEFAULT 1,
            use_global_design TINYINT(1) NOT NULL DEFAULT 1,
            show_header TINYINT(1) NOT NULL DEFAULT 1,
            show_footer TINYINT(1) NOT NULL DEFAULT 1,
            show_breadcrumb TINYINT(1) NOT NULL DEFAULT 1,
            show_toc TINYINT(1) NOT NULL DEFAULT 0,
            show_anchor_nav TINYINT(1) NOT NULL DEFAULT 1,
            noindex TINYINT(1) NOT NULL DEFAULT 0,
            nofollow TINYINT(1) NOT NULL DEFAULT 0,
            canonical_url VARCHAR(500) DEFAULT NULL,
            custom_css_class VARCHAR(120) DEFAULT NULL,
            hero_json JSON DEFAULT NULL,
            contact_json JSON DEFAULT NULL,
            seo_json JSON DEFAULT NULL,
            design_json JSON DEFAULT NULL,
            sections_json JSON DEFAULT NULL,
            tracking_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            updated_by BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_slug_tenant (tenant_id, slug),
            KEY idx_slug (slug),
            KEY idx_status (status),
            KEY idx_template (template),
            KEY idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$settings} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            setting_key VARCHAR(120) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_tenant_key (tenant_id, setting_key),
            KEY idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$presets} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            design_json JSON DEFAULT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_preset_slug_tenant (tenant_id, slug),
            KEY idx_system (is_system)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$submissions} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            landingpage_id BIGINT UNSIGNED DEFAULT NULL,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            sender_name VARCHAR(255) DEFAULT NULL,
            sender_email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(80) DEFAULT NULL,
            company VARCHAR(255) DEFAULT NULL,
            topic VARCHAR(255) DEFAULT NULL,
            desired_service VARCHAR(160) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            internal_note TEXT DEFAULT NULL,
            consent TINYINT(1) NOT NULL DEFAULT 0,
            copy_to_sender TINYINT(1) NOT NULL DEFAULT 0,
            payload_json JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'new',
            is_spam TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_landingpage (landingpage_id),
            KEY idx_tenant (tenant_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Spalten-Migrationen erst nach dem Anlegen aller Tabellen ausführen,
        // sonst scheitert die Frischinstallation am ALTER auf form_submissions.
        self::ensure_landingpage_columns($pdo, $prefix);
    }

    private static function seed_defaults(): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $settings = CMS_Beratung_Settings::defaults();
        CMS_Beratung_Settings::save_missing($settings);
        self::seed_presets();
        CMS_Beratung_Storage::instance()->seed_m365_faqs_if_missing();
    }

    private static function ensure_landingpage_columns(\PDO $pdo, string $prefix): void
    {
        $table = $prefix . 'beratung_landingpages';
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, 'hero_json']);
        if (!$stmt->fetch()) {
            $quotedTable = self::quote_identifier(self::validated_table_name($table));
            $pdo->exec("ALTER TABLE {$quotedTable} ADD COLUMN hero_json JSON DEFAULT NULL AFTER custom_css_class");
        }
        foreach ([
            'contact_json' => 'JSON DEFAULT NULL AFTER hero_json',
            'seo_json' => 'JSON DEFAULT NULL AFTER contact_json',
            'use_global_design' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER use_global_settings',
            'tracking_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sections_json',
        ] as $column => $definition) {
            $stmt->execute([$table, $column]);
            if (!$stmt->fetch()) {
                $quotedTable = self::quote_identifier(self::validated_table_name($table));
                $pdo->exec("ALTER TABLE {$quotedTable} ADD COLUMN {$column} {$definition}");
            }
        }

        $submissionTable = $prefix . 'beratung_form_submissions';
        foreach ([
            'desired_service' => 'VARCHAR(160) DEFAULT NULL AFTER topic',
            'internal_note' => 'TEXT DEFAULT NULL AFTER message',
        ] as $column => $definition) {
            $stmt->execute([$submissionTable, $column]);
            if (!$stmt->fetch()) {
                $quotedTable = self::quote_identifier(self::validated_table_name($submissionTable));
                $pdo->exec("ALTER TABLE {$quotedTable} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    private static function seed_presets(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        foreach ([
            'microsoft-365-clean' => ['Microsoft 365 Clean', 'Heller Business Look mit blauer Akzentfarbe, weißen Cards und dezentem Schatten.', ['primary_color' => '#2563eb', 'secondary_color' => '#0f172a', 'accent_color' => '#0ea5e9', 'background_color' => '#f8fafc', 'text_color' => '#111827', 'heading_color' => '#0f172a', 'button_color' => '#2563eb', 'button_text_color' => '#ffffff', 'card_background_color' => '#ffffff', 'card_border_color' => '#dbeafe']],
            'copilot-modern' => ['Copilot Modern', 'Moderner Copilot-Look mit kräftigen Akzenten, Icon-Reiter-Cards und auffälligen CTA-Flächen.', ['primary_color' => '#7c3aed', 'secondary_color' => '#0f172a', 'accent_color' => '#06b6d4', 'background_color' => '#f5f3ff', 'text_color' => '#1e1b4b', 'heading_color' => '#0f172a', 'button_color' => '#7c3aed', 'button_text_color' => '#ffffff', 'card_background_color' => '#ffffff', 'card_border_color' => '#ddd6fe']],
            'security-dark' => ['Security Dark', 'Dunkle Akzente und kontraststarke Cards für Security-, Compliance- und Defender-Themen.', ['primary_color' => '#38bdf8', 'secondary_color' => '#020617', 'accent_color' => '#f97316', 'background_color' => '#0f172a', 'text_color' => '#e5e7eb', 'heading_color' => '#ffffff', 'button_color' => '#38bdf8', 'button_text_color' => '#082f49', 'card_background_color' => '#111827', 'card_border_color' => '#334155']],
            'minimal-consulting' => ['Minimal Consulting', 'Reduziertes Design mit viel Weißraum, wenigen Farben und klaren Handlungsaufforderungen.', ['primary_color' => '#111827', 'secondary_color' => '#475569', 'accent_color' => '#64748b', 'background_color' => '#ffffff', 'text_color' => '#1f2937', 'heading_color' => '#111827', 'button_color' => '#111827', 'button_text_color' => '#ffffff', 'card_background_color' => '#ffffff', 'card_border_color' => '#e5e7eb']],
            'phinit-style' => ['PHINIT Style', 'Sachlicher, technischer und moderner Stil für Microsoft 365 Admin- und Beratungsinhalte.', ['primary_color' => '#1d4ed8', 'secondary_color' => '#111827', 'accent_color' => '#f59e0b', 'background_color' => '#f9fafb', 'text_color' => '#111827', 'heading_color' => '#0f172a', 'button_color' => '#1d4ed8', 'button_text_color' => '#ffffff', 'card_background_color' => '#ffffff', 'card_border_color' => '#d1d5db']],
        ] as $slug => [$name, $description, $design]) {
            $storage->create_preset_if_missing([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'design_json' => json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_system' => 1,
            ]);
        }
    }

    private static function get_stored_version(): string
    {
        if (!class_exists('CMS\\Database')) {
            return '0';
        }

        // OptionStore: Request-Cache der Core-Optionen (keine eigene Abfrage pro Request).
        if (class_exists('CMS\\Services\\OptionStore')) {
            return (string) (\CMS\Services\OptionStore::getInstance()->get('beratung_db_version') ?? '0');
        }

        try {
            $db = \CMS\Database::instance();
            $prefix = self::prefix($db);
            $stmt = $db->prepare("SELECT option_value FROM {$prefix}settings WHERE option_name = ? LIMIT 1");
            $stmt->execute(['beratung_db_version']);
            $value = $stmt->fetchColumn();
            return is_scalar($value) ? (string) $value : '0';
        } catch (\Throwable $e) {
            return '0';
        }
    }

    private static function store_db_version(string $version): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        try {
            $db = \CMS\Database::instance();
            $prefix = self::prefix($db);
            $exists = $db->prepare("SELECT option_value FROM {$prefix}settings WHERE option_name = ? LIMIT 1");
            $exists->execute(['beratung_db_version']);
            if ($exists->fetch()) {
                $db->prepare("UPDATE {$prefix}settings SET option_value = ? WHERE option_name = ?")->execute([$version, 'beratung_db_version']);
                return;
            }
            $db->prepare("INSERT INTO {$prefix}settings (option_name, option_value) VALUES (?, ?)")->execute(['beratung_db_version', $version]);
        } catch (\Throwable $e) {
            error_log('CMS Beratung DB version store failed: ' . $e->getMessage());
        }
    }

    private static function prefix(\CMS\Database $db): string
    {
        return method_exists($db, 'getPrefix') ? $db->getPrefix() : (method_exists($db, 'prefix') ? $db->prefix() : 'cms_');
    }

    private static function quote_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function validated_table_name(string $table): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new \RuntimeException('Invalid table identifier configured.');
        }

        return $table;
    }
}

<?php
/**
 * Schema-Anlage und -Migration für CMS Promos.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Installer
{
    /** Option (Core-Tabelle `settings`) mit der installierten Schema-Version. */
    public const DB_VERSION_OPTION = 'cms_promos_db_version';

    /** @var list<string> */
    private const TABLES = ['promos', 'promo_placements', 'promo_settings'];

    /**
     * Läuft bei jedem `cms_init`, arbeitet aber nur, wenn sich die Schema-Version geändert hat.
     * Die Option ist Teil der Autoload-Optionen ({@see \CMS\Services\OptionStore}) und kostet keine Abfrage.
     */
    public static function maybe_install(): void
    {
        if (self::installed_version() === CMS_PROMOS_DB_VERSION) {
            return;
        }

        self::install();
    }

    public static function install(): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $prefix = $db->getPrefix();

        try {
            $db->query("CREATE TABLE IF NOT EXISTS {$prefix}promo_placements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(180) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                description TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                theme_hook VARCHAR(50) NOT NULL DEFAULT 'manual',
                hook_priority INT NOT NULL DEFAULT 10,
                max_items INT UNSIGNED NOT NULL DEFAULT 3,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_slug (slug),
                KEY idx_hook (status, theme_hook, hook_priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->query("CREATE TABLE IF NOT EXISTS {$prefix}promos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                placement_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(180) NOT NULL,
                title_en VARCHAR(180) NOT NULL DEFAULT '',
                slug VARCHAR(120) NOT NULL,
                teaser VARCHAR(255) NOT NULL DEFAULT '',
                teaser_en VARCHAR(255) NOT NULL DEFAULT '',
                content_html LONGTEXT NULL,
                content_html_en LONGTEXT NULL,
                target_url VARCHAR(500) NOT NULL DEFAULT '',
                button_label VARCHAR(80) NOT NULL DEFAULT '',
                button_label_en VARCHAR(80) NOT NULL DEFAULT '',
                image_url VARCHAR(500) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                start_at DATETIME NULL,
                end_at DATETIME NULL,
                priority INT UNSIGNED NOT NULL DEFAULT 0,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                frequency_cap SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                frequency_window_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
                utm_source VARCHAR(80) NOT NULL DEFAULT '',
                utm_medium VARCHAR(80) NOT NULL DEFAULT '',
                utm_campaign VARCHAR(120) NOT NULL DEFAULT '',
                utm_term VARCHAR(120) NOT NULL DEFAULT '',
                utm_content VARCHAR(120) NOT NULL DEFAULT '',
                impression_count INT UNSIGNED NOT NULL DEFAULT 0,
                click_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_slug (slug),
                KEY idx_placement_status (placement_id, status),
                KEY idx_status_window (status, start_at, end_at),
                KEY idx_priority (priority, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->query("CREATE TABLE IF NOT EXISTS {$prefix}promo_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL,
                setting_value LONGTEXT NULL,
                UNIQUE KEY uniq_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::ensure_schema($db, $prefix);
            CMS_Promos_Repository::instance()->seed_defaults();

            if (function_exists('update_option')) {
                update_option(self::DB_VERSION_OPTION, CMS_PROMOS_DB_VERSION);
            }
        } catch (\Throwable $e) {
            error_log('[cms-promos] Installation/Migration fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Entfernt alle Tabellen und Optionen des Plugins (nur beim Löschen über die Plugin-Verwaltung).
     */
    public static function uninstall(): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $prefix = $db->getPrefix();

        try {
            foreach (self::TABLES as $table) {
                $db->query("DROP TABLE IF EXISTS {$prefix}{$table}");
            }

            $stmt = $db->prepare("DELETE FROM {$prefix}settings WHERE option_name = ?");
            $stmt->execute([self::DB_VERSION_OPTION]);
        } catch (\Throwable $e) {
            error_log('[cms-promos] Deinstallation fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public static function installed_version(): string
    {
        if (class_exists('CMS\\Services\\OptionStore')) {
            return (string) \CMS\Services\OptionStore::getInstance()->get(self::DB_VERSION_OPTION, '');
        }

        return function_exists('get_option') ? (string) get_option(self::DB_VERSION_OPTION, '') : '';
    }

    private static function ensure_schema(\CMS\Database $db, string $prefix): void
    {
        $placements = "{$prefix}promo_placements";
        $promos = "{$prefix}promos";

        self::ensure_column($db, $placements, 'theme_hook', "ALTER TABLE {$placements} ADD COLUMN theme_hook VARCHAR(50) NOT NULL DEFAULT 'manual' AFTER status");
        self::ensure_column($db, $placements, 'hook_priority', "ALTER TABLE {$placements} ADD COLUMN hook_priority INT NOT NULL DEFAULT 10 AFTER theme_hook");

        $columns = [
            'title_en' => "VARCHAR(180) NOT NULL DEFAULT '' AFTER title",
            'teaser_en' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER teaser",
            'content_html_en' => 'LONGTEXT NULL AFTER content_html',
            'button_label_en' => "VARCHAR(80) NOT NULL DEFAULT '' AFTER button_label",
            'frequency_cap' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_featured',
            'frequency_window_hours' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 24 AFTER frequency_cap',
            'utm_source' => "VARCHAR(80) NOT NULL DEFAULT '' AFTER frequency_window_hours",
            'utm_medium' => "VARCHAR(80) NOT NULL DEFAULT '' AFTER utm_source",
            'utm_campaign' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER utm_medium",
            'utm_term' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER utm_campaign",
            'utm_content' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER utm_term",
        ];
        foreach ($columns as $column => $definition) {
            self::ensure_column($db, $promos, $column, "ALTER TABLE {$promos} ADD COLUMN {$column} {$definition}");
        }

        // Indizes für die Ausspiel-Abfragen (Status/Laufzeit, Platzierung, Hook-Flächen).
        self::ensure_index($db, $placements, 'idx_hook', "ALTER TABLE {$placements} ADD KEY idx_hook (status, theme_hook, hook_priority)");
        self::ensure_index($db, $promos, 'idx_placement_status', "ALTER TABLE {$promos} ADD KEY idx_placement_status (placement_id, status)");
        self::ensure_index($db, $promos, 'idx_status_window', "ALTER TABLE {$promos} ADD KEY idx_status_window (status, start_at, end_at)");
        self::ensure_index($db, $promos, 'idx_priority', "ALTER TABLE {$promos} ADD KEY idx_priority (priority, updated_at)");
    }

    private static function ensure_column(\CMS\Database $db, string $table, string $column, string $ddl): void
    {
        // SHOW COLUMNS akzeptiert bei nativen Prepared Statements keine Platzhalter.
        $stmt = $db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        if ($stmt->fetch(\PDO::FETCH_ASSOC) === false) {
            $db->query($ddl);
        }
    }

    private static function ensure_index(\CMS\Database $db, string $table, string $index, string $ddl): void
    {
        $stmt = $db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $stmt->execute([$table, $index]);
        if ($stmt->fetch(\PDO::FETCH_ASSOC) === false) {
            $db->query($ddl);
        }
    }
}

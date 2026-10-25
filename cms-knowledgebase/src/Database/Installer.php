<?php

declare(strict_types=1);

namespace CmsKnowledgebase\Database;

use CMS\Database;
use CmsKnowledgebase\Service\SectionContent;
use CmsKnowledgebase\Support\ContentSections;
use CmsKnowledgebase\Support\Defaults;
use CmsKnowledgebase\Support\LoggerFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    private static ?self $instance = null;

    private $logger;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->logger = LoggerFactory::create();
    }

    public function install(): void
    {
        $db = Database::instance();
        $pdo = $db->getPdo();
        $prefix = $db->prefix();

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}kb_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            keyword VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            excerpt TEXT DEFAULT NULL,
            content LONGTEXT DEFAULT NULL,
            content_details LONGTEXT DEFAULT NULL,
            content_benefits LONGTEXT DEFAULT NULL,
            content_practice LONGTEXT DEFAULT NULL,
            content_notes LONGTEXT DEFAULT NULL,
            content_license LONGTEXT DEFAULT NULL,
            content_conclusion LONGTEXT DEFAULT NULL,
            content_links LONGTEXT DEFAULT NULL,
            tooltip_text TEXT DEFAULT NULL,
            synonyms TEXT DEFAULT NULL,
            category VARCHAR(120) DEFAULT NULL,
            priority INT NOT NULL DEFAULT 100,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
            is_whole_word TINYINT(1) NOT NULL DEFAULT 1,
            max_links_per_page INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_slug (slug),
            KEY idx_keyword (keyword),
            KEY idx_active_priority (is_active, priority),
            KEY idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}kb_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}kb_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_kb_category_name (name),
            UNIQUE KEY uniq_kb_category_slug (slug),
            KEY idx_kb_category_sort (sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureSectionColumns();
        $this->migrateLegacySectionContent();
        $this->seedDefaults();
        $this->disableDefaultNavLink();
        $this->syncCategoriesFromEntries();
        $this->storeVersion();
    }

    public function maybeUpgrade(): void
    {
        $currentVersion = $this->getStoredVersion();
        if ($currentVersion === CMS_KNOWLEDGEBASE_VERSION) {
            return;
        }

        $this->install();
        $this->logger->info('CMS Knowledgebase Schema geprüft/aktualisiert.', [
            'from' => $currentVersion,
            'to' => CMS_KNOWLEDGEBASE_VERSION,
        ]);
    }

    private function ensureSectionColumns(): void
    {
        $db = Database::instance();
        $pdo = $db->getPdo();
        $table = $db->prefix() . 'kb_entries';
        $previousColumn = 'content';

        foreach (ContentSections::additionalColumns() as $column) {
            if (preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
                continue;
            }

            $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            if (empty($stmt->fetchAll())) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} LONGTEXT DEFAULT NULL AFTER {$previousColumn}");
            }

            $previousColumn = $column;
        }
    }

    /**
     * Teilt Legacy-HTML aus `content` auf die strukturierten EditorJS-Bereiche auf.
     */
    private function migrateLegacySectionContent(): void
    {
        $db = Database::instance();
        $table = $db->prefix() . 'kb_entries';
        $additionalColumns = ContentSections::additionalColumns();
        $emptyConditions = implode(' AND ', array_map(
            static fn(string $column): string => "({$column} IS NULL OR {$column} = '')",
            $additionalColumns
        ));

        $stmt = $db->prepare("SELECT id, excerpt, content FROM {$table}
            WHERE content IS NOT NULL AND content <> '' AND content NOT LIKE '{%' AND {$emptyConditions}");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return;
        }

        $sectionContent = SectionContent::instance();
        $columns = ContentSections::columns();
        $assignments = implode(', ', array_map(static fn(string $column): string => "{$column} = :{$column}", $columns));
        $update = $db->prepare("UPDATE {$table} SET {$assignments} WHERE id = :id");
        $migrated = 0;

        foreach ($rows as $row) {
            $parts = $sectionContent->splitLegacyHtml((string) ($row['content'] ?? ''), (string) ($row['excerpt'] ?? ''));
            $params = ['id' => (int) ($row['id'] ?? 0)];
            foreach ($columns as $column) {
                $params[$column] = $sectionContent->sanitizeForStorage((string) ($parts[$column] ?? ''));
            }

            $update->execute($params);
            ++$migrated;
        }

        $this->logger->info('Knowledgebase-Inhalte in strukturierte Bereiche migriert.', ['entries' => $migrated]);
    }

    private function seedDefaults(): void
    {
        $db = Database::instance();
        $table = $db->prefix() . 'kb_settings';
        $stmt = $db->prepare("INSERT INTO {$table} (setting_key, setting_value)
            VALUES (:setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE setting_value = setting_value");

        foreach (Defaults::settings() as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
        }
    }

    private function disableDefaultNavLink(): void
    {
        $db = Database::instance();
        $table = $db->prefix() . 'kb_settings';
        $stmt = $db->prepare("UPDATE {$table} SET setting_value = '0' WHERE setting_key = 'show_nav_link'");
        $stmt->execute();
    }

    private function syncCategoriesFromEntries(): void
    {
        $db = Database::instance();
        $entriesTable = $db->prefix() . 'kb_entries';
        $categoriesTable = $db->prefix() . 'kb_categories';
        $stmt = $db->prepare("SELECT DISTINCT category FROM {$entriesTable} WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return;
        }

        $insert = $db->prepare("INSERT INTO {$categoriesTable} (name, slug, sort_order)
            VALUES (:name, :slug, 0)
            ON DUPLICATE KEY UPDATE name = VALUES(name)");

        foreach ($rows as $row) {
            $name = trim((string) ($row['category'] ?? ''));
            if ($name === '') {
                continue;
            }

            $insert->execute([
                'name' => $name,
                'slug' => $this->slugify($name),
            ]);
        }
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return mb_substr($value, 0, 140, 'UTF-8');
    }

    private function storeVersion(): void
    {
        $db = Database::instance();
        $settingsTable = $db->prefix() . 'settings';
        $stmt = $db->prepare("INSERT INTO {$settingsTable} (option_name, option_value, autoload)
            VALUES (:option_name, :option_value, 0)
            ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
        $stmt->execute([
            'option_name' => 'cms_knowledgebase_version',
            'option_value' => CMS_KNOWLEDGEBASE_VERSION,
        ]);
    }

    private function getStoredVersion(): string
    {
        $db = Database::instance();
        $settingsTable = $db->prefix() . 'settings';
        $stmt = $db->prepare("SELECT option_value FROM {$settingsTable} WHERE option_name = ? LIMIT 1");
        $stmt->execute(['cms_knowledgebase_version']);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : '';
    }
}

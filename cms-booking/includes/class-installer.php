<?php
/**
 * CMS Booking – Installer
 *
 * Erstellt DB-Tabellen, Seeds und Migrationen.
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('CMS_Booking_Installer', false)) {
    return;
}

final class CMS_Booking_Installer
{
    public static function install(): void
    {
        try {
            self::create_tables();
            self::seed_defaults();
            self::store_db_version(CMS_BOOKING_DB_VERSION);
        } catch (\Throwable $e) {
            self::log_install_error($e);
        }
    }

    public static function maybe_install(): void
    {
        try {
            $stored = self::get_stored_version();
            if ($stored === CMS_BOOKING_DB_VERSION) {
                return;
            }
            // Tabellen/Indizes idempotent ergänzen und fehlende Standardwerte nachtragen (INSERT IGNORE).
            self::install();
        } catch (\Throwable $e) {
            self::log_install_error($e);
        }
    }

    public static function uninstall(): void
    {
        if (class_exists('CMS_Booking_Privacy')) {
            CMS_Booking_Privacy::delete_all_exports();
        }
        if (!class_exists('CMS\Database')) {
            return;
        }
        $db  = \CMS\Database::instance();
        $pdo = $db->getPdo();
        $p   = $db->getPrefix();

        $tables = [
            'booking_meta',
            'bookings',
            'booking_availability',
            'booking_services',
            'booking_providers',
            'booking_settings',
        ];
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }

        try {
            $stmt = $db->prepare("DELETE FROM {$p}settings WHERE option_name = ?");
            $stmt->execute(['booking_db_version']);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /* ================================================================== */
    /*  Tabellen                                                           */
    /* ================================================================== */

    private static function create_tables(): void
    {
        if (!class_exists('CMS\Database')) {
            return;
        }

        $db  = \CMS\Database::instance();
        $pdo = $db->getPdo();
        $p   = $db->getPrefix();

        // 1. Anbieter (Provider) – universell: experts, speakers, companies …
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}booking_providers (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED  DEFAULT NULL  COMMENT 'FK → users.id  (NULL = externer Anbieter)',
            source_plugin   VARCHAR(50)   NOT NULL      COMMENT 'z.B. cms-experts, cms-speakers, cms-events',
            source_id       INT UNSIGNED  NOT NULL      COMMENT 'PK aus der Quell-Tabelle',
            display_name    VARCHAR(255)  NOT NULL,
            slug            VARCHAR(150)  NOT NULL,
            email           VARCHAR(255)  DEFAULT NULL,
            phone           VARCHAR(50)   DEFAULT NULL,
            avatar_url      VARCHAR(500)  DEFAULT NULL,
            bio             TEXT          DEFAULT NULL,
            contact_form_id INT UNSIGNED  DEFAULT NULL  COMMENT 'FK → contact_forms.id – optionales Kontakt-Formular',
            timezone        VARCHAR(80)   NOT NULL DEFAULT 'Europe/Berlin',
            currency        VARCHAR(10)   NOT NULL DEFAULT 'EUR',
            status          VARCHAR(20)   NOT NULL DEFAULT 'active',
            settings_json   JSON          DEFAULT NULL,
            created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_source (source_plugin, source_id),
            UNIQUE KEY idx_slug   (slug),
            INDEX idx_user   (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Dienstleistungen / buchbare Leistungen
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}booking_services (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id     INT UNSIGNED  NOT NULL,
            title           VARCHAR(255)  NOT NULL,
            slug            VARCHAR(150)  NOT NULL,
            description     TEXT          DEFAULT NULL,
            duration_min    INT UNSIGNED  NOT NULL DEFAULT 60   COMMENT 'Dauer in Minuten',
            buffer_min      INT UNSIGNED  NOT NULL DEFAULT 15   COMMENT 'Puffer danach',
            max_bookings    INT UNSIGNED  NOT NULL DEFAULT 1    COMMENT 'max. parallele Buchungen',
            price_cents     INT UNSIGNED  NOT NULL DEFAULT 0    COMMENT 'Preis in Cent (0 = kostenlos)',
            location_type   VARCHAR(30)   NOT NULL DEFAULT 'online' COMMENT 'online|onsite|hybrid',
            meeting_url     VARCHAR(500)  DEFAULT NULL          COMMENT 'Zoom / Teams Link',
            booking_type    VARCHAR(30)   NOT NULL DEFAULT 'confirmation' COMMENT 'confirmation|instant|request',
            contact_template VARCHAR(50)  DEFAULT NULL          COMMENT 'Kontakt-Template aus cms-contact',
            status          VARCHAR(20)   NOT NULL DEFAULT 'active',
            sort_order      INT UNSIGNED  NOT NULL DEFAULT 0,
            settings_json   JSON          DEFAULT NULL,
            created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_provider (provider_id),
            INDEX idx_status   (status),
            UNIQUE KEY idx_provider_slug (provider_id, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Verfügbarkeiten (Wochenplan + Ausnahmen)
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}booking_availability (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id     INT UNSIGNED  NOT NULL,
            day_of_week     TINYINT UNSIGNED DEFAULT NULL COMMENT '0=Mo … 6=So, NULL=spezifisches Datum',
            specific_date   DATE          DEFAULT NULL   COMMENT 'Für Einzeltage / Urlaub',
            start_time      TIME          NOT NULL,
            end_time        TIME          NOT NULL,
            is_blocked      TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = nicht buchbar (Urlaub)',
            created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider (provider_id),
            INDEX idx_day      (day_of_week),
            INDEX idx_date     (specific_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Buchungen
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}bookings (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id     INT UNSIGNED  NOT NULL,
            service_id      INT UNSIGNED  DEFAULT NULL,
            user_id         INT UNSIGNED  DEFAULT NULL  COMMENT 'FK buchender Nutzer',
            contact_submission_id INT UNSIGNED DEFAULT NULL COMMENT 'FK → contact_submissions.id',
            customer_name   VARCHAR(255)  NOT NULL,
            customer_email  VARCHAR(255)  NOT NULL,
            customer_phone  VARCHAR(50)   DEFAULT NULL,
            booking_date    DATE          NOT NULL,
            start_time      TIME          NOT NULL,
            end_time        TIME          NOT NULL,
            duration_min    INT UNSIGNED  NOT NULL DEFAULT 60,
            location_type   VARCHAR(30)   NOT NULL DEFAULT 'online',
            meeting_url     VARCHAR(500)  DEFAULT NULL,
            price_cents     INT UNSIGNED  NOT NULL DEFAULT 0,
            currency        VARCHAR(10)   NOT NULL DEFAULT 'EUR',
            status          VARCHAR(30)   NOT NULL DEFAULT 'pending'
                            COMMENT 'pending|confirmed|cancelled|completed|no_show',
            notes           TEXT          DEFAULT NULL,
            internal_notes  TEXT          DEFAULT NULL,
            ical_uid        VARCHAR(255)  DEFAULT NULL,
            reminder_sent   TINYINT(1)    NOT NULL DEFAULT 0,
            cancelled_at    TIMESTAMP     NULL DEFAULT NULL,
            confirmed_at    TIMESTAMP     NULL DEFAULT NULL,
            completed_at    TIMESTAMP     NULL DEFAULT NULL,
            created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_provider   (provider_id),
            INDEX idx_service    (service_id),
            INDEX idx_user       (user_id),
            INDEX idx_date       (booking_date),
            INDEX idx_status     (status),
            INDEX idx_email      (customer_email),
            INDEX idx_contact_sub (contact_submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Booking-Meta (flexibel)
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}booking_meta (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id      INT UNSIGNED  NOT NULL,
            meta_key        VARCHAR(100)  NOT NULL,
            meta_value      TEXT          DEFAULT NULL,
            UNIQUE KEY idx_bm_unique (booking_id, meta_key),
            INDEX idx_booking (booking_id),
            INDEX idx_key     (meta_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Plugin-Einstellungen
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}booking_settings (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key     VARCHAR(100)  NOT NULL,
            setting_value   TEXT          DEFAULT NULL,
            UNIQUE KEY idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::add_foreign_keys($pdo, $p);
        self::ensure_index($pdo, "{$p}bookings", 'idx_reminder', "ALTER TABLE {$p}bookings ADD INDEX idx_reminder (status, reminder_sent, booking_date)");
        self::ensure_index($pdo, "{$p}bookings", 'idx_provider_date', "ALTER TABLE {$p}bookings ADD INDEX idx_provider_date (provider_id, booking_date, status)");
        self::ensure_index($pdo, "{$p}booking_availability", 'idx_provider_date', "ALTER TABLE {$p}booking_availability ADD INDEX idx_provider_date (provider_id, specific_date)");
    }

    private static function ensure_index(\PDO $pdo, string $table, string $index, string $ddl): void
    {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $stmt->execute([$table, $index]);
        if ($stmt->fetchColumn() === false) {
            try {
                $pdo->exec($ddl);
            } catch (\Throwable $e) {
                error_log('[cms-booking] index skipped (' . $index . '): ' . $e->getMessage());
            }
        }
    }

    private static function add_foreign_keys(\PDO $pdo, string $p): void
    {
        $foreignKeys = [
            [
                'table'      => 'booking_services',
                'column'     => 'provider_id',
                'ref_table'  => 'booking_providers',
                'ref_column' => 'id',
                'name'       => 'fk_booking_services_provider',
                'on_delete'  => 'CASCADE',
            ],
            [
                'table'      => 'booking_availability',
                'column'     => 'provider_id',
                'ref_table'  => 'booking_providers',
                'ref_column' => 'id',
                'name'       => 'fk_booking_availability_provider',
                'on_delete'  => 'CASCADE',
            ],
            [
                'table'      => 'bookings',
                'column'     => 'provider_id',
                'ref_table'  => 'booking_providers',
                'ref_column' => 'id',
                'name'       => 'fk_bookings_provider',
                'on_delete'  => 'CASCADE',
            ],
            [
                'table'      => 'bookings',
                'column'     => 'service_id',
                'ref_table'  => 'booking_services',
                'ref_column' => 'id',
                'name'       => 'fk_bookings_service',
                'on_delete'  => 'SET NULL',
            ],
            [
                'table'      => 'booking_meta',
                'column'     => 'booking_id',
                'ref_table'  => 'bookings',
                'ref_column' => 'id',
                'name'       => 'fk_booking_meta_booking',
                'on_delete'  => 'CASCADE',
            ],
        ];

        foreach ($foreignKeys as $foreignKey) {
            $table     = $p . (string) $foreignKey['table'];
            $column    = (string) $foreignKey['column'];
            $refTable  = $p . (string) $foreignKey['ref_table'];
            $refColumn = (string) $foreignKey['ref_column'];
            $onDelete  = (string) $foreignKey['on_delete'];

            if (
                !self::table_exists($pdo, $table)
                || !self::table_exists($pdo, $refTable)
                || !self::column_exists($pdo, $table, $column)
                || !self::column_exists($pdo, $refTable, $refColumn)
                || self::foreign_key_relation_exists($pdo, $table, $column, $refTable, $refColumn)
            ) {
                continue;
            }

            $constraint = self::foreign_key_name($p, (string) $foreignKey['name']);
            $sql = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s',
                self::quote_identifier($table),
                self::quote_identifier($constraint),
                self::quote_identifier($column),
                self::quote_identifier($refTable),
                self::quote_identifier($refColumn),
                $onDelete
            );

            try {
                $pdo->exec($sql);
            } catch (\Throwable $e) {
                error_log('[cms-booking] foreign key skipped (' . $constraint . '): ' . $e->getMessage());
            }
        }
    }

    private static function table_exists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return $stmt->fetchColumn() !== false;
    }

    private static function column_exists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return $stmt->fetchColumn() !== false;
    }

    private static function foreign_key_relation_exists(
        \PDO $pdo,
        string $table,
        string $column,
        string $refTable,
        string $refColumn
    ): bool {
        $stmt = $pdo->prepare(
            'SELECT CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?
               AND REFERENCED_COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $column, $refTable, $refColumn]);

        return $stmt->fetchColumn() !== false;
    }

    private static function foreign_key_name(string $prefix, string $baseName): string
    {
        $normalizedPrefix = trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $prefix), '_');
        $normalizedBase   = trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', preg_replace('/^fk_/', '', $baseName)), '_');
        $name             = 'fk_' . ($normalizedPrefix !== '' ? $normalizedPrefix . '_' : '') . $normalizedBase;

        if (strlen($name) <= 64) {
            return $name;
        }

        return substr($name, 0, 53) . '_' . substr(hash('sha256', $name), 0, 10);
    }

    private static function quote_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /* ================================================================== */
    /*  Seeds                                                              */
    /* ================================================================== */

    /**
     * Standardwerte aller Einstellungen.
     *
     * @return array<string,string>
     */
    public static function default_settings(): array
    {
        return [
            'admin_email'              => '',
            'from_name'                => defined('SITE_NAME') ? (string) SITE_NAME : '365CMS',
            'from_email'               => '',
            'default_duration'         => '60',
            'default_buffer'           => '15',
            'default_timezone'         => 'Europe/Berlin',
            'default_currency'         => 'EUR',
            'booking_advance_min'      => '1',
            'booking_advance_max'      => '90',
            'booking_min_notice_hours' => '0',
            'booking_daily_limit'      => '0',
            'cancellation_hours'       => '24',
            'auto_confirm'             => '0',
            'send_reminders'           => '1',
            'reminder_hours'           => '24',
            'primary_color'            => '',
            'retention_days'           => '730',
        ];
    }

    private static function seed_defaults(): void
    {
        $db = \CMS\Database::instance();
        $stmt = $db->prepare("INSERT IGNORE INTO {$db->getPrefix()}booking_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach (self::default_settings() as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }

    /**
     * Alle Einstellungen (einmal je Request gelesen, mit Standardwerten ergänzt).
     *
     * @return array<string,string>
     */
    public static function read_settings(bool $refresh = false): array
    {
        static $cache = null;
        if ($cache !== null && !$refresh) {
            return $cache;
        }

        $settings = self::default_settings();
        try {
            $db = \CMS\Database::instance();
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM {$db->getPrefix()}booking_settings");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [] as $key => $value) {
                $settings[(string) $key] = (string) $value;
            }
        } catch (\Throwable $e) {
            error_log('[cms-booking] settings not readable: ' . $e->getMessage());
        }

        return $cache = $settings;
    }

    /**
     * @param array<string,string> $values
     */
    public static function write_settings(array $values): void
    {
        $db = \CMS\Database::instance();
        $stmt = $db->prepare(
            "INSERT INTO {$db->getPrefix()}booking_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($values as $key => $value) {
            $stmt->execute([(string) $key, (string) $value]);
        }
        self::read_settings(true);
    }

    public static function stored_version(): string
    {
        return self::get_stored_version();
    }

    /* ================================================================== */
    /*  Versions-Helfer                                                    */
    /* ================================================================== */

    private static function get_stored_version(): string
    {
        // OptionStore: Request-Cache der Core-Optionen (keine eigene Abfrage pro Request).
        try {
            if (class_exists('CMS\\Services\\OptionStore')) {
                return (string) (\CMS\Services\OptionStore::getInstance()->get('booking_db_version') ?? '0');
            }
            $db   = \CMS\Database::instance();
            $stmt = $db->prepare("SELECT option_value FROM {$db->getPrefix()}settings WHERE option_name = ?");
            $stmt->execute(['booking_db_version']);
            return (string) ($stmt->fetchColumn() ?: '0');
        } catch (\Throwable $e) {
            return '0';
        }
    }

    private static function store_db_version(string $version): void
    {
        try {
            $db = \CMS\Database::instance();
            $p  = $db->getPrefix();
            $db->prepare(
                "INSERT INTO {$p}settings (option_name, option_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)"
            )->execute(['booking_db_version', $version]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function log_install_error(\Throwable $e): void
    {
        error_log('[cms-booking] installer skipped: ' . $e->getMessage());
    }
}

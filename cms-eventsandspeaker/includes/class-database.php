<?php
/**
 * Datenbank- und Seed-Manager für 365NET Events & Speaker.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Database
{
    private static ?self $instance = null;
    private const SCHEMA_VERSION = '3.0.0';
    private const MAX_LIST_LIMIT = 300;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, array<int, string>> */
    private array $tableColumnsCache = [];

    /** @var array<string, string>|null */
    private ?array $dotEnvCache = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    /**
     * Legt/aktualisiert Tabellen und Seed-Daten idempotent.
     */
    public function ensureSchema(bool $forceSeed = false): void
    {
        $this->createTables();
        $this->seedDefaults($forceSeed);
        $this->purgeNonPersonSpeakers();
        $this->autoLinkExistingRecords();
        $this->saveSetting('schema_version', self::SCHEMA_VERSION);
    }

    /**
     * Prüft mit einer Abfrage, ob Tabellen und Seeds zum Code-Stand passen. ensureSchema() legt Tabellen an,
     * seedet und verknüpft alle Events/Speaker per UPDATE; das darf nicht bei jedem Seitenaufruf laufen.
     */
    public function isSchemaCurrent(): bool
    {
        try {
            $db = CMS\Database::instance();
            $stmt = $db->prepare("SELECT setting_value FROM {$db->prefix()}365net_event_settings WHERE setting_key = 'schema_version' LIMIT 1");
            $stmt->execute([]);
            $row = $stmt->fetch();

            return $row !== false && $row !== null && (string) ($row->setting_value ?? '') === self::SCHEMA_VERSION;
        } catch (Throwable) {
            return false;
        }
    }

    private function createTables(): void
    {
        $db = CMS\Database::instance();
        $pdo = $db->getPdo();
        $p = $db->prefix();

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}365net_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            unique_id VARCHAR(80) NOT NULL,
            source_nr INT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            date_label VARCHAR(80) DEFAULT NULL,
            end_date_label VARCHAR(80) DEFAULT NULL,
            location VARCHAR(255) DEFAULT NULL,
            organizer VARCHAR(255) DEFAULT NULL,
            source_column VARCHAR(255) DEFAULT NULL,
            category VARCHAR(255) DEFAULT NULL,
            event_type VARCHAR(120) DEFAULT NULL,
            price VARCHAR(120) DEFAULT NULL,
            website VARCHAR(600) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            description_json LONGTEXT DEFAULT NULL,
            excerpt TEXT DEFAULT NULL,
            image_url VARCHAR(600) DEFAULT NULL,
            image_alt VARCHAR(255) DEFAULT NULL,
            image_bg_color VARCHAR(7) DEFAULT NULL,
            gallery_json LONGTEXT DEFAULT NULL,
            categories VARCHAR(500) DEFAULT NULL,
            tags VARCHAR(700) DEFAULT NULL,
            target_audience VARCHAR(255) DEFAULT NULL,
            event_format VARCHAR(80) DEFAULT NULL,
            attendance_mode VARCHAR(80) DEFAULT NULL,
            difficulty_level VARCHAR(80) DEFAULT NULL,
            language VARCHAR(80) DEFAULT NULL,
            timezone VARCHAR(80) DEFAULT NULL,
            start_time VARCHAR(20) DEFAULT NULL,
            end_time VARCHAR(20) DEFAULT NULL,
            venue_name VARCHAR(255) DEFAULT NULL,
            street VARCHAR(255) DEFAULT NULL,
            postal_code VARCHAR(30) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            country VARCHAR(120) DEFAULT NULL,
            online_url VARCHAR(600) DEFAULT NULL,
            registration_url VARCHAR(600) DEFAULT NULL,
            ticket_url VARCHAR(600) DEFAULT NULL,
            price_class VARCHAR(80) DEFAULT NULL,
            price_min DECIMAL(10,2) DEFAULT NULL,
            price_max DECIMAL(10,2) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL,
            early_bird_until DATE DEFAULT NULL,
            capacity INT UNSIGNED DEFAULT NULL,
            contact_name VARCHAR(180) DEFAULT NULL,
            contact_email VARCHAR(180) DEFAULT NULL,
            contact_phone VARCHAR(80) DEFAULT NULL,
            linked_company_id INT UNSIGNED DEFAULT NULL,
            linked_expert_id INT UNSIGNED DEFAULT NULL,
            sponsors TEXT DEFAULT NULL,
            accessibility TEXT DEFAULT NULL,
            seo_title VARCHAR(255) DEFAULT NULL,
            seo_description VARCHAR(320) DEFAULT NULL,
            og_image_url VARCHAR(600) DEFAULT NULL,
            featured TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_id (unique_id),
            UNIQUE KEY slug (slug),
            INDEX idx_status_start (status, start_date),
            INDEX idx_source_nr (source_nr),
            INDEX idx_linked_company (linked_company_id),
            INDEX idx_linked_expert (linked_expert_id),
            INDEX idx_location (location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}365net_event_speakers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            unique_id VARCHAR(100) NOT NULL,
            first_name VARCHAR(120) DEFAULT NULL,
            last_name VARCHAR(120) DEFAULT NULL,
            display_name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            company VARCHAR(255) DEFAULT NULL,
            topic VARCHAR(500) DEFAULT NULL,
            award VARCHAR(255) DEFAULT NULL,
            website VARCHAR(600) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            bio_json LONGTEXT DEFAULT NULL,
            avatar_url VARCHAR(600) DEFAULT NULL,
            avatar_alt VARCHAR(255) DEFAULT NULL,
            theme_image_url VARCHAR(600) DEFAULT NULL,
            theme_image_alt VARCHAR(255) DEFAULT NULL,
            categories VARCHAR(500) DEFAULT NULL,
            tags VARCHAR(700) DEFAULT NULL,
            specializations VARCHAR(700) DEFAULT NULL,
            languages VARCHAR(255) DEFAULT NULL,
            speaker_type VARCHAR(80) DEFAULT NULL,
            price_class VARCHAR(80) DEFAULT NULL,
            fee_min DECIMAL(10,2) DEFAULT NULL,
            fee_max DECIMAL(10,2) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL,
            speaking_formats VARCHAR(255) DEFAULT NULL,
            availability VARCHAR(255) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(80) DEFAULT NULL,
            location VARCHAR(180) DEFAULT NULL,
            linked_expert_id INT UNSIGNED DEFAULT NULL,
            linked_company_id INT UNSIGNED DEFAULT NULL,
            linkedin_url VARCHAR(600) DEFAULT NULL,
            x_url VARCHAR(600) DEFAULT NULL,
            youtube_url VARCHAR(600) DEFAULT NULL,
            github_url VARCHAR(600) DEFAULT NULL,
            seo_title VARCHAR(255) DEFAULT NULL,
            seo_description VARCHAR(320) DEFAULT NULL,
            og_image_url VARCHAR(600) DEFAULT NULL,
            featured TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_id (unique_id),
            UNIQUE KEY slug (slug),
            INDEX idx_status_name (status, last_name, first_name),
            INDEX idx_linked_expert (linked_expert_id),
            INDEX idx_linked_company (linked_company_id),
            INDEX idx_company (company)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}365net_event_speaker_rel (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            speaker_id INT UNSIGNED NOT NULL,
            speaker_nr INT UNSIGNED DEFAULT NULL,
            topic VARCHAR(500) DEFAULT NULL,
            award VARCHAR(255) DEFAULT NULL,
            website VARCHAR(600) DEFAULT NULL,
            row_payload LONGTEXT DEFAULT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_relation (event_id, speaker_id, speaker_nr),
            INDEX idx_event (event_id),
            INDEX idx_speaker (speaker_id),
            CONSTRAINT fk_365net_rel_event FOREIGN KEY (event_id) REFERENCES {$p}365net_events(id) ON DELETE CASCADE,
            CONSTRAINT fk_365net_rel_speaker FOREIGN KEY (speaker_id) REFERENCES {$p}365net_event_speakers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}365net_event_settings (
            setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
            setting_value LONGTEXT DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->migrateMetaColumns();
    }

    private function migrateMetaColumns(): void
    {
        $eventColumns = $this->eventMetaColumnDefinitions();

        foreach ($eventColumns as $column => $definition) {
            try {
                $this->ensureColumn('365net_events', $column, $definition);
            } catch (Throwable $e) {
                $this->logDatabaseWarning('migrate_event_column_' . $column, $e);
            }
        }

        $speakerColumns = $this->speakerMetaColumnDefinitions();

        foreach ($speakerColumns as $column => $definition) {
            try {
                $this->ensureColumn('365net_event_speakers', $column, $definition);
            } catch (Throwable $e) {
                $this->logDatabaseWarning('migrate_speaker_column_' . $column, $e);
            }
        }
    }

    /** @return array<string, string> */
    private function eventMetaColumnDefinitions(): array
    {
        return [
            'description_json' => 'description_json LONGTEXT DEFAULT NULL',
            'excerpt' => 'excerpt TEXT DEFAULT NULL',
            'image_url' => 'image_url VARCHAR(600) DEFAULT NULL',
            'image_alt' => 'image_alt VARCHAR(255) DEFAULT NULL',
            'image_bg_color' => 'image_bg_color VARCHAR(7) DEFAULT NULL',
            'gallery_json' => 'gallery_json LONGTEXT DEFAULT NULL',
            'categories' => 'categories VARCHAR(500) DEFAULT NULL',
            'tags' => 'tags VARCHAR(700) DEFAULT NULL',
            'target_audience' => 'target_audience VARCHAR(255) DEFAULT NULL',
            'event_format' => 'event_format VARCHAR(80) DEFAULT NULL',
            'attendance_mode' => 'attendance_mode VARCHAR(80) DEFAULT NULL',
            'difficulty_level' => 'difficulty_level VARCHAR(80) DEFAULT NULL',
            'language' => 'language VARCHAR(80) DEFAULT NULL',
            'timezone' => 'timezone VARCHAR(80) DEFAULT NULL',
            'start_time' => 'start_time VARCHAR(20) DEFAULT NULL',
            'end_time' => 'end_time VARCHAR(20) DEFAULT NULL',
            'venue_name' => 'venue_name VARCHAR(255) DEFAULT NULL',
            'street' => 'street VARCHAR(255) DEFAULT NULL',
            'postal_code' => 'postal_code VARCHAR(30) DEFAULT NULL',
            'city' => 'city VARCHAR(120) DEFAULT NULL',
            'country' => 'country VARCHAR(120) DEFAULT NULL',
            'online_url' => 'online_url VARCHAR(600) DEFAULT NULL',
            'registration_url' => 'registration_url VARCHAR(600) DEFAULT NULL',
            'ticket_url' => 'ticket_url VARCHAR(600) DEFAULT NULL',
            'price_class' => 'price_class VARCHAR(80) DEFAULT NULL',
            'price_min' => 'price_min DECIMAL(10,2) DEFAULT NULL',
            'price_max' => 'price_max DECIMAL(10,2) DEFAULT NULL',
            'currency' => 'currency VARCHAR(10) DEFAULT NULL',
            'early_bird_until' => 'early_bird_until DATE DEFAULT NULL',
            'capacity' => 'capacity INT UNSIGNED DEFAULT NULL',
            'contact_name' => 'contact_name VARCHAR(180) DEFAULT NULL',
            'contact_email' => 'contact_email VARCHAR(180) DEFAULT NULL',
            'contact_phone' => 'contact_phone VARCHAR(80) DEFAULT NULL',
            'linked_company_id' => 'linked_company_id INT UNSIGNED DEFAULT NULL',
            'linked_expert_id' => 'linked_expert_id INT UNSIGNED DEFAULT NULL',
            'sponsors' => 'sponsors TEXT DEFAULT NULL',
            'accessibility' => 'accessibility TEXT DEFAULT NULL',
            'seo_title' => 'seo_title VARCHAR(255) DEFAULT NULL',
            'seo_description' => 'seo_description VARCHAR(320) DEFAULT NULL',
            'og_image_url' => 'og_image_url VARCHAR(600) DEFAULT NULL',
            'featured' => 'featured TINYINT(1) NOT NULL DEFAULT 0',
        ];
    }

    /** @return array<string, string> */
    private function speakerMetaColumnDefinitions(): array
    {
        return [
            'bio_json' => 'bio_json LONGTEXT DEFAULT NULL',
            'avatar_url' => 'avatar_url VARCHAR(600) DEFAULT NULL',
            'avatar_alt' => 'avatar_alt VARCHAR(255) DEFAULT NULL',
            'theme_image_url' => 'theme_image_url VARCHAR(600) DEFAULT NULL',
            'theme_image_alt' => 'theme_image_alt VARCHAR(255) DEFAULT NULL',
            'categories' => 'categories VARCHAR(500) DEFAULT NULL',
            'tags' => 'tags VARCHAR(700) DEFAULT NULL',
            'specializations' => 'specializations VARCHAR(700) DEFAULT NULL',
            'languages' => 'languages VARCHAR(255) DEFAULT NULL',
            'speaker_type' => 'speaker_type VARCHAR(80) DEFAULT NULL',
            'price_class' => 'price_class VARCHAR(80) DEFAULT NULL',
            'fee_min' => 'fee_min DECIMAL(10,2) DEFAULT NULL',
            'fee_max' => 'fee_max DECIMAL(10,2) DEFAULT NULL',
            'currency' => 'currency VARCHAR(10) DEFAULT NULL',
            'speaking_formats' => 'speaking_formats VARCHAR(255) DEFAULT NULL',
            'availability' => 'availability VARCHAR(255) DEFAULT NULL',
            'email' => 'email VARCHAR(180) DEFAULT NULL',
            'phone' => 'phone VARCHAR(80) DEFAULT NULL',
            'location' => 'location VARCHAR(180) DEFAULT NULL',
            'linked_expert_id' => 'linked_expert_id INT UNSIGNED DEFAULT NULL',
            'linked_company_id' => 'linked_company_id INT UNSIGNED DEFAULT NULL',
            'linkedin_url' => 'linkedin_url VARCHAR(600) DEFAULT NULL',
            'x_url' => 'x_url VARCHAR(600) DEFAULT NULL',
            'youtube_url' => 'youtube_url VARCHAR(600) DEFAULT NULL',
            'github_url' => 'github_url VARCHAR(600) DEFAULT NULL',
            'seo_title' => 'seo_title VARCHAR(255) DEFAULT NULL',
            'seo_description' => 'seo_description VARCHAR(320) DEFAULT NULL',
            'og_image_url' => 'og_image_url VARCHAR(600) DEFAULT NULL',
            'featured' => 'featured TINYINT(1) NOT NULL DEFAULT 0',
        ];
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $db = CMS\Database::instance();
        $fullTable = $db->prefix() . $table;
        $columns = $this->getExistingColumns($table);
        if ($columns !== [] && in_array($column, $columns, true)) {
            return;
        }

        $db->getPdo()->exec("ALTER TABLE `{$fullTable}` ADD COLUMN {$definition}");
        unset($this->tableColumnsCache[$table]);
    }

    /**
     * Seedet die festen Plugin-Defaultdaten. Keine Upload-/Import-Funktion.
     */
    private function seedDefaults(bool $force = false): void
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM {$p}365net_events");
        $countStmt->execute([]);
        if (!$force && (int) $countStmt->fetchColumn() > 0) {
            return;
        }

        $seedFile = CMS_365NET_EVENTS_PLUGIN_DIR . 'defaults/seed-data.php';
        if (!is_file($seedFile)) {
            return;
        }

        /** @var array<int, array<string, string>> $rows */
        $rows = require $seedFile;
        if ($rows === []) {
            return;
        }

        $eventStmt = $db->prepare("INSERT INTO {$p}365net_events
            (unique_id, source_nr, title, slug, start_date, end_date, date_label, end_date_label, location, organizer, source_column, category, event_type, price, website, description, excerpt, seo_description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title), start_date = VALUES(start_date), end_date = VALUES(end_date),
                date_label = VALUES(date_label), end_date_label = VALUES(end_date_label), location = VALUES(location),
                organizer = VALUES(organizer), source_column = VALUES(source_column), category = VALUES(category),
                event_type = VALUES(event_type), price = VALUES(price), website = VALUES(website), description = VALUES(description),
                excerpt = VALUES(excerpt), seo_description = VALUES(seo_description)");

        $eventUpdateStmt = $db->prepare("UPDATE {$p}365net_events
            SET source_nr = ?, title = ?, start_date = ?, end_date = ?, date_label = ?, end_date_label = ?,
                location = ?, organizer = ?, source_column = ?, category = ?, event_type = ?, price = ?, website = ?,
                description = ?, excerpt = ?, seo_description = ?, status = ?
            WHERE id = ?");

        $speakerStmt = $db->prepare("INSERT INTO {$p}365net_event_speakers
            (unique_id, first_name, last_name, display_name, slug, company, topic, award, website, bio, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name), last_name = VALUES(last_name), display_name = VALUES(display_name),
                company = VALUES(company), topic = VALUES(topic), award = VALUES(award), website = VALUES(website), bio = VALUES(bio)");

        $relStmt = $db->prepare("INSERT IGNORE INTO {$p}365net_event_speaker_rel
            (event_id, speaker_id, speaker_nr, topic, award, website, row_payload, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $eventsByUnique = [];
        $speakersByUnique = [];
        $order = 0;

        foreach ($rows as $row) {
            $order++;
            $sourceNr = (int) ($row['nr'] ?? 0);
            $title = $this->cleanText((string) ($row['event_name'] ?? ''), 255);
            if ($sourceNr <= 0 || $title === '') {
                continue;
            }

            $eventUnique = 'event-' . $sourceNr;
            $eventSlug = $this->uniqueSlug($title . '-' . $sourceNr);
            $start = $this->parseGermanDate((string) ($row['wann'] ?? ''));
            $end = $this->parseGermanDate((string) ($row['bis_wann'] ?? ''));
            $dateLabel = $this->cleanText((string) ($row['wann'] ?? ''), 80);
            $endDateLabel = $this->cleanText((string) ($row['bis_wann'] ?? ''), 80);
            $location = $this->cleanText((string) ($row['ort'] ?? ''), 255);
            $organizer = $this->cleanText((string) ($row['veranstalter'] ?? ''), 255);
            $sourceColumn = $this->cleanText((string) ($row['spalte1'] ?? ''), 255);
            $category = $this->cleanText((string) ($row['thema_kategorie'] ?? ''), 255);
            $eventType = $this->cleanText((string) ($row['event_art'] ?? ''), 120);
            $price = $this->cleanText((string) ($row['preis'] ?? ''), 120);
            $website = $this->cleanUrl((string) ($row['website'] ?? ''));
            $description = $this->cleanTextarea((string) ($row['description'] ?? ''), 10000);
            if ($description === '') {
                $description = $category;
            }
            $excerpt = $this->seedExcerpt($description);
            $seoDescription = $this->seedSeoDescription($description);

            if (!isset($eventsByUnique[$eventUnique])) {
                $existingEventId = $this->findExistingSeedEventId($sourceNr, $eventUnique, $title, $start, $location);
                if ($existingEventId > 0) {
                    $eventUpdateStmt->execute([
                        $sourceNr,
                        $title,
                        $start,
                        $end,
                        $dateLabel,
                        $endDateLabel,
                        $location,
                        $organizer,
                        $sourceColumn,
                        $category,
                        $eventType,
                        $price,
                        $website,
                        $description !== '' ? $description : null,
                        $excerpt,
                        $seoDescription,
                        'published',
                        $existingEventId,
                    ]);
                    $eventsByUnique[$eventUnique] = $existingEventId;
                } else {
                    $eventStmt->execute([
                        $eventUnique,
                        $sourceNr,
                        $title,
                        $eventSlug,
                        $start,
                        $end,
                        $dateLabel,
                        $endDateLabel,
                        $location,
                        $organizer,
                        $sourceColumn,
                        $category,
                        $eventType,
                        $price,
                        $website,
                        $description !== '' ? $description : null,
                        $excerpt,
                        $seoDescription,
                        'published',
                    ]);

                    $eventsByUnique[$eventUnique] = $this->findIdByUnique('365net_events', $eventUnique);
                }
            }

            $eventId = (int) ($eventsByUnique[$eventUnique] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $firstName = $this->cleanText((string) ($row['vorname'] ?? ''), 120);
            $lastName = $this->cleanText((string) ($row['nachname'] ?? ''), 120);
            $company = $this->cleanText((string) ($row['firma'] ?? ''), 255);
            $topic = $this->cleanText((string) ($row['thema_kategorie'] ?? ''), 500);
            $award = $this->cleanText((string) ($row['mvp_auszeichnung'] ?? ''), 255);
            $speakerNr = (int) ($row['speaker_nr'] ?? 0);
            if (!$this->isRealPersonSpeaker($firstName, $lastName)) {
                continue;
            }
            $displayName = trim($firstName . ' ' . $lastName);

            $speakerUnique = $this->speakerUniqueId($firstName, $lastName, $company, $topic, $sourceNr, $speakerNr);
            if (!isset($speakersByUnique[$speakerUnique])) {
                $speakerStmt->execute([
                    $speakerUnique,
                    $firstName !== '' ? $firstName : null,
                    $lastName !== '' ? $lastName : null,
                    $displayName,
                    $this->uniqueSlug($displayName . '-' . substr(hash('sha256', $speakerUnique), 0, 8)),
                    null,
                    $topic !== '' ? $topic : null,
                    $award !== '' ? $award : null,
                    $this->cleanUrl((string) ($row['website'] ?? '')),
                    $this->seedBio($topic, $award, $company),
                    'published',
                ]);

                $speakersByUnique[$speakerUnique] = $this->findIdByUnique('365net_event_speakers', $speakerUnique);
            }

            $speakerId = (int) ($speakersByUnique[$speakerUnique] ?? 0);
            if ($speakerId <= 0) {
                continue;
            }

            $relStmt->execute([
                $eventId,
                $speakerId,
                $speakerNr > 0 ? $speakerNr : null,
                $topic !== '' ? $topic : null,
                $award !== '' ? $award : null,
                $this->cleanUrl((string) ($row['website'] ?? '')),
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $order,
            ]);
        }
    }

    private function speakerUniqueId(string $firstName, string $lastName, string $company, string $topic, int $sourceNr, int $speakerNr): string
    {
        $base = trim($this->lower($firstName . '|' . $lastName . '|' . $company));
        if ($base === '||' || $base === '') {
            $base = 'event|' . $sourceNr . '|' . $speakerNr . '|' . $topic;
        }

        return 'speaker-' . substr(hash('sha256', $base), 0, 24);
    }

    private function findIdByUnique(string $table, string $uniqueId): int
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare('SELECT id FROM ' . $db->prefix() . $table . ' WHERE unique_id = ? LIMIT 1');
        $stmt->execute([$uniqueId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function findExistingSeedEventId(int $sourceNr, string $eventUnique, string $title, ?string $startDate, string $location): int
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();

        if ($sourceNr > 0) {
            $stmt = $db->prepare("SELECT id FROM {$p}365net_events WHERE source_nr = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$sourceNr]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        if ($eventUnique !== '') {
            $stmt = $db->prepare("SELECT id FROM {$p}365net_events WHERE unique_id = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$eventUnique]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        $titleNeedle = $this->normalizeMatchValue($title);
        if ($titleNeedle === '') {
            return 0;
        }

        if ($startDate !== null && $startDate !== '') {
            $stmt = $db->prepare("SELECT id FROM {$p}365net_events WHERE LOWER(TRIM(title)) = ? AND start_date = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$titleNeedle, $startDate]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        $locationNeedle = $this->normalizeMatchValue($location);
        if ($locationNeedle !== '') {
            $stmt = $db->prepare("SELECT id FROM {$p}365net_events WHERE LOWER(TRIM(title)) = ? AND LOWER(TRIM(location)) = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$titleNeedle, $locationNeedle]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        $stmt = $db->prepare("SELECT id FROM {$p}365net_events WHERE LOWER(TRIM(title)) = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$titleNeedle]);

        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        $looseTitleNeedle = $this->normalizeLooseMatchValue($title);
        if ($looseTitleNeedle === '') {
            return 0;
        }

        static $seedCandidateCache = null;
        if ($seedCandidateCache === null) {
            $seedCandidateCache = [];
            $candidateStmt = $db->prepare("SELECT id, title, start_date, location FROM {$p}365net_events ORDER BY id ASC");
            $candidateStmt->execute([]);
            $candidates = $candidateStmt->fetchAll();
            if (is_array($candidates)) {
                foreach ($candidates as $candidate) {
                    if (!is_object($candidate)) {
                        continue;
                    }

                    $seedCandidateCache[] = $candidate;
                }
            }
        }

        $titleMatches = [];
        foreach ($seedCandidateCache as $candidate) {
            $candidateTitle = $this->normalizeLooseMatchValue((string) ($candidate->title ?? ''));
            if ($candidateTitle === '' || !$this->isLooseTitleCandidateMatch($candidateTitle, $looseTitleNeedle)) {
                continue;
            }

            $titleMatches[] = $candidate;
        }

        if ($titleMatches === []) {
            return 0;
        }

        $normalizedStartDate = trim((string) $startDate);
        if ($normalizedStartDate !== '') {
            foreach ($titleMatches as $candidate) {
                if ((string) ($candidate->start_date ?? '') === $normalizedStartDate) {
                    return (int) ($candidate->id ?? 0);
                }
            }
        }

        $looseLocationNeedle = $this->normalizeLooseMatchValue($location);
        if ($looseLocationNeedle !== '') {
            foreach ($titleMatches as $candidate) {
                $candidateLocation = $this->normalizeLooseMatchValue((string) ($candidate->location ?? ''));
                if ($candidateLocation !== '' && $candidateLocation === $looseLocationNeedle) {
                    return (int) ($candidate->id ?? 0);
                }
            }
        }

        if (count($titleMatches) === 1) {
            return (int) ($titleMatches[0]->id ?? 0);
        }

        $bestId = 0;
        $bestScore = 0;
        foreach ($titleMatches as $candidate) {
            $candidateTitle = $this->normalizeLooseMatchValue((string) ($candidate->title ?? ''));
            if ($candidateTitle === '') {
                continue;
            }

            $score = 0;
            if ($candidateTitle === $looseTitleNeedle) {
                $score += 140;
            } elseif (str_contains($candidateTitle, $looseTitleNeedle) || str_contains($looseTitleNeedle, $candidateTitle)) {
                $score += 110;
            }

            $similarity = 0.0;
            similar_text($candidateTitle, $looseTitleNeedle, $similarity);
            $score += (int) round($similarity);

            if ($normalizedStartDate !== '' && (string) ($candidate->start_date ?? '') === $normalizedStartDate) {
                $score += 60;
            }

            if ($looseLocationNeedle !== '') {
                $candidateLocation = $this->normalizeLooseMatchValue((string) ($candidate->location ?? ''));
                if ($candidateLocation !== '' && $candidateLocation === $looseLocationNeedle) {
                    $score += 30;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) ($candidate->id ?? 0);
            }
        }

        if ($bestScore >= 120 && $bestId > 0) {
            return $bestId;
        }

        return 0;
    }

    /** @return array<int, object> */
    public function getEvents(array $args = []): array
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $where = ['1=1'];
        $params = [];

        if (($args['status'] ?? '') !== '') {
            $where[] = 'e.status = ?';
            $params[] = (string) $args['status'];
        }

        if (($args['search'] ?? '') !== '') {
            $term = '%' . $this->cleanText((string) $args['search'], 120) . '%';
            $where[] = '(e.title LIKE ? OR e.location LIKE ? OR e.organizer LIKE ? OR e.category LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (($args['date_mode'] ?? '') === 'current_month') {
            $monthStart = (string) ($args['month_start'] ?? date('Y-m-01'));
            $monthEnd = (string) ($args['month_end'] ?? date('Y-m-t'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date BETWEEN ? AND ?';
            array_push($params, $monthStart, $monthEnd);
        } elseif (($args['date_mode'] ?? '') === 'future') {
            $from = (string) ($args['from'] ?? date('Y-m-d'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date >= ?';
            $params[] = $from;
        } elseif (($args['date_mode'] ?? '') === 'past') {
            $before = (string) ($args['before'] ?? date('Y-m-d'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date < ?';
            $params[] = $before;
        }

        $limit = $this->limit($args['limit'] ?? 100, 100);
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $order = match ((string) ($args['order'] ?? '')) {
            'updated_desc' => 'e.updated_at DESC',
            'date_desc' => 'COALESCE(e.start_date, e.created_at) DESC, e.title ASC',
            default => 'COALESCE(e.start_date, e.created_at) ASC, e.title ASC',
        };

        $stmt = $db->prepare("SELECT e.*, COUNT(r.id) AS speaker_count
            FROM {$p}365net_events e
            LEFT JOIN {$p}365net_event_speaker_rel r ON r.event_id = e.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY e.id
            ORDER BY {$order}
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countEvents(array $args = []): int
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $where = ['1=1'];
        $params = [];

        if (($args['status'] ?? '') !== '') {
            $where[] = 'e.status = ?';
            $params[] = (string) $args['status'];
        }

        if (($args['search'] ?? '') !== '') {
            $term = '%' . $this->cleanText((string) $args['search'], 120) . '%';
            $where[] = '(e.title LIKE ? OR e.location LIKE ? OR e.organizer LIKE ? OR e.category LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (($args['date_mode'] ?? '') === 'current_month') {
            $monthStart = (string) ($args['month_start'] ?? date('Y-m-01'));
            $monthEnd = (string) ($args['month_end'] ?? date('Y-m-t'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date BETWEEN ? AND ?';
            array_push($params, $monthStart, $monthEnd);
        } elseif (($args['date_mode'] ?? '') === 'future') {
            $from = (string) ($args['from'] ?? date('Y-m-d'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date >= ?';
            $params[] = $from;
        } elseif (($args['date_mode'] ?? '') === 'past') {
            $before = (string) ($args['before'] ?? date('Y-m-d'));
            $where[] = 'e.start_date IS NOT NULL AND e.start_date < ?';
            $params[] = $before;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$p}365net_events e WHERE " . implode(' AND ', $where));
        $stmt->execute($params);

        return max(0, (int) $stmt->fetchColumn());
    }

    public function getEvent(int $id): ?object
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("SELECT * FROM {$db->prefix()}365net_events WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getEventBySlug(string $slug): ?object
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("SELECT * FROM {$db->prefix()}365net_events WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveEvent(array $data): int|false
    {
        $this->ensureSchemaForSave();
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $id = (int) ($data['id'] ?? 0);
        $existing = $id > 0 ? $this->getEvent($id) : null;
        $title = $this->cleanText((string) ($data['title'] ?? ''), 255);
        if ($title === '' && $existing !== null && (string) ($existing->title ?? '') !== '') {
            $title = (string) $existing->title;
        }
        if ($title === '') {
            return false;
        }

        $descriptionJson = $this->cleanEditorJson((string) ($data['description_json'] ?? ''));
        $description = $this->cleanTextarea((string) ($data['description'] ?? ''), 10000);
        $descriptionFromEditorJson = '';
        if ($description === '' && $descriptionJson !== null) {
            $descriptionFromEditorJson = $this->editorJsonToFallbackText($descriptionJson, 10000);
            if ($descriptionFromEditorJson !== '') {
                $description = $descriptionFromEditorJson;
            }
        }

        $locationValue = $this->cleanText((string) ($data['location'] ?? ''), 255);
        $cityValue = $this->cleanText((string) ($data['city'] ?? ''), 120);
        if ($cityValue === '') {
            $cityValue = $this->cleanText($locationValue, 120);
        }

        $payload = [
            'unique_id' => (string) ($data['unique_id'] ?? ('manual-event-' . substr(hash('sha256', $title . microtime(true)), 0, 12))),
            'source_nr' => isset($data['source_nr']) && (int) $data['source_nr'] > 0 ? (int) $data['source_nr'] : null,
            'title' => $title,
            'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $title), '365net_events', $id),
            'start_date' => $this->normalizeDate((string) ($data['start_date'] ?? '')),
            'end_date' => $this->normalizeDate((string) ($data['end_date'] ?? '')),
            'date_label' => $this->cleanText((string) ($data['date_label'] ?? ''), 80),
            'end_date_label' => $this->cleanText((string) ($data['end_date_label'] ?? ''), 80),
            'location' => $locationValue,
            'organizer' => $this->cleanText((string) ($data['organizer'] ?? ''), 255),
            'source_column' => $this->cleanText((string) ($data['source_column'] ?? ''), 255),
            'category' => $this->cleanText((string) ($data['category'] ?? ''), 255),
            'event_type' => $this->cleanText((string) ($data['event_type'] ?? ''), 120),
            'price' => $this->cleanText((string) ($data['price'] ?? ''), 120),
            'website' => $this->cleanUrl((string) ($data['website'] ?? '')),
            'description' => $description,
            'description_json' => $descriptionJson,
            'excerpt' => $this->cleanTextarea((string) ($data['excerpt'] ?? ''), 1200),
            'image_url' => $this->cleanMediaUrl(
                (string) ($data['image_url'] ?? ''),
                $existing !== null ? (string) ($existing->image_url ?? '') : null
            ),
            'image_alt' => $this->cleanText((string) ($data['image_alt'] ?? ''), 255),
            'image_bg_color' => $this->cleanHexColor(
                (string) ($data['image_bg_color'] ?? ''),
                $existing !== null ? (string) ($existing->image_bg_color ?? '') : null
            ),
            'gallery_json' => $this->cleanJsonList((string) ($data['gallery_json'] ?? '')),
            'categories' => $this->cleanList($data['categories'] ?? '', 500),
            'tags' => $this->cleanList($data['tags'] ?? '', 700),
            'target_audience' => $this->cleanList($data['target_audience'] ?? '', 255),
            'event_format' => $this->cleanText((string) ($data['event_format'] ?? ''), 80),
            'attendance_mode' => $this->cleanText((string) ($data['attendance_mode'] ?? ''), 80),
            'difficulty_level' => $this->cleanText((string) ($data['difficulty_level'] ?? ''), 80),
            'language' => $this->cleanList($data['language'] ?? '', 80),
            'timezone' => $this->cleanText((string) ($data['timezone'] ?? ''), 80),
            'start_time' => $this->cleanText((string) ($data['start_time'] ?? ''), 20),
            'end_time' => $this->cleanText((string) ($data['end_time'] ?? ''), 20),
            'venue_name' => $this->cleanText((string) ($data['venue_name'] ?? ''), 255),
            'street' => $this->cleanText((string) ($data['street'] ?? ''), 255),
            'postal_code' => $this->cleanText((string) ($data['postal_code'] ?? ''), 30),
            'city' => $cityValue,
            'country' => $this->cleanText((string) ($data['country'] ?? ''), 120),
            'online_url' => $this->cleanUrl((string) ($data['online_url'] ?? '')),
            'registration_url' => $this->cleanUrl((string) ($data['registration_url'] ?? '')),
            'ticket_url' => $this->cleanUrl((string) ($data['ticket_url'] ?? '')),
            'price_class' => $this->cleanText((string) ($data['price_class'] ?? ''), 80),
            'price_min' => $this->cleanDecimal($data['price_min'] ?? null),
            'price_max' => $this->cleanDecimal($data['price_max'] ?? null),
            'currency' => $this->cleanText((string) ($data['currency'] ?? 'EUR'), 10),
            'early_bird_until' => $this->normalizeDate((string) ($data['early_bird_until'] ?? '')),
            'capacity' => $this->cleanPositiveInt($data['capacity'] ?? null),
            'contact_name' => $this->cleanText((string) ($data['contact_name'] ?? ''), 180),
            'contact_email' => $this->cleanEmail((string) ($data['contact_email'] ?? '')),
            'contact_phone' => $this->cleanText((string) ($data['contact_phone'] ?? ''), 80),
            'linked_company_id' => $this->resolveCompanyLink($data, $existing),
            'linked_expert_id' => $this->resolveExpertLink($data, $existing),
            'sponsors' => $this->cleanTextarea((string) ($data['sponsors'] ?? ''), 3000),
            'accessibility' => $this->cleanTextarea((string) ($data['accessibility'] ?? ''), 3000),
            'seo_title' => $this->cleanText((string) ($data['seo_title'] ?? ''), 255),
            'seo_description' => $this->cleanText((string) ($data['seo_description'] ?? ''), 320),
            'og_image_url' => $this->cleanMediaUrl(
                (string) ($data['og_image_url'] ?? ''),
                $existing !== null ? (string) ($existing->og_image_url ?? '') : null
            ),
            'featured' => !empty($data['featured']) ? 1 : 0,
            'status' => in_array((string) ($data['status'] ?? 'published'), ['draft', 'published'], true) ? (string) $data['status'] : 'published',
        ];

        $payload = $this->applyUpdateFallbacks(
            $payload,
            $data,
            $existing,
            $this->looksLikeSparseUpdate($data, $existing, ['title', 'start_date', 'date_label', 'location', 'organizer', 'category', 'website'])
        );

        if ($descriptionFromEditorJson !== '') {
            $payload['description'] = $descriptionFromEditorJson;
        }

        $payloadKeysBeforeFilter = array_keys($payload);
        $payload = $this->filterPayloadByExistingColumns('365net_events', $payload);
        $this->logDroppedPayloadKeys('365net_events', $payloadKeysBeforeFilter, $payload, $data);

        if ($id > 0) {
            $sets = implode(', ', array_map(static fn(string $key): string => "`{$key}` = ?", array_keys($payload)));
            $stmt = $db->prepare("UPDATE {$p}365net_events SET {$sets} WHERE id = ?");
            $stmt->execute([...array_values($payload), $id]);
            CMS\Hooks::doAction('cms_365net_event_updated', $id, $payload);
            return $id;
        }

        $keys = implode(', ', array_map(static fn(string $key): string => "`{$key}`", array_keys($payload)));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $stmt = $db->prepare("INSERT INTO {$p}365net_events ({$keys}) VALUES ({$places})");
        $stmt->execute(array_values($payload));
        $newId = (int) $db->getPdo()->lastInsertId();
        CMS\Hooks::doAction('cms_365net_event_created', $newId, $payload);
        return $newId;
    }

    public function deleteEvent(int $id): bool
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("DELETE FROM {$db->prefix()}365net_events WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok) {
            CMS\Hooks::doAction('cms_365net_event_deleted', $id);
        }

        return $ok;
    }

    /** @return array<int, object> */
    public function getSpeakers(array $args = []): array
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $where = ['1=1'];
        $params = [];

        if (($args['status'] ?? '') !== '') {
            $where[] = 's.status = ?';
            $params[] = (string) $args['status'];
        }

        if (($args['search'] ?? '') !== '') {
            $term = '%' . $this->cleanText((string) $args['search'], 120) . '%';
            $where[] = '(s.display_name LIKE ? OR s.company LIKE ? OR s.topic LIKE ? OR s.award LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        $limit = $this->limit($args['limit'] ?? 100, 100);
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $order = match ((string) ($args['order'] ?? 'az')) {
            'za' => 's.display_name DESC',
            'date_old_new' => 'COALESCE(s.updated_at, s.created_at) ASC, s.display_name ASC',
            'date_new_old' => 'COALESCE(s.updated_at, s.created_at) DESC, s.display_name ASC',
            default => 's.display_name ASC',
        };

        $stmt = $db->prepare("SELECT s.*, COUNT(r.id) AS event_count
            FROM {$p}365net_event_speakers s
            LEFT JOIN {$p}365net_event_speaker_rel r ON r.speaker_id = s.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY s.id
            ORDER BY {$order}
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countSpeakers(array $args = []): int
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $where = ['1=1'];
        $params = [];

        if (($args['status'] ?? '') !== '') {
            $where[] = 's.status = ?';
            $params[] = (string) $args['status'];
        }

        if (($args['search'] ?? '') !== '') {
            $term = '%' . $this->cleanText((string) $args['search'], 120) . '%';
            $where[] = '(s.display_name LIKE ? OR s.company LIKE ? OR s.topic LIKE ? OR s.award LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$p}365net_event_speakers s WHERE " . implode(' AND ', $where));
        $stmt->execute($params);

        return max(0, (int) $stmt->fetchColumn());
    }

    public function getSpeaker(int $id): ?object
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("SELECT * FROM {$db->prefix()}365net_event_speakers WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getSpeakerBySlug(string $slug): ?object
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("SELECT * FROM {$db->prefix()}365net_event_speakers WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveSpeaker(array $data): int|false
    {
        $this->ensureSchemaForSave();
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $id = (int) ($data['id'] ?? 0);
        $existing = $id > 0 ? $this->getSpeaker($id) : null;
        $firstName = $this->cleanText((string) ($data['first_name'] ?? ''), 120);
        $lastName = $this->cleanText((string) ($data['last_name'] ?? ''), 120);
        $displayName = $this->cleanText((string) ($data['display_name'] ?? ''), 255);
        if ($firstName === '' && $existing !== null && (string) ($existing->first_name ?? '') !== '') {
            $firstName = (string) $existing->first_name;
        }
        if ($lastName === '' && $existing !== null && (string) ($existing->last_name ?? '') !== '') {
            $lastName = (string) $existing->last_name;
        }
        if ($displayName === '' && $existing !== null && (string) ($existing->display_name ?? '') !== '') {
            $displayName = (string) $existing->display_name;
        }

        $hasNamePair = $firstName !== '' && $lastName !== '';
        if ($displayName === '' && $hasNamePair) {
            $displayName = trim($firstName . ' ' . $lastName);
        }

        if ($displayName === '' && !$hasNamePair) {
            return false;
        }

        if ($hasNamePair && !$this->isRealPersonSpeaker($firstName, $lastName, $displayName)) {
            return false;
        }

        $bioJson = $this->cleanEditorJson((string) ($data['bio_json'] ?? ''));
        $bio = $this->cleanTextarea((string) ($data['bio'] ?? ''), 10000);
        $bioFromEditorJson = '';
        if ($bio === '' && $bioJson !== null) {
            $bioFromEditorJson = $this->editorJsonToFallbackText($bioJson, 10000);
            if ($bioFromEditorJson !== '') {
                $bio = $bioFromEditorJson;
            }
        }

        $payload = [
            'unique_id' => (string) ($data['unique_id'] ?? ('manual-speaker-' . substr(hash('sha256', $displayName . microtime(true)), 0, 12))),
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'display_name' => $displayName,
            'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $displayName), '365net_event_speakers', $id),
            'company' => null,
            'topic' => $this->cleanText((string) ($data['topic'] ?? ''), 500),
            'award' => $this->cleanText((string) ($data['award'] ?? ''), 255),
            'website' => $this->cleanUrl((string) ($data['website'] ?? '')),
            'bio' => $bio,
            'bio_json' => $bioJson,
            'avatar_url' => $this->cleanMediaUrl(
                (string) ($data['avatar_url'] ?? ''),
                $existing !== null ? (string) ($existing->avatar_url ?? '') : null
            ),
            'avatar_alt' => $this->cleanText((string) ($data['avatar_alt'] ?? ''), 255),
            'theme_image_url' => $this->cleanMediaUrl(
                (string) ($data['theme_image_url'] ?? ''),
                $existing !== null ? (string) ($existing->theme_image_url ?? '') : null
            ),
            'theme_image_alt' => $this->cleanText((string) ($data['theme_image_alt'] ?? ''), 255),
            'categories' => $this->cleanList($data['categories'] ?? '', 500),
            'tags' => $this->cleanList($data['tags'] ?? '', 700),
            'specializations' => $this->cleanList($data['specializations'] ?? '', 700),
            'languages' => $this->cleanList($data['languages'] ?? '', 255),
            'speaker_type' => $this->cleanText((string) ($data['speaker_type'] ?? ''), 80),
            'price_class' => $this->cleanText((string) ($data['price_class'] ?? ''), 80),
            'fee_min' => $this->cleanDecimal($data['fee_min'] ?? null),
            'fee_max' => $this->cleanDecimal($data['fee_max'] ?? null),
            'currency' => $this->cleanText((string) ($data['currency'] ?? 'EUR'), 10),
            'speaking_formats' => $this->cleanList($data['speaking_formats'] ?? '', 255),
            'availability' => $this->cleanText((string) ($data['availability'] ?? ''), 255),
            'email' => $this->cleanEmail((string) ($data['email'] ?? '')),
            'phone' => $this->cleanText((string) ($data['phone'] ?? ''), 80),
            'location' => $this->cleanText((string) ($data['location'] ?? ''), 180),
            'linked_expert_id' => $this->resolveExpertLink($data, $existing),
            'linked_company_id' => $this->resolveCompanyLink($data, $existing),
            'linkedin_url' => $this->cleanUrl((string) ($data['linkedin_url'] ?? '')),
            'x_url' => $this->cleanUrl((string) ($data['x_url'] ?? '')),
            'youtube_url' => $this->cleanUrl((string) ($data['youtube_url'] ?? '')),
            'github_url' => $this->cleanUrl((string) ($data['github_url'] ?? '')),
            'seo_title' => $this->cleanText((string) ($data['seo_title'] ?? ''), 255),
            'seo_description' => $this->cleanText((string) ($data['seo_description'] ?? ''), 320),
            'og_image_url' => $this->cleanMediaUrl(
                (string) ($data['og_image_url'] ?? ''),
                $existing !== null ? (string) ($existing->og_image_url ?? '') : null
            ),
            'featured' => !empty($data['featured']) ? 1 : 0,
            'status' => in_array((string) ($data['status'] ?? 'published'), ['draft', 'published'], true) ? (string) $data['status'] : 'published',
        ];

        $payload = $this->applyUpdateFallbacks(
            $payload,
            $data,
            $existing,
            $this->looksLikeSparseUpdate($data, $existing, ['display_name', 'first_name', 'last_name', 'company', 'topic', 'award', 'website'])
        );

        if ($bioFromEditorJson !== '') {
            $payload['bio'] = $bioFromEditorJson;
        }
        $payloadKeysBeforeFilter = array_keys($payload);
        $payload = $this->filterPayloadByExistingColumns('365net_event_speakers', $payload);
        $this->logDroppedPayloadKeys('365net_event_speakers', $payloadKeysBeforeFilter, $payload, $data);

        if ($id > 0) {
            $sets = implode(', ', array_map(static fn(string $key): string => "`{$key}` = ?", array_keys($payload)));
            $stmt = $db->prepare("UPDATE {$p}365net_event_speakers SET {$sets} WHERE id = ?");
            $stmt->execute([...array_values($payload), $id]);
            $this->syncExcompLinksFromSpeaker(
                $id,
                $this->cleanPositiveInt($payload['linked_expert_id'] ?? null),
                $this->cleanPositiveInt($payload['linked_company_id'] ?? null)
            );
            CMS\Hooks::doAction('cms_365net_speaker_updated', $id, $payload);
            return $id;
        }

        $keys = implode(', ', array_map(static fn(string $key): string => "`{$key}`", array_keys($payload)));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $stmt = $db->prepare("INSERT INTO {$p}365net_event_speakers ({$keys}) VALUES ({$places})");
        $stmt->execute(array_values($payload));
        $newId = (int) $db->getPdo()->lastInsertId();
        $this->syncExcompLinksFromSpeaker(
            $newId,
            $this->cleanPositiveInt($payload['linked_expert_id'] ?? null),
            $this->cleanPositiveInt($payload['linked_company_id'] ?? null)
        );
        CMS\Hooks::doAction('cms_365net_speaker_created', $newId, $payload);
        return $newId;
    }

    public function deleteSpeaker(int $id): bool
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("DELETE FROM {$db->prefix()}365net_event_speakers WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok) {
            CMS\Hooks::doAction('cms_365net_speaker_deleted', $id);
        }

        return $ok;
    }

    /** @return array<int, object> */
    public function getSpeakersForEvent(int $eventId): array
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $stmt = $db->prepare("SELECT s.*, r.speaker_nr, r.topic AS relation_topic, r.award AS relation_award, r.website AS relation_website
            FROM {$p}365net_event_speaker_rel r
            INNER JOIN {$p}365net_event_speakers s ON s.id = r.speaker_id
            WHERE r.event_id = ?
            ORDER BY COALESCE(r.speaker_nr, r.sort_order), s.display_name");
        $stmt->execute([$eventId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, object> */
    public function getEventsForSpeaker(int $speakerId): array
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $stmt = $db->prepare("SELECT e.*, r.topic AS relation_topic, r.award AS relation_award
            FROM {$p}365net_event_speaker_rel r
            INNER JOIN {$p}365net_events e ON e.id = r.event_id
            WHERE r.speaker_id = ? AND e.status = 'published'
            ORDER BY COALESCE(e.start_date, e.created_at) ASC");
        $stmt->execute([$speakerId]);

        return $stmt->fetchAll();
    }

    public function saveEventSpeakers(int $eventId, array $speakerIds): void
    {
        $db = CMS\Database::instance();
        $p = $db->prefix();
        $db->prepare("DELETE FROM {$p}365net_event_speaker_rel WHERE event_id = ?")->execute([$eventId]);
        $stmt = $db->prepare("INSERT IGNORE INTO {$p}365net_event_speaker_rel (event_id, speaker_id, speaker_nr, sort_order) VALUES (?, ?, ?, ?)");
        $order = 0;
        foreach ($speakerIds as $speakerId) {
            $speakerId = (int) $speakerId;
            if ($speakerId <= 0) {
                continue;
            }
            $order++;
            $stmt->execute([$eventId, $speakerId, $order, $order]);
        }
    }

    /** @return array<int, object> */
    public function getAvailableCompanies(int $limit = 300): array
    {
        $sourceTable = $this->resolveCompanySourceTable();

        if ($sourceTable === '') {
            return [];
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === []) {
            return [];
        }

        $nameColumn = $this->firstExistingColumn($columns, ['name', 'company_name', 'title']);
        $websiteColumn = $this->firstExistingColumn($columns, ['website', 'url', 'homepage']);
        $cityColumn = $this->firstExistingColumn($columns, ['location_city', 'city', 'location']);
        $statusColumn = $this->firstExistingColumn($columns, ['status']);

        $selectParts = ['id'];
        if ($nameColumn !== null) {
            $selectParts[] = $nameColumn . ' AS name';
        } else {
            $selectParts[] = "CONCAT('Company #', id) AS name";
        }
        if ($websiteColumn !== null) {
            $selectParts[] = $websiteColumn . ' AS website';
        }
        if ($cityColumn !== null) {
            $selectParts[] = $cityColumn . ' AS location_city';
        }
        if ($statusColumn !== null) {
            $selectParts[] = $statusColumn . ' AS status';
        }

        if ($selectParts === []) {
            return [];
        }

        $db = CMS\Database::instance();
        $limit = $this->limit($limit, 300);

        $orderParts = [];
        if ($statusColumn !== null) {
            $orderParts[] = "CASE WHEN LOWER({$statusColumn}) IN ('active','published') THEN 0 WHEN LOWER({$statusColumn}) = 'draft' THEN 1 ELSE 2 END";
        }
        if ($nameColumn !== null) {
            $orderParts[] = $nameColumn . ' ASC';
        } else {
            $orderParts[] = 'id ASC';
        }

        try {
            $stmt = $db->prepare('SELECT ' . implode(', ', $selectParts) . " FROM {$db->prefix()}{$sourceTable} ORDER BY " . implode(', ', $orderParts) . " LIMIT {$limit}");
            $stmt->execute([]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            $this->logDatabaseWarning('get_available_companies', $e);
            return [];
        }
    }

    /** @return array<int, object> */
    public function getAvailableExperts(int $limit = 300): array
    {
        $sourceTable = $this->resolveExpertSourceTable();

        if ($sourceTable === '') {
            return [];
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === []) {
            return [];
        }

        $firstNameColumn = $this->firstExistingColumn($columns, ['first_name', 'firstname', 'given_name']);
        $lastNameColumn = $this->firstExistingColumn($columns, ['last_name', 'lastname', 'family_name']);
        $displayNameColumn = $this->firstExistingColumn($columns, ['display_name', 'name', 'full_name']);
        $emailColumn = $this->firstExistingColumn($columns, ['email', 'mail']);
        $positionColumn = $this->firstExistingColumn($columns, ['position', 'title', 'role', 'topic']);
        $companyColumn = $this->firstExistingColumn($columns, ['company', 'company_name', 'organisation']);
        $statusColumn = $this->firstExistingColumn($columns, ['status']);

        $selectParts = ['id'];
        if ($firstNameColumn !== null) {
            $selectParts[] = $firstNameColumn . ' AS first_name';
        } elseif ($displayNameColumn !== null) {
            $selectParts[] = $displayNameColumn . ' AS first_name';
        } else {
            $selectParts[] = "CONCAT('Expert #', id) AS first_name";
        }
        if ($lastNameColumn !== null) {
            $selectParts[] = $lastNameColumn . ' AS last_name';
        } else {
            $selectParts[] = "'' AS last_name";
        }
        if ($emailColumn !== null) {
            $selectParts[] = $emailColumn . ' AS email';
        }
        if ($positionColumn !== null) {
            $selectParts[] = $positionColumn . ' AS position';
        }
        if ($companyColumn !== null) {
            $selectParts[] = $companyColumn . ' AS company';
        }
        if ($statusColumn !== null) {
            $selectParts[] = $statusColumn . ' AS status';
        }

        if ($selectParts === []) {
            return [];
        }

        $db = CMS\Database::instance();
        $limit = $this->limit($limit, 300);

        $orderParts = [];
        if ($statusColumn !== null) {
            $orderParts[] = "CASE WHEN LOWER({$statusColumn}) IN ('active','published') THEN 0 WHEN LOWER({$statusColumn}) = 'draft' THEN 1 ELSE 2 END";
        }
        if ($lastNameColumn !== null) {
            $orderParts[] = $lastNameColumn . ' ASC';
        }
        if ($firstNameColumn !== null) {
            $orderParts[] = $firstNameColumn . ' ASC';
        } elseif ($displayNameColumn !== null) {
            $orderParts[] = $displayNameColumn . ' ASC';
        } else {
            $orderParts[] = 'id ASC';
        }

        try {
            $stmt = $db->prepare('SELECT ' . implode(', ', $selectParts) . " FROM {$db->prefix()}{$sourceTable} ORDER BY " . implode(', ', $orderParts) . " LIMIT {$limit}");
            $stmt->execute([]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            $this->logDatabaseWarning('get_available_experts', $e);
            return [];
        }
    }

    public function getLinkedCompany(?int $companyId): ?object
    {
        if (!$companyId) {
            return null;
        }

        $sourceTable = $this->resolveCompanySourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === []) {
            return null;
        }

        $selectColumns = [];
        foreach (['id', 'name', 'website', 'logo_url', 'location_city', 'industry', 'linked_expert_id', 'linked_speaker_id', 'status'] as $column) {
            if (in_array($column, $columns, true)) {
                $selectColumns[] = $column;
            }
        }
        if (!in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }

        $db = CMS\Database::instance();
        $stmt = $db->prepare('SELECT ' . implode(', ', $selectColumns) . " FROM {$db->prefix()}{$sourceTable} WHERE id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getLinkedExpert(?int $expertId): ?object
    {
        if (!$expertId) {
            return null;
        }

        $sourceTable = $this->resolveExpertSourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === []) {
            return null;
        }

        $selectColumns = [];
        foreach (['id', 'first_name', 'last_name', 'email', 'position', 'company', 'photo_url', 'location_city', 'linked_company_id', 'linked_speaker_id', 'status'] as $column) {
            if (in_array($column, $columns, true)) {
                $selectColumns[] = $column;
            }
        }
        if (!in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }

        $db = CMS\Database::instance();
        $stmt = $db->prepare('SELECT ' . implode(', ', $selectColumns) . " FROM {$db->prefix()}{$sourceTable} WHERE id = ? LIMIT 1");
        $stmt->execute([$expertId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    private function resolveCompanyLink(array $data, ?object $existing): ?int
    {
        if (array_key_exists('linked_company_id', $data)) {
            $manual = $this->cleanPositiveInt($data['linked_company_id']);
            if ($manual && $this->getLinkedCompany($manual)) {
                return $manual;
            }

            return $this->findCompanyIdBySignals($data);
        }

        if ($existing !== null && !empty($existing->linked_company_id)) {
            return (int) $existing->linked_company_id;
        }

        return $this->findCompanyIdBySignals($data);
    }

    /** @param array<string, mixed> $data */
    private function resolveExpertLink(array $data, ?object $existing): ?int
    {
        if (array_key_exists('linked_expert_id', $data)) {
            $manual = $this->cleanPositiveInt($data['linked_expert_id']);
            if ($manual && $this->getLinkedExpert($manual)) {
                return $manual;
            }

            return $this->findExpertIdBySignals($data);
        }

        if ($existing !== null && !empty($existing->linked_expert_id)) {
            return (int) $existing->linked_expert_id;
        }

        return $this->findExpertIdBySignals($data);
    }

    private function resolveCompanySourceTable(): string
    {
        return $this->resolvePreferredSourceTable(['365net_excomp_companies', 'companies']);
    }

    private function resolveExpertSourceTable(): string
    {
        return $this->resolvePreferredSourceTable(['365net_excomp_experts', 'experts']);
    }

    /** @param array<int, string> $candidates */
    private function resolvePreferredSourceTable(array $candidates): string
    {
        $available = [];
        foreach ($candidates as $candidate) {
            if ($this->tableExists($candidate)) {
                $available[] = $candidate;
            }
        }

        if ($available === []) {
            return '';
        }

        foreach ($available as $candidate) {
            if ($this->tableRowCount($candidate) > 0) {
                return $candidate;
            }
        }

        return $available[0];
    }

    private function tableRowCount(string $table): int
    {
        try {
            $db = CMS\Database::instance();
            $stmt = $db->prepare('SELECT COUNT(*) FROM ' . $db->prefix() . $table);
            $stmt->execute([]);
            return max(0, (int) ($stmt->fetchColumn() ?: 0));
        } catch (Throwable) {
            return 0;
        }
    }

    /** @param array<int, string> $columns @param array<int, string> $candidates */
    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $signals */
    public function detectLinkedCompanyBySignals(array $signals): ?object
    {
        $companyId = $this->findCompanyIdBySignals($signals);
        return $companyId !== null ? $this->getLinkedCompany($companyId) : null;
    }

    /** @param array<string, mixed> $signals */
    public function detectLinkedExpertBySignals(array $signals): ?object
    {
        $expertId = $this->findExpertIdBySignals($signals);
        return $expertId !== null ? $this->getLinkedExpert($expertId) : null;
    }

    public function findLinkedCompanyBySpeakerId(int $speakerId): ?object
    {
        if ($speakerId <= 0) {
            return null;
        }

        $sourceTable = $this->resolveCompanySourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if (!in_array('linked_speaker_id', $columns, true)) {
            return null;
        }

        $where = ['linked_speaker_id = ?'];
        $params = [$speakerId];
        if (in_array('status', $columns, true)) {
            $where[] = 'LOWER(status) IN (?, ?)';
            $params[] = 'active';
            $params[] = 'published';
        }

        $orderParts = [];
        foreach (['is_sponsor', 'is_top_partner', 'is_partner'] as $partnerColumn) {
            if (in_array($partnerColumn, $columns, true)) {
                $orderParts[] = $partnerColumn . ' DESC';
            }
        }
        $orderParts[] = in_array('name', $columns, true) ? 'name ASC' : 'id ASC';

        $db = CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id FROM {$db->prefix()}{$sourceTable} WHERE " . implode(' AND ', $where) . ' ORDER BY ' . implode(', ', $orderParts) . ' LIMIT 1'
        );
        $stmt->execute($params);
        $companyId = (int) ($stmt->fetchColumn() ?: 0);

        return $companyId > 0 ? $this->getLinkedCompany($companyId) : null;
    }

    public function findLinkedExpertBySpeakerId(int $speakerId): ?object
    {
        if ($speakerId <= 0) {
            return null;
        }

        $sourceTable = $this->resolveExpertSourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if (!in_array('linked_speaker_id', $columns, true)) {
            return null;
        }

        $where = ['linked_speaker_id = ?'];
        $params = [$speakerId];
        if (in_array('status', $columns, true)) {
            $where[] = 'LOWER(status) IN (?, ?)';
            $params[] = 'active';
            $params[] = 'published';
        }

        $orderBy = in_array('updated_at', $columns, true) ? 'updated_at DESC' : 'id DESC';
        $db = CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id FROM {$db->prefix()}{$sourceTable} WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT 1'
        );
        $stmt->execute($params);
        $expertId = (int) ($stmt->fetchColumn() ?: 0);

        return $expertId > 0 ? $this->getLinkedExpert($expertId) : null;
    }

    /** @param array<string, mixed> $data */
    private function findCompanyIdBySignals(array $data): ?int
    {
        $sourceTable = $this->resolveCompanySourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === [] || !in_array('id', $columns, true)) {
            return null;
        }

        $nameColumn = $this->firstExistingColumn($columns, ['name', 'company_name', 'title']);
        $websiteColumn = $this->firstExistingColumn($columns, ['website', 'url', 'homepage']);

        $names = array_filter(array_unique(array_map([$this, 'normalizeMatchValue'], [
            (string) ($data['organizer'] ?? ''),
            (string) ($data['company'] ?? ''),
            (string) ($data['contact_name'] ?? ''),
        ])));
        $domain = $this->extractDomain((string) (($data['website'] ?? '') ?: ($data['registration_url'] ?? '') ?: ($data['ticket_url'] ?? '')));

        if ($names === [] && $domain === '') {
            return null;
        }

        $conditions = [];
        $params = [];
        if ($nameColumn !== null) {
            foreach ($names as $name) {
                $conditions[] = 'LOWER(TRIM(' . $nameColumn . ')) = ?';
                $params[] = $name;
            }
        }
        if ($domain !== '' && $websiteColumn !== null) {
            $conditions[] = $websiteColumn . ' LIKE ?';
            $params[] = '%' . $domain . '%';
        }

        if ($conditions === []) {
            return null;
        }

        $where = ['(' . implode(' OR ', $conditions) . ')'];

        $orderParts = [];
        foreach (['is_sponsor', 'is_top_partner', 'is_partner'] as $partnerColumn) {
            if (in_array($partnerColumn, $columns, true)) {
                $orderParts[] = $partnerColumn . ' DESC';
            }
        }
        if ($nameColumn !== null) {
            $orderParts[] = $nameColumn . ' ASC';
        } else {
            $orderParts[] = 'id ASC';
        }

        $db = CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id FROM {$db->prefix()}{$sourceTable} WHERE " . implode(' AND ', $where) . ' ORDER BY ' . implode(', ', $orderParts) . ' LIMIT 1'
        );
        $stmt->execute($params);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    /** @param array<string, mixed> $data */
    private function findExpertIdBySignals(array $data): ?int
    {
        $sourceTable = $this->resolveExpertSourceTable();
        if ($sourceTable === '') {
            return null;
        }

        $columns = $this->getExternalTableColumns($sourceTable);
        if ($columns === [] || !in_array('id', $columns, true)) {
            return null;
        }

        $emailColumn = $this->firstExistingColumn($columns, ['email', 'mail']);
        $firstNameColumn = $this->firstExistingColumn($columns, ['first_name', 'firstname', 'given_name']);
        $lastNameColumn = $this->firstExistingColumn($columns, ['last_name', 'lastname', 'family_name']);

        $display = $this->normalizeMatchValue((string) (($data['display_name'] ?? '') ?: ($data['contact_name'] ?? '')));
        $first = $this->normalizeMatchValue((string) ($data['first_name'] ?? ''));
        $last = $this->normalizeMatchValue((string) ($data['last_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $conditions = [];
        $params = [];

        if ($email !== '' && $emailColumn !== null) {
            $conditions[] = 'LOWER(' . $emailColumn . ') = ?';
            $params[] = $email;
        }
        if ($display !== '' && $firstNameColumn !== null && $lastNameColumn !== null) {
            $conditions[] = "LOWER(TRIM(CONCAT({$firstNameColumn}, ' ', {$lastNameColumn}))) = ?";
            $params[] = $display;
        }
        if ($first !== '' && $last !== '' && $firstNameColumn !== null && $lastNameColumn !== null) {
            $conditions[] = '(LOWER(TRIM(' . $firstNameColumn . ')) = ? AND LOWER(TRIM(' . $lastNameColumn . ')) = ?)';
            $params[] = $first;
            $params[] = $last;
        }
        if ($conditions === []) {
            return null;
        }

        $where = ['(' . implode(' OR ', $conditions) . ')'];

        $orderBy = in_array('updated_at', $columns, true) ? 'updated_at DESC' : 'id DESC';

        $db = CMS\Database::instance();
        $stmt = $db->prepare(
            "SELECT id FROM {$db->prefix()}{$sourceTable} WHERE " . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT 1'
        );
        $stmt->execute($params);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    private function tableExists(string $table): bool
    {
        if (isset($this->tableExistsCache[$table])) {
            return $this->tableExistsCache[$table];
        }

        if (!in_array($table, ['companies', 'experts', '365net_excomp_companies', '365net_excomp_experts', '365net_events', '365net_event_speakers'], true)) {
            return false;
        }

        try {
            $db = CMS\Database::instance();
            $this->tableExistsCache[$table] = $db->tableExists($db->prefix() . $table);
        } catch (Throwable $e) {
            $this->logDatabaseWarning('table_exists_' . $table, $e);
            $this->tableExistsCache[$table] = false;
        }

        return $this->tableExistsCache[$table];
    }

    private function normalizeMatchValue(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        return mb_strtolower($value);
    }

    private function normalizeLooseMatchValue(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{2010}-\x{2015}\x{2212}\-]+/u', '-', $value) ?: $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        return $this->lower($value);
    }

    private function extractDomain(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $host = parse_url(str_starts_with($url, 'http') ? $url : 'https://' . $url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return preg_replace('/^www\./i', '', mb_strtolower($host)) ?: '';
    }

    public function autoLinkExistingRecords(): void
    {
        try {
            $db = CMS\Database::instance();
            $p = $db->prefix();

            if ($this->tableExists('365net_events')) {
                $events = $db->prepare("SELECT id, organizer, contact_name, website, registration_url, ticket_url FROM {$p}365net_events WHERE linked_company_id IS NULL OR linked_expert_id IS NULL LIMIT 500");
                $events->execute([]);
                $updateEvent = $db->prepare("UPDATE {$p}365net_events SET linked_company_id = COALESCE(linked_company_id, ?), linked_expert_id = COALESCE(linked_expert_id, ?) WHERE id = ?");
                foreach ($events->fetchAll() as $event) {
                    $signals = [
                        'organizer' => $event->organizer ?? '',
                        'contact_name' => $event->contact_name ?? '',
                        'website' => $event->website ?? '',
                        'registration_url' => $event->registration_url ?? '',
                        'ticket_url' => $event->ticket_url ?? '',
                    ];
                    $updateEvent->execute([$this->findCompanyIdBySignals($signals), $this->findExpertIdBySignals($signals), (int) $event->id]);
                }
            }

            if ($this->tableExists('365net_event_speakers')) {
                $speakers = $db->prepare("SELECT id, first_name, last_name, display_name, company, email, website FROM {$p}365net_event_speakers WHERE linked_company_id IS NULL OR linked_expert_id IS NULL LIMIT 500");
                $speakers->execute([]);
                $updateSpeaker = $db->prepare("UPDATE {$p}365net_event_speakers SET linked_company_id = COALESCE(linked_company_id, ?), linked_expert_id = COALESCE(linked_expert_id, ?) WHERE id = ?");
                foreach ($speakers->fetchAll() as $speaker) {
                    $signals = [
                        'first_name' => $speaker->first_name ?? '',
                        'last_name' => $speaker->last_name ?? '',
                        'display_name' => $speaker->display_name ?? '',
                        'company' => $speaker->company ?? '',
                        'email' => $speaker->email ?? '',
                        'website' => $speaker->website ?? '',
                    ];
                    $updateSpeaker->execute([$this->findCompanyIdBySignals($signals), $this->findExpertIdBySignals($signals), (int) $speaker->id]);
                }
            }
        } catch (Throwable $e) {
            $this->logDatabaseWarning('auto_link_existing_records', $e);
        }
    }

    public function purgeNonPersonSpeakers(): void
    {
        try {
            if (!$this->tableExists('365net_event_speakers')) {
                return;
            }

            $db = CMS\Database::instance();
            $p = $db->prefix();
            $stmt = $db->prepare("SELECT id, first_name, last_name, display_name FROM {$p}365net_event_speakers LIMIT 1000");
            $stmt->execute([]);
            $delete = $db->prepare("DELETE FROM {$p}365net_event_speakers WHERE id = ?");
            foreach ($stmt->fetchAll() as $speaker) {
                if (!$this->isRealPersonSpeaker((string) ($speaker->first_name ?? ''), (string) ($speaker->last_name ?? ''), (string) ($speaker->display_name ?? ''))) {
                    $delete->execute([(int) $speaker->id]);
                }
            }

            $db->prepare("UPDATE {$p}365net_event_speakers SET company = NULL")->execute([]);
        } catch (Throwable $e) {
            $this->logDatabaseWarning('purge_non_person_speakers', $e);
        }
    }

    private function isRealPersonSpeaker(string $firstName, string $lastName, string $displayName = ''): bool
    {
        $firstName = $this->cleanText($firstName, 120);
        $lastName = $this->cleanText($lastName, 120);
        $displayName = $this->cleanText($displayName, 255);

        if ($firstName === '' || $lastName === '') {
            return false;
        }

        $combinedRaw = trim($firstName . ' ' . $lastName . ' ' . $displayName);
        $combined = function_exists('mb_strtolower') ? mb_strtolower($combinedRaw, 'UTF-8') : strtolower($combinedRaw);
        $companyTerms = ['gmbh', 'ag', 'kg', 'ug', 'inc', 'ltd', 'llc', 'group', 'gruppe', 'media', 'messe', 'community', 'plattform', 'platform', 'verband', 'verein', 'team', 'experten', 'experts', 'champions', 'architekten', 'speaker', 'organisation', 'organizer'];
        foreach ($companyTerms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $combined) === 1) {
                return false;
            }
        }

        return preg_match('/^[\p{L}\p{M} .\'\-]+$/u', $firstName . ' ' . $lastName) === 1;
    }

    private function syncExcompLinksFromSpeaker(int $speakerId, ?int $linkedExpertId, ?int $linkedCompanyId): void
    {
        if ($speakerId <= 0) {
            return;
        }

        $db = CMS\Database::instance();

        try {
            if ($this->tableExists('365net_excomp_experts')) {
                $expertColumns = $this->getExternalTableColumns('365net_excomp_experts');
                if (in_array('linked_speaker_id', $expertColumns, true)) {
                    $expertsTable = $db->prefix() . '365net_excomp_experts';

                    if ($linkedExpertId === null || $linkedExpertId <= 0) {
                        $clearExperts = $db->prepare("UPDATE {$expertsTable} SET linked_speaker_id = NULL WHERE linked_speaker_id = ?");
                        $clearExperts->execute([$speakerId]);
                    } else {
                        $clearOtherExperts = $db->prepare("UPDATE {$expertsTable} SET linked_speaker_id = NULL WHERE linked_speaker_id = ? AND id <> ?");
                        $clearOtherExperts->execute([$speakerId, $linkedExpertId]);

                        if (in_array('linked_company_id', $expertColumns, true)) {
                            $updateExpert = $db->prepare("UPDATE {$expertsTable} SET linked_speaker_id = ?, linked_company_id = ? WHERE id = ?");
                            $updateExpert->execute([$speakerId, $linkedCompanyId, $linkedExpertId]);
                        } else {
                            $updateExpert = $db->prepare("UPDATE {$expertsTable} SET linked_speaker_id = ? WHERE id = ?");
                            $updateExpert->execute([$speakerId, $linkedExpertId]);
                        }
                    }
                }
            }

            if ($this->tableExists('365net_excomp_companies')) {
                $companyColumns = $this->getExternalTableColumns('365net_excomp_companies');
                if (in_array('linked_speaker_id', $companyColumns, true)) {
                    $companiesTable = $db->prefix() . '365net_excomp_companies';

                    if ($linkedCompanyId === null || $linkedCompanyId <= 0) {
                        $clearCompanies = $db->prepare("UPDATE {$companiesTable} SET linked_speaker_id = NULL WHERE linked_speaker_id = ?");
                        $clearCompanies->execute([$speakerId]);
                    } else {
                        $clearOtherCompanies = $db->prepare("UPDATE {$companiesTable} SET linked_speaker_id = NULL WHERE linked_speaker_id = ? AND id <> ?");
                        $clearOtherCompanies->execute([$speakerId, $linkedCompanyId]);

                        if (in_array('linked_expert_id', $companyColumns, true)) {
                            if ($linkedExpertId !== null && $linkedExpertId > 0) {
                                $updateCompany = $db->prepare("UPDATE {$companiesTable} SET linked_speaker_id = ?, linked_expert_id = COALESCE(linked_expert_id, ?) WHERE id = ?");
                                $updateCompany->execute([$speakerId, $linkedExpertId, $linkedCompanyId]);
                            } else {
                                $updateCompany = $db->prepare("UPDATE {$companiesTable} SET linked_speaker_id = ? WHERE id = ?");
                                $updateCompany->execute([$speakerId, $linkedCompanyId]);
                            }
                        } else {
                            $updateCompany = $db->prepare("UPDATE {$companiesTable} SET linked_speaker_id = ? WHERE id = ?");
                            $updateCompany->execute([$speakerId, $linkedCompanyId]);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logDatabaseWarning('sync_excomp_links_from_speaker', $e);
        }
    }

    /** @return array<int, string> */
    private function getExternalTableColumns(string $table): array
    {
        try {
            $db = CMS\Database::instance();
            $fullTable = $db->prefix() . $table;
            $stmt = $db->prepare("SHOW COLUMNS FROM `{$fullTable}`");
            $stmt->execute([]);
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $field = is_object($row) ? ($row->Field ?? null) : ($row['Field'] ?? null);
                if (is_string($field) && $field !== '') {
                    $columns[] = $field;
                }
            }
            return $columns;
        } catch (Throwable) {
            return [];
        }
    }

    private function ensureSchemaForSave(): void
    {
        try {
            $this->createTables();
        } catch (Throwable $e) {
            $this->logDatabaseWarning('ensure_schema_for_save', $e);
        }
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $data @return array<string, mixed> */
    private function applyUpdateFallbacks(array $payload, array $data, ?object $existing, bool $preserveEmptyValues = false): array
    {
        if ($existing === null) {
            return $payload;
        }

        foreach ($payload as $key => $value) {
            $hasPostedValue = array_key_exists($key, $data);
            $existingValue = property_exists($existing, $key) ? $existing->{$key} : null;

            if ($hasPostedValue && (!$preserveEmptyValues || !$this->isEmptySubmittedValue($value) || $this->isEmptySubmittedValue($existingValue))) {
                continue;
            }

            if (property_exists($existing, $key)) {
                $payload[$key] = $existingValue;
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $data @param array<int, string> $keys */
    private function looksLikeSparseUpdate(array $data, ?object $existing, array $keys): bool
    {
        if ($existing === null) {
            return false;
        }

        $existingFilled = 0;
        $postedFilled = 0;
        foreach ($keys as $key) {
            if (property_exists($existing, $key) && !$this->isEmptySubmittedValue($existing->{$key})) {
                $existingFilled++;
            }
            if (array_key_exists($key, $data) && !$this->isEmptySubmittedValue($data[$key])) {
                $postedFilled++;
            }
        }

        return $existingFilled >= 3 && $postedFilled <= 1;
    }

    private function isEmptySubmittedValue(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function filterPayloadByExistingColumns(string $table, array $payload): array
    {
        $columns = $this->getExistingColumns($table);
        if ($columns === []) {
            return $payload;
        }

        $missingColumns = array_values(array_diff(array_keys($payload), $columns));
        if ($missingColumns !== []) {
            $definitions = $table === '365net_events'
                ? $this->eventMetaColumnDefinitions()
                : ($table === '365net_event_speakers' ? $this->speakerMetaColumnDefinitions() : []);

            foreach ($missingColumns as $column) {
                if (!isset($definitions[$column])) {
                    continue;
                }

                try {
                    $this->ensureColumn($table, $column, $definitions[$column]);
                } catch (Throwable $e) {
                    $this->logDatabaseWarning('ensure_payload_column_' . $table . '_' . $column, $e);
                }
            }

            $columns = $this->getExistingColumns($table);
        }

        return array_intersect_key($payload, array_flip($columns));
    }

    /**
     * @param array<int, string> $payloadKeysBeforeFilter
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $submittedData
     */
    private function logDroppedPayloadKeys(string $table, array $payloadKeysBeforeFilter, array $payload, array $submittedData): void
    {
        $payloadKeysAfterFilter = array_keys($payload);
        $dropped = array_values(array_diff($payloadKeysBeforeFilter, $payloadKeysAfterFilter));
        if ($dropped === []) {
            return;
        }

        $criticalDropped = [];
        foreach ($dropped as $key) {
            if (!array_key_exists($key, $submittedData)) {
                continue;
            }

            $raw = $submittedData[$key];
            if (is_array($raw)) {
                $nonEmptyItems = array_filter(
                    array_map(static fn(mixed $item): string => trim((string) $item), $raw),
                    static fn(string $item): bool => $item !== ''
                );
                if ($nonEmptyItems === []) {
                    continue;
                }
            } elseif (trim((string) $raw) === '') {
                continue;
            }

            $criticalDropped[] = $key;
        }

        $message = 'Dropped payload columns in ' . $table . ': ' . implode(', ', $dropped);
        if ($criticalDropped !== []) {
            $message .= ' | non-empty submitted: ' . implode(', ', $criticalDropped);
        }

        $this->logDatabaseWarning('payload_columns_dropped_' . $table, new RuntimeException($message));
    }

    /** @return array<int, string> */
    private function getExistingColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        $allowedTables = ['365net_events', '365net_event_speakers'];
        if (!in_array($table, $allowedTables, true)) {
            return [];
        }

        try {
            $db = CMS\Database::instance();
            $fullTable = $db->prefix() . $table;
            $stmt = $db->prepare("SHOW COLUMNS FROM `{$fullTable}`");
            $stmt->execute([]);
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $field = is_object($row) ? ($row->Field ?? null) : ($row['Field'] ?? null);
                if (is_string($field) && $field !== '') {
                    $columns[] = $field;
                }
            }
            $this->tableColumnsCache[$table] = $columns;
            return $columns;
        } catch (Throwable $e) {
            $this->logDatabaseWarning('get_existing_columns_' . $table, $e);
            return [];
        }
    }

    private function logDatabaseWarning(string $context, Throwable $error): void
    {
        if (class_exists('CMS_365NET_Events')) {
            CMS_365NET_Events::instance()->log($context, $error);
            return;
        }

        error_log('CMS 365NET Events DB [' . $context . ']: ' . $error->getMessage());
    }

    /** @return array<string, string> */
    public function getSettings(): array
    {
        $defaults = [
            'show_nav_link' => '0',
            'nav_label' => 'Events',
            'events_header_text_enabled' => '1',
            'events_header_kicker_enabled' => '1',
            'events_header_title_enabled' => '1',
            'events_header_description_enabled' => '1',
            'events_search_placeholder_enabled' => '1',
            'archive_title' => 'Events & Messen 2026',
            'archive_description' => 'Kuratiertes Event- und Speaker-Verzeichnis für IT, Cloud, Security, AI und digitale Transformation.',
            'archive_kicker' => '365NET Event Directory',
            'events_header_btn_1_text' => '',
            'events_header_btn_1_url' => '',
            'events_header_btn_2_text' => '',
            'events_header_btn_2_url' => '',
            'events_header_btn_3_text' => '',
            'events_header_btn_3_url' => '',
            'archive_search_placeholder' => 'Event, Ort, Thema oder Veranstalter suchen …',
            'archive_search_button' => 'Suchen',
            'archive_reset_label' => 'Zurücksetzen',
            'archive_current_month_label' => 'Zukünftige Events',
            'archive_past_button' => 'Vergangene Events anzeigen',
            'archive_current_button' => 'Zurück zu zukünftigen Events',
            'archive_empty_current' => 'Es wurden keine zukünftigen Events gefunden.',
            'archive_empty_past' => 'Keine vergangenen Events gefunden.',
            'speakers_header_text_enabled' => '1',
            'speakers_header_kicker_enabled' => '1',
            'speakers_header_title_enabled' => '1',
            'speakers_header_description_enabled' => '1',
            'speakers_search_placeholder_enabled' => '1',
            'speaker_archive_title' => 'Event-Speaker',
            'speaker_archive_kicker' => '365NET Speaker Directory',
            'speaker_archive_description' => 'Personen, Expertengruppen und Organisationen aus dem Event-Datensatz.',
            'speakers_header_btn_1_text' => '',
            'speakers_header_btn_1_url' => '',
            'speakers_header_btn_2_text' => '',
            'speakers_header_btn_2_url' => '',
            'speakers_header_btn_3_text' => '',
            'speakers_header_btn_3_url' => '',
            'speaker_search_placeholder' => 'Speaker, Thema oder Tag suchen …',
            'detail_back_events_label' => 'Events',
            'detail_speakers_heading' => 'Speaker & Themen',
            'detail_no_speakers_text' => 'Für dieses Event sind noch keine Speaker verknüpft.',
            'detail_register_label' => 'Registrieren',
            'detail_website_label' => 'Website öffnen',
            'layout_primary_color' => '#1d4ed8',
            'layout_accent_color' => '#f59e0b',
            'layout_event_card_top_border_color' => '#f59e0b',
            'layout_speaker_card_top_border_color' => '#8b5cf6',
            'layout_text_color' => '#0f172a',
            'layout_card_background' => '#ffffff',
            'layout_card_border' => '#e2e8f0',
            'layout_radius' => '24',
            'layout_card_radius' => '20',
            'layout_gap' => '18',
            'layout_gap_y' => '18',
            'layout_top_spacing' => '32',
            'layout_bottom_spacing' => '56',
            'layout_container_width' => '1160',
            'taxonomy_event_categories' => "KI & Copilot\nMicrosoft 365\nAzure\nSecurity\nModern Workplace\nBusiness Applications\nEntwicklung\nCommunity\nMesse\nKonferenz\nWebinar\nWorkshop",
            'taxonomy_event_types' => "Konferenz\nKonferenz & Messe\nKongress\nKongress & Messe\nFestival\nMesse\nWebinar\nWorkshop\nKonferenz & Workshops\nKongress & Workshops\nMeetup\nHackathon\nTraining\nRoundtable\nCommunity Event\nNetworking\nMasterclass",
            'taxonomy_event_price_classes' => "Kostenfrei\nKostenpflichtig\nAuf Einladung\nAuf Anfrage\nSponsorenfinanziert",
            'taxonomy_event_tags' => "Microsoft 365\nCopilot\nAzure\nSecurity\nAI\nPower Platform\nTeams\nSharePoint\nEntra ID\nIntune\nWindows\nGovernance\nCompliance\nAutomation\nCommunity",
            'taxonomy_speaker_categories' => "MVP\nCommunity Speaker\nConsultant\nTrainer\nVendor\nModerator\nPanelist\nExpertengruppe\nOrganisation",
            'taxonomy_speaker_types' => "MVP\nCommunity Speaker\nConsultant\nTrainer\nVendor\nModerator\nPanelist\nKeynote Speaker\nWorkshop Lead\nOrganisation",
            'taxonomy_speaker_price_classes' => "Kostenfrei\nKostenpflichtig\nAuf Einladung\nAuf Anfrage\nPro-bono",
            'taxonomy_speaker_tags' => "Microsoft 365\nCopilot\nAzure\nSecurity\nAI\nPower Platform\nTeams\nSharePoint\nLeadership\nGovernance\nDeveloper\nAdmin\nConsulting\nTraining",
        ];

        $db = CMS\Database::instance();
        try {
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM {$db->prefix()}365net_event_settings");
            $stmt->execute([]);
            foreach ($stmt->fetchAll() as $row) {
                $defaults[(string) $row->setting_key] = (string) $row->setting_value;
            }
        } catch (Throwable) {
        }

        $defaults['taxonomy_event_types'] = $this->ensureOptionListContains(
            (string) ($defaults['taxonomy_event_types'] ?? ''),
            ['Konferenz & Messe', 'Konferenz & Workshops', 'Kongress', 'Kongress & Messe', 'Kongress & Workshops', 'Festival']
        );

        return $defaults;
    }

    public function saveSetting(string $key, string $value): void
    {
        $db = CMS\Database::instance();
        $stmt = $db->prepare("INSERT INTO {$db->prefix()}365net_event_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$this->cleanText($key, 120), $value]);
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(array $data): void
    {
        $allowed = array_keys($this->getSettings());
        $booleanKeys = [
            'show_nav_link',
            'events_header_text_enabled',
            'events_header_kicker_enabled',
            'events_header_title_enabled',
            'events_header_description_enabled',
            'events_search_placeholder_enabled',
            'speakers_header_text_enabled',
            'speakers_header_kicker_enabled',
            'speakers_header_title_enabled',
            'speakers_header_description_enabled',
            'speakers_search_placeholder_enabled',
        ];
        foreach ($allowed as $key) {
            if (str_starts_with($key, 'taxonomy_')) {
                continue;
            }
            $value = (string) ($data[$key] ?? '');
            if (in_array($key, $booleanKeys, true)) {
                $value = !empty($data[$key]) ? '1' : '0';
            } elseif (str_starts_with($key, 'layout_')) {
                $value = $this->cleanLayoutSetting($key, $value);
            } else {
                $value = $this->cleanTextarea($value, 1000);
            }
            $this->saveSetting($key, $value);
        }
    }

    /** @return array<string, array<int, string>> */
    public function getTaxonomyOptions(): array
    {
        $settings = $this->getSettings();
        $keys = [
            'event_categories' => 'taxonomy_event_categories',
            'event_types' => 'taxonomy_event_types',
            'event_price_classes' => 'taxonomy_event_price_classes',
            'event_tags' => 'taxonomy_event_tags',
            'speaker_categories' => 'taxonomy_speaker_categories',
            'speaker_types' => 'taxonomy_speaker_types',
            'speaker_price_classes' => 'taxonomy_speaker_price_classes',
            'speaker_tags' => 'taxonomy_speaker_tags',
        ];
        $options = [];
        foreach ($keys as $name => $settingKey) {
            $options[$name] = $this->splitOptionList((string) ($settings[$settingKey] ?? ''));
        }

        // Preislogik bewusst vereinfacht: keine Unterteilung kostenpflichtiger Kategorien.
        $options['event_price_classes'] = [
            'Kostenfrei',
            'Kostenpflichtig',
            'Auf Einladung',
            'Auf Anfrage',
            'Sponsorenfinanziert',
        ];
        $options['speaker_price_classes'] = [
            'Kostenfrei',
            'Kostenpflichtig',
            'Auf Einladung',
            'Auf Anfrage',
            'Pro-bono',
        ];

        return $options;
    }

    /** @param array<string, mixed> $data */
    public function saveTaxonomyOptions(array $data): void
    {
        foreach (array_keys($this->getTaxonomyOptions()) as $name) {
            $key = 'taxonomy_' . $name;
            $this->saveSetting($key, implode("\n", $this->splitOptionList((string) ($data[$key] ?? ''))));
        }
    }

    /** @return array<int, string> */
    private function splitOptionList(string $value): array
    {
        $items = preg_split('/[,;\n]+/', $value) ?: [];
        $clean = [];
        foreach ($items as $item) {
            $item = $this->cleanText((string) $item, 120);
            if ($item !== '' && !in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /** @param array<int, string> $requiredOptions */
    private function ensureOptionListContains(string $value, array $requiredOptions): string
    {
        $options = $this->splitOptionList($value);
        foreach ($requiredOptions as $requiredOption) {
            $requiredOption = $this->cleanText((string) $requiredOption, 120);
            if ($requiredOption !== '' && !in_array($requiredOption, $options, true)) {
                $options[] = $requiredOption;
            }
        }

        return implode("\n", $options);
    }

    private function cleanLayoutSetting(string $key, string $value): string
    {
        $value = trim($value);
        if (str_contains($key, 'color') || str_contains($key, 'background') || str_contains($key, 'border')) {
            return preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? $value : '#ffffff';
        }

        $number = (int) $value;
        return (string) max(0, min(1800, $number));
    }

    private function uniqueSlug(string $value, string $table = '365net_events', int $ignoreId = 0): string
    {
        $base = $this->slugify($value) ?: 'eintrag';
        $slug = $base;
        $db = CMS\Database::instance();
        $i = 2;

        while ($this->slugExists($table, $slug, $ignoreId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $table, string $slug, int $ignoreId): bool
    {
        $db = CMS\Database::instance();
        $sql = "SELECT COUNT(*) FROM {$db->prefix()}{$table} WHERE slug = ?" . ($ignoreId > 0 ? ' AND id <> ?' : '');
        $params = $ignoreId > 0 ? [$slug, $ignoreId] : [$slug];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function slugify(string $value): string
    {
        $value = $this->lower($value);
        $value = str_replace(['ä','ö','ü','ß','à','á','â','ã','å','è','é','ê','ë','ì','í','î','ï','ò','ó','ô','õ','ø','ù','ú','û','ý','ÿ','ñ','ç'], ['ae','oe','ue','ss','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','y','y','n','c'], $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function parseGermanDate(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? $m[3] . '-' . $m[2] . '-' . $m[1] : null;
        }

        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        return $this->parseGermanDate($value);
    }

    private function backfillSeedEventDescriptions(): void
    {
        $this->backfillSeedEventDescriptionsInternal();
    }

    public function backfillSeedEventDescriptionsFromSeed(): int
    {
        return $this->backfillSeedEventDescriptionsInternal();
    }

    /** @return array{configured:bool,processed:int,updated:int,skipped:int,failed:int} */
    public function backfillSpeakerProfilesFromGoogle(): array
    {
        $result = [
            'configured' => false,
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if (!$this->tableExists('365net_event_speakers')) {
            return $result;
        }

        $config = $this->googleSearchConfig();
        if (!$config['configured']) {
            return $result;
        }
        $result['configured'] = true;

        try {
            $db = CMS\Database::instance();
            $p = $db->prefix();
            $select = $db->prepare("SELECT id, first_name, last_name, display_name, company, topic, website, bio, seo_description, linkedin_url, x_url, youtube_url, github_url
                FROM {$p}365net_event_speakers
                WHERE status = ?
                ORDER BY id ASC
                LIMIT 500");
            $select->execute(['published']);
            $speakers = $select->fetchAll();

            if (!is_array($speakers) || $speakers === []) {
                return $result;
            }

            $update = $db->prepare("UPDATE {$p}365net_event_speakers
                SET website = ?, bio = ?, seo_description = ?, linkedin_url = ?, x_url = ?, youtube_url = ?, github_url = ?
                WHERE id = ?");

            foreach ($speakers as $speaker) {
                if (!is_object($speaker)) {
                    continue;
                }

                $result['processed']++;

                try {
                    $query = $this->buildSpeakerGoogleQuery($speaker);
                    if ($query === '') {
                        $result['skipped']++;
                        continue;
                    }

                    $items = $this->fetchGoogleSearchItems($query, (string) $config['apiKey'], (string) $config['cseId']);
                    if ($items === []) {
                        $result['skipped']++;
                        continue;
                    }

                    $payload = $this->enrichSpeakerFromSearchResults($speaker, $items);
                    if ($payload === null) {
                        $result['skipped']++;
                        continue;
                    }

                    $update->execute([
                        $payload['website'],
                        $payload['bio'],
                        $payload['seo_description'],
                        $payload['linkedin_url'],
                        $payload['x_url'],
                        $payload['youtube_url'],
                        $payload['github_url'],
                        (int) ($speaker->id ?? 0),
                    ]);

                    if ($update->rowCount() > 0) {
                        $result['updated']++;
                    } else {
                        $result['skipped']++;
                    }
                } catch (Throwable $e) {
                    $result['failed']++;
                    $this->logDatabaseWarning('speaker_google_backfill_' . (int) ($speaker->id ?? 0), $e);
                }
            }
        } catch (Throwable $e) {
            $this->logDatabaseWarning('speaker_google_backfill', $e);
        }

        return $result;
    }

    private function backfillSeedEventDescriptionsInternal(): int
    {
        $seedFile = CMS_365NET_EVENTS_PLUGIN_DIR . 'defaults/seed-data.php';
        if (!is_file($seedFile) || !$this->tableExists('365net_events')) {
            return 0;
        }

        try {
            /** @var array<int, array<string, string>> $rows */
            $rows = require $seedFile;
            $seedBySourceNr = [];
            $seedByUniqueId = [];
            $seedByFingerprint = [];
            $seedByLooseFingerprint = [];
            $seedByLooseTitle = [];
            foreach ($rows as $row) {
                $sourceNr = (int) ($row['nr'] ?? 0);
                if ($sourceNr <= 0 || isset($seedBySourceNr[$sourceNr])) {
                    continue;
                }
                $seedBySourceNr[$sourceNr] = $row;
                $seedByUniqueId['event-' . $sourceNr] = $row;

                $title = $this->cleanText((string) ($row['event_name'] ?? ''), 255);
                $start = $this->parseGermanDate((string) ($row['wann'] ?? ''));
                $location = $this->cleanText((string) ($row['ort'] ?? ''), 255);
                $fingerprint = $this->seedEventFingerprint($title, $start, $location);
                if ($fingerprint !== '' && !isset($seedByFingerprint[$fingerprint])) {
                    $seedByFingerprint[$fingerprint] = $row;
                }

                $looseFingerprint = $this->seedEventLooseFingerprint($title, $start, $location);
                if ($looseFingerprint !== '' && !isset($seedByLooseFingerprint[$looseFingerprint])) {
                    $seedByLooseFingerprint[$looseFingerprint] = $row;
                }

                $looseTitle = $this->normalizeLooseMatchValue($title);
                if ($looseTitle !== '') {
                    if (!array_key_exists($looseTitle, $seedByLooseTitle)) {
                        $seedByLooseTitle[$looseTitle] = $row;
                    } elseif (is_array($seedByLooseTitle[$looseTitle])) {
                        $existingNr = (int) (($seedByLooseTitle[$looseTitle]['nr'] ?? 0));
                        if ($existingNr !== $sourceNr) {
                            $seedByLooseTitle[$looseTitle] = null;
                        }
                    }
                }
            }

            $db = CMS\Database::instance();
            $p = $db->prefix();
            $eventsStmt = $db->prepare("SELECT * FROM {$p}365net_events ORDER BY id ASC");
            $eventsStmt->execute([]);
            $events = $eventsStmt->fetchAll();
            if (!is_array($events) || $events === []) {
                return 0;
            }

            $updateStmt = $db->prepare("UPDATE {$p}365net_events
                SET description = ?, excerpt = ?, seo_description = ?, source_nr = COALESCE(?, source_nr)
                WHERE id = ?");
            $updated = 0;

            foreach ($events as $event) {
                if (!is_object($event)) {
                    continue;
                }

                $sourceNr = (int) ($event->source_nr ?? 0);
                $seedRow = $seedBySourceNr[$sourceNr] ?? [];

                if ($seedRow === []) {
                    $uniqueId = trim((string) ($event->unique_id ?? ''));
                    if ($uniqueId !== '' && isset($seedByUniqueId[$uniqueId])) {
                        $seedRow = $seedByUniqueId[$uniqueId];
                    }
                }

                if ($seedRow === []) {
                    $fingerprint = $this->seedEventFingerprint(
                        (string) ($event->title ?? ''),
                        $this->normalizeDate((string) ($event->start_date ?? '')),
                        (string) ($event->location ?? '')
                    );
                    if ($fingerprint !== '' && isset($seedByFingerprint[$fingerprint])) {
                        $seedRow = $seedByFingerprint[$fingerprint];
                    }
                }

                if ($seedRow === []) {
                    $looseFingerprint = $this->seedEventLooseFingerprint(
                        (string) ($event->title ?? ''),
                        $this->normalizeDate((string) ($event->start_date ?? '')),
                        (string) ($event->location ?? '')
                    );
                    if ($looseFingerprint !== '' && isset($seedByLooseFingerprint[$looseFingerprint])) {
                        $seedRow = $seedByLooseFingerprint[$looseFingerprint];
                    }
                }

                if ($seedRow === []) {
                    $looseTitle = $this->normalizeLooseMatchValue((string) ($event->title ?? ''));
                    if ($looseTitle !== '' && isset($seedByLooseTitle[$looseTitle]) && is_array($seedByLooseTitle[$looseTitle])) {
                        $seedRow = $seedByLooseTitle[$looseTitle];
                    }
                }

                if ($seedRow === []) {
                    $seedRow = $this->findSeedRowByFuzzyMatch($event, $rows);
                }

                $description = $this->cleanTextarea((string) ($seedRow['description'] ?? ''), 10000);
                $seedSourceNr = (int) ($seedRow['nr'] ?? 0);

                if ($description === '') {
                    continue;
                }

                $updateStmt->execute([
                    $description,
                    $this->seedExcerpt($description),
                    $this->seedSeoDescription($description),
                    $seedSourceNr > 0 ? $seedSourceNr : null,
                    (int) ($event->id ?? 0),
                ]);

                if ($updateStmt->rowCount() > 0) {
                    $updated++;
                }
            }

            return $updated;
        } catch (Throwable $e) {
            $this->logDatabaseWarning('backfill_seed_event_descriptions', $e);
            return 0;
        }
    }

    private function seedEventFingerprint(string $title, ?string $startDate, string $location): string
    {
        $titlePart = $this->normalizeMatchValue($title);
        if ($titlePart === '') {
            return '';
        }

        $datePart = trim((string) $startDate);
        $locationPart = $this->normalizeMatchValue($location);

        return $titlePart . '|' . $datePart . '|' . $locationPart;
    }

    private function seedEventLooseFingerprint(string $title, ?string $startDate, string $location): string
    {
        $titlePart = $this->normalizeLooseMatchValue($title);
        if ($titlePart === '') {
            return '';
        }

        $datePart = trim((string) $startDate);
        $locationPart = $this->normalizeLooseMatchValue($location);

        return $titlePart . '|' . $datePart . '|' . $locationPart;
    }

    /** @param array<int, array<string, string>> $rows @return array<string, string> */
    private function findSeedRowByFuzzyMatch(object $event, array $rows): array
    {
        $eventLooseTitle = $this->normalizeLooseMatchValue((string) ($event->title ?? ''));
        if ($eventLooseTitle === '') {
            return [];
        }

        $eventStartDate = $this->normalizeDate((string) ($event->start_date ?? ''));
        $eventLooseLocation = $this->normalizeLooseMatchValue((string) ($event->location ?? ''));

        $bestRow = [];
        $bestScore = 0;

        foreach ($rows as $row) {
            $seedTitle = $this->normalizeLooseMatchValue((string) ($row['event_name'] ?? ''));
            if ($seedTitle === '' || !$this->isLooseTitleCandidateMatch($seedTitle, $eventLooseTitle)) {
                continue;
            }

            $score = 0;
            if ($seedTitle === $eventLooseTitle) {
                $score += 140;
            } elseif (str_contains($seedTitle, $eventLooseTitle) || str_contains($eventLooseTitle, $seedTitle)) {
                $score += 100;
            }

            $similarity = 0.0;
            similar_text($seedTitle, $eventLooseTitle, $similarity);
            $score += (int) round($similarity);

            $seedStartDate = $this->parseGermanDate((string) ($row['wann'] ?? ''));
            if ($eventStartDate !== null && $eventStartDate !== '' && $seedStartDate !== null && $seedStartDate === $eventStartDate) {
                $score += 45;
            }

            $seedLocation = $this->normalizeLooseMatchValue((string) ($row['ort'] ?? ''));
            if ($eventLooseLocation !== '' && $seedLocation !== '' && $seedLocation === $eventLooseLocation) {
                $score += 25;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row;
            }
        }

        return $bestScore >= 120 ? $bestRow : [];
    }

    private function isLooseTitleCandidateMatch(string $candidate, string $needle): bool
    {
        if ($candidate === '' || $needle === '') {
            return false;
        }

        if ($candidate === $needle) {
            return true;
        }

        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
            return true;
        }

        $similarity = 0.0;
        similar_text($candidate, $needle, $similarity);
        return $similarity >= 82.0;
    }

    /**
     * @return array{configured:bool,apiKey:string,cseId:string}
     */
    private function googleSearchConfig(): array
    {
        $apiKey = $this->readEnvValue([
            'CMS_365NETEVENTS_GOOGLE_API_KEY',
            'GOOGLE_CSE_API_KEY',
            'GOOGLE_API_KEY',
        ]);
        $cseId = $this->readEnvValue([
            'CMS_365NETEVENTS_GOOGLE_CSE_ID',
            'GOOGLE_CSE_ID',
            'GOOGLE_SEARCH_ENGINE_ID',
        ]);

        return [
            'configured' => $apiKey !== '' && $cseId !== '',
            'apiKey' => $apiKey,
            'cseId' => $cseId,
        ];
    }

    /** @param array<int, string> $keys */
    private function readEnvValue(array $keys): string
    {
        $dotenv = $this->loadDotEnv();
        foreach ($keys as $key) {
            $candidates = [
                getenv($key),
                $_ENV[$key] ?? null,
                $_SERVER[$key] ?? null,
                $dotenv[$key] ?? null,
            ];
            foreach ($candidates as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $value = trim($value);
                // Leere Werte und Vorlagen-Platzhalter („__SET_…__“ aus .env.example) zählen nicht.
                if ($value === '' || str_starts_with($value, '__SET_')) {
                    continue;
                }

                return $this->cleanText($value, 400);
            }
        }

        return '';
    }

    /** @return array<string, string> */
    private function loadDotEnv(): array
    {
        if ($this->dotEnvCache !== null) {
            return $this->dotEnvCache;
        }

        $this->dotEnvCache = [];
        $paths = [
            CMS_365NET_EVENTS_PLUGIN_DIR . '.env',
            dirname((string) CMS_365NET_EVENTS_PLUGIN_DIR) . DIRECTORY_SEPARATOR . '.env',
        ];
        // 365CMS-Stammverzeichnis: .env ist dort per Core-.htaccess gesperrt.
        if (defined('ABSPATH')) {
            $paths[] = rtrim((string) ABSPATH, '/\\') . DIRECTORY_SEPARATOR . '.env';
        }

        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_starts_with($line, 'export ')) {
                    $line = trim(substr($line, 7));
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim((string) $k);
                $v = trim((string) $v);
                if ($k === '') {
                    continue;
                }
                if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                    $v = substr($v, 1, -1);
                }
                if ($v === '' || str_starts_with($v, '__SET_')) {
                    continue;
                }

                $this->dotEnvCache[$k] = $v;
            }
        }

        return $this->dotEnvCache;
    }

    private function buildSpeakerGoogleQuery(object $speaker): string
    {
        $displayName = $this->cleanText((string) ($speaker->display_name ?? ''), 255);
        if ($displayName === '') {
            $displayName = trim(
                $this->cleanText((string) ($speaker->first_name ?? ''), 120)
                . ' '
                . $this->cleanText((string) ($speaker->last_name ?? ''), 120)
            );
        }

        if ($displayName === '') {
            return '';
        }

        $parts = [$displayName];
        $company = $this->cleanText((string) ($speaker->company ?? ''), 120);
        $topic = $this->cleanText((string) ($speaker->topic ?? ''), 120);
        if ($company !== '') {
            $parts[] = $company;
        }
        if ($topic !== '') {
            $parts[] = $topic;
        }
        $parts[] = 'Speaker Profil LinkedIn';

        return trim(implode(' ', $parts));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGoogleSearchItems(string $query, string $apiKey, string $cseId): array
    {
        if (!class_exists('CMS\\Http\\Client')) {
            return [];
        }

        $url = 'https://customsearch.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => $apiKey,
            'cx' => $cseId,
            'q' => $query,
            'num' => 8,
            'hl' => 'de',
            'gl' => 'de',
            'safe' => 'off',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = \CMS\Http\Client::getInstance()->get($url, [
            'timeout' => 12,
            'connectTimeout' => 6,
            'maxBytes' => 512000,
            'allowedContentTypes' => ['application/json'],
        ]);

        if (($response['success'] ?? false) !== true) {
            return [];
        }

        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            return [];
        }

        return array_values(array_filter($decoded['items'], static fn(mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, string|null>|null
     */
    private function enrichSpeakerFromSearchResults(object $speaker, array $items): ?array
    {
        $displayName = $this->cleanText((string) ($speaker->display_name ?? ''), 255);
        $company = $this->cleanText((string) ($speaker->company ?? ''), 255);
        $topic = $this->cleanText((string) ($speaker->topic ?? ''), 255);
        $nameNeedle = $this->lower($displayName);

        $website = $this->cleanUrl((string) ($speaker->website ?? ''));
        $linkedin = $this->cleanUrl((string) ($speaker->linkedin_url ?? ''));
        $x = $this->cleanUrl((string) ($speaker->x_url ?? ''));
        $youtube = $this->cleanUrl((string) ($speaker->youtube_url ?? ''));
        $github = $this->cleanUrl((string) ($speaker->github_url ?? ''));
        $snippets = [];

        foreach ($items as $item) {
            $link = $this->cleanUrl((string) ($item['link'] ?? ''));
            $title = $this->cleanText((string) ($item['title'] ?? ''), 220);
            $snippet = $this->cleanText((string) ($item['snippet'] ?? ''), 420);
            $haystack = $this->lower(trim($title . ' ' . $snippet));

            if ($snippet !== '' && ($nameNeedle === '' || str_contains($haystack, $nameNeedle))) {
                $snippets[] = $snippet;
            }

            if ($link === null) {
                continue;
            }

            $socialField = $this->detectSocialPlatform($link);
            if ($socialField === 'linkedin_url' && $linkedin === null) {
                $linkedin = $link;
                continue;
            }
            if ($socialField === 'x_url' && $x === null) {
                $x = $link;
                continue;
            }
            if ($socialField === 'youtube_url' && $youtube === null) {
                $youtube = $link;
                continue;
            }
            if ($socialField === 'github_url' && $github === null) {
                $github = $link;
                continue;
            }

            if ($socialField === null && $website === null && $this->isLikelySpeakerWebsite($link, $title, $snippet, $displayName, $company)) {
                $website = $link;
            }
        }

        $snippets = array_values(array_unique(array_filter(array_map(static fn(string $s): string => trim($s), $snippets))));
        if ($snippets === []) {
            return null;
        }

        $maxSnippets = array_slice($snippets, 0, 3);
        $context = $topic !== '' ? $topic : 'IT-, Cloud- und Digitalisierungsthemen';
        $bioParts = [];
        $bioParts[] = $displayName !== ''
            ? $displayName . ' wird in öffentlich auffindbaren Google-Suchergebnissen im Kontext von ' . $context . ' erwähnt.'
            : 'Die Person wird in öffentlich auffindbaren Google-Suchergebnissen im Kontext von ' . $context . ' erwähnt.';
        if ($company !== '') {
            $bioParts[] = 'Häufige Zuordnung: ' . $company . '.';
        }
        $bioParts[] = 'Ausgewertete Trefferhinweise: ' . implode(' ', array_map(static fn(string $snippet): string => '„' . $snippet . '“', $maxSnippets));
        $bio = $this->cleanTextarea(implode("\n\n", $bioParts), 2500);
        if ($bio === '') {
            return null;
        }

        $currentBio = $this->cleanTextarea((string) ($speaker->bio ?? ''), 2500);
        if ($currentBio === $bio
            && $website === $this->cleanUrl((string) ($speaker->website ?? ''))
            && $linkedin === $this->cleanUrl((string) ($speaker->linkedin_url ?? ''))
            && $x === $this->cleanUrl((string) ($speaker->x_url ?? ''))
            && $youtube === $this->cleanUrl((string) ($speaker->youtube_url ?? ''))
            && $github === $this->cleanUrl((string) ($speaker->github_url ?? ''))
        ) {
            return null;
        }

        return [
            'bio' => $bio,
            'seo_description' => $this->seedSeoDescription($bio),
            'website' => $website,
            'linkedin_url' => $linkedin,
            'x_url' => $x,
            'youtube_url' => $youtube,
            'github_url' => $github,
        ];
    }

    private function detectSocialPlatform(string $url): ?string
    {
        if (preg_match('#^https?://(?:[a-z]{2,3}\.)?linkedin\.com/(?:in|pub|company)/#i', $url) === 1) {
            return 'linkedin_url';
        }
        if (preg_match('#^https?://(?:www\.)?(?:x\.com|twitter\.com)/[A-Za-z0-9_]+#i', $url) === 1) {
            return 'x_url';
        }
        if (preg_match('#^https?://(?:www\.)?youtube\.com/(?:@|channel/|c/|user/)#i', $url) === 1) {
            return 'youtube_url';
        }
        if (preg_match('#^https?://(?:www\.)?github\.com/[A-Za-z0-9_.\-]+#i', $url) === 1) {
            return 'github_url';
        }

        return null;
    }

    private function isLikelySpeakerWebsite(string $url, string $title, string $snippet, string $displayName, string $company): bool
    {
        $host = $this->extractDomain($url);
        if ($host === '') {
            return false;
        }

        $blockedHosts = [
            'linkedin.com',
            'x.com',
            'twitter.com',
            'youtube.com',
            'github.com',
            'facebook.com',
            'instagram.com',
            'tiktok.com',
            'wikipedia.org',
        ];
        foreach ($blockedHosts as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.' . $blockedHost)) {
                return false;
            }
        }

        $needle = $this->lower(trim($displayName));
        $companyNeedle = $this->lower(trim($company));
        $haystack = $this->lower(trim($title . ' ' . $snippet . ' ' . $host));

        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }

        if ($companyNeedle !== '' && str_contains($haystack, $companyNeedle)) {
            return true;
        }

        return false;
    }

    private function seedExcerpt(string $description): ?string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description)));
        if ($plain === '') {
            return null;
        }

        return $this->cleanText($plain, 420);
    }

    private function seedSeoDescription(string $description): ?string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description)));
        if ($plain === '') {
            return null;
        }

        return $this->cleanText($plain, 300);
    }

    private function seedBio(string $topic, string $award, string $company): ?string
    {
        $parts = [];
        if ($company !== '') {
            $parts[] = 'Organisation/Firma: ' . $company;
        }
        if ($topic !== '') {
            $parts[] = 'Schwerpunkt: ' . $topic;
        }
        if ($award !== '') {
            $parts[] = 'Auszeichnung: ' . $award;
        }

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        return function_exists('mb_substr') ? (string) mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    private function cleanTextarea(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value, '<p><br><strong><b><em><i><ul><ol><li><a>'));
        return function_exists('mb_substr') ? (string) mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    private function cleanList(mixed $value, int $maxLength): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(static fn(mixed $item): string => (string) $item, $value));
        }
        $items = preg_split('/[,;\n]+/', (string) $value) ?: [];
        $clean = [];
        foreach ($items as $item) {
            $item = $this->cleanText((string) $item, 80);
            if ($item !== '' && !in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        return $this->cleanText(implode(', ', $clean), $maxLength);
    }

    private function cleanEditorJson(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            $decoded = ['time' => time() * 1000, 'blocks' => []];
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: null;
    }

    private function editorJsonToFallbackText(string $json, int $maxLength): string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return '';
        }

        $allowedTextKeys = ['text', 'content', 'caption', 'title', 'message', 'quote', 'code', 'html', 'description'];
        $ignoredExactValues = ['left', 'right', 'center', 'justify', 'normal', 'small', 'medium', 'large', 'ordered', 'unordered', 'checklist', 'info', 'success', 'warning', 'danger'];
        $chunks = [];
        $appendChunk = static function (string $value) use (&$chunks, $ignoredExactValues): void {
            $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($value !== '') {
                $normalized = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
                if (in_array($normalized, $ignoredExactValues, true)) {
                    return;
                }
                if (preg_match('/^(left|right|center|justify)(\s+(normal|small|medium|large))*$/iu', $normalized) === 1) {
                    return;
                }
                $chunks[] = $value;
            }
        };

        $collect = null;
        $collect = static function (mixed $value, ?string $currentKey = null) use (&$collect, $appendChunk, $allowedTextKeys): void {
            if (is_string($value)) {
                if ($currentKey !== null && in_array($currentKey, $allowedTextKeys, true)) {
                    $appendChunk($value);
                }
                return;
            }
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $key => $item) {
                $nextKey = is_string($key) ? strtolower(trim($key)) : null;
                if (is_string($item) || is_array($item)) {
                    $collect($item, $nextKey);
                }
            }
        };

        foreach ($decoded['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $data = $block['data'] ?? null;
            if (!is_array($data)) {
                continue;
            }

            $collect($data, null);
        }

        if ($chunks === []) {
            return '';
        }

        $deduplicated = [];
        foreach ($chunks as $chunk) {
            if ($deduplicated === [] || end($deduplicated) !== $chunk) {
                $deduplicated[] = $chunk;
            }
        }

        return $this->cleanTextarea(implode("\n\n", $deduplicated), $maxLength);
    }

    private function cleanJsonList(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/[,;\n]+/', $value) ?: [];
        }

        $items = [];
        foreach ($decoded as $item) {
            $url = is_array($item) ? (string) ($item['url'] ?? $item['file']['url'] ?? '') : (string) $item;
            $url = $this->cleanUrl($url);
            if ($url !== null) {
                $items[] = ['url' => $url];
            }
        }

        return $items !== [] ? json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    private function cleanDecimal(mixed $value): ?string
    {
        $raw = str_replace(',', '.', trim((string) $value));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return number_format(max(0.0, (float) $raw), 2, '.', '');
    }

    private function cleanPositiveInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function cleanEmail(string $value): ?string
    {
        $email = trim($value);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 180) : null;
    }

    private function cleanHexColor(string $value, ?string $fallback = null): ?string
    {
        $normalized = $this->normalizeColorToHex($value);
        if ($normalized !== null) {
            return $normalized;
        }

        if ($fallback !== null) {
            $fallbackNormalized = $this->normalizeColorToHex((string) $fallback);
            if ($fallbackNormalized !== null) {
                return $fallbackNormalized;
            }
        }

        return null;
    }

    private function normalizeColorToHex(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^#?[0-9a-f]{6}$/', $normalized) === 1) {
            return '#' . ltrim($normalized, '#');
        }

        if (preg_match('/^#?[0-9a-f]{3}$/', $normalized) === 1) {
            $short = ltrim($normalized, '#');
            return '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2];
        }

        if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(?:0|0?\.\d+|1(?:\.0+)?))?\s*\)$/i', $normalized, $matches) === 1) {
            $r = (int) ($matches[1] ?? 0);
            $g = (int) ($matches[2] ?? 0);
            $b = (int) ($matches[3] ?? 0);
            if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
                return null;
            }

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        return null;
    }

    private function cleanUrl(string $value): ?string
    {
        $url = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = str_replace('\\', '/', $url);

        if (preg_match('#^(?:media-file\?|uploads/)#i', $url) === 1) {
            $url = '/' . ltrim($url, '/');
        }

        if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]<>"\']/', $url) === 1) {
            return null;
        }

        if (preg_match('#^(?:/|\./|\.\./)[^\s]*$#', $url) === 1 && !str_starts_with($url, '//')) {
            return $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || ($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            return null;
        }

        return $url;
    }

    private function cleanMediaUrl(string $value, ?string $existingValue = null): ?string
    {
        $raw = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($raw === '') {
            return null;
        }

        $normalized = $this->normalizeMediaInputUrl($raw);
        $clean = $this->cleanUrl($normalized);
        if ($clean !== null) {
            return $clean;
        }

        $noSpaces = preg_replace('/\s+/u', '%20', $normalized) ?? $normalized;
        $clean = $this->cleanUrl($noSpaces);
        if ($clean !== null) {
            return $clean;
        }

        $fallback = $existingValue !== null ? $this->cleanUrl((string) $existingValue) : null;
        return $fallback;
    }

    private function normalizeMediaInputUrl(string $value): string
    {
        $url = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = str_replace('\\', '/', $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            $parts = parse_url($url);
            if (is_array($parts)) {
                $path = (string) ($parts['path'] ?? '');
                $query = (string) ($parts['query'] ?? '');
                if (str_starts_with($path, '/uploads/')) {
                    return $path . ($query !== '' ? '?' . $query : '');
                }

                if ($path === '/media-file') {
                    parse_str($query, $params);
                    $mediaPath = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
                    if ($mediaPath !== '') {
                        return '/uploads/' . $mediaPath;
                    }
                }
            }
        }

        if (preg_match('#^media-file(?:\?|$)#i', $url) === 1 || preg_match('#^(?:uploads|media)(?:/|$)#i', $url) === 1) {
            $url = '/' . ltrim($url, '/');
        }

        if (preg_match('#^/media-file(?:\?|$)#i', $url) === 1) {
            $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
            parse_str($query, $params);
            $mediaPath = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
            if ($mediaPath !== '') {
                return '/uploads/' . $mediaPath;
            }
        }

        if (str_starts_with($url, './')) {
            $url = '/' . ltrim(substr($url, 2), '/');
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && $url !== '' && $url[0] !== '/') {
            $url = '/' . ltrim($url, '/');
        }

        return preg_replace('/\s+/u', '%20', $url) ?? $url;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function limit(mixed $value, int $default): int
    {
        $limit = (int) $value;
        return $limit > 0 ? min($limit, self::MAX_LIST_LIMIT) : $default;
    }
}

<?php
/**
 * Admin-UI für 365NET Events & Speaker (Layout-Vertrag 365CMS 4.0.0).
 *
 * Alle Unterseiten laufen über den Core-AdminRouter (/admin/plugins/365netevents/…). Die einheitliche
 * Navigation (Kopfzeile mit KPI-Karten, Plugin-Menü links, Inhalt rechts) setzt der gemeinsame Helfer
 * PhinIT_Plugin_Admin_Nav; die Callbacks hier geben nur den Inhalt der rechten Spalte aus. Formulare
 * posten auf die eigene Unterseite, Schreibaktionen prüfen Admin-Recht und CSRF und leiten mit 303 weiter.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Admin
{
    public const MENU_SLUG = '365netevents';
    public const PAGE_EVENTS = '365netevents-events';
    public const PAGE_SPEAKERS = '365netevents-speakers';
    public const PAGE_TAXONOMIES = '365netevents-taxonomies';
    public const PAGE_SETTINGS = '365netevents-settings';
    public const TITLE = '365CMS | Events & Speaker';
    private const PER_PAGE = 25;

    private static ?self $instance = null;
    private static bool $assetsPrinted = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (!class_exists('CMS\\Hooks')) {
            return;
        }

        CMS\Hooks::addAction('cms_admin_menu', [$this, 'registerAdminMenu'], 10);

        // Einheitliches Plugin-Layout (365CMS 4.0.0): Kopfzeile mit KPI-Karten, links das Plugin-Menü,
        // rechts der Inhalt; die Übersicht liefert adminDashboard().
        if (is_file(__DIR__ . '/phinit-admin-nav.php')) {
            require_once __DIR__ . '/phinit-admin-nav.php';
            \PhinIT_Plugin_Admin_Nav::adopt(self::MENU_SLUG, [
                'icon' => 'ti ti-calendar-event',
                'menu_title' => self::TITLE,
                'title' => self::TITLE,
                'version' => defined('CMS_365NET_EVENTS_VERSION') ? (string) CMS_365NET_EVENTS_VERSION : '',
                'status' => [self::class, 'adminStatus'],
                'description' => 'Events, Messen und Speaker für die öffentlichen Seiten /events und /speakers pflegen.',
                'stats' => [self::class, 'adminStats'],
                'dashboard' => [self::class, 'adminDashboard'],
                'badges' => [self::class, 'adminBadges'],
                'icons' => [
                    self::PAGE_EVENTS => 'ti ti-calendar-event',
                    self::PAGE_SPEAKERS => 'ti ti-microphone',
                    self::PAGE_TAXONOMIES => 'ti ti-tags',
                    self::PAGE_SETTINGS => 'ti ti-settings',
                ],
                'descriptions' => self::sectionDescriptions(),
            ]);
        }
    }

    public function registerAdminMenu(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(self::TITLE, self::TITLE, 'manage_options', self::MENU_SLUG, [self::class, 'pageDashboard'], 'ti ti-calendar-event', 45);
        if (function_exists('add_submenu_page')) {
            add_submenu_page(self::MENU_SLUG, 'Übersicht', 'Übersicht', 'manage_options', self::MENU_SLUG, [self::class, 'pageDashboard']);
            add_submenu_page(self::MENU_SLUG, 'Events', 'Events', 'manage_options', self::PAGE_EVENTS, [self::class, 'pageEvents']);
            add_submenu_page(self::MENU_SLUG, 'Speaker', 'Speaker', 'manage_options', self::PAGE_SPEAKERS, [self::class, 'pageSpeakers']);
            add_submenu_page(self::MENU_SLUG, 'Kategorien & Tags', 'Kategorien & Tags', 'manage_options', self::PAGE_TAXONOMIES, [self::class, 'pageTaxonomies']);
            add_submenu_page(self::MENU_SLUG, 'Einstellungen', 'Einstellungen', 'manage_options', self::PAGE_SETTINGS, [self::class, 'pageSettings']);
        }
    }

    /**
     * Adresse einer Admin-Unterseite (mit Helfer: /admin/plugins/365netevents/365netevents?view=…).
     *
     * @param array<string, scalar> $query
     */
    public static function url(string $page = self::MENU_SLUG, array $query = []): string
    {
        if (class_exists('PhinIT_Plugin_Admin_Nav', false)) {
            return \PhinIT_Plugin_Admin_Nav::url(self::MENU_SLUG, $page, $query);
        }

        $path = '/admin/plugins/' . self::MENU_SLUG . ($page !== '' && $page !== self::MENU_SLUG ? '/' . $page : '');

        return $query !== [] ? $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $path;
    }

    // ---------------------------------------------------------------------------------------------
    // Seiten-Callbacks (AdminRouter)
    // ---------------------------------------------------------------------------------------------

    public static function pageDashboard(): void
    {
        $admin = self::instance();
        if (!$admin->guard()) {
            return;
        }

        $upcoming = [];
        try {
            $upcoming = CMS_365NET_Events_Database::instance()->getEvents(['date_mode' => 'future', 'from' => date('Y-m-d'), 'limit' => 5]);
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('admin.dashboard', $e);
        }

        $admin->renderPage(self::MENU_SLUG, function () use ($admin, $upcoming): void {
            $admin->flash();
            echo class_exists('PhinIT_Plugin_Admin_Nav', false)
                ? \PhinIT_Plugin_Admin_Nav::dashboard(self::MENU_SLUG)
                : \cms_plugin_dashboard(self::adminDashboard());
            $admin->view('dashboard', ['upcomingEvents' => $upcoming]);
        });
    }

    public static function pageEvents(): void
    {
        $admin = self::instance();
        if (!$admin->guard()) {
            return;
        }
        if ($admin->isPost()) {
            $admin->handleEventsPost();
            return;
        }

        $db = CMS_365NET_Events_Database::instance();
        $action = $admin->query('action');

        if ($action === 'export') {
            $admin->exportCsv('events', $admin->eventFilters());
            return;
        }

        if ($action === 'new' || $action === 'edit') {
            $event = null;
            if ($action === 'edit') {
                $event = $db->getEvent((int) ($_GET['id'] ?? 0));
                if ($event === null) {
                    $admin->redirect(self::PAGE_EVENTS, ['error' => 'not_found']);
                }
            }
            $context = [
                'event' => $event,
                'allSpeakers' => $db->getSpeakers(['limit' => 300]),
                'assignedSpeakers' => $event !== null ? $db->getSpeakersForEvent((int) $event->id) : [],
                'companies' => $db->getAvailableCompanies(300),
                'experts' => $db->getAvailableExperts(300),
                'taxonomies' => $db->getTaxonomyOptions(),
                'excompActive' => $db->isExcompActive(),
            ];
            $admin->renderPage(self::PAGE_EVENTS, function () use ($admin, $context): void {
                $admin->view('event-form', $context);
                $admin->renderMediaPickerModal();
            });
            return;
        }

        $filters = $admin->eventFilters();
        $page = $admin->pageNumber();
        $total = $db->countEvents($filters['args']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $events = $db->getEvents($filters['args'] + [
            'limit' => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
            'order' => $filters['time'] === 'past' ? 'date_desc' : '',
        ]);

        $admin->renderPage(self::PAGE_EVENTS, function () use ($admin, $events, $filters, $total, $page, $pages): void {
            $admin->view('events-list', [
                'events' => $events,
                'filters' => $filters,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
            ]);
        });
    }

    public static function pageSpeakers(): void
    {
        $admin = self::instance();
        if (!$admin->guard()) {
            return;
        }
        if ($admin->isPost()) {
            $admin->handleSpeakersPost();
            return;
        }

        $db = CMS_365NET_Events_Database::instance();
        $action = $admin->query('action');

        if ($action === 'export') {
            $admin->exportCsv('speakers', $admin->speakerFilters());
            return;
        }

        if ($action === 'new' || $action === 'edit') {
            $speaker = null;
            if ($action === 'edit') {
                $speaker = $db->getSpeaker((int) ($_GET['id'] ?? 0));
                if ($speaker === null) {
                    $admin->redirect(self::PAGE_SPEAKERS, ['error' => 'not_found']);
                }
            }
            $context = [
                'speaker' => $speaker,
                'companies' => $db->getAvailableCompanies(300),
                'experts' => $db->getAvailableExperts(300),
                'taxonomies' => $db->getTaxonomyOptions(),
                'excompActive' => $db->isExcompActive(),
            ];
            $admin->renderPage(self::PAGE_SPEAKERS, function () use ($admin, $context): void {
                $admin->view('speaker-form', $context);
                $admin->renderMediaPickerModal();
            });
            return;
        }

        $filters = $admin->speakerFilters();
        $page = $admin->pageNumber();
        $total = $db->countSpeakers($filters['args']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $speakers = $db->getSpeakers($filters['args'] + [
            'order' => $filters['sort'],
            'limit' => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
        ]);

        $admin->renderPage(self::PAGE_SPEAKERS, function () use ($admin, $speakers, $filters, $total, $page, $pages): void {
            $admin->view('speakers-list', [
                'speakers' => $speakers,
                'filters' => $filters,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
            ]);
        });
    }

    public static function pageTaxonomies(): void
    {
        $admin = self::instance();
        if (!$admin->guard()) {
            return;
        }
        $db = CMS_365NET_Events_Database::instance();

        if ($admin->isPost()) {
            if (!$admin->verifyCsrf('365net_taxonomies_form')) {
                $admin->redirect(self::PAGE_TAXONOMIES, ['error' => 'csrf']);
            }
            try {
                $db->saveTaxonomyOptions($_POST);
                $admin->redirect(self::PAGE_TAXONOMIES, ['saved' => 1]);
            } catch (\Throwable $e) {
                CMS_365NET_Events::instance()->log('adminTaxonomiesSave', $e);
                $admin->redirect(self::PAGE_TAXONOMIES, ['error' => 'save']);
            }
            return;
        }

        $settings = $db->getSettings();
        $admin->renderPage(self::PAGE_TAXONOMIES, function () use ($admin, $settings): void {
            $admin->view('taxonomies', ['settings' => $settings]);
        });
    }

    public static function pageSettings(): void
    {
        $admin = self::instance();
        if (!$admin->guard()) {
            return;
        }
        if ($admin->isPost()) {
            $admin->handleSettingsPost();
            return;
        }

        $db = CMS_365NET_Events_Database::instance();
        $context = [
            'settings' => $db->getSettings(),
            'google' => $db->googleCredentialStatus(),
            'googleConfigured' => $db->isGoogleSearchConfigured(),
        ];
        $admin->renderPage(self::PAGE_SETTINGS, function () use ($admin, $context): void {
            $admin->view('settings', $context);
        });
    }

    // ---------------------------------------------------------------------------------------------
    // Schreibaktionen
    // ---------------------------------------------------------------------------------------------

    private function handleEventsPost(): void
    {
        $db = CMS_365NET_Events_Database::instance();
        $operation = $this->postOperation();

        if ($operation === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $back = $id > 0 ? ['action' => 'edit', 'id' => $id] : ['action' => 'new'];
            if (!$this->verifyCsrf('365net_event_form')) {
                $this->redirect(self::PAGE_EVENTS, $back + ['error' => 'csrf']);
            }
            try {
                $savedId = $db->saveEvent($_POST);
                if ($savedId === false) {
                    $this->redirect(self::PAGE_EVENTS, $back + ['error' => 'required_title']);
                }
                try {
                    $db->saveEventSpeakers((int) $savedId, (array) ($_POST['speaker_ids'] ?? []));
                    $this->redirect(self::PAGE_EVENTS, ['action' => 'edit', 'id' => (int) $savedId, 'saved' => 1]);
                } catch (\Throwable $relationError) {
                    CMS_365NET_Events::instance()->log('adminEventSaveRelations', $relationError);
                    $this->redirect(self::PAGE_EVENTS, ['action' => 'edit', 'id' => (int) $savedId, 'saved' => 1, 'warning' => 'relations']);
                }
            } catch (\Throwable $e) {
                CMS_365NET_Events::instance()->log('adminEventSave', $e);
                $this->redirect(self::PAGE_EVENTS, $back + ['error' => 'save']);
            }
            return;
        }

        if (!$this->verifyCsrf('365net_admin')) {
            $this->redirect(self::PAGE_EVENTS, ['error' => 'csrf']);
        }

        try {
            if ($operation === 'link_sync') {
                $db->purgeNonPersonSpeakers();
                $db->autoLinkExistingRecords();
                $this->redirect(self::PAGE_EVENTS, ['synced' => 1]);
            }

            $rowDelete = (int) ($_POST['row_delete'] ?? 0);
            if ($operation === 'delete' || $rowDelete > 0) {
                $id = $rowDelete > 0 ? $rowDelete : (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
                $deleted = $id > 0 && $db->deleteEvent($id) ? 1 : 0;
                $this->redirect(self::PAGE_EVENTS, ['deleted' => $deleted]);
            }

            if ($operation === 'bulk') {
                $this->redirect(self::PAGE_EVENTS, $this->applyBulk('events'));
            }
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('adminEventsPost', $e);
            $this->redirect(self::PAGE_EVENTS, ['error' => 'save']);
        }

        $this->redirect(self::PAGE_EVENTS, ['error' => 'unknown_action']);
    }

    private function handleSpeakersPost(): void
    {
        $db = CMS_365NET_Events_Database::instance();
        $operation = $this->postOperation();

        if ($operation === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $back = $id > 0 ? ['action' => 'edit', 'id' => $id] : ['action' => 'new'];
            if (!$this->verifyCsrf('365net_speaker_form')) {
                $this->redirect(self::PAGE_SPEAKERS, $back + ['error' => 'csrf']);
            }
            try {
                $savedId = $db->saveSpeaker($_POST);
                if ($savedId === false) {
                    $this->redirect(self::PAGE_SPEAKERS, $back + ['error' => 'required_name']);
                }
                $this->redirect(self::PAGE_SPEAKERS, ['action' => 'edit', 'id' => (int) $savedId, 'saved' => 1]);
            } catch (\Throwable $e) {
                CMS_365NET_Events::instance()->log('adminSpeakerSave', $e);
                $this->redirect(self::PAGE_SPEAKERS, $back + ['error' => 'save']);
            }
            return;
        }

        if (!$this->verifyCsrf('365net_admin')) {
            $this->redirect(self::PAGE_SPEAKERS, ['error' => 'csrf']);
        }

        try {
            $rowDelete = (int) ($_POST['row_delete'] ?? 0);
            if ($operation === 'delete' || $rowDelete > 0) {
                $id = $rowDelete > 0 ? $rowDelete : (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
                $deleted = $id > 0 && $db->deleteSpeaker($id) ? 1 : 0;
                $this->redirect(self::PAGE_SPEAKERS, ['deleted' => $deleted]);
            }

            if ($operation === 'bulk') {
                $this->redirect(self::PAGE_SPEAKERS, $this->applyBulk('speakers'));
            }
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSpeakersPost', $e);
            $this->redirect(self::PAGE_SPEAKERS, ['error' => 'save']);
        }

        $this->redirect(self::PAGE_SPEAKERS, ['error' => 'unknown_action']);
    }

    private function handleSettingsPost(): void
    {
        $db = CMS_365NET_Events_Database::instance();
        $operation = $this->postOperation();

        try {
            if ($operation === 'backfill_descriptions') {
                if (!$this->verifyCsrf('365net_settings_backfill')) {
                    $this->redirect(self::PAGE_SETTINGS, ['error' => 'csrf']);
                }
                $updated = $db->backfillSeedEventDescriptionsFromSeed();
                $this->redirect(self::PAGE_SETTINGS, ['backfilled' => 1, 'updated' => max(0, $updated)]);
            }

            if ($operation === 'backfill_speakers') {
                if (!$this->verifyCsrf('365net_settings_speaker_backfill')) {
                    $this->redirect(self::PAGE_SETTINGS, ['error' => 'csrf']);
                }
                if (function_exists('set_time_limit')) {
                    @set_time_limit(300);
                }
                $result = $db->backfillSpeakerProfilesFromGoogle();
                $query = [
                    'speaker_backfilled' => 1,
                    'speaker_updated' => max(0, (int) ($result['updated'] ?? 0)),
                    'speaker_skipped' => max(0, (int) ($result['skipped'] ?? 0)),
                    'speaker_failed' => max(0, (int) ($result['failed'] ?? 0)),
                    'speaker_more' => !empty($result['more']) ? 1 : 0,
                ];
                if (!(bool) ($result['configured'] ?? false)) {
                    $query['speaker_config'] = 0;
                }
                $this->redirect(self::PAGE_SETTINGS, $query);
            }

            if ($operation === 'save') {
                if (!$this->verifyCsrf('365net_settings_form')) {
                    $this->redirect(self::PAGE_SETTINGS, ['error' => 'csrf']);
                }
                $db->saveSettings($_POST);
                $googleOk = $db->saveGoogleCredentials(
                    (string) ($_POST['google_api_key'] ?? ''),
                    (string) ($_POST['google_cse_id'] ?? ''),
                    !empty($_POST['google_api_key_clear'])
                );
                $this->redirect(self::PAGE_SETTINGS, $googleOk ? ['saved' => 1] : ['saved' => 1, 'warning' => 'google_key']);
            }
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSettingsPost', $e);
            $this->redirect(self::PAGE_SETTINGS, ['error' => 'save']);
        }

        $this->redirect(self::PAGE_SETTINGS, ['error' => 'unknown_action']);
    }

    /**
     * Sammelaktion der Listen (Status setzen oder löschen).
     *
     * @param 'events'|'speakers' $type
     * @return array<string, int|string>
     */
    private function applyBulk(string $type): array
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $bulk = (string) ($_POST['bulk_action'] ?? '');
        if ($ids === [] || !in_array($bulk, ['publish', 'draft', 'delete'], true)) {
            return ['error' => 'bulk_empty'];
        }

        $db = CMS_365NET_Events_Database::instance();
        if ($bulk === 'delete') {
            return ['deleted' => $db->bulkDelete($type, $ids)];
        }

        return ['bulk_updated' => $db->bulkSetStatus($type, $ids, $bulk === 'publish' ? 'published' : 'draft')];
    }

    /**
     * CSV-Download (UTF-8 mit BOM, Semikolon, Schutz vor Formel-Injection). GET mit persistentem Token.
     *
     * @param 'events'|'speakers' $type
     * @param array{args: array<string, mixed>} $filters
     */
    private function exportCsv(string $type, array $filters): void
    {
        $page = $type === 'speakers' ? self::PAGE_SPEAKERS : self::PAGE_EVENTS;
        if (!CMS\Security::instance()->verifyPersistentToken((string) ($_GET['token'] ?? ''), '365net_export')) {
            $this->redirect($page, ['error' => 'csrf']);
        }

        try {
            [$header, $rows] = CMS_365NET_Events_Database::instance()->exportRows($type, $filters['args']);
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('adminExport', $e);
            $this->redirect($page, ['error' => 'export']);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = 'events-and-speaker-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'csvCell'], $row), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Zellen mit = + - @ Tab oder CR am Anfang werden mit ' entschärft (Formel-Injection). */
    public static function csvCell(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }

    // ---------------------------------------------------------------------------------------------
    // Filter, Anfrage, Weiterleitung
    // ---------------------------------------------------------------------------------------------

    /** @return array{q: string, status: string, time: string, args: array<string, mixed>} */
    private function eventFilters(): array
    {
        $q = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        $status = in_array($this->query('status'), ['published', 'draft'], true) ? $this->query('status') : '';
        $time = $this->query('time');
        if ($time === '' && (string) ($_GET['past'] ?? '') === '1') {
            $time = 'past';
        }
        $time = in_array($time, ['upcoming', 'past', 'all', 'undated'], true) ? $time : 'upcoming';

        $args = ['search' => $q, 'status' => $status];
        if ($time === 'upcoming') {
            $args += ['date_mode' => 'future', 'from' => date('Y-m-d')];
        } elseif ($time === 'past') {
            $args += ['date_mode' => 'past', 'before' => date('Y-m-d')];
        } elseif ($time === 'undated') {
            $args += ['date_mode' => 'undated'];
        }

        return ['q' => $q, 'status' => $status, 'time' => $time, 'args' => $args];
    }

    /** @return array{q: string, status: string, sort: string, args: array<string, mixed>} */
    private function speakerFilters(): array
    {
        $q = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        $status = in_array($this->query('status'), ['published', 'draft'], true) ? $this->query('status') : '';
        $sort = in_array($this->query('sort'), ['az', 'za', 'date_old_new', 'date_new_old'], true) ? $this->query('sort') : 'az';

        return ['q' => $q, 'status' => $status, 'sort' => $sort, 'args' => ['search' => $q, 'status' => $status]];
    }

    private function guard(): bool
    {
        if (class_exists('CMS\\Auth') && CMS\Auth::instance()->isAdmin()) {
            return true;
        }

        echo '<div class="alert alert-danger" role="alert">Für diesen Bereich fehlt die Berechtigung.</div>';

        return false;
    }

    private function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    /** Aktion eines Formulars: verstecktes Feld cms365_action, für alte Adressen (307) der Parameter do. */
    private function postOperation(): string
    {
        $operation = (string) ($_POST['cms365_action'] ?? $_GET['do'] ?? '');

        return preg_match('/^[a-z_]{1,40}$/', $operation) === 1 ? $operation : '';
    }

    private function query(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? strtolower(trim(substr($value, 0, 40))) : '';
    }

    private function pageNumber(): int
    {
        $raw = $_GET['p'] ?? 1;

        return is_scalar($raw) ? max(1, min(9999, (int) $raw)) : 1;
    }

    private function cleanQuery(string $value): string
    {
        $value = trim(strip_tags($value));

        return function_exists('mb_substr') ? (string) mb_substr($value, 0, 120, 'UTF-8') : substr($value, 0, 120);
    }

    private function verifyCsrf(string $action): bool
    {
        return CMS\Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), $action);
    }

    /** @param array<string, scalar> $query */
    private function redirect(string $page, array $query = []): never
    {
        $target = self::url($page, $query);
        if (method_exists('CMS\\Security', 'safeLocalPath')) {
            $target = CMS\Security::safeLocalPath($target, '/admin/plugins/' . self::MENU_SLUG);
        }

        header('Location: ' . rtrim((string) SITE_URL, '/') . $target, true, 303);
        exit;
    }

    // ---------------------------------------------------------------------------------------------
    // Layout
    // ---------------------------------------------------------------------------------------------

    /**
     * Gibt den Inhalt einer Unterseite aus. Mit dem Helfer setzt dieser Kopfzeile und Plugin-Menü;
     * ohne ihn (nur ältere Installationen) wird das Layout hier über das Core-Partial geöffnet.
     */
    private function renderPage(string $page, callable $body): void
    {
        $this->printAssets();

        $ownLayout = !class_exists('PhinIT_Plugin_Admin_Nav', false);
        if ($ownLayout) {
            $this->loadWorkspace();
            echo \cms_plugin_workspace_open([
                'plugin' => self::MENU_SLUG,
                'title' => self::TITLE,
                'icon' => 'ti ti-calendar-event',
                'version' => defined('CMS_365NET_EVENTS_VERSION') ? (string) CMS_365NET_EVENTS_VERSION : '',
                'status' => self::adminStatus(),
                'description' => 'Events, Messen und Speaker für die öffentlichen Seiten /events und /speakers pflegen.',
                'stats' => self::adminStats(),
                'nav' => $this->fallbackNav($page),
                'nav_explicit' => true,
            ]);
        }

        $body();

        if ($ownLayout) {
            echo \cms_plugin_workspace_close();
        }
    }

    /** @return list<array<string, mixed>> */
    private function fallbackNav(string $active): array
    {
        $badges = self::adminBadges();
        $items = [
            [self::MENU_SLUG, 'Übersicht', 'ti ti-layout-dashboard'],
            [self::PAGE_EVENTS, 'Events', 'ti ti-calendar-event'],
            [self::PAGE_SPEAKERS, 'Speaker', 'ti ti-microphone'],
            [self::PAGE_TAXONOMIES, 'Kategorien & Tags', 'ti ti-tags'],
            [self::PAGE_SETTINGS, 'Einstellungen', 'ti ti-settings'],
        ];
        $nav = [];
        foreach ($items as [$slug, $label, $icon]) {
            $nav[] = [
                'slug' => $slug,
                'label' => $label,
                'icon' => $icon,
                'url' => self::url($slug),
                'active' => $slug === $active,
                'badge' => $badges[$slug]['value'] ?? null,
                'badge_tone' => (string) ($badges[$slug]['tone'] ?? ''),
            ];
        }

        return $nav;
    }

    private function loadWorkspace(): void
    {
        if (!function_exists('cms_plugin_workspace_open')) {
            $core = ABSPATH . 'admin/partials/plugin-workspace.php';
            if (is_file($core)) {
                require_once $core;
            }
        }
        if (!function_exists('cms_plugin_workspace_open')) {
            require_once __DIR__ . '/plugin-workspace.php';
            $css = CMS_365NET_EVENTS_PLUGIN_DIR . 'assets/css/phinit-admin-nav.css';
            if (is_file($css)) {
                echo '<link rel="stylesheet" href="' . $this->e(CMS_365NET_EVENTS_PLUGIN_URL . 'assets/css/phinit-admin-nav.css?v=' . CMS_365NET_EVENTS_VERSION) . '">';
            }
        }
    }

    /** Plugin-CSS/-JS nur auf den eigenen Admin-Seiten (der Router verschiebt das Stylesheet in den head). */
    private function printAssets(): void
    {
        if (self::$assetsPrinted) {
            return;
        }
        self::$assetsPrinted = true;

        $css = CMS_365NET_EVENTS_PLUGIN_DIR . 'assets/css/admin.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="' . $this->e(CMS_365NET_EVENTS_PLUGIN_URL . 'assets/css/admin.css?v=' . CMS_365NET_EVENTS_VERSION . '-' . (int) filemtime($css)) . '">' . "\n";
        }

        $js = CMS_365NET_EVENTS_PLUGIN_DIR . 'assets/js/admin.js';
        if (is_file($js)) {
            $nonce = class_exists('CMS\\Security') && method_exists('CMS\\Security', 'nonceAttr') ? ' ' . CMS\Security::instance()->nonceAttr() : '';
            echo '<script src="' . $this->e(CMS_365NET_EVENTS_PLUGIN_URL . 'assets/js/admin.js?v=' . CMS_365NET_EVENTS_VERSION . '-' . (int) filemtime($js)) . '" defer' . $nonce . '></script>' . "\n";
        }
    }

    /** @param array<string, mixed> $vars */
    private function view(string $name, array $vars = []): void
    {
        $name = preg_replace('/[^a-z0-9-]/', '', $name) ?? '';
        $file = CMS_365NET_EVENTS_PLUGIN_DIR . 'admin/views/' . $name . '.php';
        if ($name === '' || !is_file($file)) {
            echo '<div class="alert alert-danger" role="alert">Ansicht nicht gefunden.</div>';
            return;
        }

        extract($vars, EXTR_SKIP);
        include $file;
    }

    /** Kopf einer Unterseite: Titel, Einzeiler und Aktionen (Layout-Vertrag: Header → Toolbar → Inhalt). */
    private function pageHeader(string $title, string $description, string $actions = ''): void
    {
        echo '<div class="admin-page-header"><div><h2>' . $this->e($title) . '</h2><p>' . $this->e($description) . '</p></div>'
            . ($actions !== '' ? '<div class="header-actions">' . $actions . '</div>' : '') . '</div>';
    }

    private function button(string $label, string $url, string $icon, string $class = 'btn-secondary', bool $external = false): string
    {
        return '<a class="btn ' . $this->e($class) . '" href="' . $this->e($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
            . '<i class="ti ' . $this->e($icon) . '" aria-hidden="true"></i> ' . $this->e($label) . '</a>';
    }

    /** GET-Formular auf eine Unterseite: Pfad als action, Query (z. B. view=…) als versteckte Felder. */
    private function getFormOpen(string $page, string $class = 'cms365-toolbar'): string
    {
        $url = self::url($page);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? $url);
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
        $html = '<form method="get" action="' . $this->e($path) . '" class="' . $this->e($class) . '" role="search">';
        foreach ($query as $key => $value) {
            if (is_scalar($value)) {
                $html .= '<input type="hidden" name="' . $this->e((string) $key) . '" value="' . $this->e((string) $value) . '">';
            }
        }

        return $html;
    }

    /** @param array<string, scalar> $query */
    private function pagination(string $page, int $current, int $pages, array $query): string
    {
        if ($pages <= 1) {
            return '';
        }

        $html = '<nav class="cms365-pagination" aria-label="Seiten"><span class="cms365-pagination__info">Seite ' . $current . ' von ' . $pages . '</span>';
        if ($current > 1) {
            $html .= '<a class="btn btn-sm btn-secondary" href="' . $this->e(self::url($page, $query + ['p' => $current - 1])) . '"><i class="ti ti-chevron-left" aria-hidden="true"></i> Zurück</a>';
        }
        if ($current < $pages) {
            $html .= '<a class="btn btn-sm btn-secondary" href="' . $this->e(self::url($page, $query + ['p' => $current + 1])) . '">Weiter <i class="ti ti-chevron-right" aria-hidden="true"></i></a>';
        }

        return $html . '</nav>';
    }

    private function flash(): void
    {
        $alert = function (string $tone, string $text): void {
            echo '<div class="alert alert-' . $this->e($tone) . '" role="' . ($tone === 'danger' ? 'alert' : 'status') . '">' . $this->e($text) . '</div>';
        };

        if (isset($_GET['saved'])) {
            $alert('success', 'Gespeichert.');
        }
        if (isset($_GET['backfilled'])) {
            $alert('success', 'Beschreibungen eingespielt. Aktualisierte Events: ' . max(0, (int) ($_GET['updated'] ?? 0)) . '.');
        }
        if (isset($_GET['speaker_backfilled'])) {
            if ((string) ($_GET['speaker_config'] ?? '') === '0') {
                $alert('warning', 'Speaker-Backfill nicht ausgeführt: Google API-Key und Suchmaschinen-ID fehlen (Einstellungen oder Umgebungsvariablen).');
            } else {
                $alert('success', sprintf(
                    'Speaker-Backfill ausgeführt. Aktualisiert: %d, übersprungen: %d, Fehler: %d.%s',
                    max(0, (int) ($_GET['speaker_updated'] ?? 0)),
                    max(0, (int) ($_GET['speaker_skipped'] ?? 0)),
                    max(0, (int) ($_GET['speaker_failed'] ?? 0)),
                    (string) ($_GET['speaker_more'] ?? '') === '1' ? ' Weitere Speaker stehen an – Backfill erneut starten.' : ''
                ));
            }
        }
        if (isset($_GET['deleted'])) {
            $count = max(0, (int) $_GET['deleted']);
            $count > 0 ? $alert('success', 'Gelöscht: ' . $count . '.') : $alert('warning', 'Es wurde nichts gelöscht.');
        }
        if (isset($_GET['bulk_updated'])) {
            $alert('success', 'Status geändert: ' . max(0, (int) $_GET['bulk_updated']) . ' Einträge.');
        }
        if (isset($_GET['synced'])) {
            $alert('success', 'Verknüpfungen zu Firmen und Experten wurden aktualisiert.');
        }
        $warning = (string) ($_GET['warning'] ?? '');
        if ($warning === 'relations') {
            $alert('warning', 'Event gespeichert, aber die Speaker-Zuordnung konnte nicht aktualisiert werden. Details stehen im Protokoll.');
        } elseif ($warning === 'google_key') {
            $alert('warning', 'Der Google API-Key wurde nicht gespeichert: ungültiges Format oder keine Verschlüsselung verfügbar (CMS_ENCRYPTION_KEY).');
        }
        $error = (string) ($_GET['error'] ?? '');
        if ($error !== '') {
            $messages = [
                'save' => 'Speichern fehlgeschlagen. Bitte Pflichtfelder und das Protokoll prüfen.',
                'required_title' => 'Speichern fehlgeschlagen: Der Titel ist erforderlich.',
                'required_name' => 'Speichern fehlgeschlagen: Vor- und Nachname (echte Person) oder ein Anzeigename sind erforderlich.',
                'csrf' => 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden und erneut absenden.',
                'not_found' => 'Der Datensatz wurde nicht gefunden.',
                'bulk_empty' => 'Bitte Einträge markieren und eine Sammelaktion wählen.',
                'export' => 'Der Export ist fehlgeschlagen.',
            ];
            $alert('danger', $messages[$error] ?? 'Die Aktion konnte nicht ausgeführt werden.');
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Kopfzeile, Übersicht, Badges (Optionen des Helfers)
    // ---------------------------------------------------------------------------------------------

    /** @return array<string, string> Kurzbeschreibungen der Bereiche */
    private static function sectionDescriptions(): array
    {
        return [
            self::PAGE_EVENTS => 'Anstehende und vergangene Events suchen, bearbeiten und Verknüpfungen aktualisieren.',
            self::PAGE_SPEAKERS => 'Speaker-Profile pflegen und mit Experts & Companies verknüpfen.',
            self::PAGE_TAXONOMIES => 'Dropdown-Werte für Kategorien, Arten, Klassen und Tags pflegen.',
            self::PAGE_SETTINGS => 'Texte, Layout und Farben der öffentlichen Eventseiten sowie Backfill-Werkzeuge.',
        ];
    }

    /** @return array{label: string, tone: string} */
    public static function adminStatus(): array
    {
        if (!class_exists('CMS\\Database') || !class_exists('CMS_365NET_Events_Database', false)) {
            return ['label' => 'Datenbank fehlt', 'tone' => 'danger'];
        }
        try {
            if (!CMS_365NET_Events_Database::instance()->isSchemaCurrent()) {
                return ['label' => 'Aktualisierung ausstehend', 'tone' => 'warning'];
            }
        } catch (\Throwable) {
            return ['label' => 'Datenbankfehler', 'tone' => 'danger'];
        }

        return ['label' => 'Aktiv', 'tone' => 'success'];
    }

    /**
     * Kennzahlen für die Kopfzeile. Nur COUNT-Abfragen; ohne Datenbank leer.
     *
     * @return list<array<string, mixed>>
     */
    public static function adminStats(): array
    {
        if (!class_exists('CMS\\Database') || !class_exists('CMS_365NET_Events_Database', false)) {
            return [];
        }

        $stats = [];
        try {
            $db = CMS_365NET_Events_Database::instance();
            $upcoming = $db->countEvents(['status' => 'published', 'date_mode' => 'future', 'from' => date('Y-m-d')]);
            $total = $db->countEvents();
            $speakers = $db->countSpeakers(['status' => 'published']);
            $drafts = $db->countEvents(['status' => 'draft']);

            $stats[] = ['label' => 'Kommende Events', 'value' => $upcoming, 'icon' => 'ti ti-calendar-event', 'tone' => $upcoming > 0 ? 'success' : 'muted', 'url' => self::url(self::PAGE_EVENTS), 'hint' => 'Veröffentlicht, ab heute'];
            $stats[] = ['label' => 'Events gesamt', 'value' => $total, 'icon' => 'ti ti-calendar', 'url' => self::url(self::PAGE_EVENTS, ['time' => 'all']), 'hint' => 'Anstehende und vergangene Events'];
            $stats[] = ['label' => 'Speaker', 'value' => $speakers, 'icon' => 'ti ti-microphone', 'tone' => 'info', 'url' => self::url(self::PAGE_SPEAKERS, ['status' => 'published']), 'hint' => 'Öffentliche Speaker-Profile'];
            $stats[] = ['label' => 'Entwürfe', 'value' => $drafts, 'icon' => 'ti ti-pencil', 'tone' => $drafts > 0 ? 'warning' : 'muted', 'url' => self::url(self::PAGE_EVENTS, ['time' => 'all', 'status' => 'draft']), 'hint' => 'Events mit Status Entwurf (nicht öffentlich)'];
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminStats]: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Übersicht: Schnellaktionen, Status-/System-Check und zuletzt bearbeitete Events.
     *
     * @return array<string, mixed>
     */
    public static function adminDashboard(): array
    {
        $actions = [
            ['label' => 'Neues Event', 'url' => self::url(self::PAGE_EVENTS, ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Event, Messe oder Webinar anlegen und Speaker zuordnen.'],
            ['label' => 'Neuer Speaker', 'url' => self::url(self::PAGE_SPEAKERS, ['action' => 'new']), 'icon' => 'ti ti-microphone', 'description' => 'Speaker-Profil anlegen und mit Experts & Companies verknüpfen.'],
            ['label' => 'Vergangene Events', 'url' => self::url(self::PAGE_EVENTS, ['time' => 'past']), 'icon' => 'ti ti-history', 'description' => 'Zurückliegende Events nachpflegen oder archivieren.'],
            ['label' => 'Öffentliche Eventseite', 'url' => '/events', 'icon' => 'ti ti-world', 'external' => true, 'description' => 'Die Eventübersicht so prüfen, wie Besucher sie sehen.'],
        ];

        if (!class_exists('CMS\\Database') || !class_exists('CMS_365NET_Events_Database', false)) {
            return [
                'actions' => $actions,
                'checks' => [['label' => 'Datenbank', 'status' => 'error', 'value' => 'nicht verfügbar']],
            ];
        }

        $db = CMS_365NET_Events_Database::instance();
        $checks = [];
        $items = [];
        try {
            $checks[] = $db->tablesPresent()
                ? ['label' => 'Datenbanktabellen', 'status' => 'ok', 'value' => '4 Tabellen vorhanden']
                : ['label' => 'Datenbanktabellen', 'status' => 'error', 'value' => 'fehlen', 'hint' => 'Plugin deaktivieren und erneut aktivieren, um die Tabellen anzulegen.'];
            $checks[] = $db->isSchemaCurrent()
                ? ['label' => 'Schema-Version', 'status' => 'ok', 'value' => 'aktuell']
                : ['label' => 'Schema-Version', 'status' => 'warning', 'value' => 'Aktualisierung ausstehend', 'hint' => 'Wird beim nächsten Seitenaufruf bzw. bei der Plugin-Aktivierung angehoben.'];

            $upcoming = $db->countEvents(['status' => 'published', 'date_mode' => 'future', 'from' => date('Y-m-d')]);
            $checks[] = $upcoming > 0
                ? ['label' => 'Öffentliche Eventseite', 'status' => 'ok', 'value' => $upcoming . ' kommende Events', 'url' => '/events']
                : ['label' => 'Öffentliche Eventseite', 'status' => 'warning', 'value' => 'keine kommenden Events', 'hint' => '/events zeigt derzeit nur den Leerzustand.', 'url' => self::url(self::PAGE_EVENTS, ['action' => 'new'])];

            $drafts = $db->countEvents(['status' => 'draft']);
            $checks[] = $drafts > 0
                ? ['label' => 'Entwürfe', 'status' => 'info', 'value' => $drafts . ' nicht veröffentlicht', 'url' => self::url(self::PAGE_EVENTS, ['time' => 'all', 'status' => 'draft'])]
                : ['label' => 'Entwürfe', 'status' => 'ok', 'value' => 'keine offenen Entwürfe'];

            $speakers = $db->countSpeakers(['status' => 'published']);
            $checks[] = ['label' => 'Speaker-Verzeichnis', 'status' => $speakers > 0 ? 'ok' : 'warning', 'value' => $speakers . ' öffentlich', 'url' => '/speakers'];
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminDashboard]: ' . $e->getMessage());
            $checks[] = ['label' => 'Datenbank', 'status' => 'error', 'value' => 'Abfrage fehlgeschlagen', 'hint' => 'Details stehen im Fehlerprotokoll.'];
        }

        $checks[] = $db->isExcompActive()
            ? ['label' => 'Experts & Companies', 'status' => 'ok', 'value' => 'Verknüpfung verfügbar']
            : ['label' => 'Experts & Companies', 'status' => 'muted', 'value' => 'Plugin nicht aktiv', 'hint' => 'Firmen und Experten lassen sich dann nicht verknüpfen.'];

        try {
            $checks[] = $db->isGoogleSearchConfigured()
                ? ['label' => 'Google-Suche (Speaker-Backfill)', 'status' => 'ok', 'value' => 'konfiguriert', 'url' => self::url(self::PAGE_SETTINGS)]
                : ['label' => 'Google-Suche (Speaker-Backfill)', 'status' => 'muted', 'value' => 'nicht konfiguriert', 'hint' => 'Optional; API-Key und Suchmaschinen-ID in den Einstellungen oder als Umgebungsvariablen.', 'url' => self::url(self::PAGE_SETTINGS)];

            foreach ($db->getEvents(['order' => 'updated_desc', 'limit' => 6]) as $event) {
                $updated = strtotime((string) ($event->updated_at ?? ''));
                $date = trim((string) ($event->date_label ?? ''));
                $published = (string) ($event->status ?? '') === 'published';
                $items[] = [
                    'title' => (string) ($event->title ?? ''),
                    'url' => self::url(self::PAGE_EVENTS, ['action' => 'edit', 'id' => (int) ($event->id ?? 0)]),
                    'meta' => ($updated !== false ? 'geändert ' . date('d.m.Y, H:i', $updated) : 'geändert') . ($date !== '' ? ' · Termin ' . $date : ''),
                    'badge' => $published ? 'Veröffentlicht' : 'Entwurf',
                    'tone' => $published ? 'success' : 'warning',
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminDashboard activity]: ' . $e->getMessage());
        }

        return [
            'intro' => 'Events, Messen und Speaker pflegen; die öffentlichen Seiten /events und /speakers zeigen nur veröffentlichte Einträge.',
            'actions' => $actions,
            'checks' => $checks,
            'activity' => [
                'heading' => 'Zuletzt bearbeitete Events',
                'items' => $items,
                'empty' => 'Noch keine Events vorhanden.',
                'more_url' => self::url(self::PAGE_EVENTS, ['time' => 'all']),
                'more_label' => 'Alle Events',
            ],
        ];
    }

    /**
     * Zähler-Badges im Plugin-Menü: Entwürfe bei „Events“.
     *
     * @return array<string, mixed>
     */
    public static function adminBadges(): array
    {
        if (!class_exists('CMS\\Database') || !class_exists('CMS_365NET_Events_Database', false)) {
            return [];
        }
        try {
            $drafts = CMS_365NET_Events_Database::instance()->countEvents(['status' => 'draft']);
        } catch (\Throwable) {
            return [];
        }

        return $drafts > 0 ? [self::PAGE_EVENTS => ['value' => $drafts, 'tone' => 'warning']] : [];
    }

    // ---------------------------------------------------------------------------------------------
    // Formular-Bausteine (in den Views genutzt)
    // ---------------------------------------------------------------------------------------------

    private function csrfField(string $action): string
    {
        return '<input type="hidden" name="csrf_token" value="' . $this->e(CMS\Security::instance()->generateToken($action)) . '">';
    }

    private function field(string $name, string $label, mixed $value, bool $required = false, string $type = 'text'): string
    {
        $step = $type === 'number' ? ' step="0.01" min="0"' : '';

        return '<label class="form-group"><span>' . $this->e($label) . '</span><input class="form-control" type="' . $this->e($type) . '" name="' . $this->e($name) . '" value="' . $this->e((string) $value) . '"' . $step . ($required ? ' required' : '') . '></label>';
    }

    /** @param array<string, string> $options */
    private function select(string $name, string $label, string $value, array $options): string
    {
        if ($value !== '' && !array_key_exists($value, $options)) {
            $options[$value] = $value . ' (bestehend)';
        }
        $html = '<label class="form-group"><span>' . $this->e($label) . '</span><select class="form-control" name="' . $this->e($name) . '">';
        foreach ($options as $optionValue => $optionLabel) {
            $html .= '<option value="' . $this->e($optionValue) . '"' . ($value === (string) $optionValue ? ' selected' : '') . '>' . $this->e($optionLabel) . '</option>';
        }

        return $html . '</select></label>';
    }

    /** @param array<int, string> $items @return array<string, string> */
    private function optionMap(array $items): array
    {
        $options = ['' => 'Bitte wählen'];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $options[$item] = $item;
            }
        }

        return $options;
    }

    /** @return array<int, string> */
    private function targetAudienceOptions(): array
    {
        return ['IT-Admins', 'Entscheider', 'Developers', 'Architects', 'Consultants', 'Security Teams', 'Endanwender', 'Führungskräfte', 'Community', 'Einsteiger', 'Fortgeschrittene'];
    }

    /** @return array<int, string> */
    private function languageOptions(): array
    {
        return ['Deutsch', 'Englisch', 'Deutsch/Englisch', 'Französisch', 'Spanisch', 'Italienisch', 'Niederländisch'];
    }

    /** @param array<int, string> $options */
    private function multiSelect(string $name, string $label, string $value, array $options, int $size = 6): string
    {
        $selected = array_filter(array_map('trim', preg_split('/[,;\n]+/', $value) ?: []));
        foreach ($selected as $selectedValue) {
            if ($selectedValue !== '' && !in_array($selectedValue, $options, true)) {
                $options[] = $selectedValue;
            }
        }
        $html = '<label class="form-group"><span>' . $this->e($label) . '</span><select class="form-control cms365-multiselect" name="' . $this->e($name) . '[]" multiple size="' . max(3, min(14, $size)) . '">';
        foreach ($options as $option) {
            $option = trim((string) $option);
            if ($option === '') {
                continue;
            }
            $html .= '<option value="' . $this->e($option) . '"' . (in_array($option, $selected, true) ? ' selected' : '') . '>' . $this->e($option) . '</option>';
        }

        return $html . '</select><small class="form-hint">Mehrfachauswahl mit Strg/Cmd oder Doppelklick.</small></label>';
    }

    private function textareaSetting(string $name, string $label, mixed $value): string
    {
        return '<label class="form-group"><span>' . $this->e($label) . '</span><textarea name="' . $this->e($name) . '" class="form-control" rows="9">' . $this->e((string) $value) . '</textarea></label>';
    }

    /** @param array<int, object> $companies @return array<string, string> */
    private function companyOptions(array $companies): array
    {
        $options = ['' => 'Automatisch erkennen / keine Verknüpfung'];
        foreach ($companies as $company) {
            $label = (string) ($company->name ?? 'Firma #' . (int) ($company->id ?? 0));
            if (!empty($company->location_city)) {
                $label .= ' · ' . (string) $company->location_city;
            }
            $options[(string) (int) ($company->id ?? 0)] = $label;
        }

        return $options;
    }

    /** @param array<int, object> $experts @return array<string, string> */
    private function expertOptions(array $experts): array
    {
        $options = ['' => 'Automatisch erkennen / keine Verknüpfung'];
        foreach ($experts as $expert) {
            $name = trim((string) ($expert->first_name ?? '') . ' ' . (string) ($expert->last_name ?? ''));
            $label = $name !== '' ? $name : 'Expert #' . (int) ($expert->id ?? 0);
            if (!empty($expert->company)) {
                $label .= ' · ' . (string) $expert->company;
            } elseif (!empty($expert->position)) {
                $label .= ' · ' . (string) $expert->position;
            }
            $options[(string) (int) ($expert->id ?? 0)] = $label;
        }

        return $options;
    }

    private function checkbox(string $name, string $label, bool $checked): string
    {
        return '<label class="form-group cms365-checkbox"><input type="hidden" name="' . $this->e($name) . '" value="0"><input type="checkbox" name="' . $this->e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span>' . $this->e($label) . '</span></label>';
    }

    private function statusSelect(string $value): string
    {
        return $this->select('status', 'Status', $value === 'draft' ? 'draft' : 'published', ['published' => 'Veröffentlicht', 'draft' => 'Entwurf']);
    }

    private function statusBadge(string $status): string
    {
        $published = $status === 'published';

        return '<span class="status-badge ' . ($published ? 'active' : 'inactive') . '">' . ($published ? 'Veröffentlicht' : 'Entwurf') . '</span>';
    }

    private function preview(string $previewId, string $inputId, string $url): string
    {
        $safe = preg_match('#^(?:https?://|/)#i', $url) === 1 && !str_starts_with($url, '//');

        return '<div id="' . $this->e($previewId) . '" class="cms365-image-preview" data-cms365-image-preview data-input-id="' . $this->e($inputId) . '">'
            . ($url !== '' && $safe ? '<img src="' . $this->e($url) . '" alt="" loading="lazy">' : '<span>Keine Vorschau</span>') . '</div>';
    }

    private function mediaButtons(string $inputId, string $previewId, string $title): string
    {
        return '<button type="button" class="btn btn-secondary" data-open-media-picker data-target-input="' . $this->e($inputId) . '" data-preview-id="' . $this->e($previewId) . '" data-picker-title="' . $this->e($title) . '"><i class="ti ti-photo" aria-hidden="true"></i> Mediathek</button>'
            . '<button type="button" class="btn btn-secondary" data-clear-media-input data-target-input="' . $this->e($inputId) . '">Leeren</button>';
    }

    private function imageField(string $urlName, string $urlLabel, mixed $urlValue, string $altName, mixed $altValue): string
    {
        $url = (string) $urlValue;
        $inputId = 'cms365-media-' . preg_replace('/[^a-z0-9_-]+/i', '-', $urlName);
        $previewId = $inputId . '-preview';

        return '<div class="cms365-image-field"><label class="form-group"><span>' . $this->e($urlLabel) . '</span><div class="cms365-media-row"><input id="' . $this->e($inputId) . '" class="form-control" type="text" name="' . $this->e($urlName) . '" value="' . $this->e($url) . '" placeholder="/uploads/bild.webp oder https://…" data-cms365-image-input>'
            . $this->mediaButtons($inputId, $previewId, $urlLabel . ' auswählen') . '</div></label>'
            . $this->preview($previewId, $inputId, $url)
            . '<label class="form-group"><span>Alt-Text</span><input class="form-control" type="text" name="' . $this->e($altName) . '" value="' . $this->e((string) $altValue) . '"></label></div>';
    }

    private function mediaUrlField(string $name, string $label, mixed $value): string
    {
        $url = (string) $value;
        $inputId = 'cms365-media-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
        $previewId = $inputId . '-preview';

        return '<div class="form-group cms365-media-field"><label for="' . $this->e($inputId) . '"><span>' . $this->e($label) . '</span></label><div class="cms365-media-row"><input id="' . $this->e($inputId) . '" class="form-control" type="text" name="' . $this->e($name) . '" value="' . $this->e($url) . '" placeholder="/uploads/bild.webp oder https://…" data-cms365-image-input>'
            . $this->mediaButtons($inputId, $previewId, $label . ' auswählen') . '</div>'
            . $this->preview($previewId, $inputId, $url) . '</div>';
    }

    private function galleryField(string $name, string $label, mixed $value): string
    {
        $inputId = 'cms365-gallery-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);

        return '<div class="form-group cms365-gallery-field"><label for="' . $this->e($inputId) . '"><span>' . $this->e($label) . '</span></label><div class="cms365-media-row cms365-media-row--toolbar"><button type="button" class="btn btn-secondary" data-open-media-picker data-target-input="' . $this->e($inputId) . '" data-gallery-target="1" data-picker-title="Galeriebilder auswählen"><i class="ti ti-photo-plus" aria-hidden="true"></i> Bilder aus Mediathek hinzufügen</button><button type="button" class="btn btn-secondary" data-clear-media-input data-target-input="' . $this->e($inputId) . '">Galerie leeren</button></div>'
            . '<textarea id="' . $this->e($inputId) . '" name="' . $this->e($name) . '" class="form-control" rows="5" placeholder="/uploads/bild-1.webp&#10;/uploads/bild-2.webp" data-cms365-gallery-input>' . $this->e($this->galleryText((string) $value)) . '</textarea>'
            . '<small class="form-hint">Ein Klick im Mediathek-Fenster fügt das Bild als neue Zeile hinzu.</small><div class="cms365-gallery-preview" data-cms365-gallery-preview data-input-id="' . $this->e($inputId) . '"></div></div>';
    }

    /** Gespeichertes Galerie-JSON ([{"url":…}]) als eine URL je Zeile anzeigen. */
    private function galleryText(string $value): string
    {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $value;
        }
        $urls = [];
        foreach ($decoded as $item) {
            $url = is_array($item) ? (string) ($item['url'] ?? '') : (string) $item;
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return implode("\n", $urls);
    }

    private function renderMediaPickerModal(): void
    {
        $token = CMS\Security::instance()->generateToken('editorjs_media');
        echo '<div class="modal modal-blur fade cms365-media-modal" id="settingsMediaPickerModal" tabindex="-1" aria-hidden="true" aria-labelledby="cms365-media-title"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">'
            . '<div class="modal-header"><h5 class="modal-title" id="cms365-media-title" data-media-picker-title>Bild auswählen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>'
            . '<div class="modal-body"><div data-media-picker-modal data-api-url="/api/media" data-csrf-token="' . $this->e($token) . '" data-path-prefix="events">'
            . '<p class="text-secondary small mb-3">Ein Klick übernimmt das Bild in das gewählte Feld; bei Galerien werden Bilder zeilenweise ergänzt.</p>'
            . '<div class="cms365-media-picker-search"><input type="search" class="form-control" placeholder="Mediathek durchsuchen …" aria-label="Mediathek durchsuchen" data-media-picker-search><span class="form-hint" data-media-picker-status>Lade Medien …</span></div>'
            . '<div class="cms365-media-picker-grid" data-media-picker-grid></div></div></div></div></div></div>';
    }

    private function editor(string $name, string $jsonValue, string $fallbackText, string $label): string
    {
        $jsonValue = trim($jsonValue);
        if ($jsonValue !== '') {
            $decoded = json_decode($jsonValue, true);
            if (!is_array($decoded) || !isset($decoded['blocks']) || !is_array($decoded['blocks']) || $decoded['blocks'] === []) {
                $jsonValue = '';
            }
        }

        if ($jsonValue === '' && $fallbackText !== '') {
            $jsonValue = json_encode([
                'time' => time() * 1000,
                'blocks' => array_values(array_map(static fn(string $part): array => ['type' => 'paragraph', 'data' => ['text' => htmlspecialchars(trim($part), ENT_QUOTES, 'UTF-8')]], array_filter(preg_split('/\n{2,}/', $fallbackText) ?: []))),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (class_exists('CMS\\Services\\EditorJs\\EditorJsAssetService')) {
            $service = new CMS\Services\EditorJs\EditorJsAssetService();

            return $service->render($name, $jsonValue, ['height' => 460, 'context' => '365netevents', 'aria_label' => $label, 'content_width' => 960]);
        }

        return '<textarea class="form-control" name="' . $this->e($name) . '" rows="10" aria-label="' . $this->e($label) . '">' . $this->e($jsonValue) . '</textarea>';
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

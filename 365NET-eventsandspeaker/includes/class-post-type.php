<?php
/**
 * Router-/Controller-Klasse für 365NET Events & Speaker.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Post_Type
{
    private const PUBLIC_PER_PAGE = 15;
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (class_exists('CMS\\Hooks')) {
            CMS\Hooks::addAction('register_routes', [$this, 'registerRoutes'], 10);
            CMS\Hooks::addAction('main_nav', [$this, 'addMenuItem'], 10);
        }
    }

    public function registerRoutes($router): void
    {
        $router->addRoute('GET', '/events', [$this, 'archiveEvents']);
        $router->addRoute('GET', '/events/:slug', [$this, 'singleEvent']);
        $router->addRoute('GET', '/speakers', [$this, 'archiveSpeakers']);
        $router->addRoute('GET', '/speakers/:slug', [$this, 'singleSpeaker']);
        $router->addRoute('GET', '/event-speakers', [$this, 'archiveSpeakers']);
        $router->addRoute('GET', '/event-speakers/:slug', [$this, 'singleSpeaker']);

        $router->addRoute('GET', '/admin/365netevents', [$this, 'adminEvents']);
        $router->addRoute('GET', '/admin/365netevents/dashboard', [$this, 'adminDashboard']);
        $router->addRoute('GET', '/admin/365netevents/new', [$this, 'adminEventNew']);
        $router->addRoute('GET', '/admin/365netevents/edit/:id', [$this, 'adminEventEdit']);
        $router->addRoute('POST', '/admin/365netevents/save', [$this, 'adminEventSave']);
        $router->addRoute('POST', '/admin/365netevents/delete/:id', [$this, 'adminEventDelete']);
        $router->addRoute('POST', '/admin/365netevents/link-sync', [$this, 'adminLinkSync']);
        $router->addRoute('GET', '/admin/365netevents/taxonomies', [$this, 'adminTaxonomies']);
        $router->addRoute('POST', '/admin/365netevents/taxonomies/save', [$this, 'adminTaxonomiesSave']);
        $router->addRoute('GET', '/admin/365netevents/settings', [$this, 'adminSettings']);
        $router->addRoute('POST', '/admin/365netevents/settings/save', [$this, 'adminSettingsSave']);
        $router->addRoute('POST', '/admin/365netevents/settings/backfill-descriptions', [$this, 'adminSettingsBackfillDescriptions']);
        $router->addRoute('POST', '/admin/365netevents/settings/backfill-speakers', [$this, 'adminSettingsBackfillSpeakers']);

        $router->addRoute('GET', '/admin/365netevents/speakers', [$this, 'adminSpeakers']);
        $router->addRoute('GET', '/admin/365netevents/speakers/new', [$this, 'adminSpeakerNew']);
        $router->addRoute('GET', '/admin/365netevents/speakers/edit/:id', [$this, 'adminSpeakerEdit']);
        $router->addRoute('POST', '/admin/365netevents/speakers/save', [$this, 'adminSpeakerSave']);
        $router->addRoute('POST', '/admin/365netevents/speakers/delete/:id', [$this, 'adminSpeakerDelete']);
    }

    public function addMenuItem(): void
    {
        $settings = CMS_365NET_Events_Database::instance()->getSettings();
        if (($settings['show_nav_link'] ?? '0') !== '1') {
            return;
        }

        $label = trim((string) ($settings['nav_label'] ?? 'Events')) ?: 'Events';
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $active = str_starts_with($path, '/events') ? 'active' : '';
        echo '<a href="' . htmlspecialchars(rtrim((string) SITE_URL, '/') . '/events', ENT_QUOTES, 'UTF-8') . '" class="nav-link ' . htmlspecialchars($active, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    public function archiveEvents(): void
    {
        $db = CMS_365NET_Events_Database::instance();
        $settings = $db->getSettings();
        $search = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        $showPast = (string) ($_GET['past'] ?? '') === '1';
        $page = $this->pageNumber();
        $eventArgs = [
            'status' => 'published',
            'search' => $search,
            'date_mode' => $showPast ? 'past' : 'future',
            'from' => date('Y-m-d'),
            'before' => date('Y-m-d'),
            'order' => $showPast ? 'date_desc' : '',
        ];
        $total = $db->countEvents($eventArgs);
        $pagination = $this->paginationData($page, $total, self::PUBLIC_PER_PAGE);
        if ($page > (int) $pagination['total_pages']) {
            $this->render404('Eventseite nicht gefunden.', '/events');
            return;
        }
        $eventArgs['limit'] = self::PUBLIC_PER_PAGE;
        $eventArgs['offset'] = ((int) $pagination['current'] - 1) * self::PUBLIC_PER_PAGE;
        $events = $db->getEvents($eventArgs);
        foreach ($events as $event) {
            if (!is_object($event)) {
                continue;
            }

            $linkedCompany = $db->getLinkedCompany(isset($event->linked_company_id) ? (int) $event->linked_company_id : null);
            if ($linkedCompany === null) {
                $eventSignals = [
                    'organizer' => (string) ($event->organizer ?? ''),
                    'company' => (string) ($event->organizer ?? ''),
                    'contact_name' => (string) ($event->contact_name ?? ''),
                    'website' => (string) ($event->website ?? ''),
                    'registration_url' => (string) ($event->registration_url ?? ''),
                    'ticket_url' => (string) ($event->ticket_url ?? ''),
                ];
                $linkedCompany = $db->detectLinkedCompanyBySignals($eventSignals);
            }

            $event->linked_company_is_top_partner = ($linkedCompany !== null && (int) ($linkedCompany->is_top_partner ?? 0) === 1) ? 1 : 0;
        }

        $this->renderWithTheme('archive-events', [
            'events' => $events,
            'settings' => $settings,
            'filters' => ['q' => $search, 'past' => $showPast ? '1' : '0'],
            'pagination' => $pagination,
        ]);
    }

    public function singleEvent(string $slug = ''): void
    {
        $slug = $slug !== '' ? $slug : (string) ($_GET['slug'] ?? '');
        $db = CMS_365NET_Events_Database::instance();
        $event = $db->getEventBySlug($this->cleanSlug($slug));
        if (!$event || (string) $event->status !== 'published') {
            $this->render404('Event nicht gefunden.', '/events');
            return;
        }

        $speakers = $db->getSpeakersForEvent((int) $event->id);
        CMS\Hooks::doAction('cms_365net_event_viewed', (int) $event->id);

        $linkedCompany = $db->getLinkedCompany(isset($event->linked_company_id) ? (int) $event->linked_company_id : null);
        $linkedExpert = $db->getLinkedExpert(isset($event->linked_expert_id) ? (int) $event->linked_expert_id : null);

        $eventSignals = [
            'organizer' => (string) ($event->organizer ?? ''),
            'company' => (string) ($event->organizer ?? ''),
            'contact_name' => (string) ($event->contact_name ?? ''),
            'website' => (string) ($event->website ?? ''),
            'registration_url' => (string) ($event->registration_url ?? ''),
            'ticket_url' => (string) ($event->ticket_url ?? ''),
        ];

        if ($linkedCompany === null) {
            $linkedCompany = $db->detectLinkedCompanyBySignals($eventSignals);
        }
        if ($linkedExpert === null) {
            $linkedExpert = $db->detectLinkedExpertBySignals($eventSignals);
        }

        if (($linkedCompany === null || $linkedExpert === null) && $speakers !== []) {
            foreach ($speakers as $speaker) {
                if (!is_object($speaker)) {
                    continue;
                }

                $speakerId = (int) ($speaker->id ?? 0);

                if ($linkedCompany === null) {
                    $speakerLinkedCompanyId = (int) ($speaker->linked_company_id ?? 0);
                    if ($speakerLinkedCompanyId > 0) {
                        $linkedCompany = $db->getLinkedCompany($speakerLinkedCompanyId);
                    }
                    if ($linkedCompany === null && $speakerId > 0) {
                        $linkedCompany = $db->findLinkedCompanyBySpeakerId($speakerId);
                    }
                }

                if ($linkedExpert === null) {
                    $speakerLinkedExpertId = (int) ($speaker->linked_expert_id ?? 0);
                    if ($speakerLinkedExpertId > 0) {
                        $linkedExpert = $db->getLinkedExpert($speakerLinkedExpertId);
                    }
                    if ($linkedExpert === null && $speakerId > 0) {
                        $linkedExpert = $db->findLinkedExpertBySpeakerId($speakerId);
                    }
                }

                if ($linkedCompany !== null && $linkedExpert !== null) {
                    break;
                }
            }
        }

        if ($linkedCompany === null && $linkedExpert !== null) {
            $expertLinkedCompanyId = (int) ($linkedExpert->linked_company_id ?? 0);
            if ($expertLinkedCompanyId > 0) {
                $linkedCompany = $db->getLinkedCompany($expertLinkedCompanyId);
            }
        }
        if ($linkedExpert === null && $linkedCompany !== null) {
            $companyLinkedExpertId = (int) ($linkedCompany->linked_expert_id ?? 0);
            if ($companyLinkedExpertId > 0) {
                $linkedExpert = $db->getLinkedExpert($companyLinkedExpertId);
            }
        }

        $this->renderWithTheme('single-event', [
            'event' => $event,
            'speakers' => $speakers,
            'linkedCompany' => $linkedCompany,
            'linkedExpert' => $linkedExpert,
            'settings' => $db->getSettings(),
        ]);
    }

    public function archiveSpeakers(): void
    {
        $db = CMS_365NET_Events_Database::instance();
        $search = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        $page = $this->pageNumber();
        $speakerArgs = ['status' => 'published', 'search' => $search];
        $total = $db->countSpeakers($speakerArgs);
        $pagination = $this->paginationData($page, $total, self::PUBLIC_PER_PAGE);
        if ($page > (int) $pagination['total_pages']) {
            $this->render404('Speakerseite nicht gefunden.', '/speakers');
            return;
        }
        $speakerArgs['limit'] = self::PUBLIC_PER_PAGE;
        $speakerArgs['offset'] = ((int) $pagination['current'] - 1) * self::PUBLIC_PER_PAGE;
        $speakers = $db->getSpeakers($speakerArgs);

        $this->renderWithTheme('archive-speakers', [
            'speakers' => $speakers,
            'settings' => $db->getSettings(),
            'filters' => ['q' => $search],
            'pagination' => $pagination,
        ]);
    }

    public function singleSpeaker(string $slug = ''): void
    {
        $slug = $slug !== '' ? $slug : (string) ($_GET['slug'] ?? '');
        $db = CMS_365NET_Events_Database::instance();
        $speaker = $db->getSpeakerBySlug($this->cleanSlug($slug));
        if (!$speaker || (string) $speaker->status !== 'published') {
            $this->render404('Speaker nicht gefunden.', '/speakers');
            return;
        }

        $events = $db->getEventsForSpeaker((int) $speaker->id);
        CMS\Hooks::doAction('cms_365net_speaker_viewed', (int) $speaker->id);

        $linkedCompany = $db->getLinkedCompany(isset($speaker->linked_company_id) ? (int) $speaker->linked_company_id : null);
        $linkedExpert = $db->getLinkedExpert(isset($speaker->linked_expert_id) ? (int) $speaker->linked_expert_id : null);

        $speakerId = (int) ($speaker->id ?? 0);
        if ($linkedCompany === null && $speakerId > 0) {
            $linkedCompany = $db->findLinkedCompanyBySpeakerId($speakerId);
        }
        if ($linkedExpert === null && $speakerId > 0) {
            $linkedExpert = $db->findLinkedExpertBySpeakerId($speakerId);
        }

        $speakerSignals = [
            'display_name' => (string) ($speaker->display_name ?? ''),
            'first_name' => (string) ($speaker->first_name ?? ''),
            'last_name' => (string) ($speaker->last_name ?? ''),
            'company' => (string) ($speaker->company ?? ''),
            'email' => (string) ($speaker->email ?? ''),
            'website' => (string) ($speaker->website ?? ''),
        ];
        if ($linkedCompany === null) {
            $linkedCompany = $db->detectLinkedCompanyBySignals($speakerSignals);
        }
        if ($linkedExpert === null) {
            $linkedExpert = $db->detectLinkedExpertBySignals($speakerSignals);
        }

        if ($linkedCompany === null && $linkedExpert !== null) {
            $expertLinkedCompanyId = (int) ($linkedExpert->linked_company_id ?? 0);
            if ($expertLinkedCompanyId > 0) {
                $linkedCompany = $db->getLinkedCompany($expertLinkedCompanyId);
            }
        }
        if ($linkedExpert === null && $linkedCompany !== null) {
            $companyLinkedExpertId = (int) ($linkedCompany->linked_expert_id ?? 0);
            if ($companyLinkedExpertId > 0) {
                $linkedExpert = $db->getLinkedExpert($companyLinkedExpertId);
            }
        }

        $this->renderWithTheme('single-speaker', [
            'speaker' => $speaker,
            'relatedEvents' => $events,
            'linkedCompany' => $linkedCompany,
            'linkedExpert' => $linkedExpert,
            'settings' => $db->getSettings(),
        ]);
    }

    public function adminDashboard(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $upcoming = [];
        try {
            $upcoming = CMS_365NET_Events_Database::instance()->getEvents([
                'date_mode' => 'future',
                'from' => date('Y-m-d'),
                'limit' => 5,
            ]);
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('admin.dashboard', $e);
        }
        CMS_365NET_Events_Admin::instance()->renderDashboard($upcoming);
    }

    public function adminEvents(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $showPast = (string) ($_GET['past'] ?? '') === '1';
        $events = CMS_365NET_Events_Database::instance()->getEvents([
            'search' => $this->cleanQuery((string) ($_GET['q'] ?? '')),
            'date_mode' => $showPast ? 'past' : 'future',
            'from' => date('Y-m-d'),
            'before' => date('Y-m-d'),
            'limit' => 300,
            'order' => $showPast ? 'date_desc' : '',
        ]);
        CMS_365NET_Events_Admin::instance()->renderEventsList($events, $showPast);
    }

    public function adminEventNew(): void
    {
        if ($this->requireAdmin()) {
            $db = CMS_365NET_Events_Database::instance();
            CMS_365NET_Events_Admin::instance()->renderEventForm(
                null,
                $db->getSpeakers(['limit' => 300]),
                [],
                $db->getAvailableCompanies(300),
                $db->getAvailableExperts(300),
                $db->getTaxonomyOptions()
            );
        }
    }

    public function adminEventEdit(string $id = ''): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $event = CMS_365NET_Events_Database::instance()->getEvent((int) $id);
        if (!$event) {
            CMS\Router::instance()->redirect('/admin/365netevents?error=not_found');
            return;
        }

        $db = CMS_365NET_Events_Database::instance();
        CMS_365NET_Events_Admin::instance()->renderEventForm(
            $event,
            $db->getSpeakers(['limit' => 300]),
            $db->getSpeakersForEvent((int) $event->id),
            $db->getAvailableCompanies(300),
            $db->getAvailableExperts(300),
            $db->getTaxonomyOptions()
        );
    }

    public function adminEventSave(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_event_form')) {
            CMS\Router::instance()->redirect('/admin/365netevents?error=csrf');
            return;
        }

        try {
            // „id“ fehlt bei Neuanlagen regulär und wird deshalb nicht erwartet.
            $expectedKeys = ['title', 'slug', 'description_json', 'image_url', 'location', 'organizer', 'category', 'website', 'status'];
            $missingKeys = array_values(array_filter($expectedKeys, static fn(string $key): bool => !array_key_exists($key, $_POST)));
            if ($missingKeys !== []) {
                CMS_365NET_Events::instance()->log(
                    'adminEventSaveMissingPostKeys',
                    new RuntimeException(
                        'Missing POST keys: ' . implode(', ', $missingKeys)
                        . ' | posted keys count=' . count($_POST)
                    )
                );
            }

            $id = CMS_365NET_Events_Database::instance()->saveEvent($_POST);
            if ($id === false) {
                CMS_365NET_Events::instance()->log('adminEventSaveValidation', new RuntimeException('Event save returned false. Posted title length: ' . strlen((string) ($_POST['title'] ?? ''))));
                $target = isset($_POST['id']) && (int) $_POST['id'] > 0 ? '/admin/365netevents/edit/' . (int) $_POST['id'] : '/admin/365netevents/new';
                CMS\Router::instance()->redirect($target . '?error=required_title');
                return;
            }
            try {
                CMS_365NET_Events_Database::instance()->saveEventSpeakers($id, (array) ($_POST['speaker_ids'] ?? []));
                CMS\Router::instance()->redirect('/admin/365netevents/edit/' . $id . '?saved=1');
            } catch (Throwable $relationError) {
                CMS_365NET_Events::instance()->log('adminEventSaveRelations', $relationError);
                CMS\Router::instance()->redirect('/admin/365netevents/edit/' . $id . '?saved=1&warning=relations');
            }
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminEventSave', $e);
            $target = isset($_POST['id']) && (int) $_POST['id'] > 0 ? '/admin/365netevents/edit/' . (int) $_POST['id'] : '/admin/365netevents/new';
            CMS\Router::instance()->redirect($target . '?error=save');
        }
    }

    public function adminSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        CMS_365NET_Events_Admin::instance()->renderSettingsForm(CMS_365NET_Events_Database::instance()->getSettings());
    }

    public function adminTaxonomies(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        CMS_365NET_Events_Admin::instance()->renderTaxonomiesForm(CMS_365NET_Events_Database::instance()->getSettings());
    }

    public function adminTaxonomiesSave(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_taxonomies_form')) {
            CMS\Router::instance()->redirect('/admin/365netevents/taxonomies?error=csrf');
            return;
        }

        try {
            CMS_365NET_Events_Database::instance()->saveTaxonomyOptions($_POST);
            CMS\Router::instance()->redirect('/admin/365netevents/taxonomies?saved=1');
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminTaxonomiesSave', $e);
            CMS\Router::instance()->redirect('/admin/365netevents/taxonomies?error=save');
        }
    }

    public function adminSettingsSave(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_settings_form')) {
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=csrf');
            return;
        }

        try {
            CMS_365NET_Events_Database::instance()->saveSettings($_POST);
            CMS\Router::instance()->redirect('/admin/365netevents/settings?saved=1');
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSettingsSave', $e);
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=save');
        }
    }

    public function adminSettingsBackfillDescriptions(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_settings_backfill')) {
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=csrf');
            return;
        }

        try {
            $updated = CMS_365NET_Events_Database::instance()->backfillSeedEventDescriptionsFromSeed();
            CMS\Router::instance()->redirect('/admin/365netevents/settings?backfilled=1&updated=' . max(0, $updated));
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSettingsBackfillDescriptions', $e);
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=save');
        }
    }

    public function adminSettingsBackfillSpeakers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_settings_speaker_backfill')) {
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=csrf');
            return;
        }

        try {
            $result = CMS_365NET_Events_Database::instance()->backfillSpeakerProfilesFromGoogle();
            $query = [
                'speaker_backfilled' => '1',
                'speaker_updated' => max(0, (int) ($result['updated'] ?? 0)),
                'speaker_skipped' => max(0, (int) ($result['skipped'] ?? 0)),
                'speaker_failed' => max(0, (int) ($result['failed'] ?? 0)),
            ];
            if (!(bool) ($result['configured'] ?? false)) {
                $query['speaker_config'] = '0';
            }

            CMS\Router::instance()->redirect('/admin/365netevents/settings?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSettingsBackfillSpeakers', $e);
            CMS\Router::instance()->redirect('/admin/365netevents/settings?error=save');
        }
    }

    public function adminEventDelete(string $id = ''): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_admin')) {
            CMS\Router::instance()->redirect('/admin/365netevents?error=csrf');
            return;
        }
        CMS_365NET_Events_Database::instance()->deleteEvent((int) $id);
        CMS\Router::instance()->redirect('/admin/365netevents?deleted=1');
    }

    public function adminLinkSync(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_admin')) {
            CMS\Router::instance()->redirect('/admin/365netevents?error=csrf');
            return;
        }

        try {
            $db = CMS_365NET_Events_Database::instance();
            $db->purgeNonPersonSpeakers();
            $db->autoLinkExistingRecords();
            CMS\Router::instance()->redirect('/admin/365netevents?synced=1');
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminLinkSync', $e);
            CMS\Router::instance()->redirect('/admin/365netevents?error=save');
        }
    }

    public function adminSpeakers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $search = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        $sort = $this->parseAdminSort((string) ($_GET['sort'] ?? 'az'));
        $speakers = CMS_365NET_Events_Database::instance()->getSpeakers([
            'search' => $search,
            'order' => $sort,
            'limit' => 300,
        ]);
        CMS_365NET_Events_Admin::instance()->renderSpeakersList($speakers, $search, $sort);
    }

    public function adminSpeakerNew(): void
    {
        if ($this->requireAdmin()) {
            $db = CMS_365NET_Events_Database::instance();
            CMS_365NET_Events_Admin::instance()->renderSpeakerForm(null, $db->getAvailableCompanies(300), $db->getAvailableExperts(300), $db->getTaxonomyOptions());
        }
    }

    public function adminSpeakerEdit(string $id = ''): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $speaker = CMS_365NET_Events_Database::instance()->getSpeaker((int) $id);
        if (!$speaker) {
            CMS\Router::instance()->redirect('/admin/365netevents/speakers?error=not_found');
            return;
        }
        $db = CMS_365NET_Events_Database::instance();
        CMS_365NET_Events_Admin::instance()->renderSpeakerForm($speaker, $db->getAvailableCompanies(300), $db->getAvailableExperts(300), $db->getTaxonomyOptions());
    }

    public function adminSpeakerSave(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_speaker_form')) {
            CMS\Router::instance()->redirect('/admin/365netevents/speakers?error=csrf');
            return;
        }

        try {
            // „id“ fehlt bei Neuanlagen regulär und wird deshalb nicht erwartet.
            $expectedKeys = ['display_name', 'slug', 'bio_json', 'avatar_url', 'theme_image_url', 'topic', 'website', 'status'];
            $missingKeys = array_values(array_filter($expectedKeys, static fn(string $key): bool => !array_key_exists($key, $_POST)));
            if ($missingKeys !== []) {
                CMS_365NET_Events::instance()->log(
                    'adminSpeakerSaveMissingPostKeys',
                    new RuntimeException(
                        'Missing POST keys: ' . implode(', ', $missingKeys)
                        . ' | posted keys count=' . count($_POST)
                    )
                );
            }

            $id = CMS_365NET_Events_Database::instance()->saveSpeaker($_POST);
            if ($id === false) {
                CMS_365NET_Events::instance()->log('adminSpeakerSaveValidation', new RuntimeException('Speaker save returned false. Posted display_name length: ' . strlen((string) ($_POST['display_name'] ?? ''))));
                $target = isset($_POST['id']) && (int) $_POST['id'] > 0 ? '/admin/365netevents/speakers/edit/' . (int) $_POST['id'] : '/admin/365netevents/speakers/new';
                CMS\Router::instance()->redirect($target . '?error=required_title');
                return;
            }
            CMS\Router::instance()->redirect('/admin/365netevents/speakers/edit/' . $id . '?saved=1');
        } catch (Throwable $e) {
            CMS_365NET_Events::instance()->log('adminSpeakerSave', $e);
            CMS\Router::instance()->redirect('/admin/365netevents/speakers?error=save');
        }
    }

    public function adminSpeakerDelete(string $id = ''): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyCsrf('365net_admin')) {
            CMS\Router::instance()->redirect('/admin/365netevents/speakers?error=csrf');
            return;
        }
        CMS_365NET_Events_Database::instance()->deleteSpeaker((int) $id);
        CMS\Router::instance()->redirect('/admin/365netevents/speakers?deleted=1');
    }

    private function renderWithTheme(string $template, array $context): void
    {
        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        CMS_365NET_Events_Template_Loader::instance()->render($template, $context);
        $theme->getFooter();
    }

    private function render404(string $message, string $backUrl): void
    {
        http_response_code(404);
        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        echo '<main class="cms-events-public"><div class="cms-events-container"><h1>404</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a class="cms-events-btn" href="' . htmlspecialchars(rtrim((string) SITE_URL, '/') . $backUrl, ENT_QUOTES, 'UTF-8') . '">Zurück</a></div></main>';
        $theme->getFooter();
    }

    private function requireAdmin(): bool
    {
        if (!CMS\Auth::instance()->isAdmin()) {
            CMS\Router::instance()->redirect('/login');
            return false;
        }

        return true;
    }

    private function verifyCsrf(string $action): bool
    {
        return CMS\Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), $action);
    }

    private function cleanQuery(string $value): string
    {
        $value = trim(strip_tags($value));
        return function_exists('mb_substr') ? (string) mb_substr($value, 0, 120, 'UTF-8') : substr($value, 0, 120);
    }

    private function pageNumber(): int
    {
        $raw = $_GET['page'] ?? ($_GET['paged'] ?? 1);
        if (is_array($raw)) {
            return 1;
        }

        return max(1, min(9999, (int) $raw));
    }

    /** @return array{current:int,total:int,per_page:int,total_pages:int} */
    private function paginationData(int $page, int $total, int $perPage): array
    {
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        return [
            'current' => max(1, min($page, $totalPages)),
            'total' => max(0, $total),
            'per_page' => max(1, $perPage),
            'total_pages' => $totalPages,
        ];
    }

    private function cleanSlug(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '', $value) ?? '';
        return substr($value, 0, 255);
    }

    private function parseAdminSort(string $sort): string
    {
        $sort = trim($sort);
        return in_array($sort, ['az', 'za', 'date_old_new', 'date_new_old'], true) ? $sort : 'az';
    }
}

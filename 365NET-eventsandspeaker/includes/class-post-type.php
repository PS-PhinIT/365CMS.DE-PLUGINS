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

        // Alte Admin-Adressen (bis 3.2.0): Lesezeichen und offene Formulare auf die Plugin-Seiten des
        // Core-AdminRouters umleiten (GET 302, POST 307 mit Formulardaten).
        $legacyGet = [
            '/admin/365netevents' => 'legacyEvents',
            '/admin/365netevents/dashboard' => 'legacyDashboard',
            '/admin/365netevents/new' => 'legacyEventNew',
            '/admin/365netevents/edit/:id' => 'legacyEventEdit',
            '/admin/365netevents/taxonomies' => 'legacyTaxonomies',
            '/admin/365netevents/settings' => 'legacySettings',
            '/admin/365netevents/speakers' => 'legacySpeakers',
            '/admin/365netevents/speakers/new' => 'legacySpeakerNew',
            '/admin/365netevents/speakers/edit/:id' => 'legacySpeakerEdit',
        ];
        foreach ($legacyGet as $path => $method) {
            $router->addRoute('GET', $path, [$this, $method]);
        }
        $legacyPost = [
            '/admin/365netevents/save' => 'legacyEventSave',
            '/admin/365netevents/delete/:id' => 'legacyEventDelete',
            '/admin/365netevents/link-sync' => 'legacyLinkSync',
            '/admin/365netevents/taxonomies/save' => 'legacyTaxonomiesSave',
            '/admin/365netevents/settings/save' => 'legacySettingsSave',
            '/admin/365netevents/settings/backfill-descriptions' => 'legacyBackfillDescriptions',
            '/admin/365netevents/settings/backfill-speakers' => 'legacyBackfillSpeakers',
            '/admin/365netevents/speakers/save' => 'legacySpeakerSave',
            '/admin/365netevents/speakers/delete/:id' => 'legacySpeakerDelete',
        ];
        foreach ($legacyPost as $path => $method) {
            $router->addRoute('POST', $path, [$this, $method]);
        }
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

        // Top-Partner-Kennzeichen gebündelt laden (vorher je Event mehrere Abfragen).
        $companyIds = [];
        foreach ($events as $event) {
            $companyId = (int) ($event->linked_company_id ?? 0);
            if ($companyId <= 0) {
                $companyId = (int) ($db->detectCompanyIdBySignals([
                    'organizer' => (string) ($event->organizer ?? ''),
                    'company' => (string) ($event->organizer ?? ''),
                    'contact_name' => (string) ($event->contact_name ?? ''),
                    'website' => (string) ($event->website ?? ''),
                    'registration_url' => (string) ($event->registration_url ?? ''),
                    'ticket_url' => (string) ($event->ticket_url ?? ''),
                ]) ?? 0);
            }
            $event->resolved_company_id = $companyId;
            if ($companyId > 0) {
                $companyIds[] = $companyId;
            }
        }
        $flags = $db->getTopPartnerFlags($companyIds);
        foreach ($events as $event) {
            $event->linked_company_is_top_partner = !empty($flags[(int) ($event->resolved_company_id ?? 0)]) ? 1 : 0;
        }

        $this->setSeoMeta([
            'title' => (string) ($settings['archive_title'] ?? 'Events'),
            'description' => $this->plainText((string) ($settings['archive_description'] ?? ''), 300),
            'canonical_url' => rtrim((string) SITE_URL, '/') . '/events' . ($showPast ? '?past=1' : ''),
            'robots_index' => $search === '' && $page === 1,
            'robots_follow' => true,
        ]);

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

        // Öffentlich nur veröffentlichte Speaker und aktive Firmen/Experts zeigen (Entwürfe nicht preisgeben).
        $speakers = array_values(array_filter($speakers, static fn (mixed $speaker): bool => is_object($speaker)
            && (!isset($speaker->status) || (string) $speaker->status === 'published')));
        $linkedCompany = $this->publicLink($linkedCompany);
        $linkedExpert = $this->publicLink($linkedExpert);

        $this->setSeoMeta([
            'title' => (string) (($event->seo_title ?? '') ?: ($event->title ?? '')),
            'description' => $this->plainText((string) (($event->seo_description ?? '') ?: ($event->excerpt ?? '') ?: ($event->description ?? '')), 300),
            'canonical_url' => rtrim((string) SITE_URL, '/') . '/events/' . rawurlencode((string) $event->slug),
            'og_type' => 'article',
            'og_image' => (string) (($event->og_image_url ?? '') ?: ($event->image_url ?? '')),
            'schema_type' => 'Event',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

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
        $settings = $db->getSettings();

        $this->setSeoMeta([
            'title' => (string) ($settings['speaker_archive_title'] ?? 'Speaker'),
            'description' => $this->plainText((string) ($settings['speaker_archive_description'] ?? ''), 300),
            'canonical_url' => rtrim((string) SITE_URL, '/') . '/speakers',
            'robots_index' => $search === '' && $page === 1,
            'robots_follow' => true,
        ]);

        $this->renderWithTheme('archive-speakers', [
            'speakers' => $speakers,
            'settings' => $settings,
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

        $linkedCompany = $this->publicLink($linkedCompany);
        $linkedExpert = $this->publicLink($linkedExpert);

        $this->setSeoMeta([
            'title' => (string) (($speaker->seo_title ?? '') ?: ($speaker->display_name ?? '')),
            'description' => $this->plainText((string) (($speaker->seo_description ?? '') ?: ($speaker->bio ?? '') ?: ($speaker->topic ?? '')), 300),
            // /event-speakers/… ist ein Alias – kanonisch ist /speakers/….
            'canonical_url' => rtrim((string) SITE_URL, '/') . '/speakers/' . rawurlencode((string) $speaker->slug),
            'og_type' => 'profile',
            'og_image' => (string) (($speaker->og_image_url ?? '') ?: ($speaker->avatar_url ?? '')),
            'schema_type' => 'Person',
            'robots_index' => true,
            'robots_follow' => true,
        ]);

        $this->renderWithTheme('single-speaker', [
            'speaker' => $speaker,
            'relatedEvents' => $events,
            'linkedCompany' => $linkedCompany,
            'linkedExpert' => $linkedExpert,
            'settings' => $db->getSettings(),
        ]);
    }

    // --- Alte Admin-Adressen -------------------------------------------------------------------

    public function legacyEvents(): void
    {
        $query = [];
        if ((string) ($_GET['past'] ?? '') === '1') {
            $query['time'] = 'past';
        }
        $q = $this->cleanQuery((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $query['q'] = $q;
        }
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, $query);
    }

    public function legacyDashboard(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::MENU_SLUG);
    }

    public function legacyEventNew(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, ['action' => 'new']);
    }

    public function legacyEventEdit(string $id = ''): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, ['action' => 'edit', 'id' => max(0, (int) $id)]);
    }

    public function legacyTaxonomies(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_TAXONOMIES);
    }

    public function legacySettings(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SETTINGS);
    }

    public function legacySpeakers(): void
    {
        $query = [];
        foreach (['q', 'sort'] as $key) {
            $value = $this->cleanQuery((string) ($_GET[$key] ?? ''));
            if ($value !== '') {
                $query[$key] = $value;
            }
        }
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SPEAKERS, $query);
    }

    public function legacySpeakerNew(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SPEAKERS, ['action' => 'new']);
    }

    public function legacySpeakerEdit(string $id = ''): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SPEAKERS, ['action' => 'edit', 'id' => max(0, (int) $id)]);
    }

    public function legacyEventSave(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, ['do' => 'save'], true);
    }

    public function legacyEventDelete(string $id = ''): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, ['do' => 'delete', 'id' => max(0, (int) $id)], true);
    }

    public function legacyLinkSync(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_EVENTS, ['do' => 'link_sync'], true);
    }

    public function legacyTaxonomiesSave(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_TAXONOMIES, ['do' => 'save'], true);
    }

    public function legacySettingsSave(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SETTINGS, ['do' => 'save'], true);
    }

    public function legacyBackfillDescriptions(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SETTINGS, ['do' => 'backfill_descriptions'], true);
    }

    public function legacyBackfillSpeakers(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SETTINGS, ['do' => 'backfill_speakers'], true);
    }

    public function legacySpeakerSave(): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SPEAKERS, ['do' => 'save'], true);
    }

    public function legacySpeakerDelete(string $id = ''): void
    {
        $this->legacyRedirect(CMS_365NET_Events_Admin::PAGE_SPEAKERS, ['do' => 'delete', 'id' => max(0, (int) $id)], true);
    }

    /** @param array<string, scalar> $query */
    private function legacyRedirect(string $page, array $query = [], bool $post = false): void
    {
        $target = CMS_365NET_Events_Admin::url($page, $query);
        if (method_exists('CMS\\Security', 'safeLocalPath')) {
            $target = CMS\Security::safeLocalPath($target, '/admin/plugins/' . CMS_365NET_Events_Admin::MENU_SLUG);
        }

        header('Location: ' . rtrim((string) SITE_URL, '/') . $target, true, $post ? 307 : 302);
        exit;
    }

    // --- SEO ------------------------------------------------------------------------------------

    /** @param array<string, mixed> $meta */
    private function setSeoMeta(array $meta): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }

        try {
            $payload = array_filter($meta, static fn(mixed $value): bool => $value !== null && $value !== '');
            if (isset($payload['og_image']) && is_string($payload['og_image']) && str_starts_with($payload['og_image'], '/') && !str_starts_with($payload['og_image'], '//')) {
                $payload['og_image'] = rtrim((string) SITE_URL, '/') . $payload['og_image'];
            }
            CMS\Services\SEOService::getInstance()->setRequestMeta($payload);
        } catch (\Throwable $e) {
            CMS_365NET_Events::instance()->log('seo.meta', $e);
        }
    }

    private function plainText(string $value, int $length): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
            return rtrim((string) mb_substr($value, 0, $length - 1, 'UTF-8')) . '…';
        }

        return strlen($value) > $length ? rtrim(substr($value, 0, $length - 1)) . '…' : $value;
    }

    private function renderWithTheme(string $template, array $context): void
    {
        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        CMS_365NET_Events_Template_Loader::instance()->render($template, $context);
        $theme->getFooter();
    }

    /** Verknüpfte Firma/Expert nur öffentlich zeigen, wenn sie in Experts & Companies aktiv ist. */
    private function publicLink(?object $row): ?object
    {
        if ($row === null) {
            return null;
        }
        $status = isset($row->status) ? (string) $row->status : 'active';

        return in_array($status, ['active', 'published', ''], true) ? $row : null;
    }

    private function render404(string $message, string $backUrl): void
    {
        http_response_code(404);
        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        echo '<main class="cms-events-public"><div class="cms-events-container"><h1>404</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a class="cms-events-btn" href="' . htmlspecialchars(rtrim((string) SITE_URL, '/') . $backUrl, ENT_QUOTES, 'UTF-8') . '">Zurück</a></div></main>';
        $theme->getFooter();
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
}

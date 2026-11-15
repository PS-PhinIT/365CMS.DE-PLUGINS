<?php
/**
 * Admin-Bereich von 365CMS | Experts & Companies (Layout-Vertrag 365CMS 4.0.0).
 *
 * Alle Seiten laufen über den AdminRouter (`/admin/plugins/experts-companie/<unterseite>`); die einheitliche
 * Navigation (PhinIT_Plugin_Admin_Nav) setzt Kopfzeile, KPI-Karten und Plugin-Menü, die Callbacks geben nur
 * den Inhalt der rechten Spalte aus (Header → Toolbar/Filter → Inhalt). Views liegen unter admin/views/.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Experts_And_Companie_Admin
{
    public const MENU_SLUG = 'experts-companie';
    public const PAGE_PUBLIC = 'experts-companie-public';
    public const PAGE_EXPERTS = 'experts-companie-experts';
    public const PAGE_COMPANIES = 'experts-companie-companies';
    public const PAGE_SETTINGS = 'experts-companie-settings';
    public const PAGE_TRANSFER = 'experts-companie-transfer';
    public const PAGE_EXPERT_NEW = 'experts-companie-expert-new';
    public const PAGE_COMPANY_NEW = 'experts-companie-company-new';
    public const TITLE = '365CMS | Experts & Companies';
    public const CAPABILITY = 'manage_options';
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
                'icon' => 'ti ti-heart-handshake',
                'title' => self::TITLE,
                'menu_title' => self::TITLE,
                'version' => defined('CMS_365NET_EXCOMP_VERSION') ? (string) CMS_365NET_EXCOMP_VERSION : '',
                'description' => 'Expert:innen und Unternehmen für /experts, /companies und den gemeinsamen Hub pflegen.',
                'status' => [self::class, 'adminStatus'],
                'stats' => [self::class, 'adminStats'],
                'dashboard' => [self::class, 'adminDashboard'],
                'hidden' => [self::PAGE_EXPERT_NEW, self::PAGE_COMPANY_NEW],
                'parents' => [self::PAGE_EXPERT_NEW => self::PAGE_EXPERTS, self::PAGE_COMPANY_NEW => self::PAGE_COMPANIES],
                'icons' => [
                    self::PAGE_PUBLIC => 'ti ti-world',
                    self::PAGE_EXPERTS => 'ti ti-user-star',
                    self::PAGE_COMPANIES => 'ti ti-building',
                    self::PAGE_TRANSFER => 'ti ti-file-import',
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

        add_menu_page(self::TITLE, self::TITLE, self::CAPABILITY, self::MENU_SLUG, [self::class, 'pageOverview'], 'ti ti-heart-handshake', 46);

        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(self::MENU_SLUG, 'Übersicht', 'Übersicht', self::CAPABILITY, self::MENU_SLUG, [self::class, 'pageOverview']);
        add_submenu_page(self::MENU_SLUG, 'Public Hub', 'Public Hub', self::CAPABILITY, self::PAGE_PUBLIC, [self::class, 'pagePublic']);
        add_submenu_page(self::MENU_SLUG, 'Experts verwalten', 'Experts', self::CAPABILITY, self::PAGE_EXPERTS, [self::class, 'pageExperts']);
        add_submenu_page(self::MENU_SLUG, 'Companies verwalten', 'Companies', self::CAPABILITY, self::PAGE_COMPANIES, [self::class, 'pageCompanies']);
        add_submenu_page(self::MENU_SLUG, 'Import & Export', 'Import & Export', self::CAPABILITY, self::PAGE_TRANSFER, [self::class, 'pageTransfer']);
        add_submenu_page(self::MENU_SLUG, 'Einstellungen', 'Einstellungen', self::CAPABILITY, self::PAGE_SETTINGS, [self::class, 'pageSettings']);
        add_submenu_page(self::MENU_SLUG, 'Expert anlegen', 'Expert anlegen', self::CAPABILITY, self::PAGE_EXPERT_NEW, [self::class, 'pageExpertNew']);
        add_submenu_page(self::MENU_SLUG, 'Company anlegen', 'Company anlegen', self::CAPABILITY, self::PAGE_COMPANY_NEW, [self::class, 'pageCompanyNew']);
    }

    /**
     * Adresse einer Admin-Unterseite (Pfad-Route des AdminRouters). Die Navigation schreibt sie in der Ausgabe
     * auf ihre ?view=-Form um; alte Pfade leitet sie weiter.
     *
     * @param array<string, scalar> $query
     */
    public static function url(string $page = '', array $query = []): string
    {
        $url = '/admin/plugins/' . self::MENU_SLUG . ($page !== '' && $page !== self::MENU_SLUG ? '/' . $page : '');
        $query = array_filter($query, static fn (mixed $value): bool => $value !== '' && $value !== null);

        return $query !== [] ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    /** @return array<string, string> Kurzbeschreibungen der Bereiche */
    private static function sectionDescriptions(): array
    {
        return [
            self::PAGE_PUBLIC => 'Öffentliche Übersichten, Hauptmenü-Link und Suchmaschinen-Anbindung prüfen.',
            self::PAGE_EXPERTS => 'Expert:innen suchen, anlegen, bearbeiten und löschen.',
            self::PAGE_COMPANIES => 'Unternehmen, Partner-Level und Beschreibungen pflegen.',
            self::PAGE_TRANSFER => 'Experts und Companies als CSV exportieren oder importieren.',
            self::PAGE_SETTINGS => 'Navigation, Texte, Abstände und Farben der Publicseiten.',
        ];
    }

    private static function db(): ?CMS_365NET_Experts_And_Companie_Database
    {
        if (!class_exists('CMS\\Database') || !class_exists('CMS_365NET_Experts_And_Companie_Database', false)) {
            return null;
        }

        return CMS_365NET_Experts_And_Companie_Database::instance();
    }

    /* =====================================================================
     * Kopfzeile, Kennzahlen, Übersicht
     * =================================================================== */

    /** @return array{label:string,tone:string} */
    public static function adminStatus(): array
    {
        $db = self::db();
        if ($db === null) {
            return ['label' => 'Datenbank fehlt', 'tone' => 'danger'];
        }

        return $db->isSchemaCurrent()
            ? ['label' => 'Aktiv', 'tone' => 'success']
            : ['label' => 'Aktualisierung ausstehend', 'tone' => 'warning'];
    }

    /**
     * Kennzahlen für die Kopfzeile: zwei gebündelte COUNT-Abfragen.
     *
     * @return list<array<string, mixed>>
     */
    public static function adminStats(): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }

        try {
            $experts = $db->countByStatus('experts');
            $companies = $db->countByStatus('companies');
        } catch (\Throwable $e) {
            error_log('CMS 365NET Experts & Companies [adminStats]: ' . $e->getMessage());
            return [];
        }
        $visible = $experts['active'] + $companies['active'];
        $hidden = $experts['inactive'] + $companies['inactive'];

        return [
            ['label' => 'Experts', 'value' => $experts['total'], 'icon' => 'ti ti-user-star', 'tone' => 'info', 'url' => self::url(self::PAGE_EXPERTS)],
            ['label' => 'Companies', 'value' => $companies['total'], 'icon' => 'ti ti-building', 'tone' => 'info', 'url' => self::url(self::PAGE_COMPANIES)],
            ['label' => 'Öffentlich sichtbar', 'value' => $visible, 'icon' => 'ti ti-eye', 'tone' => 'success', 'url' => self::url(self::PAGE_PUBLIC), 'hint' => 'Experts und Companies mit Status „aktiv“'],
            ['label' => 'Ausgeblendet', 'value' => $hidden, 'icon' => 'ti ti-eye-off', 'tone' => $hidden > 0 ? 'warning' : 'muted', 'url' => self::url(self::PAGE_EXPERTS, ['status' => 'inactive']), 'hint' => 'Experts und Companies mit Status „inaktiv“'],
        ];
    }

    /**
     * Übersicht (Layout-Vertrag 365CMS 4.0.0): Schnellaktionen, Status-/System-Check und zuletzt bearbeitete Einträge.
     *
     * @return array<string, mixed>
     */
    public static function adminDashboard(): array
    {
        $actions = [
            ['label' => 'Neuer Expert', 'url' => self::url(self::PAGE_EXPERT_NEW), 'icon' => 'ti ti-user-plus', 'primary' => true, 'description' => 'Profil mit Skills, Zertifizierungen und Firma anlegen.'],
            ['label' => 'Neue Company', 'url' => self::url(self::PAGE_COMPANY_NEW), 'icon' => 'ti ti-building-plus', 'description' => 'Unternehmen mit Partner-Level und Beschreibung anlegen.'],
            ['label' => 'CSV importieren', 'url' => self::url(self::PAGE_TRANSFER), 'icon' => 'ti ti-file-import', 'description' => 'Experts oder Companies aus einer CSV-Datei übernehmen.'],
            ['label' => 'Texte & Layout', 'url' => self::url(self::PAGE_SETTINGS), 'icon' => 'ti ti-settings', 'description' => 'Navigation, Texte, Abstände und Farben der Publicseiten.'],
            ['label' => 'Öffentlicher Hub', 'url' => '/experts-companie', 'icon' => 'ti ti-world', 'external' => true, 'description' => 'Experts und Companies so prüfen, wie Besucher sie sehen.'],
        ];

        $db = self::db();
        if ($db === null) {
            return ['actions' => $actions, 'checks' => [['label' => 'Datenbank', 'status' => 'error', 'value' => 'nicht verfügbar']]];
        }

        $checks = [];
        $items = [];
        try {
            $stored = $db->storedSchemaVersion();
            $checks[] = $db->isSchemaCurrent()
                ? ['label' => 'Datenbanktabellen', 'status' => 'ok', 'value' => 'Schema ' . CMS_365NET_Experts_And_Companie_Database::SCHEMA_VERSION]
                : ['label' => 'Datenbanktabellen', 'status' => 'warning', 'value' => $stored === '' ? 'nicht angelegt' : 'Schema ' . $stored . ' → ' . CMS_365NET_Experts_And_Companie_Database::SCHEMA_VERSION, 'hint' => 'Wird beim nächsten Seitenaufruf bzw. bei der Plugin-Aktivierung angelegt und angehoben.'];

            $experts = $db->countByStatus('experts');
            $companies = $db->countByStatus('companies');
            $checks[] = ['label' => 'Öffentliche Experts', 'status' => $experts['active'] > 0 ? 'ok' : 'warning', 'value' => $experts['active'] . ' von ' . $experts['total'] . ' sichtbar', 'url' => '/experts'];
            $checks[] = ['label' => 'Öffentliche Companies', 'status' => $companies['active'] > 0 ? 'ok' : 'warning', 'value' => $companies['active'] . ' von ' . $companies['total'] . ' sichtbar', 'url' => '/companies'];

            $settings = $db->getSettings();
            $checks[] = ($settings['show_nav_link'] ?? '0') === '1'
                ? ['label' => 'Link im Hauptmenü', 'status' => 'ok', 'value' => (string) ($settings['nav_label'] ?? 'Experts & Companies'), 'url' => self::url(self::PAGE_SETTINGS)]
                : ['label' => 'Link im Hauptmenü', 'status' => 'muted', 'value' => 'ausgeschaltet', 'url' => self::url(self::PAGE_SETTINGS)];
        } catch (\Throwable $e) {
            error_log('CMS 365NET Experts & Companies [adminDashboard]: ' . $e->getMessage());
            $checks[] = ['label' => 'Datenbank', 'status' => 'error', 'value' => 'Abfrage fehlgeschlagen', 'hint' => 'Details stehen im Fehlerprotokoll.'];
        }

        $uploadDir = defined('UPLOAD_PATH') ? rtrim((string) UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'experts-companie' : '';
        $uploadWritable = $uploadDir !== '' && (is_dir($uploadDir) ? is_writable($uploadDir) : is_writable(dirname($uploadDir)));
        $checks[] = $uploadWritable
            ? ['label' => 'Bild-Uploads', 'status' => 'ok', 'value' => '/uploads/experts-companie beschreibbar']
            : ['label' => 'Bild-Uploads', 'status' => 'warning', 'value' => 'Upload-Ordner nicht beschreibbar', 'hint' => 'Profilbilder und Logos lassen sich dann nur über die Mediathek wählen.'];

        $checks[] = ['label' => 'Sitemap & Suche', 'status' => 'ok', 'value' => 'Detailseiten werden gemeldet', 'url' => self::url(self::PAGE_PUBLIC)];

        $checks[] = defined('CMS_365NET_EVENTS_VERSION')
            ? ['label' => 'Events & Speaker', 'status' => 'ok', 'value' => 'Speaker-Verknüpfung verfügbar']
            : ['label' => 'Events & Speaker', 'status' => 'muted', 'value' => 'Plugin nicht aktiv', 'hint' => 'Speaker lassen sich dann nicht mit Experts verknüpfen.'];

        try {
            $rows = [];
            foreach ($db->getExpertsAdminList(['sort' => 'date_new_old'], 6, 0) as $expert) {
                $rows[] = [
                    'type' => 'Expert',
                    'title' => trim((string) ($expert->first_name ?? '') . ' ' . (string) ($expert->last_name ?? '')),
                    'url' => self::url(self::PAGE_EXPERTS, ['action' => 'edit', 'id' => (int) ($expert->id ?? 0)]),
                    'status' => (string) ($expert->status ?? ''),
                    'updated' => (string) ($expert->updated_at ?? ''),
                ];
            }
            foreach ($db->getCompaniesAdminList(['sort' => 'date_new_old'], 6, 0) as $company) {
                $rows[] = [
                    'type' => 'Company',
                    'title' => (string) ($company->name ?? ''),
                    'url' => self::url(self::PAGE_COMPANIES, ['action' => 'edit', 'id' => (int) ($company->id ?? 0)]),
                    'status' => (string) ($company->status ?? ''),
                    'updated' => (string) ($company->updated_at ?? ''),
                ];
            }
            usort($rows, static fn (array $a, array $b): int => strcmp($b['updated'], $a['updated']));
            foreach (array_slice($rows, 0, 6) as $row) {
                $updated = strtotime($row['updated']);
                $active = $row['status'] !== 'inactive';
                $items[] = [
                    'title' => $row['title'] !== '' ? $row['title'] : $row['type'],
                    'url' => $row['url'],
                    'meta' => $row['type'] . ($updated !== false ? ' · geändert ' . date('d.m.Y, H:i', $updated) : ''),
                    'badge' => $active ? 'Sichtbar' : 'Ausgeblendet',
                    'tone' => $active ? 'success' : 'muted',
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS 365NET Experts & Companies [adminDashboard activity]: ' . $e->getMessage());
        }

        return [
            'intro' => 'Experts und Companies bilden die Verzeichnisse /experts und /companies sowie den gemeinsamen Hub /experts-companie.',
            'actions' => $actions,
            'checks' => $checks,
            'activity' => [
                'heading' => 'Zuletzt bearbeitet',
                'items' => $items,
                'empty' => 'Noch keine Experts oder Companies vorhanden.',
                'more_url' => self::url(self::PAGE_EXPERTS, ['sort' => 'date_new_old']),
                'more_label' => 'Alle Experts',
            ],
        ];
    }

    /* =====================================================================
     * Seiten
     * =================================================================== */

    public static function pageOverview(): void
    {
        $db = self::db();
        if (self::isPost() && self::postAction() === 'seed_refresh') {
            self::guardPost('excomp_admin', self::url());
            if ($db !== null) {
                $db->ensureSchema(true);
            }
            self::redirect(self::url('', ['notice' => 'seeded']));
        }

        self::printAssets();
        self::view('overview', [
            'notice' => self::notice(),
            'dashboard' => class_exists('PhinIT_Plugin_Admin_Nav', false)
                ? \PhinIT_Plugin_Admin_Nav::dashboard(self::MENU_SLUG)
                : (function_exists('cms_plugin_dashboard') ? cms_plugin_dashboard(self::adminDashboard()) : ''),
            'csrf' => self::token('excomp_admin'),
            'seedUrl' => self::url(),
        ]);
    }

    public static function pagePublic(): void
    {
        $db = self::db();
        $settings = $db?->getSettings() ?? [];
        $experts = $db?->countByStatus('experts') ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
        $companies = $db?->countByStatus('companies') ?? ['total' => 0, 'active' => 0, 'inactive' => 0];

        self::printAssets();
        self::view('public-hub', [
            'settings' => $settings,
            'experts' => $experts,
            'companies' => $companies,
            'settingsUrl' => self::url(self::PAGE_SETTINGS),
            'siteUrl' => rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/'),
        ]);
    }

    public static function pageExperts(): void
    {
        self::entityPage('experts');
    }

    public static function pageCompanies(): void
    {
        self::entityPage('companies');
    }

    public static function pageExpertNew(): void
    {
        if (self::isPost()) {
            self::handleEntityPost('experts');
        }
        self::renderEntityForm('experts', null);
    }

    public static function pageCompanyNew(): void
    {
        if (self::isPost()) {
            self::handleEntityPost('companies');
        }
        self::renderEntityForm('companies', null);
    }

    public static function pageSettings(): void
    {
        $db = self::db();
        if (self::isPost() && self::postAction() === 'save_settings') {
            self::guardPost('excomp_settings_form', self::url(self::PAGE_SETTINGS));
            $rejected = $db !== null ? $db->saveSettings($_POST) : [];
            if (class_exists('CMS_365NET_Experts_And_Companie', false)) {
                CMS_365NET_Experts_And_Companie::flushPublicCache();
            }
            self::redirect(self::url(self::PAGE_SETTINGS, ['notice' => $rejected === [] ? 'saved' : 'saved_partial', 'rejected' => implode(',', array_slice($rejected, 0, 20))]));
        }

        $settings = $db?->getSettings() ?? CMS_365NET_Experts_And_Companie_Database::defaultSettings();
        $rejected = [];
        foreach (explode(',', (string) ($_GET['rejected'] ?? '')) as $key) {
            if (array_key_exists($key, CMS_365NET_Experts_And_Companie_Database::defaultSettings())) {
                $rejected[] = $key;
            }
        }

        self::printAssets();
        self::view('settings', [
            'settings' => $settings,
            'rejected' => $rejected,
            'notice' => self::notice(),
            'csrf' => self::token('excomp_settings_form'),
            'action' => self::url(self::PAGE_SETTINGS),
        ]);
    }

    public static function pageTransfer(): void
    {
        $transfer = class_exists('CMS_365NET_Experts_And_Companie_Transfer', false) ? CMS_365NET_Experts_And_Companie_Transfer::instance() : null;
        $privacy = class_exists('CMS_365NET_Experts_And_Companie_Integrations', false) ? CMS_365NET_Experts_And_Companie_Integrations::class : null;
        $back = self::url(self::PAGE_TRANSFER);

        if (self::isPost() && $transfer !== null) {
            $action = self::postAction();
            $entity = in_array((string) ($_POST['entity'] ?? ''), ['experts', 'companies'], true) ? (string) $_POST['entity'] : 'experts';

            if (in_array($action, ['export', 'template'], true)) {
                // Downloads: Token mehrfach verwendbar (die Seite wird dabei nicht neu geladen).
                self::guardPost('excomp_export', $back, true);
                $filter = ['search' => self::cleanText((string) ($_POST['q'] ?? ''), 120), 'status' => (string) ($_POST['status'] ?? '')];
                $action === 'template' ? $transfer->streamTemplate($entity) : $transfer->streamExport($entity, $filter);
            }

            if ($action === 'privacy_download' && $privacy !== null) {
                self::guardPost('excomp_export', $back, true);
                if (!$privacy::streamPrivacyExport((string) ($_POST['file'] ?? ''))) {
                    self::redirect(self::url(self::PAGE_TRANSFER, ['notice' => 'privacy_missing']));
                }
            }

            if ($action === 'privacy_delete' && $privacy !== null) {
                self::guardPost('excomp_transfer', $back);
                $privacy::deletePrivacyExport((string) ($_POST['file'] ?? ''));
                self::redirect(self::url(self::PAGE_TRANSFER, ['notice' => 'privacy_deleted']));
            }

            if ($action === 'import') {
                self::guardPost('excomp_transfer', $back);
                $file = is_array($_FILES['csv_file'] ?? null) ? $_FILES['csv_file'] : [];
                $result = $transfer->import($entity, $file, !empty($_POST['update_existing']));
                self::storeImportResult($result + ['entity' => $entity]);
                if (class_exists('CMS_365NET_Experts_And_Companie', false)) {
                    CMS_365NET_Experts_And_Companie::flushPublicCache();
                }
                self::redirect(self::url(self::PAGE_TRANSFER, ['notice' => $result['ok'] ? 'imported' : 'import_failed']));
            }

            self::redirect(self::url(self::PAGE_TRANSFER, ['notice' => 'unknown']));
        }

        self::printAssets();
        self::view('transfer', [
            'notice' => self::notice(),
            'importResult' => self::takeImportResult(),
            'csrfExport' => self::persistentToken('excomp_export'),
            'csrfTransfer' => self::token('excomp_transfer'),
            'action' => $back,
            'maxBytes' => CMS_365NET_Experts_And_Companie_Transfer::MAX_BYTES,
            'maxRows' => CMS_365NET_Experts_And_Companie_Transfer::MAX_ROWS,
            'columns' => [
                'experts' => CMS_365NET_Experts_And_Companie_Transfer::headers('experts'),
                'companies' => CMS_365NET_Experts_And_Companie_Transfer::headers('companies'),
            ],
            'privacyFiles' => $privacy !== null ? $privacy::listPrivacyExports() : [],
        ]);
    }

    /* =====================================================================
     * Experts / Companies: Liste, Formular, Aktionen
     * =================================================================== */

    private static function entityPage(string $entity): void
    {
        if (self::isPost()) {
            self::handleEntityPost($entity);
        }

        $db = self::db();
        $action = (string) ($_GET['action'] ?? '');
        if ($action === 'new') {
            self::renderEntityForm($entity, null);
            return;
        }
        if ($action === 'edit') {
            $id = max(0, (int) ($_GET['id'] ?? 0));
            $item = $db !== null ? ($entity === 'experts' ? $db->getExpertById($id) : $db->getCompanyById($id)) : null;
            if ($item === null) {
                self::redirect(self::url(self::entityPageSlug($entity), ['notice' => 'notfound']));
            }
            self::renderEntityForm($entity, $item);
            return;
        }

        $filter = [
            'search' => self::cleanText((string) ($_GET['q'] ?? ''), 120),
            'status' => in_array((string) ($_GET['status'] ?? ''), ['active', 'inactive'], true) ? (string) $_GET['status'] : '',
            'sort' => in_array((string) ($_GET['sort'] ?? ''), ['az', 'za', 'date_old_new', 'date_new_old'], true) ? (string) $_GET['sort'] : 'az',
        ];
        $rows = [];
        $total = 0;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        if ($db !== null) {
            $total = $entity === 'experts' ? $db->countExpertsAdmin($filter) : $db->countCompaniesAdmin($filter);
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $page = min($page, $pages);
            $offset = ($page - 1) * self::PER_PAGE;
            $rows = $entity === 'experts' ? $db->getExpertsAdminList($filter, self::PER_PAGE, $offset) : $db->getCompaniesAdminList($filter, self::PER_PAGE, $offset);
        }

        self::printAssets();
        self::view($entity === 'experts' ? 'experts-list' : 'companies-list', [
            'rows' => $rows,
            'filter' => $filter,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'perPage' => self::PER_PAGE,
            'notice' => self::notice(),
            'csrf' => self::token('excomp_list'),
            'csrfExport' => self::persistentToken('excomp_export'),
            'pageSlug' => self::entityPageSlug($entity),
            'listUrl' => self::url(self::entityPageSlug($entity)),
            'newUrl' => self::url($entity === 'experts' ? self::PAGE_EXPERT_NEW : self::PAGE_COMPANY_NEW),
            'transferUrl' => self::url(self::PAGE_TRANSFER),
            'siteUrl' => rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/'),
        ]);
    }

    private static function renderEntityForm(string $entity, ?object $item): void
    {
        $db = self::db();
        $data = [
            'item' => $item,
            'notice' => self::notice(),
            'csrf' => self::token($entity === 'experts' ? 'excomp_expert_form' : 'excomp_company_form'),
            'csrfList' => $item !== null ? self::token('excomp_list') : '',
            'action' => self::url(self::entityPageSlug($entity)),
            'listUrl' => self::url(self::entityPageSlug($entity)),
            'speakers' => $db?->getAvailableSpeakers(300) ?? [],
            'companies' => $db?->getCompaniesAdmin('', 400) ?? [],
            'experts' => $entity === 'companies' ? ($db?->getExpertsAdmin('', 400) ?? []) : [],
            'siteUrl' => rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/'),
        ];

        self::printAssets(true);
        self::view($entity === 'experts' ? 'expert-form' : 'company-form', $data);
    }

    private static function handleEntityPost(string $entity): void
    {
        $db = self::db();
        $listSlug = self::entityPageSlug($entity);
        $listUrl = self::url($listSlug);
        $action = self::postAction();
        if ($db === null) {
            self::redirect(self::url($listSlug, ['notice' => 'db']));
        }

        if ($action === 'export') {
            self::guardPost('excomp_export', $listUrl, true);
            if (class_exists('CMS_365NET_Experts_And_Companie_Transfer', false)) {
                CMS_365NET_Experts_And_Companie_Transfer::instance()->streamExport($entity, [
                    'search' => self::cleanText((string) ($_POST['q'] ?? ''), 120),
                    'status' => (string) ($_POST['status'] ?? ''),
                ]);
            }
            self::redirect(self::url($listSlug, ['notice' => 'unknown']));
        }

        if ($action === 'save') {
            self::guardPost($entity === 'experts' ? 'excomp_expert_form' : 'excomp_company_form', $listUrl);
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $saved = $entity === 'experts' ? $db->saveExpert(self::expertPayload($id)) : $db->saveCompany(self::companyPayload($id));
            if ($saved === false) {
                $target = $id > 0
                    ? self::url($listSlug, ['action' => 'edit', 'id' => $id, 'notice' => 'save_failed'])
                    : self::url($entity === 'experts' ? self::PAGE_EXPERT_NEW : self::PAGE_COMPANY_NEW, ['notice' => 'save_failed']);
                self::redirect($target);
            }
            self::flushPublic();
            self::redirect(self::url($listSlug, ['action' => 'edit', 'id' => (int) $saved, 'notice' => $id > 0 ? 'saved' : 'created']));
        }

        if ($action === 'delete' || $action === 'bulk') {
            self::guardPost('excomp_list', $listUrl);
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn (int $id): bool => $id > 0));
            $operation = $action === 'delete' ? 'delete' : (string) ($_POST['bulk_action'] ?? '');
            if ($ids === [] || !in_array($operation, ['delete', 'activate', 'deactivate'], true)) {
                self::redirect(self::url($listSlug, ['notice' => 'nothing_selected']));
            }

            $count = match ($operation) {
                'delete' => $entity === 'experts' ? $db->deleteExperts($ids) : $db->deleteCompanies($ids),
                'activate' => $db->setStatus($entity, $ids, 'active'),
                default => $db->setStatus($entity, $ids, 'inactive'),
            };
            self::flushPublic();
            self::redirect(self::url($listSlug, ['notice' => $operation === 'delete' ? 'deleted' : 'status_changed', 'count' => $count]));
        }

        self::redirect(self::url($listSlug, ['notice' => 'unknown']));
    }

    /** @return array<string, mixed> */
    private static function expertPayload(int $id): array
    {
        $p = static fn (string $key, int $max): string => self::cleanText((string) ($_POST[$key] ?? ''), $max);

        return [
            'id' => $id,
            'first_name' => $p('first_name', 120),
            'last_name' => $p('last_name', 120),
            'email' => $p('email', 180),
            'company' => $p('company', 255),
            'position' => $p('position', 255),
            'city' => $p('city', 120),
            'country' => $p('country', 120),
            'website' => $p('website', 600),
            'availability' => $p('availability', 80),
            'experience_years' => $p('experience_years', 10),
            'hourly_rate' => $p('hourly_rate', 20),
            'daily_rate' => $p('daily_rate', 20),
            'skills_general' => $p('skills_general', 2000),
            'skills_tech' => $p('skills_tech', 2000),
            'skills_soft' => $p('skills_soft', 2000),
            'awards' => $p('awards', 2000),
            'certifications' => $p('certifications', 2000),
            'biography_json' => self::limit((string) ($_POST['biography_json'] ?? ''), 200000),
            'biography' => $p('biography', 18000),
            'photo_url' => $p('photo_url', 600),
            'linked_company_id' => max(0, (int) ($_POST['linked_company_id'] ?? 0)),
            'linked_speaker_id' => max(0, (int) ($_POST['linked_speaker_id'] ?? 0)),
            'status' => ((string) ($_POST['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active',
        ];
    }

    /** @return array<string, mixed> */
    private static function companyPayload(int $id): array
    {
        $p = static fn (string $key, int $max): string => self::cleanText((string) ($_POST[$key] ?? ''), $max);

        return [
            'id' => $id,
            'name' => $p('name', 255),
            'email' => $p('email', 180),
            'phone' => $p('phone', 80),
            'industry' => $p('industry', 255),
            'company_size' => $p('company_size', 120),
            'website' => $p('website', 600),
            'city' => $p('city', 120),
            'zip' => $p('zip', 20),
            'country' => $p('country', 120),
            'founded_year' => $p('founded_year', 10),
            'employee_count' => $p('employee_count', 20),
            'parent_company_id' => max(0, (int) ($_POST['parent_company_id'] ?? 0)),
            'linked_expert_id' => max(0, (int) ($_POST['linked_expert_id'] ?? 0)),
            'linked_speaker_id' => max(0, (int) ($_POST['linked_speaker_id'] ?? 0)),
            'description_json' => self::limit((string) ($_POST['description_json'] ?? ''), 200000),
            'description' => $p('description', 20000),
            'logo_url' => $p('logo_url', 600),
            'is_partner' => !empty($_POST['is_partner']) ? 1 : 0,
            'is_top_partner' => !empty($_POST['is_top_partner']) ? 1 : 0,
            'is_sponsor' => !empty($_POST['is_sponsor']) ? 1 : 0,
            'status' => ((string) ($_POST['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private static function entityPageSlug(string $entity): string
    {
        return $entity === 'experts' ? self::PAGE_EXPERTS : self::PAGE_COMPANIES;
    }

    private static function flushPublic(): void
    {
        if (class_exists('CMS_365NET_Experts_And_Companie', false)) {
            CMS_365NET_Experts_And_Companie::flushPublicCache();
        }
    }

    /* =====================================================================
     * Meldungen, Import-Ergebnis
     * =================================================================== */

    /** @return array{type:string,text:string}|null Meldung zum Query-Code (keine reflektierten Texte). */
    private static function notice(): ?array
    {
        $code = (string) ($_GET['notice'] ?? '');
        $count = max(0, (int) ($_GET['count'] ?? 0));
        $messages = [
            'saved' => ['success', 'Änderungen wurden gespeichert.'],
            'created' => ['success', 'Eintrag wurde angelegt.'],
            'saved_partial' => ['warning', 'Einstellungen gespeichert. Ungültige Design-Werte oder Links wurden nicht übernommen (markiert).'],
            'save_failed' => ['danger', 'Speichern fehlgeschlagen. Pflichtfelder: Vorname und Nachname (Experts) bzw. Name (Companies).'],
            'deleted' => ['success', $count === 1 ? '1 Eintrag wurde gelöscht.' : $count . ' Einträge wurden gelöscht.'],
            'status_changed' => ['success', 'Status von ' . $count . ($count === 1 ? ' Eintrag' : ' Einträgen') . ' geändert.'],
            'nothing_selected' => ['warning', 'Bitte mindestens einen Eintrag und eine Aktion auswählen.'],
            'notfound' => ['warning', 'Der Eintrag wurde nicht gefunden.'],
            'csrf' => ['danger', 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.'],
            'forbidden' => ['danger', 'Für diese Aktion fehlt die Berechtigung.'],
            'seeded' => ['success', 'Seed-Daten wurden geprüft; fehlende Seed-Einträge sind wieder angelegt.'],
            'imported' => ['success', 'Import abgeschlossen.'],
            'import_failed' => ['danger', 'Import fehlgeschlagen.'],
            'privacy_deleted' => ['success', 'Auskunftsdatei wurde gelöscht.'],
            'privacy_missing' => ['warning', 'Die Auskunftsdatei ist nicht mehr vorhanden.'],
            'db' => ['danger', 'Die Datenbank ist nicht verfügbar.'],
            'unknown' => ['warning', 'Unbekannte Aktion.'],
        ];
        if (!isset($messages[$code])) {
            return null;
        }

        return ['type' => $messages[$code][0], 'text' => $messages[$code][1]];
    }

    /** @param array<string, mixed> $result */
    private static function storeImportResult(array $result): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cms_excomp_import_result'] = $result;
        }
    }

    /** @return array<string, mixed>|null */
    private static function takeImportResult(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !is_array($_SESSION['cms_excomp_import_result'] ?? null)) {
            return null;
        }
        $result = $_SESSION['cms_excomp_import_result'];
        unset($_SESSION['cms_excomp_import_result']);

        return $result;
    }

    /* =====================================================================
     * Hilfen: Sicherheit, Redirect, Assets, Views
     * =================================================================== */

    private static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private static function postAction(): string
    {
        $action = (string) ($_POST['excomp_action'] ?? ($_GET['excomp_action'] ?? ''));

        return preg_match('/^[a-z_]{1,40}$/', $action) === 1 ? $action : '';
    }

    /** Capability und CSRF jeder Schreibaktion serverseitig prüfen; sonst zurück mit Meldung. */
    private static function guardPost(string $tokenAction, string $back, bool $persistent = false): void
    {
        $isAdmin = class_exists('CMS\\Auth') && CMS\Auth::instance()->isAdmin();
        $can = !function_exists('current_user_can') || current_user_can(self::CAPABILITY);
        if (!$isAdmin || !$can) {
            self::redirect(self::withQuery($back, ['notice' => 'forbidden']));
        }

        $token = (string) ($_POST['csrf_token'] ?? '');
        $valid = false;
        if (class_exists('CMS\\Security')) {
            $security = CMS\Security::instance();
            $valid = $persistent ? $security->verifyPersistentToken($token, $tokenAction) : $security->verifyToken($token, $tokenAction);
        }
        if (!$valid) {
            self::redirect(self::withQuery($back, ['notice' => 'csrf']));
        }
    }

    /** @param array<string, scalar> $query */
    private static function withQuery(string $url, array $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function token(string $action): string
    {
        return class_exists('CMS\\Security') ? (string) CMS\Security::instance()->generateToken($action) : '';
    }

    /** Ein Token je Anfrage für Download-Formulare (verifyPersistentToken, mehrfach nutzbar). */
    private static function persistentToken(string $action): string
    {
        static $tokens = [];

        return $tokens[$action] ??= self::token($action);
    }

    /** Nur lokale Ziele (Security::safeLocalPath), 303 nach POST. */
    public static function redirect(string $path): never
    {
        $fallback = self::url();
        $path = class_exists('CMS\\Security') ? CMS\Security::safeLocalPath($path, $fallback) : (str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : $fallback);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/') . $path, true, 303);
        exit;
    }

    private static function cleanText(string $value, int $max): string
    {
        $value = trim(strip_tags($value));

        return self::limit($value, $max);
    }

    private static function limit(string $value, int $max): string
    {
        return function_exists('mb_substr') ? (string) mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    /** Admin-CSS/JS nur auf den eigenen Seiten (der Router verschiebt <link> in den <head>). */
    private static function printAssets(bool $media = false): void
    {
        $base = CMS_365NET_EXCOMP_PLUGIN_URL . 'assets/';
        $version = rawurlencode(CMS_365NET_EXCOMP_VERSION);
        if (!self::$assetsPrinted) {
            self::$assetsPrinted = true;
            echo '<link rel="stylesheet" href="' . self::e($base . 'css/admin.css?v=' . $version) . '">' . "\n";
            echo '<script src="' . self::e($base . 'js/admin.js?v=' . $version) . '" defer></script>' . "\n";
        }

        if (!$media) {
            return;
        }

        static $mediaPrinted = false;
        if ($mediaPrinted) {
            return;
        }
        $mediaPrinted = true;

        $config = [
            'uploadUrl' => '/api/experts-companie/media-upload',
            'libraryUrl' => '/api/media',
            'csrfToken' => class_exists('CMS\\Security') ? (string) CMS\Security::instance()->generateToken('editorjs_media') : '',
            'uploadFolder' => '/uploads/experts-companie/',
            'maxSizeMb' => 10,
        ];
        $json = (string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<div hidden id="cms-excomp-media-config" data-config="' . self::e($json) . '"></div>' . "\n";
        echo '<script src="' . self::e($base . 'js/admin-media.js?v=' . $version) . '" defer></script>' . "\n";
    }

    /** @param array<string, mixed> $vars */
    private static function view(string $name, array $vars): void
    {
        $file = CMS_365NET_EXCOMP_PLUGIN_DIR . 'admin/views/' . $name . '.php';
        if (preg_match('/^[a-z-]+$/', $name) !== 1 || !is_file($file)) {
            echo '<div class="alert alert-danger" role="alert">Ansicht nicht gefunden.</div>';
            return;
        }

        $e = static fn (mixed $value): string => self::e((string) $value);
        $url = static fn (string $page = '', array $query = []): string => self::url($page, $query);
        extract($vars, EXTR_SKIP);
        include $file;
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /* =====================================================================
     * Formular-Bausteine für die Views
     * =================================================================== */

    public static function textField(string $name, string $label, string $value, string $type = 'text', string $attrs = ''): string
    {
        $id = 'excomp-' . str_replace('_', '-', $name);

        return '<div class="cms-excomp-field"><label class="form-label" for="' . self::e($id) . '">' . self::e($label) . '</label>'
            . '<input class="form-control" type="' . self::e($type) . '" id="' . self::e($id) . '" name="' . self::e($name) . '" value="' . self::e($value) . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '></div>';
    }

    public static function textAreaField(string $name, string $label, string $value, int $rows = 3): string
    {
        $id = 'excomp-' . str_replace('_', '-', $name);

        return '<div class="cms-excomp-field cms-excomp-field--wide"><label class="form-label" for="' . self::e($id) . '">' . self::e($label) . '</label>'
            . '<textarea class="form-control" id="' . self::e($id) . '" name="' . self::e($name) . '" rows="' . max(2, $rows) . '">' . self::e($value) . '</textarea></div>';
    }

    public static function checkField(string $name, string $label, bool $checked): string
    {
        return '<label class="cms-excomp-check form-check"><input class="form-check-input" type="checkbox" name="' . self::e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span class="form-check-label">' . self::e($label) . '</span></label>';
    }

    /** @param array<string, string> $options */
    public static function selectField(string $name, string $label, string $value, array $options): string
    {
        $id = 'excomp-' . str_replace('_', '-', $name);
        $html = '<div class="cms-excomp-field"><label class="form-label" for="' . self::e($id) . '">' . self::e($label) . '</label><select class="form-select" id="' . self::e($id) . '" name="' . self::e($name) . '">';
        foreach ($options as $optionValue => $optionLabel) {
            $html .= '<option value="' . self::e((string) $optionValue) . '"' . ((string) $optionValue === $value ? ' selected' : '') . '>' . self::e($optionLabel) . '</option>';
        }

        return $html . '</select></div>';
    }

    public static function mediaField(string $name, string $label, string $value, string $hint = ''): string
    {
        if (class_exists('CMS_365NET_Experts_And_Companie', false)) {
            $value = CMS_365NET_Experts_And_Companie::normalizeMediaUrl($value);
        }
        $id = 'excomp-' . str_replace('_', '-', $name);

        return '<div class="cms-excomp-field cms-excomp-media-label"><label class="form-label" for="' . self::e($id) . '">' . self::e($label) . '</label>'
            . '<input class="form-control" type="text" id="' . self::e($id) . '" name="' . self::e($name) . '" value="' . self::e($value) . '" placeholder="/uploads/experts-companie/… oder https://…" data-excomp-media-field>'
            . ($hint !== '' ? '<small class="cms-excomp-media-note">' . self::e($hint) . '</small>' : '') . '</div>';
    }

    public static function editor(string $name, string $jsonValue, string $fallbackText, string $label): string
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
                'blocks' => array_values(array_map(
                    static fn (string $part): array => [
                        'type' => 'paragraph',
                        'data' => ['text' => htmlspecialchars(trim($part), ENT_QUOTES, 'UTF-8')],
                    ],
                    array_filter(preg_split('/\n{2,}/', $fallbackText) ?: [])
                )),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (class_exists('CMS\\Services\\EditorJs\\EditorJsAssetService')) {
            $service = new CMS\Services\EditorJs\EditorJsAssetService();

            return '<div class="cms-excomp-field cms-excomp-field--wide"><span class="form-label">' . self::e($label) . '</span>' . $service->render($name, $jsonValue, [
                'height' => 460,
                'context' => '365netexpertsandcompanie',
                'aria_label' => $label,
                'content_width' => 960,
            ]) . '</div>';
        }

        return self::textAreaField($name, $label, $jsonValue, 12);
    }

    /** Status-Badge in Listen. */
    public static function statusBadge(string $status): string
    {
        $active = $status !== 'inactive';

        return '<span class="status-badge ' . ($active ? 'active' : 'inactive') . '">' . ($active ? 'Aktiv' : 'Inaktiv') . '</span>';
    }

    /**
     * Seitennavigation einer Liste.
     *
     * @param array<string, string> $filter
     */
    public static function pagination(string $pageSlug, array $filter, int $page, int $pages): string
    {
        if ($pages <= 1) {
            return '';
        }

        $link = static function (int $target, string $label, bool $current = false, bool $disabled = false) use ($pageSlug, $filter): string {
            if ($disabled) {
                return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
            }
            $href = self::url($pageSlug, ['q' => $filter['search'] ?? '', 'status' => $filter['status'] ?? '', 'sort' => $filter['sort'] ?? '', 'paged' => $target > 1 ? $target : '']);

            return '<li class="page-item' . ($current ? ' active' : '') . '"><a class="page-link" href="' . self::e($href) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $label . '</a></li>';
        };

        $html = '<nav class="cms-excomp-pagination" aria-label="Seiten"><ul class="pagination">';
        $html .= $link($page - 1, '<i class="ti ti-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Zurück</span>', false, $page <= 1);
        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        if ($start > 1) {
            $html .= $link(1, '1');
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $link($i, (string) $i, $i === $page);
        }
        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= $link($pages, (string) $pages);
        }
        $html .= $link($page + 1, '<i class="ti ti-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Weiter</span>', false, $page >= $pages);

        return $html . '</ul></nav>';
    }
}

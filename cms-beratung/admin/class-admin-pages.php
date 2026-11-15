<?php
/**
 * CMS Beratung – Admin-Seiten, Aktionen und Downloads (Layout-Vertrag 4.0.0).
 *
 * Der AdminRouter liefert die Shell; jede Seite gibt nur Inhalt aus:
 * cms_plugin_workspace_open() → Unterseite (Header → Toolbar/Filter → Inhalt) → cms_plugin_workspace_close().
 * Schreibaktionen prüfen Admin-Rechte und CSRF-Token serverseitig und leiten per 303 auf die eigene Seite um.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('CMS_Beratung_Admin_Pages', false)) {
    return;
}

final class CMS_Beratung_Admin_Pages
{
    public const MENU_SLUG = 'cms-beratung';
    public const DEFAULT_PAGE_SLUG = 'cms-beratung-landingpages';

    /** Ansichten ohne eigenen Menüeintrag; sie laufen über ?view= der Landingpage-Liste bzw. der Hauptroute. */
    private const VIEW_SLUGS = ['cms-beratung-landingpages', 'cms-beratung-new', 'cms-beratung-preview'];

    private const PER_PAGE = 20;

    /** @var array<string,array{0:string,1:string}> slug => [Seitentitel, Render-Methode] */
    private const PAGES = [
        'cms-beratung' => ['Übersicht', 'render_dashboard'],
        'cms-beratung-landingpages' => ['Landingpages', 'render_landingpages'],
        'cms-beratung-new' => ['Landingpage bearbeiten', 'render_new_page'],
        'cms-beratung-preview' => ['Landingpage-Vorschau', 'render_preview'],
        'cms-beratung-submissions' => ['Anfragen', 'render_submissions'],
        'cms-beratung-faqs' => ['M365 FAQs', 'render_faqs'],
        'cms-beratung-presets' => ['Design Presets', 'render_presets'],
        'cms-beratung-settings' => ['Einstellungen', 'render_settings'],
        'cms-beratung-import-export' => ['Import und Export', 'render_import_export'],
        'cms-beratung-help' => ['Hilfe und Dokumentation', 'render_help'],
    ];

    /** Meldungen nach Redirect (nur Schlüssel in der URL, keine Fremdtexte). */
    private const NOTICES = [
        'saved' => ['success', 'Gespeichert.'],
        'created' => ['success', 'Landingpage angelegt.'],
        'deleted' => ['success', 'Gelöscht.'],
        'updated' => ['success', 'Aktion erfolgreich ausgeführt.'],
        'duplicated' => ['success', 'Landingpage dupliziert (als Entwurf).'],
        'preset_created' => ['success', 'Design-Preset angelegt.'],
        'preset_deleted' => ['success', 'Design-Preset gelöscht.'],
        'imported' => ['success', 'Import abgeschlossen.'],
        'csrf' => ['danger', 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden und erneut versuchen.'],
        'none' => ['danger', 'Bitte mindestens einen Eintrag auswählen.'],
        'failed' => ['danger', 'Die Aktion konnte nicht ausgeführt werden.'],
        'notfound' => ['danger', 'Der Eintrag wurde nicht gefunden.'],
    ];

    /** @var array<string,mixed>|null */
    private static ?array $statsCache = null;

    // ─── Menü und Dispatch ─────────────────────────────────────────────────

    /** @return array<int,array{slug:string,title:string,menu_title:string}> */
    public static function get_menu_pages(): array
    {
        return [
            ['slug' => 'cms-beratung-landingpages', 'title' => 'Landingpages', 'menu_title' => 'Landingpages'],
            ['slug' => 'cms-beratung-submissions', 'title' => 'Anfragen', 'menu_title' => 'Anfragen'],
            ['slug' => 'cms-beratung-faqs', 'title' => 'M365 FAQs', 'menu_title' => 'M365 FAQs'],
            ['slug' => 'cms-beratung-presets', 'title' => 'Design Presets', 'menu_title' => 'Design Presets'],
            ['slug' => 'cms-beratung-settings', 'title' => 'Einstellungen', 'menu_title' => 'Einstellungen'],
            ['slug' => 'cms-beratung-import-export', 'title' => 'Import und Export', 'menu_title' => 'Import und Export'],
            ['slug' => 'cms-beratung-help', 'title' => 'Hilfe und Dokumentation', 'menu_title' => 'Hilfe'],
        ];
    }

    /** @return array{0:class-string,1:string} */
    public static function dispatch_callback_for_slug(string $slug): array
    {
        $method = 'dispatch_' . str_replace('-', '_', self::normalize_slug($slug));
        return method_exists(self::class, $method) ? [self::class, $method] : [self::class, 'dispatch_overview'];
    }

    public static function dispatch_overview(): void { self::render_page('cms-beratung'); }
    public static function dispatch_cms_beratung(): void { self::render_page('cms-beratung'); }
    public static function dispatch_cms_beratung_landingpages(): void { self::render_page('cms-beratung-landingpages'); }
    public static function dispatch_cms_beratung_new(): void { self::render_page('cms-beratung-new'); }
    public static function dispatch_cms_beratung_preview(): void { self::render_page('cms-beratung-preview'); }
    public static function dispatch_cms_beratung_settings(): void { self::render_page('cms-beratung-settings'); }
    public static function dispatch_cms_beratung_presets(): void { self::render_page('cms-beratung-presets'); }
    public static function dispatch_cms_beratung_faqs(): void { self::render_page('cms-beratung-faqs'); }
    public static function dispatch_cms_beratung_submissions(): void { self::render_page('cms-beratung-submissions'); }
    public static function dispatch_cms_beratung_import_export(): void { self::render_page('cms-beratung-import-export'); }
    public static function dispatch_cms_beratung_help(): void { self::render_page('cms-beratung-help'); }

    /** Kompatibilität: früherer Einstiegspunkt mit ?page= */
    public static function render_dispatch(): void
    {
        self::render_page(self::normalize_slug((string) ($_GET['page'] ?? self::MENU_SLUG)));
    }

    private static function render_page(string $slug): void
    {
        self::check_access();
        // Unteransichten (Editor, Vorschau) laufen per ?view= über die Hauptroute oder die Landingpage-Liste.
        $view = self::normalize_slug((string) ($_GET['view'] ?? ''));
        if (in_array($slug, [self::MENU_SLUG, self::DEFAULT_PAGE_SLUG], true) && in_array($view, self::VIEW_SLUGS, true)) {
            $slug = $view;
        }
        $method = self::PAGES[$slug][1] ?? 'render_dashboard';
        self::$method();
    }

    public static function register_admin_routes(mixed $router): void
    {
        if (is_object($router) && method_exists($router, 'addRoute')) {
            $router->addRoute('POST', '/api/cms-beratung/media-upload', [self::class, 'handle_media_upload']);
        }
    }

    // ─── Layout ────────────────────────────────────────────────────────────

    private static function load_workspace(): void
    {
        if (!function_exists('cms_plugin_workspace_open')) {
            $core = ABSPATH . 'admin/partials/plugin-workspace.php';
            if (is_file($core)) {
                require_once $core;
            }
        }
        if (!function_exists('cms_plugin_workspace_open')) {
            require_once CMS_BERATUNG_PLUGIN_DIR . 'includes/plugin-workspace.php';
            // Rückfall-CSS nur, wenn der Core das Layout nicht liefert.
            echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/cms-plugin-workspace.css?v=' . CMS_BERATUNG_VERSION) . '">';
        }
    }

    /** @return array<string,mixed> Live-Kennzahlen (einmal pro Request). */
    public static function stats(): array
    {
        if (self::$statsCache !== null) {
            return self::$statsCache;
        }
        $storage = CMS_Beratung_Storage::instance();
        return self::$statsCache = [
            'pages' => $storage->landingpage_counts(),
            'submissions' => $storage->submission_counts(),
        ];
    }

    /** Öffnet Kopfzeile, linkes Plugin-Menü und Inhaltsspalte; $active ist der Slug des aktiven Menüpunkts. */
    private static function open_layout(string $active): void
    {
        self::load_workspace();
        self::enqueue_admin_assets();
        $stats = self::stats();
        $settings = CMS_Beratung_Settings::all();
        $pages = $stats['pages'];
        $subs = $stats['submissions'];
        $showSubmissions = ($settings['show_submissions_backend'] ?? '1') === '1';
        $missing = CMS_Beratung_Installer::missing_tables();
        $tab = self::settings_tab();

        $nav = [
            ['slug' => 'cms-beratung', 'label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard', 'url' => self::admin_url('cms-beratung'), 'active' => $active === 'cms-beratung'],
            ['slug' => 'cms-beratung-landingpages', 'label' => 'Landingpages', 'icon' => 'ti ti-file-text', 'badge' => (int) $pages['total'], 'url' => self::admin_url('cms-beratung-landingpages'), 'active' => in_array($active, ['cms-beratung-landingpages', 'cms-beratung-edit'], true)],
            ['slug' => 'cms-beratung-new', 'label' => 'Neue Landingpage', 'icon' => 'ti ti-plus', 'url' => self::admin_url('cms-beratung-new'), 'active' => $active === 'cms-beratung-new'],
        ];
        if ($showSubmissions) {
            $nav[] = ['slug' => 'cms-beratung-submissions', 'label' => 'Anfragen', 'icon' => 'ti ti-inbox', 'badge' => (int) $subs['new'], 'badge_tone' => 'warning', 'url' => self::admin_url('cms-beratung-submissions'), 'active' => $active === 'cms-beratung-submissions'];
        }
        $nav[] = ['slug' => 'cms-beratung-faqs', 'label' => 'M365 FAQs', 'icon' => 'ti ti-help-circle', 'url' => self::admin_url('cms-beratung-faqs'), 'active' => $active === 'cms-beratung-faqs'];
        $nav[] = ['slug' => 'cms-beratung-presets', 'label' => 'Design Presets', 'icon' => 'ti ti-palette', 'url' => self::admin_url('cms-beratung-presets'), 'active' => $active === 'cms-beratung-presets'];
        foreach (CMS_Beratung_Settings::tabs() as $key => $definition) {
            $nav[] = ['slug' => 'cms-beratung-settings-' . $key, 'label' => $definition['label'], 'icon' => $definition['icon'], 'group' => 'Einstellungen', 'url' => self::admin_url('cms-beratung-settings', ['tab' => $key]), 'active' => $active === 'cms-beratung-settings' && $tab === $key];
        }
        $nav[] = ['slug' => 'cms-beratung-import-export', 'label' => 'Import und Export', 'icon' => 'ti ti-transfer', 'url' => self::admin_url('cms-beratung-import-export'), 'active' => $active === 'cms-beratung-import-export'];
        $nav[] = ['slug' => 'cms-beratung-help', 'label' => 'Hilfe', 'icon' => 'ti ti-book', 'url' => self::admin_url('cms-beratung-help'), 'active' => $active === 'cms-beratung-help'];

        $status = ['label' => 'Aktiv', 'tone' => 'success'];
        if ($missing !== []) {
            $status = ['label' => 'Tabellen fehlen', 'tone' => 'danger'];
        } elseif (($settings['notification_enabled'] ?? '1') === '1' && !filter_var((string) ($settings['contact_recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $status = ['label' => 'Empfänger fehlt', 'tone' => 'warning'];
        }

        echo cms_plugin_workspace_open([
            'plugin' => self::MENU_SLUG,
            'title' => '365CMS | Beratung',
            'icon' => 'ti ti-briefcase',
            'version' => CMS_BERATUNG_VERSION,
            'status' => $status,
            'description' => 'Landingpages für Microsoft-365-, Copilot- und Security-Beratung mit Anfrageformular.',
            'stats' => [
                ['label' => 'Landingpages', 'value' => (int) $pages['total'], 'icon' => 'ti ti-file-text', 'url' => self::admin_url('cms-beratung-landingpages')],
                ['label' => 'Veröffentlicht', 'value' => (int) ($pages['published'] ?? 0), 'icon' => 'ti ti-world', 'tone' => 'success', 'url' => self::admin_url('cms-beratung-landingpages', ['status' => 'published'])],
                ['label' => 'Neue Anfragen', 'value' => (int) ($subs['new'] ?? 0), 'icon' => 'ti ti-inbox', 'tone' => ((int) ($subs['new'] ?? 0)) > 0 ? 'warning' : '', 'url' => self::admin_url('cms-beratung-submissions', ['status' => 'new'])],
                ['label' => 'Anfragen gesamt', 'value' => (int) $subs['total'], 'icon' => 'ti ti-messages', 'url' => self::admin_url('cms-beratung-submissions')],
            ],
            'nav' => $nav,
            'nav_explicit' => true,
        ]);
        echo '<div class="beratung-admin">';
    }

    private static function close_layout(): void
    {
        echo '</div>';
        echo cms_plugin_workspace_close();
        self::enqueue_admin_scripts();
    }

    /** Unterseiten-Header: Titel, Einzeiler, Aktionen (bereits escaptes HTML). */
    public static function page_header(string $title, string $description, string $actionsHtml = ''): void
    {
        echo '<div class="admin-page-header"><div><h2>' . self::esc($title) . '</h2>'
            . ($description !== '' ? '<p>' . self::esc($description) . '</p>' : '') . '</div>'
            . ($actionsHtml !== '' ? '<div class="header-actions">' . $actionsHtml . '</div>' : '') . '</div>';
    }

    public static function button_link(string $label, string $url, string $icon = '', string $class = 'btn btn-outline-secondary', bool $newTab = false): string
    {
        return '<a class="' . self::esc($class) . '" href="' . self::esc($url) . '"' . ($newTab ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
            . ($icon !== '' ? '<i class="' . self::esc($icon) . '" aria-hidden="true"></i> ' : '') . self::esc($label) . '</a>';
    }

    /** Meldung aus ?notice=<schlüssel> sowie direkte Meldungen. */
    public static function render_alerts(string $success = '', string $error = ''): void
    {
        $key = (string) ($_GET['notice'] ?? '');
        if (isset(self::NOTICES[$key])) {
            [$tone, $text] = self::NOTICES[$key];
            $count = isset($_GET['count']) ? max(0, (int) $_GET['count']) : null;
            echo '<div class="alert alert-' . $tone . '" role="status">' . self::esc($text . ($count !== null && $tone === 'success' ? ' (' . $count . ')' : '')) . '</div>';
        }
        if ($success !== '') {
            echo '<div class="alert alert-success" role="status">' . self::esc($success) . '</div>';
        }
        if ($error !== '') {
            echo '<div class="alert alert-danger" role="alert">' . self::esc($error) . '</div>';
        }
    }

    private static function enqueue_admin_assets(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;
        $css = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/admin.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/admin.css?v=' . CMS_BERATUNG_VERSION . '-' . filemtime($css)) . '">' . "\n";
        }
    }

    private static function enqueue_admin_scripts(): void
    {
        static $loaded = false;
        $js = CMS_BERATUNG_PLUGIN_DIR . 'assets/js/admin-builder.js';
        if ($loaded || !is_file($js)) {
            return;
        }
        $loaded = true;
        $nonce = class_exists('CMS\\Security') ? ' ' . \CMS\Security::instance()->nonceAttr() : '';
        echo '<script src="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/js/admin-builder.js?v=' . CMS_BERATUNG_VERSION . '-' . filemtime($js)) . '" defer' . $nonce . '></script>' . "\n";
    }

    private static function view(string $name, array $vars = []): void
    {
        $file = CMS_BERATUNG_PLUGIN_DIR . 'admin/views/' . $name . '.php';
        if (!is_file($file)) {
            echo '<div class="alert alert-danger">Ansicht fehlt: ' . self::esc($name) . '</div>';
            return;
        }
        extract($vars, EXTR_SKIP);
        include $file;
    }

    // ─── Seiten ────────────────────────────────────────────────────────────

    public static function render_dashboard(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $recentPages = $storage->recent_landingpages(6);
        $recentSubmissions = (CMS_Beratung_Settings::all()['show_submissions_backend'] ?? '1') === '1'
            ? $storage->list_submissions([], 1, 5)['rows'] : [];
        self::open_layout('cms-beratung');
        self::render_alerts();
        self::view('dashboard', ['recentPages' => $recentPages, 'recentSubmissions' => $recentSubmissions, 'stats' => self::stats()]);
        self::close_layout();
    }

    public static function render_landingpages(): void
    {
        self::handle_landingpage_actions();
        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'search' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
            'sort' => (string) ($_GET['sort'] ?? 'updated'),
        ];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $result = CMS_Beratung_Storage::instance()->list_landingpages($filters, $page, self::PER_PAGE);
        self::open_layout('cms-beratung-landingpages');
        self::view('landingpages', ['filters' => $filters, 'result' => $result, 'pageNumber' => $page, 'perPage' => self::PER_PAGE]);
        self::close_layout();
    }

    public static function render_new_page(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $error = '';
        $posted = null;
        if (self::is_post() && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_landingpage') {
            if (!self::verify_nonce('beratung_save_landingpage')) {
                $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut speichern.';
                $posted = $_POST;
            } else {
                try {
                    $savedId = $storage->save_landingpage($_POST);
                    self::redirect('cms-beratung-new', ['id' => $savedId, 'notice' => $id > 0 ? 'saved' : 'created']);
                } catch (\Throwable $e) {
                    $error = 'Landingpage konnte nicht gespeichert werden: ' . $e->getMessage();
                    $posted = $_POST;
                }
            }
        }

        $page = $id > 0 ? $storage->get_landingpage($id) : null;
        if ($id > 0 && $page === null) {
            self::redirect('cms-beratung-landingpages', ['notice' => 'notfound']);
        }
        if ($posted !== null) {
            // Eingaben nach Fehler erhalten (bereinigt).
            $page = array_merge($page ?? [], CMS_Beratung_Import_Export::sanitize_landingpage_payload($posted), ['id' => $id]);
        }
        if ($page === null) {
            $page = CMS_Beratung_Admin_Defaults::default_landingpage((string) ($_GET['proposal'] ?? 'microsoft-365-copilot'));
        }

        self::open_layout($id > 0 ? 'cms-beratung-edit' : 'cms-beratung-new');
        $actions = self::button_link('Zur Übersicht', self::admin_url('cms-beratung-landingpages'), 'ti ti-arrow-left');
        if ($id > 0) {
            $actions = self::button_link('Vorschau', self::preview_url($id), 'ti ti-eye', 'btn btn-outline-secondary', true) . $actions;
        }
        self::page_header($id > 0 ? 'Landingpage bearbeiten' : 'Neue Landingpage', $id > 0 ? (string) ($page['internal_title'] ?? '') : 'Vorlage wählen, Inhalte anpassen und als Entwurf speichern.', $actions);
        self::render_alerts('', $error);
        if ($id <= 0) {
            self::view('proposals', ['active' => (string) ($_GET['proposal'] ?? 'microsoft-365-copilot')]);
        }
        self::view('editor', ['page' => $page, 'formAction' => self::admin_url('cms-beratung-new', $id > 0 ? ['id' => $id] : [])]);
        self::close_layout();
    }

    /** Alte Vorschau-Adresse (?view=cms-beratung-preview) auf die eigenständige Vorschau-Route umleiten. */
    public static function render_preview(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        header('Location: ' . self::site_url() . self::preview_url($id), true, 303);
        exit;
    }

    public static function render_settings(): void
    {
        $tab = self::settings_tab();
        $tabs = CMS_Beratung_Settings::tabs();
        if (self::is_post() && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_settings') {
            if (!self::verify_nonce('beratung_settings')) {
                self::redirect('cms-beratung-settings', ['tab' => $tab, 'notice' => 'csrf']);
            }
            CMS_Beratung_Settings::save(CMS_Beratung_Settings::sanitize_from_post($_POST, $tabs[$tab]['keys']));
            self::redirect('cms-beratung-settings', ['tab' => $tab, 'notice' => 'saved']);
        }
        self::open_layout('cms-beratung-settings');
        self::page_header('Einstellungen: ' . $tabs[$tab]['label'], $tabs[$tab]['description']);
        self::render_alerts();
        self::view('settings', ['tab' => $tab, 'definition' => $tabs[$tab], 'settings' => CMS_Beratung_Settings::all()]);
        self::close_layout();
    }

    public static function render_presets(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $error = '';
        if (self::is_post()) {
            $action = (string) ($_POST['beratung_admin_action'] ?? '');
            if (!self::verify_nonce('beratung_presets')) {
                self::redirect('cms-beratung-presets', ['notice' => 'csrf']);
            }
            if ($action === 'create_preset') {
                try {
                    $colors = [];
                    foreach (CMS_Beratung_Storage::preset_color_keys() as $key) {
                        $colors[$key] = is_scalar($_POST[$key] ?? null) ? (string) $_POST[$key] : '';
                    }
                    $storage->create_custom_preset((string) ($_POST['preset_name'] ?? ''), (string) ($_POST['preset_description'] ?? ''), $colors);
                    self::redirect('cms-beratung-presets', ['notice' => 'preset_created']);
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            } elseif ($action === 'delete_preset') {
                self::redirect('cms-beratung-presets', ['notice' => $storage->delete_custom_preset((int) ($_POST['preset_id'] ?? 0)) ? 'preset_deleted' : 'failed']);
            }
        }
        self::open_layout('cms-beratung-presets');
        self::page_header('Design Presets', 'Farbsets für den Landingpage-Editor („Design Preset anwenden“). System-Presets sind schreibgeschützt.');
        self::render_alerts('', $error);
        self::view('presets', ['presets' => $storage->all_presets(), 'settings' => CMS_Beratung_Settings::all()]);
        self::close_layout();
    }

    public static function render_faqs(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $error = '';
        if (self::is_post() && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_m365_faqs') {
            if (!self::verify_nonce('beratung_m365_faqs')) {
                $error = 'Sicherheitsprüfung fehlgeschlagen.';
            } else {
                try {
                    $storage->save_m365_faq_config($_POST);
                    self::redirect('cms-beratung-faqs', ['notice' => 'saved']);
                } catch (\Throwable $e) {
                    $error = 'FAQs konnten nicht gespeichert werden: ' . $e->getMessage();
                }
            }
        }
        $config = $storage->m365_faq_config();
        self::open_layout('cms-beratung-faqs');
        self::page_header('M365 FAQs', 'Zentrale FAQs für alle Landingpages (inklusive FAQ-Schema).');
        self::render_alerts('', $error);
        self::view('faqs', ['config' => $config]);
        self::close_layout();
    }

    public static function render_submissions(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $enabled = (CMS_Beratung_Settings::all()['show_submissions_backend'] ?? '1') === '1';
        if ($enabled) {
            self::handle_submission_actions();
        }
        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'landingpage_id' => max(0, (int) ($_GET['landingpage'] ?? 0)),
            'search' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
        ];
        $detailId = max(0, (int) ($_GET['id'] ?? 0));
        $detail = null;
        if ($enabled && $detailId > 0) {
            $detail = $storage->submission($detailId);
            if ($detail !== null && (string) ($detail['status'] ?? '') === 'new') {
                $storage->mark_submission_read($detailId);
                $detail['status'] = 'read';
                self::$statsCache = null;
            }
        }
        $page = max(1, (int) ($_GET['p'] ?? 1));
        self::open_layout('cms-beratung-submissions');
        if (!$enabled) {
            self::page_header('Anfragen', 'Die Anzeige der Anfragen ist ausgeschaltet.', self::button_link('Einstellung ändern', self::admin_url('cms-beratung-settings', ['tab' => 'contact']), 'ti ti-settings'));
            echo '<div class="alert alert-info">Anfragen werden weiterhin gespeichert, sofern „Anfragen in der Datenbank speichern“ aktiv ist. Unter Einstellungen → Kontaktformular lässt sich die Anzeige wieder einschalten.</div>';
        } elseif ($detailId > 0) {
            self::view('submission-detail', ['submission' => $detail]);
        } else {
            self::view('submissions', [
                'filters' => $filters,
                'result' => $storage->list_submissions($filters, $page, self::PER_PAGE),
                'pageNumber' => $page,
                'perPage' => self::PER_PAGE,
                'landingpages' => $storage->all_landingpages(),
                'exports' => CMS_Beratung_Integrations::privacy_exports(),
            ]);
        }
        self::close_layout();
    }

    public static function render_import_export(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $notice = '';
        $error = '';
        if (self::is_post()) {
            $action = (string) ($_POST['beratung_admin_action'] ?? '');
            if (!self::verify_nonce('beratung_import_export')) {
                $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.';
            } elseif ($action === 'export_landingpage') {
                $exportPage = $storage->get_landingpage(max(0, (int) ($_POST['export_id'] ?? 0)));
                if ($exportPage === null) {
                    $error = 'Bitte eine Landingpage für den Export wählen.';
                } else {
                    self::send_download('landingpage-' . CMS_Beratung_Settings::slug((string) ($exportPage['slug'] ?? 'beratung')) . '-' . date('Ymd') . '.json', 'application/json; charset=utf-8', CMS_Beratung_Import_Export::export_json($exportPage));
                }
            } elseif ($action === 'export_config') {
                self::send_download('cms-beratung-konfiguration-' . date('Ymd') . '.json', 'application/json; charset=utf-8', self::config_export_json());
            } elseif ($action === 'import_json') {
                try {
                    $raw = is_array($_FILES['import_file'] ?? null) ? CMS_Beratung_Import_Export::read_import_file($_FILES['import_file']) : '';
                    if ($raw === '') {
                        $raw = is_scalar($_POST['import_json'] ?? null) ? (string) $_POST['import_json'] : '';
                    }
                    $payload = CMS_Beratung_Import_Export::decode_import_json($raw);
                    if (!empty($_POST['skip_images'])) {
                        $payload = self::strip_import_images($payload);
                    }
                    $mode = (string) ($_POST['import_mode'] ?? 'new');
                    $overwriteId = max(0, (int) ($_POST['overwrite_id'] ?? 0));
                    if ($mode === 'overwrite' && ($overwriteId <= 0 || $storage->get_landingpage($overwriteId) === null)) {
                        throw new \InvalidArgumentException('Bitte die Landingpage wählen, die überschrieben werden soll.');
                    }
                    $payload['id'] = $mode === 'overwrite' ? $overwriteId : 0;
                    $payload['status'] = 'draft';
                    $originalSlug = (string) ($payload['slug'] ?? 'beratung');
                    $newId = $storage->save_landingpage($payload);
                    $imported = $storage->get_landingpage($newId);
                    $newSlug = (string) ($imported['slug'] ?? $originalSlug);
                    $notice = ($payload['id'] > 0 ? 'Landingpage überschrieben (Status: Entwurf).' : 'Landingpage als neuer Entwurf importiert.')
                        . ' ID ' . $newId . ', ' . count((array) ($imported['sections'] ?? [])) . ' Bereiche.'
                        . ($newSlug !== $originalSlug ? ' Slug-Konflikt erkannt, neuer Slug: ' . $newSlug : '');
                } catch (\Throwable $e) {
                    $error = 'Import fehlgeschlagen: ' . $e->getMessage();
                }
            } elseif ($action === 'import_config') {
                try {
                    $notice = self::import_config(is_array($_FILES['config_file'] ?? null) ? CMS_Beratung_Import_Export::read_import_file($_FILES['config_file']) : '');
                } catch (\Throwable $e) {
                    $error = 'Import der Konfiguration fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }
        $previewId = max(0, (int) ($_GET['export'] ?? 0));
        $previewPage = $previewId > 0 ? $storage->get_landingpage($previewId) : null;
        self::open_layout('cms-beratung-import-export');
        self::page_header('Import und Export', 'Landingpages und Konfiguration als JSON sichern, übertragen und wiederherstellen.');
        self::render_alerts($notice, $error);
        self::view('import-export', [
            'landingpages' => $storage->all_landingpages(),
            'previewPage' => $previewPage,
            'previewJson' => $previewPage !== null ? CMS_Beratung_Import_Export::export_json($previewPage) : '',
        ]);
        self::close_layout();
    }

    public static function render_help(): void
    {
        self::open_layout('cms-beratung-help');
        self::page_header('Hilfe und Dokumentation', 'Routing, Bereiche, SEO, Datenschutz und Sicherheit im Überblick.');
        self::view('help');
        self::close_layout();
    }

    // ─── Aktionen ──────────────────────────────────────────────────────────

    private static function handle_landingpage_actions(): void
    {
        if (!self::is_post() || (string) ($_POST['beratung_admin_action'] ?? '') !== 'landingpage_action') {
            return;
        }
        $back = self::list_query_params();
        if (!self::verify_nonce('beratung_landingpage_action')) {
            self::redirect('cms-beratung-landingpages', $back + ['notice' => 'csrf']);
        }
        $storage = CMS_Beratung_Storage::instance();
        $rowAction = (string) ($_POST['row_action'] ?? '');
        if ($rowAction !== '' && preg_match('/^(publish|draft|duplicate|delete|export):(\d+)$/', $rowAction, $m) === 1) {
            $id = (int) $m[2];
            $result = match ($m[1]) {
                'publish' => $storage->update_status($id, 'published') ? 'updated' : 'failed',
                'draft' => $storage->update_status($id, 'draft') ? 'updated' : 'failed',
                'duplicate' => $storage->duplicate_landingpage($id) > 0 ? 'duplicated' : 'failed',
                'delete' => $storage->delete_landingpage($id) ? 'deleted' : 'failed',
                default => 'failed',
            };
            if ($m[1] === 'export') {
                $page = $storage->get_landingpage($id);
                if ($page !== null) {
                    self::send_download('landingpage-' . CMS_Beratung_Settings::slug((string) ($page['slug'] ?? 'beratung')) . '-' . date('Ymd') . '.json', 'application/json; charset=utf-8', CMS_Beratung_Import_Export::export_json($page));
                }
                $result = 'notfound';
            }
            self::redirect('cms-beratung-landingpages', $back + ['notice' => $result]);
        }

        $ids = CMS_Beratung_Storage::clean_ids(is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []);
        $bulk = (string) ($_POST['bulk_action'] ?? '');
        if ($ids === []) {
            self::redirect('cms-beratung-landingpages', $back + ['notice' => 'none']);
        }
        $count = match ($bulk) {
            'publish' => $storage->update_status_bulk($ids, 'published'),
            'draft' => $storage->update_status_bulk($ids, 'draft'),
            'private' => $storage->update_status_bulk($ids, 'private'),
            'archived' => $storage->update_status_bulk($ids, 'archived'),
            'delete' => $storage->delete_landingpages($ids),
            default => -1,
        };
        self::redirect('cms-beratung-landingpages', $back + ['notice' => $count < 0 ? 'failed' : ($bulk === 'delete' ? 'deleted' : 'updated'), 'count' => max(0, $count)]);
    }

    private static function handle_submission_actions(): void
    {
        if (!self::is_post()) {
            return;
        }
        $action = (string) ($_POST['beratung_admin_action'] ?? '');
        $back = self::list_query_params(['status', 'landingpage', 'q', 'p']);
        if (!self::verify_nonce('beratung_submissions')) {
            self::redirect('cms-beratung-submissions', $back + ['notice' => 'csrf']);
        }
        $storage = CMS_Beratung_Storage::instance();
        if ($action === 'update_submission') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $ok = $storage->update_submission($id, (string) ($_POST['status'] ?? ''), mb_substr(is_scalar($_POST['internal_note'] ?? null) ? (string) $_POST['internal_note'] : '', 0, 5000));
            self::redirect('cms-beratung-submissions', ['id' => $id, 'notice' => $ok ? 'saved' : 'failed']);
        }
        if ($action === 'delete_submission') {
            $count = $storage->delete_submissions([(int) ($_POST['id'] ?? 0)]);
            self::redirect('cms-beratung-submissions', ['notice' => $count > 0 ? 'deleted' : 'failed']);
        }
        if ($action === 'export_csv') {
            self::export_submissions_csv([
                'status' => (string) ($_POST['status'] ?? ''),
                'landingpage_id' => max(0, (int) ($_POST['landingpage'] ?? 0)),
                'search' => mb_substr(trim(is_scalar($_POST['q'] ?? null) ? (string) $_POST['q'] : ''), 0, 120),
            ]);
        }
        if ($action === 'privacy_export') {
            $name = (string) ($_POST['file'] ?? '');
            $path = CMS_Beratung_Integrations::privacy_export_path($name);
            if ($path === null) {
                self::redirect('cms-beratung-submissions', ['notice' => 'notfound']);
            }
            if (!empty($_POST['delete'])) {
                CMS_Beratung_Integrations::delete_privacy_export($name);
                self::redirect('cms-beratung-submissions', ['notice' => 'deleted']);
            }
            self::send_download($name, 'application/json; charset=utf-8', (string) file_get_contents($path));
        }
        if ($action === 'bulk_submissions') {
            $ids = CMS_Beratung_Storage::clean_ids(is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []);
            if ($ids === []) {
                self::redirect('cms-beratung-submissions', $back + ['notice' => 'none']);
            }
            $bulk = (string) ($_POST['bulk_action'] ?? '');
            if ($bulk === 'delete') {
                $count = $storage->delete_submissions($ids);
                self::redirect('cms-beratung-submissions', $back + ['notice' => 'deleted', 'count' => $count]);
            }
            $count = array_key_exists($bulk, CMS_Beratung_Storage::submission_statuses()) ? $storage->update_submission_status_bulk($ids, $bulk) : -1;
            self::redirect('cms-beratung-submissions', $back + ['notice' => $count < 0 ? 'failed' : 'updated', 'count' => max(0, $count)]);
        }
    }

    /** @param array{status?:string,landingpage_id?:int,search?:string} $filters */
    private static function export_submissions_csv(array $filters): never
    {
        $statuses = CMS_Beratung_Storage::submission_statuses();
        $cell = static function (mixed $value): string {
            $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
            if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
                $value = "'" . $value; // Schutz vor Formel-Injection in Tabellenprogrammen
            }
            return '"' . str_replace('"', '""', $value) . '"';
        };
        $lines = [implode(';', array_map($cell, ['ID', 'Datum', 'Landingpage', 'Status', 'Name', 'E-Mail', 'Telefon', 'Unternehmen', 'Thema', 'Wunschleistung', 'Nachricht', 'Interne Notiz']))];
        foreach (CMS_Beratung_Storage::instance()->iterate_submissions($filters) as $row) {
            $lines[] = implode(';', array_map($cell, [
                (int) $row['id'], (string) ($row['created_at'] ?? ''), (string) ($row['landingpage_title'] ?? ''),
                $statuses[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
                (string) ($row['sender_name'] ?? ''), (string) ($row['sender_email'] ?? ''), (string) ($row['phone'] ?? ''),
                (string) ($row['company'] ?? ''), (string) ($row['topic'] ?? ''), (string) ($row['desired_service'] ?? ''),
                (string) ($row['message'] ?? ''), (string) ($row['internal_note'] ?? ''),
            ]));
        }
        self::send_download('beratung-anfragen-' . date('Ymd-His') . '.csv', 'text/csv; charset=utf-8', "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n");
    }

    /** Konfiguration (globale Einstellungen, M365-FAQs, eigene Presets) als JSON. */
    private static function config_export_json(): string
    {
        $settings = array_intersect_key(CMS_Beratung_Settings::all(), CMS_Beratung_Settings::defaults());
        $presets = [];
        foreach (CMS_Beratung_Storage::instance()->all_presets() as $preset) {
            if (empty($preset['is_system'])) {
                $presets[] = ['name' => (string) $preset['name'], 'description' => (string) ($preset['description'] ?? ''), 'design' => json_decode((string) ($preset['design_json'] ?? '{}'), true) ?: []];
            }
        }
        return json_encode([
            'schema' => 'cms-beratung-config/v1',
            'exported_at' => date('c'),
            'plugin_version' => CMS_BERATUNG_VERSION,
            'settings' => $settings,
            'm365_faqs' => CMS_Beratung_Storage::instance()->m365_faq_config(),
            'presets' => $presets,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    private static function import_config(string $raw): string
    {
        if (trim($raw) === '') {
            throw new \InvalidArgumentException('Bitte eine Konfigurationsdatei (.json) wählen.');
        }
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? '') !== 'cms-beratung-config/v1') {
            throw new \InvalidArgumentException('Die Datei ist keine CMS-Beratung-Konfiguration (schema cms-beratung-config/v1).');
        }
        $summary = [];
        if (is_array($data['settings'] ?? null)) {
            $keys = array_values(array_intersect(array_keys(CMS_Beratung_Settings::defaults()), array_keys($data['settings'])));
            CMS_Beratung_Settings::save(CMS_Beratung_Settings::sanitize_from_post($data['settings'], $keys));
            $summary[] = count($keys) . ' Einstellungen';
        }
        if (is_array($data['m365_faqs'] ?? null)) {
            $faq = $data['m365_faqs'];
            $items = is_array($faq['items'] ?? null) ? array_values($faq['items']) : [];
            $post = $faq;
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $post['faq_question'][$index] = (string) ($item['question'] ?? '');
                $post['faq_answer'][$index] = (string) ($item['answer'] ?? '');
                $post['faq_sort_order'][$index] = (int) ($item['sort_order'] ?? 0);
                if (!empty($item['enabled'])) {
                    $post['faq_enabled'][$index] = '1';
                }
                if (!empty($item['default_open'])) {
                    $post['faq_default_open'][$index] = '1';
                }
            }
            CMS_Beratung_Storage::instance()->save_m365_faq_config($post);
            $summary[] = count($items) . ' FAQs';
        }
        $presetCount = 0;
        foreach (is_array($data['presets'] ?? null) ? array_slice($data['presets'], 0, 50) : [] as $preset) {
            if (is_array($preset) && trim((string) ($preset['name'] ?? '')) !== '') {
                CMS_Beratung_Storage::instance()->create_custom_preset((string) $preset['name'], (string) ($preset['description'] ?? ''), is_array($preset['design'] ?? null) ? array_map('strval', array_filter($preset['design'], 'is_scalar')) : []);
                $presetCount++;
            }
        }
        if ($presetCount > 0) {
            $summary[] = $presetCount . ' Presets';
        }
        return 'Konfiguration importiert: ' . ($summary !== [] ? implode(', ', $summary) : 'keine Daten') . '.';
    }

    /** Download ohne Layout: Ausgabepuffer des Routers verwerfen, Header setzen, beenden. */
    private static function send_download(string $filename, string $contentType, string $body): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'download';
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, max-age=0');
        echo $body;
        exit;
    }

    // ─── Medien-Upload ─────────────────────────────────────────────────────

    /** Erlaubte Endungen und ihre MIME-Typen (finfo). */
    private const UPLOAD_MIME = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
    ];

    private const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;

    public static function handle_media_upload(): void
    {
        if (!self::has_access()) {
            self::json_response(['success' => 0, 'message' => 'Keine Berechtigung.'], 403);
        }

        $token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!class_exists('CMS\\Security') || !\CMS\Security::instance()->verifyPersistentToken($token, 'editorjs_media')) {
            self::json_response(['success' => 0, 'message' => 'Sicherheitsüberprüfung fehlgeschlagen.'], 403);
        }

        if (!defined('UPLOAD_PATH')) {
            self::json_response(['success' => 0, 'message' => 'Upload-Verzeichnis ist nicht konfiguriert.'], 500);
        }

        $file = $_FILES['file'] ?? ($_FILES['image'] ?? null);
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            self::json_response(['success' => 0, 'message' => 'Keine gültige Bilddatei empfangen.'], 400);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::UPLOAD_MAX_BYTES) {
            self::json_response(['success' => 0, 'message' => 'Die Bilddatei ist leer oder größer als 10 MB.'], 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'beratung-bild');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::UPLOAD_MIME[$extension]) || !self::uploaded_file_matches($tmpName, $extension)) {
            self::json_response(['success' => 0, 'message' => 'Nur Bilddateien (JPG, PNG, GIF, WebP, BMP, ICO, SVG) sind erlaubt.'], 400);
        }

        if ($extension === 'svg' && !self::svg_upload_is_safe($tmpName)) {
            self::json_response(['success' => 0, 'message' => 'SVG enthält nicht erlaubte aktive Inhalte.'], 400);
        }

        $targetDir = rtrim((string) UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'beratung';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            self::json_response(['success' => 0, 'message' => 'Der Ordner /uploads/beratung konnte nicht erstellt werden.'], 500);
        }

        $filename = self::random_upload_filename($targetDir, self::sanitize_upload_basename((string) pathinfo($originalName, PATHINFO_FILENAME)), $extension);
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            self::json_response(['success' => 0, 'message' => 'Die Datei konnte nicht gespeichert werden.'], 500);
        }
        @chmod($targetPath, 0644);

        $relativePath = 'beratung/' . $filename;
        $url = '/uploads/' . $relativePath;
        self::json_response([
            'success' => 1,
            'url' => $url,
            'path' => $relativePath,
            'file' => ['url' => $url, 'path' => $relativePath, 'name' => $filename, 'size' => filesize($targetPath) ?: $size, 'extension' => $extension],
            'message' => 'Bild wurde nach /uploads/beratung hochgeladen.',
        ]);
    }

    /** MIME-Prüfung per finfo gegen die Endung; Rastergrafiken müssen zusätzlich als Bild lesbar sein. */
    private static function uploaded_file_matches(string $tmpName, string $extension): bool
    {
        if ($tmpName === '' || !is_file($tmpName)) {
            return false;
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                // finfo-Objekt nicht explizit schließen (seit PHP 8.5 veraltet); es wird automatisch freigegeben.
                $mime = strtolower((string) finfo_file($finfo, $tmpName));
            }
        }
        if ($mime !== '' && !in_array($mime, self::UPLOAD_MIME[$extension], true)) {
            return false;
        }
        if ($extension === 'svg') {
            return true; // Inhalt prüft svg_upload_is_safe()
        }
        if ($extension === 'ico') {
            return $mime !== '';
        }
        return @getimagesize($tmpName) !== false;
    }

    private static function svg_upload_is_safe(string $tmpName): bool
    {
        $content = (string) @file_get_contents($tmpName, false, null, 0, 1024 * 1024);
        if ($content === '' || preg_match('#<svg[\s>]#i', $content) !== 1) {
            return false;
        }
        $checks = [
            '#<!(?:DOCTYPE|ENTITY)#i',
            '#<(script|foreignObject|iframe|object|embed|form|input|button|textarea|select|link|meta|base|style|animate|set|handler)\b#i',
            '/\son[a-z]+\s*=/i',
            '/(?:href|xlink:href|src)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data|https?):/i',
            '/url\s*\(\s*["\']?\s*(?:javascript|data|https?):/i',
            '/<\?xml-stylesheet/i',
        ];
        foreach ($checks as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return false;
            }
        }
        return true;
    }

    private static function sanitize_upload_basename(string $baseName): string
    {
        $baseName = strtolower(trim($baseName));
        $baseName = trim(preg_replace('/[^a-z0-9_-]+/i', '-', $baseName) ?? '', '-_');
        return substr($baseName !== '' ? $baseName : 'beratung-bild', 0, 40);
    }

    /** Zufälliger, nicht erratbarer Dateiname (lesbarer Präfix + 12 Hex-Zeichen). */
    private static function random_upload_filename(string $targetDir, string $baseName, string $extension): string
    {
        do {
            $candidate = $baseName . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        } while (is_file($targetDir . DIRECTORY_SEPARATOR . $candidate));
        return $candidate;
    }

    /** @param array<string,mixed> $payload */
    private static function json_response(array $payload, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"success":0}';
        exit;
    }

    // ─── Konfiguration für den Builder ────────────────────────────────────

    public static function render_media_config(): void
    {
        $config = [
            'uploadUrl' => '/api/cms-beratung/media-upload',
            'libraryUrl' => '/api/media',
            'csrfToken' => class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken('editorjs_media') : '',
            'uploadFolder' => '/uploads/beratung/',
            'maxSizeMb' => 10,
        ];
        echo '<script type="application/json" id="beratung-media-config">' . self::json_attr($config) . '</script>';
    }

    public static function render_expert_options_config(): void
    {
        $items = [];
        if (class_exists('CMS_365NET_Experts_And_Companie_Database')) {
            try {
                foreach (CMS_365NET_Experts_And_Companie_Database::instance()->getExpertsPublic('', '', 500, 0) as $expert) {
                    if (!is_object($expert) || (int) ($expert->id ?? 0) <= 0) {
                        continue;
                    }
                    $name = trim(trim((string) ($expert->first_name ?? '')) . ' ' . trim((string) ($expert->last_name ?? '')));
                    if ($name === '') {
                        continue;
                    }
                    $position = trim((string) ($expert->position ?? ''));
                    $company = trim((string) ($expert->company ?? ''));
                    $awards = trim((string) ($expert->awards ?? ''));
                    $items[] = [
                        'id' => (int) $expert->id,
                        'name' => $name,
                        'label' => trim('Expert: ' . $name . ($position !== '' ? ' · ' . $position : '') . ($company !== '' ? ' · ' . $company : '')),
                        'source' => 'experts',
                        'position' => $position,
                        'company' => $company,
                        'photo_url' => trim((string) ($expert->photo_url ?? '')),
                        'awards' => $awards,
                        'is_mvp' => $awards !== '' && stripos($awards, 'mvp') !== false,
                    ];
                }
            } catch (\Throwable) {
                $items = [];
            }
        }
        echo '<script type="application/json" id="beratung-expert-options">' . self::json_attr(['experts' => $items]) . '</script>';
    }

    /** JSON für Script-Datenblöcke und Attribute (HTML-Sonderzeichen escaped). */
    public static function json_attr(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    // ─── Formular-Helfer (auch für Views) ─────────────────────────────────

    public static function field(string $label, string $name, string $value, string $type = 'text', string $hint = ''): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><input type="' . self::esc($type) . '" name="' . self::esc($name) . '" value="' . self::esc($value) . '">';
        if ($hint !== '') {
            echo '<small class="beratung-field__hint">' . self::esc($hint) . '</small>';
        }
        echo '</label>';
    }

    public static function textarea(string $label, string $name, string $value, int $rows): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><textarea name="' . self::esc($name) . '" rows="' . $rows . '">' . self::esc($value) . '</textarea></label>';
    }

    /** @param array<string,string> $options */
    public static function select(string $label, string $name, string $value, array $options): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><select name="' . self::esc($name) . '">';
        foreach ($options as $key => $optionLabel) {
            echo '<option value="' . self::esc((string) $key) . '"' . ((string) $key === $value ? ' selected' : '') . '>' . self::esc($optionLabel) . '</option>';
        }
        echo '</select></label>';
    }

    public static function checkbox(string $label, string $name, bool $checked, string $hint = ''): void
    {
        echo '<input type="hidden" name="' . self::esc($name) . '" value="0"><label class="beratung-check"><input type="checkbox" name="' . self::esc($name) . '" value="1"' . ($checked ? ' checked' : '') . '> <span>' . self::esc($label) . '</span></label>';
        if ($hint !== '') {
            echo '<small class="beratung-field__hint">' . self::esc($hint) . '</small>';
        }
    }

    public static function hidden_token(string $action): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::esc(self::nonce($action)) . '">';
    }

    /** Pagination unter Listen. */
    public static function pagination(string $slug, array $params, int $total, int $page, int $perPage): string
    {
        $pages = (int) max(1, ceil($total / max(1, $perPage)));
        if ($pages <= 1) {
            return '';
        }
        $html = '<nav class="beratung-pagination" aria-label="Seiten"><span>Seite ' . $page . ' von ' . $pages . ' (' . $total . ' Einträge)</span>';
        if ($page > 1) {
            $html .= self::button_link('Zurück', self::admin_url($slug, $params + ['p' => $page - 1]), 'ti ti-chevron-left', 'btn btn-sm btn-outline-secondary');
        }
        if ($page < $pages) {
            $html .= self::button_link('Weiter', self::admin_url($slug, $params + ['p' => $page + 1]), 'ti ti-chevron-right', 'btn btn-sm btn-outline-secondary');
        }
        return $html . '</nav>';
    }

    public static function status_badge(string $status, string $label): string
    {
        $tone = match ($status) {
            'published', 'done', 'answered' => 'success',
            'new', 'draft' => 'warning',
            'in_progress', 'read', 'private' => 'info',
            default => 'muted',
        };
        return '<span class="beratung-badge beratung-badge--' . $tone . '">' . self::esc($label) . '</span>';
    }

    // ─── Allgemeine Helfer ─────────────────────────────────────────────────

    private static function settings_tab(): string
    {
        $tab = (string) ($_GET['tab'] ?? 'design');
        return array_key_exists($tab, CMS_Beratung_Settings::tabs()) ? $tab : 'design';
    }

    /** @param list<string> $keys @return array<string,string> */
    private static function list_query_params(array $keys = ['status', 'q', 'sort', 'p']): array
    {
        $params = [];
        foreach ($keys as $key) {
            $value = $_GET[$key] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $params[$key] = mb_substr((string) $value, 0, 120);
            }
        }
        return $params;
    }

    private static function is_post(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private static function has_access(): bool
    {
        $isAdmin = class_exists('CMS\\Auth') && \CMS\Auth::instance()->isAdmin();
        $hasCapability = !function_exists('current_user_can') || current_user_can('manage_options');
        return $isAdmin && $hasCapability;
    }

    private static function check_access(): void
    {
        if (!self::has_access()) {
            header('Location: ' . self::site_url() . '/', true, 303);
            exit;
        }
    }

    public static function nonce(string $action): string
    {
        return class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken($action) : '';
    }

    /** CSRF (Einmal-Token) und Admin-Recht serverseitig prüfen. */
    private static function verify_nonce(string $action): bool
    {
        return self::has_access()
            && class_exists('CMS\\Security')
            && \CMS\Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), $action);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function strip_import_images(array $payload): array
    {
        foreach (['image_url', 'logo_url', 'background_image_url', 'trust_image_url', 'og_image', 'twitter_image', 'divider_image_url', 'standalone_header_blog_logo_url', 'standalone_header_partner_logo_url'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '';
            }
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::strip_import_images($value);
            }
        }
        return $payload;
    }

    /**
     * Admin-Pfad einer Seite. Editor und Vorschau sind Ansichten der Landingpage-Liste (?view=).
     *
     * @param array<string,scalar> $params
     */
    public static function admin_url(string $pageSlug, array $params = []): string
    {
        $pageSlug = self::normalize_slug($pageSlug);
        if (in_array($pageSlug, ['cms-beratung-new', 'cms-beratung-preview'], true)) {
            $params = ['view' => $pageSlug] + $params;
            $pageSlug = self::DEFAULT_PAGE_SLUG;
        }
        $url = $pageSlug === self::MENU_SLUG || !isset(self::PAGES[$pageSlug])
            ? '/admin/plugins/' . self::MENU_SLUG
            : '/admin/plugins/' . self::MENU_SLUG . '/' . rawurlencode($pageSlug);
        return $params !== [] ? $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    public static function preview_url(int $id): string
    {
        return '/api/cms-beratung/preview?id=' . max(0, $id);
    }

    public static function public_landingpage_url(string $slug): string
    {
        $slug = trim($slug, '/');
        return $slug === '' ? '' : self::site_url() . '/beratung/' . rawurlencode($slug);
    }

    private static function site_url(): string
    {
        return defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    }

    /** @param array<string,scalar> $params */
    private static function redirect(string $pageSlug, array $params = []): never
    {
        $target = class_exists('CMS\\Security')
            ? \CMS\Security::safeLocalPath(self::admin_url($pageSlug, $params), '/admin/plugins/' . self::MENU_SLUG)
            : self::admin_url($pageSlug, $params);
        header('Location: ' . self::site_url() . $target, true, 303);
        exit;
    }

    private static function normalize_slug(string $slug): string
    {
        return trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($slug)), '-');
    }

    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

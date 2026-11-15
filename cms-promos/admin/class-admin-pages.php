<?php
/**
 * Admin-Seiten (Layout-Vertrag 4.0.0): Übersicht, Promos, Platzierungen, Einstellungen.
 *
 * Der AdminRouter liefert Shell, Sidebar und Header; die Callbacks geben nur Inhalt aus.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use CMS\Auth;
use CMS\Security;

final class CMS_Promos_Admin_Pages
{
    public const ROOT_SLUG = 'promos-dashboard';
    private const DEFAULT_PAGE = 'promos-dashboard';

    /** Unterseiten-Slug → Render-Methode. Slugs bleiben aus 3.x erhalten (Lesezeichen, Formularziele). */
    private const PAGES = [
        'promos-dashboard' => 'render_dashboard',
        'promos-items' => 'render_promos',
        'promos-placements' => 'render_placements',
        'promos-settings' => 'render_settings',
    ];

    private const CSRF_ACTION = 'promos_admin';
    private const EXPORT_CSRF_ACTION = 'promos_export';
    private const NOTICE_KEY = 'cms_promos_admin_notice';
    private const PER_PAGE = 25;
    private const MAX_IMPORT_BYTES = 2 * 1024 * 1024;

    /** Erlaubte Listen-Parameter, die nach Aktionen erhalten bleiben. */
    private const LIST_QUERY_KEYS = ['q', 'status', 'placement', 'sort', 'p', 'hook'];

    /** @var array{type:string,message:string}|null Meldung ohne Redirect (Validierungsfehler im Formular) */
    private static ?array $inlineNotice = null;
    /** @var array<string,mixed>|null zuletzt abgeschickte Formularwerte nach einem Validierungsfehler */
    private static ?array $formData = null;
    /** @var array<string,int>|null */
    private static ?array $stats = null;

    // ── Dispatcher ──────────────────────────────────────────────────────────

    public static function render_page_dispatcher(): void
    {
        $method = self::PAGES[self::current_page()] ?? self::PAGES[self::DEFAULT_PAGE];
        self::$method();
    }

    public static function render_dashboard(): void
    {
        self::boot('promos-dashboard');
        $repository = CMS_Promos_Repository::instance();
        $stats = self::stats();
        $settings = $repository->get_settings();
        $placements = $repository->get_placements();
        $recent = $repository->get_recent_promos(8);
        $tablesExist = CMS_Promos_Installer::tables_exist();
        $notice = self::pull_notice();

        self::open('promos-dashboard');
        include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-dashboard.php';
        self::close();
    }

    public static function render_promos(): void
    {
        self::boot('promos-items');
        $repository = CMS_Promos_Repository::instance();
        $placements = $repository->get_placements();
        $notice = self::pull_notice();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);
        $exportToken = Security::instance()->generateToken(self::EXPORT_CSRF_ACTION);
        $statusLabels = CMS_Promos_Repository::STATUS_LABELS;

        $editId = self::get_int($_GET, 'edit');
        $isForm = $editId > 0 || (string) ($_GET['action'] ?? '') === 'new' || self::$formData !== null;
        if ($isForm) {
            $promo = $editId > 0 ? $repository->get_promo($editId) : null;
            if ($editId > 0 && $promo === null && self::$formData === null) {
                $notice = ['type' => 'error', 'message' => 'Die Promo wurde nicht gefunden.'];
                $isForm = false;
            }
            if (self::$formData !== null) {
                $promo = array_merge($promo ?? [], self::$formData, [
                    'id' => self::get_int(self::$formData, 'promo_id'),
                    'is_featured' => isset(self::$formData['is_featured']) ? 1 : 0,
                ]);
            }
            $settings = $repository->get_settings();
        }

        if (!$isForm) {
            $filters = self::promo_filters();
            $list = $repository->list_promos($filters, max(1, self::get_int($_GET, 'p')), self::PER_PAGE);
        }

        self::open('promos-items');
        include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-promos.php';
        self::close();
    }

    public static function render_placements(): void
    {
        self::boot('promos-placements');
        $repository = CMS_Promos_Repository::instance();
        $hookOptions = $repository->get_theme_hook_options();
        $notice = self::pull_notice();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

        $editId = self::get_int($_GET, 'edit');
        $isForm = $editId > 0 || (string) ($_GET['action'] ?? '') === 'new' || self::$formData !== null;
        if ($isForm) {
            $placement = $editId > 0 ? $repository->get_placement($editId) : null;
            if ($editId > 0 && $placement === null && self::$formData === null) {
                $notice = ['type' => 'error', 'message' => 'Die Platzierung wurde nicht gefunden.'];
                $isForm = false;
            }
            if (self::$formData !== null) {
                $placement = array_merge($placement ?? [], self::$formData, ['id' => self::get_int(self::$formData, 'placement_id')]);
            }
        }

        if (!$isForm) {
            $filters = [
                'q' => self::get_string($_GET, 'q', 120),
                'status' => in_array((string) ($_GET['status'] ?? ''), ['active', 'inactive'], true) ? (string) $_GET['status'] : '',
                'hook' => array_key_exists((string) ($_GET['hook'] ?? ''), $hookOptions) ? (string) $_GET['hook'] : '',
            ];
            $placements = $repository->filter_placements($filters);
        }

        self::open('promos-placements');
        include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-placements.php';
        self::close();
    }

    public static function render_settings(): void
    {
        self::boot('promos-settings');
        $repository = CMS_Promos_Repository::instance();
        $settings = $repository->get_settings();
        if (self::$formData !== null) {
            // Nicht angehakte Checkboxen fehlen im POST → ausdrücklich auf „aus“ setzen.
            $settings = array_merge($settings, self::$formData, [
                'archive_indexable' => isset(self::$formData['archive_indexable']) ? '1' : '0',
                'click_export_enabled' => isset(self::$formData['click_export_enabled']) ? '1' : '0',
            ]);
        }
        $notice = self::pull_notice();
        $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);
        $exportToken = Security::instance()->generateToken(self::EXPORT_CSRF_ACTION);
        $retentionDays = max(0, (int) ($settings['retention_days'] ?? 0));
        $retentionDue = $repository->count_retention_due($retentionDays);
        $maxImportMb = (int) (self::MAX_IMPORT_BYTES / 1024 / 1024);
        $webhookStored = $repository->has_webhook_url();
        $webhookEncrypted = $repository->is_webhook_encrypted();
        // Secret nie ins Formular zurückgeben (auch nicht nach einem Validierungsfehler).
        unset($settings['click_export_webhook_url']);

        self::open('promos-settings');
        include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-settings.php';
        self::close();
    }

    // ── Layout ──────────────────────────────────────────────────────────────

    private static function boot(string $page): void
    {
        self::check_access();
        self::handle_post($page);
    }

    private static function load_workspace(): void
    {
        if (!function_exists('cms_plugin_workspace_open')) {
            $core = ABSPATH . 'admin/partials/plugin-workspace.php';
            if (is_file($core)) {
                require_once $core;
            }
        }
        if (!function_exists('cms_plugin_workspace_open')) {
            require_once CMS_PROMOS_PLUGIN_DIR . 'includes/plugin-workspace.php';
            // Rückfall-CSS nur, wenn der Core das Layout nicht liefert.
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/css/cms-plugin-workspace.css?v=' . CMS_PROMOS_VERSION, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    private static function open(string $active): void
    {
        self::load_workspace();
        self::enqueue_admin_assets();

        $stats = self::stats();
        $schemaOk = CMS_Promos_Installer::is_schema_current();
        $ctr = $stats['impressions'] > 0 ? round($stats['clicks'] / $stats['impressions'] * 100, 1) : 0.0;

        echo cms_plugin_workspace_open([
            'plugin' => self::ROOT_SLUG,
            'title' => '365CMS | Promos',
            'icon' => 'ti ti-speakerphone',
            'version' => CMS_PROMOS_VERSION,
            'status' => $schemaOk ? ['label' => 'Aktiv', 'tone' => 'success'] : ['label' => 'Datenbank prüfen', 'tone' => 'warning'],
            'description' => 'Banner, Teaser und CTA-Flächen mit Platzierungen, Laufzeiten und Klick-Tracking.',
            'stats' => [
                ['label' => 'Promos gesamt', 'value' => $stats['promos'], 'icon' => 'ti ti-speakerphone', 'url' => self::url('promos-items')],
                ['label' => 'Laufen gerade', 'value' => $stats['live_promos'], 'icon' => 'ti ti-player-play', 'tone' => 'success', 'url' => self::url('promos-items', ['status' => 'live'])],
                ['label' => 'Impressions', 'value' => $stats['impressions'], 'icon' => 'ti ti-eye'],
                ['label' => 'Klicks', 'value' => $stats['clicks'], 'icon' => 'ti ti-click', 'hint' => 'Klickrate: ' . number_format($ctr, 1, ',', '.') . ' %'],
            ],
            'nav' => [
                ['slug' => 'promos-dashboard', 'label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard', 'url' => self::url('promos-dashboard'), 'active' => $active === 'promos-dashboard'],
                ['slug' => 'promos-items', 'label' => 'Promos', 'icon' => 'ti ti-speakerphone', 'url' => self::url('promos-items'), 'active' => $active === 'promos-items',
                    'badge' => $stats['expired_active'], 'badge_tone' => 'warning'],
                ['slug' => 'promos-placements', 'label' => 'Platzierungen', 'icon' => 'ti ti-layout-grid', 'url' => self::url('promos-placements'), 'active' => $active === 'promos-placements',
                    'badge' => $stats['placements']],
                ['slug' => 'promos-settings', 'label' => 'Einstellungen', 'icon' => 'ti ti-settings', 'url' => self::url('promos-settings'), 'active' => $active === 'promos-settings'],
            ],
            'nav_explicit' => true,
        ]);
    }

    private static function close(): void
    {
        echo cms_plugin_workspace_close();
    }

    private static function enqueue_admin_assets(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/css/promos-admin.css?v=' . CMS_PROMOS_VERSION, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<script src="' . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/js/promos-admin.js?v=' . CMS_PROMOS_VERSION, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }

    /**
     * @return array<string,int>
     */
    private static function stats(): array
    {
        return self::$stats ??= CMS_Promos_Repository::instance()->get_dashboard_stats();
    }

    // ── POST-Verarbeitung ───────────────────────────────────────────────────

    private static function handle_post(string $page): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $action = (string) preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['action'] ?? '')));
        $security = Security::instance();
        $repository = CMS_Promos_Repository::instance();

        // Downloads: wiederverwendbares Token, damit die Seite nach dem Download weiter bedienbar bleibt.
        if ($action === 'export_csv' || $action === 'export_json') {
            if (!$security->verifyPersistentToken((string) ($_POST['export_token'] ?? ''), self::EXPORT_CSRF_ACTION)) {
                self::finish($page, false, 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.');
            }
            $action === 'export_csv' ? self::send_csv_export($repository) : self::send_json_export($repository);
        }

        if (!$security->verifyToken((string) ($_POST['csrf_token'] ?? ''), self::CSRF_ACTION)) {
            self::finish($page, false, 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
        }

        $singleDelete = self::get_int($_POST, 'single_delete');

        switch ($action) {
            case 'save_promo':
                $result = $repository->save_promo($_POST);
                if (!$result['success']) {
                    self::keep_form($result);
                    return;
                }
                self::finish('promos-items', true, (string) ($result['message'] ?? ''), ['edit' => (int) ($result['id'] ?? 0)]);
                // no break – finish() beendet den Request

            case 'bulk_promos':
                $result = $singleDelete > 0
                    ? $repository->delete_promo($singleDelete)
                    : $repository->bulk_promos((string) ($_POST['bulk_action'] ?? ''), self::post_ids());
                self::finish('promos-items', $result['success'], self::result_message($result), self::return_query());
                // no break

            case 'save_placement':
                $result = $repository->save_placement($_POST);
                if (!$result['success']) {
                    self::keep_form($result);
                    return;
                }
                self::finish('promos-placements', true, (string) ($result['message'] ?? ''), ['edit' => (int) ($result['id'] ?? 0)]);
                // no break

            case 'bulk_placements':
                $result = $singleDelete > 0
                    ? $repository->delete_placement($singleDelete)
                    : $repository->bulk_placements((string) ($_POST['bulk_action'] ?? ''), self::post_ids());
                self::finish('promos-placements', $result['success'], self::result_message($result), self::return_query());
                // no break

            case 'save_settings':
                $result = $repository->save_settings($_POST);
                if (!$result['success']) {
                    self::keep_form($result);
                    return;
                }
                self::$stats = null;
                self::finish('promos-settings', true, (string) ($result['message'] ?? ''));
                // no break

            case 'import_json':
                $result = self::handle_import($repository);
                self::finish('promos-settings', $result['success'], self::result_message($result));
                // no break

            case 'run_retention':
                $days = $repository->get_retention_days();
                if ($days <= 0) {
                    self::finish('promos-settings', false, 'Es ist keine Löschfrist eingestellt.');
                }
                $deleted = $repository->prune_retention($days);
                self::finish('promos-settings', true, sprintf('%d abgelaufene/archivierte Promo(s) gelöscht.', $deleted));
                // no break

            default:
                self::finish($page, false, 'Unbekannte Aktion.');
        }
    }

    /**
     * @param array<string,mixed> $result
     */
    private static function keep_form(array $result): void
    {
        $data = [];
        foreach ($_POST as $key => $value) {
            if (is_string($key) && is_scalar($value) && !in_array($key, ['csrf_token', 'export_token', 'action', 'click_export_webhook_url'], true)) {
                $data[$key] = (string) $value;
            }
        }
        self::$formData = $data;
        self::$inlineNotice = ['type' => 'error', 'message' => self::result_message($result)];
    }

    /**
     * @param array<string,mixed> $result
     */
    private static function result_message(array $result): string
    {
        return (string) ($result['message'] ?? $result['error'] ?? '');
    }

    /**
     * @return list<int>
     */
    private static function post_ids(): array
    {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($id): int => is_scalar($id) ? max(0, (int) $id) : 0, $ids)));
    }

    /**
     * Listenfilter des Formulars (hidden `return_query`) nach der Aktion wiederherstellen – nur bekannte Schlüssel.
     *
     * @return array<string,string>
     */
    private static function return_query(): array
    {
        $raw = (string) ($_POST['return_query'] ?? '');
        if ($raw === '' || strlen($raw) > 1000) {
            return [];
        }

        parse_str($raw, $parsed);
        $query = [];
        foreach (self::LIST_QUERY_KEYS as $key) {
            if (isset($parsed[$key]) && is_scalar($parsed[$key]) && (string) $parsed[$key] !== '') {
                $query[$key] = mb_substr((string) $parsed[$key], 0, 120);
            }
        }

        return $query;
    }

    /**
     * @return array{success:bool,message?:string,error?:string}
     */
    private static function handle_import(CMS_Promos_Repository $repository): array
    {
        $file = $_FILES['import_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'Bitte eine JSON-Datei auswählen.'];
        }
        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['success' => false, 'error' => 'Der Upload ist fehlgeschlagen.'];
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_IMPORT_BYTES) {
            return ['success' => false, 'error' => sprintf('Die Datei ist leer oder größer als %d MB.', (int) (self::MAX_IMPORT_BYTES / 1024 / 1024))];
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'json') {
            return ['success' => false, 'error' => 'Nur .json-Dateien aus dem Promo-Export werden akzeptiert.'];
        }

        $tmp = (string) $file['tmp_name'];
        if (class_exists('finfo')) {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!in_array($mime, ['application/json', 'text/plain', 'text/json'], true)) {
                return ['success' => false, 'error' => 'Der Dateityp wird nicht unterstützt (' . $mime . ').'];
            }
        }

        $raw = file_get_contents($tmp);
        if (!is_string($raw)) {
            return ['success' => false, 'error' => 'Die Datei konnte nicht gelesen werden.'];
        }
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['success' => false, 'error' => 'Die Datei enthält kein gültiges JSON.'];
        }

        return is_array($data) ? $repository->import_config($data) : ['success' => false, 'error' => 'Die Datei enthält kein gültiges JSON-Objekt.'];
    }

    private static function send_csv_export(CMS_Promos_Repository $repository): never
    {
        $rows = $repository->get_export_promos();
        self::clear_output_buffers();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="promos-' . gmdate('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        $columns = [
            'id' => 'ID', 'title' => 'Titel', 'slug' => 'Slug', 'status' => 'Status', 'placement_name' => 'Platzierung', 'target_url' => 'Ziel-URL',
            'start_at' => 'Start', 'end_at' => 'Ende', 'priority' => 'Priorität', 'is_featured' => 'Featured', 'impression_count' => 'Impressions',
            'click_count' => 'Klicks', 'utm_source' => 'UTM Source', 'utm_medium' => 'UTM Medium', 'utm_campaign' => 'UTM Campaign',
            'updated_at' => 'Geändert',
        ];
        fputcsv($out, array_values($columns), ';', '"', '');
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = self::csv_cell($row[$key] ?? '');
            }
            fputcsv($out, $line, ';', '"', '');
        }
        fclose($out);
        exit;
    }

    private static function send_json_export(CMS_Promos_Repository $repository): never
    {
        $json = json_encode($repository->build_config_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::clear_output_buffers();

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="promos-config-' . gmdate('Y-m-d') . '.json"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo is_string($json) ? $json : '{}';
        exit;
    }

    /** Formel-Injection-Schutz: Zellen mit = + - @ Tab CR am Anfang werden mit ' entschärft. */
    private static function csv_cell(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private static function clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    // ── Zugriff, Meldungen, Redirects ───────────────────────────────────────

    private static function check_access(): void
    {
        $allowed = class_exists('CMS\\Auth') && Auth::instance()->isAdmin();
        if ($allowed && function_exists('current_user_can')) {
            $allowed = (bool) current_user_can('manage_options');
        }
        if ($allowed) {
            return;
        }

        self::clear_output_buffers();
        header('Location: ' . rtrim((string) SITE_URL, '/') . '/', true, 303);
        exit;
    }

    /**
     * Meldung merken und per 303 auf die eigene Unterseite zurückleiten (PRG). Die Admin-Session läuft
     * im Admin immer; ohne Session wird ein fester Statuscode als Query-Parameter übergeben.
     *
     * @param array<string,int|string> $query
     */
    private static function finish(string $page, bool $success, string $message, array $query = []): never
    {
        $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== 0);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::NOTICE_KEY] = ['type' => $success ? 'success' : 'error', 'message' => mb_substr($message, 0, 500)];
        } else {
            $query['notice'] = $success ? 'done' : 'failed';
        }

        $path = self::url(isset(self::PAGES[$page]) ? $page : self::DEFAULT_PAGE, $query);
        $target = class_exists('CMS\\Security') ? Security::safeLocalPath($path, self::url(self::DEFAULT_PAGE)) : $path;

        self::clear_output_buffers();
        header('Location: ' . rtrim((string) SITE_URL, '/') . $target, true, 303);
        exit;
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private static function pull_notice(): ?array
    {
        if (self::$inlineNotice !== null) {
            return self::$inlineNotice;
        }

        if (session_status() === PHP_SESSION_ACTIVE && is_array($_SESSION[self::NOTICE_KEY] ?? null)) {
            $notice = $_SESSION[self::NOTICE_KEY];
            unset($_SESSION[self::NOTICE_KEY]);

            return ['type' => ($notice['type'] ?? '') === 'success' ? 'success' : 'error', 'message' => (string) ($notice['message'] ?? '')];
        }

        return match ((string) ($_GET['notice'] ?? '')) {
            'done' => ['type' => 'success', 'message' => 'Die Aktion wurde ausgeführt.'],
            'failed' => ['type' => 'error', 'message' => 'Die Aktion konnte nicht ausgeführt werden.'],
            default => null,
        };
    }

    // ── URLs und Request-Helfer ─────────────────────────────────────────────

    /**
     * @param array<string,int|string> $query
     */
    public static function url(string $page, array $query = []): string
    {
        $page = isset(self::PAGES[$page]) ? $page : self::DEFAULT_PAGE;
        $url = '/admin/plugins/' . self::ROOT_SLUG . '/' . $page;
        $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);

        return $query !== [] ? $url . '?' . http_build_query($query) : $url;
    }

    private static function current_page(): string
    {
        $page = strtolower(trim((string) (is_string($_GET['page'] ?? null) ? $_GET['page'] : '')));
        if (isset(self::PAGES[$page])) {
            return $page;
        }

        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        if (preg_match('#/admin/plugins/' . preg_quote(self::ROOT_SLUG, '#') . '/([a-z0-9_-]+)/?$#', $path, $m) === 1 && isset(self::PAGES[$m[1]])) {
            return $m[1];
        }

        return self::DEFAULT_PAGE;
    }

    /**
     * @return array{q:string,status:string,placement:string,sort:string}
     */
    private static function promo_filters(): array
    {
        $status = (string) ($_GET['status'] ?? '');
        $placement = (string) ($_GET['placement'] ?? '');
        $sort = (string) ($_GET['sort'] ?? '');

        return [
            'q' => self::get_string($_GET, 'q', 120),
            'status' => isset(CMS_Promos_Repository::STATUS_LABELS[$status]) || in_array($status, ['live', 'expired'], true) ? $status : '',
            'placement' => $placement === 'none' || (int) $placement > 0 ? ($placement === 'none' ? 'none' : (string) (int) $placement) : '',
            'sort' => isset(CMS_Promos_Repository::PROMO_SORTS[$sort]) ? $sort : '',
        ];
    }

    /**
     * Aktuelle Listenparameter als Query-String (für `return_query` und Pagination).
     *
     * @param array<string,string> $filters
     */
    public static function list_query(array $filters, int $page = 0): string
    {
        $query = array_filter($filters, static fn ($value): bool => (string) $value !== '');
        if ($page > 1) {
            $query['p'] = (string) $page;
        }

        return http_build_query($query);
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function get_int(array $source, string $key): int
    {
        $value = $source[$key] ?? 0;

        return is_scalar($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function get_string(array $source, string $key, int $max): string
    {
        $value = $source[$key] ?? '';

        return is_scalar($value) ? mb_substr(trim(strip_tags((string) $value)), 0, $max) : '';
    }

    public static function status_tone(string $status, bool $live = false): string
    {
        if ($status === 'active') {
            return $live ? 'green' : 'yellow';
        }

        return match ($status) {
            'paused' => 'orange',
            'archived' => 'secondary',
            default => 'azure',
        };
    }

    public static function format_datetime(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);

        return $ts !== false ? date('d.m.Y H:i', $ts) : '';
    }
}

<?php
/**
 * Admin-Seiten von CMS Promos im einheitlichen 365CMS-4.0.0-Plugin-Layout
 * (Split-Header mit KPI-Karten, linkes Plugin-Menü, Inhaltsspalte).
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use CMS\Security;

final class CMS_Promos_Admin_Pages
{
    public const ROOT_SLUG = 'promos-dashboard';
    public const CAPABILITY = 'manage_options';
    private const CSRF_ACTION = 'promos_admin';
    private const EXPORT_ACTION = 'promos_export';
    private const NOTICE_KEY = 'promos_admin_notice';

    /** @var array<string,array{label:string,icon:string,callback:string}> */
    private const PAGES = [
        'promos-dashboard' => ['label' => 'Übersicht', 'icon' => 'ti ti-layout-dashboard', 'callback' => 'render_dashboard'],
        'promos-items' => ['label' => 'Promos', 'icon' => 'ti ti-speakerphone', 'callback' => 'render_promos'],
        'promos-placements' => ['label' => 'Platzierungen', 'icon' => 'ti ti-layout-board', 'callback' => 'render_placements'],
        'promos-settings' => ['label' => 'Einstellungen', 'icon' => 'ti ti-settings', 'callback' => 'render_settings'],
    ];

    public static function render_page_dispatcher(): void
    {
        $page = self::current_page();
        $callback = [self::class, self::PAGES[$page]['callback']];
        if (is_callable($callback)) {
            call_user_func($callback);
        }
    }

    public static function render_dashboard(): void
    {
        self::render_page('promos-dashboard', static function (CMS_Promos_Repository $repository): void {
            $stats = $repository->get_dashboard_stats();
            $settings = $repository->get_settings();
            $placements = $repository->get_placements();
            $expired = $repository->count_expired_active();
            $hookPlacements = count(array_filter($placements, static fn (array $p): bool => ($p['status'] ?? '') === 'active' && ($p['theme_hook'] ?? 'manual') !== 'manual'));
            $schemaOk = CMS_Promos_Installer::installed_version() === CMS_PROMOS_DB_VERSION;
            $exportEnabled = ($settings['click_export_enabled'] ?? '0') === '1';

            $activity = [];
            foreach ($repository->get_recent_promos(6) as $item) {
                $impressions = (int) ($item['impression_count'] ?? 0);
                $clicks = (int) ($item['click_count'] ?? 0);
                $activity[] = [
                    'title' => (string) $item['title'],
                    'url' => self::page_url('promos-items', ['edit' => (int) $item['id']]),
                    'meta' => trim(($item['placement_name'] ?? '') !== '' ? (string) $item['placement_name'] . ' · ' : '')
                        . number_format($impressions, 0, ',', '.') . ' Impressions · ' . number_format($clicks, 0, ',', '.') . ' Klicks · ' . self::format_date((string) ($item['updated_at'] ?? '')),
                    'badge' => $repository->status_label((string) ($item['status'] ?? 'draft')),
                    'tone' => self::status_tone((string) ($item['status'] ?? 'draft')),
                ];
            }

            echo cms_plugin_dashboard([
                'intro' => 'Kampagnen, CTA-Kacheln und Banner zentral pflegen und automatisch in Theme-Hooks ausspielen.',
                'actions' => [
                    ['label' => 'Neue Promo', 'url' => self::page_url('promos-items', ['action' => 'new']), 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Teaser, Ziel-URL, Laufzeit und Platzierung festlegen.'],
                    ['label' => 'Neue Platzierung', 'url' => self::page_url('promos-placements', ['action' => 'new']), 'icon' => 'ti ti-layout-board-split', 'description' => 'Fläche anlegen und an einen Theme-Hook koppeln.'],
                    ['label' => 'Leistung als CSV', 'url' => self::export_url('csv'), 'icon' => 'ti ti-file-spreadsheet', 'description' => 'Impressions, Klicks und Klickrate exportieren.'],
                    ['label' => 'Öffentliches Archiv', 'url' => '/promos', 'icon' => 'ti ti-world', 'external' => true, 'description' => 'Aktive Promos auf /promos ansehen.'],
                ],
                'checks' => [
                    ['label' => 'Datenbank-Schema', 'status' => $schemaOk ? 'ok' : 'warning', 'value' => $schemaOk ? 'v' . CMS_PROMOS_DB_VERSION : 'Migration ausstehend', 'hint' => 'Tabellen promos, promo_placements und promo_settings.'],
                    ['label' => 'Platzierungen mit Theme-Hook', 'status' => $hookPlacements > 0 ? 'ok' : 'info', 'value' => $hookPlacements, 'hint' => $hookPlacements > 0 ? 'Aktive Promos erscheinen automatisch im Theme.' : 'Promos erscheinen nur im Archiv /promos.', 'url' => self::page_url('promos-placements')],
                    ['label' => 'Abgelaufene aktive Promos', 'status' => $expired > 0 ? 'warning' : 'ok', 'value' => $expired, 'hint' => $expired > 0 ? 'Enddatum überschritten – archivieren oder verlängern.' : 'Alle aktiven Promos liegen in ihrer Laufzeit.', 'url' => self::page_url('promos-items', ['status' => 'expired'])],
                    ['label' => 'Klick-Export (Webhook)', 'status' => $exportEnabled ? 'ok' : 'muted', 'value' => $exportEnabled ? 'aktiv' : 'aus', 'hint' => 'Hook cms_promos_click_export ist immer verfügbar.', 'url' => self::page_url('promos-settings')],
                    ['label' => 'Gesamtleistung', 'status' => 'info', 'value' => number_format((int) $stats['clicks'], 0, ',', '.') . ' / ' . number_format((int) $stats['impressions'], 0, ',', '.'), 'hint' => 'Klicks / Impressions seit dem letzten Zurücksetzen.'],
                ],
                'activity' => [
                    'heading' => 'Zuletzt bearbeitet',
                    'items' => $activity,
                    'empty' => 'Noch keine Promos angelegt.',
                    'more_url' => self::page_url('promos-items'),
                    'more_label' => 'Alle Promos',
                ],
            ]);
        });
    }

    public static function render_promos(): void
    {
        self::render_page('promos-items', static function (CMS_Promos_Repository $repository): void {
            $editId = self::request_int($_GET, 'edit');
            $promo = $editId > 0 ? $repository->get_promo($editId) : null;
            $showForm = $promo !== null || (string) ($_GET['action'] ?? '') === 'new';
            $filters = [
                'status' => self::request_key($_GET, 'status'),
                'q' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100)),
                'placement' => self::request_int($_GET, 'placement'),
            ];
            $promos = $showForm ? [] : $repository->find_promos($filters['status'], $filters['q'], $filters['placement']);
            $placements = $repository->get_placements();
            $settings = $repository->get_settings();
            $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);
            $statusLabels = CMS_Promos_Repository::STATUS_LABELS;

            include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-promos.php';
            self::enqueue_script('promos-admin.js');
        });
    }

    public static function render_placements(): void
    {
        self::render_page('promos-placements', static function (CMS_Promos_Repository $repository): void {
            $editId = self::request_int($_GET, 'edit');
            $placement = $editId > 0 ? $repository->get_placement($editId) : null;
            $showForm = $placement !== null || (string) ($_GET['action'] ?? '') === 'new';
            $placements = $repository->get_placements();
            $hookOptions = $repository->get_theme_hook_options();
            $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

            include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-placements.php';
        });
    }

    public static function render_settings(): void
    {
        self::render_page('promos-settings', static function (CMS_Promos_Repository $repository): void {
            $settings = $repository->get_settings();
            $csrfToken = Security::instance()->generateToken(self::CSRF_ACTION);

            include CMS_PROMOS_PLUGIN_DIR . 'admin/views/page-settings.php';
        });
    }

    /**
     * Gemeinsamer Rahmen: Rechte, POST/Export, Layout-Vertrag (Kopfzeile + Menü), Flash-Meldung, Inhalt.
     *
     * @param callable(CMS_Promos_Repository):void $renderer
     */
    private static function render_page(string $slug, callable $renderer): void
    {
        self::check_access();
        self::handle_export();
        self::handle_post();
        self::ensure_workspace_helpers();

        $repository = CMS_Promos_Repository::instance();
        $stats = $repository->get_dashboard_stats();
        $live = $repository->count_live_promos();
        $impressions = (int) $stats['impressions'];
        $ctr = $impressions > 0 ? round((int) $stats['clicks'] / $impressions * 100, 1) : 0.0;

        self::enqueue_style('promos-admin.css');

        echo cms_plugin_workspace_open([
            'plugin' => self::ROOT_SLUG,
            'title' => '365CMS | Promos',
            'icon' => 'ti ti-speakerphone',
            'version' => CMS_PROMOS_VERSION,
            'status' => $live > 0 ? ['label' => $live . ' live', 'tone' => 'success'] : ['label' => 'Keine Promo live', 'tone' => 'muted'],
            'description' => 'Banner, Teaser und CTA-Kacheln mit Laufzeit, Platzierung und Klick-Tracking.',
            'stats' => [
                ['label' => 'Promos gesamt', 'value' => (int) $stats['promos'], 'icon' => 'ti ti-speakerphone', 'url' => self::page_url('promos-items')],
                ['label' => 'Aktuell live', 'value' => $live, 'icon' => 'ti ti-broadcast', 'tone' => $live > 0 ? 'success' : 'muted', 'url' => self::page_url('promos-items', ['status' => 'live'])],
                ['label' => 'Impressions', 'value' => $impressions, 'icon' => 'ti ti-eye'],
                ['label' => 'Klickrate', 'value' => number_format($ctr, 1, ',', '.') . ' %', 'icon' => 'ti ti-click', 'hint' => number_format((int) $stats['clicks'], 0, ',', '.') . ' Klicks'],
            ],
            'nav' => self::nav_items($slug, (int) $stats['promos'], (int) $stats['placements'], $repository->count_expired_active()),
            'nav_explicit' => true,
        ]);

        self::render_notice();
        $renderer($repository);

        echo cms_plugin_workspace_close();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function nav_items(string $active, int $promoCount, int $placementCount, int $expired): array
    {
        $badges = [
            'promos-items' => $expired > 0 ? ['badge' => $expired, 'badge_tone' => 'warning'] : ['badge' => $promoCount],
            'promos-placements' => ['badge' => $placementCount],
        ];
        $items = [];
        foreach (self::PAGES as $slug => $page) {
            $items[] = [
                'slug' => $slug,
                'label' => $page['label'],
                'icon' => $page['icon'],
                'url' => self::page_url($slug),
                'active' => $slug === $active,
            ] + ($badges[$slug] ?? []);
        }

        return $items;
    }

    private static function handle_post(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        if (!Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), self::CSRF_ACTION)) {
            self::store_notice(false, 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
            self::redirect(self::current_page());
        }

        $repository = CMS_Promos_Repository::instance();
        $action = self::request_key($_POST, 'action');
        $promoId = self::request_int($_POST, 'promo_id');
        $placementId = self::request_int($_POST, 'placement_id');

        $result = match ($action) {
            'save_promo' => $repository->save_promo($_POST),
            'delete_promo' => $repository->delete_promo($promoId),
            'duplicate_promo' => $repository->duplicate_promo($promoId),
            'reset_promo_stats' => $repository->reset_stats($promoId),
            'reset_all_stats' => $repository->reset_stats(0),
            'save_placement' => $repository->save_placement($_POST),
            'delete_placement' => $repository->delete_placement($placementId),
            'save_settings' => $repository->save_settings($_POST),
            'reset_settings' => $repository->reset_settings(),
            default => ['success' => false, 'error' => 'Unbekannte Aktion.'],
        };

        $success = (bool) ($result['success'] ?? false);
        self::store_notice($success, (string) ($result['message'] ?? $result['error'] ?? ''));

        // Nach Fehlern im Formular bleiben, nach Erfolg zur Liste (bzw. zur duplizierten Promo).
        $query = [];
        if ($action === 'duplicate_promo' && $success && !empty($result['id'])) {
            $query['edit'] = (int) $result['id'];
        } elseif (!$success && $action === 'save_promo') {
            $query = $promoId > 0 ? ['edit' => $promoId] : ['action' => 'new'];
        } elseif (!$success && $action === 'save_placement') {
            $query = $placementId > 0 ? ['edit' => $placementId] : ['action' => 'new'];
        }

        self::redirect(self::current_page(), $query);
    }

    /**
     * CSV-/JSON-Export der Leistungsdaten (GET mit persistentem Token).
     */
    private static function handle_export(): void
    {
        $format = self::request_key($_GET, 'export');
        if ($format === '') {
            return;
        }

        if (!in_array($format, ['csv', 'json'], true)
            || !Security::instance()->verifyPersistentToken((string) ($_GET['token'] ?? ''), self::EXPORT_ACTION)) {
            self::store_notice(false, 'Export abgelehnt: ungültiges oder abgelaufenes Token.');
            self::redirect(self::current_page());
        }

        $rows = CMS_Promos_Repository::instance()->get_export_rows();
        $filename = 'promos-' . date('Y-m-d') . '.' . $format;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['exported_at' => date('c'), 'promos' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Titel', 'Slug', 'Status', 'Platzierung', 'Ziel-URL', 'Start', 'Ende', 'Priorität', 'Featured', 'Impressions', 'Klicks', 'Klickrate %', 'Geändert'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn (mixed $value): string => self::csv_safe((string) $value), array_values($row)), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Formel-Injektion in Tabellenkalkulationen verhindern. */
    private static function csv_safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value) ? "'" . $value : $value;
    }

    public static function export_url(string $format): string
    {
        return self::page_url(self::ROOT_SLUG, [
            'export' => $format,
            'token' => Security::instance()->generateToken(self::EXPORT_ACTION),
        ]);
    }

    /**
     * @param array<string,scalar> $query
     */
    public static function page_url(string $slug, array $query = []): string
    {
        $slug = array_key_exists($slug, self::PAGES) ? $slug : self::ROOT_SLUG;
        $url = '/admin/plugins/' . self::ROOT_SLUG . '/' . rawurlencode($slug);

        return $query !== [] ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    public static function status_tone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'paused' => 'warning',
            'archived' => 'muted',
            default => 'info',
        };
    }

    public static function format_date(string $value): string
    {
        $timestamp = $value !== '' ? strtotime($value) : false;

        return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : '–';
    }

    private static function current_page(): string
    {
        $page = strtolower(trim((string) ($_GET['page'] ?? self::ROOT_SLUG)));

        return array_key_exists($page, self::PAGES) ? $page : self::ROOT_SLUG;
    }

    private static function ensure_workspace_helpers(): void
    {
        if (!function_exists('cms_plugin_workspace_open') && is_file(ABSPATH . 'admin/partials/plugin-workspace.php')) {
            require_once ABSPATH . 'admin/partials/plugin-workspace.php';
        }
    }

    private static function check_access(): void
    {
        if (!function_exists('current_user_can') || !current_user_can(self::CAPABILITY)) {
            http_response_code(403);
            header('Location: ' . Security::safeLocalPath('/admin', '/'), true, 303);
            exit;
        }
    }

    private static function enqueue_style(string $file): void
    {
        $path = CMS_PROMOS_PLUGIN_DIR . 'assets/css/' . $file;
        if (is_file($path)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/css/' . $file . '?v=' . (string) filemtime($path), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }

    private static function enqueue_script(string $file): void
    {
        $path = CMS_PROMOS_PLUGIN_DIR . 'assets/js/' . $file;
        if (is_file($path)) {
            echo '<script src="' . htmlspecialchars(CMS_PROMOS_PLUGIN_URL . 'assets/js/' . $file . '?v=' . (string) filemtime($path), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }

    private static function store_notice(bool $success, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::NOTICE_KEY] = ['type' => $success ? 'success' : 'danger', 'message' => $message];
        }
    }

    private static function render_notice(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::NOTICE_KEY]) || !is_array($_SESSION[self::NOTICE_KEY])) {
            return;
        }

        $notice = $_SESSION[self::NOTICE_KEY];
        unset($_SESSION[self::NOTICE_KEY]);
        $type = ($notice['type'] ?? '') === 'success' ? 'success' : 'danger';
        echo '<div class="alert alert-' . $type . '" role="' . ($type === 'success' ? 'status' : 'alert') . '">'
            . htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    /**
     * @param array<string,scalar> $query
     */
    private static function redirect(string $slug, array $query = []): never
    {
        header('Location: ' . Security::safeLocalPath(self::page_url($slug, $query), self::page_url(self::ROOT_SLUG)), true, 303);
        exit;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function request_int(array $source, string $key): int
    {
        return isset($source[$key]) && is_scalar($source[$key]) ? max(0, (int) $source[$key]) : 0;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function request_key(array $source, string $key): string
    {
        $value = isset($source[$key]) && is_string($source[$key]) ? strtolower(trim($source[$key])) : '';

        return (string) preg_replace('/[^a-z0-9_]+/', '', $value);
    }
}

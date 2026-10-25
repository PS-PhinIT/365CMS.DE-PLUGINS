<?php

declare(strict_types=1);

namespace CmsKnowledgebase\Admin;

use CMS\Auth;
use CMS\Security;
use CmsKnowledgebase\Repository\EntryRepository;
use CmsKnowledgebase\Service\StandardPackages;
use CmsKnowledgebase\Support\LoggerFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class Pages
{
    private const DEFAULT_PAGE = 'knowledgebase-dashboard';

    private static ?array $requestNotice = null;

    public static function renderDashboard(): void
    {
        self::renderWithLayout('Knowledgebase', static function (): void {
            $repository = EntryRepository::instance();
            $stats = $repository->getDashboardStats();
            $entries = array_slice($repository->getEntryList(), 0, 8);
            $settings = $repository->getSettings();
            $csrfToken = Security::instance()->generateToken('knowledgebase_admin');
            $notice = self::pullNotice();

            include CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'admin/views/page-dashboard.php';
        });
    }

    public static function renderEntries(): void
    {
        self::renderWithLayout('Knowledgebase-Einträge', static function (): void {
            $repository = EntryRepository::instance();
            $entries = $repository->getEntryList();
            $csrfToken = Security::instance()->generateToken('knowledgebase_admin');
            $notice = self::pullNotice();

            include CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'admin/views/page-entries.php';
        });
    }

    public static function renderEntryEditor(): void
    {
        self::renderWithLayout('Knowledgebase-Eintrag bearbeiten', static function (): void {
            $repository = EntryRepository::instance();
            $entry = isset($_GET['edit']) ? $repository->getEntry((int) $_GET['edit']) : null;
            $categories = $repository->getCategories();
            $csrfToken = Security::instance()->generateToken('knowledgebase_admin');
            $notice = self::pullNotice();

            include CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'admin/views/page-entry-editor.php';
        });
    }

    public static function renderCategories(): void
    {
        self::renderWithLayout('Knowledgebase-Kategorien', static function (): void {
            $repository = EntryRepository::instance();
            $categories = $repository->getCategories();
            $categoryItem = isset($_GET['edit']) ? $repository->getCategory((int) $_GET['edit']) : null;
            $csrfToken = Security::instance()->generateToken('knowledgebase_admin');
            $notice = self::pullNotice();

            include CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'admin/views/page-categories.php';
        });
    }

    public static function renderSettings(): void
    {
        self::renderSettingsSection('general');
    }

    public static function renderSettingsGeneral(): void
    {
        self::renderSettingsSection('general');
    }

    public static function renderSettingsDesign(): void
    {
        self::renderSettingsSection('design');
    }

    public static function renderSettingsImport(): void
    {
        self::renderSettingsSection('import');
    }

    public static function renderSettingsSystem(): void
    {
        self::renderSettingsSection('system');
    }

    private static function renderSettingsSection(string $settingsSection): void
    {
        $settingsSection = self::sanitizeSettingsSection($settingsSection);

        self::renderWithLayout('Knowledgebase-Einstellungen', static function () use ($settingsSection): void {
            $repository = EntryRepository::instance();
            $settings = $repository->getSettings();
            $stats = $repository->getDashboardStats();
            $standardPackagesService = StandardPackages::instance();
            $standardPackages = $standardPackagesService->getPackages();
            $csvFilenameWarnings = $standardPackagesService->getCsvFilenameWarnings();
            $csvTemplateColumns = $standardPackagesService->templateColumns();
            $csvTemplateFileName = StandardPackages::TEMPLATE_FILE_NAME;
            $activeSettingsPage = self::sanitizeAdminPageSlug((string) ($_GET['page'] ?? self::settingsPageSlugForSection($settingsSection)));
            $designTokens = $repository->getPublicDesignTokens();
            $lastImportAtRaw = trim((string) ($settings['csv_last_import_at'] ?? ''));
            $lastImportAt = 'Noch kein CSV-Import';
            if ($lastImportAtRaw !== '') {
                try {
                    $lastImportAt = (new \DateTimeImmutable($lastImportAtRaw))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('d.m.Y H:i');
                } catch (\Throwable) {
                    $lastImportAt = $lastImportAtRaw;
                }
            }

            $systemInfo = [
                'plugin_version' => CMS_KNOWLEDGEBASE_VERSION,
                'public_route' => '/kb',
                'glossary_sitemap_url' => SITE_URL . '/glossar-sitemap.xml',
                'content_max_width' => $settings['content_max_width'] ?? '1200',
                'sidebar_width' => $settings['sidebar_width'] ?? '300',
                'autolink_enabled' => ($settings['enable_autolink'] ?? '0') === '1' ? 'Ja' : 'Nein',
                'output_buffer' => ($settings['enable_output_buffer'] ?? '0') === '1' ? 'Ja' : 'Nein',
                'csv_last_import_at' => $lastImportAt,
                'csv_last_import_count' => (int) ($settings['csv_last_import_count'] ?? 0),
                'csv_last_import_scope' => (string) ($settings['csv_last_import_scope'] ?? '—'),
                'current_entry_count' => (int) ($stats['entries'] ?? 0),
            ];
            $csrfToken = Security::instance()->generateToken('knowledgebase_admin');
            $notice = self::pullNotice();

            include CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'admin/views/page-settings.php';
        });
    }

    public static function renderSectionEditor(string $name, string $value, string $ariaLabel, int $height = 220): string
    {
        $safeName = preg_replace('/[^a-z0-9_]/i', '', $name) ?: 'content';
        $content = trim($value);

        try {
            if (class_exists('CMS\\Services\\EditorJs\\EditorJsAssetService')) {
                if (class_exists('CMS\\Services\\EditorJs\\EditorJsContentNormalizer')) {
                    $content = (string) json_encode(
                        \CMS\Services\EditorJs\EditorJsContentNormalizer::normalize($content),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }

                static $service = null;
                $service ??= new \CMS\Services\EditorJs\EditorJsAssetService();

                return $service->render($safeName, $content, [
                    'height' => $height,
                    'context' => 'cms-knowledgebase',
                    'aria_label' => $ariaLabel,
                    'content_width' => 920,
                    'content_padding_x' => 28,
                ]);
            }
        } catch (\Throwable $exception) {
            self::logDispatchProblem('EditorJS-Bereich konnte nicht gerendert werden', [
                'field' => $safeName,
                'exception' => $exception->getMessage(),
            ]);
        }

        return '<textarea id="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" name="'
            . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" rows="8" aria-label="'
            . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') . '</textarea>';
    }

    private static function renderWithLayout(string $title, callable $renderer): void
    {
        self::checkAccess();
        self::handleDownload();
        self::handlePost();
        self::loadAdminMenu();

        // Aktive Unterseite für Sidebar-Markierung und Breadcrumb.
        $activeSlug = strtolower(trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($_GET['page'] ?? '')), '-'));
        $activeSlug = $activeSlug !== '' ? $activeSlug : Menu::ROOT_SLUG;

        if (function_exists('cms_plugin_admin_layout_start')) {
            cms_plugin_admin_layout_start($title, $activeSlug);
        } elseif (function_exists('renderAdminLayoutStart')) {
            renderAdminLayoutStart($title, $activeSlug);
        }

        self::enqueueAdminAssets();
        $renderer();

        if (function_exists('cms_plugin_admin_layout_end')) {
            cms_plugin_admin_layout_end();
        } elseif (function_exists('renderAdminLayoutEnd')) {
            renderAdminLayoutEnd();
        }
    }

    private static function handlePost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        if (!Auth::instance()->isAdmin()) {
            self::storeNotice(false, 'Keine Berechtigung für diese Aktion.');
            self::redirectBack();
        }

        if (!Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), 'knowledgebase_admin')) {
            self::storeNotice(false, 'Sicherheitscheck fehlgeschlagen.');
            self::redirectBack();
        }

        $repository = EntryRepository::instance();
        $action = (string) ($_POST['action'] ?? '');
        $redirectPage = self::sanitizeAdminPageSlug((string) ($_POST['redirect_page'] ?? $_GET['page'] ?? self::DEFAULT_PAGE));
        $redirectSectionRaw = trim((string) ($_POST['redirect_section'] ?? $_POST['redirect_tab'] ?? ''));
        $redirectSection = $redirectSectionRaw !== '' ? self::sanitizeSettingsSection($redirectSectionRaw) : '';
        if ($redirectSection !== '' && str_starts_with($redirectPage, 'knowledgebase-settings')) {
            $redirectPage = self::settingsPageSlugForSection($redirectSection);
        }

        try {
            $result = match ($action) {
                'save_entry' => $repository->saveEntry($_POST, false),
                'delete_entry' => $repository->deleteEntry((int) ($_POST['entry_id'] ?? 0)),
                'save_category' => $repository->saveCategory($_POST),
                'delete_category' => $repository->deleteCategory((int) ($_POST['category_id'] ?? 0)),
                'hard_reset_entries' => $repository->hardResetEntries(),
                'hard_reset_and_import_all' => self::hardResetAndImportAll(),
                'save_settings' => $repository->saveSettings($_POST),
                'create_standard_package' => StandardPackages::instance()->importPackage((string) ($_POST['package_key'] ?? '')),
                'create_all_standard_packages' => StandardPackages::instance()->importAllPackages(),
                default => ['success' => false, 'error' => 'Unbekannte Aktion.'],
            };
        } catch (\Throwable $exception) {
            self::logDispatchProblem('Post-Aktion fehlgeschlagen', [
                'action' => $action,
                'exception' => $exception->getMessage(),
            ]);
            $result = ['success' => false, 'error' => 'Die Aktion konnte nicht verarbeitet werden.'];
        }

        self::storeNotice((bool) ($result['success'] ?? false), (string) ($result['message'] ?? $result['error'] ?? ''));
        self::redirectBack((int) ($result['id'] ?? 0), $redirectPage);
    }

    /**
     * Liefert die Beispiel-CSV aus (`?kb_download=csv-template`). Der Zugriff ist durch
     * checkAccess() auf Admins beschränkt; die Datei enthält nur Beispieldaten.
     */
    private static function handleDownload(): void
    {
        if ((string) ($_GET['kb_download'] ?? '') !== 'csv-template') {
            return;
        }

        $path = StandardPackages::instance()->templateCsvPath();
        if ($path === '' || !is_readable($path)) {
            self::storeNotice(false, 'Die Beispiel-CSV wurde im Plugin-Ordner csv_kb nicht gefunden.');
            self::redirectBack(0, 'knowledgebase-settings-import');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . StandardPackages::TEMPLATE_FILE_NAME . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    private static function enqueueAdminAssets(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $css = CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'assets/css/knowledgebase-admin.css';
        if (is_file($css)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(CMS_KNOWLEDGEBASE_PLUGIN_URL . 'assets/css/knowledgebase-admin.css?v=' . filemtime($css), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $js = CMS_KNOWLEDGEBASE_PLUGIN_DIR . 'assets/js/knowledgebase-admin.js';
        if (is_file($js)) {
            echo '<script src="' . htmlspecialchars(CMS_KNOWLEDGEBASE_PLUGIN_URL . 'assets/js/knowledgebase-admin.js?v=' . filemtime($js), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
    }

    private static function loadAdminMenu(): void
    {
        if (function_exists('add_menu_page') && function_exists('renderAdminLayoutStart') && function_exists('renderAdminLayoutEnd')) {
            return;
        }

        if (function_exists('cms_plugin_admin_require_layout_helpers')) {
            cms_plugin_admin_require_layout_helpers();
            if (function_exists('renderAdminLayoutStart') && function_exists('renderAdminLayoutEnd')) {
                return;
            }
        }

        $candidates = [
            ABSPATH . 'admin/partials/admin-menu.php',
            ABSPATH . 'includes/functions/admin-menu.php',
            ABSPATH . 'CMS/includes/functions/admin-menu.php',
        ];

        foreach ($candidates as $menuFile) {
            if (is_file($menuFile)) {
                require_once $menuFile;
            }

            if (function_exists('renderAdminLayoutStart') && function_exists('renderAdminLayoutEnd')) {
                return;
            }
        }
    }

    private static function checkAccess(): void
    {
        if (!Auth::instance()->isAdmin()) {
            header('Location: ' . SITE_URL, true, 303);
            exit;
        }
    }

    private static function storeNotice(bool $success, string $message): void
    {
        $payload = [
            'type' => $success ? 'success' : 'error',
            'message' => $message,
        ];

        if (isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION['knowledgebase_admin_notice'] = $payload;
            return;
        }

        self::$requestNotice = $payload;
    }

    private static function pullNotice(): ?array
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            if (empty($_SESSION['knowledgebase_admin_notice']) || !is_array($_SESSION['knowledgebase_admin_notice'])) {
                return null;
            }

            $notice = $_SESSION['knowledgebase_admin_notice'];
            unset($_SESSION['knowledgebase_admin_notice']);

            return self::normalizeNotice($notice);
        }

        if (self::$requestNotice !== null) {
            $notice = self::$requestNotice;
            self::$requestNotice = null;
            return self::normalizeNotice($notice);
        }

        return null;
    }

    private static function redirectBack(int $editId = 0, string $page = self::DEFAULT_PAGE): void
    {
        $page = self::sanitizeAdminPageSlug($page);
        $url = SITE_URL . '/admin/plugins/' . Menu::ROOT_SLUG . '/' . $page;
        if ($page === 'knowledgebase-entry-editor' && $editId > 0) {
            $url .= '?edit=' . $editId;
        }

        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function sanitizeAdminPageSlug(string $slug): string
    {
        $normalized = Menu::normalizePageSlug($slug);
        $allowed = Menu::registeredPageSlugs();

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        self::logDispatchProblem('Unbekannter Admin-Slug angefordert', [
            'requested' => $slug,
            'normalized' => $normalized,
        ]);

        return self::DEFAULT_PAGE;
    }

    private static function sanitizeSettingsSection(string $section): string
    {
        return in_array($section, ['general', 'design', 'import', 'system'], true) ? $section : 'general';
    }

    private static function settingsPageSlugForSection(string $section): string
    {
        return 'knowledgebase-settings-' . self::sanitizeSettingsSection($section);
    }

    /**
     * @param array<string, scalar> $context
     */
    private static function logDispatchProblem(string $message, array $context = []): void
    {
        LoggerFactory::create()->warning($message, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function hardResetAndImportAll(): array
    {
        $repository = EntryRepository::instance();
        $resetResult = $repository->hardResetEntries();

        if (!((bool) ($resetResult['success'] ?? false))) {
            return $resetResult;
        }

        $importResult = StandardPackages::instance()->importAllPackages();
        if (!((bool) ($importResult['success'] ?? false))) {
            return [
                'success' => false,
                'error' => trim(((string) ($resetResult['message'] ?? 'Hardreset abgeschlossen.')) . ' ' . ((string) ($importResult['message'] ?? $importResult['error'] ?? 'CSV-Import fehlgeschlagen.'))),
            ];
        }

        return [
            'success' => true,
            'message' => trim(((string) ($resetResult['message'] ?? '')) . ' ' . ((string) ($importResult['message'] ?? ''))),
        ];
    }

    /**
     * @param mixed $notice
     * @return array{type: string, message: string}|null
     */
    private static function normalizeNotice(mixed $notice): ?array
    {
        if (!is_array($notice)) {
            return null;
        }

        $type = ((string) ($notice['type'] ?? '')) === 'success' ? 'success' : 'error';
        $message = trim((string) ($notice['message'] ?? ''));
        if ($message === '') {
            return null;
        }

        return [
            'type' => $type,
            'message' => mb_substr($message, 0, 500, 'UTF-8'),
        ];
    }
}

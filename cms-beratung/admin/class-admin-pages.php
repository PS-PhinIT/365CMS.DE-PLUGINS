<?php
/**
 * CMS Beratung – Admin pages and actions.
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

    /** Ansichten ohne eigenen Menüeintrag; sie laufen über die Hauptroute mit ?view=. */
    private const UNLISTED_PAGE_SLUGS = ['cms-beratung-landingpages', 'cms-beratung-new', 'cms-beratung-preview'];

    private const PAGE_TITLES = [
        'cms-beratung' => 'Landingpages',
        'cms-beratung-landingpages' => 'Landingpages',
        'cms-beratung-new' => 'Neue Landingpage erstellen',
        'cms-beratung-settings' => 'Globale Einstellungen',
        'cms-beratung-presets' => 'Design Presets',
        'cms-beratung-faqs' => 'M365 FAQs',
        'cms-beratung-submissions' => 'Formular Anfragen',
        'cms-beratung-import-export' => 'Import und Export',
        'cms-beratung-preview' => 'Landingpage Vorschau',
        'cms-beratung-help' => 'Hilfe und Dokumentation',
    ];

    private const PAGE_RENDERERS = [
        'cms-beratung' => 'render_landingpages',
        'cms-beratung-landingpages' => 'render_landingpages',
        'cms-beratung-new' => 'render_new_page',
        'cms-beratung-settings' => 'render_settings',
        'cms-beratung-presets' => 'render_presets',
        'cms-beratung-faqs' => 'render_faqs',
        'cms-beratung-submissions' => 'render_submissions',
        'cms-beratung-import-export' => 'render_import_export',
        'cms-beratung-preview' => 'render_preview',
        'cms-beratung-help' => 'render_help',
    ];

    /** @return array<int,array{slug:string,title:string,menu_title:string}> */
    public static function get_menu_pages(): array
    {
        return [
            ['slug' => 'cms-beratung-settings', 'title' => 'Globale Einstellungen', 'menu_title' => 'Globale Einstellungen'],
            ['slug' => 'cms-beratung-presets', 'title' => 'Design Presets', 'menu_title' => 'Design Presets'],
            ['slug' => 'cms-beratung-faqs', 'title' => 'M365 FAQs', 'menu_title' => 'M365 FAQs'],
            ['slug' => 'cms-beratung-submissions', 'title' => 'Formular Anfragen', 'menu_title' => 'Formular Anfragen'],
            ['slug' => 'cms-beratung-import-export', 'title' => 'Import und Export', 'menu_title' => 'Import und Export'],
            ['slug' => 'cms-beratung-help', 'title' => 'Hilfe und Dokumentation', 'menu_title' => 'Hilfe und Dokumentation'],
        ];
    }

    /** @return array{0:class-string,1:string} */
    public static function dispatch_callback_for_slug(string $slug): array
    {
        $slug = self::normalize_slug($slug);
        $method = 'dispatch_' . str_replace('-', '_', $slug);
        return is_callable([self::class, $method]) ? [self::class, $method] : [self::class, 'dispatch_cms_beratung_landingpages'];
    }

    public static function __callStatic(string $name, array $arguments): void
    {
        unset($arguments);
        if (str_starts_with($name, 'dispatch_')) {
            $_GET['page'] = str_replace('_', '-', substr($name, 9));
            self::render_dispatch();
        }
    }

    public static function dispatch_overview(): void
    {
        $requested = self::normalize_slug((string) ($_GET['view'] ?? ''));
        $_GET['page'] = in_array($requested, self::UNLISTED_PAGE_SLUGS, true) ? $requested : self::DEFAULT_PAGE_SLUG;
        self::render_dispatch();
        // Breadcrumb und Sidebar-Markierung beziehen sich auf den registrierten Menüeintrag.
        $_GET['page'] = self::MENU_SLUG;
    }

    public static function dispatch_cms_beratung_landingpages(): void { $_GET['page'] = 'cms-beratung-landingpages'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_new(): void { $_GET['page'] = 'cms-beratung-new'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_settings(): void { $_GET['page'] = 'cms-beratung-settings'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_presets(): void { $_GET['page'] = 'cms-beratung-presets'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_faqs(): void { $_GET['page'] = 'cms-beratung-faqs'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_submissions(): void { $_GET['page'] = 'cms-beratung-submissions'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_import_export(): void { $_GET['page'] = 'cms-beratung-import-export'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_preview(): void { $_GET['page'] = 'cms-beratung-preview'; self::render_dispatch(); }
    public static function dispatch_cms_beratung_help(): void { $_GET['page'] = 'cms-beratung-help'; self::render_dispatch(); }

    public static function render_dispatch(): void
    {
        self::ensure_shared_contract_loaded();
        if (function_exists('cms_plugin_admin_sync_page_from_request')) {
            cms_plugin_admin_sync_page_from_request(self::MENU_SLUG);
        }

        $callbacks = self::resolve_callbacks();
        if (function_exists('cms_plugin_admin_dispatch_page')) {
            cms_plugin_admin_dispatch_page($callbacks, self::DEFAULT_PAGE_SLUG, self::MENU_SLUG);
            return;
        }

        $slug = self::normalize_slug((string) ($_GET['page'] ?? self::DEFAULT_PAGE_SLUG));
        $callback = $callbacks[$slug] ?? $callbacks[self::DEFAULT_PAGE_SLUG];
        call_user_func($callback);
    }

    public static function register_admin_routes(mixed $router): void
    {
        self::ensure_shared_contract_loaded();
        if (is_object($router) && method_exists($router, 'addRoute')) {
            $router->addRoute('POST', '/api/cms-beratung/media-upload', [self::class, 'handle_media_upload']);
        }
        if (function_exists('cms_plugin_admin_register_routes')) {
            cms_plugin_admin_register_routes($router, self::MENU_SLUG, self::resolve_callbacks());
        }
    }

    public static function handle_media_upload(): void
    {
        self::check_access();

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

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'beratung-bild');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg'];
        if (!in_array($extension, $allowedExtensions, true) || !self::uploaded_file_is_image($tmpName)) {
            self::json_response(['success' => 0, 'message' => 'Nur Bilddateien sind erlaubt.'], 400);
        }

        if ($extension === 'svg' && !self::svg_upload_is_safe($tmpName)) {
            self::json_response(['success' => 0, 'message' => 'SVG enthält nicht erlaubte aktive Inhalte.'], 400);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            self::json_response(['success' => 0, 'message' => 'Die Bilddatei ist leer oder größer als 10 MB.'], 400);
        }

        $uploadRoot = rtrim((string) UPLOAD_PATH, '/\\');
        $targetDir = $uploadRoot . DIRECTORY_SEPARATOR . 'beratung';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            self::json_response(['success' => 0, 'message' => 'Der Ordner /uploads/beratung konnte nicht erstellt werden.'], 500);
        }

        $baseName = self::sanitize_upload_basename((string) pathinfo($originalName, PATHINFO_FILENAME));
        $filename = self::unique_upload_filename($targetDir, $baseName, $extension);
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
            'file' => [
                'url' => $url,
                'path' => $relativePath,
                'name' => $filename,
                'size' => filesize($targetPath) ?: $size,
                'extension' => $extension,
            ],
            'message' => 'Bild wurde nach /uploads/beratung hochgeladen.',
        ]);
    }

    public static function enqueue_admin_assets_for_request(): void
    {
        $uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $page = strtolower((string) ($_GET['page'] ?? ''));
        if (!str_contains($uri, 'cms-beratung') && !str_contains($page, 'cms-beratung')) {
            return;
        }
        self::enqueue_admin_assets();
    }

    public static function render_landingpages(): void
    {
        self::handle_landingpage_actions();
        self::render_with_layout('cms-beratung-landingpages', function (): void {
            $pages = CMS_Beratung_Storage::instance()->all_landingpages();
            $statuses = CMS_Beratung_Settings::statuses();
            $total = count($pages);
            $published = 0;
            $drafts = 0;
            foreach ($pages as $page) {
                $status = (string) ($page['status'] ?? 'draft');
                if ($status === 'published') { $published++; }
                if ($status === 'draft') { $drafts++; }
            }
            echo '<div class="admin-page-header"><div><h2>Landingpages</h2><p>Kompakte Verwaltung aller Beratungs-Landingpages. Neue Seiten legst du direkt hier über den Anlegen-Button an.</p></div><div class="header-actions"><a class="btn btn-primary" href="' . self::esc(self::admin_url('cms-beratung-new')) . '">Landingpage anlegen</a></div></div>';
            echo '<div class="dashboard-grid"><div class="stat-card"><div class="stat-number">' . $total . '</div><div class="stat-label">Gesamt</div></div><div class="stat-card"><div class="stat-number">' . $published . '</div><div class="stat-label">Veröffentlicht</div></div><div class="stat-card"><div class="stat-number">' . $drafts . '</div><div class="stat-label">Entwürfe</div></div></div>';
            self::notice_from_query();
            if ($pages === []) {
                echo '<section class="beratung-empty beratung-empty--landingpages"><h2>Noch keine Landingpages vorhanden.</h2><p>Starte mit einer Vorlage und speichere die Seite anschließend als Entwurf.</p><a class="beratung-btn" href="' . self::esc(self::admin_url('cms-beratung-new')) . '">Erste Landingpage anlegen</a></section>';
                return;
            }
            echo '<div class="beratung-landingpage-list" role="list">';
            foreach ($pages as $page) {
                $id = (int) ($page['id'] ?? 0);
                $status = (string) ($page['status'] ?? 'draft');
                $publicUrl = $status === 'published' ? self::public_landingpage_url((string) ($page['slug'] ?? '')) : '';
                $publicLink = $publicUrl !== '' ? '<a target="_blank" rel="noopener noreferrer" href="' . self::esc($publicUrl) . '">Publicseite</a>' : '';
                $hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
                $standaloneUrl = $status === 'published' && !empty($hero['standalone_header_enabled']) && !empty($hero['standalone_header_slug']) ? self::public_landingpage_url((string) $hero['standalone_header_slug']) : '';
                $standaloneLink = $standaloneUrl !== '' ? '<a target="_blank" rel="noopener noreferrer" href="' . self::esc($standaloneUrl) . '">Standalone</a>' : '';
                $template = CMS_Beratung_Settings::templates()[(string) ($page['template'] ?? 'standard')] ?? (string) ($page['template'] ?? 'standard');
                echo '<article class="beratung-landingpage-item" role="listitem">';
                echo '<div class="beratung-landingpage-item__main"><div class="beratung-landingpage-item__title"><strong>' . self::esc((string) ($page['internal_title'] ?? 'Ohne internen Titel')) . '</strong><span class="beratung-status beratung-status--' . self::esc($status) . '">' . self::esc($statuses[$status] ?? $status) . '</span></div>';
                echo '<p>' . self::esc((string) ($page['public_title'] ?? '')) . '</p><div class="beratung-landingpage-item__meta"><span><b>Slug</b> <code>' . self::esc((string) ($page['slug'] ?? '')) . '</code></span><span><b>Template</b> ' . self::esc($template) . '</span><span><b>Geändert</b> ' . self::esc((string) ($page['updated_at'] ?? '')) . '</span><span><b>Erstellt von</b> ' . self::esc((string) ($page['created_by'] ?? 'System')) . '</span></div></div>';
                echo '<div class="beratung-actions beratung-landingpage-item__actions">'
                    . '<a class="is-primary" href="' . self::esc(self::admin_url('cms-beratung-new', ['id' => $id])) . '">Bearbeiten</a>'
                    . '<a target="_blank" rel="noopener noreferrer" href="' . self::esc(self::admin_url('cms-beratung-preview', ['id' => $id])) . '">Vorschau</a>'
                    . $publicLink
                    . $standaloneLink
                    . '<a href="' . self::esc(self::admin_url('cms-beratung-import-export', ['export' => $id])) . '">Export</a>'
                    . self::action_form($id, 'duplicate', 'Duplizieren')
                    . ($status === 'published' ? self::action_form($id, 'deactivate', 'Deaktivieren') : self::action_form($id, 'publish', 'Veröffentlichen'))
                    . self::action_form($id, 'delete', 'Löschen', true)
                    . '</div>';
                echo '</article>';
            }
            echo '</div>';
        });
    }

    public static function render_new_page(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $notice = '';
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_landingpage') {
            self::check_access();
            if (!self::verify_nonce('beratung_save_landingpage')) {
                $error = 'Sicherheitscheck fehlgeschlagen.';
            } else {
                try {
                    $savedId = $storage->save_landingpage($_POST);
                    self::redirect('cms-beratung-new', ['id' => $savedId, 'saved' => 1]);
                } catch (\Throwable $e) {
                    $error = 'Landingpage konnte nicht gespeichert werden: ' . $e->getMessage();
                }
            }
        }

        self::render_with_layout('cms-beratung-new', function () use ($storage, $id, $notice, $error): void {
            $page = $id > 0 ? $storage->get_landingpage($id) : null;
            if ($page === null) {
                $page = self::default_landingpage((string) ($_GET['proposal'] ?? 'microsoft-365-copilot'));
            }
            if (!empty($_GET['saved'])) {
                $notice = 'Landingpage gespeichert.';
            }
            self::render_notice($notice, $error);
            if ($id <= 0) {
                self::render_landingpage_proposals((string) ($_GET['proposal'] ?? 'microsoft-365-copilot'));
            }
            self::render_landingpage_form($page);
        });
    }

    public static function render_settings(): void
    {
        self::render_with_layout('cms-beratung-settings', function (): void {
            $notice = '';
            $error = '';
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_settings') {
                if (!self::verify_nonce('beratung_settings')) {
                    $error = 'Sicherheitscheck fehlgeschlagen.';
                } else {
                    CMS_Beratung_Settings::save(CMS_Beratung_Settings::sanitize_from_post($_POST));
                    $notice = 'Globale Einstellungen gespeichert.';
                }
            }
            $settings = CMS_Beratung_Settings::all();
            self::render_notice($notice, $error);
            self::render_settings_form($settings);
        });
    }

    public static function render_presets(): void
    {
        self::render_with_layout('cms-beratung-presets', function (): void {
            CMS_Beratung_Installer::ensure_for_admin_save();
            $presets = CMS_Beratung_Storage::instance()->all_presets();
            echo '<div class="beratung-card"><h1>Design Presets</h1><p>System-Presets für schnelle Beratungsseiten. Eigene Presets sind im Datenmodell vorbereitet.</p><div class="beratung-preset-grid">';
            foreach ($presets as $preset) {
                $design = json_decode((string) ($preset['design_json'] ?? '{}'), true);
                $primary = is_array($design) ? (string) ($design['primary_color'] ?? '#2563eb') : '#2563eb';
                echo '<article class="beratung-preset"><span style="background:' . self::esc($primary) . '"></span><h3>' . self::esc((string) ($preset['name'] ?? 'Preset')) . '</h3><p>' . self::esc((string) ($preset['description'] ?? '')) . '</p></article>';
            }
            echo '</div></div>';
        });
    }

    public static function render_faqs(): void
    {
        $storage = CMS_Beratung_Storage::instance();
        $notice = '';
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['beratung_admin_action'] ?? '') === 'save_m365_faqs') {
            self::check_access();
            if (!self::verify_nonce('beratung_m365_faqs')) {
                $error = 'Sicherheitscheck fehlgeschlagen.';
            } else {
                try {
                    $storage->save_m365_faq_config($_POST);
                    self::redirect('cms-beratung-faqs', ['saved' => 1]);
                } catch (\Throwable $e) {
                    $error = 'FAQs konnten nicht gespeichert werden: ' . $e->getMessage();
                }
            }
        }

        self::render_with_layout('cms-beratung-faqs', function () use ($storage, $notice, $error): void {
            if (!empty($_GET['saved'])) { $notice = 'M365 FAQs gespeichert.'; }
            $config = $storage->m365_faq_config();
            $items = is_array($config['items'] ?? null) ? $config['items'] : [];
            self::render_notice($notice, $error);
            echo '<form method="post" class="beratung-card beratung-faq-admin"><input type="hidden" name="csrf_token" value="' . self::esc(self::nonce('beratung_m365_faqs')) . '"><input type="hidden" name="beratung_admin_action" value="save_m365_faqs"><div class="beratung-template-library__head"><div><h1>M365 FAQs</h1><p>Diese zentralen FAQs werden auf den Public Landingpages ausgegeben. In einzelnen Landingpages werden FAQ-Inhalte nicht mehr bearbeitet.</p></div><span>' . count($items) . ' Fragen</span></div>';
            echo '<div class="beratung-grid-2"><section><h2>Anzeige</h2>';
            self::checkbox('FAQ Bereich auf Landingpages anzeigen', 'enabled', !empty($config['enabled']));
            self::field('Eyebrow', 'eyebrow', (string) ($config['eyebrow'] ?? 'FAQ'));
            self::field('Titel', 'title', (string) ($config['title'] ?? 'Häufige Fragen'));
            self::textarea('Intro Text', 'intro', (string) ($config['intro'] ?? ''), 3);
            self::field('Anker ID', 'anchor_id', (string) ($config['anchor_id'] ?? 'faq'));
            echo '</section><section><h2>Verhalten</h2>';
            self::checkbox('Mehrere Einträge gleichzeitig geöffnet erlauben', 'allow_multiple', !empty($config['allow_multiple']));
            self::select('Standard Öffnung', 'open_behavior', (string) ($config['open_behavior'] ?? 'first'), ['none' => 'Kein Eintrag offen', 'first' => 'Erster Eintrag offen', 'custom' => 'Individuell pro Frage']);
            self::select('Icon Stil', 'icon_style', (string) ($config['icon_style'] ?? 'plus'), ['plus' => 'Plus', 'chevron' => 'Chevron', 'question' => 'Fragezeichen']);
            self::checkbox('FAQ Schema aktivieren', 'schema_enabled', !empty($config['schema_enabled']));
            echo '</section></div><h2>Fragen und Antworten</h2><div class="beratung-faq-list">';
            $items[] = ['enabled' => true, 'question' => '', 'answer' => '', 'default_open' => false, 'sort_order' => (count($items) + 1) * 10];
            foreach (array_values($items) as $index => $item) {
                echo '<article class="beratung-faq-row"><div class="beratung-faq-row__meta"><strong>FAQ ' . ($index + 1) . '</strong><label><span>Sortierung</span><input type="number" name="faq_sort_order[' . $index . ']" value="' . (int) ($item['sort_order'] ?? (($index + 1) * 10)) . '"></label><label class="beratung-check"><input type="checkbox" name="faq_enabled[' . $index . ']" value="1"' . (!empty($item['enabled']) ? ' checked' : '') . '> <span>Aktiv</span></label><label class="beratung-check"><input type="checkbox" name="faq_default_open[' . $index . ']" value="1"' . (!empty($item['default_open']) ? ' checked' : '') . '> <span>Offen</span></label></div>';
                echo '<label class="beratung-field"><span>Frage</span><input name="faq_question[' . $index . ']" value="' . self::esc((string) ($item['question'] ?? '')) . '"></label><label class="beratung-field"><span>Antwort</span><textarea name="faq_answer[' . $index . ']" rows="4">' . self::esc((string) ($item['answer'] ?? '')) . '</textarea></label></article>';
            }
            echo '</div><p><button class="beratung-btn" type="submit">M365 FAQs speichern</button></p></form>';
        });
    }

    public static function render_submissions(): void
    {
        self::render_with_layout('cms-beratung-submissions', function (): void {
            $submissions = CMS_Beratung_Storage::instance()->submissions();
            echo '<div class="beratung-card"><h1>Formular Anfragen</h1><p>Gespeicherte Beratungsanfragen aus Landingpage-Formularen.</p></div>';
            echo '<div class="beratung-table-wrap"><table class="beratung-table"><thead><tr><th>Datum</th><th>Landingpage</th><th>Name</th><th>E-Mail</th><th>Thema</th><th>Status</th></tr></thead><tbody>';
            if ($submissions === []) {
                echo '<tr><td colspan="6"><div class="beratung-empty">Noch keine Formular Anfragen vorhanden.</div></td></tr>';
            }
            foreach ($submissions as $submission) {
                echo '<tr><td>' . self::esc((string) ($submission['created_at'] ?? '')) . '</td><td>' . self::esc((string) ($submission['landingpage_title'] ?? '—')) . '</td><td>' . self::esc((string) ($submission['sender_name'] ?? '')) . '</td><td>' . self::esc((string) ($submission['sender_email'] ?? '')) . '</td><td>' . self::esc((string) ($submission['topic'] ?? '')) . '</td><td>' . self::esc((string) ($submission['status'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        });
    }

    public static function render_import_export(): void
    {
        self::render_with_layout('cms-beratung-import-export', function (): void {
            $notice = '';
            $error = '';
            $exportJson = '';
            $exportId = max(0, (int) ($_GET['export'] ?? 0));
            if ($exportId > 0) {
                $page = CMS_Beratung_Storage::instance()->get_landingpage($exportId);
                if ($page !== null) {
                    $exportJson = CMS_Beratung_Import_Export::export_json($page);
                }
            }
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['beratung_admin_action'] ?? '') === 'import_json') {
                if (!self::verify_nonce('beratung_import_json')) {
                    $error = 'Sicherheitscheck fehlgeschlagen.';
                } else {
                    try {
                        $payload = CMS_Beratung_Import_Export::decode_import_json(self::import_json_from_request());
                        if ($payload === null) {
                            throw new \InvalidArgumentException('Die JSON-Datei konnte nicht gelesen werden.');
                        }
                        if (!empty($_POST['skip_images'])) {
                            $payload = self::strip_import_images($payload);
                        }
                        $mode = (string) ($_POST['import_mode'] ?? 'new');
                        $overwriteId = max(0, (int) ($_POST['overwrite_id'] ?? 0));
                        $payload['id'] = ($mode === 'overwrite' && $overwriteId > 0) ? $overwriteId : 0;
                        $payload['status'] = 'draft';
                        $originalSlug = (string) ($payload['slug'] ?? 'beratung');
                        $newId = CMS_Beratung_Storage::instance()->save_landingpage($payload);
                        $imported = CMS_Beratung_Storage::instance()->get_landingpage($newId);
                        $newSlug = (string) ($imported['slug'] ?? $originalSlug);
                        $notice = ($payload['id'] > 0 ? 'Landingpage überschrieben.' : 'Landingpage als neuer Entwurf importiert.') . ' ID: ' . $newId . ($newSlug !== $originalSlug ? ' Slug-Konflikt erkannt, neuer Slug: ' . $newSlug : '');
                    } catch (\Throwable $e) {
                        $error = $e->getMessage();
                    }
                }
            }
            self::render_notice($notice, $error);
            $pages = CMS_Beratung_Storage::instance()->all_landingpages();
            echo '<div class="beratung-grid-2"><form method="post" enctype="multipart/form-data" class="beratung-card"><input type="hidden" name="csrf_token" value="' . self::esc(self::nonce('beratung_import_json')) . '"><input type="hidden" name="beratung_admin_action" value="import_json"><h1>Import</h1><p>JSON-Datei hochladen oder JSON einfügen. Importierte Seiten werden standardmäßig als Entwurf gespeichert.</p><label class="beratung-field"><span>JSON Datei</span><input type="file" name="import_file" accept="application/json,.json"></label><label class="beratung-field"><span>Oder JSON einfügen</span><textarea name="import_json" rows="12" class="beratung-code"></textarea></label><label class="beratung-field"><span>Import Modus</span><select name="import_mode"><option value="new">Als neue Landingpage importieren</option><option value="overwrite">Bestehende Landingpage überschreiben</option></select></label><label class="beratung-field"><span>Bestehende Landingpage für Überschreiben</span><select name="overwrite_id"><option value="0">Bitte wählen</option>';
            foreach ($pages as $existing) { echo '<option value="' . (int) ($existing['id'] ?? 0) . '">' . self::esc((string) ($existing['public_title'] ?? $existing['internal_title'] ?? 'Landingpage')) . '</option>'; }
            echo '</select></label><label class="beratung-check"><input type="checkbox" name="skip_images" value="1"> <span>Bilder beim Import überspringen, wenn Pfade nicht sicher übernommen werden sollen</span></label><button class="beratung-btn" type="submit">JSON importieren</button></form>';
            echo '<div class="beratung-card"><h1>Export</h1><p>Wählen Sie in der Übersicht „Exportieren“. Der JSON-Export erscheint hier.</p><textarea readonly rows="18" class="beratung-code">' . self::esc($exportJson) . '</textarea></div></div>';
        });
    }

    public static function render_preview(): void
    {
        self::check_access();
        $page = CMS_Beratung_Storage::instance()->get_landingpage(max(0, (int) ($_GET['id'] ?? 0)));
        if ($page === null) {
            http_response_code(404);
            echo 'Landingpage Vorschau nicht gefunden.';
            return;
        }
        $css = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend.css';
        $extraCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-extra.css';
        $themeSafeCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-theme-safe.css';
        $premiumCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-premium.css';
        $globalDesignCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/frontend-global-design.css';
        $js = CMS_BERATUNG_PLUGIN_DIR . 'assets/js/frontend.js';
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vorschau: ' . self::esc((string) ($page['public_title'] ?? 'CMS Beratung')) . '</title>';
        if (is_file($css)) { echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend.css') . '?v=' . filemtime($css) . '">'; }
        if (is_file($extraCss)) { echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-extra.css') . '?v=' . filemtime($extraCss) . '">'; }
        if (is_file($themeSafeCss)) { echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-theme-safe.css') . '?v=' . filemtime($themeSafeCss) . '">'; }
        if (is_file($premiumCss)) { echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-premium.css') . '?v=' . filemtime($premiumCss) . '">'; }
        if (is_file($globalDesignCss)) { echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/frontend-global-design.css') . '?v=' . filemtime($globalDesignCss) . '">'; }
        echo '</head><body><div class="cms-beratung-previewbar">Entwurfs-Vorschau · nur für berechtigte Benutzer</div>';
        CMS_Beratung_Renderer::render($page, ['success' => false, 'message' => '', 'errors' => [], 'values' => []]);
        if (is_file($js)) { echo '<script src="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/js/frontend.js') . '?v=' . filemtime($js) . '" defer></script>'; }
        echo '</body></html>';
    }

    public static function render_help(): void
    {
        self::render_with_layout('cms-beratung-help', function (): void {
            echo '<div class="beratung-card"><h1>Hilfe und Dokumentation</h1><ul class="beratung-doc-list"><li><strong>Routing:</strong> Veröffentlichte Seiten sind unter <code>/beratung/{slug}</code> erreichbar.</li><li><strong>Bereiche:</strong> Jeder Bereich kann aktiviert, dupliziert, gelöscht und verschoben werden. Cards unterstützen 1 bis 4 Desktop-Spalten.</li><li><strong>Responsivität:</strong> Desktop bis 4 Spalten, Tablet 2 Spalten, Mobil 1 Spalte.</li><li><strong>SEO:</strong> Meta, Open Graph, Twitter Card, Canonical, Noindex/Nofollow und Schema sind vorbereitet.</li><li><strong>Sicherheit:</strong> Admin-Aktionen verwenden Rechteprüfung und CSRF-Token; Frontend-Formulare nutzen Honeypot und Token.</li><li><strong>Mandantenfähigkeit:</strong> Tabellen enthalten <code>tenant_id</code> und sind für spätere Rollen-/Tenant-Filter vorbereitet.</li></ul></div>';
        });
    }

    private static function render_landingpage_form(array $page): void
    {
        $statuses = CMS_Beratung_Settings::statuses();
        $templates = CMS_Beratung_Settings::templates();
        CMS_Beratung_Installer::ensure_for_admin_save();
        $presets = CMS_Beratung_Storage::instance()->all_presets();
        $heroJson = json_encode($page['hero'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $heroSettings = is_array($page['hero'] ?? null) ? $page['hero'] : [];
        $contactJson = json_encode($page['contact'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $editableSections = array_values(array_filter(is_array($page['sections'] ?? null) ? $page['sections'] : [], static fn($section): bool => is_array($section) && ($section['type'] ?? '') !== 'faq'));
        $sectionsJson = json_encode($editableSections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        $designJson = json_encode($page['design'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        self::render_media_config();
        self::render_expert_options_config();
        echo '<form method="post" class="beratung-editor"><input type="hidden" name="csrf_token" value="' . self::esc(self::nonce('beratung_save_landingpage')) . '"><input type="hidden" name="beratung_admin_action" value="save_landingpage"><input type="hidden" name="id" value="' . (int) ($page['id'] ?? 0) . '">';
        echo '<section class="beratung-card beratung-general-settings" data-always-open="1"><h1>Allgemeine Einstellungen</h1>';
        self::field('Interner Titel', 'internal_title', (string) ($page['internal_title'] ?? ''));
        self::field('Öffentlicher Titel / Website-Titel', 'public_title', (string) ($page['public_title'] ?? ''), 'text', 'Dieser Titel wird für die Public-Landingpage verwendet: Website-Titel, Breadcrumb, Header-Fallback und öffentliche Listenansicht.');
        self::field('URL Slug', 'slug', (string) ($page['slug'] ?? ''));
        self::field('Meta Title', 'meta_title', (string) ($page['meta_title'] ?? ''), 'text', 'Optionaler SEO-Titel. Leer lassen, wenn der öffentliche Website-Titel auch als Meta-/Browser-Titel ausgegeben werden soll.');
        self::textarea('Meta Description', 'meta_description', (string) ($page['meta_description'] ?? ''), 3);
        self::field('Fokus Keyword', 'focus_keyword', (string) ($page['focus_keyword'] ?? ''));
        self::select('Template Auswahl', 'template', (string) ($page['template'] ?? 'standard'), $templates);
        self::field('Maximale Inhaltsbreite', 'max_content_width', (string) ($page['max_content_width'] ?? '1160'), 'number');
        self::field('Canonical URL', 'canonical_url', (string) ($page['canonical_url'] ?? ''));
        self::field('Eigene CSS Klasse', 'custom_css_class', (string) ($page['custom_css_class'] ?? ''));
        if ((int) ($page['id'] ?? 0) > 0) {
            $publicUrl = ((string) ($page['status'] ?? 'draft')) === 'published' ? self::public_landingpage_url((string) ($page['slug'] ?? '')) : '';
            $standaloneUrl = ((string) ($page['status'] ?? 'draft')) === 'published' && !empty($heroSettings['standalone_header_enabled']) && !empty($heroSettings['standalone_header_slug']) ? self::public_landingpage_url((string) $heroSettings['standalone_header_slug']) : '';
            echo '<p class="beratung-general-settings__links"><a class="beratung-link" target="_blank" rel="noopener noreferrer" href="' . self::esc(self::admin_url('cms-beratung-preview', ['id' => (int) $page['id']])) . '">Entwurfs-Vorschau öffnen</a>';
            if ($publicUrl !== '') {
                echo '<a class="beratung-btn" target="_blank" rel="noopener noreferrer" href="' . self::esc($publicUrl) . '">Publicseite mit Theme öffnen</a>';
            }
            if ($standaloneUrl !== '') {
                echo '<a class="beratung-link" target="_blank" rel="noopener noreferrer" href="' . self::esc($standaloneUrl) . '">Standalone Publicsite öffnen</a>';
            }
            echo '</p>';
        }
        echo '</section><section class="beratung-card beratung-publicsite-card"><h1>Publicsite Anzeige</h1><p>Hier steuerst du pro Landingpage die normale Publicsite mit Theme-Rahmen und optional eine zusätzliche Standalone-Publicsite ohne Theme Header und Footer.</p><div class="beratung-grid-2"><div><h2>Aktivierung</h2>';
        self::select('Publicsite Status', 'status', (string) ($page['status'] ?? 'draft'), $statuses);
        echo '<p class="description">Nur veröffentlichte Publicsites sind unter <code>/beratung/{slug}</code> erreichbar. Entwürfe bleiben im Admin und in der Vorschau.</p></div><div><h2>Standard Publicsite</h2><input type="hidden" name="show_header" value="1"><input type="hidden" name="show_footer" value="1">';
        $standaloneSlug = (string) ($heroSettings['standalone_header_slug'] ?? '');
        echo '<p class="description">Der normale Publicsite-Slug <code>/beratung/' . self::esc((string) ($page['slug'] ?? 'slug')) . '</code> rendert immer mit Theme Header und Theme Footer. Die zusätzliche Standalone-Variante nutzt einen eigenen Slug, z. B. <code>/beratung/' . self::esc($standaloneSlug !== '' ? $standaloneSlug : 'eigener-standalone-slug') . '</code>.</p></div></div><div class="beratung-publicsite-standalone"><h2>Zusätzliche Standalone-Publicsite</h2><p>Diese zweite Publicsite nutzt keinen Theme Header und keinen Theme Footer, lädt aber weiterhin die Standard-Assets des aktiven Themes und alle Plugin-Styles. Der eigene Slug ist Pflicht und muss sich vom normalen Publicsite-Slug unterscheiden.</p>';
        self::checkbox('Zusätzliche Standalone-Publicsite aktivieren', 'standalone_header_enabled', !empty($heroSettings['standalone_header_enabled']));
        self::field('Eigener Standalone Slug', 'standalone_header_slug', $standaloneSlug, 'text', 'Pflicht bei aktiver Standalone-Publicsite. Beispiel: microsoft-365-beratung-clean. Erreichbar unter /beratung/{standalone-slug}.');
        echo '<div class="beratung-grid-2"><label class="beratung-field"><span>Blog Logo</span><input data-media-field name="standalone_header_blog_logo_url" value="' . self::esc((string) ($heroSettings['standalone_header_blog_logo_url'] ?? '')) . '" placeholder="/uploads/beratung/... oder https://..."></label><label class="beratung-field"><span>Partner Logo</span><input data-media-field name="standalone_header_partner_logo_url" value="' . self::esc((string) ($heroSettings['standalone_header_partner_logo_url'] ?? '')) . '" placeholder="/uploads/beratung/... oder https://..."></label></div>';
        self::field('Header Titel', 'standalone_header_title', (string) ($heroSettings['standalone_header_title'] ?? ''), 'text', 'Wird nur angezeigt, wenn ein eigener Titel hinterlegt ist.');
        self::textarea('Header Untertitel', 'standalone_header_subtitle', (string) ($heroSettings['standalone_header_subtitle'] ?? ''), 2);
        self::checkbox('Menüband im eigenen Header anzeigen', 'standalone_header_menu_enabled', array_key_exists('standalone_header_menu_enabled', $heroSettings) ? !empty($heroSettings['standalone_header_menu_enabled']) : true);
        $standaloneMenuItems = is_array($heroSettings['standalone_header_menu_items'] ?? null) ? array_values($heroSettings['standalone_header_menu_items']) : [];
        echo '<div class="beratung-publicsite-menu-items"><h3>Menüband Links</h3>';
        for ($i = 1; $i <= 4; $i++) {
            $item = is_array($standaloneMenuItems[$i - 1] ?? null) ? $standaloneMenuItems[$i - 1] : [];
            echo '<div class="beratung-publicsite-menu-row"><label class="beratung-field"><span>Menü Label ' . $i . '</span><input name="standalone_header_menu_label_' . $i . '" value="' . self::esc((string) ($item['label'] ?? '')) . '" placeholder="z. B. Leistungen"></label><label class="beratung-field"><span>Menü Ziel ' . $i . '</span><input name="standalone_header_menu_target_' . $i . '" value="' . self::esc((string) ($item['target'] ?? '')) . '" placeholder="#leistungen oder https://..."></label></div>';
        }
        echo '</div></div><div class="beratung-grid-2"><div><h2>Navigation</h2>';
        self::checkbox('Breadcrumb anzeigen', 'show_breadcrumb', !empty($page['show_breadcrumb']));
        self::checkbox('Inhaltsverzeichnis anzeigen', 'show_toc', !empty($page['show_toc']));
        echo '</div><div><h2>Suchmaschinen</h2>';
        self::checkbox('Noindex aktivieren', 'noindex', !empty($page['noindex']));
        self::checkbox('Nofollow aktivieren', 'nofollow', !empty($page['nofollow']));
        echo '</div></div></section><section class="beratung-card"><h1>Design und System Optionen</h1>';
        foreach (['custom_design_enabled' => 'Individuelles Design aktivieren', 'use_global_settings' => 'Globale Plugin Einstellungen verwenden', 'use_global_design' => 'Globales Design Modul verwenden'] as $name => $label) {
            self::checkbox($label, $name, !empty($page[$name]));
        }
        echo '</section>';
        echo '<section class="beratung-card"><h1>Hero / Content Header</h1><p>Bild links oder rechts, nahtloser Bildrand, Badge, Titel, Text, bis zu 3 Buttons und Trust-Hinweis.</p><div id="beratung-hero-builder" data-target="hero_json"></div><textarea id="hero_json" name="hero_json" rows="12" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . self::esc($heroJson) . '</textarea></section>';
        echo '<section class="beratung-card"><h1>Anker Navigation / Navigation</h1><p>Die Navigation zwischen Content Header und den Inhaltsbereichen ist hier direkt einstellbar.</p>';
        self::checkbox('Anker Navigation unter dem Content Header anzeigen', 'show_anchor_nav', !empty($page['show_anchor_nav']));
        echo '<div id="beratung-anchor-nav-builder" data-target="hero_json"></div></section>';
        echo '<section class="beratung-card"><h1>Kontaktbereich</h1><p>Der Anfragebereich am Ende der Landingpage kann hier direkt gepflegt werden: Texte, Bild, Farben, Buttons, Datenschutz und Captcha.</p>';
        self::checkbox('Kontaktbereich anzeigen', 'contact_enabled', ($page['contact']['enabled'] ?? true) !== false);
        echo '<div id="beratung-contact-builder" data-target="contact_json"></div><textarea id="contact_json" name="contact_json" rows="10" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . self::esc($contactJson) . '</textarea></section>';
        echo '<section class="beratung-card beratung-root-builder-intro"><h1>Landingpage Bereiche nach Content Header</h1><p>Jeder Bereich nach dem Content Header erscheint darunter als eigener, auf-/zuklappbarer Root-Abschnitt. Es gibt keinen großen Sammelbereich mehr: Partnerband, Belegbare Grundlagen, Terminbuchung, Zusammenarbeit, Cards, CTA, Trenner und weitere Inhaltsbereiche stehen jeweils eigenständig auf Root-Ebene.</p></section><div id="beratung-builder" data-target="sections_json"></div><textarea id="sections_json" name="sections_json" rows="16" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . self::esc($sectionsJson) . '</textarea>';
        echo '<section class="beratung-card"><h1>Design Preset und individuelles Design</h1><p>Preset wählen und Farben direkt über die Felder anpassen.</p><label class="beratung-field"><span>Design Preset anwenden</span><select id="beratung-design-preset"><option value="">Bitte wählen</option>';
        foreach ($presets as $preset) { echo '<option value="' . self::esc((string) ($preset['slug'] ?? '')) . '" data-design="' . self::esc((string) ($preset['design_json'] ?? '{}')) . '">' . self::esc((string) ($preset['name'] ?? 'Preset')) . '</option>'; }
        echo '</select></label><div id="beratung-design-builder" data-target="design_json"></div><textarea id="design_json" name="design_json" rows="8" class="beratung-code is-technical-json" aria-hidden="true" tabindex="-1">' . self::esc($designJson) . '</textarea></section>';
        echo '<p><button type="submit" class="beratung-btn">Landingpage speichern</button> <a class="beratung-link" href="' . self::esc(self::admin_url('cms-beratung-landingpages')) . '">Zur Übersicht</a></p></form>';
    }

    /** @param array<string,string> $settings */
    private static function render_settings_form(array $settings): void
    {
        $groups = [
            'Globale Design Einstellungen' => ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'heading_color', 'button_color', 'button_text_color', 'card_background_color', 'card_border_color', 'card_shadow_enabled', 'border_radius', 'spacing', 'content_width', 'font_size_base', 'font_size_hero', 'font_size_section_title', 'font_size_card_title', 'header_spacing', 'footer_spacing', 'card_spacing', 'section_content_spacing', 'use_default_font', 'allow_custom_page_css_class'],
            'Kontakt Einstellungen' => ['contact_recipient_email', 'contact_subject_prefix', 'privacy_text', 'success_message', 'error_message', 'form_storage_enabled', 'sender_copy_enabled', 'honeypot_enabled', 'captcha_prepared', 'show_submissions_backend'],
            'SEO Einstellungen' => ['seo_meta_title_enabled', 'seo_meta_description_enabled', 'seo_open_graph_enabled', 'seo_twitter_card_enabled', 'seo_faq_schema_enabled', 'seo_service_schema_enabled', 'seo_breadcrumb_schema_enabled', 'seo_canonical_enabled', 'seo_noindex_per_page_enabled', 'seo_nofollow_per_page_enabled'],
            'Tracking Einstellungen' => ['tracking_button_clicks_enabled', 'tracking_form_submit_enabled', 'tracking_anchor_clicks_enabled', 'tracking_only_when_system_active'],
        ];
        echo '<form method="post"><input type="hidden" name="csrf_token" value="' . self::esc(self::nonce('beratung_settings')) . '"><input type="hidden" name="beratung_admin_action" value="save_settings"><div class="beratung-settings-grid">';
        foreach ($groups as $title => $keys) {
            echo '<section class="beratung-card"><h1>' . self::esc($title) . '</h1>';
            foreach ($keys as $key) {
                $label = ucwords(str_replace('_', ' ', $key));
                if (str_ends_with($key, '_enabled') || str_starts_with($key, 'seo_') || str_starts_with($key, 'tracking_') || in_array($key, ['use_default_font', 'allow_custom_page_css_class', 'captcha_prepared', 'show_submissions_backend'], true)) {
                    self::checkbox($label, $key, ($settings[$key] ?? '0') === '1');
                } elseif (str_contains($key, 'color')) {
                    self::field($label, $key, $settings[$key] ?? '', 'color');
                } elseif (str_contains($key, 'message') || str_contains($key, 'privacy')) {
                    self::textarea($label, $key, $settings[$key] ?? '', 3);
                } else {
                    self::field($label, $key, $settings[$key] ?? '', in_array($key, ['border_radius', 'spacing', 'content_width', 'font_size_base', 'font_size_hero', 'font_size_section_title', 'font_size_card_title', 'header_spacing', 'footer_spacing', 'card_spacing', 'section_content_spacing'], true) ? 'number' : 'text');
                }
            }
            echo '</section>';
        }
        echo '</div><p><button class="beratung-btn" type="submit">Einstellungen speichern</button></p></form>';
    }

    private static function handle_landingpage_actions(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || (string) ($_POST['beratung_admin_action'] ?? '') !== 'landingpage_action') {
            return;
        }
        if (!self::verify_nonce('beratung_landingpage_action')) {
            self::redirect('cms-beratung-landingpages', ['error' => 'csrf']);
        }
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $action = (string) ($_POST['landingpage_action'] ?? '');
        $storage = CMS_Beratung_Storage::instance();
        if ($action === 'publish') {
            $storage->update_status($id, 'published');
        } elseif ($action === 'deactivate') {
            $storage->update_status($id, 'draft');
        } elseif ($action === 'duplicate') {
            $storage->duplicate_landingpage($id);
        } elseif ($action === 'delete') {
            $storage->delete_landingpage($id);
        }
        self::redirect('cms-beratung-landingpages', ['updated' => 1]);
    }

    private static function action_form(int $id, string $action, string $label, bool $danger = false): string
    {
        return '<form method="post" class="beratung-inline-form"><input type="hidden" name="csrf_token" value="' . self::esc(self::nonce('beratung_landingpage_action')) . '"><input type="hidden" name="beratung_admin_action" value="landingpage_action"><input type="hidden" name="landingpage_action" value="' . self::esc($action) . '"><input type="hidden" name="id" value="' . $id . '"><button class="' . ($danger ? 'is-danger' : '') . '" type="submit">' . self::esc($label) . '</button></form>';
    }

    private static function render_landingpage_proposals(string $active): void
    {
        echo '<section class="beratung-card beratung-template-library"><div class="beratung-template-library__head"><div><h1>Landingpage Vorschläge</h1><p>Wähle eine Vorlage aus. Die komplette Platzhalterseite wird direkt in den Editor geladen und kann anschließend als Entwurf gespeichert werden.</p></div><span>5 Vorlagen</span></div><div class="beratung-template-grid">';
        foreach (self::landingpage_proposals() as $slug => $proposal) {
            $isActive = $slug === $active;
            echo '<a class="beratung-template-card ' . ($isActive ? 'is-active' : '') . '" href="' . self::esc(self::admin_url('cms-beratung-new', ['proposal' => $slug])) . '">';
            echo '<span class="beratung-template-card__icon">' . self::esc((string) ($proposal['icon'] ?? '📄')) . '</span>';
            echo '<strong>' . self::esc((string) ($proposal['title'] ?? $slug)) . '</strong>';
            echo '<small>' . self::esc((string) ($proposal['badge'] ?? 'Vorlage')) . '</small>';
            echo '<p>' . self::esc((string) ($proposal['description'] ?? '')) . '</p>';
            echo '<em>' . ($isActive ? 'Aktuell geladen' : 'Diese Vorlage laden') . '</em>';
            echo '</a>';
        }
        echo '</div></section>';
    }

    /** @return array<string,array<string,string>> */
    private static function landingpage_proposals(): array
    {
        return [
            'microsoft-365-copilot' => ['icon' => '🤖', 'title' => 'Microsoft 365 und Copilot Beratung', 'badge' => 'Vollständige Beispielseite', 'description' => 'Hero, Herausforderungen, Leistungen, GenAI vs. Agentic AI, Ablauf, Technologien, Trust, CTA und Kontakt. FAQs werden zentral gepflegt.'],
            'copilot-readiness' => ['icon' => '✅', 'title' => 'Copilot Readiness Check', 'badge' => 'Copilot Vorlage', 'description' => 'Für Tenant-Readiness, Berechtigungen, Datenstruktur, Governance und Pilotierung.'],
            'security-review' => ['icon' => '🛡️', 'title' => 'Microsoft 365 Security Review', 'badge' => 'Security Vorlage', 'description' => 'Für Entra ID, Conditional Access, Defender, MFA, Rollen und Maßnahmenplan.'],
            'governance-workshop' => ['icon' => '🧭', 'title' => 'SharePoint Governance Workshop', 'badge' => 'Governance Vorlage', 'description' => 'Für SharePoint, OneDrive, Teams, Lifecycle, Berechtigungen und Namenskonzepte.'],
            'admin-automation' => ['icon' => '⚙️', 'title' => 'Admin Workshop & PowerShell', 'badge' => 'Admin Vorlage', 'description' => 'Für Admin Enablement, Dokumentation, Automatisierung und Betriebsübergabe.'],
        ];
    }

    /** @return array<string,mixed> */
    private static function default_landingpage(string $proposal = 'microsoft-365-copilot'): array
    {
        $proposal = array_key_exists($proposal, self::landingpage_proposals()) ? $proposal : 'microsoft-365-copilot';
        $page = [
            'internal_title' => 'Microsoft 365 Beratung',
            'public_title' => 'Microsoft 365 und Copilot Beratung',
            'slug' => 'microsoft-365-und-copilot-beratung',
            'status' => 'draft',
            'template' => 'modern',
            'max_content_width' => 1160,
            'use_global_settings' => 1,
            'use_global_design' => 1,
            'show_header' => 1,
            'show_footer' => 1,
            'show_breadcrumb' => 1,
            'show_toc' => 1,
            'show_anchor_nav' => 1,
            'hero' => [
                'enabled' => true,
                'image_url' => '',
                'image_position' => 'left',
                'image_flush' => true,
                'image_height' => 520,
                'image_width' => 46,
                'image_fit' => 'cover',
                'image_alt' => 'Microsoft 365 Beratung',
                'badge_text' => 'Microsoft 365 und Copilot Beratung',
                'badge_show' => true,
                'standalone_header_enabled' => false,
                'standalone_header_slug' => 'microsoft-365-und-copilot-beratung-standalone',
                'standalone_header_blog_logo_url' => '',
                'standalone_header_partner_logo_url' => '',
                'standalone_header_title' => 'Microsoft 365 und Copilot Beratung',
                'standalone_header_subtitle' => 'Standalone Publicsite ohne Theme Header und Footer.',
                'standalone_header_menu_enabled' => true,
                'standalone_header_menu_items' => [
                    ['label' => 'Leistungen', 'target' => '#leistungen'],
                    ['label' => 'Ablauf', 'target' => '#ablauf'],
                    ['label' => 'Termin', 'target' => '#termin-buchen'],
                    ['label' => 'Kontakt', 'target' => '#kontakt'],
                ],
                'title' => 'Microsoft 365 und Copilot sauber einführen, statt einfach nur Lizenzen zu verteilen',
                'subtitle' => 'Microsoft 365, Copilot, Security und Governance technisch sauber bewerten und praxisnah umsetzen.',
                'description' => 'Ich unterstütze Dich dabei, Microsoft 365, Copilot, Security und Governance technisch sauber zu bewerten, sinnvoll zu strukturieren und praxisnah umzusetzen.',
                'button_1' => ['text' => 'Beratung anfragen', 'target' => '#kontakt', 'target_type' => 'contact', 'style' => 'primary'],
                'button_2' => ['text' => 'Leistungen ansehen', 'target' => '#leistungen', 'target_type' => 'anchor', 'style' => 'ghost'],
                'button_3' => ['text' => 'Copilot Readiness prüfen', 'target' => '#copilot-readiness', 'target_type' => 'anchor', 'style' => 'secondary'],
                'trust_badges' => ['Ex-Microsoft MVP', '20+ Jahre', 'LPIC 1 & 2', 'Microsoft zertifiziert'],
                'trust_image_enabled' => true,
                'trust_image_url' => '',
                'trust_image_alt' => 'Portrait eines Microsoft 365 Beraters',
                'partner_band_enabled' => false,
                'partner_band_text' => 'Zugehörig zum copilotberater.de Netzwerk',
                'partner_band_website_label' => 'copilotberater.de',
                'partner_band_website_url' => 'https://copilotberater.de',
                'partner_band_map_label' => 'Copilotberater Deutschland Karte',
                'partner_band_map_url' => 'https://copilotberater.de/copilotberater-deutschland-karte/',
                'anchor_nav_layout' => 'pills',
                'toc_right_display' => 'card',
                'toc_right_layout' => 'card',
                'trust_text' => 'Praxisnahe Beratung für Microsoft 365, Copilot, Security und Compliance.',
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'vertical_align' => 'center',
                'mobile_order' => 'image-first',
            ],
            'sections' => [[
                'id' => 'intro',
                'anchor_id' => 'intro',
                'enabled' => true,
                'type' => 'text',
                'internal_name' => 'Intro Bereich',
                'eyebrow' => 'Einordnung',
                'title' => 'Microsoft 365 Beratung beginnt bei Struktur, Sicherheit und Alltagstauglichkeit',
                'intro' => 'Bevor neue Tools wie Copilot echten Mehrwert liefern, müssen Berechtigungen, Datenqualität, Governance und Betriebsprozesse zusammenpassen. Diese Beispielseite zeigt eine vollständige Beratungs-Landingpage mit austauschbaren Platzhalterinhalten.',
                'columns' => 1,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'cards' => [],
            ], [
                'id' => 'herausforderungen',
                'anchor_id' => 'herausforderungen',
                'enabled' => true,
                'type' => 'card_grid',
                'internal_name' => 'Typische Herausforderungen',
                'eyebrow' => 'Ausgangslage',
                'title' => 'Typische Herausforderungen vor Microsoft 365 und Copilot Projekten',
                'intro' => 'Diese Punkte tauchen in gewachsenen Microsoft 365 Umgebungen besonders häufig auf.',
                'columns' => 3,
                'card_type' => 'text',
                'card_design' => 'accent',
                'equal_height' => true,
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔓', 'title' => 'Zu viele Berechtigungen', 'text' => 'Zu viele Berechtigungen in SharePoint und OneDrive machen Datenzugriffe schwer nachvollziehbar.'],
                    ['enabled' => true, 'icon' => '💬', 'title' => 'Unklare Teams Governance', 'text' => 'Microsoft Teams ist produktiv, aber Namenskonzepte, Lebenszyklen und Verantwortlichkeiten sind unklar.'],
                    ['enabled' => true, 'icon' => '🤖', 'title' => 'Copilot ohne Vorbereitung', 'text' => 'Copilot wird eingeführt, aber der Tenant ist technisch und organisatorisch noch nicht vorbereitet.'],
                    ['enabled' => true, 'icon' => '🚦', 'title' => 'Historischer Conditional Access', 'text' => 'Conditional Access und MFA sind historisch gewachsen und enthalten Ausnahmen oder blinde Flecken.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Purview nicht sauber konfiguriert', 'text' => 'DLP, Sensitivity Labels und Aufbewahrung sind vorhanden, aber nicht konsistent nutzbar.'],
                ],
            ], [
                'id' => 'leistungen',
                'anchor_id' => 'leistungen',
                'enabled' => true,
                'type' => 'services',
                'internal_name' => 'Meine Leistungen',
                'eyebrow' => 'Meine Leistungen',
                'title' => 'Microsoft 365 und Copilot Beratung aus der Praxis',
                'intro' => 'Frei sortierbare Leistungen für Tenant, Security, Compliance, Governance, Automatisierung und Workshops.',
                'columns' => 4,
                'card_type' => 'text',
                'card_design' => 'accent',
                'equal_height' => true,
                'categories_enabled' => true,
                'note_text' => 'Alle Leistungen können als Einzeltermin, Workshop oder Projektbegleitung kombiniert werden.',
                'button_1_text' => 'Beratung anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'button_1_style' => 'primary',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'padding_top' => 56,
                'padding_bottom' => 56,
                'max_width' => 1160,
                'text_align' => 'left',
                'cards' => [
                    ['enabled' => true, 'icon' => '🏢', 'category' => 'Tenant', 'title' => 'Microsoft 365 Tenant Check', 'text' => 'Du bekommst eine klare Risiko- und Lizenzeinschätzung deines Tenants und weißt, wo Governance fehlt.'],
                    ['enabled' => true, 'icon' => '🤖', 'category' => 'Copilot', 'title' => 'Copilot Readiness Check', 'text' => 'Du erkennst, ob Datenzugriffe, Inhalte und Compliance für Copilot belastbar vorbereitet sind. So startest du kontrolliert statt mit blinden Flecken.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Identity', 'title' => 'Entra ID Security Review', 'text' => 'Du siehst, welche Identitätsrisiken deinen Tenant wirklich betreffen. Daraus entstehen konkrete Schritte für MFA, Rollen und Zugriffsschutz.'],
                    ['enabled' => true, 'icon' => '📜', 'category' => 'Compliance', 'title' => 'Microsoft Purview Beratung', 'text' => 'Du weißt, welche Purview-Funktionen für deine Daten sinnvoll sind und wie Schutz, DLP und Aufbewahrung zusammenwirken.'],
                    ['enabled' => true, 'icon' => '🧭', 'category' => 'Governance', 'title' => 'SharePoint und OneDrive Governance', 'text' => 'Du bekommst klare Regeln für Sites, Freigaben und Berechtigungen. So reduzierst du Wildwuchs und schaffst eine bessere Grundlage für Copilot.'],
                    ['enabled' => true, 'icon' => '✉️', 'category' => 'Exchange', 'title' => 'Exchange Online Analyse', 'text' => 'Du erkennst Risiken in Mailfluss, Postfächern und Schutzfunktionen. Danach weißt du, welche Anpassungen Betrieb und Sicherheit verbessern.'],
                    ['enabled' => true, 'icon' => '🛡️', 'category' => 'Security', 'title' => 'Microsoft Defender Review', 'text' => 'Du bekommst priorisierte Findings zu Defender und Secure Score. So weißt du, welche Schutzmaßnahmen zuerst Wirkung bringen.'],
                    ['enabled' => true, 'icon' => '🚦', 'category' => 'Access', 'title' => 'Conditional Access Bewertung', 'text' => 'Du erkennst unsichere Ausnahmen, Lücken und Konflikte in deinen Richtlinien. Daraus entsteht ein belastbarer Zugriffsschutz für Benutzer und Admins.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Enablement', 'title' => 'Admin Workshops', 'text' => 'Dein Admin-Team versteht die Entscheidungen und kann sie selbstständig weiterführen. Die Inhalte richten sich an euren echten Aufgaben aus.'],
                    ['enabled' => true, 'icon' => '⚙️', 'category' => 'Automation', 'title' => 'PowerShell Automatisierung', 'text' => 'Du reduzierst wiederkehrende Admin-Arbeit und bekommst nachvollziehbare Skripte für stabile Abläufe.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Betrieb', 'title' => 'Dokumentation und Übergabe', 'text' => 'Du erhältst verständliche Ergebnisse, Entscheidungen und nächste Schritte. Damit bleiben Wissen und Verantwortung im Team nutzbar.'],
                    ['enabled' => true, 'icon' => '🤝', 'category' => 'Projekt', 'title' => 'Projektbegleitung', 'text' => 'Du bekommst technische Begleitung während Umsetzung, Review und Übergabe. So bleiben Entscheidungen sauber und Risiken früh sichtbar.'],
                ],
            ], [
                'id' => 'zusammenarbeit',
                'anchor_id' => 'zusammenarbeit',
                'enabled' => true,
                'type' => 'collaboration',
                'internal_name' => 'Zusammenarbeit',
                'eyebrow' => 'Zusammenarbeit',
                'title' => 'Expertinnen und Experten, mit denen ich bei dieser Dienstleistung zusammenarbeite',
                'intro' => 'Für Spezialthemen kann die Dienstleistung durch ausgewählte Experts aus dem 365 Network ergänzt werden.',
                'columns' => 3,
                'card_design' => 'accent',
                'expert_ids' => [],
                'mvp_note_enabled' => true,
                'mvp_note_text' => 'Darunter auch Microsoft MVPs aus dem 365 Network.',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'padding_top' => 56,
                'padding_bottom' => 56,
                'max_width' => 1160,
                'text_align' => 'left',
            ], [
                'id' => 'ablauf',
                'anchor_id' => 'ablauf',
                'enabled' => true,
                'type' => 'steps',
                'internal_name' => 'Beratungsablauf',
                'eyebrow' => 'Vorgehen',
                'title' => 'So läuft die Zusammenarbeit ab',
                'intro' => 'Ein klarer Ablauf macht Beratung planbar, nachvollziehbar und technisch belastbar.',
                'columns' => 4,
                'card_type' => 'step',
                'display_style' => 'horizontal',
                'auto_number' => true,
                'connector' => true,
                'equal_height' => true,
                'button_1_text' => 'Erstgespräch anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Erstgespräch', 'text' => 'Rahmen, Ausgangslage und gewünschtes Ergebnis klären.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Zielklärung', 'text' => 'Prioritäten, Risiken und betroffene Microsoft 365 Bereiche festlegen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Technische Analyse', 'text' => 'Tenant, Entra ID, Security, Compliance und Governance prüfen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Bewertung', 'text' => 'Findings einordnen und realistische Handlungsoptionen ableiten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Maßnahmenplan', 'text' => 'Konkrete Roadmap mit Quick Wins und sauberer Priorisierung erstellen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Umsetzung', 'text' => 'Konfigurationen, Pilotierung und Admin Enablement begleiten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Dokumentation', 'text' => 'Ergebnisse, Entscheidungen und Betriebswissen verständlich festhalten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Übergabe', 'text' => 'Nächste Schritte, Betrieb und Verantwortlichkeiten sauber übergeben.', 'connector' => false],
                ],
            ], [
                'id' => 'vergleich-genai-agentic-ai',
                'anchor_id' => 'vergleich',
                'enabled' => true,
                'type' => 'comparison',
                'internal_name' => 'Vergleich GenAI Agentic AI',
                'eyebrow' => 'Einordnung',
                'title' => 'Vergleiche und Infografik Inhalte',
                'intro' => 'Komplexe KI- und Governance-Themen verständlich gegenüberstellen.',
                'columns' => 2,
                'comparison_variant' => 'genai_agentic',
                'title_band_enabled' => true,
                'title_band_text' => 'GenAI vs. Agentic AI: Die wichtigsten Unterschiede',
                'title_band_background_color' => '#1e3a8a',
                'title_band_text_color' => '#ffffff',
                'border_enabled' => true,
                'shadow_enabled' => true,
                'mobile_stack' => true,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'icon' => '✨', 'title' => 'Generative KI', 'text' => 'Erstellt Inhalte wie Texte, Bilder oder Code auf Basis großer Sprachmodelle. Kreativ und leistungsfähig, aber oft isoliert und ohne direkten Bezug zu Unternehmensprozessen.', 'extra_text' => 'Typischer Einsatz: Content Erstellung, Ideenfindung, Automatisierung einfacher Aufgaben'],
                    ['enabled' => true, 'icon' => '🧠', 'title' => 'Agentic AI', 'text' => 'Geht einen Schritt weiter, handelt proaktiv, integriert sich in bestehende Systeme und führt Aufgaben je nach Anforderung teilautonom aus.', 'extra_text' => 'Typischer Einsatz: Prozessautomatisierung, intelligente Assistenz, datenbasierte Entscheidungsunterstützung'],
                ],
            ], [
                'id' => 'faq',
                'anchor_id' => 'faq',
                'enabled' => true,
                'type' => 'faq',
                'internal_name' => 'FAQ Modul',
                'eyebrow' => 'FAQ',
                'title' => 'Häufige Fragen zur Microsoft 365 und Copilot Beratung',
                'intro' => 'Antworten auf typische Fragen vor einem Beratungsprojekt.',
                'faq_allow_multiple' => false,
                'faq_icon_style' => 'plus',
                'faq_open_behavior' => 'first',
                'faq_schema_enabled' => true,
                'faq_question_background_color' => '#ffffff',
                'faq_question_text_color' => '#111827',
                'faq_answer_background_color' => '#f8fafc',
                'background_color' => '#f8fafc',
                'cards' => [
                    ['enabled' => true, 'question' => 'Was ist Microsoft 365 Copilot?', 'answer' => 'Microsoft 365 Copilot verbindet KI-Funktionen mit Microsoft 365 Apps und Unternehmensdaten, sofern Berechtigungen, Datenstruktur und Compliance sauber vorbereitet sind.', 'default_open' => true],
                    ['enabled' => true, 'question' => 'Für wen eignet sich Microsoft Copilot?', 'answer' => 'Copilot eignet sich für Organisationen, die Microsoft 365 aktiv nutzen und ihre Daten, Berechtigungen und Prozesse transparent im Griff haben möchten.'],
                    ['enabled' => true, 'question' => 'Ist Microsoft Copilot DSGVO konform einsetzbar?', 'answer' => 'Ein DSGVO-konformer Einsatz hängt von Konfiguration, Datenklassifizierung, Governance, Betriebsprozessen und rechtlicher Bewertung ab. Die Beratung hilft bei der technischen Einordnung.'],
                    ['enabled' => true, 'question' => 'Welche Voraussetzungen braucht mein Tenant?', 'answer' => 'Wichtig sind saubere Identitäten, Berechtigungen, Informationsschutz, SharePoint Governance, Auditierbarkeit und passende Lizenzierung.'],
                    ['enabled' => true, 'question' => 'Wie läuft ein Copilot Readiness Check ab?', 'answer' => 'Der Check bewertet Tenant-Konfiguration, Datenzugriffe, Security, Compliance, Governance und organisatorische Bereitschaft.'],
                    ['enabled' => true, 'question' => 'Was kostet eine Microsoft 365 Beratung?', 'answer' => 'Das hängt von Umfang, Zielen und gewünschter Tiefe ab. Nach einem Erstgespräch kann der Aufwand realistisch eingegrenzt werden.'],
                    ['enabled' => true, 'question' => 'Kann die Beratung remote stattfinden?', 'answer' => 'Ja, viele Checks, Workshops und Reviews können remote durchgeführt werden.'],
                    ['enabled' => true, 'question' => 'Unterstützt du auch bei der technischen Umsetzung?', 'answer' => 'Ja, neben Analyse und Konzept kann auch die technische Umsetzung begleitet werden.'],
                    ['enabled' => true, 'question' => 'Gibt es eine Dokumentation nach der Beratung?', 'answer' => 'Ja, Ergebnisse, Empfehlungen und nächste Schritte werden verständlich dokumentiert.'],
                    ['enabled' => true, 'question' => 'Können Workshops für Admin Teams durchgeführt werden?', 'answer' => 'Ja, Workshops für Admin Teams sind ein zentraler Bestandteil des Angebots.'],
                ],
            ], [
                'id' => 'trust',
                'anchor_id' => 'vertrauen',
                'enabled' => true,
                'type' => 'trust',
                'internal_name' => 'Trust Bereich',
                'eyebrow' => 'Vertrauen',
                'title' => 'Technische Beratung statt reines Folien Consulting',
                'intro' => 'Praxis, Betrieb und Sicherheit stehen im Mittelpunkt.',
                'columns' => 3,
                'button_1_text' => 'Projekt besprechen',
                'button_1_target' => '#kontakt',
                'background_color' => '#ffffff',
                'cards' => [
                    ['enabled' => true, 'icon' => '⏱️', 'metric' => '20+ Jahre', 'title' => 'IT Erfahrung', 'text' => 'Langjährige Praxis in Infrastruktur, Microsoft 365 und Administration.'],
                    ['enabled' => true, 'icon' => '🏢', 'title' => 'Echte Admin Umgebungen', 'text' => 'Microsoft 365 Praxis aus produktiven Szenarien statt Labortheorie.'],
                    ['enabled' => true, 'icon' => '🛡️', 'title' => 'Security und Governance', 'text' => 'Fokus auf Sicherheit, Governance und nachhaltigen Betrieb.'],
                    ['enabled' => true, 'icon' => '🛠️', 'title' => 'Technische Beratung', 'text' => 'Konkrete Konfiguration, Bewertung und Umsetzung statt Folienfriedhof.'],
                    ['enabled' => true, 'icon' => '📘', 'title' => 'Verständliche Dokumentation', 'text' => 'Ergebnisse so dokumentiert, dass Teams damit weiterarbeiten können.'],
                    ['enabled' => true, 'icon' => '🎓', 'title' => 'Admin Workshops', 'text' => 'Praxisnahe Workshops für Admin Teams und Betrieb.'],
                ],
            ], [
                'id' => 'technologien',
                'anchor_id' => 'technologien',
                'enabled' => true,
                'type' => 'technology',
                'internal_name' => 'Technologie Bereich',
                'eyebrow' => 'Technologien',
                'title' => 'Relevante Microsoft Technologien',
                'intro' => 'Beratung entlang der Plattformen, die im Microsoft 365 Betrieb wirklich zusammenspielen.',
                'columns' => 4,
                'display_style' => 'icon_grid',
                'background_color' => '#f8fafc',
                'cards' => [
                    ['enabled' => true, 'icon' => '☁️', 'name' => 'Microsoft 365'], ['enabled' => true, 'icon' => '🤖', 'name' => 'Microsoft Copilot'], ['enabled' => true, 'icon' => '🔐', 'name' => 'Microsoft Entra ID'], ['enabled' => true, 'icon' => '📜', 'name' => 'Microsoft Purview'],
                    ['enabled' => true, 'icon' => '🛡️', 'name' => 'Microsoft Defender'], ['enabled' => true, 'icon' => '🧭', 'name' => 'SharePoint Online'], ['enabled' => true, 'icon' => '📁', 'name' => 'OneDrive for Business'], ['enabled' => true, 'icon' => '✉️', 'name' => 'Exchange Online'],
                    ['enabled' => true, 'icon' => '💬', 'name' => 'Microsoft Teams'], ['enabled' => true, 'icon' => '⚙️', 'name' => 'PowerShell'], ['enabled' => true, 'icon' => '🌐', 'name' => 'Azure'], ['enabled' => true, 'icon' => '🚦', 'name' => 'Conditional Access'],
                ],
            ], [
                'id' => 'cta-copilot-readiness',
                'anchor_id' => 'copilot-readiness',
                'enabled' => true,
                'type' => 'cta',
                'internal_name' => 'CTA Band Copilot Readiness',
                'eyebrow' => 'Copilot Readiness',
                'title' => 'Du möchtest wissen, ob dein Microsoft 365 Tenant bereit für Copilot ist?',
                'intro' => 'Dann lass uns gemeinsam prüfen, wo Berechtigungen, Datenstruktur, Governance und Compliance wirklich stehen.',
                'display_style' => 'compact',
                'button_1_text' => 'Beratung anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'button_1_style' => 'primary',
                'button_2_text' => 'Leistungen ansehen',
                'button_2_target' => '#leistungen',
                'button_2_target_type' => 'anchor',
                'button_2_style' => 'ghost',
                'background_color' => '#1e3a8a',
                'text_color' => '#ffffff',
            ], [
                'id' => 'trenner',
                'anchor_id' => 'trenner',
                'enabled' => true,
                'type' => 'divider',
                'internal_name' => 'Zitat Trenner',
                'divider_type' => 'quote',
                'divider_title' => 'Gute Copilot Einführung beginnt nicht beim Prompt, sondern bei Daten, Identitäten und Governance.',
                'divider_icon' => '💬',
                'background_color' => '#eff6ff',
                'text_color' => '#111827',
                'divider_line_color' => '#93c5fd',
                'divider_width' => 80,
                'divider_padding_left' => 20,
                'divider_padding_right' => 20,
            ], [
                'id' => 'html-hinweis',
                'anchor_id' => 'html-hinweis',
                'enabled' => false,
                'type' => 'html',
                'internal_name' => 'Freier HTML Bereich',
                'eyebrow' => 'Optional',
                'title' => 'Freier HTML Bereich',
                'intro' => 'Nur für berechtigte Benutzer. Unsichere Skripte werden gefiltert.',
                'html' => '<p><strong>Optionaler Hinweis:</strong> Dieser Bereich kann für geprüfte HTML-Inhalte verwendet werden.</p>',
            ]],
            'design' => [],
        ];

        $updateSection = static function (array &$landingpage, string $id, array $updates): void {
            foreach ($landingpage['sections'] as &$section) {
                if (($section['id'] ?? '') !== $id) {
                    continue;
                }
                foreach ($updates as $key => $value) {
                    $section[$key] = $value;
                }
                break;
            }
            unset($section);
        };

        if ($proposal === 'copilot-readiness') {
            $page['internal_title'] = 'Copilot Readiness Check';
            $page['public_title'] = 'Copilot Readiness Check';
            $page['slug'] = 'copilot-readiness-check';
            $page['hero']['badge_text'] = 'Copilot Readiness';
            $page['hero']['title'] = 'Microsoft 365 Copilot vorbereiten, bevor sensible Daten sichtbar werden';
            $page['hero']['subtitle'] = 'Tenant, Berechtigungen, Governance und Compliance als Grundlage für Copilot.';
            $page['hero']['description'] = 'Diese Vorlage zeigt eine Landingpage für Copilot Readiness Checks mit Platzhaltertexten zu Datenzugriffen, SharePoint, Purview, Entra ID und Pilotierung.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Risiken vor einer Copilot Einführung',
                'intro' => 'Copilot verstärkt vorhandene Daten- und Berechtigungsstrukturen. Genau deshalb lohnt sich der Readiness Check vor dem Rollout.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔎', 'title' => 'Unklare Datenzugriffe', 'text' => 'Zu viele Personen können sensible SharePoint- oder OneDrive-Inhalte finden.'],
                    ['enabled' => true, 'icon' => '📚', 'title' => 'Veraltete Inhalte', 'text' => 'Alte Dokumente, Dubletten und Testdaten verschlechtern Copilot-Antworten.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Fehlende Klassifizierung', 'text' => 'Sensitivity Labels, DLP und Aufbewahrung sind nicht konsequent umgesetzt.'],
                    ['enabled' => true, 'icon' => '👥', 'title' => 'Pilotgruppe unklar', 'text' => 'Ohne sinnvolle Pilotgruppen entstehen falsche Erwartungen und wenig messbarer Nutzen.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Copilot Readiness Bausteine',
                'intro' => 'Konkrete Prüfpunkte für einen kontrollierten Microsoft 365 Copilot Start.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🧭', 'category' => 'Readiness', 'title' => 'Tenant Readiness', 'text' => 'Lizenzierung, Admin Center, Basiskonfiguration und technische Voraussetzungen prüfen.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Datenzugriff', 'title' => 'Permission Review', 'text' => 'SharePoint, OneDrive und Teams Berechtigungen risikoorientiert bewerten.'],
                    ['enabled' => true, 'icon' => '🏷️', 'category' => 'Compliance', 'title' => 'Purview Quick Check', 'text' => 'Labels, DLP, Audit und Aufbewahrung als Copilot-Schutzschicht einordnen.'],
                    ['enabled' => true, 'icon' => '🧪', 'category' => 'Pilot', 'title' => 'Pilotkonzept', 'text' => 'Pilotgruppen, Use Cases, Erfolgskriterien und Feedbackprozess definieren.'],
                    ['enabled' => true, 'icon' => '📈', 'category' => 'Adoption', 'title' => 'Enablement Plan', 'text' => 'Admin- und Anwenderkommunikation für realistische Copilot-Nutzung vorbereiten.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Ergebnis', 'title' => 'Readiness Report', 'text' => 'Priorisierte Maßnahmen, Quick Wins und konkrete nächste Schritte dokumentieren.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Copilot Readiness Check', 'cards' => [
                ['enabled' => true, 'question' => 'Warum vor Copilot einen Readiness Check durchführen?', 'answer' => 'Weil Copilot vorhandene Berechtigungen und Datenqualität nutzt. Schwächen werden dadurch sichtbarer.'],
                ['enabled' => true, 'question' => 'Wird Copilot dabei bereits aktiviert?', 'answer' => 'Nein, der Check bewertet die Voraussetzungen und bereitet einen kontrollierten Pilot vor.'],
                ['enabled' => true, 'question' => 'Welche Bereiche werden geprüft?', 'answer' => 'SharePoint, OneDrive, Teams, Entra ID, Purview, Lizenzierung, Governance und Pilotfähigkeit.'],
            ]]);
        } elseif ($proposal === 'security-review') {
            $page['internal_title'] = 'Microsoft 365 Security Review';
            $page['public_title'] = 'Microsoft 365 Security Review';
            $page['slug'] = 'microsoft-365-security-review';
            $page['template'] = 'security';
            $page['hero']['badge_text'] = 'Security · Entra ID · Defender';
            $page['hero']['title'] = 'Microsoft 365 Security sichtbar machen und Risiken priorisieren';
            $page['hero']['subtitle'] = 'Entra ID, Conditional Access, MFA, Defender und Adminrollen strukturiert prüfen.';
            $page['hero']['description'] = 'Diese Vorlage ist für Security Reviews mit Platzhaltertexten zu Identitäten, Zugriffen, Schutzfunktionen und Maßnahmenplan vorbereitet.';
            $page['hero']['background_color'] = '#0f172a';
            $page['hero']['text_color'] = '#ffffff';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Security-Schwachstellen in Microsoft 365',
                'intro' => 'Diese Vorlage fokussiert Identitäten, Adminrollen, Zugriffe und Schutzfunktionen.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔑', 'title' => 'Zu viele Adminrollen', 'text' => 'Privilegierte Konten sind nicht sauber getrennt, dokumentiert oder überwacht.'],
                    ['enabled' => true, 'icon' => '🚪', 'title' => 'Conditional Access Lücken', 'text' => 'Ausnahmen, Legacy Authentication oder fehlende Gerätesignale erzeugen Risiko.'],
                    ['enabled' => true, 'icon' => '🛡️', 'title' => 'Defender nicht ausgeschöpft', 'text' => 'Schutzfunktionen sind vorhanden, aber nicht konsistent aktiviert oder überwacht.'],
                    ['enabled' => true, 'icon' => '👤', 'title' => 'Gastzugriffe unklar', 'text' => 'Externe Benutzer, Freigaben und Kollaboration sind nicht transparent.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Security Review Module',
                'intro' => 'Gezielte Analyse für Entra ID, Conditional Access, Defender und Admin-Betrieb.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🪪', 'category' => 'Identity', 'title' => 'Entra ID Rollencheck', 'text' => 'Adminrollen, PIM-Vorbereitung, Break-Glass und privilegierte Konten bewerten.'],
                    ['enabled' => true, 'icon' => '🚦', 'category' => 'Access', 'title' => 'Conditional Access Review', 'text' => 'Richtlinien, Ausnahmen, MFA, Geräte und Risiko-Signale prüfen.'],
                    ['enabled' => true, 'icon' => '🛡️', 'category' => 'Defender', 'title' => 'Defender Konfigurationscheck', 'text' => 'E-Mail, Endpoint, Identity und Cloud Apps Schutzfunktionen einordnen.'],
                    ['enabled' => true, 'icon' => '📊', 'category' => 'Secure Score', 'title' => 'Priorisierung', 'text' => 'Findings nach Risiko, Aufwand und Wirkung sortieren.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Dokumentation', 'title' => 'Maßnahmenplan', 'text' => 'Konkreter Maßnahmenplan mit Quick Wins und Verantwortlichkeiten.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Enablement', 'title' => 'Admin Briefing', 'text' => 'Ergebnisse verständlich erklären und Betriebsteam befähigen.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Microsoft 365 Security Review', 'cards' => [
                ['enabled' => true, 'question' => 'Ist der Review invasiv?', 'answer' => 'Nein, im Fokus stehen Konfiguration, Export/Ansicht vorhandener Einstellungen und nachvollziehbare Bewertung.'],
                ['enabled' => true, 'question' => 'Wer sollte teilnehmen?', 'answer' => 'Mindestens Microsoft 365 Admins, Security-Verantwortliche und Personen mit Entscheidungsbefugnis.'],
                ['enabled' => true, 'question' => 'Gibt es konkrete Empfehlungen?', 'answer' => 'Ja, die Ergebnisse werden priorisiert und mit Quick Wins sowie Folgeaufgaben dokumentiert.'],
            ]]);
        } elseif ($proposal === 'governance-workshop') {
            $page['internal_title'] = 'SharePoint Governance Workshop';
            $page['public_title'] = 'SharePoint und OneDrive Governance Workshop';
            $page['slug'] = 'sharepoint-governance-workshop';
            $page['hero']['badge_text'] = 'SharePoint · OneDrive · Teams';
            $page['hero']['title'] = 'SharePoint, OneDrive und Teams sauber strukturieren';
            $page['hero']['subtitle'] = 'Governance, Berechtigungen, Lifecycle und Namenskonzepte verständlich aufbauen.';
            $page['hero']['description'] = 'Diese Vorlage ist für Governance Workshops mit Platzhaltertexten zu Freigaben, Sites, Teams, Lifecycle und Rollenmodell vorbereitet.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Governance-Probleme in SharePoint und Teams',
                'intro' => 'Diese Vorlage richtet sich an Organisationen mit gewachsenen Sites, Teams und Freigaben.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🗂️', 'title' => 'Unklare Site-Struktur', 'text' => 'Sites, Hubs und Teams sind historisch gewachsen und schwer nachvollziehbar.'],
                    ['enabled' => true, 'icon' => '🔗', 'title' => 'Freigaben ohne Leitplanken', 'text' => 'Externe Links und Gastzugriffe werden nicht einheitlich gesteuert.'],
                    ['enabled' => true, 'icon' => '♻️', 'title' => 'Kein Lifecycle', 'text' => 'Alte Teams und Sites bleiben bestehen, obwohl sie nicht mehr genutzt werden.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Fehlende Namenskonzepte', 'text' => 'Teams, Gruppen und Sites folgen keinem verständlichen Muster.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Governance Workshop Inhalte',
                'intro' => 'Praktische Bausteine für SharePoint, OneDrive und Teams Governance.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🧭', 'category' => 'Struktur', 'title' => 'Informationsarchitektur', 'text' => 'Sites, Hubs, Teams und Ablagebereiche verständlich strukturieren.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Zugriff', 'title' => 'Berechtigungsmodell', 'text' => 'Rollen, Freigaben, Gäste und Verantwortlichkeiten sauber festlegen.'],
                    ['enabled' => true, 'icon' => '♻️', 'category' => 'Lifecycle', 'title' => 'Lebenszyklus-Konzept', 'text' => 'Erstellung, Prüfung, Archivierung und Löschung von Arbeitsbereichen definieren.'],
                    ['enabled' => true, 'icon' => '🏷️', 'category' => 'Naming', 'title' => 'Namenskonventionen', 'text' => 'Benennung, Vorlagen und Metadaten praxisnah festlegen.'],
                    ['enabled' => true, 'icon' => '📜', 'category' => 'Regeln', 'title' => 'Governance Leitfaden', 'text' => 'Kurze, verständliche Regeln statt unlesbarer Richtliniendokumente.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Workshop', 'title' => 'Admin Enablement', 'text' => 'Admins und Site Owner für den Alltag befähigen.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Governance Workshop', 'cards' => [
                ['enabled' => true, 'question' => 'Geht es nur um SharePoint?', 'answer' => 'Nein, betrachtet werden SharePoint, OneDrive, Teams, Gruppen, Freigaben und Verantwortlichkeiten gemeinsam.'],
                ['enabled' => true, 'question' => 'Entsteht ein Governance Dokument?', 'answer' => 'Ja, als verständlicher Leitfaden mit Regeln, Rollen und nächsten Schritten.'],
                ['enabled' => true, 'question' => 'Kann ein bestehender Wildwuchs bereinigt werden?', 'answer' => 'Ja, der Workshop kann eine Bereinigungs-Roadmap und Quick Wins vorbereiten.'],
            ]]);
        } elseif ($proposal === 'admin-automation') {
            $page['internal_title'] = 'Admin Workshop und PowerShell Automatisierung';
            $page['public_title'] = 'Admin Workshop und PowerShell Automatisierung';
            $page['slug'] = 'admin-workshop-powershell-automatisierung';
            $page['template'] = 'technical';
            $page['hero']['badge_text'] = 'Admin Workshop · PowerShell · Betrieb';
            $page['hero']['title'] = 'Microsoft 365 Administration verständlich machen und wiederkehrende Aufgaben automatisieren';
            $page['hero']['subtitle'] = 'Workshops, Skripte, Dokumentation und Übergabe für Admin Teams.';
            $page['hero']['description'] = 'Diese Vorlage ist für Admin Enablement, PowerShell-Automatisierung und Betriebsübergabe mit passenden Platzhalterbereichen vorbereitet.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Herausforderungen im Microsoft 365 Admin-Alltag',
                'intro' => 'Diese Vorlage ist für Admin Teams, die Wissen, Prozesse und wiederkehrende Aufgaben stabilisieren möchten.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🧑‍💻', 'title' => 'Wissen verteilt', 'text' => 'Admin-Wissen steckt in Köpfen, Chats und alten Notizen statt in klaren Abläufen.'],
                    ['enabled' => true, 'icon' => '🔁', 'title' => 'Manuelle Routinen', 'text' => 'Wiederkehrende Prüfungen und Exporte werden manuell und uneinheitlich erledigt.'],
                    ['enabled' => true, 'icon' => '📉', 'title' => 'Fehlende Standards', 'text' => 'Skripte, Namensregeln und Dokumentation folgen keinem gemeinsamen Muster.'],
                    ['enabled' => true, 'icon' => '🚚', 'title' => 'Schwierige Übergabe', 'text' => 'Betrieb und Projektwissen lassen sich schwer an Teams übergeben.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Admin Workshop und Automation Bausteine',
                'intro' => 'Praxisnahe Inhalte für Microsoft 365 Admin Teams und Betriebsübergaben.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Workshop', 'title' => 'Admin Enablement', 'text' => 'Microsoft 365 Admin Center, Entra ID, Exchange, Teams und SharePoint praxisnah erklären.'],
                    ['enabled' => true, 'icon' => '⚙️', 'category' => 'PowerShell', 'title' => 'Skript Grundlagen', 'text' => 'Wiederverwendbare PowerShell-Strukturen, Logging und sichere Ausführung aufbauen.'],
                    ['enabled' => true, 'icon' => '📊', 'category' => 'Reporting', 'title' => 'Admin Reports', 'text' => 'Benutzer, Gruppen, Lizenzen, Gastzugriffe und Berechtigungen nachvollziehbar auswerten.'],
                    ['enabled' => true, 'icon' => '🧪', 'category' => 'Betrieb', 'title' => 'Runbooks', 'text' => 'Wiederkehrende Aufgaben als klare Runbooks und Checklisten dokumentieren.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Sicherheit', 'title' => 'Sichere Automatisierung', 'text' => 'Berechtigungen, App-Registrierungen und Secrets sauber einordnen.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Übergabe', 'title' => 'Dokumentation', 'text' => 'Wissen so festhalten, dass das Admin Team es im Alltag nutzen kann.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zu Admin Workshop und PowerShell', 'cards' => [
                ['enabled' => true, 'question' => 'Müssen PowerShell-Kenntnisse vorhanden sein?', 'answer' => 'Nein, Inhalte können von Grundlagen bis zu fortgeschrittener Automatisierung angepasst werden.'],
                ['enabled' => true, 'question' => 'Werden echte Skripte erstellt?', 'answer' => 'Ja, auf Wunsch entstehen wiederverwendbare Beispiele, Reports oder Runbooks für den Alltag.'],
                ['enabled' => true, 'question' => 'Ist die Übergabe dokumentiert?', 'answer' => 'Ja, Ziel ist eine verständliche Dokumentation für Betrieb und Weiterentwicklung.'],
            ]]);
        }

        $targetSectionOrder = ['proof', 'partner_band', 'comparison', 'services', 'steps', 'collaboration', 'trust', 'technology', 'html', 'booking', 'divider', 'cta'];
        $targetSectionNames = [
            'proof' => 'Grundlagen',
            'partner_band' => 'Partnerband',
            'comparison' => 'Vergleich GenAI Agentic AI',
            'services' => 'Meine Leistungen',
            'steps' => 'Beratungsablauf',
            'collaboration' => 'Zusammenarbeit',
            'trust' => 'Trust Bereich',
            'technology' => 'Technologie Bereich',
            'html' => 'Freier HTML Bereich',
            'booking' => 'Terminbuchung',
            'divider' => 'Zitat Trenner',
            'cta' => 'CTA Band Copilot Readiness',
        ];
        $ensureSection = static function (array &$sections, string $type, array $defaults): void {
            foreach ($sections as $section) {
                if (($section['type'] ?? '') === $type) {
                    return;
                }
            }
            $sections[] = $defaults + ['enabled' => true, 'type' => $type, 'cards' => []];
        };
        $ensureSection($page['sections'], 'proof', [
            'id' => 'belegbare-grundlagen',
            'anchor_id' => 'belegbare-grundlagen',
            'internal_name' => 'Grundlagen',
            'eyebrow' => 'Belegbare Grundlagen',
            'title' => 'Was nach Beratung greifbar wird',
            'intro' => 'Keine erfundenen Kundenzitate. Hier stehen nur nachvollziehbare Erfahrung, klare Projektbelege und später echte freigegebene Referenzen.',
            'columns' => 3,
            'card_type' => 'text',
            'card_design' => 'accent',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
            'cards' => [
                ['enabled' => true, 'badge' => 'Erfahrung', 'title' => '20+ Jahre Microsoft-Infrastruktur', 'text' => 'Senior IT-Admin mit Schwerpunkt Microsoft 365, Azure, Exchange, PowerShell, IT-Security und Datenschutz/Compliance.'],
                ['enabled' => true, 'badge' => 'Prüfung', 'title' => 'IHK-Prüfer', 'text' => 'Prüfungsperspektive aus Ausbildung und Praxis. Das hilft bei klaren Standards, verständlicher Übergabe und sauberer Dokumentation.'],
                ['enabled' => true, 'badge' => 'Zertifizierung', 'title' => 'Mehrfach zertifiziert', 'text' => 'LPIC 1 & 2 sowie Microsoft-Zertifizierungen ergänzen die praktische Erfahrung.'],
            ],
        ]);
        $ensureSection($page['sections'], 'partner_band', [
            'id' => 'partnerband',
            'anchor_id' => 'partnerband',
            'enabled' => false,
            'internal_name' => 'Partnerband',
            'eyebrow' => 'Netzwerk',
            'title' => 'Zugehörig zum copilotberater.de Netzwerk',
            'intro' => 'Einordnung, Netzwerkbezug und weiterführende Links zum Partnerangebot.',
            'partner_layout' => 'network-card',
            'button_1_text' => 'copilotberater.de',
            'button_1_target' => 'https://copilotberater.de',
            'button_1_target_type' => 'external',
            'button_1_style' => 'primary',
            'button_2_text' => 'Copilotberater Deutschland Karte',
            'button_2_target' => 'https://copilotberater.de/copilotberater-deutschland-karte/',
            'button_2_target_type' => 'external',
            'button_2_style' => 'ghost',
        ]);
        $ensureSection($page['sections'], 'booking', [
            'id' => 'termin-buchen',
            'anchor_id' => 'termin-buchen',
            'internal_name' => 'Terminbuchung',
            'eyebrow' => 'Termin',
            'title' => 'Direkt einen Termin buchen',
            'intro' => 'Wähle einen passenden Slot für ein erstes Gespräch zu Microsoft 365, Copilot oder Security. Danach klären wir Ziel, Ausgangslage und den nächsten sinnvollen Schritt.',
            'booking_url' => '',
            'booking_display' => 'embed',
            'booking_button_text' => 'Termin buchen',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ]);
        $page['sections'] = array_values(array_filter($page['sections'], static fn(array $section): bool => in_array((string) ($section['type'] ?? ''), $targetSectionOrder, true)));
        usort($page['sections'], static function (array $a, array $b) use ($targetSectionOrder): int {
            $rankA = array_search((string) ($a['type'] ?? ''), $targetSectionOrder, true);
            $rankB = array_search((string) ($b['type'] ?? ''), $targetSectionOrder, true);
            return ($rankA === false ? 999 : $rankA) <=> ($rankB === false ? 999 : $rankB);
        });
        foreach ($page['sections'] as $index => &$section) {
            $section['sort_order'] = $index + 1;
            $type = (string) ($section['type'] ?? '');
            if (isset($targetSectionNames[$type])) {
                $section['internal_name'] = $targetSectionNames[$type];
            }
        }
        unset($section);
        return $page;
    }

    private static function render_with_layout(string $activePage, callable $renderer): void
    {
        self::check_access();
        self::ensure_shared_contract_loaded();
        // Nicht im Menü geführte Ansichten markieren den Menüeintrag „Landingpages“.
        $layoutActivePage = in_array($activePage, self::UNLISTED_PAGE_SLUGS, true) ? self::MENU_SLUG : $activePage;
        if (function_exists('cms_plugin_admin_layout_start')) {
            cms_plugin_admin_layout_start(self::PAGE_TITLES[$activePage] ?? 'Beratung', $layoutActivePage);
        } elseif (function_exists('renderAdminLayoutStart')) {
            renderAdminLayoutStart(self::PAGE_TITLES[$activePage] ?? 'Beratung', $layoutActivePage);
        }
        self::enqueue_admin_assets();
        echo '<div class="beratung-admin beratung-admin--' . self::esc(self::normalize_slug($activePage)) . '">';
        $renderer();
        echo '</div>';
        self::enqueue_admin_scripts();
        if (function_exists('cms_plugin_admin_layout_end')) {
            cms_plugin_admin_layout_end();
        } elseif (function_exists('renderAdminLayoutEnd')) {
            renderAdminLayoutEnd();
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
            echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/admin.css') . '?v=' . filemtime($css) . '">' . "\n";
        }
        $faqCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/admin-faq.css';
        if (is_file($faqCss)) {
            echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/admin-faq.css') . '?v=' . filemtime($faqCss) . '">' . "\n";
        }
        $mediaCss = CMS_BERATUNG_PLUGIN_DIR . 'assets/css/admin-media.css';
        if (is_file($mediaCss)) {
            echo '<link rel="stylesheet" href="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/css/admin-media.css') . '?v=' . filemtime($mediaCss) . '">' . "\n";
        }
    }

    private static function enqueue_admin_scripts(): void
    {
        $js = CMS_BERATUNG_PLUGIN_DIR . 'assets/js/admin-builder.js';
        if (is_file($js)) {
            echo '<script src="' . self::esc(CMS_BERATUNG_PLUGIN_URL . 'assets/js/admin-builder.js') . '?v=' . filemtime($js) . '" defer></script>' . "\n";
        }
    }

    private static function check_access(): void
    {
        $hasCapability = !function_exists('current_user_can') || current_user_can('manage_options');
        $isAdmin = !class_exists('CMS\\Auth') || \CMS\Auth::instance()->isAdmin();
        if (!$hasCapability || !$isAdmin) {
            header('Location: ' . (defined('SITE_URL') ? (string) SITE_URL : '/'), true, 302);
            exit;
        }
    }

    private static function nonce(string $action): string
    {
        return class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken($action) : '';
    }

    private static function verify_nonce(string $action): bool
    {
        return class_exists('CMS\\Security') && \CMS\Security::instance()->verifyToken((string) ($_POST['csrf_token'] ?? ''), $action);
    }

    private static function render_media_config(): void
    {
        $config = [
            'uploadUrl' => '/api/cms-beratung/media-upload',
            'libraryUrl' => '/api/media',
            'csrfToken' => class_exists('CMS\\Security') ? (string) \CMS\Security::instance()->generateToken('editorjs_media') : '',
            'uploadFolder' => '/uploads/beratung/',
            'maxSizeMb' => 10,
        ];
        $json = (string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '<script type="application/json" id="beratung-media-config">' . str_replace('</script', '<\/script', $json) . '</script>';
    }

    private static function render_expert_options_config(): void
    {
        $items = [];
        if (class_exists('CMS_365NET_Experts_And_Companie_Database')) {
            try {
                foreach (CMS_365NET_Experts_And_Companie_Database::instance()->getExpertsPublic('', '', 500, 0) as $expert) {
                    if (!is_object($expert)) {
                        continue;
                    }
                    $id = (int) ($expert->id ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $first = trim((string) ($expert->first_name ?? ''));
                    $last = trim((string) ($expert->last_name ?? ''));
                    $name = trim($first . ' ' . $last);
                    if ($name === '') {
                        continue;
                    }
                    $position = trim((string) ($expert->position ?? ''));
                    $company = trim((string) ($expert->company ?? ''));
                    $awards = trim((string) ($expert->awards ?? ''));
                    $items[] = [
                        'id' => $id,
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
            } catch (Throwable) {
                $items = [];
            }
        }
        $json = (string) json_encode(['experts' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '<script type="application/json" id="beratung-expert-options">' . str_replace('</script', '<\/script', $json) . '</script>';
    }

    /** @param array<string,mixed> $payload */
    private static function json_response(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"success":0}';
        exit;
    }

    private static function uploaded_file_is_image(string $tmpName): bool
    {
        if ($tmpName === '' || !is_file($tmpName)) {
            return false;
        }

        if (function_exists('exif_imagetype') && @exif_imagetype($tmpName) !== false) {
            return true;
        }

        if (@getimagesize($tmpName) !== false) {
            return true;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpName);
                unset($finfo); // finfo_close() ist seit PHP 8.5 veraltet; die Ausgabe würde die JSON-Antwort des Uploads zerstören.
                return is_string($mime) && str_starts_with(strtolower($mime), 'image/');
            }
        }

        return false;
    }

    private static function svg_upload_is_safe(string $tmpName): bool
    {
        $content = (string) @file_get_contents($tmpName, false, null, 0, 1024 * 1024);
        if ($content === '' || stripos($content, '<svg') === false) {
            return false;
        }

        if (preg_match('#<(script|foreignObject|iframe|object|embed|form|input|button|textarea|select|link)\b#i', $content) === 1) {
            return false;
        }

        if (preg_match('/\s+on[a-z]+\s*=/i', $content) === 1) {
            return false;
        }

        if (preg_match('/(?:href|xlink:href|src)\s*=\s*(["\'])\s*(?:javascript:|data:text\/html)/i', $content) === 1) {
            return false;
        }

        return true;
    }

    private static function sanitize_upload_basename(string $baseName): string
    {
        $baseName = strtolower(trim($baseName));
        $baseName = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName) ?? '';
        $baseName = trim($baseName, '-_');
        if ($baseName === '') {
            $baseName = 'beratung-bild';
        }

        return function_exists('mb_substr') ? mb_substr($baseName, 0, 80) : substr($baseName, 0, 80);
    }

    private static function unique_upload_filename(string $targetDir, string $baseName, string $extension): string
    {
        $extension = strtolower(trim($extension, '.'));
        $candidate = $baseName . '.' . $extension;
        $counter = 1;
        while (is_file($targetDir . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $baseName . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    private static function field(string $label, string $name, string $value, string $type = 'text', string $hint = ''): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><input type="' . self::esc($type) . '" name="' . self::esc($name) . '" value="' . self::esc($value) . '">';
        if ($hint !== '') {
            echo '<small class="beratung-field__hint">' . self::esc($hint) . '</small>';
        }
        echo '</label>';
    }

    private static function textarea(string $label, string $name, string $value, int $rows): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><textarea name="' . self::esc($name) . '" rows="' . $rows . '">' . self::esc($value) . '</textarea></label>';
    }

    /** @param array<string,string> $options */
    private static function select(string $label, string $name, string $value, array $options): void
    {
        echo '<label class="beratung-field"><span>' . self::esc($label) . '</span><select name="' . self::esc($name) . '">';
        foreach ($options as $key => $optionLabel) {
            echo '<option value="' . self::esc($key) . '"' . ($key === $value ? ' selected' : '') . '>' . self::esc($optionLabel) . '</option>';
        }
        echo '</select></label>';
    }

    private static function checkbox(string $label, string $name, bool $checked): void
    {
        echo '<input type="hidden" name="' . self::esc($name) . '" value="0"><label class="beratung-check"><input type="checkbox" name="' . self::esc($name) . '" value="1"' . ($checked ? ' checked' : '') . '> <span>' . self::esc($label) . '</span></label>';
    }

    private static function render_notice(string $notice, string $error): void
    {
        if ($notice !== '') {
            echo '<div class="beratung-notice is-success">' . self::esc($notice) . '</div>';
        }
        if ($error !== '') {
            echo '<div class="beratung-notice is-error">' . self::esc($error) . '</div>';
        }
    }

    private static function import_json_from_request(): string
    {
        $file = $_FILES['import_file'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $content = file_get_contents((string) $file['tmp_name']);
            return is_string($content) ? $content : '';
        }
        return (string) ($_POST['import_json'] ?? '');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function strip_import_images(array $payload): array
    {
        foreach (['image_url', 'logo_url', 'background_image_url', 'trust_image_url', 'og_image', 'twitter_image'] as $key) {
            if (array_key_exists($key, $payload)) { $payload[$key] = ''; }
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) { $payload[$key] = self::strip_import_images($value); }
        }
        return $payload;
    }

    private static function notice_from_query(): void
    {
        if (!empty($_GET['updated'])) {
            self::render_notice('Aktion erfolgreich ausgeführt.', '');
        }
        if (!empty($_GET['error'])) {
            self::render_notice('', 'Die Aktion konnte nicht ausgeführt werden.');
        }
    }

    /** @return array<string,callable|null> */
    private static function resolve_callbacks(): array
    {
        $callbacks = [];
        foreach (self::PAGE_RENDERERS as $slug => $method) {
            $callbacks[$slug] = is_callable([self::class, $method]) ? [self::class, $method] : null;
        }
        return $callbacks;
    }

    private static function admin_url(string $pageSlug, array $params = []): string
    {
        self::ensure_shared_contract_loaded();
        $pageSlug = self::normalize_slug($pageSlug);
        if ($pageSlug === self::MENU_SLUG || in_array($pageSlug, self::UNLISTED_PAGE_SLUGS, true)) {
            if ($pageSlug !== self::MENU_SLUG && $pageSlug !== self::DEFAULT_PAGE_SLUG) {
                $params = ['view' => $pageSlug] + $params;
            }
            $pageSlug = self::MENU_SLUG;
        }

        $url = '/admin/plugins/' . self::MENU_SLUG . '/' . rawurlencode($pageSlug);
        return $params !== [] ? $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    private static function public_landingpage_url(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }
        $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
        return $base . '/beratung/' . rawurlencode($slug);
    }

    private static function redirect(string $pageSlug, array $params = []): never
    {
        header('Location: ' . self::admin_url($pageSlug, $params), true, 302);
        exit;
    }

    private static function ensure_shared_contract_loaded(): void
    {
        if (function_exists('cms_plugin_admin_dispatch_page')) {
            return;
        }
        $shared = dirname(CMS_BERATUNG_PLUGIN_DIR) . '/shared/admin/plugin-admin-contract.php';
        if (is_file($shared)) {
            require_once $shared;
        }
    }

    private static function normalize_slug(string $slug): string
    {
        if (function_exists('cms_plugin_admin_normalize_slug')) {
            return cms_plugin_admin_normalize_slug($slug);
        }
        return trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($slug)), '-');
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

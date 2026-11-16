<?php
/**
 * Router-/Controller-Klasse für 365NET Experts & Companie.
 *
 * @package CMS_365NET_ExpertsAndCompanie
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Experts_And_Companie_Post_Type
{
    private const UPLOAD_MAX_BYTES = 10485760;

    /** Endung → erlaubte finfo-MIME-Typen. */
    private const UPLOAD_MIME = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'svg' => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
    ];

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

    /** @param mixed $router */
    public function registerRoutes($router): void
    {
        if (!is_object($router) || !method_exists($router, 'addRoute')) {
            return;
        }

        $router->addRoute('GET', '/experts', [$this, 'expertsPage']);
        $router->addRoute('GET', '/experts/:id', [$this, 'singleExpert']);
        $router->addRoute('GET', '/companies', [$this, 'companiesPage']);
        $router->addRoute('GET', '/companies/:id', [$this, 'singleCompany']);
        $router->addRoute('GET', '/experts-companie', [$this, 'archivePage']);
        $router->addRoute('GET', '/experts-and-companie', [$this, 'archivePage']);
        $router->addRoute('GET', '/experts-companies', [$this, 'archivePage']);
        // Design-Variablen der Publicseiten als Stylesheet (CSP: keine Inline-Styles).
        $router->addRoute('GET', '/experts-companie/design-vars', [$this, 'designVarsStylesheet']);
        $router->addRoute('POST', '/api/experts-companie/media-upload', [$this, 'adminMediaUpload']);

        // Alte Admin-Adressen (bis 1.2.0) → Plugin-Seiten des AdminRouters (Lesezeichen, offene Formulare).
        $legacy = [
            ['GET', '/admin/experts-companie', '', []],
            ['GET', '/admin/experts-companie/settings', CMS_365NET_Experts_And_Companie_Admin::PAGE_SETTINGS, []],
            ['GET', '/admin/experts-companie/experts', CMS_365NET_Experts_And_Companie_Admin::PAGE_EXPERTS, []],
            ['GET', '/admin/experts-companie/experts/new', CMS_365NET_Experts_And_Companie_Admin::PAGE_EXPERT_NEW, []],
            ['GET', '/admin/experts-companie/companies', CMS_365NET_Experts_And_Companie_Admin::PAGE_COMPANIES, []],
            ['GET', '/admin/experts-companie/companies/new', CMS_365NET_Experts_And_Companie_Admin::PAGE_COMPANY_NEW, []],
            ['POST', '/admin/experts-companie/seed-refresh', '', ['excomp_action' => 'seed_refresh']],
            ['POST', '/admin/experts-companie/settings/save', CMS_365NET_Experts_And_Companie_Admin::PAGE_SETTINGS, ['excomp_action' => 'save_settings']],
            ['POST', '/admin/experts-companie/experts/save', CMS_365NET_Experts_And_Companie_Admin::PAGE_EXPERTS, ['excomp_action' => 'save']],
            ['POST', '/admin/experts-companie/companies/save', CMS_365NET_Experts_And_Companie_Admin::PAGE_COMPANIES, ['excomp_action' => 'save']],
        ];
        foreach ($legacy as [$method, $path, $page, $query]) {
            $router->addRoute($method, $path, static function () use ($page, $query): void {
                self::legacyAdminRedirect($page, $query);
            });
        }
        $router->addRoute('GET', '/admin/experts-companie/experts/edit/:id', static function (string $id = ''): void {
            self::legacyAdminRedirect(CMS_365NET_Experts_And_Companie_Admin::PAGE_EXPERTS, ['action' => 'edit', 'id' => (int) $id]);
        });
        $router->addRoute('GET', '/admin/experts-companie/companies/edit/:id', static function (string $id = ''): void {
            self::legacyAdminRedirect(CMS_365NET_Experts_And_Companie_Admin::PAGE_COMPANIES, ['action' => 'edit', 'id' => (int) $id]);
        });
    }

    /**
     * Alte Adresse umleiten: GET 301, POST 307 (Formulardaten bleiben, CSRF/Capability prüft die Zielseite).
     *
     * @param array<string, scalar> $query
     */
    private static function legacyAdminRedirect(string $page, array $query): never
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'GET') {
            foreach (['q', 'sort', 'saved', 'seeded'] as $key) {
                if (isset($_GET[$key]) && is_scalar($_GET[$key]) && !isset($query[$key])) {
                    $query[$key] = (string) $_GET[$key];
                }
            }
        }
        $target = CMS_365NET_Experts_And_Companie_Admin::url($page, $query);
        $target = class_exists('CMS\\Security') ? CMS\Security::safeLocalPath($target, '/admin') : $target;
        header('Location: ' . rtrim((string) SITE_URL, '/') . $target, true, $method === 'GET' ? 301 : 307);
        exit;
    }

    /** Stylesheet mit den Design-Variablen aus den Einstellungen (validierte Werte, lange cachebar). */
    public function designVarsStylesheet(): never
    {
        $css = class_exists('CMS_365NET_Experts_And_Companie', false) ? CMS_365NET_Experts_And_Companie::designVarsCss() : '';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/css; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        $requested = (string) ($_GET['v'] ?? '');
        $hash = substr(hash('sha256', $css), 0, 12);
        header($requested === $hash ? 'Cache-Control: public, max-age=31536000, immutable' : 'Cache-Control: public, max-age=300');
        echo $css;
        exit;
    }

    public function addMenuItem(): void
    {
        $settings = CMS_365NET_Experts_And_Companie_Database::instance()->getSettings();
        if (($settings['show_nav_link'] ?? '0') !== '1') {
            return;
        }

        $label = trim((string) ($settings['nav_label'] ?? 'Experts & Companies'));
        if ($label === '') {
            $label = 'Experts & Companies';
        }

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $isActive = str_starts_with($path, '/experts')
            || str_starts_with($path, '/companies')
            || str_starts_with($path, '/experts-companie')
            || str_starts_with($path, '/experts-and-companie')
            || str_starts_with($path, '/experts-companies');

        echo '<a href="' . htmlspecialchars(rtrim((string) SITE_URL, '/') . '/experts', ENT_QUOTES, 'UTF-8') . '" class="nav-link ' . ($isActive ? 'active' : '') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    public function expertsPage(): void
    {
        $this->renderPublicByType('experts', 'archive-experts');
    }

    public function companiesPage(): void
    {
        $this->renderPublicByType('companies', 'archive-companies');
    }

    public function archivePage(): void
    {
        $this->renderPublicByType('all', 'archive-experts-companie');
    }

    public function singleExpert(string $id = ''): void
    {
        $expertId = $this->parsePositiveId($id);
        if ($expertId <= 0) {
            $this->renderSimpleError('Expert nicht gefunden.');
            return;
        }

        $db = CMS_365NET_Experts_And_Companie_Database::instance();
        $expert = $db->getExpertPublicById($expertId);
        if ($expert === null) {
            $this->renderSimpleError('Expert nicht gefunden.');
            return;
        }

        $linkedCompany = null;
        $linkedCompanyId = (int) ($expert->linked_company_id ?? 0);
        if ($linkedCompanyId > 0) {
            $linkedCompany = $db->getCompanyPublicById($linkedCompanyId);
        }

        $linkedSpeaker = null;
        $linkedSpeakerId = (int) ($expert->linked_speaker_id ?? 0);
        if ($linkedSpeakerId > 0) {
            $linkedSpeaker = $db->getPublishedLinkedSpeaker($linkedSpeakerId);
        }
        if ($linkedSpeaker === null) {
            $linkedSpeaker = $db->getSpeakerByLinkedExpert($expertId);
        }
        if ($linkedSpeaker === null && $linkedCompanyId > 0) {
            $companySpeakers = $db->getSpeakersByLinkedCompany($linkedCompanyId, 1);
            if ($companySpeakers !== []) {
                $linkedSpeaker = $companySpeakers[0];
            }
        }
        if ($linkedSpeaker === null && $linkedCompany !== null) {
            $companyLinkedSpeakerId = (int) ($linkedCompany->linked_speaker_id ?? 0);
            if ($companyLinkedSpeakerId > 0) {
                $linkedSpeaker = $db->getPublishedLinkedSpeaker($companyLinkedSpeakerId);
            }
        }

        $fullName = trim((string) ($expert->first_name ?? '') . ' ' . (string) ($expert->last_name ?? ''));
        $this->setMeta(
            $fullName . (trim((string) ($expert->position ?? '')) !== '' ? ' – ' . trim((string) $expert->position) : ''),
            (string) ($expert->biography ?? ''),
            '/experts/' . $expertId,
            true,
            'Person'
        );

        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        CMS_365NET_Experts_And_Companie_Template_Loader::instance()->render('single-expert', [
            'expert' => $expert,
            'linkedCompany' => $linkedCompany,
            'linkedSpeaker' => $linkedSpeaker,
            'settings' => $db->getSettings(),
        ]);
        $theme->getFooter();
    }

    public function singleCompany(string $id = ''): void
    {
        $companyId = $this->parsePositiveId($id);
        if ($companyId <= 0) {
            $this->renderSimpleError('Company nicht gefunden.');
            return;
        }

        $db = CMS_365NET_Experts_And_Companie_Database::instance();
        $company = $db->getCompanyPublicById($companyId);
        if ($company === null) {
            $this->renderSimpleError('Company nicht gefunden.');
            return;
        }

        $linkedExpert = null;
        $linkedExpertId = (int) ($company->linked_expert_id ?? 0);
        if ($linkedExpertId > 0) {
            $linkedExpert = $db->getExpertPublicById($linkedExpertId);
        }

        $linkedSpeaker = null;
        $linkedSpeakerId = (int) ($company->linked_speaker_id ?? 0);
        if ($linkedSpeakerId > 0) {
            $linkedSpeaker = $db->getPublishedLinkedSpeaker($linkedSpeakerId);
        }

        if ($linkedSpeaker === null && $linkedExpertId > 0) {
            $linkedSpeaker = $db->getSpeakerByLinkedExpert($linkedExpertId);
        }

        $linkedExperts = $db->getExpertsByLinkedCompany($companyId, 8);
        if ($linkedExpert !== null) {
            $hasPrimaryExpert = false;
            foreach ($linkedExperts as $linkedExpertRow) {
                if ((int) ($linkedExpertRow->id ?? 0) === (int) ($linkedExpert->id ?? 0)) {
                    $hasPrimaryExpert = true;
                    break;
                }
            }
            if (!$hasPrimaryExpert) {
                array_unshift($linkedExperts, $linkedExpert);
            }
        }

        $linkedSpeakers = $db->getSpeakersByLinkedCompany($companyId, 8);
        if ($linkedSpeaker !== null) {
            $hasPrimarySpeaker = false;
            foreach ($linkedSpeakers as $linkedSpeakerRow) {
                if ((int) ($linkedSpeakerRow->id ?? 0) === (int) ($linkedSpeaker->id ?? 0)) {
                    $hasPrimarySpeaker = true;
                    break;
                }
            }
            if (!$hasPrimarySpeaker) {
                array_unshift($linkedSpeakers, $linkedSpeaker);
            }
        }

        $this->setMeta((string) ($company->name ?? 'Company'), (string) ($company->description ?? ''), '/companies/' . $companyId, true, 'Organization');

        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        CMS_365NET_Experts_And_Companie_Template_Loader::instance()->render('single-company', [
            'company' => $company,
            'linkedExpert' => $linkedExpert,
            'linkedSpeaker' => $linkedSpeaker,
            'linkedExperts' => $linkedExperts,
            'linkedSpeakers' => $linkedSpeakers,
            'settings' => $db->getSettings(),
        ]);
        $theme->getFooter();
    }

    private function renderPublicByType(string $routeType, string $template): void
    {
        if (!class_exists('CMS_365NET_Experts_And_Companie_Database')) {
            $this->renderSimpleError('Die Datenbankverbindung ist derzeit nicht verfügbar.');
            return;
        }

        $search = $this->cleanText((string) ($_GET['q'] ?? ''), 120);
        $city = $this->cleanText((string) ($_GET['city'] ?? ''), 80);
        $type = $routeType;
        if ($routeType === 'all') {
            $type = $this->allowValue((string) ($_GET['type'] ?? 'all'), ['all', 'experts', 'companies']) ?? 'all';
        }

        $db = CMS_365NET_Experts_And_Companie_Database::instance();
        $settings = $db->getSettings();
        $requestedPage = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $experts = [];
        $companies = [];
        $pagination = [
            'current' => 1,
            'total' => 0,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];

        if ($type === 'all' || $type === 'experts') {
            $expertsLimit = 30;
            $expertsOffset = 0;

            if ($routeType === 'experts') {
                $expertsTotal = max(0, $db->countExpertsPublic($search, $city));
                $expertsTotalPages = max(1, (int) ceil($expertsTotal / $perPage));
                $expertsPage = min($requestedPage, $expertsTotalPages);
                $expertsLimit = $perPage;
                $expertsOffset = ($expertsPage - 1) * $expertsLimit;

                $pagination = [
                    'current' => $expertsPage,
                    'total' => $expertsTotal,
                    'per_page' => $perPage,
                    'total_pages' => $expertsTotalPages,
                ];
            }

            $experts = $db->getExpertsPublic($search, $city, $expertsLimit, $expertsOffset);

            // Verknüpfungen gebündelt laden (statt bis zu drei Abfragen je Karte).
            $speakerIds = [];
            $companyIds = [];
            $expertIds = [];
            foreach ($experts as $expert) {
                $expertIds[] = (int) ($expert->id ?? 0);
                $speakerIds[] = (int) ($expert->linked_speaker_id ?? 0);
                $companyIds[] = (int) ($expert->linked_company_id ?? 0);
            }
            $speakersById = $db->getSpeakersByIds($speakerIds);
            $speakersByExpert = $db->getSpeakersByLinkedExpertIds($expertIds);
            $companiesById = $db->getCompaniesByIds($companyIds);

            foreach ($experts as $expert) {
                $expertId = (int) ($expert->id ?? 0);
                $expert->detail_url = rtrim((string) SITE_URL, '/') . '/experts/' . $expertId;
                $expert->linked_company = $companiesById[(int) ($expert->linked_company_id ?? 0)] ?? null;
                $expert->linked_speaker = $speakersById[(int) ($expert->linked_speaker_id ?? 0)] ?? ($speakersByExpert[$expertId] ?? null);
            }
        }

        if ($type === 'all' || $type === 'companies') {
            $companiesLimit = 30;
            $companiesOffset = 0;

            if ($routeType === 'companies') {
                $companiesTotal = max(0, $db->countCompaniesPublic($search, $city));
                $companiesTotalPages = max(1, (int) ceil($companiesTotal / $perPage));
                $companiesPage = min($requestedPage, $companiesTotalPages);
                $companiesLimit = $perPage;
                $companiesOffset = ($companiesPage - 1) * $companiesLimit;

                $pagination = [
                    'current' => $companiesPage,
                    'total' => $companiesTotal,
                    'per_page' => $perPage,
                    'total_pages' => $companiesTotalPages,
                ];
            }

            $companies = $db->getCompaniesPublic($search, $city, $companiesLimit, $companiesOffset);

            $companyIds = [];
            $expertIds = [];
            $speakerIds = [];
            foreach ($companies as $company) {
                $companyIds[] = (int) ($company->id ?? 0);
                $expertIds[] = (int) ($company->linked_expert_id ?? 0);
                $speakerIds[] = (int) ($company->linked_speaker_id ?? 0);
            }
            $expertsById = $db->getExpertsByIds($expertIds);
            $speakersById = $db->getSpeakersByIds($speakerIds);
            $speakersByExpert = $db->getSpeakersByLinkedExpertIds($expertIds);
            $expertsByCompany = $db->getExpertsByLinkedCompanyIds($companyIds, 3);
            $speakersByCompany = $db->getSpeakersByLinkedCompanyIds($companyIds, 3);

            foreach ($companies as $company) {
                $companyId = (int) ($company->id ?? 0);
                $linkedExpertId = (int) ($company->linked_expert_id ?? 0);
                $company->detail_url = rtrim((string) SITE_URL, '/') . '/companies/' . $companyId;
                $company->linked_expert = $expertsById[$linkedExpertId] ?? null;
                $company->linked_speaker = $speakersById[(int) ($company->linked_speaker_id ?? 0)] ?? ($speakersByExpert[$linkedExpertId] ?? null);
                $company->linked_experts = self::prependUnique($expertsByCompany[$companyId] ?? [], $company->linked_expert);
                $company->linked_speakers = self::prependUnique($speakersByCompany[$companyId] ?? [], $company->linked_speaker);
            }
        }

        $this->setArchiveMeta($routeType, $settings, $search !== '' || $city !== '' || $requestedPage > 1 || ($routeType === 'all' && $type !== 'all'));

        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        CMS_365NET_Experts_And_Companie_Template_Loader::instance()->render($template, [
            'experts' => $experts,
            'companies' => $companies,
            'pagination' => $pagination,
            'settings' => $settings,
            'filters' => [
                'q' => $search,
                'city' => $city,
                'type' => $type,
            ],
        ]);
        $theme->getFooter();
    }

    /** @param array<string,mixed> $payload */
    private function jsonResponse(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{"success":0}';
        exit;
    }

    /**
     * Bild-Upload für Profilbilder/Logos (Admin + CSRF `editorjs_media`). Prüft Endung, finfo-MIME passend zur
     * Endung, Bildinhalt (Raster) bzw. aktive Inhalte (SVG), Größe; Dateiname mit Zufallsanteil.
     */
    public function adminMediaUpload(): void
    {
        if (!class_exists('CMS\\Auth') || !CMS\Auth::instance()->isAdmin()
            || (function_exists('current_user_can') && !current_user_can('manage_options'))) {
            $this->jsonResponse(['success' => 0, 'message' => 'Nicht berechtigt.'], 403);
        }

        $token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $valid = class_exists('CMS\\Security') && CMS\Security::instance()->verifyPersistentToken($token, 'editorjs_media');
        if (!$valid) {
            $this->jsonResponse(['success' => 0, 'message' => 'Sicherheitsüberprüfung fehlgeschlagen.'], 403);
        }

        if (!defined('UPLOAD_PATH')) {
            $this->jsonResponse(['success' => 0, 'message' => 'Upload-Verzeichnis ist nicht konfiguriert.'], 500);
        }

        $file = $_FILES['file'] ?? ($_FILES['image'] ?? null);
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $this->jsonResponse(['success' => 0, 'message' => 'Keine gültige Bilddatei empfangen.'], 400);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::UPLOAD_MAX_BYTES || (int) filesize($tmpName) > self::UPLOAD_MAX_BYTES) {
            $this->jsonResponse(['success' => 0, 'message' => 'Die Bilddatei ist leer oder größer als 10 MB.'], 400);
        }

        $originalName = (string) ($file['name'] ?? 'profilbild');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (!isset(self::UPLOAD_MIME[$extension])) {
            $this->jsonResponse(['success' => 0, 'message' => 'Erlaubt sind JPG, PNG, GIF, WebP, AVIF und SVG.'], 400);
        }

        $mime = $this->detectMime($tmpName);
        if ($mime === '' || !in_array($mime, self::UPLOAD_MIME[$extension], true)) {
            $this->jsonResponse(['success' => 0, 'message' => 'Dateiinhalt passt nicht zur Endung .' . $extension . '.'], 400);
        }
        if ($extension === 'svg' ? !$this->svgUploadIsSafe($tmpName) : @getimagesize($tmpName) === false) {
            $this->jsonResponse(['success' => 0, 'message' => $extension === 'svg' ? 'SVG enthält nicht erlaubte aktive Inhalte.' : 'Die Datei ist kein gültiges Bild.'], 400);
        }

        $targetDir = rtrim((string) UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'experts-companie';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $this->jsonResponse(['success' => 0, 'message' => 'Der Ordner /uploads/experts-companie konnte nicht erstellt werden.'], 500);
        }

        $baseName = $this->sanitizeUploadBasename((string) pathinfo($originalName, PATHINFO_FILENAME));
        $filename = $this->uniqueUploadFilename($targetDir, $baseName . '-' . bin2hex(random_bytes(4)), $extension);
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            $this->jsonResponse(['success' => 0, 'message' => 'Die Datei konnte nicht gespeichert werden.'], 500);
        }
        @chmod($targetPath, 0644);

        $relativePath = 'experts-companie/' . $filename;
        $url = '/uploads/' . $relativePath;
        $this->jsonResponse([
            'success' => 1,
            'url' => $url,
            'path' => $relativePath,
            'file' => ['url' => $url, 'path' => $relativePath, 'name' => $filename, 'size' => filesize($targetPath) ?: $size],
        ]);
    }

    /** MIME-Typ per finfo-Objekt (wird automatisch freigegeben, PHP 8.5-konform). */
    private function detectMime(string $tmpName): string
    {
        if ($tmpName === '' || !is_file($tmpName) || !class_exists('finfo')) {
            return '';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function svgUploadIsSafe(string $tmpName): bool
    {
        $content = (string) @file_get_contents($tmpName, false, null, 0, self::UPLOAD_MAX_BYTES);
        if ($content === '' || stripos($content, '<svg') === false) {
            return false;
        }
        $checks = [
            '#<(script|foreignObject|iframe|object|embed|form|input|button|textarea|select|link|meta|base|audio|video|animate|set|handler|listener)\\b#i',
            '/<!ENTITY|<!DOCTYPE[^>]*\\[/i',                            // XXE / Entity-Expansion
            '/<\\?xml-stylesheet/i',
            '/\\son[a-z]+\\s*=/i',                                       // Event-Handler
            '/(javascript|vbscript)\\s*:/i',
            "/(?:href|xlink:href|src)\\s*=\\s*[\"']\\s*(?!#|data:image\\/(?:png|jpe?g|gif|webp);)[a-z][a-z0-9+.-]*:/i", // externe/aktive URLs
            '/@import|url\\(\\s*[\"\']?\\s*(?!#|data:image\\/)[a-z][a-z0-9+.-]*:/i',
        ];
        foreach ($checks as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeUploadBasename(string $baseName): string
    {
        $baseName = strtolower(trim($baseName));
        $baseName = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName) ?? '';
        $baseName = trim($baseName, '-_');
        if ($baseName === '') {
            $baseName = 'profilbild';
        }
        return function_exists('mb_substr') ? (string) mb_substr($baseName, 0, 80, 'UTF-8') : substr($baseName, 0, 80);
    }

    private function uniqueUploadFilename(string $targetDir, string $baseName, string $extension): string
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




    private function cleanText(string $value, int $maxLen = 120): string
    {
        $value = trim(strip_tags($value));
        return function_exists('mb_substr') ? (string) mb_substr($value, 0, $maxLen, 'UTF-8') : substr($value, 0, $maxLen);
    }

    private function allowValue(string $value, array $allowed): ?string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function parsePositiveId(string $value): int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return 0;
        }

        return max(0, (int) $value);
    }


    /**
     * Manuell verknüpften Eintrag vorn einreihen, falls er noch nicht in der Liste steht.
     *
     * @param array<int, object> $list
     * @return array<int, object>
     */
    private static function prependUnique(array $list, ?object $item): array
    {
        if ($item === null) {
            return $list;
        }
        foreach ($list as $row) {
            if ((int) ($row->id ?? 0) === (int) ($item->id ?? 0)) {
                return $list;
            }
        }
        array_unshift($list, $item);

        return $list;
    }

    /** @param array<string, string> $settings */
    private function setArchiveMeta(string $routeType, array $settings, bool $filtered): void
    {
        [$path, $titleKey, $descKey, $fallback] = match ($routeType) {
            'experts' => ['/experts', 'experts_archive_title', 'experts_archive_description', 'Experts'],
            'companies' => ['/companies', 'companies_archive_title', 'companies_archive_description', 'Companies'],
            default => ['/experts-companie', 'hub_archive_title', 'hub_archive_description', 'Experts & Companies'],
        };
        $title = trim((string) ($settings[$titleKey] ?? '')) ?: $fallback;
        $this->setMeta($title, (string) ($settings[$descKey] ?? ''), $path, !$filtered);
    }

    /** Titel, Beschreibung, Canonical und Robots der Plugin-Seite (SEOService, falls vorhanden). */
    private function setMeta(string $title, string $description, string $path, bool $index = true, string $schemaType = ''): void
    {
        if (!class_exists('CMS\\Services\\SEOService')) {
            return;
        }
        try {
            $meta = [
                'title' => $title,
                'description' => class_exists('CMS_365NET_Experts_And_Companie_Integrations', false) ? CMS_365NET_Experts_And_Companie_Integrations::excerpt($description, 160) : $description,
                'canonical_url' => rtrim((string) SITE_URL, '/') . $path,
                'robots_index' => $index,
                'robots_follow' => true,
            ];
            if ($schemaType !== '') {
                $meta['schema_type'] = $schemaType;
                $meta['og_type'] = $schemaType === 'Person' ? 'profile' : 'website';
            }
            CMS\Services\SEOService::getInstance()->setRequestMeta($meta);
        } catch (\Throwable $e) {
            error_log('CMS 365NET Experts & Companies [SEO]: ' . $e->getMessage());
        }
    }

    private function renderSimpleError(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }
        if (class_exists('CMS\\Services\\SEOService')) {
            try {
                CMS\Services\SEOService::getInstance()->setRequestMeta(['title' => 'Nicht gefunden', 'robots_index' => false, 'robots_follow' => false]);
            } catch (\Throwable) {
            }
        }
        $theme = CMS\ThemeManager::instance();
        $theme->getHeader();
        echo '<main class="cms-excomp-public"><div class="cms-excomp-container"><div class="cms-excomp-empty"><h2>Hinweis</h2><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div></div></main>';
        $theme->getFooter();
    }
}

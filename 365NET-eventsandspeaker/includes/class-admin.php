<?php
/**
 * Admin-UI für 365NET Events & Speaker.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_365NET_Events_Admin
{
    private static ?self $instance = null;
    private static bool $fallbackAssetsPrinted = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->loadSharedAdminContract();
        if (class_exists('CMS\\Hooks')) {
            CMS\Hooks::addAction('cms_admin_menu', [$this, 'registerAdminMenu'], 10);
            // Einheitliches Plugin-Layout (365CMS 4.0.0): Kopfzeile mit KPI-Karten, links das Plugin-Menü,
            // rechts der Inhalt; die Übersicht liefert adminDashboard().
            if (is_file(__DIR__ . '/phinit-admin-nav.php')) {
                require_once __DIR__ . '/phinit-admin-nav.php';
                \PhinIT_Plugin_Admin_Nav::adopt('365netevents', [
                    'icon' => 'ti ti-calendar-event',
                    'title' => 'PHINIT | Events & Speaker',
                    'version' => defined('CMS_365NET_EVENTS_VERSION') ? (string) CMS_365NET_EVENTS_VERSION : '',
                    'description' => 'Events, Messen und Speaker für die öffentlichen Seiten /events und /speakers pflegen.',
                    'stats' => [self::class, 'adminStats'],
                    'dashboard' => [self::class, 'adminDashboard'],
                    'badges' => [self::class, 'adminBadges'],
                    'icons' => [
                        '365netevents-events' => 'ti ti-calendar-event',
                        '365netevents-speakers' => 'ti ti-microphone',
                        '365netevents-taxonomies' => 'ti ti-tags',
                        '365netevents-settings' => 'ti ti-settings',
                    ],
                    'descriptions' => self::sectionDescriptions(),
                ]);
            }
            CMS\Hooks::addFilter('admin_menu_items', [$this, 'addMenuItem'], 10);
        }
    }

    private function loadSharedAdminContract(): void
    {
        $contractFile = dirname(__DIR__, 2) . '/shared/admin/plugin-admin-contract.php';
        if (is_file($contractFile)) {
            require_once $contractFile;
        }

        $menuFile = ABSPATH . 'admin/partials/admin-menu.php';
        if (is_file($menuFile) && !function_exists('renderAdminLayoutStart')) {
            require_once $menuFile;
        }
    }

    public function registerAdminMenu(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page('365 | Events & Speaker', 'PHINIT | Events & Speaker', 'manage_options', '365netevents', [self::class, 'bridgeDashboard'], '📅', 45);
        if (function_exists('add_submenu_page')) {
            // Erster Bereich = Übersicht (/admin/365netevents/dashboard); die Event-Liste bleibt unter /admin/365netevents.
            add_submenu_page('365netevents', 'Übersicht', 'Übersicht', 'manage_options', '365netevents', [self::class, 'bridgeDashboard']);
            add_submenu_page('365netevents', 'Events', 'Events', 'manage_options', '365netevents-events', [self::class, 'bridgeEvents']);
            add_submenu_page('365netevents', 'Speaker', 'Speaker', 'manage_options', '365netevents-speakers', [self::class, 'bridgeSpeakers']);
            add_submenu_page('365netevents', 'Kategorien & Tags', 'Kategorien & Tags', 'manage_options', '365netevents-taxonomies', [self::class, 'bridgeTaxonomies']);
            add_submenu_page('365netevents', 'Einstellungen', 'Einstellungen', 'manage_options', '365netevents-settings', [self::class, 'bridgeSettings']);
        }
    }

    public static function bridgeDashboard(): void
    {
        CMS\Router::instance()->redirect('/admin/365netevents/dashboard');
    }

    public static function bridgeEvents(): void
    {
        CMS\Router::instance()->redirect('/admin/365netevents');
    }

    public static function bridgeSpeakers(): void
    {
        CMS\Router::instance()->redirect('/admin/365netevents/speakers');
    }

    public static function bridgeSettings(): void
    {
        CMS\Router::instance()->redirect('/admin/365netevents/settings');
    }

    public static function bridgeTaxonomies(): void
    {
        CMS\Router::instance()->redirect('/admin/365netevents/taxonomies');
    }

    public function addMenuItem(array $items): array
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $items[] = [
            'type' => 'item',
            'slug' => '365netevents',
            'label' => 'PHINIT | Events & Speaker',
            'icon' => '📅',
            'url' => '/admin/365netevents/dashboard',
            'active' => str_starts_with($path, '/admin/365netevents'),
        ];

        return $items;
    }

    /** @return array<string, string> Kurzbeschreibungen der Bereiche (Dashboard-Kacheln) */
    private static function sectionDescriptions(): array
    {
        return [
            '365netevents-events' => 'Anstehende und vergangene Events suchen, bearbeiten und Verknüpfungen aktualisieren.',
            '365netevents-speakers' => 'Speaker-Profile pflegen und mit Experts & Companies verknüpfen.',
            '365netevents-taxonomies' => 'Dropdown-Werte für Kategorien, Arten, Klassen und Tags pflegen.',
            '365netevents-settings' => 'Texte, Layout und Farben der öffentlichen Eventseiten sowie Backfill-Werkzeuge.',
        ];
    }

    /**
     * Kennzahlen für Seitenkopf und Dashboard (PhinIT-Admin-Layout). Nur COUNT-Abfragen; ohne Datenbank leer.
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
            $upcoming = $db->countEvents(['date_mode' => 'future', 'from' => date('Y-m-d')]);
            $total = $db->countEvents();
            $speakers = $db->countSpeakers();
            $drafts = $db->countEvents(['status' => 'draft']);

            $stats[] = ['label' => 'Kommende Events', 'value' => $upcoming, 'icon' => 'ti ti-calendar-event', 'tone' => $upcoming > 0 ? 'success' : 'muted', 'url' => '/admin/365netevents'];
            $stats[] = ['label' => 'Events gesamt', 'value' => $total, 'icon' => 'ti ti-calendar', 'url' => '/admin/365netevents?past=1', 'hint' => 'Anstehende und vergangene Events'];
            $stats[] = ['label' => 'Speaker', 'value' => $speakers, 'icon' => 'ti ti-microphone', 'tone' => 'info', 'url' => '/admin/365netevents/speakers'];
            $stats[] = ['label' => 'Entwürfe', 'value' => $drafts, 'icon' => 'ti ti-pencil', 'tone' => $drafts > 0 ? 'warning' : 'muted', 'hint' => 'Events mit Status „draft“ (nicht öffentlich)'];
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminStats]: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Übersicht (Layout-Vertrag 365CMS 4.0.0): Schnellaktionen, Status-/System-Check und zuletzt bearbeitete Events.
     *
     * @return array<string, mixed>
     */
    public static function adminDashboard(): array
    {
        $actions = [
            ['label' => 'Neues Event', 'url' => '/admin/365netevents/new', 'icon' => 'ti ti-plus', 'primary' => true, 'description' => 'Event, Messe oder Webinar anlegen und Speaker zuordnen.'],
            ['label' => 'Neuer Speaker', 'url' => '/admin/365netevents/speakers/new', 'icon' => 'ti ti-microphone', 'description' => 'Speaker-Profil anlegen und mit Experts & Companies verknüpfen.'],
            ['label' => 'Vergangene Events', 'url' => '/admin/365netevents?past=1', 'icon' => 'ti ti-history', 'description' => 'Zurückliegende Events nachpflegen oder archivieren.'],
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
            $checks[] = $db->isSchemaCurrent()
                ? ['label' => 'Datenbanktabellen', 'status' => 'ok', 'value' => '4 Tabellen aktuell']
                : ['label' => 'Datenbanktabellen', 'status' => 'warning', 'value' => 'Aktualisierung ausstehend', 'hint' => 'Wird beim nächsten Seitenaufruf bzw. bei der Plugin-Aktivierung angelegt und angehoben.'];

            $upcoming = $db->countEvents(['status' => 'published', 'date_mode' => 'future', 'from' => date('Y-m-d')]);
            $checks[] = $upcoming > 0
                ? ['label' => 'Öffentliche Eventseite', 'status' => 'ok', 'value' => $upcoming . ' kommende Events', 'url' => '/events']
                : ['label' => 'Öffentliche Eventseite', 'status' => 'warning', 'value' => 'keine kommenden Events', 'hint' => '/events zeigt derzeit nur den Leerzustand.', 'url' => '/admin/365netevents/new'];

            $drafts = $db->countEvents(['status' => 'draft']);
            $checks[] = $drafts > 0
                ? ['label' => 'Entwürfe', 'status' => 'info', 'value' => $drafts . ' nicht veröffentlicht', 'url' => '/admin/365netevents']
                : ['label' => 'Entwürfe', 'status' => 'ok', 'value' => 'keine offenen Entwürfe'];

            $speakers = $db->countSpeakers(['status' => 'published']);
            $checks[] = ['label' => 'Speaker-Verzeichnis', 'status' => $speakers > 0 ? 'ok' : 'warning', 'value' => $speakers . ' öffentlich', 'url' => '/speakers'];
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminDashboard]: ' . $e->getMessage());
            $checks[] = ['label' => 'Datenbank', 'status' => 'error', 'value' => 'Abfrage fehlgeschlagen', 'hint' => 'Details stehen im Fehlerprotokoll.'];
        }

        $checks[] = defined('CMS_365NET_EXCOMP_VERSION')
            ? ['label' => 'Experts & Companies', 'status' => 'ok', 'value' => 'Verknüpfung verfügbar']
            : ['label' => 'Experts & Companies', 'status' => 'muted', 'value' => 'Plugin nicht aktiv', 'hint' => 'Firmen und Experten lassen sich dann nicht verknüpfen.'];

        try {
            $checks[] = $db->isGoogleSearchConfigured()
                ? ['label' => 'Google-Suche (Speaker-Backfill)', 'status' => 'ok', 'value' => 'konfiguriert']
                : ['label' => 'Google-Suche (Speaker-Backfill)', 'status' => 'muted', 'value' => 'nicht konfiguriert', 'hint' => 'Optional; API-Key und Suchmaschinen-ID kommen aus Umgebungsvariablen (siehe README).'];

            foreach ($db->getEvents(['order' => 'updated_desc', 'limit' => 6]) as $event) {
                $updated = strtotime((string) ($event->updated_at ?? ''));
                $date = trim((string) ($event->date_label ?? ''));
                $published = (string) ($event->status ?? '') === 'published';
                $items[] = [
                    'title' => (string) ($event->title ?? ''),
                    'url' => '/admin/365netevents/edit/' . (int) ($event->id ?? 0),
                    'meta' => ($updated !== false ? 'geändert ' . date('d.m.Y, H:i', $updated) : 'geändert') . ($date !== '' ? ' · Termin ' . $date : ''),
                    'badge' => $published ? 'Veröffentlicht' : 'Entwurf',
                    'tone' => $published ? 'success' : 'warning',
                ];
            }
        } catch (\Throwable $e) {
            error_log('CMS 365NET Events [adminDashboard activity]: ' . $e->getMessage());
        }

        return [
            'actions' => $actions,
            'checks' => $checks,
            'activity' => [
                'heading' => 'Zuletzt bearbeitete Events',
                'items' => $items,
                'empty' => 'Noch keine Events vorhanden.',
                'more_url' => '/admin/365netevents',
                'more_label' => 'Alle Events',
            ],
        ];
    }

    /**
     * Zähler-Badges im Plugin-Menü (Option „badges“): Entwürfe bei „Events“.
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
        } catch (\Throwable $e) {
            return [];
        }

        return $drafts > 0 ? ['365netevents-events' => ['value' => $drafts, 'tone' => 'warning']] : [];
    }

    /** @param array<int, object> $upcomingEvents */
    public function renderDashboard(array $upcomingEvents): void
    {
        $this->start('365NET Events Dashboard', '365netevents');
        $site = rtrim((string) SITE_URL, '/');
        $layout = class_exists('PhinIT_Plugin_Admin_Nav', false) && method_exists('PhinIT_Plugin_Admin_Nav', 'handlesTabs');
        if (!$layout): ?>
        <div class="cms365-admin-header">
            <div><h2>Events &amp; Speaker</h2><p>Überblick über Events, Speaker und die öffentlichen Eventseiten.</p></div>
            <div class="cms365-admin-actions"><a class="btn btn-secondary" href="<?= htmlspecialchars($site . '/events', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Öffentlich</a><a class="btn btn-secondary" href="<?= htmlspecialchars($site . '/admin/365netevents/speakers/new', ENT_QUOTES, 'UTF-8') ?>">Neuer Speaker</a><a class="btn btn-primary" href="<?= htmlspecialchars($site . '/admin/365netevents/new', ENT_QUOTES, 'UTF-8') ?>">Neues Event</a></div>
        </div>
        <?php endif; ?>
        <?php $this->flash(); ?>
        <?php
        if ($layout) {
            // Kopfzeile mit KPI-Karten und Plugin-Menü setzt der Helfer; hier nur die Übersicht.
            echo \PhinIT_Plugin_Admin_Nav::dashboard('365netevents');
        }
        ?>
        <div class="admin-card cms365-admin-card">
            <h3>Nächste Events</h3>
            <?php if ($upcomingEvents === []): ?>
                <p class="description">Keine anstehenden Events vorhanden.</p>
            <?php else: ?>
                <div class="cms365-table-wrap"><table class="users-table cms365-table"><thead><tr><th>Event</th><th>Datum</th><th>Ort</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
                <?php foreach ($upcomingEvents as $event): ?>
                    <tr>
                        <td><strong><?= $this->e($event->title ?? '') ?></strong></td>
                        <td><?= $this->e($event->date_label ?? '') ?><?= ($event->end_date_label ?? '') !== '' ? ' – ' . $this->e($event->end_date_label) : '' ?></td>
                        <td><?= $this->e($event->location ?? '') ?></td>
                        <td><span class="status-badge <?= ($event->status ?? '') === 'published' ? 'active' : 'inactive' ?>"><?= $this->e($event->status ?? '') ?></span></td>
                        <td><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars($site . '/admin/365netevents/edit/' . (int) ($event->id ?? 0), ENT_QUOTES, 'UTF-8') ?>">Bearbeiten</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <p><a class="btn btn-secondary" href="<?= htmlspecialchars($site . '/admin/365netevents', ENT_QUOTES, 'UTF-8') ?>">Alle anstehenden Events</a></p>
            <?php endif; ?>
        </div>
        <?php
        $this->end();
    }

    /** @param array<int, object> $events */
    public function renderEventsList(array $events, bool $showPast = false): void
    {
        $this->start('365NET Events', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_admin');
        $q = htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
        $baseAdminUrl = rtrim((string) SITE_URL, '/') . '/admin/365netevents';
        $toggleUrl = $showPast ? $baseAdminUrl : ($baseAdminUrl . '?past=1');
        $toggleLabel = $showPast ? 'Anstehende anzeigen' : 'Vergangene einblenden';
        $listLabel = $showPast ? 'Vergangene Eventliste' : 'Anstehende Eventliste';
        $listHint = $showPast
            ? 'Es werden nur zurückliegende Events angezeigt (absteigend nach Datum).'
            : 'Es werden nur anstehende Events angezeigt (aufsteigend nach Datum).';
        $resetUrl = $baseAdminUrl . ($showPast ? '?past=1' : '');
        ?>
        <div class="cms365-admin-header">
            <div><h2>📅 365NET Events</h2><p>Verwalte Events, Messen und verknüpfte Speaker.</p></div>
            <div class="cms365-admin-actions"><form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/link-sync', ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-secondary" type="submit">🔗 Verknüpfungen aktualisieren</button></form><a class="btn btn-secondary" href="<?= htmlspecialchars($toggleUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8') ?></a><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/events', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Öffentlich</a><a class="btn btn-primary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/new', ENT_QUOTES, 'UTF-8') ?>">Neues Event</a></div>
        </div>
        <?php $this->flash(); ?>
        <div class="admin-card cms365-admin-card">
            <form method="GET" class="cms365-filter-form">
                <?php if ($showPast): ?><input type="hidden" name="past" value="1"><?php endif; ?>
                <input type="text" name="q" value="<?= $q ?>" placeholder="Titel, Ort, Veranstalter oder Kategorie suchen …" class="form-control">
                <button class="btn btn-primary" type="submit">Suchen</button>
                <?php if ($q !== ''): ?><a class="btn btn-secondary" href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>">Reset</a><?php endif; ?>
            </form>
        </div>
        <div class="admin-card cms365-admin-card">
            <h3><?= htmlspecialchars($listLabel, ENT_QUOTES, 'UTF-8') ?> (<?= count($events) ?>)</h3>
            <p class="description"><?= htmlspecialchars($listHint, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="cms365-table-wrap"><table class="users-table cms365-table"><thead><tr><th>Event</th><th>Datum</th><th>Ort</th><th>Speaker</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><strong><?= $this->e($event->title ?? '') ?></strong><br><small><?= $this->e($event->organizer ?? '') ?></small></td>
                    <td><?= $this->e($event->date_label ?? '') ?><?= ($event->end_date_label ?? '') !== '' ? ' – ' . $this->e($event->end_date_label) : '' ?></td>
                    <td><?= $this->e($event->location ?? '') ?></td>
                    <td><?= (int) ($event->speaker_count ?? 0) ?></td>
                    <td><span class="status-badge <?= ($event->status ?? '') === 'published' ? 'active' : 'inactive' ?>"><?= $this->e($event->status ?? '') ?></span></td>
                    <td class="cms365-row-actions">
                        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/edit/' . (int) $event->id, ENT_QUOTES, 'UTF-8') ?>">✏️</a>
                        <a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/events/' . rawurlencode((string) $event->slug), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">👁️</a>
                        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/delete/' . (int) $event->id, ENT_QUOTES, 'UTF-8') ?>" data-confirm="Dieses Event löschen?">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <?php
        $this->end();
    }

    /** @param array<int, object> $allSpeakers @param array<int, object> $assignedSpeakers @param array<int, object> $companies @param array<int, object> $experts @param array<string, array<int, string>> $taxonomies */
    public function renderEventForm(?object $event, array $allSpeakers, array $assignedSpeakers = [], array $companies = [], array $experts = [], array $taxonomies = []): void
    {
        $isEdit = $event !== null;
        $this->start($isEdit ? 'Event bearbeiten' : 'Event erstellen', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_event_form');
        $assignedIds = array_map(static fn(object $speaker): int => (int) $speaker->id, $assignedSpeakers);
        ?>
        <div class="cms365-admin-header"><div><h2><?= $isEdit ? '✏️ Event bearbeiten' : '➕ Neues Event' ?></h2><p><?= $isEdit ? 'ID #' . (int) $event->id : 'Manuellen Event-Datensatz anlegen.' ?></p></div><div><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents', ENT_QUOTES, 'UTF-8') ?>">← Zurück</a></div></div>
        <?php $this->flash(); ?>
        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/save', ENT_QUOTES, 'UTF-8') ?>" class="cms365-form cms365-form--excomp" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $event->id ?>"><input type="hidden" name="unique_id" value="<?= $this->e($event->unique_id ?? '') ?>"><?php endif; ?>
            <div class="admin-card cms365-admin-card cms365-section"><h3>1. Basisdaten & Veröffentlichung</h3><p class="description">Pflichtdaten, Slug, Status und redaktionelle Hervorhebung.</p><div class="cms365-form-grid">
                <?= $this->field('title', 'Titel *', $event->title ?? '', true) ?>
                <?= $this->field('slug', 'Slug', $event->slug ?? '') ?>
                <?= $this->field('excerpt', 'Kurzbeschreibung / Teaser', $event->excerpt ?? '') ?>
                <?= $this->field('source_column', 'Spalte1 / Zusatzquelle', $event->source_column ?? '') ?>
                <label class="form-group"><span>Status</span><select name="status" class="form-control"><option value="published" <?= ($event->status ?? 'published') === 'published' ? 'selected' : '' ?>>published</option><option value="draft" <?= ($event->status ?? '') === 'draft' ? 'selected' : '' ?>>draft</option></select></label>
                <?= $this->checkbox('featured', 'Als Highlight anzeigen', !empty($event->featured)) ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>2. Beschreibung (EditorJS)</h3><p class="description">Strukturierter Block-Inhalt mit Medien, Tabellen, Checklisten und Linkkarten.</p><?= $this->editor('description_json', (string) ($event->description_json ?? ''), (string) ($event->description ?? ''), 'Event-Beschreibung') ?></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>3. Bild & Medien</h3><div class="cms365-form-grid">
                <?= $this->imageField('image_url', 'Event Bild / Hero URL', $event->image_url ?? '', 'image_alt', $event->image_alt ?? '') ?>
                <?= $this->field('image_bg_color', 'Bild-Hintergrund (rechter Header, HEX oder RGB)', $event->image_bg_color ?? '#eef2f7') ?>
                <?= $this->mediaUrlField('og_image_url', 'Social Sharing Bild', $event->og_image_url ?? '') ?>
            </div><p class="description">Optional: Farbe hinter dem Event-Bild im rechten 33%-Headerbereich (wird nur auf der Detailseite genutzt).</p><?= $this->galleryField('gallery_json', 'Galerie (mehrere Bilder aus der Mediathek oder je Zeile ein Bild)', $event->gallery_json ?? '') ?></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>4. Datum, Zeit & Zeitzone</h3><div class="cms365-form-grid">
                <?= $this->field('start_date', 'Startdatum', $event->start_date ?? '', false, 'date') ?>
                <?= $this->field('end_date', 'Enddatum', $event->end_date ?? '', false, 'date') ?>
                <?= $this->field('start_time', 'Startzeit', $event->start_time ?? '', false, 'time') ?>
                <?= $this->field('end_time', 'Endzeit', $event->end_time ?? '', false, 'time') ?>
                <?= $this->field('date_label', 'Datumslabel original', $event->date_label ?? '') ?>
                <?= $this->field('end_date_label', 'Bis-Label original', $event->end_date_label ?? '') ?>
                <?= $this->field('timezone', 'Zeitzone', $event->timezone ?? 'Europe/Berlin') ?>
                <?= $this->field('early_bird_until', 'Early-Bird bis', $event->early_bird_until ?? '', false, 'date') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>5. Ort, Online & Hybrid</h3><div class="cms365-form-grid">
                <?= $this->field('location', 'Ort', $event->location ?? '') ?>
                <?= $this->field('venue_name', 'Venue / Locationname', $event->venue_name ?? '') ?>
                <?= $this->field('street', 'Straße', $event->street ?? '') ?>
                <?= $this->field('postal_code', 'PLZ', $event->postal_code ?? '') ?>
                <?= $this->field('country', 'Land', $event->country ?? 'Deutschland') ?>
                <?= $this->select('attendance_mode', 'Durchführung', (string) ($event->attendance_mode ?? ''), ['' => 'Bitte wählen', 'Vor Ort' => 'Vor Ort', 'Online' => 'Online', 'Hybrid' => 'Hybrid']) ?>
                <?= $this->field('online_url', 'Online-/Stream-Link', $event->online_url ?? '', false, 'url') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>6. Veranstalter & Kontakt</h3><div class="cms365-form-grid">
                <?= $this->field('organizer', 'Veranstalter', $event->organizer ?? '') ?>
                <?= $this->field('contact_name', 'Kontaktperson', $event->contact_name ?? '') ?>
                <?= $this->field('contact_email', 'Kontakt E-Mail', $event->contact_email ?? '', false, 'email') ?>
                <?= $this->field('contact_phone', 'Kontakt Telefon', $event->contact_phone ?? '') ?>
                <?= $this->field('website', 'Website', $event->website ?? '', false, 'url') ?>
            </div><label class="form-group"><span>Sponsoren / Partner</span><textarea name="sponsors" class="form-control" rows="3"><?= $this->e($event->sponsors ?? '') ?></textarea></label></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>6b. 365CMS-Verknüpfung</h3><p class="description">Optional manuell setzen. Ohne Auswahl versucht das Plugin automatisch, Veranstalter/Kontakt/Website mit bestehenden Firmen oder Experten zu matchen.</p><div class="cms365-form-grid">
                <?= $this->select('linked_company_id', 'Vorhandene Firma verknüpfen', (string) ($event->linked_company_id ?? ''), $this->companyOptions($companies)) ?>
                <?= $this->select('linked_expert_id', 'Vorhandenen Expert verknüpfen', (string) ($event->linked_expert_id ?? ''), $this->expertOptions($experts)) ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section cms365-section--taxonomy-target"><h3>7. Kategorien, Tags & Zielgruppe</h3><p class="description">Alle Metadaten kompakt nebeneinander. Mehrfachauswahl ist bei Kategorien, Tags, Zielgruppe und Sprache möglich.</p><div class="cms365-form-grid cms365-form-grid--taxonomy-target">
                <?= $this->select('category', 'Hauptkategorie', (string) ($event->category ?? ''), $this->optionMap($taxonomies['event_categories'] ?? [])) ?>
                <?= $this->multiSelect('categories', 'Weitere Kategorien', (string) ($event->categories ?? ''), $taxonomies['event_categories'] ?? []) ?>
                <?= $this->multiSelect('tags', 'Tags / Schlagwörter', (string) ($event->tags ?? ''), $taxonomies['event_tags'] ?? [], 8) ?>
                <?= $this->multiSelect('target_audience', 'Zielgruppe', (string) ($event->target_audience ?? ''), $this->targetAudienceOptions(), 8) ?>
                <?= $this->select('event_format', 'Format', (string) ($event->event_format ?? $event->event_type ?? ''), $this->optionMap($taxonomies['event_types'] ?? [])) ?>
                <?= $this->select('difficulty_level', 'Level', (string) ($event->difficulty_level ?? ''), ['' => 'Bitte wählen', 'Einsteiger' => 'Einsteiger', 'Fortgeschritten' => 'Fortgeschritten', 'Expert' => 'Expert', 'Business' => 'Business', 'Technisch' => 'Technisch']) ?>
                <?= $this->multiSelect('language', 'Sprache', (string) ($event->language ?? 'Deutsch'), $this->languageOptions(), 6) ?>
                <?= $this->field('event_type', 'Event Art (Legacy)', $event->event_type ?? '') ?>
            </div><label class="form-group cms365-form-group--full"><span>Barrierefreiheit / Hinweise</span><textarea name="accessibility" class="form-control" rows="3"><?= $this->e($event->accessibility ?? '') ?></textarea></label></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>8. Preise, Tickets & Registrierung</h3><div class="cms365-form-grid">
                <?= $this->select('price_class', 'Preisklasse', (string) ($event->price_class ?? ''), $this->optionMap($taxonomies['event_price_classes'] ?? [])) ?>
                <?= $this->field('price', 'Preislabel', $event->price ?? '') ?>
                <?= $this->field('price_min', 'Preis min.', $event->price_min ?? '', false, 'number') ?>
                <?= $this->field('price_max', 'Preis max.', $event->price_max ?? '', false, 'number') ?>
                <?= $this->field('currency', 'Währung', $event->currency ?? 'EUR') ?>
                <?= $this->field('capacity', 'Kapazität', $event->capacity ?? '', false, 'number') ?>
                <?= $this->field('registration_url', 'Registrierung', $event->registration_url ?? '', false, 'url') ?>
                <?= $this->field('ticket_url', 'Ticket-Link', $event->ticket_url ?? '', false, 'url') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>9. Speaker-Zuordnung</h3><p class="description">Mehrfachauswahl möglich. Neue Speaker können separat angelegt und anschließend hier verknüpft werden.</p><select name="speaker_ids[]" class="form-control cms365-multiselect" multiple size="12">
                <?php foreach ($allSpeakers as $speaker): ?><option value="<?= (int) $speaker->id ?>" <?= in_array((int) $speaker->id, $assignedIds, true) ? 'selected' : '' ?>><?= $this->e($speaker->display_name ?? '') ?><?= ($speaker->topic ?? '') ? ' – ' . $this->e($speaker->topic) : '' ?></option><?php endforeach; ?>
            </select></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>10. SEO & Social</h3><div class="cms365-form-grid">
                <?= $this->field('seo_title', 'SEO-Titel', $event->seo_title ?? '') ?>
                <?= $this->field('seo_description', 'SEO-Beschreibung', $event->seo_description ?? '') ?>
            </div></div>
            <div class="admin-card cms365-actions-card"><button type="submit" class="btn btn-primary">💾 Speichern</button><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents', ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a></div>
        </form>
        <?php
        $this->end();
    }

    /** @param array<int, object> $speakers */
    public function renderSpeakersList(array $speakers, string $search = '', string $sort = 'az'): void
    {
        $this->start('365NET Speaker', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_admin');
        $search = trim($search);
        $sort = in_array($sort, ['az', 'za', 'date_old_new', 'date_new_old'], true) ? $sort : 'az';
        $q = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
        $sortOptions = [
            'az' => 'A–Z',
            'za' => 'Z–A',
            'date_old_new' => 'Datum alt→neu',
            'date_new_old' => 'Datum neu→alt',
        ];
        $baseUrl = rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers';
        $buildSortUrl = static function (string $targetSort) use ($baseUrl, $search): string {
            $query = ['sort' => $targetSort];
            if ($search !== '') {
                $query['q'] = $search;
            }

            return $baseUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        };
        ?>
        <div class="cms365-admin-header"><div><h2>🎤 Event-Speaker</h2><p>Speaker, Organisationen und Sammel-Speaker aus den Seed-Daten verwalten.</p></div><div class="cms365-admin-actions"><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/speakers', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Öffentlich</a><a class="btn btn-primary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers/new', ENT_QUOTES, 'UTF-8') ?>">Neuer Speaker</a></div></div>
        <?php $this->flash(); ?>
        <div class="admin-card cms365-admin-card"><form method="GET" class="cms365-filter-form"><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>"><input type="text" name="q" value="<?= $q ?>" placeholder="Speaker, Thema oder Tag suchen …" class="form-control"><button class="btn btn-primary" type="submit">Suchen</button><?php if ($search !== ''): ?><a class="btn btn-secondary" href="<?= htmlspecialchars($buildSortUrl($sort), ENT_QUOTES, 'UTF-8') ?>">Reset</a><?php endif; ?></form></div>
        <div class="cms365-admin-actions cms365-admin-sort-actions"><?php foreach ($sortOptions as $sortKey => $sortLabel): ?><a class="btn <?= $sortKey === $sort ? 'btn-primary' : 'btn-secondary' ?>" href="<?= htmlspecialchars($buildSortUrl($sortKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sortLabel, ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div>
        <div class="admin-card cms365-admin-card"><h3>Speakerliste (<?= count($speakers) ?>)</h3><div class="cms365-table-wrap"><table class="users-table cms365-table"><thead><tr><th>Speaker</th><th>Thema</th><th>Events</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
        <?php foreach ($speakers as $speaker): ?><tr><td><strong><?= $this->e($speaker->display_name ?? '') ?></strong><br><small><?= $this->e($speaker->award ?? '') ?></small></td><td><?= $this->e($speaker->topic ?? '') ?></td><td><?= (int) ($speaker->event_count ?? 0) ?></td><td><span class="status-badge <?= ($speaker->status ?? '') === 'published' ? 'active' : 'inactive' ?>"><?= $this->e($speaker->status ?? '') ?></span></td><td class="cms365-row-actions"><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers/edit/' . (int) $speaker->id, ENT_QUOTES, 'UTF-8') ?>">✏️</a><a class="btn btn-sm btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/speakers/' . rawurlencode((string) $speaker->slug), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">👁️</a><form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers/delete/' . (int) $speaker->id, ENT_QUOTES, 'UTF-8') ?>" data-confirm="Diesen Speaker löschen?"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-sm btn-danger" type="submit">🗑️</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
        <?php $this->end();
    }

    /** @param array<string, string> $settings */
    public function renderSettingsForm(array $settings): void
    {
        $this->start('365NET Events Einstellungen', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_settings_form');
        $backfillCsrf = CMS\Security::instance()->generateToken('365net_settings_backfill');
        ?>
        <div class="cms365-admin-header"><div><h2>⚙️ Event-Einstellungen</h2><p>Texte, Layout, Farben, Rundungen und Abstände der öffentlichen Eventseiten steuern.</p></div><div class="cms365-admin-actions"><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/events', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Öffentlich prüfen</a></div></div>
        <?php $this->flash(); ?>
        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/settings/save', ENT_QUOTES, 'UTF-8') ?>" class="cms365-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-card cms365-admin-card cms365-section"><h3>1. Navigation & Archivtexte</h3><div class="cms365-form-grid">
                <?= $this->checkbox('show_nav_link', 'Im Hauptmenü anzeigen', ($settings['show_nav_link'] ?? '0') === '1') ?>
                <?= $this->checkbox('events_header_text_enabled', 'Event-Headertexte anzeigen', ($settings['events_header_text_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('events_header_kicker_enabled', 'Event Kicker anzeigen', ($settings['events_header_kicker_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('events_header_title_enabled', 'Event Titel anzeigen', ($settings['events_header_title_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('events_header_description_enabled', 'Event Beschreibung anzeigen', ($settings['events_header_description_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('events_search_placeholder_enabled', 'Event Such-Placeholder anzeigen', ($settings['events_search_placeholder_enabled'] ?? '1') === '1') ?>
                <?= $this->field('nav_label', 'Navigationslabel', $settings['nav_label'] ?? 'Events') ?>
                <?= $this->field('archive_kicker', 'Event-Archiv Kicker', $settings['archive_kicker'] ?? '') ?>
                <?= $this->field('archive_title', 'Event-Archiv Titel', $settings['archive_title'] ?? '') ?>
                <?= $this->field('archive_description', 'Event-Archiv Beschreibung', $settings['archive_description'] ?? '') ?>
                <?= $this->field('archive_current_month_label', 'Label zukünftige Events', $settings['archive_current_month_label'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>2. Event-Header-Buttons (unten rechts)</h3><div class="cms365-form-grid">
                <?= $this->field('events_header_btn_1_text', 'Button 1 Text', $settings['events_header_btn_1_text'] ?? '') ?>
                <?= $this->field('events_header_btn_1_url', 'Button 1 URL', $settings['events_header_btn_1_url'] ?? '') ?>
                <?= $this->field('events_header_btn_2_text', 'Button 2 Text', $settings['events_header_btn_2_text'] ?? '') ?>
                <?= $this->field('events_header_btn_2_url', 'Button 2 URL', $settings['events_header_btn_2_url'] ?? '') ?>
                <?= $this->field('events_header_btn_3_text', 'Button 3 Text', $settings['events_header_btn_3_text'] ?? '') ?>
                <?= $this->field('events_header_btn_3_url', 'Button 3 URL', $settings['events_header_btn_3_url'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>3. Suche, Buttons & Leerzustände</h3><div class="cms365-form-grid">
                <?= $this->field('archive_search_placeholder', 'Suchfeld Placeholder', $settings['archive_search_placeholder'] ?? '') ?>
                <?= $this->field('archive_search_button', 'Suchbutton', $settings['archive_search_button'] ?? '') ?>
                <?= $this->field('archive_reset_label', 'Reset-Text', $settings['archive_reset_label'] ?? '') ?>
                <?= $this->field('archive_past_button', 'Button vergangene Events', $settings['archive_past_button'] ?? '') ?>
                <?= $this->field('archive_current_button', 'Button zukünftige Events', $settings['archive_current_button'] ?? '') ?>
                <?= $this->field('archive_empty_current', 'Leertext zukünftige Events', $settings['archive_empty_current'] ?? '') ?>
                <?= $this->field('archive_empty_past', 'Leertext vergangene Events', $settings['archive_empty_past'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>4. Speaker- und Detailtexte</h3><div class="cms365-form-grid">
                <?= $this->checkbox('speakers_header_text_enabled', 'Speaker-Headertexte anzeigen', ($settings['speakers_header_text_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('speakers_header_kicker_enabled', 'Speaker Kicker anzeigen', ($settings['speakers_header_kicker_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('speakers_header_title_enabled', 'Speaker Titel anzeigen', ($settings['speakers_header_title_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('speakers_header_description_enabled', 'Speaker Beschreibung anzeigen', ($settings['speakers_header_description_enabled'] ?? '1') === '1') ?>
                <?= $this->checkbox('speakers_search_placeholder_enabled', 'Speaker Such-Placeholder anzeigen', ($settings['speakers_search_placeholder_enabled'] ?? '1') === '1') ?>
                <?= $this->field('speaker_archive_kicker', 'Speaker Kicker', $settings['speaker_archive_kicker'] ?? '') ?>
                <?= $this->field('speaker_archive_title', 'Speaker Titel', $settings['speaker_archive_title'] ?? '') ?>
                <?= $this->field('speaker_archive_description', 'Speaker Beschreibung', $settings['speaker_archive_description'] ?? '') ?>
                <?= $this->field('speakers_header_btn_1_text', 'Speaker Button 1 Text', $settings['speakers_header_btn_1_text'] ?? '') ?>
                <?= $this->field('speakers_header_btn_1_url', 'Speaker Button 1 URL', $settings['speakers_header_btn_1_url'] ?? '') ?>
                <?= $this->field('speakers_header_btn_2_text', 'Speaker Button 2 Text', $settings['speakers_header_btn_2_text'] ?? '') ?>
                <?= $this->field('speakers_header_btn_2_url', 'Speaker Button 2 URL', $settings['speakers_header_btn_2_url'] ?? '') ?>
                <?= $this->field('speakers_header_btn_3_text', 'Speaker Button 3 Text', $settings['speakers_header_btn_3_text'] ?? '') ?>
                <?= $this->field('speakers_header_btn_3_url', 'Speaker Button 3 URL', $settings['speakers_header_btn_3_url'] ?? '') ?>
                <?= $this->field('speaker_search_placeholder', 'Speaker Suche Placeholder', $settings['speaker_search_placeholder'] ?? '') ?>
                <?= $this->field('detail_back_events_label', 'Breadcrumb Events', $settings['detail_back_events_label'] ?? '') ?>
                <?= $this->field('detail_speakers_heading', 'Detail Überschrift Speaker', $settings['detail_speakers_heading'] ?? '') ?>
                <?= $this->field('detail_no_speakers_text', 'Detail Leertext Speaker', $settings['detail_no_speakers_text'] ?? '') ?>
                <?= $this->field('detail_register_label', 'Registrierungsbutton', $settings['detail_register_label'] ?? '') ?>
                <?= $this->field('detail_website_label', 'Websitebutton', $settings['detail_website_label'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>5. Layout, Farben & Abstände</h3><p class="description">Der öffentliche Bereich hat keinen eigenen Seitenhintergrund; diese Werte steuern nur Karten, Buttons, Rundungen und Innenabstände.</p><div class="cms365-form-grid">
                <?= $this->field('layout_primary_color', 'Primärfarbe', $settings['layout_primary_color'] ?? '#1d4ed8', false, 'color') ?>
                <?= $this->field('layout_accent_color', 'Akzentfarbe', $settings['layout_accent_color'] ?? '#f59e0b', false, 'color') ?>
                <?= $this->field('layout_event_card_top_border_color', 'Event-Card oberer Rand', $settings['layout_event_card_top_border_color'] ?? ($settings['layout_accent_color'] ?? '#f59e0b'), false, 'color') ?>
                <?= $this->field('layout_speaker_card_top_border_color', 'Speaker-Card oberer Rand', $settings['layout_speaker_card_top_border_color'] ?? '#8b5cf6', false, 'color') ?>
                <?= $this->field('layout_text_color', 'Textfarbe', $settings['layout_text_color'] ?? '#0f172a', false, 'color') ?>
                <?= $this->field('layout_card_background', 'Kartenhintergrund', $settings['layout_card_background'] ?? '#ffffff', false, 'color') ?>
                <?= $this->field('layout_card_border', 'Kartenrahmen', $settings['layout_card_border'] ?? '#e2e8f0', false, 'color') ?>
                <?= $this->field('layout_radius', 'Hero-/Box-Rundung px', $settings['layout_radius'] ?? '24', false, 'number') ?>
                <?= $this->field('layout_card_radius', 'Karten-Rundung px', $settings['layout_card_radius'] ?? '20', false, 'number') ?>
                <?= $this->field('layout_gap', 'Grid-Abstand horizontal (Karten nebeneinander) px', $settings['layout_gap'] ?? '18', false, 'number') ?>
                <?= $this->field('layout_gap_y', 'Grid-Abstand vertikal (Karten untereinander) px', $settings['layout_gap_y'] ?? ($settings['layout_gap'] ?? '18'), false, 'number') ?>
                <?= $this->field('layout_top_spacing', 'Abstand zum Header px', $settings['layout_top_spacing'] ?? '32', false, 'number') ?>
                <?= $this->field('layout_bottom_spacing', 'Abstand zum Footer px', $settings['layout_bottom_spacing'] ?? '56', false, 'number') ?>
                <?= $this->field('layout_container_width', 'Containerbreite px', $settings['layout_container_width'] ?? '1160', false, 'number') ?>
            </div></div>
            <div class="admin-card cms365-actions-card"><button type="submit" class="btn btn-primary">💾 Einstellungen speichern</button><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents', ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a></div>
        </form>
        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/settings/backfill-descriptions', ENT_QUOTES, 'UTF-8') ?>" class="cms365-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($backfillCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-card cms365-admin-card cms365-section">
                <h3>6. Seed-Beschreibungen auf bestehende Events anwenden</h3>
                <p class="description">Übernimmt die statischen Beschreibungen aus der Seed-Map anhand <code>NR/source_nr</code> in vorhandene Events (inkl. Excerpt und SEO-Beschreibung).</p>
                <button type="submit" class="btn btn-secondary">🧠 Beschreibungen jetzt einspielen</button>
            </div>
        </form>
        <?php
        $this->end();
    }

    /** @param array<string, string> $settings */
    public function renderTaxonomiesForm(array $settings): void
    {
        $this->start('365NET Kategorien & Tags', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_taxonomies_form');
        ?>
        <div class="cms365-admin-header"><div><h2>🏷️ Kategorien, Arten, Klassen & Tags</h2><p>Pflege die Dropdown-Werte für Event- und Speaker-Editoren. Ein Eintrag pro Zeile; Duplikate werden beim Speichern entfernt.</p></div><div class="cms365-admin-actions"></div></div>
        <?php $this->flash(); ?>
        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/taxonomies/save', ENT_QUOTES, 'UTF-8') ?>" class="cms365-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-card cms365-admin-card cms365-section"><h3>Events</h3><div class="cms365-form-grid">
                <?= $this->textareaSetting('taxonomy_event_categories', 'Event-Kategorien / Hauptkategorie', $settings['taxonomy_event_categories'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_event_types', 'Event-Arten / Formate', $settings['taxonomy_event_types'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_event_price_classes', 'Event-Preisklassen', $settings['taxonomy_event_price_classes'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_event_tags', 'Event-Tags', $settings['taxonomy_event_tags'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>Speaker</h3><div class="cms365-form-grid">
                <?= $this->textareaSetting('taxonomy_speaker_categories', 'Speaker-Kategorien / Themen', $settings['taxonomy_speaker_categories'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_speaker_types', 'Speaker-Typen', $settings['taxonomy_speaker_types'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_speaker_price_classes', 'Speaker-Preisklassen', $settings['taxonomy_speaker_price_classes'] ?? '') ?>
                <?= $this->textareaSetting('taxonomy_speaker_tags', 'Speaker-Tags', $settings['taxonomy_speaker_tags'] ?? '') ?>
            </div></div>
            <div class="admin-card cms365-actions-card"><button type="submit" class="btn btn-primary">💾 Listen speichern</button><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents', ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a></div>
        </form>
        <?php
        $this->end();
    }

    /** @param array<int, object> $companies @param array<int, object> $experts @param array<string, array<int, string>> $taxonomies */
    public function renderSpeakerForm(?object $speaker = null, array $companies = [], array $experts = [], array $taxonomies = []): void
    {
        $isEdit = $speaker !== null;
        $this->start($isEdit ? 'Speaker bearbeiten' : 'Speaker erstellen', '365netevents');
        $csrf = CMS\Security::instance()->generateToken('365net_speaker_form');
        ?>
        <div class="cms365-admin-header"><div><h2><?= $isEdit ? '✏️ Speaker bearbeiten' : '➕ Neuer Speaker' ?></h2><p><?= $isEdit ? 'ID #' . (int) $speaker->id : 'Nur echte Personen als Speaker anlegen. Firmen werden über das Companies-Plugin verknüpft.' ?></p></div><div><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers', ENT_QUOTES, 'UTF-8') ?>">← Zurück</a></div></div>
        <?php $this->flash(); ?>
        <form method="POST" action="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers/save', ENT_QUOTES, 'UTF-8') ?>" class="cms365-form cms365-form--excomp" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $speaker->id ?>"><input type="hidden" name="unique_id" value="<?= $this->e($speaker->unique_id ?? '') ?>"><?php endif; ?>
            <div class="admin-card cms365-admin-card cms365-section"><h3>1. Profil & Veröffentlichung</h3><div class="cms365-form-grid">
                <?= $this->field('first_name', 'Vorname', $speaker->first_name ?? '') ?>
                <?= $this->field('last_name', 'Nachname', $speaker->last_name ?? '') ?>
                <?= $this->field('display_name', 'Anzeigename (optional, alternativ zu Vorname + Nachname)', $speaker->display_name ?? '') ?>
                <?= $this->field('slug', 'Slug', $speaker->slug ?? '') ?>
                <?= $this->field('location', 'Standort', $speaker->location ?? '') ?>
                <?= $this->select('speaker_type', 'Speaker-Typ', (string) ($speaker->speaker_type ?? ''), $this->optionMap($taxonomies['speaker_types'] ?? [])) ?>
                <?= $this->checkbox('featured', 'Als Highlight anzeigen', !empty($speaker->featured)) ?>
                <label class="form-group"><span>Status</span><select name="status" class="form-control"><option value="published" <?= ($speaker->status ?? 'published') === 'published' ? 'selected' : '' ?>>published</option><option value="draft" <?= ($speaker->status ?? '') === 'draft' ? 'selected' : '' ?>>draft</option></select></label>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>2. Bio (EditorJS)</h3><?= $this->editor('bio_json', (string) ($speaker->bio_json ?? ''), (string) ($speaker->bio ?? ''), 'Speaker-Bio') ?></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>3. Bild & Kontakt</h3><div class="cms365-form-grid">
                <?= $this->imageField('avatar_url', 'Profilbild URL', $speaker->avatar_url ?? '', 'avatar_alt', $speaker->avatar_alt ?? '') ?>
                <?= $this->imageField('theme_image_url', 'Speaker Themenbild URL (Header rechts)', $speaker->theme_image_url ?? '', 'theme_image_alt', $speaker->theme_image_alt ?? '') ?>
                <?= $this->field('email', 'E-Mail', $speaker->email ?? '', false, 'email') ?>
                <?= $this->field('phone', 'Telefon', $speaker->phone ?? '') ?>
                <?= $this->field('website', 'Website', $speaker->website ?? '', false, 'url') ?>
            </div><p class="description">Pfad-Vorgaben: Profilbild bevorzugt unter <code>/uploads/speaker/…</code>, Themenbild bevorzugt unter <code>/uploads/speakerthemen/…</code>.</p></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>3b. 365CMS-Verknüpfung</h3><p class="description">Optional vorhandenen Expert-/Firmen-Datensatz auswählen. Firmeninformationen bleiben im Companies-Plugin; am Speaker wird nur die Verknüpfung gespeichert.</p><div class="cms365-form-grid">
                <?= $this->select('linked_expert_id', 'Vorhandenen Expert verknüpfen', (string) ($speaker->linked_expert_id ?? ''), $this->expertOptions($experts)) ?>
                <?= $this->select('linked_company_id', 'Vorhandene Firma verknüpfen', (string) ($speaker->linked_company_id ?? ''), $this->companyOptions($companies)) ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>4. Themen, Kategorien & Tags</h3><div class="cms365-form-grid">
                <?= $this->select('topic', 'Thema/Kategorie', (string) ($speaker->topic ?? ''), $this->optionMap($taxonomies['speaker_categories'] ?? [])) ?>
                <?= $this->field('award', 'MVP/Auszeichnung', $speaker->award ?? '') ?>
                <?= $this->multiSelect('categories', 'Kategorien', (string) ($speaker->categories ?? ''), $taxonomies['speaker_categories'] ?? []) ?>
                <?= $this->multiSelect('tags', 'Tags', (string) ($speaker->tags ?? ''), $taxonomies['speaker_tags'] ?? [], 8) ?>
                <?= $this->field('specializations', 'Spezialisierungen', $speaker->specializations ?? '') ?>
                <?= $this->field('languages', 'Sprachen', $speaker->languages ?? 'Deutsch, Englisch') ?>
                <?= $this->field('speaking_formats', 'Formate', $speaker->speaking_formats ?? 'Keynote, Session, Workshop') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>5. Preisklasse & Verfügbarkeit</h3><div class="cms365-form-grid">
                <?= $this->select('price_class', 'Preisklasse', (string) ($speaker->price_class ?? ''), $this->optionMap($taxonomies['speaker_price_classes'] ?? [])) ?>
                <?= $this->field('fee_min', 'Honorar min.', $speaker->fee_min ?? '', false, 'number') ?>
                <?= $this->field('fee_max', 'Honorar max.', $speaker->fee_max ?? '', false, 'number') ?>
                <?= $this->field('currency', 'Währung', $speaker->currency ?? 'EUR') ?>
                <?= $this->field('availability', 'Verfügbarkeit', $speaker->availability ?? '') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>6. Social Links</h3><div class="cms365-form-grid">
                <?= $this->field('linkedin_url', 'LinkedIn', $speaker->linkedin_url ?? '', false, 'url') ?>
                <?= $this->field('x_url', 'X / Twitter', $speaker->x_url ?? '', false, 'url') ?>
                <?= $this->field('youtube_url', 'YouTube', $speaker->youtube_url ?? '', false, 'url') ?>
                <?= $this->field('github_url', 'GitHub', $speaker->github_url ?? '', false, 'url') ?>
            </div></div>
            <div class="admin-card cms365-admin-card cms365-section"><h3>7. SEO & Social</h3><div class="cms365-form-grid">
                <?= $this->field('seo_title', 'SEO-Titel', $speaker->seo_title ?? '') ?>
                <?= $this->field('seo_description', 'SEO-Beschreibung', $speaker->seo_description ?? '') ?>
                <?= $this->mediaUrlField('og_image_url', 'Social Sharing Bild', $speaker->og_image_url ?? '') ?>
            </div></div>
            <div class="admin-card cms365-actions-card"><button type="submit" class="btn btn-primary">💾 Speichern</button><a class="btn btn-secondary" href="<?= htmlspecialchars(rtrim((string) SITE_URL, '/') . '/admin/365netevents/speakers', ENT_QUOTES, 'UTF-8') ?>">Abbrechen</a></div>
        </form>
        <?php $this->end();
    }

    private function start(string $title, string $activePage): void
    {
        // Einheitliche PhinIT-Admin-Navigation (Reiter und Design wie die übrigen PhinIT-Plugins).
        if (class_exists('PhinIT_Plugin_Admin_Nav', false)) {
            // Erster Menüpunkt = Übersicht; aktiv ist der längste passende Pfad (/admin/365netevents/new → Events).
            $descriptions = self::sectionDescriptions();
            $badges = self::adminBadges();
            \PhinIT_Plugin_Admin_Nav::begin([
                ['label' => 'Übersicht', 'url' => '/admin/365netevents/dashboard', 'icon' => 'ti ti-layout-dashboard'],
                ['label' => 'Events', 'url' => '/admin/365netevents', 'icon' => 'ti ti-calendar-event', 'description' => $descriptions['365netevents-events'], 'badge' => $badges['365netevents-events']['value'] ?? null, 'badge_tone' => 'warning'],
                ['label' => 'Speaker', 'url' => '/admin/365netevents/speakers', 'icon' => 'ti ti-microphone', 'description' => $descriptions['365netevents-speakers']],
                ['label' => 'Kategorien & Tags', 'url' => '/admin/365netevents/taxonomies', 'icon' => 'ti ti-tags', 'description' => $descriptions['365netevents-taxonomies']],
                ['label' => 'Einstellungen', 'url' => '/admin/365netevents/settings', 'icon' => 'ti ti-settings', 'description' => $descriptions['365netevents-settings']],
            ], '365netevents');
        }
        $this->startShell($title, $activePage);
    }

    private function end(): void
    {
        $this->endShell();
        if (class_exists('PhinIT_Plugin_Admin_Nav', false)) {
            \PhinIT_Plugin_Admin_Nav::end();
        }
    }

    private function startShell(string $title, string $activePage): void
    {
        $this->printFallbackAssetsIfNeeded();

        if (function_exists('cms_plugin_admin_layout_start')) {
            cms_plugin_admin_layout_start($title, $activePage);
            return;
        }
        if (function_exists('renderAdminLayoutStart')) {
            renderAdminLayoutStart($title, $activePage);
            echo '<div class="cms-plugin-admin-layout"><div class="cms-plugin-admin-layout__content">';
            return;
        }
        $pageTitle = $title;
        require_once ABSPATH . 'admin/partials/header.php';
        require_once ABSPATH . 'admin/partials/sidebar.php';
    }

    private function printFallbackAssetsIfNeeded(): void
    {
        if (self::$fallbackAssetsPrinted) {
            return;
        }

        if (!defined('CMS_365NET_EVENTS_PLUGIN_DIR') || !defined('CMS_365NET_EVENTS_PLUGIN_URL')) {
            return;
        }

        $styles = ['admin.css', 'admin-enhancements.css', 'admin-excomp-forms.css'];
        foreach ($styles as $file) {
            $path = CMS_365NET_EVENTS_PLUGIN_DIR . 'assets/css/' . $file;
            if (!is_file($path)) {
                continue;
            }

            $href = CMS_365NET_EVENTS_PLUGIN_URL . 'assets/css/' . $file . '?v=' . (string) filemtime($path);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $scriptPath = CMS_365NET_EVENTS_PLUGIN_DIR . 'assets/js/admin.js';
        if (is_file($scriptPath)) {
            $src = CMS_365NET_EVENTS_PLUGIN_URL . 'assets/js/admin.js?v=' . (string) filemtime($scriptPath);
            echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }

        self::$fallbackAssetsPrinted = true;
    }

    private function endShell(): void
    {
        $this->renderMediaPickerModal();
        if (function_exists('cms_plugin_admin_layout_end')) {
            cms_plugin_admin_layout_end();
            return;
        }
        echo '</div></div>';
        if (function_exists('renderAdminLayoutEnd')) {
            renderAdminLayoutEnd();
            return;
        }
        require_once ABSPATH . 'admin/partials/footer.php';
    }

    private function flash(): void
    {
        if (isset($_GET['saved'])) {
            echo '<div class="alert alert-success">✅ Gespeichert.</div>';
        }
        if (isset($_GET['backfilled'])) {
            $updated = max(0, (int) ($_GET['updated'] ?? 0));
            echo '<div class="alert alert-success">🧠 Beschreibungs-Backfill ausgeführt. Aktualisierte Events: ' . htmlspecialchars((string) $updated, ENT_QUOTES, 'UTF-8') . '.</div>';
        }
        if (isset($_GET['speaker_backfilled'])) {
            if ((string) ($_GET['speaker_config'] ?? '') === '0') {
                echo '<div class="alert alert-warning">⚠️ Speaker-Backfill wurde nicht ausgeführt: Bitte zuerst GOOGLE API Key und CSE-ID in ENV/.env setzen.</div>';
            } else {
                $updated = max(0, (int) ($_GET['speaker_updated'] ?? 0));
                $skipped = max(0, (int) ($_GET['speaker_skipped'] ?? 0));
                $failed = max(0, (int) ($_GET['speaker_failed'] ?? 0));
                echo '<div class="alert alert-success">🌐 Speaker-Backfill ausgeführt. Aktualisiert: ' . htmlspecialchars((string) $updated, ENT_QUOTES, 'UTF-8') . ', übersprungen: ' . htmlspecialchars((string) $skipped, ENT_QUOTES, 'UTF-8') . ', Fehler: ' . htmlspecialchars((string) $failed, ENT_QUOTES, 'UTF-8') . '.</div>';
            }
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="alert alert-success">🗑️ Gelöscht.</div>';
        }
        if (isset($_GET['synced'])) {
            echo '<div class="alert alert-success">🔗 Verknüpfungen zu Firmen und Experts wurden aktualisiert.</div>';
        }
        if (isset($_GET['warning']) && (string) $_GET['warning'] === 'relations') {
            echo '<div class="alert alert-warning">⚠️ Event wurde gespeichert, aber die Speaker-Zuordnung konnte nicht aktualisiert werden. Details stehen im Plugin-Log.</div>';
        }
        if (isset($_GET['error'])) {
            $error = (string) $_GET['error'];
            $messages = [
                'save' => 'Speichern fehlgeschlagen. Bitte Pflichtfelder, Datenbank-Migration und Plugin-Log prüfen.',
                'required_title' => 'Speichern fehlgeschlagen: Titel bzw. Anzeigename ist erforderlich.',
                'csrf' => 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut speichern.',
                'not_found' => 'Datensatz wurde nicht gefunden.',
            ];
            echo '<div class="alert alert-error">❌ Fehler: ' . htmlspecialchars($messages[$error] ?? $error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }

    private function field(string $name, string $label, mixed $value, bool $required = false, string $type = 'text'): string
    {
        $step = $type === 'number' ? ' step="0.01"' : '';
        return '<label class="form-group"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><input class="form-control" type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . $step . ($required ? ' required' : '') . '></label>';
    }

    /** @param array<string,string> $options */
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
        return [
            'IT-Admins',
            'Entscheider',
            'Developers',
            'Architects',
            'Consultants',
            'Security Teams',
            'Endanwender',
            'Führungskräfte',
            'Community',
            'Einsteiger',
            'Fortgeschrittene',
        ];
    }

    /** @return array<int, string> */
    private function languageOptions(): array
    {
        return [
            'Deutsch',
            'Englisch',
            'Deutsch/Englisch',
            'Französisch',
            'Spanisch',
            'Italienisch',
            'Niederländisch',
        ];
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

        return $html . '</select><small class="description">Mehrfachauswahl mit Strg/Cmd oder Doppelklick.</small></label>';
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
        return '<label class="form-group cms365-checkbox"><span>' . $this->e($label) . '</span><input type="hidden" name="' . $this->e($name) . '" value="0"><input type="checkbox" name="' . $this->e($name) . '" value="1"' . ($checked ? ' checked' : '') . '></label>';
    }

    private function imageField(string $urlName, string $urlLabel, mixed $urlValue, string $altName, mixed $altValue): string
    {
        $url = (string) $urlValue;
        $inputId = 'cms365-media-' . preg_replace('/[^a-z0-9_-]+/i', '-', $urlName);
        $previewId = $inputId . '-preview';
        return '<div class="cms365-image-field"><label class="form-group"><span>' . $this->e($urlLabel) . '</span><div class="cms365-media-row"><input id="' . $this->e($inputId) . '" class="form-control" type="text" name="' . $this->e($urlName) . '" value="' . $this->e($url) . '" placeholder="/uploads/bild.webp oder https://…" data-cms365-image-input data-media-target-input><button type="button" class="btn btn-secondary" data-open-media-picker data-target-input="' . $this->e($inputId) . '" data-preview-id="' . $this->e($previewId) . '" data-picker-title="' . $this->e($urlLabel . ' auswählen') . '">🖼️ Mediathek</button><button type="button" class="btn btn-secondary" data-clear-media-input data-target-input="' . $this->e($inputId) . '" data-preview-id="' . $this->e($previewId) . '">Leeren</button></div></label><div id="' . $this->e($previewId) . '" class="cms365-image-preview" data-cms365-image-preview data-media-preview data-preview-variant="image" data-input-id="' . $this->e($inputId) . '">' . ($url !== '' ? '<img src="' . $this->e($url) . '" alt="" loading="lazy">' : '<span>Keine Vorschau</span>') . '</div><label class="form-group"><span>Alt-Text</span><input class="form-control" type="text" name="' . $this->e($altName) . '" value="' . $this->e((string) $altValue) . '"></label></div>';
    }

    private function mediaUrlField(string $name, string $label, mixed $value): string
    {
        $url = (string) $value;
        $inputId = 'cms365-media-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
        $previewId = $inputId . '-preview';

        return '<label class="form-group cms365-media-field"><span>' . $this->e($label) . '</span><div class="cms365-media-row"><input id="' . $this->e($inputId) . '" class="form-control" type="text" name="' . $this->e($name) . '" value="' . $this->e($url) . '" placeholder="/uploads/bild.webp oder https://…" data-cms365-image-input data-media-target-input><button type="button" class="btn btn-secondary" data-open-media-picker data-target-input="' . $this->e($inputId) . '" data-preview-id="' . $this->e($previewId) . '" data-picker-title="' . $this->e($label . ' auswählen') . '">🖼️ Mediathek</button><button type="button" class="btn btn-secondary" data-clear-media-input data-target-input="' . $this->e($inputId) . '" data-preview-id="' . $this->e($previewId) . '">Leeren</button></div><div id="' . $this->e($previewId) . '" class="cms365-image-preview" data-cms365-image-preview data-media-preview data-preview-variant="image" data-input-id="' . $this->e($inputId) . '">' . ($url !== '' ? '<img src="' . $this->e($url) . '" alt="" loading="lazy">' : '<span>Keine Vorschau</span>') . '</div></label>';
    }

    private function galleryField(string $name, string $label, mixed $value): string
    {
        $inputId = 'cms365-gallery-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
        return '<label class="form-group cms365-gallery-field"><span>' . $this->e($label) . '</span><div class="cms365-media-row cms365-media-row--toolbar"><button type="button" class="btn btn-secondary" data-open-media-picker data-target-input="' . $this->e($inputId) . '" data-gallery-target="1" data-picker-title="Galeriebilder auswählen">🖼️ Bilder aus Mediathek hinzufügen</button><button type="button" class="btn btn-secondary" data-clear-gallery-input data-target-input="' . $this->e($inputId) . '">Galerie leeren</button></div><textarea id="' . $this->e($inputId) . '" name="' . $this->e($name) . '" class="form-control" rows="5" placeholder="/uploads/bild-1.webp&#10;/uploads/bild-2.webp" data-cms365-gallery-input>' . $this->e((string) $value) . '</textarea><small class="description">Ein Klick im Mediathek-Fenster fügt das Bild als neue Zeile hinzu. Mehrere Bilder nacheinander auswählen – fertig ist die Galerie.</small><div class="cms365-gallery-preview" data-cms365-gallery-preview data-input-id="' . $this->e($inputId) . '"></div></label>';
    }

    private function renderMediaPickerModal(): void
    {
        $token = CMS\Security::instance()->generateToken('editorjs_media');
        echo '<div class="modal modal-blur fade cms365-media-modal" id="settingsMediaPickerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-media-picker-title>Bild auswählen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen">×</button></div><div class="modal-body"><div data-media-picker-modal data-api-url="/api/media" data-csrf-token="' . $this->e($token) . '" data-path-prefix="events"><p class="text-secondary small mb-3">Ein Klick übernimmt das Bild in das gewählte Feld; bei Galerien werden Bilder zeilenweise ergänzt.</p><div class="cms365-media-picker-search"><input type="search" class="form-control" placeholder="Mediathek durchsuchen …" data-media-picker-search><span class="description" data-media-picker-status>Lade Medien …</span></div><div class="cms365-media-picker-grid" data-media-picker-grid></div></div></div></div></div></div>';
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
                'blocks' => array_map(static fn(string $part): array => ['type' => 'paragraph', 'data' => ['text' => htmlspecialchars(trim($part), ENT_QUOTES, 'UTF-8')]], array_filter(preg_split('/\n{2,}/', $fallbackText) ?: [])),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (class_exists('CMS\\Services\\EditorJs\\EditorJsAssetService')) {
            $service = new CMS\Services\EditorJs\EditorJsAssetService();
            return $service->render($name, $jsonValue, ['height' => 460, 'context' => '365netevents', 'aria_label' => $label, 'content_width' => 960]);
        }

        return '<textarea class="form-control" name="' . $this->e($name) . '" rows="10">' . $this->e($jsonValue) . '</textarea>';
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

<?php
/**
 * PhinIT – einheitliche Admin-Navigation für alle PhinIT-Plugins.
 *
 * QUELLE: tools/phinit-admin-nav/phinit-admin-nav.php. Die Kopien unter <plugin>/includes/
 * werden mit `php tools/phinit-admin-nav/sync.php` geschrieben und nie direkt bearbeitet.
 *
 * Was der Helfer tut (ein Aufruf je Plugin: PhinIT_Plugin_Admin_Nav::adopt('<menü-slug>', [...])):
 * - Seitenleiste: Das Plugin erscheint als EIN Link ohne aufklappbare Unterpunkte. Die mit
 *   add_submenu_page() registrierten Unterseiten werden aus dem sichtbaren Menü genommen.
 * - Unterseiten laufen über die Hauptseite: /admin/plugins/<slug>/<slug>?view=<unterseite>
 *   (wie die Core-Seiten mit ?tab=). Der Seitenleisten-Link öffnet das Plugin-Dashboard.
 * - Einheitliches Layout (ab 3.0.0, Layout-Vertrag von 365CMS 4.0.0, admin/partials/plugin-workspace.php):
 *   Kopfzeile mit Pluginname, Version/Status-Badge und Einzeiler links sowie 3–4 KPI-Karten rechts
 *   (Optionen „description“, „status“, „stats“); darunter ein zweispaltiger Arbeitsbereich mit dem
 *   Plugin-Menü links (erster Punkt „Übersicht“) und dem Seiteninhalt rechts – auf dem Dashboard und
 *   jeder Unterseite gleich. dashboard() liefert die Übersicht mit Schnellaktionen, Status-/System-
 *   Check und letzten Aktivitäten (Option „dashboard“). Das Markup kommt aus dem Core-Partial; fehlt
 *   es (ältere Cores), nutzt der Helfer seine Kopie includes/plugin-workspace.php und lädt das
 *   Rückfall-Stylesheet assets/css/phinit-admin-nav.css.
 * - Alte Adressen /admin/plugins/<slug>/<unterseite> (Lesezeichen, Formular-Ziele, AJAX) bleiben
 *   gültig: Ein verstecktes Zweitmenü leitet sie um (GET 302, POST 307 mit Formulardaten).
 *   Links und Weiterleitungen der Plugins auf alte Adressen werden in der Ausgabe umgeschrieben.
 * - Design wie im 365CMS-Core: keine Emojis in Menü, Reitern, Überschriften und Brotkrume;
 *   übliche Aktions-Emojis in Buttons/Links werden Tabler-Icons. Formularwerte (value-Attribute,
 *   textarea, option, script, style, pre, code) bleiben unverändert, gespeicherte Daten also auch.
 *
 * @package PhinIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PhinIT_Plugin_Admin_Nav', false)) {
    final class PhinIT_Plugin_Admin_Nav
    {
        public const VERSION = '3.0.0';

        /** Unveränderte Rohtext-Elemente: Inhalt bleibt exakt erhalten. */
        private const RAW_ELEMENTS = ['script', 'style', 'textarea', 'pre', 'code', 'svg'];

        /** In diesen Elementen werden Emojis entfernt statt durch Icons ersetzt. */
        private const PLAIN_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'title', 'th', 'legend'];

        /** Emoji (ohne Variation Selector) → Tabler-Icon. */
        private const ICON_MAP = [
            '⚙' => 'settings', '🔧' => 'tool', '🧰' => 'tool', '💾' => 'device-floppy', '🔁' => 'refresh',
            '🔄' => 'refresh', '✅' => 'check', '✓' => 'check', '✔' => 'check', '❌' => 'x', '✕' => 'x',
            '✖' => 'x', '✗' => 'x', '✘' => 'x', '🎨' => 'palette', '🧾' => 'receipt', '👁' => 'eye',
            '👀' => 'eye', '➕' => 'plus', '🖥' => 'device-desktop', '💻' => 'device-laptop', '💶' => 'currency-euro',
            '💰' => 'coin', '🧩' => 'puzzle', '🏢' => 'building', '📌' => 'pin', '🧭' => 'compass', '✏' => 'pencil',
            '✍' => 'pencil', '📝' => 'edit', '🗑' => 'trash', '⚠' => 'alert-triangle', '❗' => 'alert-circle',
            '📅' => 'calendar', '🗓' => 'calendar', '📦' => 'package', '🔗' => 'link', '📊' => 'chart-bar',
            '📈' => 'trending-up', '📉' => 'trending-down', '📋' => 'clipboard-list', '🧮' => 'calculator',
            '🌐' => 'world', '🏷' => 'tag', '🗂' => 'folders', '📁' => 'folder', '📂' => 'folder-open',
            '📄' => 'file-text', '📃' => 'file-text', '🖼' => 'photo', '📷' => 'camera', '📭' => 'mailbox',
            '📬' => 'mailbox', '📐' => 'ruler', '🎁' => 'gift', '⚡' => 'bolt', '⭐' => 'star', '★' => 'star',
            '🤝' => 'heart-handshake', '📚' => 'books', '📘' => 'book', '📖' => 'book', '🤖' => 'robot',
            '📰' => 'news', '🏠' => 'home', '👤' => 'user', '👥' => 'users', '🔐' => 'lock', '🔒' => 'lock',
            '🔓' => 'lock-open', '🔑' => 'key', '🃏' => 'cards', '📤' => 'upload', '📥' => 'download',
            '⬇' => 'download', '⬆' => 'upload', '🔎' => 'search', '🔍' => 'search', '☁' => 'cloud',
            '📧' => 'mail', '📩' => 'mail', '✉' => 'mail', '🔔' => 'bell', '⏸' => 'player-pause',
            '▶' => 'player-play', '🏁' => 'flag', '🔤' => 'abc', '🧠' => 'brain', '🧪' => 'flask',
            '🔦' => 'bulb', '💡' => 'bulb', '🗄' => 'database', '🎯' => 'target', '🔘' => 'circle-dot',
            '🚫' => 'ban', '⛔' => 'ban', '🚀' => 'rocket', '🛡' => 'shield', '🔥' => 'flame', '⏱' => 'clock',
            '⏰' => 'clock', '🕒' => 'clock', '⌛' => 'hourglass', '⏳' => 'hourglass', '📞' => 'phone',
            '☎' => 'phone', '🎤' => 'microphone', '🧡' => 'heart', '💚' => 'heart', '❤' => 'heart',
            '🧑' => 'user', '🎓' => 'school', '🏆' => 'trophy', '📍' => 'map-pin', '🗺' => 'map',
            '🛒' => 'shopping-cart', '💳' => 'credit-card', '🧷' => 'paperclip', '📎' => 'paperclip',
            '🖨' => 'printer', '📣' => 'speakerphone', '📢' => 'speakerphone', '💬' => 'message',
            '🗨' => 'message', '❓' => 'help', '❔' => 'help', 'ℹ' => 'info-circle', '🆕' => 'sparkles',
            '✨' => 'sparkles', '🏗' => 'building-factory', '🔀' => 'arrows-shuffle', '↩' => 'arrow-back-up',
            '🔙' => 'arrow-left', '➡' => 'arrow-right', '⬅' => 'arrow-left', '🧹' => 'eraser',
            '♻' => 'recycle', '🔴' => 'point-filled', '🟢' => 'point-filled', '🔵' => 'point-filled',
            '🟡' => 'point-filled', '🟠' => 'point-filled', '⚪' => 'point', '⚫' => 'point-filled',
        ];

        /** @var array<string,array<string,mixed>> */
        private static array $plugins = [];

        /** @var array<string,list<array{slug:string,label:string,callable:mixed,capability:string,in_nav:bool,icon:string}>> */
        private static array $tabs = [];

        /** @var array<string,list<array<string,mixed>>> Kennzahlen je Plugin (einmal je Anfrage berechnet) */
        private static array $statsCache = [];

        private static bool $assetsPrinted = false;

        /** @var array{menu:string,tab:string}|null */
        private static ?array $current = null;

        private static bool $hooked = false;
        private static bool $shutdownRegistered = false;

        /**
         * Übernimmt das Admin-Menü eines Plugins in die einheitliche Navigation.
         *
         * Optionen:
         * - icon         Tabler-Icon des Seitenleisten-Eintrags, z. B. 'ti ti-calculator'
         * - menu_title   Titel in der Seitenleiste (Schema „M365 | …“ bzw. „PHINIT | …“)
         * - title        Pluginname im Seitenkopf (Standard: menu_title bzw. Titel aus add_menu_page())
         * - version      Plugin-Version, erscheint dezent neben dem Pluginnamen
         * - stats        callable(): list<array{label:string,value:string|int|float,icon?:string,tone?:string,url?:string}>
         *                – die wichtigsten Kennzahlen; tone: success|warning|danger|info|muted
         * - description  Einzeiler unter dem Pluginnamen
         * - status       ['label' => 'Aktiv', 'tone' => 'success'] oder callable, das dieses Array liefert
         * - dashboard    callable(): array{actions?:list, checks?:list, activity?:array, intro?:string} für dashboard()
         * - badges       callable(): array<unterseiten-slug, int|string> – Zähler-Badges im Plugin-Menü
         * - subnav       [unterseiten-slug => ['param' => 'tab', 'items' => array|callable, 'default' => 'overview']]
         *                – Reiter einer Unterseite (?tab=…) als eigene Punkte im Plugin-Menü; items:
         *                [schlüssel => 'Beschriftung' | ['label' => …, 'icon' => 'ti ti-…']], 'group' = Zwischen-
         *                überschrift im Menü (Standard: Beschriftung der Unterseite, '' = keine). Die Unterseite
         *                blendet ihre eigene Reiterleiste aus, wenn handlesTabs() true liefert.
         * - first_label  Beschriftung des ersten Menüpunkts (Standard „Übersicht“)
         * - labels       [unterseiten-slug => Reiter-Beschriftung]
         * - icons        [unterseiten-slug => Tabler-Icon], sonst anhand der Beschriftung gewählt
         * - descriptions [unterseiten-slug => Kurzbeschreibung] für die Kacheln von dashboard()
         * - hidden       Unterseiten, die erreichbar bleiben, aber keinen Reiter bekommen (z. B. „Neu anlegen“)
         * - parents      [versteckte-unterseite => unterseite], deren Menüpunkt dabei aktiv ist
         *
         * @param array<string,mixed> $options
         */
        public static function adopt(string $menuSlug, array $options = []): void
        {
            $menuSlug = self::slug($menuSlug);
            if ($menuSlug === '') {
                return;
            }

            self::$plugins[$menuSlug] = $options + self::defaultOptions();

            if (!self::$hooked && class_exists('CMS\\Hooks')) {
                \CMS\Hooks::addAction('cms_admin_menu', [self::class, 'transform'], 1000);
                self::$hooked = true;
            }
        }

        /** Hook `cms_admin_menu` (spät): Menüs der übernommenen Plugins umbauen. */
        public static function transform(): void
        {
            global $cms_admin_menu;
            if (!is_array($cms_admin_menu)) {
                return;
            }

            foreach (self::$plugins as $slug => $options) {
                $visibleKey = null;
                $legacyKey = null;
                foreach ($cms_admin_menu as $key => $entry) {
                    if (!is_array($entry) || (string) ($entry['menu_slug'] ?? '') !== $slug) {
                        continue;
                    }
                    if (!empty($entry['phinit_admin_nav_legacy'])) {
                        $legacyKey ??= $key;
                    } else {
                        $visibleKey ??= $key;
                    }
                }
                if ($visibleKey === null) {
                    continue;
                }

                $menu = $cms_admin_menu[$visibleKey];
                $children = array_values(array_filter((array) ($menu['children'] ?? []), 'is_array'));
                if ($children !== [] || !isset(self::$tabs[$slug])) {
                    self::$tabs[$slug] = self::buildTabs($slug, $menu, $children, $options);
                }

                $capability = (string) ($menu['capability'] ?? 'manage_options');
                $menu['children'] = [];
                $menu['callable'] = [self::class, 'dispatch'];
                $menu['menu_title'] = (string) $options['menu_title'] !== '' ? (string) $options['menu_title'] : self::plain((string) ($menu['menu_title'] ?? $slug));
                $menu['page_title'] = self::plain((string) ($menu['page_title'] ?? $menu['menu_title']));
                if ((string) $options['icon'] !== '') {
                    $menu['icon_url'] = (string) $options['icon'];
                } elseif (!preg_match('/^(?:ti\s+)?ti-[a-z0-9-]+$|^</', trim((string) ($menu['icon_url'] ?? '')))) {
                    $menu['icon_url'] = '';
                }
                $cms_admin_menu[$visibleKey] = $menu;

                $legacyChildren = [];
                foreach (self::$tabs[$slug] as $tab) {
                    if ($tab['slug'] === $slug) {
                        continue;
                    }
                    $legacyChildren[] = [
                        'type' => 'submenu_page',
                        'page_title' => $tab['label'],
                        'menu_title' => $tab['label'],
                        'capability' => $tab['capability'],
                        'menu_slug' => $tab['slug'],
                        'callable' => [self::class, 'legacyRedirect'],
                        'url' => '/admin/plugins/' . $slug . '/' . $tab['slug'],
                    ];
                }

                if ($legacyChildren === []) {
                    if ($legacyKey !== null) {
                        unset($cms_admin_menu[$legacyKey]);
                    }
                    continue;
                }

                // Unsichtbar für die Seitenleiste (hidden), aber vom AdminRouter auflösbar.
                $legacy = [
                    'type' => 'plugin_page',
                    'page_title' => $menu['page_title'],
                    'menu_title' => $menu['menu_title'],
                    'capability' => $capability,
                    'menu_slug' => $slug,
                    'callable' => [self::class, 'legacyRedirect'],
                    'icon_url' => '',
                    'position' => null,
                    'hidden' => true,
                    'phinit_admin_nav_legacy' => true,
                    'children' => $legacyChildren,
                ];
                if ($legacyKey !== null) {
                    $cms_admin_menu[$legacyKey] = $legacy;
                } else {
                    $cms_admin_menu[] = $legacy;
                }
            }
        }

        /** Callback des sichtbaren Menüeintrags: rendert die per ?view= gewählte Unterseite. */
        public static function dispatch(): void
        {
            $slug = self::requestMenuSlug();
            $tabs = self::$tabs[$slug] ?? [];
            if ($tabs === []) {
                echo '<div class="alert alert-warning" role="alert">Diese Plugin-Seite ist nicht registriert.</div>';
                return;
            }

            $tab = self::resolveTab($slug, $tabs);
            if (!self::can($tab['capability'])) {
                echo '<div class="alert alert-danger" role="alert">Für diesen Bereich fehlt die Berechtigung.</div>';
                return;
            }
            if (!is_callable($tab['callable'])) {
                echo '<div class="alert alert-danger" role="alert">Dieser Bereich des Plugins ist nicht verfügbar.</div>';
                return;
            }

            // Plugins lesen ihre aktive Unterseite aus $_GET['page'].
            $_GET['page'] = $tab['slug'];
            $_REQUEST['page'] = $tab['slug'];
            self::$current = ['menu' => $slug, 'tab' => $tab['slug']];
            self::registerShutdown();

            if (self::isAjax()) {
                call_user_func($tab['callable']);
                return;
            }

            $level = ob_get_level();
            ob_start();
            try {
                call_user_func($tab['callable']);
            } catch (\Throwable $e) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                throw $e;
            }
            $html = '';
            while (ob_get_level() > $level) {
                $html = (string) ob_get_clean() . $html;
            }

            echo self::decorate($html, $slug, $tab['slug']);
        }

        /** Callback der alten Unterseiten-Adressen: auf ?view= umleiten (POST mit 307). */
        public static function legacyRedirect(): void
        {
            [$slug, $page] = self::requestSegments();
            $query = $_GET;
            if (isset($query['page']) && (string) $query['page'] === $page) {
                unset($query['page']);
            }
            unset($query['view']);
            $target = self::viewUrl($slug, $page, $query);
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            if (!headers_sent()) {
                header('Location: ' . $target, true, in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? 307 : 302);
                exit;
            }

            echo '<p><a class="btn btn-primary" href="' . self::esc($target) . '">Weiter</a></p>';
        }

        /** Adresse einer Unterseite in der einheitlichen Navigation. */
        public static function url(string $menuSlug, string $page = '', array $query = []): string
        {
            return self::viewUrl(self::slug($menuSlug), self::slug($page), $query);
        }

        /** @var list<array{level:int,tabs:list<array<string,mixed>>,plugin:string|array<string,mixed>}> */
        private static array $customStack = [];

        /**
         * Für Plugins mit eigenen Admin-Routen (z. B. /admin/<plugin>/…): vor dem Layout-Start aufrufen.
         * Die Ausgabe bis end() bekommt dieselbe Reiterleiste und dasselbe Design wie die übrigen Plugins.
         *
         * Der erste Reiter ist das Dashboard des Plugins. $plugin ist der mit adopt() übernommene Menü-Slug
         * (Pluginname, Icon und Kennzahlen kommen dann aus dessen Optionen) oder ein Options-Array wie bei adopt().
         *
         * @param list<array{label:string,url:string,icon?:string,description?:string}> $tabs Reiter in Anzeigereihenfolge (aktiv = längster passender Pfad)
         * @param string|array<string,mixed> $plugin
         */
        public static function begin(array $tabs = [], string|array $plugin = []): void
        {
            self::$customStack[] = ['level' => ob_get_level() + 1, 'tabs' => array_values($tabs), 'plugin' => $plugin];
            ob_start();
        }

        /** Gegenstück zu begin(): nach dem Layout-Ende aufrufen. */
        public static function end(): void
        {
            $context = array_pop(self::$customStack);
            if ($context === null) {
                return;
            }

            $html = '';
            while (ob_get_level() >= $context['level']) {
                $html = (string) ob_get_clean() . $html;
            }
            if (self::isNonHtmlResponse($html)) {
                echo $html;
                return;
            }

            [$options, $key] = self::contextOptions($context['plugin']);
            $tabs = self::customTabs($context['tabs'], $options);

            $contentStart = self::contentStart($html);
            $head = self::styleHead(substr($html, 0, $contentStart));
            $content = self::styleContent(substr($html, $contentStart));
            $content = self::wrapContent($content, $key, $options, $tabs, $contentStart > 0, !self::hasPluginContainer($html));

            echo $head . $content;
        }

        /**
         * Text ohne Emojis (für Titel, Reiter und Seitenleiste).
         */
        public static function plain(string $text): string
        {
            $text = (string) preg_replace(self::emojiPattern(), '', $text);
            $text = (string) preg_replace('/^[\s↳›»\-–|]+/u', '', $text);

            return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
        }

        /**
         * @param array<string,mixed> $menu
         * @param list<array<string,mixed>> $children
         * @param array<string,mixed> $options
         * @return list<array{slug:string,label:string,callable:mixed,capability:string,in_nav:bool,icon:string}>
         */
        private static function buildTabs(string $slug, array $menu, array $children, array $options): array
        {
            $menuCapability = (string) ($menu['capability'] ?? 'manage_options');
            $hidden = array_map([self::class, 'slug'], (array) $options['hidden']);
            $labels = (array) $options['labels'];
            $previous = [];
            foreach (self::$tabs[$slug] ?? [] as $tab) {
                $previous[$tab['slug']] = $tab;
            }

            $main = null;
            $others = [];
            foreach ($children as $child) {
                $childSlug = (string) ($child['menu_slug'] ?? '');
                if ($childSlug === '') {
                    continue;
                }
                $callable = $child['callable'] ?? null;
                if (self::isOwnCallable($callable)) {
                    $callable = $previous[$childSlug]['callable'] ?? null;
                }
                $label = (string) ($labels[$childSlug] ?? self::plain((string) (($child['menu_title'] ?? '') !== '' ? $child['menu_title'] : ($child['page_title'] ?? $childSlug))));
                $label = $label !== '' ? $label : $childSlug;
                $tab = [
                    'slug' => $childSlug,
                    'label' => $label,
                    'callable' => $callable,
                    'capability' => (string) (($child['capability'] ?? '') !== '' ? $child['capability'] : $menuCapability),
                    'in_nav' => !in_array($childSlug, $hidden, true),
                    'icon' => self::tabIcon($childSlug, $label, (array) $options['icons']),
                ];
                if ($childSlug === $slug) {
                    $main = $tab;
                } else {
                    $others[] = $tab;
                }
            }

            if ($main === null) {
                $callable = $menu['callable'] ?? null;
                if (self::isOwnCallable($callable)) {
                    $callable = $previous[$slug]['callable'] ?? null;
                }
                $main = [
                    'slug' => $slug,
                    'label' => 'Übersicht',
                    'callable' => $callable,
                    'capability' => $menuCapability,
                    'in_nav' => true,
                    'icon' => '',
                ];
            }
            // Erster Menüpunkt = Übersicht/Dashboard des Plugins (Ziel des Seitenleisten-Links).
            $main['label'] = (string) $options['first_label'] !== '' ? (string) $options['first_label'] : 'Übersicht';
            $main['icon'] = self::normalizeIcon((string) (((array) $options['icons'])[$slug] ?? '')) ?: 'ti ti-layout-dashboard';

            return array_merge([$main], $others);
        }

        /**
         * @param list<array{slug:string,label:string,callable:mixed,capability:string,in_nav:bool,icon:string}> $tabs
         * @return array{slug:string,label:string,callable:mixed,capability:string,in_nav:bool,icon:string}
         */
        private static function resolveTab(string $slug, array $tabs): array
        {
            $requested = self::slug((string) ($_GET['view'] ?? ''));
            if ($requested === '') {
                // Ältere Links wie ?page=<unterseite> auf der Hauptseite.
                $page = self::slug(is_scalar($_GET['page'] ?? null) ? (string) $_GET['page'] : '');
                $requested = $page !== $slug ? $page : '';
            }
            foreach ($tabs as $tab) {
                if ($tab['slug'] === $requested) {
                    return $tab;
                }
            }

            return $tabs[0];
        }

        private static function decorate(string $html, string $slug, string $current): string
        {
            if (self::isNonHtmlResponse($html)) {
                return $html;
            }

            $html = self::rewriteUrls($html, $slug);

            // Nur den Inhaltsbereich gestalten; Kopf und Seitenleiste des Cores bleiben unberührt.
            $contentStart = self::contentStart($html);
            $content = self::styleContent(substr($html, $contentStart));
            $options = self::$plugins[$slug] ?? self::defaultOptions();
            $content = self::wrapContent($content, $slug, $options, self::navTabs($slug, $current), $contentStart > 0, !self::hasPluginContainer($html));

            return self::styleHead(substr($html, 0, $contentStart)) . $content;
        }

        /** Beginn des Inhaltsbereichs, wenn die Ausgabe die komplette Admin-Shell enthält; sonst 0. */
        private static function contentStart(string $html): int
        {
            if (preg_match('/<div[^>]*class="[^"]*\bcms-plugin-admin-content\b[^"]*"[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE) === 1) {
                return $m[0][1] + strlen($m[0][0]);
            }
            // Vollständige Shell ohne Plugin-Container: ab dem Seitenrahmen des Cores.
            if (stripos($html, '<body') !== false
                && preg_match('/<div[^>]*class="[^"]*\bpage-wrapper\b[^"]*"[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE) === 1) {
                return $m[0][1] + strlen($m[0][0]);
            }

            return 0;
        }

        /** Seitentitel und Brotkrume der Shell ohne Emojis. */
        private static function styleHead(string $head): string
        {
            if ($head === '') {
                return '';
            }
            $head = (string) preg_replace_callback('#<title>(.*?)</title>#is', static fn (array $t): string => '<title>' . self::plain($t[1]) . '</title>', $head);

            return (string) preg_replace_callback(
                '#(<nav[^>]*cms-topbar__breadcrumb[^>]*>)(.*?)(</nav>)#is',
                static fn (array $n): string => $n[1] . self::stripTextEmoji($n[2]) . $n[3],
                $head
            );
        }

        /**
         * Übersicht (Dashboard) eines Plugins nach dem Layout-Vertrag von 365CMS 4.0.0:
         * 1. Schnellaktionen, 2. Status-/System-Check, 3. Letzte Aktivitäten, optional Bereichs-Kacheln.
         * Die KPI-Karten stehen bereits in der Kopfzeile und werden hier nicht wiederholt.
         *
         * Werte kommen aus $options und – für alles, was dort fehlt – aus dem Callback „dashboard“ von adopt():
         * - intro     Einleitungssatz (Standard: Option „description“)
         * - actions   list<array{label:string,url:string,icon?:string,description?:string,primary?:bool,external?:bool}>
         *             (Standard: je Bereich des Plugins eine Aktion)
         * - checks    list<array{label:string,status:'ok'|'warning'|'error'|'info'|'muted',value?:string,hint?:string,url?:string}>
         * - activity  array{heading?:string,items:list<array{title:string,url?:string,meta?:string,badge?:string,tone?:string}>,empty?:string,more_url?:string}
         * - sections  Bereichs-Kacheln wie actions (früher „tiles“); 'heading' bzw. 'sections_heading' als Überschrift
         *
         * @param array<string,mixed> $options
         */
        public static function dashboard(string $menuSlug, array $options = []): string
        {
            $slug = self::slug($menuSlug);
            $pluginOptions = self::$plugins[$slug] ?? self::defaultOptions();
            self::loadWorkspace();

            $provided = [];
            $provider = $pluginOptions['dashboard'] ?? null;
            if (is_callable($provider)) {
                try {
                    $result = call_user_func($provider);
                    $provided = is_array($result) ? $result : [];
                } catch (\Throwable $e) {
                    error_log('PhinIT-Admin-Navigation: Dashboard-Daten für „' . $slug . '“ nicht verfügbar: ' . $e->getMessage());
                    $provided = ['checks' => [['label' => 'Dashboard-Daten', 'status' => 'error', 'value' => 'nicht verfügbar', 'hint' => 'Details stehen im Fehlerprotokoll.']]];
                }
            }
            $data = $options + $provided;

            // Ältere Aufrufe: 'tiles' + 'heading' sind die Bereichs-Kacheln.
            if (!isset($data['sections']) && isset($data['tiles']) && is_array($data['tiles'])) {
                $data['sections'] = $data['tiles'];
            }
            if (!isset($data['sections_heading']) && isset($data['heading'])) {
                $data['sections_heading'] = (string) $data['heading'];
            }
            $data['sections'] = self::normalizeLinks(is_array($data['sections'] ?? null) ? $data['sections'] : []);

            if (!isset($data['actions']) || !is_array($data['actions'])) {
                $data['actions'] = [];
                $descriptions = (array) $pluginOptions['descriptions'];
                foreach (self::$tabs[$slug] ?? [] as $index => $tab) {
                    if ($index === 0 || !$tab['in_nav'] || !self::can($tab['capability'])) {
                        continue;
                    }
                    $data['actions'][] = [
                        'label' => $tab['label'],
                        'url' => self::viewUrl($slug, $tab['slug']),
                        'icon' => $tab['icon'],
                        'description' => (string) ($descriptions[$tab['slug']] ?? ''),
                    ];
                }
                // Ohne eigene Aktionen ersetzen die Bereiche die Kacheln, damit nichts doppelt erscheint.
                if ($data['actions'] === [] && $data['sections'] !== []) {
                    $data['actions'] = $data['sections'];
                    $data['sections'] = [];
                }
            }
            $data['actions'] = self::normalizeLinks($data['actions']);

            unset($data['tiles'], $data['heading'], $data['stats']);

            return self::assetTag() . \cms_plugin_dashboard($data);
        }

        /**
         * @param array<mixed> $links
         * @return list<array<string,mixed>>
         */
        private static function normalizeLinks(array $links): array
        {
            $result = [];
            foreach ($links as $link) {
                if (!is_array($link) || (string) ($link['url'] ?? '') === '' || trim((string) ($link['label'] ?? '')) === '') {
                    continue;
                }
                $link['label'] = self::plain((string) $link['label']);
                $link['icon'] = self::normalizeIcon((string) ($link['icon'] ?? '')) ?: self::tabIcon('', (string) $link['label'], []);
                if (!isset($link['external']) && preg_match('#^https?://#i', (string) $link['url']) === 1) {
                    $link['external'] = true;
                }
                $result[] = $link;
            }

            return $result;
        }

        /**
         * Kennzahlen eines Plugins (Option „stats“ aus adopt()), einmal je Anfrage berechnet.
         *
         * @return list<array{label:string,value:string,icon:string,tone:string,url:string,hint:string}>
         */
        public static function stats(string $menuSlug): array
        {
            $slug = self::slug($menuSlug);
            if (isset(self::$statsCache[$slug])) {
                return self::$statsCache[$slug];
            }

            return self::$statsCache[$slug] = self::computeStats(self::$plugins[$slug]['stats'] ?? null, $slug);
        }

        /**
         * @return list<array{label:string,value:string,icon:string,tone:string,url:string,hint:string}>
         */
        private static function computeStats(mixed $provider, string $context): array
        {
            if (is_array($provider) && !is_callable($provider)) {
                return self::normalizeStats($provider);
            }
            if (!is_callable($provider)) {
                return [];
            }

            try {
                $stats = call_user_func($provider);
            } catch (\Throwable $e) {
                error_log('PhinIT-Admin-Navigation: Kennzahlen für „' . $context . '“ nicht verfügbar: ' . $e->getMessage());

                return [];
            }

            return is_array($stats) ? self::normalizeStats($stats) : [];
        }

        /**
         * @param array<mixed> $stats
         * @return list<array{label:string,value:string,icon:string,tone:string,url:string,hint:string}>
         */
        private static function normalizeStats(array $stats): array
        {
            $normalized = [];
            foreach ($stats as $stat) {
                if (!is_array($stat) || !isset($stat['label'], $stat['value']) || !is_scalar($stat['value'])) {
                    continue;
                }
                $value = $stat['value'];
                if (is_int($value) || is_float($value)) {
                    $value = number_format((float) $value, is_float($value) && floor($value) !== $value ? 1 : 0, ',', '.');
                }
                $tone = strtolower((string) ($stat['tone'] ?? ''));
                $normalized[] = [
                    'label' => self::plain((string) $stat['label']),
                    'value' => (string) $value,
                    'icon' => self::normalizeIcon((string) ($stat['icon'] ?? '')),
                    'tone' => in_array($tone, ['success', 'warning', 'danger', 'info', 'muted'], true) ? $tone : '',
                    'url' => (string) ($stat['url'] ?? ''),
                    'hint' => (string) ($stat['hint'] ?? ''),
                ];
            }

            return $normalized;
        }

        /**
         * Inhalt in das einheitliche Layout setzen: Kopfzeile + Workspace (Plugin-Menü | Inhalt).
         * Bei vollständigen Shells (Header/Seitenleiste des Cores) wird nur der Inhaltscontainer umschlossen.
         *
         * @param array<string,mixed> $options
         * @param list<array{slug?:string,label:string,url:string,icon:string,active:bool,badge?:mixed}> $tabs
         */
        private static function wrapContent(string $content, string $key, array $options, array $tabs, bool $isShell, bool $searchContainer): string
        {
            [$open, $close] = self::layoutParts($key, $options, $tabs);

            // Shell mit Plugin-Container: $content beginnt direkt im Container.
            if ($isShell && !$searchContainer) {
                $end = self::matchingDivEnd($content, 0);

                return $open . substr($content, 0, $end) . $close . substr($content, $end);
            }

            // Shell ohne Plugin-Container bzw. Fragment mit eigenem page-body: in den Seitencontainer setzen.
            if (($isShell || stripos($content, 'page-body') !== false)
                && preg_match('/<div[^>]*class="([^"]*\bcontainer(?:-xl|-fluid|-lg)?\b[^"]*)"[^>]*>/i', $content, $m, PREG_OFFSET_CAPTURE) === 1) {
                $tag = $m[0][0];
                $position = $m[0][1] + strlen($tag);
                if (!preg_match('/\bcms-plugin-admin-content\b/', $m[1][0])) {
                    // Gestaltung des Layouts ist auf .cms-plugin-admin-content begrenzt.
                    $tag = substr($tag, 0, $m[1][1] - $m[0][1]) . $m[1][0] . ' cms-plugin-admin-content' . substr($tag, $m[1][1] - $m[0][1] + strlen($m[1][0]));
                }
                $end = self::matchingDivEnd($content, $position);

                return substr($content, 0, $m[0][1]) . $tag . $open . substr($content, $position, $end - $position) . $close . substr($content, $end);
            }

            return $open . $content . $close;
        }

        /**
         * Anfang und Ende des Layouts (Markup aus admin/partials/plugin-workspace.php).
         *
         * @param array<string,mixed> $options
         * @param list<array{slug?:string,label:string,url:string,icon:string,active:bool,badge?:mixed}> $tabs
         * @return array{0:string,1:string}
         */
        private static function layoutParts(string $key, array $options, array $tabs): array
        {
            self::loadWorkspace();

            $title = self::plain((string) ($options['title'] ?? ''));
            if ($title === '') {
                $title = self::plain((string) ($options['menu_title'] ?? ''));
            }
            if ($title === '' && $key !== '') {
                $title = self::plain(self::menuTitle($key));
            }
            $stats = $key !== '' && isset(self::$plugins[$key]) ? self::stats($key) : self::computeStats($options['stats'] ?? null, $title);

            $status = $options['status'] ?? null;
            if (is_callable($status)) {
                try {
                    $status = call_user_func($status);
                } catch (\Throwable $e) {
                    error_log('PhinIT-Admin-Navigation: Status für „' . $title . '“ nicht verfügbar: ' . $e->getMessage());
                    $status = null;
                }
            }

            $nav = [];
            foreach ($tabs as $tab) {
                $nav[] = [
                    'slug' => (string) ($tab['slug'] ?? ''),
                    'label' => $tab['label'],
                    'url' => $tab['url'],
                    'icon' => $tab['icon'],
                    'active' => $tab['active'],
                    'badge' => $tab['badge'] ?? null,
                    'badge_tone' => (string) ($tab['badge_tone'] ?? ''),
                    'group' => (string) ($tab['group'] ?? ''),
                ];
            }

            $open = self::assetTag() . \cms_plugin_workspace_open([
                'plugin' => $key,
                'title' => $title,
                'icon' => self::normalizeIcon((string) ($options['icon'] ?? '')),
                'version' => (string) ($options['version'] ?? ''),
                'status' => is_array($status) ? ['label' => self::plain((string) ($status['label'] ?? '')), 'tone' => (string) ($status['tone'] ?? '')] : null,
                'description' => self::plain((string) ($options['description'] ?? '')),
                'stats' => $stats,
                'nav' => $nav,
                'nav_label' => $title !== '' ? 'Bereiche von ' . $title : 'Bereiche des Plugins',
                'nav_explicit' => true,
            ]);

            return [$open, \cms_plugin_workspace_close()];
        }

        /**
         * Position des schließenden </div> zum Container, in dem $offset liegt (Tiefe 1).
         * Inhalte von script/style/textarea/template zählen nicht. Ohne Treffer: vor Footer bzw. </body>.
         */
        private static function matchingDivEnd(string $html, int $offset): int
        {
            $depth = 1;
            if (preg_match_all('~<(script|style|textarea|template)\b[^>]*>.*?</\1\s*>|<div\b[^>]*>|</div\s*>~is', $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $offset)) {
                foreach ($matches as $match) {
                    if (($match[1][0] ?? '') !== '') {
                        continue;
                    }
                    $tag = $match[0][0];
                    if ($tag[1] === '/') {
                        $depth--;
                        if ($depth === 0) {
                            return $match[0][1];
                        }
                    } elseif (!str_ends_with(rtrim($tag), '/>')) {
                        $depth++;
                    }
                }
            }

            foreach (['<footer', '</body>'] as $marker) {
                $position = stripos($html, $marker, $offset);
                if ($position !== false) {
                    return $position;
                }
            }

            return strlen($html);
        }

        /** Render-Helfer des Layouts laden: aus dem Core, sonst die mitgelieferte Kopie. */
        private static function loadWorkspace(): void
        {
            if (function_exists('cms_plugin_workspace_open')) {
                return;
            }
            $core = defined('ABSPATH') ? ABSPATH . 'admin/partials/plugin-workspace.php' : '';
            if ($core !== '' && is_file($core)) {
                require_once $core;
            }
            if (!function_exists('cms_plugin_workspace_open')) {
                require_once __DIR__ . '/plugin-workspace.php';
            }
        }

        /** Liefert der Core das Layout (Partial + admin-plugins.css)? */
        private static function coreProvidesLayout(): bool
        {
            return defined('ABSPATH') && is_file(ABSPATH . 'admin/partials/plugin-workspace.php');
        }

        /** Stylesheet des einheitlichen Layouts (einmal je Seite). */
        private static function assetTag(): string
        {
            if (self::$assetsPrinted) {
                return '';
            }
            self::$assetsPrinted = true;
            // Ab 365CMS 4.0.0 gestaltet admin-plugins.css das Layout; das Plugin-Stylesheet ist nur der Rückfall.
            if (self::coreProvidesLayout()) {
                return '';
            }

            $plugin = basename(dirname(__DIR__));
            $file = dirname(__DIR__) . '/assets/css/phinit-admin-nav.css';
            if ($plugin === '' || !is_file($file)) {
                return '';
            }

            return '<link rel="stylesheet" href="' . self::esc('/plugins/' . rawurlencode($plugin) . '/assets/css/phinit-admin-nav.css?v=' . self::VERSION . '-' . (int) filemtime($file)) . '">';
        }

        /**
         * Reiter einer per adopt() übernommenen Seite.
         *
         * @return list<array{label:string,url:string,icon:string,active:bool}>
         */
        private static function navTabs(string $slug, string $current): array
        {
            $badges = self::badges($slug);
            $current = (string) (((array) (self::$plugins[$slug]['parents'] ?? []))[$current] ?? $current);
            $tabs = [];
            foreach (self::$tabs[$slug] ?? [] as $tab) {
                if (!$tab['in_nav'] || !self::can($tab['capability'])) {
                    continue;
                }
                $sub = self::subnavItems($slug, $tab['slug']);
                if ($sub !== []) {
                    $config = ((array) (self::$plugins[$slug]['subnav'] ?? []))[$tab['slug']] ?? [];
                    $group = array_key_exists('group', (array) $config) ? (string) $config['group'] : $tab['label'];
                    foreach ($sub as $item) {
                        $badge = $badges[$tab['slug'] . ':' . $item['key']] ?? null;
                        $tabs[] = [
                            'slug' => $tab['slug'] . ':' . $item['key'],
                            'label' => $item['label'],
                            'url' => self::viewUrl($slug, $tab['slug'], [$item['param'] => $item['key']]),
                            'icon' => $item['icon'] !== '' ? $item['icon'] : self::tabIcon('', $item['label'], []),
                            'active' => $tab['slug'] === $current && $item['active'],
                            'group' => $group,
                            'badge' => is_array($badge) ? ($badge['value'] ?? null) : $badge,
                            'badge_tone' => is_array($badge) ? (string) ($badge['tone'] ?? '') : '',
                        ];
                    }
                    continue;
                }
                $badge = $badges[$tab['slug']] ?? null;
                $tabs[] = [
                    'slug' => $tab['slug'],
                    'label' => $tab['label'],
                    'url' => self::viewUrl($slug, $tab['slug']),
                    'icon' => $tab['icon'],
                    'active' => $tab['slug'] === $current,
                    'badge' => is_array($badge) ? ($badge['value'] ?? null) : $badge,
                    'badge_tone' => is_array($badge) ? (string) ($badge['tone'] ?? '') : '',
                ];
            }

            return $tabs;
        }

        /**
         * Reiter einer Unterseite für das Plugin-Menü (Option „subnav“).
         *
         * @return list<array{key:string,label:string,icon:string,param:string,active:bool}>
         */
        private static function subnavItems(string $slug, string $page): array
        {
            $config = ((array) (self::$plugins[$slug]['subnav'] ?? []))[$page] ?? null;
            if (!is_array($config)) {
                return [];
            }
            $items = $config['items'] ?? [];
            if (is_callable($items)) {
                try {
                    $items = call_user_func($items);
                } catch (\Throwable $e) {
                    error_log('PhinIT-Admin-Navigation: Reiter für „' . $page . '“ nicht verfügbar: ' . $e->getMessage());
                    $items = [];
                }
            }
            if (!is_array($items) || $items === []) {
                return [];
            }

            $param = self::slug((string) ($config['param'] ?? 'tab')) ?: 'tab';
            $keys = array_map('strval', array_keys($items));
            $default = (string) ($config['default'] ?? '');
            $default = in_array($default, $keys, true) ? $default : (string) ($keys[0] ?? '');
            $requested = is_scalar($_GET[$param] ?? null) ? self::slug((string) $_GET[$param]) : '';
            $activeKey = in_array($requested, $keys, true) ? $requested : $default;

            $result = [];
            foreach ($items as $key => $item) {
                $label = is_array($item) ? (string) ($item['label'] ?? $key) : (string) $item;
                $result[] = [
                    'key' => (string) $key,
                    'label' => self::plain($label) !== '' ? self::plain($label) : (string) $key,
                    'icon' => is_array($item) ? self::normalizeIcon((string) ($item['icon'] ?? '')) : '',
                    'param' => $param,
                    'active' => (string) $key === $activeKey,
                ];
            }

            return $result;
        }

        /**
         * true, wenn das Plugin-Menü die Reiter der aktuellen Unterseite zeigt (Option „subnav“);
         * die Seite blendet dann ihre eigene Reiterleiste aus.
         */
        public static function handlesTabs(string $menuSlug, string $page = ''): bool
        {
            $slug = self::slug($menuSlug);
            $page = $page !== '' ? self::slug($page) : (string) (self::$current['tab'] ?? '');
            if (self::$current === null || self::$current['menu'] !== $slug || self::$current['tab'] !== $page) {
                return false;
            }

            return self::subnavItems($slug, $page) !== [];
        }

        /**
         * Zähler-Badges des Plugin-Menüs (Option „badges“), je Anfrage einmal berechnet.
         *
         * @return array<string,mixed>
         */
        private static function badges(string $slug): array
        {
            static $cache = [];
            if (isset($cache[$slug])) {
                return $cache[$slug];
            }
            $provider = self::$plugins[$slug]['badges'] ?? null;
            $badges = [];
            if (is_callable($provider)) {
                try {
                    $result = call_user_func($provider);
                    $badges = is_array($result) ? $result : [];
                } catch (\Throwable $e) {
                    error_log('PhinIT-Admin-Navigation: Menü-Zähler für „' . $slug . '“ nicht verfügbar: ' . $e->getMessage());
                }
            } elseif (is_array($provider)) {
                $badges = $provider;
            }

            return $cache[$slug] = $badges;
        }

        /**
         * Reiter für Plugins mit eigenen Admin-Routen (begin()/end()); aktiv ist der längste passende Pfad.
         *
         * @param list<array<string,mixed>> $tabs
         * @param array<string,mixed> $options
         * @return list<array{label:string,url:string,icon:string,active:bool}>
         */
        private static function customTabs(array $tabs, array $options): array
        {
            if ($tabs === []) {
                return [];
            }

            $request = '/' . trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/');
            $active = -1;
            $bestLength = -1;
            foreach ($tabs as $index => $tab) {
                $path = '/' . trim((string) (parse_url((string) ($tab['url'] ?? ''), PHP_URL_PATH) ?? ''), '/');
                if (($request === $path || str_starts_with($request, rtrim($path, '/') . '/')) && strlen($path) > $bestLength) {
                    $active = $index;
                    $bestLength = strlen($path);
                }
            }

            $result = [];
            foreach (array_values($tabs) as $index => $tab) {
                $label = self::plain((string) ($tab['label'] ?? ''));
                if ($index === 0) {
                    // Erster Menüpunkt ist immer die Übersicht des Plugins.
                    $label = (string) ($options['first_label'] ?? '') !== '' ? (string) $options['first_label'] : 'Übersicht';
                }
                $icon = self::normalizeIcon((string) ($tab['icon'] ?? ''));
                $result[] = [
                    'label' => $label,
                    'url' => (string) ($tab['url'] ?? ''),
                    'icon' => $icon !== '' ? $icon : ($index === 0 ? 'ti ti-layout-dashboard' : self::tabIcon('', $label, [])),
                    'active' => $index === $active,
                    'badge' => $tab['badge'] ?? null,
                    'badge_tone' => (string) ($tab['badge_tone'] ?? ''),
                    'group' => (string) ($tab['group'] ?? ''),
                ];
            }

            return $result;
        }

        /**
         * @param string|array<string,mixed> $plugin
         * @return array{0:array<string,mixed>,1:string}
         */
        private static function contextOptions(string|array $plugin): array
        {
            if (is_string($plugin)) {
                $slug = self::slug($plugin);

                return [self::$plugins[$slug] ?? self::defaultOptions(), isset(self::$plugins[$slug]) ? $slug : ''];
            }

            return [$plugin + self::defaultOptions(), ''];
        }

        /** @return array<string,mixed> */
        private static function defaultOptions(): array
        {
            return [
                'icon' => '',
                'menu_title' => '',
                'title' => '',
                'version' => '',
                'stats' => null,
                'description' => '',
                'status' => null,
                'dashboard' => null,
                'badges' => null,
                'first_label' => '',
                'labels' => [],
                'icons' => [],
                'descriptions' => [],
                'hidden' => [],
                'parents' => [],
            ];
        }

        /** Titel des (umgebauten) Seitenleisten-Eintrags eines Plugins. */
        private static function menuTitle(string $slug): string
        {
            global $cms_admin_menu;
            foreach (is_array($cms_admin_menu ?? null) ? $cms_admin_menu : [] as $entry) {
                if (is_array($entry) && (string) ($entry['menu_slug'] ?? '') === $slug && empty($entry['phinit_admin_nav_legacy'])) {
                    return (string) ($entry['menu_title'] ?? '');
                }
            }

            return '';
        }



        private static function hasPluginContainer(string $html): bool
        {
            return preg_match('/<div[^>]*class="[^"]*\bcms-plugin-admin-content\b/i', $html) === 1;
        }

        /** @param array<string,string> $icons */
        private static function tabIcon(string $slug, string $label, array $icons): string
        {
            if ($slug !== '' && isset($icons[$slug])) {
                $icon = self::normalizeIcon((string) $icons[$slug]);
                if ($icon !== '') {
                    return $icon;
                }
            }

            $text = mb_strtolower($label . ' ' . $slug, 'UTF-8');
            $map = [
                'dashboard' => 'layout-dashboard', 'übersicht' => 'layout-dashboard', 'uebersicht' => 'layout-dashboard',
                'zentrale' => 'adjustments-horizontal', 'einstellung' => 'settings', 'settings' => 'settings',
                'design' => 'palette', 'anzeige' => 'eye', 'darstellung' => 'eye', 'texte' => 'typography',
                'hinweis' => 'info-circle', 'inhaltsverzeichnis' => 'list-tree', 'inhalt' => 'file-text',
                'eintr' => 'list-details', 'speaker' => 'microphone', 'event' => 'calendar-event',
                'kategor' => 'tags', 'tags' => 'tags', 'expert' => 'user-star', 'compan' => 'building',
                'public' => 'world', 'lizenzmatrix' => 'table', 'add-on' => 'puzzle', 'copilot' => 'sparkles',
                'matrix' => 'table', 'plugins' => 'plug', 'themes' => 'brush', 'verzeichnis' => 'folders',
                'cms' => 'box', 'nachricht' => 'messages', 'message' => 'messages', 'preis' => 'currency-euro',
                'service' => 'cloud', 'kachel' => 'layout-grid', 'seite' => 'file', 'link' => 'link',
                'import' => 'file-import', 'export' => 'file-export', 'log' => 'list', 'statistik' => 'chart-bar',
                'hilfe' => 'help', 'doku' => 'book', 'sync' => 'refresh',
            ];
            foreach ($map as $needle => $icon) {
                if (str_contains($text, $needle)) {
                    return 'ti ti-' . $icon;
                }
            }

            return 'ti ti-point';
        }

        private static function normalizeIcon(string $icon): string
        {
            $icon = trim($icon);
            if ($icon === '' || preg_match('/^(?:ti\s+)?ti-[a-z0-9-]+$/', $icon) !== 1) {
                return '';
            }

            return str_starts_with($icon, 'ti ') ? $icon : 'ti ' . $icon;
        }

        /** Emojis im Inhaltsbereich: Überschriften ohne, Buttons/Links mit Tabler-Icon. */
        private static function styleContent(string $html): string
        {
            $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (!is_array($parts)) {
                return $html;
            }

            $raw = [];
            $plain = 0;
            $optionModes = [];
            $out = '';
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if ($part[0] === '<') {
                    if (preg_match('#^<(/?)([a-z][a-z0-9]*)\b#i', $part, $tag) === 1) {
                        $name = strtolower($tag[2]);
                        $closing = $tag[1] === '/';
                        $selfClosing = str_ends_with(rtrim($part), '/>');
                        $optionOpen = $optionModes !== [] && ($raw === [] || $raw === ['option']);
                        // Eine <option> endet mit </option>, der nächsten <option>/<optgroup> oder </select>.
                        if ($optionOpen && (($name === 'option') || ($name === 'optgroup') || ($name === 'select' && $closing))) {
                            if (array_pop($optionModes) === true) {
                                $plain = max(0, $plain - 1);
                            } else {
                                array_pop($raw);
                            }
                            if ($closing && $name === 'option') {
                                $out .= $part;
                                continue;
                            }
                        }
                        if ($name === 'option' && !$closing && $raw === []) {
                            // Mit value-Attribut ist der Text reine Anzeige → Emojis entfernen; ohne value ist er der Wert.
                            $withValue = preg_match('/\svalue\s*=/i', $part) === 1;
                            $optionModes[] = $withValue;
                            if ($withValue) {
                                $plain++;
                            } else {
                                $raw[] = 'option';
                            }
                            $out .= $part;
                            continue;
                        }
                        if ($name === 'option') {
                            $out .= $part;
                            continue;
                        }
                        if (in_array($name, self::RAW_ELEMENTS, true) && !$selfClosing) {
                            if ($closing) {
                                if ($raw !== [] && end($raw) === $name) {
                                    array_pop($raw);
                                }
                            } elseif (!($name === 'svg' && $raw !== [])) {
                                $raw[] = $name;
                            }
                        } elseif ($raw === [] && in_array($name, self::PLAIN_ELEMENTS, true)) {
                            $plain = max(0, $plain + ($closing ? -1 : 1));
                        }
                    }
                    $out .= $part;
                    continue;
                }

                if ($raw !== []) {
                    $out .= $part;
                } elseif ($plain > 0) {
                    $out .= self::stripTextEmoji(self::decodeEmojiEntities($part));
                } else {
                    $out .= self::iconizeText(self::decodeEmojiEntities($part));
                }
            }

            return $out;
        }

        /** Numerische Entities von Emojis (z. B. &#128230;) als Zeichen behandeln. */
        private static function decodeEmojiEntities(string $text): string
        {
            if (!str_contains($text, '&#')) {
                return $text;
            }

            return (string) preg_replace_callback('/&#(?:[xX]([0-9a-fA-F]{4,6})|([0-9]{4,7}));/', static function (array $m): string {
                $codepoint = ($m[1] ?? '') !== '' ? (int) hexdec($m[1]) : (int) $m[2];
                $isEmoji = ($codepoint >= 0x1F000 && $codepoint <= 0x1FAFF) || ($codepoint >= 0x2600 && $codepoint <= 0x27BF) || $codepoint === 0xFE0F;

                return $isEmoji ? (string) mb_chr($codepoint, 'UTF-8') : $m[0];
            }, $text);
        }

        private static function stripTextEmoji(string $text): string
        {
            return (string) preg_replace_callback(
                '/(<[^>]*>)|' . substr(self::emojiPattern(), 1, -2) . '[ \x{00A0}]?/u',
                static fn (array $m): string => ($m[1] ?? '') !== '' ? $m[1] : '',
                $text
            );
        }

        private static function iconizeText(string $text): string
        {
            $pattern = '/' . substr(self::emojiPattern(), 1, -2) . '([ \x{00A0}]+)?/u';

            return (string) preg_replace_callback($pattern, static function (array $m): string {
                $base = (string) preg_replace('/[\x{FE0E}\x{FE0F}\x{200D}\x{20E3}\x{1F3FB}-\x{1F3FF}]/u', '', $m[0]);
                $first = mb_substr(trim($base), 0, 1);
                $icon = self::ICON_MAP[$first] ?? null;
                if ($icon === null) {
                    return '';
                }

                // Abstand zum folgenden Text als Klasse statt Leerzeichen (in Flex-Buttons entfällt Whitespace).
                $spaced = ($m[1] ?? '') !== '';

                return '<i class="ti ti-' . $icon . ($spaced ? ' me-1' : '') . '" aria-hidden="true"></i>';
            }, $text);
        }

        private static function emojiPattern(): string
        {
            $emoji = '(?:[\x{1F000}-\x{1FAFF}]|[\x{2600}-\x{27BF}]|[\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B50}\x{2B55}]'
                . '|[\x{231A}\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{2139}\x{21A9}\x{21AA}\x{24C2}\x{25B6}\x{25C0}])';

            return '/(?:[0-9#*]\x{FE0F}?\x{20E3}|' . $emoji . '[\x{FE0E}\x{FE0F}\x{1F3FB}-\x{1F3FF}]?(?:\x{200D}' . $emoji . '[\x{FE0E}\x{FE0F}]?)*|\x{FE0F})/u';
        }

        /** Alte Unterseiten-Adressen in Links, Formularen und JSON auf ?view= umschreiben. */
        private static function rewriteUrls(string $text, string $slug): string
        {
            $subs = [];
            foreach (self::$tabs[$slug] ?? [] as $tab) {
                if ($tab['slug'] !== $slug) {
                    $subs[] = $tab['slug'];
                }
            }
            if ($subs === []) {
                return $text;
            }
            usort($subs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $alternatives = implode('|', array_map(static fn (string $s): string => preg_quote($s, '~'), $subs));
            $pattern = '~(\\\\?/)admin\1plugins\1' . preg_quote($slug, '~') . '\1(' . $alternatives . ')(?![A-Za-z0-9_-])(\?)?~';

            return (string) preg_replace_callback($pattern, static function (array $m) use ($slug): string {
                $sep = $m[1];

                return $sep . 'admin' . $sep . 'plugins' . $sep . $slug . $sep . $slug . '?view=' . $m[2] . (($m[3] ?? '') !== '' ? '&' : '');
            }, $text);
        }

        private static function registerShutdown(): void
        {
            if (self::$shutdownRegistered) {
                return;
            }
            self::$shutdownRegistered = true;

            // Läuft vor dem Senden der gepufferten Ausgabe, also auch nach header(); exit; im Plugin.
            register_shutdown_function(static function (): void {
                if (self::$current === null || headers_sent()) {
                    return;
                }
                foreach (headers_list() as $header) {
                    if (stripos($header, 'location:') !== 0) {
                        continue;
                    }
                    $location = trim(substr($header, 9));
                    $rewritten = self::rewriteUrls($location, self::$current['menu']);
                    if ($rewritten !== $location) {
                        header('Location: ' . $rewritten, true);
                    }
                }
            });
        }

        private static function isNonHtmlResponse(string $html): bool
        {
            $trimmed = ltrim($html);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                return true;
            }
            foreach (headers_list() as $header) {
                if (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false) {
                    return true;
                }
            }

            return false;
        }

        private static function isOwnCallable(mixed $callable): bool
        {
            return is_array($callable) && ($callable[0] ?? null) === self::class;
        }

        /** @param array<string,mixed> $query */
        private static function viewUrl(string $slug, string $page = '', array $query = []): string
        {
            $params = $page !== '' && $page !== $slug ? ['view' => $page] + $query : $query;
            $url = '/admin/plugins/' . rawurlencode($slug) . '/' . rawurlencode($slug);

            return $params !== [] ? $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $url;
        }

        private static function requestMenuSlug(): string
        {
            [$slug] = self::requestSegments();
            if ($slug !== '' && isset(self::$tabs[$slug])) {
                return $slug;
            }

            return count(self::$tabs) === 1 ? (string) array_key_first(self::$tabs) : $slug;
        }

        /** @return array{0:string,1:string} */
        private static function requestSegments(): array
        {
            $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
            if (preg_match('#/admin/plugins/([^/]+)(?:/([^/]+))?#', $path, $m) !== 1) {
                return ['', ''];
            }

            return [self::slug(rawurldecode($m[1])), self::slug(rawurldecode($m[2] ?? ''))];
        }

        private static function isAjax(): bool
        {
            return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        }

        private static function can(string $capability): bool
        {
            return $capability === '' || !function_exists('current_user_can') || current_user_can($capability);
        }

        private static function slug(string $value): string
        {
            return trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($value))), '-');
        }

        private static function esc(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }
}

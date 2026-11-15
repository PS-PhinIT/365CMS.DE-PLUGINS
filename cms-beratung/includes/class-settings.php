<?php
/**
 * CMS Beratung – global settings, sanitizing and options.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'primary_color' => '#2563eb',
            'secondary_color' => '#0f172a',
            'accent_color' => '#f59e0b',
            'background_color' => '#f8fafc',
            'text_color' => '#111827',
            'heading_color' => '#0f172a',
            'button_color' => '#2563eb',
            'button_text_color' => '#ffffff',
            'card_background_color' => '#ffffff',
            'card_border_color' => '#dbeafe',
            'card_shadow_enabled' => '1',
            'border_radius' => '4',
            'spacing' => '20',
            'content_width' => '1160',
            'font_size_base' => '16',
            'font_size_hero' => '52',
            'font_size_section_title' => '32',
            'font_size_card_title' => '21',
            'header_spacing' => '48',
            'footer_spacing' => '48',
            'card_spacing' => '24',
            'section_content_spacing' => '24',
            'use_default_font' => '1',
            'allow_custom_page_css_class' => '1',
            'contact_recipient_email' => '',
            'contact_subject_prefix' => '[CMS Beratung]',
            'privacy_text' => 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Anfrage zu.',
            'success_message' => 'Vielen Dank. Ihre Anfrage wurde erfolgreich gesendet.',
            'error_message' => 'Die Anfrage konnte nicht gesendet werden. Bitte prüfen Sie Ihre Eingaben.',
            'form_storage_enabled' => '1',
            'notification_enabled' => '1',
            'sender_copy_enabled' => '0',
            'honeypot_enabled' => '1',
            'captcha_prepared' => '0',
            'show_submissions_backend' => '1',
            'store_ip_enabled' => '0',
            'store_user_agent_enabled' => '0',
            'auto_delete_submissions_enabled' => '0',
            'retention_days' => '180',
            'seo_meta_title_enabled' => '1',
            'seo_meta_description_enabled' => '1',
            'seo_open_graph_enabled' => '1',
            'seo_twitter_card_enabled' => '1',
            'seo_faq_schema_enabled' => '1',
            'seo_service_schema_enabled' => '1',
            'seo_breadcrumb_schema_enabled' => '1',
            'seo_canonical_enabled' => '1',
            'seo_noindex_per_page_enabled' => '1',
            'seo_nofollow_per_page_enabled' => '1',
            'tracking_button_clicks_enabled' => '0',
            'tracking_form_submit_enabled' => '0',
            'tracking_anchor_clicks_enabled' => '0',
            'tracking_faq_enabled' => '0',
            'tracking_download_enabled' => '0',
            'tracking_bookings_enabled' => '0',
            'tracking_only_when_system_active' => '1',
        ];
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return array_merge(self::defaults(), self::$cache);
        }

        if (!class_exists('CMS\\Database')) {
            return self::defaults();
        }

        try {
            $db = \CMS\Database::instance();
            $prefix = self::prefix($db);
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM {$prefix}beratung_settings WHERE tenant_id IS NULL");
            $stmt->execute();
            $stored = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $key = self::clean_key((string) ($row['setting_key'] ?? ''));
                if ($key !== '') {
                    $stored[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
            self::$cache = $stored;
        } catch (\Throwable $e) {
            error_log('CMS Beratung settings load failed: ' . $e->getMessage());
            self::$cache = [];
        }

        return array_merge(self::defaults(), self::$cache);
    }

    /** @param array<string,string> $settings */
    public static function save(array $settings): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $prefix = self::prefix($db);
        $existing = [];
        $stmt = $db->prepare("SELECT setting_key FROM {$prefix}beratung_settings WHERE tenant_id IS NULL");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $key) {
            $existing[(string) $key] = true;
        }
        $insert = $db->prepare("INSERT INTO {$prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
        $update = $db->prepare("UPDATE {$prefix}beratung_settings SET setting_value = ? WHERE tenant_id IS NULL AND setting_key = ?");

        foreach ($settings as $key => $value) {
            $cleanKey = self::clean_key((string) $key);
            if ($cleanKey === '' || !array_key_exists($cleanKey, self::defaults())) {
                continue;
            }
            if (isset($existing[$cleanKey])) {
                $update->execute([(string) $value, $cleanKey]);
            } else {
                $insert->execute([$cleanKey, (string) $value]);
                $existing[$cleanKey] = true;
            }
        }

        self::flush();
    }

    /** Request-Cache verwerfen (nach Schreibzugriffen auf beratung_settings). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Roh-Wert eines gespeicherten Schlüssels (auch ohne Default, z. B. m365_faq_*), aus dem gebündelten Cache. */
    public static function raw(string $key): ?string
    {
        self::all();
        return array_key_exists($key, self::$cache ?? []) ? (string) self::$cache[$key] : null;
    }

    /**
     * Einstellungsbereiche (eigene Menüpunkte im Plugin-Menü). Gespeichert werden je Bereich nur dessen Schlüssel.
     *
     * @return array<string,array{label:string,icon:string,description:string,keys:list<string>}>
     */
    public static function tabs(): array
    {
        return [
            'design' => ['label' => 'Design', 'icon' => 'ti ti-palette', 'description' => 'Farben, Schriftgrößen und Abstände aller Landingpages, die globale Einstellungen verwenden.', 'keys' => ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'heading_color', 'button_color', 'button_text_color', 'card_background_color', 'card_border_color', 'card_shadow_enabled', 'border_radius', 'spacing', 'content_width', 'font_size_base', 'font_size_hero', 'font_size_section_title', 'font_size_card_title', 'header_spacing', 'footer_spacing', 'card_spacing', 'section_content_spacing', 'use_default_font', 'allow_custom_page_css_class']],
            'contact' => ['label' => 'Kontaktformular', 'icon' => 'ti ti-mail', 'description' => 'Empfänger, Texte und Schutzmechanismen des Anfrageformulars.', 'keys' => ['contact_recipient_email', 'contact_subject_prefix', 'privacy_text', 'success_message', 'error_message', 'form_storage_enabled', 'notification_enabled', 'sender_copy_enabled', 'honeypot_enabled', 'captcha_prepared', 'show_submissions_backend']],
            'privacy' => ['label' => 'Datenschutz', 'icon' => 'ti ti-shield-lock', 'description' => 'Gespeicherte Metadaten und automatische Löschfrist der Anfragen.', 'keys' => ['store_ip_enabled', 'store_user_agent_enabled', 'auto_delete_submissions_enabled', 'retention_days']],
            'seo' => ['label' => 'SEO', 'icon' => 'ti ti-world-search', 'description' => 'Meta-Angaben, Social Cards und strukturierte Daten der Landingpages.', 'keys' => ['seo_meta_title_enabled', 'seo_meta_description_enabled', 'seo_open_graph_enabled', 'seo_twitter_card_enabled', 'seo_faq_schema_enabled', 'seo_service_schema_enabled', 'seo_breadcrumb_schema_enabled', 'seo_canonical_enabled', 'seo_noindex_per_page_enabled', 'seo_nofollow_per_page_enabled']],
            'tracking' => ['label' => 'Tracking', 'icon' => 'ti ti-chart-dots', 'description' => 'Ereignisse an die im CMS eingerichtete Webanalyse melden (nur nach Einwilligung).', 'keys' => ['tracking_button_clicks_enabled', 'tracking_form_submit_enabled', 'tracking_anchor_clicks_enabled', 'tracking_faq_enabled', 'tracking_download_enabled', 'tracking_bookings_enabled', 'tracking_only_when_system_active']],
        ];
    }

    /** @return array<string,array{label:string,hint?:string}> */
    public static function labels(): array
    {
        return [
            'primary_color' => ['label' => 'Primärfarbe'],
            'secondary_color' => ['label' => 'Sekundärfarbe'],
            'accent_color' => ['label' => 'Akzentfarbe'],
            'background_color' => ['label' => 'Hintergrundfarbe'],
            'text_color' => ['label' => 'Textfarbe'],
            'heading_color' => ['label' => 'Überschriftenfarbe'],
            'button_color' => ['label' => 'Buttonfarbe'],
            'button_text_color' => ['label' => 'Button-Textfarbe'],
            'card_background_color' => ['label' => 'Card-Hintergrund'],
            'card_border_color' => ['label' => 'Card-Rahmenfarbe'],
            'card_shadow_enabled' => ['label' => 'Card-Schatten anzeigen'],
            'border_radius' => ['label' => 'Eckenradius (px, 2–6)'],
            'spacing' => ['label' => 'Grundabstand (px, 12–25)'],
            'content_width' => ['label' => 'Inhaltsbreite (px, 720–1160)'],
            'font_size_base' => ['label' => 'Basis-Schriftgröße (px)'],
            'font_size_hero' => ['label' => 'Titelgröße Content Header (px)'],
            'font_size_section_title' => ['label' => 'Bereichstitel (px)'],
            'font_size_card_title' => ['label' => 'Card-Titel (px)'],
            'header_spacing' => ['label' => 'Abstand oben (px)'],
            'footer_spacing' => ['label' => 'Abstand unten (px)'],
            'card_spacing' => ['label' => 'Card-Abstand (px)'],
            'section_content_spacing' => ['label' => 'Abstand Titel zu Inhalt (px)'],
            'use_default_font' => ['label' => 'Schrift des Themes verwenden', 'hint' => 'Aus: Landingpages nutzen die Systemschrift des Plugins.'],
            'allow_custom_page_css_class' => ['label' => 'Eigene CSS-Klasse je Landingpage erlauben'],
            'contact_recipient_email' => ['label' => 'Empfänger-E-Mail', 'hint' => 'Pflicht für Benachrichtigungen über neue Anfragen.'],
            'contact_subject_prefix' => ['label' => 'Betreff-Präfix'],
            'privacy_text' => ['label' => 'Datenschutz-Einwilligung (Standardtext)'],
            'success_message' => ['label' => 'Erfolgsmeldung'],
            'error_message' => ['label' => 'Fehlermeldung bei ungültigen Eingaben'],
            'form_storage_enabled' => ['label' => 'Anfragen in der Datenbank speichern'],
            'notification_enabled' => ['label' => 'Benachrichtigung per E-Mail senden'],
            'sender_copy_enabled' => ['label' => 'Option „Kopie an mich senden“ anbieten'],
            'honeypot_enabled' => ['label' => 'Honeypot-Feld gegen Spam'],
            'captcha_prepared' => ['label' => 'Sicherheitsfrage (Rechenaufgabe) auf allen Formularen', 'hint' => 'Zusätzlich je Landingpage im Kontaktbereich aktivierbar.'],
            'show_submissions_backend' => ['label' => 'Anfragen im Backend anzeigen'],
            'store_ip_enabled' => ['label' => 'IP-Adresse speichern'],
            'store_user_agent_enabled' => ['label' => 'Browserkennung (User Agent) speichern'],
            'auto_delete_submissions_enabled' => ['label' => 'Anfragen automatisch löschen', 'hint' => 'Wird über die zentrale Datenaufbewahrung des CMS (Cron) ausgeführt.'],
            'retention_days' => ['label' => 'Löschfrist in Tagen (1–3650)'],
            'seo_meta_title_enabled' => ['label' => 'Meta-Titel setzen'],
            'seo_meta_description_enabled' => ['label' => 'Meta-Beschreibung setzen'],
            'seo_open_graph_enabled' => ['label' => 'Open-Graph-Angaben'],
            'seo_twitter_card_enabled' => ['label' => 'Twitter/X Card'],
            'seo_faq_schema_enabled' => ['label' => 'FAQ-Schema (JSON-LD)'],
            'seo_service_schema_enabled' => ['label' => 'Service-Schema (JSON-LD)'],
            'seo_breadcrumb_schema_enabled' => ['label' => 'Breadcrumb-Schema (JSON-LD)'],
            'seo_canonical_enabled' => ['label' => 'Canonical-URL setzen'],
            'seo_noindex_per_page_enabled' => ['label' => 'Noindex je Landingpage berücksichtigen'],
            'seo_nofollow_per_page_enabled' => ['label' => 'Nofollow je Landingpage berücksichtigen'],
            'tracking_button_clicks_enabled' => ['label' => 'Button-Klicks melden'],
            'tracking_form_submit_enabled' => ['label' => 'Formularversand melden'],
            'tracking_anchor_clicks_enabled' => ['label' => 'Klicks in der Anker-Navigation melden'],
            'tracking_faq_enabled' => ['label' => 'Geöffnete FAQ-Einträge melden'],
            'tracking_download_enabled' => ['label' => 'Download-Links melden'],
            'tracking_bookings_enabled' => ['label' => 'Klicks auf Terminbuchung melden'],
            'tracking_only_when_system_active' => ['label' => 'Nur an aktive Analyse-Anbieter melden', 'hint' => 'Aus: zusätzlich DOM-Ereignis „cms-beratung:track“ für eigene Integrationen auslösen.'],
        ];
    }

    /** Wirksame Löschfrist der Anfragen in Tagen (0 = nie). */
    public static function retention_days(): int
    {
        $settings = self::all();
        if (($settings['auto_delete_submissions_enabled'] ?? '0') !== '1') {
            return 0;
        }

        return max(1, min(3650, (int) ($settings['retention_days'] ?? 180)));
    }

    /** @param array<string,string> $settings */
    public static function save_missing(array $settings): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $prefix = self::prefix($db);
        $existing = [];
        $stmt = $db->prepare("SELECT setting_key FROM {$prefix}beratung_settings WHERE tenant_id IS NULL");
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $key) {
            $existing[(string) $key] = true;
        }
        $insert = $db->prepare("INSERT INTO {$prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
        foreach ($settings as $key => $value) {
            $cleanKey = self::clean_key((string) $key);
            if ($cleanKey === '' || isset($existing[$cleanKey])) {
                continue;
            }
            $insert->execute([$cleanKey, (string) $value]);
            $existing[$cleanKey] = true;
        }
        self::flush();
    }

    /**
     * @param array<string,mixed> $posted
     * @param list<string>|null   $onlyKeys nur diese Schlüssel übernehmen (Einstellungsbereich); null = alle
     * @return array<string,string>
     */
    public static function sanitize_from_post(array $posted, ?array $onlyKeys = null): array
    {
        $settings = array_merge(self::defaults(), self::all());
        foreach (self::defaults() as $key => $default) {
            if ($onlyKeys !== null && !in_array($key, $onlyKeys, true)) {
                continue;
            }
            if (!array_key_exists($key, $posted)) {
                if (self::is_checkbox($key)) {
                    $settings[$key] = '0';
                }
                continue;
            }

            $raw = is_scalar($posted[$key]) ? (string) $posted[$key] : $default;
            if (self::is_checkbox($key)) {
                $settings[$key] = ($raw !== '' && $raw !== '0') ? '1' : '0';
            } elseif (str_contains($key, 'color')) {
                $settings[$key] = self::color($raw, $default);
            } elseif ($key === 'border_radius') {
                $settings[$key] = (string) self::int_range($raw, 4, 2, 6);
            } elseif ($key === 'spacing') {
                $settings[$key] = (string) self::int_range($raw, 20, 12, 25);
            } elseif ($key === 'retention_days') {
                $settings[$key] = (string) self::int_range($raw, 180, 1, 3650);
            } elseif ($key === 'content_width') {
                $settings[$key] = (string) self::int_range($raw, 1160, 720, 1160);
            } elseif (str_starts_with($key, 'font_size_')) {
                $settings[$key] = (string) self::int_range($raw, (int) $default, 12, 96);
            } elseif (in_array($key, ['header_spacing', 'footer_spacing', 'card_spacing', 'section_content_spacing'], true)) {
                $settings[$key] = (string) self::int_range($raw, (int) $default, 0, 160);
            } elseif (str_contains($key, 'email')) {
                $raw = trim(str_replace(["\r", "\n"], '', $raw));
                $settings[$key] = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
            } else {
                $settings[$key] = self::limit(self::text($raw), str_contains($key, 'message') || str_contains($key, 'privacy') ? 1000 : 255);
            }
        }

        return $settings;
    }

    public static function color(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtolower($value) : $fallback;
    }

    public static function text(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = strip_tags($value);
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value));
    }

    public static function slug(string $value, string $fallback = 'beratung'): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', strip_tags($value)), '-'));
        return $slug !== '' ? self::limit($slug, 160) : $fallback;
    }

    public static function public_url(string $value): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }
        if ((str_starts_with($value, '/') || str_starts_with($value, '#')) && !str_starts_with($value, '//') && preg_match('#^[A-Za-z0-9/_?&=.%\#+:;,@~\-]*$#', $value) === 1) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https'], true) ? $value : '';
        }
        return '';
    }

    public static function image_url(string $value): string
    {
        $value = trim(str_replace('\\', '/', strip_tags($value)));
        $value = str_replace(' ', '%20', $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^media-file(?:\?|$)#i', $value) === 1 || preg_match('#^(?:uploads|images/importer|importer|media)(?:/|$)#i', $value) === 1) {
            $value = '/' . ltrim($value, '/');
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $path = rawurldecode((string) (parse_url($value, PHP_URL_PATH) ?: $value));
            return str_contains($path, '..') || preg_match('#[<>`"\']#', $path) === 1 ? '' : $value;
        }
        return self::public_url($value);
    }

    /** @return array<string,string> */
    public static function statuses(): array
    {
        return ['draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'private' => 'Privat', 'archived' => 'Archiviert'];
    }

    /** @return array<string,string> */
    public static function templates(): array
    {
        return [
            'standard' => 'Standard',
            'modern' => 'Modern',
            'compact' => 'Kompakt',
            'technical' => 'Technisch',
            'consulting' => 'Beratungsfokus',
            'microsoft-365' => 'Microsoft 365',
            'copilot' => 'Copilot',
            'security' => 'Security',
            'minimal' => 'Minimal',
        ];
    }

    private static function prefix(\CMS\Database $db): string
    {
        return method_exists($db, 'getPrefix') ? $db->getPrefix() : (method_exists($db, 'prefix') ? $db->prefix() : 'cms_');
    }

    private static function clean_key(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9_]+/i', '_', strtolower($value)), '_');
    }

    public static function is_checkbox(string $key): bool
    {
        return str_ends_with($key, '_enabled') || str_starts_with($key, 'seo_') || str_starts_with($key, 'tracking_') || in_array($key, ['use_default_font', 'allow_custom_page_css_class', 'captcha_prepared', 'show_submissions_backend'], true);
    }

    private static function int_range(string $value, int $default, int $min, int $max): int
    {
        $number = trim($value) !== '' ? (int) $value : $default;
        return max($min, min($max, $number));
    }

    private static function limit(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}

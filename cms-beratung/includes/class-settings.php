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

        CMS_Beratung_Installer::ensure_for_admin_save();
        $db = \CMS\Database::instance();
        $prefix = self::prefix($db);
        $exists = $db->prepare("SELECT id FROM {$prefix}beratung_settings WHERE tenant_id IS NULL AND setting_key = ? LIMIT 1");
        $insert = $db->prepare("INSERT INTO {$prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
        $update = $db->prepare("UPDATE {$prefix}beratung_settings SET setting_value = ? WHERE tenant_id IS NULL AND setting_key = ?");

        foreach ($settings as $key => $value) {
            $cleanKey = self::clean_key($key);
            if ($cleanKey === '' || !array_key_exists($cleanKey, self::defaults())) {
                continue;
            }
            $exists->execute([$cleanKey]);
            if ($exists->fetch()) {
                $update->execute([$value, $cleanKey]);
            } else {
                $insert->execute([$cleanKey, $value]);
            }
        }

        self::$cache = null;
    }

    /** @param array<string,string> $settings */
    public static function save_missing(array $settings): void
    {
        if (!class_exists('CMS\\Database')) {
            return;
        }

        $db = \CMS\Database::instance();
        $prefix = self::prefix($db);
        $exists = $db->prepare("SELECT id FROM {$prefix}beratung_settings WHERE tenant_id IS NULL AND setting_key = ? LIMIT 1");
        $insert = $db->prepare("INSERT INTO {$prefix}beratung_settings (tenant_id, setting_key, setting_value) VALUES (NULL, ?, ?)");
        foreach ($settings as $key => $value) {
            $cleanKey = self::clean_key($key);
            if ($cleanKey === '') {
                continue;
            }
            $exists->execute([$cleanKey]);
            if (!$exists->fetch()) {
                $insert->execute([$cleanKey, $value]);
            }
        }
    }

    /** @param array<string,mixed> $posted @return array<string,string> */
    public static function sanitize_from_post(array $posted): array
    {
        $settings = array_merge(self::defaults(), self::all());
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $posted)) {
                if (self::is_checkbox($key)) {
                    $settings[$key] = '0';
                }
                continue;
            }

            $raw = is_scalar($posted[$key]) ? (string) $posted[$key] : $default;
            if (self::is_checkbox($key)) {
                $settings[$key] = $raw !== '' ? '1' : '0';
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
        $value = strtr(mb_strtolower(strip_tags($value), 'UTF-8'), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $slug = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $value), '-');
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

    private static function is_checkbox(string $key): bool
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

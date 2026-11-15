<?php
/**
 * Gemeinsame Bereinigung für Admin-Eingaben, Import und öffentliche Ausgabe.
 *
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Sanitizer
{
    /** Erlaubte Tags im Promo-Zusatztext. Attribute werden – bis auf ein geprüftes href – verworfen. */
    private const ALLOWED_TAGS = ['p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'h2', 'h3', 'h4', 'span'];

    public static function text(mixed $value, int $max = 255): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = trim(strip_tags($value));
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return mb_substr($value, 0, max(1, $max));
    }

    public static function textarea(mixed $value, int $max = 2000): string
    {
        return self::text($value, $max);
    }

    /**
     * Absolute, öffentliche http(s)-URL (keine Zugangsdaten, kein localhost, keine privaten IPs).
     */
    public static function public_url(mixed $value): string
    {
        $url = is_scalar($value) ? trim((string) $value) : '';
        if ($url === '' || strlen($url) > 2048 || preg_match('/[[:cntrl:]\s]/', $url) === 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if ((string) ($parts['user'] ?? '') !== '' || (string) ($parts['pass'] ?? '') !== '') {
            return '';
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return '';
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        return $url;
    }

    /**
     * Lokaler Pfad (`/…`, kein `//`, keine Steuerzeichen) – z. B. Medien aus `/uploads/…`.
     */
    public static function local_path(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (class_exists('CMS\\Security') && method_exists('CMS\\Security', 'safeLocalPath')) {
            return \CMS\Security::safeLocalPath($value, '');
        }

        if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//') || str_contains($value, '\\')
            || preg_match('/[\x00-\x20\x7F]/', $value) === 1 || strlen($value) > 1000) {
            return '';
        }

        return $value;
    }

    /** Öffentliche URL oder lokaler Pfad. */
    public static function link(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return '';
        }

        return str_starts_with($value, '/') ? self::local_path($value) : self::public_url($value);
    }

    /**
     * Whitelist-Sanitizer: nur erlaubte Tags, keine Attribute außer einem geprüften href auf <a>.
     */
    public static function html(mixed $value, int $max = 20000): string
    {
        $html = is_scalar($value) ? (string) $value : '';
        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $html = trim(strip_tags($html, $allowed));

        $html = (string) preg_replace_callback('/<(\/?)([a-z0-9]+)\b([^>]*)>/i', static function (array $m): string {
            $closing = $m[1] === '/';
            $tag = strtolower($m[2]);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                return '';
            }
            if ($closing) {
                return $tag === 'br' ? '' : '</' . $tag . '>';
            }
            if ($tag !== 'a') {
                return '<' . $tag . '>';
            }

            $href = '';
            if (preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $m[3], $hm) === 1) {
                $raw = $hm[1] !== '' ? $hm[1] : (($hm[2] ?? '') !== '' ? $hm[2] : ($hm[3] ?? ''));
                $href = self::link(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if ($href === '') {
                return '<a>';
            }

            $external = !str_starts_with($href, '/');

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . ($external ? ' rel="noopener noreferrer"' : '') . '>';
        }, $html);

        return mb_substr($html, 0, $max);
    }

    public static function utm_token(mixed $value, int $max = 120): string
    {
        $token = mb_strtolower(self::text($value, 500));
        $token = (string) preg_replace('/\s+/', '-', $token);
        $token = (string) preg_replace('/[^a-z0-9._~-]+/', '-', $token);
        $token = trim($token, '-');

        return mb_substr($token, 0, max(1, $max));
    }

    public static function slug(mixed $value, string $fallback = 'promo'): string
    {
        $slug = mb_strtolower(self::text($value, 500));
        $slug = strtr($slug, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return mb_substr($slug !== '' ? $slug : $fallback, 0, 120);
    }

    public static function datetime(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    public static function esc(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
    }
}

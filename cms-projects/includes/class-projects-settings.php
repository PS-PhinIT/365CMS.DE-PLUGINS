<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin-Einstellungen und Schema-Version (Optionen in der Core-Tabelle `settings`).
 */
final class CMS_Projects_Settings
{
    public const OPTION_SETTINGS = 'cms_projects_settings';
    public const OPTION_SCHEMA = 'cms_projects_schema_version';
    public const SCHEMA_VERSION = '4.0.0';

    private const DEFAULTS = [
        'audit_retention_days' => 0,
        'sitemap_enabled' => true,
        'search_enabled' => true,
    ];

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;
    private static ?string $schemaCache = null;

    /** @return array{audit_retention_days:int,sitemap_enabled:bool,search_enabled:bool} */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $raw = self::readOption(self::OPTION_SETTINGS);
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = [];
            }
        }

        self::$cache = self::normalize(is_array($decoded) ? $decoded : []);
        return self::$cache;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * @param array<string,mixed> $values
     */
    public static function save(array $values): bool
    {
        $normalized = self::normalize(array_merge(self::all(), $values));
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || !self::writeOption(self::OPTION_SETTINGS, $json)) {
            return false;
        }

        self::$cache = $normalized;
        return true;
    }

    public static function storedSchemaVersion(): string
    {
        if (self::$schemaCache === null) {
            self::$schemaCache = (string) (self::readOption(self::OPTION_SCHEMA) ?? '');
        }

        return self::$schemaCache;
    }

    public static function isSchemaCurrent(): bool
    {
        return self::storedSchemaVersion() === self::SCHEMA_VERSION;
    }

    public static function markSchemaCurrent(): void
    {
        if (self::writeOption(self::OPTION_SCHEMA, self::SCHEMA_VERSION)) {
            self::$schemaCache = self::SCHEMA_VERSION;
        }
    }

    /** Entfernt die Optionen des Plugins (nur eigene Schlüssel). */
    public static function deleteAll(): void
    {
        try {
            $db = \CMS\Database::instance();
            foreach ([self::OPTION_SETTINGS, self::OPTION_SCHEMA] as $name) {
                $db->delete('settings', ['option_name' => $name]);
            }
        } catch (\Throwable) {
            // Tabelle nicht erreichbar – nichts zu tun.
        }

        self::$cache = null;
        self::$schemaCache = null;
        self::flushOptionStore();
    }

    /** @param array<string,mixed> $values */
    private static function normalize(array $values): array
    {
        return [
            'audit_retention_days' => max(0, min(3650, (int) ($values['audit_retention_days'] ?? 0))),
            'sitemap_enabled' => self::toBool($values['sitemap_enabled'] ?? true),
            'search_enabled' => self::toBool($values['search_enabled'] ?? true),
        ];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function readOption(string $name): ?string
    {
        try {
            if (class_exists('CMS\\Services\\OptionStore')) {
                return \CMS\Services\OptionStore::getInstance()->get($name);
            }

            if (function_exists('get_option')) {
                $value = get_option($name, null);
                return $value === null ? null : (string) $value;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private static function writeOption(string $name, string $value): bool
    {
        try {
            if (function_exists('update_option')) {
                $result = (bool) update_option($name, $value);
            } else {
                $db = \CMS\Database::instance();
                $exists = (int) ($db->get_var('SELECT COUNT(*) FROM ' . $db->getPrefix() . 'settings WHERE option_name = ?', [$name]) ?? 0) > 0;
                $result = $exists
                    ? $db->update('settings', ['option_value' => $value], ['option_name' => $name])
                    : (bool) $db->insert('settings', ['option_name' => $name, 'option_value' => $value]);
            }
        } catch (\Throwable) {
            return false;
        }

        self::flushOptionStore();
        return $result;
    }

    private static function flushOptionStore(): void
    {
        if (class_exists('CMS\\Services\\OptionStore')) {
            try {
                \CMS\Services\OptionStore::getInstance()->flush();
            } catch (\Throwable) {
                // ignorieren
            }
        }
    }
}

<?php
/**
 * CMS Booking – Integration-API
 *
 * Ermöglicht anderen Plugins (cms-experts, cms-speakers, cms-events, cms-companies …)
 * sich als buchbare Anbieter zu registrieren und Buchungs-Buttons einzublenden.
 *
 * Verwendung durch andere Plugins:
 *
 *   CMS\Hooks::addAction('booking_register_providers', function () {
 *       CMS_Booking_Integration::register_provider_type('cms-experts', [
 *           'label'          => 'Experten',
 *           'icon'           => 'ti ti-user-star',
 *           'source_table'   => 'experts',
 *           'name_column'    => 'name',
 *           'email_column'   => 'email',
 *           'user_id_column' => 'user_id',
 *           'single_route'   => '/experte/{slug}',
 *           'contact_template' => 'booking-expert',
 *       ]);
 *   });
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Integration
{
    /** @var array<string, array> Registrierte Anbieter-Typen */
    private static array $providerTypes = [];

    /** @var bool Wurde init() bereits aufgerufen? */
    private static bool $initialized = false;

    /**
     * Wird per cms_init (Priorität 50) aufgerufen.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Standard-Typen vorab registrieren (sofern Plugins aktiv)
        self::register_builtin_types();

        // Hook für externe Plugins
        if (class_exists('CMS\Hooks')) {
            \CMS\Hooks::doAction('booking_register_providers');
        }
    }

    /* ================================================================== */
    /*  Provider-Type-Registry                                             */
    /* ================================================================== */

    /**
     * Neuen Anbieter-Typ registrieren.
     *
     * @param  string  $pluginSlug     Plugin-Slug (z. B. 'cms-experts')
     * @param  array   $config         Konfiguration
     *    - label           string  Anzeigename (z. B. "Experten")
     *    - icon            string  Tabler-Icon-Klasse (z. B. 'ti ti-user')
     *    - source_table    string  DB-Tabellenname ohne Prefix (z. B. "experts")
     *    - name_column     string  Spalte für Anzeigename
     *    - email_column    string  Spalte für E-Mail
     *    - user_id_column  string  Spalte für User-ID (optional)
     *    - single_route    string  URL-Pattern der Single-Seite (optional)
     *    - contact_template string Bevorzugtes Kontakt-Template (optional)
     *    - fields_map      array   Zusätzliches Mapping (optional)
     */
    public static function register_provider_type(string $pluginSlug, array $config): void
    {
        if (!preg_match('/^[a-z0-9\-_]+$/', $pluginSlug)) {
            throw new \InvalidArgumentException('Ungültiger Plugin-Slug für Booking-Provider.');
        }

        $defaults = [
            'label'            => $pluginSlug,
            'icon'             => 'ti ti-user',
            'source_table'     => '',
            'name_column'      => 'name',
            'email_column'     => 'email',
            'user_id_column'   => 'user_id',
            'single_route'     => '',
            'contact_template' => '',
            'fields_map'       => [],
        ];

        $type = array_merge($defaults, $config);

        foreach (['source_table', 'name_column', 'email_column', 'user_id_column'] as $identifierKey) {
            if ($type[$identifierKey] !== '' && !self::is_safe_identifier((string) $type[$identifierKey])) {
                throw new \InvalidArgumentException('Ungültiger Booking-Provider-Identifier: ' . $identifierKey);
            }
        }

        self::$providerTypes[$pluginSlug] = $type;
    }

    /**
     * Alle registrierten Anbieter-Typen abrufen.
     */
    public static function get_provider_types(): array
    {
        return self::$providerTypes;
    }

    /**
     * Einzelnen Anbieter-Typ abrufen.
     */
    public static function get_provider_type(string $pluginSlug): ?array
    {
        return self::$providerTypes[$pluginSlug] ?? null;
    }

    /**
     * Prüfe ob ein Plugin als Anbieter registriert ist.
     */
    public static function is_registered(string $pluginSlug): bool
    {
        return isset(self::$providerTypes[$pluginSlug]);
    }

    /* ================================================================== */
    /*  Eingebaute Provider-Typen                                          */
    /* ================================================================== */

    private static function register_builtin_types(): void
    {
        if (!class_exists('CMS\PluginManager')) {
            return;
        }
        $pm = \CMS\PluginManager::instance();

        if ($pm->isPluginActive('cms-experts')) {
            self::register_provider_type('cms-experts', [
                'label'            => 'Experten',
                'icon'             => 'ti ti-user-star',
                'source_table'     => 'experts',
                'name_column'      => 'name',
                'email_column'     => 'email',
                'user_id_column'   => 'user_id',
                'single_route'     => '/experte/{slug}',
                'contact_template' => 'booking-expert',
            ]);
        }

        if ($pm->isPluginActive('cms-speakers')) {
            self::register_provider_type('cms-speakers', [
                'label'            => 'Speaker',
                'icon'             => 'ti ti-microphone',
                'source_table'     => 'speakers',
                'name_column'      => 'name',
                'email_column'     => 'email',
                'user_id_column'   => 'user_id',
                'single_route'     => '/speaker/{slug}',
                'contact_template' => 'booking-event',
            ]);
        }

        if ($pm->isPluginActive('cms-events')) {
            self::register_provider_type('cms-events', [
                'label'            => 'Events',
                'icon'             => 'ti ti-calendar-event',
                'source_table'     => 'events',
                'name_column'      => 'title',
                'email_column'     => 'contact_email',
                'user_id_column'   => 'organizer_id',
                'single_route'     => '/event/{slug}',
                'contact_template' => 'booking-event',
            ]);
        }

        if ($pm->isPluginActive('cms-companies')) {
            self::register_provider_type('cms-companies', [
                'label'            => 'Unternehmen',
                'icon'             => 'ti ti-building',
                'source_table'     => 'companies',
                'name_column'      => 'name',
                'email_column'     => 'email',
                'user_id_column'   => 'user_id',
                'single_route'     => '/unternehmen/{slug}',
                'contact_template' => 'booking-service',
            ]);
        }
    }

    /* ================================================================== */
    /*  Buchungs-Button rendern                                            */
    /* ================================================================== */

    /**
     * Buchungs-Button für eine Single-Seite rendern.
     *
     * Aufruf durch andere Plugins:
     *   CMS_Booking_Integration::render_booking_button('cms-experts', $expertId, [
     *       'label' => 'Experte buchen',
     *       'class' => 'btn btn-primary',
     *   ]);
     *
     * @param  string  $pluginSlug   Plugin-Slug
     * @param  int     $sourceId     Quell-ID (z. B. Expert-ID)
     * @param  array   $options      Optionale Anpassungen (label, class, icon)
     * @return string  HTML
     */
    public static function render_booking_button(string $pluginSlug, int $sourceId, array $options = []): string
    {
        $provider = CMS_Booking_Providers::instance()->get_by_source($pluginSlug, $sourceId);
        if (!$provider || $provider['status'] !== 'active') {
            return '';
        }

        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $url     = htmlspecialchars($siteUrl . '/booking/' . rawurlencode((string) $provider['slug']), ENT_QUOTES, 'UTF-8');
        $label   = htmlspecialchars((string) ($options['label'] ?? 'Termin buchen'), ENT_QUOTES, 'UTF-8');
        $icon    = htmlspecialchars(strip_tags((string) ($options['icon'] ?? '')), ENT_QUOTES, 'UTF-8');
        $class   = htmlspecialchars(self::sanitize_class_list((string) ($options['class'] ?? 'btn btn-primary booking-btn')), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<a href="{$url}" class="{$class}">
    <span class="booking-btn__icon">{$icon}</span>
    <span class="booking-btn__label">{$label}</span>
</a>
HTML;
    }

    /**
     * Buchungs-Widget für Single-Seiten rendern (Button + Info-Box).
     */
    public static function render_booking_widget(string $pluginSlug, int $sourceId, array $options = []): string
    {
        $provider = CMS_Booking_Providers::instance()->get_by_source($pluginSlug, $sourceId);
        if (!$provider || $provider['status'] !== 'active') {
            return '';
        }

        $services = CMS_Booking_Services::instance()->get_by_provider((int) $provider['id'], 'active');
        if (empty($services)) {
            return self::render_booking_button($pluginSlug, $sourceId, $options);
        }

        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $baseUrl = $siteUrl . '/booking/' . rawurlencode((string) $provider['slug']);
        $title   = htmlspecialchars((string) ($options['title'] ?? 'Buchbare Leistungen'), ENT_QUOTES, 'UTF-8');

        $html = '<div class="booking-widget">';
        $html .= '<h4 class="booking-widget__title">' . $title . '</h4>';
        $html .= '<ul class="booking-widget__services">';

        foreach ($services as $service) {
            $url      = htmlspecialchars($baseUrl . '/' . rawurlencode((string) $service['slug']), ENT_QUOTES, 'UTF-8');
            $sTitle   = htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8');
            $duration = CMS_Booking_Services::format_duration((int) $service['duration_min']);
            $price    = CMS_Booking_Services::format_price((int) $service['price_cents'], $provider['currency'] ?? 'EUR');

            $html .= <<<HTML
<li class="booking-widget__service">
    <div class="booking-widget__service-info">
        <strong>{$sTitle}</strong>
        <span class="booking-widget__meta">{$duration} · {$price}</span>
    </div>
    <a href="{$url}" class="btn btn-sm btn-primary">Buchen</a>
</li>
HTML;
        }

        $html .= '</ul></div>';
        return $html;
    }

    /* ================================================================== */
    /*  Auto-Sync: Quell-Datensatz → Booking-Provider                     */
    /* ================================================================== */

    /**
     * Synchronisiert einen Datensatz aus einem Quell-Plugin als Buchungsanbieter.
     *
     * Wird typischerweise aufgerufen, wenn ein Experte/Speaker/Event bearbeitet wird.
     *
     * @param  string  $pluginSlug  Plugin-Slug (z. B. 'cms-experts')
     * @param  int     $sourceId    PK im Quell-Plugin
     * @param  array   $overrides   Optionale Überschreibungen
     * @return int     Provider-ID
     */
    public static function sync_provider(string $pluginSlug, int $sourceId, array $overrides = []): int
    {
        $type = self::get_provider_type($pluginSlug);
        if (!$type || $type['source_table'] === '') {
            throw new \InvalidArgumentException("Unbekannter Provider-Typ: {$pluginSlug}");
        }

        $sourceTable = (string) $type['source_table'];
        $nameColumn = (string) $type['name_column'];
        $emailColumn = (string) $type['email_column'];
        $userIdColumn = (string) $type['user_id_column'];

        if (!self::is_safe_identifier($sourceTable)
            || !self::is_safe_identifier($nameColumn)
            || !self::is_safe_identifier($emailColumn)
            || ($userIdColumn !== '' && !self::is_safe_identifier($userIdColumn))
        ) {
            throw new \InvalidArgumentException('Unsichere Booking-Provider-Konfiguration.');
        }

        $db   = \CMS\Database::instance();
        $p    = $db->getPrefix();
        $columns = array_values(array_unique(array_filter(['id', $nameColumn, $emailColumn, $userIdColumn], static fn (string $c): bool => $c !== '')));
        $select  = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns));
        $stmt = $db->prepare("SELECT {$select} FROM `{$p}{$sourceTable}` WHERE id = ?");
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$source) {
            throw new \RuntimeException("Quell-Datensatz nicht gefunden: {$pluginSlug}#{$sourceId}");
        }

        $data = array_merge([
            'source_plugin' => $pluginSlug,
            'source_id'     => $sourceId,
            'display_name'  => (string) ($source[$nameColumn] ?? 'Unbekannt'),
            'email'         => (string) ($source[$emailColumn] ?? ''),
            'user_id'       => $userIdColumn !== '' && isset($source[$userIdColumn]) ? (int) $source[$userIdColumn] : null,
        ], $overrides);

        return CMS_Booking_Providers::instance()->upsert($data);
    }

    private static function is_safe_identifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
    }

    private static function sanitize_class_list(string $classList): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_\-\s]/', '', $classList) ?: 'booking-btn');
    }

    /**
     * Buchungs-Button HTML für eine bestimmte Quell-Entität zurückgeben.
     * Erstellt den Provider bei Bedarf automatisch (Lazy Registration).
     */
    public static function get_or_create_button(string $pluginSlug, int $sourceId, array $options = []): string
    {
        $provider = CMS_Booking_Providers::instance()->get_by_source($pluginSlug, $sourceId);
        if (!$provider) {
            try {
                self::sync_provider($pluginSlug, $sourceId);
            } catch (\Throwable $e) {
                return '';
            }
        }
        return self::render_booking_button($pluginSlug, $sourceId, $options);
    }
}

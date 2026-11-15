<?php
/**
 * CMS Booking – Admin-Menü-Registrierung
 *
 * Hauptmenü-Slug `booking` und Unterseiten `bookings`, `providers`, `services`, `settings` bleiben
 * unverändert (Lesezeichen, Formularziele, Core-Fallback im AdminRouter).
 *
 * @package CMS_Booking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Booking_Admin_Menu
{
    public const MENU_SLUG = 'booking';
    public const MENU_TITLE = '365CMS | Buchungen';
    public const MENU_ICON = 'ti ti-calendar-event';

    /**
     * Über den Hook cms_admin_menu aufgerufen.
     */
    public static function register(): void
    {
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        add_menu_page(
            'Buchungen',
            self::MENU_TITLE,
            'manage_options',
            self::MENU_SLUG,
            [CMS_Booking_Admin_Pages::class, 'render_dashboard'],
            self::MENU_ICON,
            55
        );

        $pages = [
            [self::MENU_SLUG, 'Übersicht', 'render_dashboard'],
            ['bookings', 'Buchungen', 'render_bookings'],
            ['providers', 'Anbieter', 'render_providers'],
            ['services', 'Leistungen', 'render_services'],
            ['settings', 'Einstellungen', 'render_settings'],
        ];

        foreach ($pages as [$slug, $title, $method]) {
            add_submenu_page(
                self::MENU_SLUG,
                $title,
                $title,
                'manage_options',
                $slug,
                [CMS_Booking_Admin_Pages::class, $method]
            );
        }
    }
}

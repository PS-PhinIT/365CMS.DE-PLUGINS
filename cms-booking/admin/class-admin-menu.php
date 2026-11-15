<?php
/**
 * CMS Booking – Admin-Menü
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

    /** Callback je Unterseite (Slugs bleiben für bestehende Links und den Router-Fallback unverändert). */
    private const SUBPAGES = [
        'booking' => 'dispatch_dashboard',
        'bookings' => 'dispatch_bookings',
        'providers' => 'dispatch_providers',
        'availability' => 'dispatch_availability',
        'services' => 'dispatch_services',
        'settings' => 'dispatch_settings',
    ];

    public static function register(): void
    {
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        add_menu_page(
            'Buchungen',
            '365CMS | Buchungen',
            CMS_Booking_Admin_Pages::CAPABILITY,
            self::MENU_SLUG,
            [CMS_Booking_Admin_Pages::class, 'dispatch_dashboard'],
            'ti-calendar-event',
            55
        );

        foreach (self::SUBPAGES as $slug => $method) {
            $label = CMS_Booking_Admin_Pages::PAGES[$slug]['label'];
            add_submenu_page(self::MENU_SLUG, $label, $label, CMS_Booking_Admin_Pages::CAPABILITY, $slug, [CMS_Booking_Admin_Pages::class, $method]);
        }
    }
}

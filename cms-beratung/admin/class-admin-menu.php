<?php
/**
 * CMS Beratung – Admin menu registration.
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Admin_Menu
{
    public static function register(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'Beratung',
            '365CMS | Beratung',
            CMS_Beratung_Admin_Pages::CAPABILITY,
            CMS_Beratung_Admin_Pages::MENU_SLUG,
            [CMS_Beratung_Admin_Pages::class, 'dispatch_overview'],
            'ti-presentation',
            57
        );

        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            CMS_Beratung_Admin_Pages::MENU_SLUG,
            'Übersicht',
            'Übersicht',
            CMS_Beratung_Admin_Pages::CAPABILITY,
            CMS_Beratung_Admin_Pages::MENU_SLUG,
            [CMS_Beratung_Admin_Pages::class, 'dispatch_overview']
        );

        foreach (CMS_Beratung_Admin_Pages::get_menu_pages() as $page) {
            add_submenu_page(
                CMS_Beratung_Admin_Pages::MENU_SLUG,
                (string) $page['title'],
                (string) $page['menu_title'],
                CMS_Beratung_Admin_Pages::CAPABILITY,
                (string) $page['slug'],
                CMS_Beratung_Admin_Pages::dispatch_callback_for_slug((string) $page['slug'])
            );
        }
    }
}

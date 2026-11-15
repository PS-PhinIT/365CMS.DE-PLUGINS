<?php
/**
 * @package CMS_Promos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Promos_Admin_Menu
{
    public static function register(): void
    {
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        $dispatcher = [CMS_Promos_Admin_Pages::class, 'render_page_dispatcher'];
        $root = CMS_Promos_Admin_Pages::ROOT_SLUG;

        add_menu_page('Promos', '365CMS | Promos', 'manage_options', $root, $dispatcher, 'ti ti-speakerphone');
        add_submenu_page($root, 'Promos – Übersicht', 'Übersicht', 'manage_options', 'promos-dashboard', $dispatcher);
        add_submenu_page($root, 'Promos verwalten', 'Promos', 'manage_options', 'promos-items', $dispatcher);
        add_submenu_page($root, 'Promo-Platzierungen', 'Platzierungen', 'manage_options', 'promos-placements', $dispatcher);
        add_submenu_page($root, 'Promo-Einstellungen', 'Einstellungen', 'manage_options', 'promos-settings', $dispatcher);
    }
}

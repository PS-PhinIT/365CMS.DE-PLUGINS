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
        $capability = CMS_Promos_Admin_Pages::CAPABILITY;

        add_menu_page('Promos', '365CMS | Promos', $capability, $root, $dispatcher, 'ti-speakerphone');
        add_submenu_page($root, 'Übersicht', 'Übersicht', $capability, $root, $dispatcher);
        add_submenu_page($root, 'Promos', 'Promos', $capability, 'promos-items', $dispatcher);
        add_submenu_page($root, 'Platzierungen', 'Platzierungen', $capability, 'promos-placements', $dispatcher);
        add_submenu_page($root, 'Einstellungen', 'Einstellungen', $capability, 'promos-settings', $dispatcher);
    }
}

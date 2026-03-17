<?php

/**
 * Menu - Core Singleton
 * Orchestrates header navigation: walkers, ACF fields, menu filters.
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * 
 * License: GPL v2 or later
 * 
 * File: inc/menu/Core/MenuCore.php
 */

declare(strict_types=1);

namespace Menu\Core;

final class MenuCore
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // ACF field registration for menu item settings
        add_action('acf/init', [$this, 'registerAcfFields']);

        // Menu object filters
        add_filter('wp_nav_menu_objects', [$this, 'setMenuFlags'], 10, 2);

        // Clean empty submenus in header
        add_filter('wp_nav_menu_items', [$this, 'cleanEmptySubmenus'], 10, 2);

        // Allow <img> in wp_kses
        add_filter('wp_kses_allowed_html', [$this, 'allowMenuImages']);

        // Enqueue Interactivity API assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue Interactivity API assets for the menu store.
     * Runs globally — the header is on every page.
     * Only enqueued for guests (logged-in users don't need auth gate).
     */
    public function enqueueAssets(): void
    {
        if (is_user_logged_in()) {
            return;
        }

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/menu',
                get_template_directory_uri() . '/assets/js/menu-store.module.js',
                ['@wordpress/interactivity']
            );
            wp_enqueue_script_module('@starwishx/menu');
        }

        wp_interactivity_state('menu', [
            'isLoggedIn'    => false,
            'showAuthPopup' => false,
        ]);
    }

    /**
     * Register ACF field group for menu item settings.
     */
    public function registerAcfFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'    => 'group_menu_item_mobile',
            'title'  => 'Mobile menu',
            'fields' => [
                [
                    'key'           => 'field_mobile_submenu_type',
                    'label'         => 'Submenu type in the mobile version',
                    'name'          => 'mobile_submenu_type',
                    'type'          => 'select',
                    'choices'       => [
                        ''            => 'Regular item (submenu is not displayed)',
                        'custom_grid' => 'Custom grid (2 rows, like Opportunities)',
                    ],
                    'default_value' => false,
                    'allow_null'    => true,
                    'ui'            => true,
                ],
                [
                    'key'     => 'field_menu_auth_gate',
                    'label'   => 'Auth gate (requires login)',
                    'name'    => 'menu_auth_gate',
                    'type'    => 'true_false',
                    'ui'      => true,
                    'message' => 'Show auth popup for guests',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'nav_menu_item',
                        'operator' => '==',
                        'value'    => 'all',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Set `has-children` class and `mobile_submenu_type` property on menu items.
     *
     * @param array $items
     * @param object $args
     * @return array
     */
    public function setMenuFlags(array $items, object $args): array
    {
        $children = [];
        foreach ($items as $i) {
            if ($i->menu_item_parent) {
                $children[$i->menu_item_parent][] = $i;
            }
        }

        foreach ($items as &$item) {
            if (!empty($children[$item->ID])) {
                $item->classes[] = 'has-children';
            }
            $item->mobile_submenu_type = get_field('mobile_submenu_type', $item);
            $item->menu_auth_gate      = (bool) get_field('menu_auth_gate', $item);
        }

        return $items;
    }

    /**
     * Remove empty <ul class="sub-menu"></ul> from header menu output.
     *
     * @param string $items
     * @param object $args
     * @return string
     */
    public function cleanEmptySubmenus(string $items, object $args): string
    {
        if ($args->theme_location === 'menu-header') {
            $items = str_replace('<ul class="sub-menu"></ul>', '', $items);
        }

        return $items;
    }

    /**
     * Allow <img> tags in wp_kses output.
     *
     * @param array $tags
     * @return array
     */
    public function allowMenuImages(array $tags): array
    {
        $tags['img'] = ['src' => 1, 'alt' => 1, 'class' => 1, 'width' => 1, 'height' => 1];

        return $tags;
    }
}

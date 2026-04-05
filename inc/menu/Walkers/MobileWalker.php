<?php

/**
 * Mobile header menu walker.
 *
 * Renders top-level items only, with optional custom grid submenus
 * for items marked with `mobile_submenu_type = 'custom_grid'`.
 *
 * File: inc/menu/Walkers/MobileWalker.php
 */

declare(strict_types=1);

namespace Menu\Walkers;

class MobileWalker extends \Walker_Nav_Menu
{
    /** @var int[] Track which items opened an <li> for proper closing. */
    private array $opened_items = [];

    public function start_lvl(&$output, $depth = 0, $args = null) {}
    public function end_lvl(&$output, $depth = 0, $args = null) {}

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        if ($item->menu_item_parent) {
            return;
        }

        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes = apply_filters('nav_menu_css_class', array_filter($classes), $item, $args);

        if (in_array('hide-mobile', $classes, true)) {
            return;
        }

        $this->opened_items[] = $item->ID;

        $is_custom = (
            isset($item->mobile_submenu_type) &&
            $item->mobile_submenu_type === 'custom_grid' &&
            in_array('has-children', $classes, true)
        );

        if ($is_custom) {
            $classes[] = 'menu-item-custom-grid';
        }

        $class_names = implode(' ', $classes);

        $output .= '<li class="' . esc_attr($class_names) . '">';

        // Auth gate attribute for guests
        $auth_attr = '';
        if (!empty($item->menu_auth_gate) && !is_user_logged_in()) {
            $auth_attr = ' data-menu-auth data-wp-on--click="actions.handleAuthGate"';
        }

        // Standard link (same for both regular and custom_grid items)
        $output .= '<a class="menu-button" href="' . esc_url($item->url) . '"' . $auth_attr . '>';
        $output .= '<span>' . esc_html($item->title) . '</span>';

        if ($is_custom) {
            // sprites are not suitable for gradient 
            // $output .= '<svg class="arrow-icon" width="24" height="24" aria-hidden="true"><use xlink:href="' . get_template_directory_uri() . '/assets/img/sprites.svg#icon-stars"></use></svg>'; 
            $output .= '<img class="icon-menu-custom" width="24" height="24" aria-hidden="true" src="' . get_template_directory_uri() . '/assets/img/icon-stars-gradient.svg" alt="' . esc_attr($item->title) . '" >';
        }

        $output .= '</a>';

        // Custom grid submenu — always visible, no toggle
        if (!$is_custom) {
            return;
        }

        // Fetch children via get_posts (Phase 2 will replace with MenuDataProvider)
        $children = get_posts([
            'post_type'   => 'nav_menu_item',
            'numberposts' => -1,
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'meta_query'  => [['key' => '_menu_item_menu_item_parent', 'value' => $item->ID]],
        ]);

        if ($children) {
            $half = (int) ceil(count($children) / 2);
            $rows = array_chunk($children, $half);

            $output .= '<div class="mobile-submenu">';
            foreach ($rows as $row) {
                $output .= '<div class="mobile-submenu-row">';
                foreach ($row as $ch) {
                    $img_id = get_field('images', $ch->ID);
                    $url    = $img_id ? wp_get_attachment_image_url($img_id, 'full') : '';
                    $alt    = $img_id ? get_post_meta($img_id, '_wp_attachment_image_alt', true) : '';
                    $output .= '<a class="mobile-submenu-item" href="' . esc_url(get_post_meta($ch->ID, '_menu_item_url', true)) . '">';
                    if ($url) {
                        // Semantic: figure > picture > img + figcaption
                        // alt="" because figcaption provides the accessible name (avoids double announcement)
                        $output .= '<figure class="submenu-figure">';
                        $output .= '<picture>';
                        $output .= '<img src="' . esc_url($url) . '" class="submenu-bg-image" loading="lazy" alt="">';
                        $output .= '</picture>';
                        $output .= '<figcaption class="submenu-text">' . esc_html($ch->post_title) . '</figcaption>';
                        $output .= '</figure>';
                    } else {
                        $output .= '<span class="submenu-text">' . esc_html($ch->post_title) . '</span>';
                    }
                    $output .= '</a>';
                }
                $output .= '</div>';
            }
            $output .= '</div>';
        }
    }

    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        if (in_array($item->ID, $this->opened_items, true)) {
            $output .= '</li>';
        }
    }
}

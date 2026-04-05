<?php

/**
 * Desktop header menu walker.
 *
 * Renders the main navigation with submenu background images (ACF)
 * and chevron icons for parent items.
 *
 * File: inc/menu/Walkers/DesktopWalker.php
 */

declare(strict_types=1);

namespace Menu\Walkers;

class DesktopWalker extends \Walker_Nav_Menu
{
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        if ($depth === 0) {
            $output .= '<ul class="sub-menu">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        if ($depth === 0) {
            $output .= '</ul>';
        }
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes = apply_filters('nav_menu_css_class', array_filter($classes), $item, $args);

        $class_names = implode(' ', $classes);

        $output .= '<li class="' . esc_attr($class_names) . '">';

        $auth_attr = '';
        if (!empty($item->menu_auth_gate) && !is_user_logged_in()) {
            $auth_attr = ' data-menu-auth data-wp-on--click="actions.handleAuthGate"';
        }

        $output .= '<a class="submenu-link" href="' . esc_url($item->url) . '"' . $auth_attr . '>';

        if ($depth === 1 && ($img_id = get_field('images', $item))) {
            // Semantic: figure > picture > img + figcaption
            // alt="" because figcaption provides the accessible name (avoids double announcement)
            $output .= '<figure class="submenu-figure">';
            $output .= '<picture>';
            $output .= wp_get_attachment_image($img_id, 'medium_large', false, [
                'class'   => 'submenu-bg-image',
                'loading' => 'lazy',
                'alt'     => '',
            ]);
            $output .= '</picture>';
            $output .= '<figcaption class="submenu-text">' . esc_html($item->title) . '</figcaption>';
            $output .= '</figure>';
        } else {
            $output .= '<span class="submenu-text">' . $item->title . '</span>';
        }

        if ($depth === 0 && in_array('has-children', $classes, true)) {
            $output .= '<svg class="arrow-icon" width="24" height="24" aria-hidden="true">
                <use href="' . get_template_directory_uri() . '/assets/img/sprites.svg#icon-arrows"></use>
            </svg>';
        }

        $output .= '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        $output .= '</li>';
    }
}

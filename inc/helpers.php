<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('get_category_by_id')) {
    function get_category_by_id(array $category_color,  $category_id)
    {
        foreach ($category_color as $cat_item) {
            if ((int)$cat_item['category'] === $category_id) {
                return $cat_item;
            }
        }

        return null;
    }
}

if (!function_exists('pagination_url')) {
    function pagination_url($base_url, $page, $per_page)
    {
        return esc_url(add_query_arg([
            'page_num' => $page,
            'per_page' => $per_page,
        ], $base_url));
    }
}

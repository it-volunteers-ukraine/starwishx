<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('get_category_by_id')) {
    function get_category_by_id(array $category_color, int $category_id)
    {
        foreach ($category_color as $cat_item) {
            if ((int)$cat_item['category'] === $category_id) {
                return $cat_item;
            }
        }

        return null;
    }
}

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

if (!function_exists('my_category_color')) {
    function my_category_colors($category_id)
    {
        $categories_colors = get_field('categories_labels_color', 'options');
        foreach ($categories_colors as $cat_item) {
            if ((int)$cat_item['category'] === $category_id) {
                return $cat_item;
            }
        }
        $res = [
            'label_color_text' => 'white',
            'label_color_background' => 'grey',
            'label_color_border' => 'grey',
        ];
        return $res;
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

if (!function_exists('query_search')) {
    function query_search($args)
    {
        return new WP_Query($args);
    }
}

function get_url_params($name)
{
    if (isset($_GET[$name])) {
        return sanitize_text_field($_GET[$name]);
    }
}

// arguments:
// $post_type, $search_term, $sortby, $sort, $per_page, $page
// need for query 
// 'post_type'      => $post_type,
// 's'              => $search_term,
// 'orderby'        => $sortby, 
// 'order'          => $sort,
// 'posts_per_page' => $per_page,
// 'paged'          => $page,
if (!function_exists('query_args_prepare')) {

    function query_args_prepare($args)
    {
        // $list_args = ['post_type', 's', 'sortby', 'order', 'posts_per_page', 'paged'];

        $new_args = [];
        $blocks = parse_blocks(get_the_content());
        // echo 'blocks: ' . '<br>';
        // echo '<pre>';
        // print_r($blocks);
        // echo '</pre>';

        $block_params = seach_params_from_blocks($blocks, ['sortby', 'order', 'default_per_page']);

        $args['no_found_rows'] = false; // нужно для pagination

        //post_type
        $arg['spost_type'] = $args['post_type'] ?? my_post_type();

        // search
        if (isset($args->s)) {
            $new_args['s'] = $args->s;
        } elseif (isset($_GET['search'])) {
            $new_args['s'] = sanitize_text_field($_GET['search']);
        }

        // sortby
        if (isset($args->sortby)) {
            $new_args['sortby'] = $args->sortby;
        } elseif (isset($_GET['sortby'])) {
            $new_args['sortby'] = sanitize_text_field($_GET['sortby']);
        } else {
            if (isset($block_params['sortby'])) {
                $new_args['sortby'] = $block_params['sortby'];
            }
        }

        // order
        if (isset($args->order)) {
            $new_args['order'] = $args->order;
        } elseif (isset($_GET['order'])) {
            $new_args['order'] = sanitize_text_field($_GET['order']);
        } else {
            if (isset($block_params['order'])) {
                $new_args['order'] = $block_params['order'];
            }
        }

        // per_page
        if (isset($args->per_page)) {
            $new_args['per_page'] = $args->per_page;
        } elseif (isset($_GET['posts_per_page'])) {
            $new_args['posts_per_page'] = (int) sanitize_text_field($_GET['posts_per_page']);
        } else {
            if (isset($block_params['default_per_page'])) {
                $new_args['posts_per_page'] = (int) $block_params['default_per_page'];
            }
        }

        // paged
        if (isset($args->paged)) {
            $new_args['paged'] = $args->paged;
        } elseif (isset($_GET['page_num'])) {
            $new_args['paged'] = (int) sanitize_text_field($_GET['page_num']);
        } else {
            $new_args['paged'] = 1;
        }

        // echo '<pre>';
        // print_r($new_args);
        // echo '</pre>';

        return $new_args;
    }
}

if (!function_exists('seach_params_from_blocks')) {
    function seach_params_from_blocks($blocks, $search_params = [])
    {
        $result = [];
        foreach ($search_params as $param) {
            foreach ($blocks as $block) {
                // if ($block['blockName'] === 'acf/search-page') {
                if (isset($block['attrs']['data'][$param])) {
                    $result[$param] = $block['attrs']['data'][$param];
                    // return $block['attrs']['data'][$param];
                }
                // }
            }
        }
        // echo 'search: ' . $_GET['search'] . '<br>';
        // echo '$_SERVER[REQUEST_URI]' . $_SERVER['REQUEST_URI'] . '<br>';
        return $result;
    }
}

if (!function_exists('my_category')) {
    function my_category()
    {
        return 'category-oportunities';
    }
}


if (!function_exists('my_post_type')) {
    function my_post_type()
    {
        $request_path =  trim($_SERVER['REQUEST_URI'], '/');
        $parent  = explode('/', $request_path)[0];
        if ($parent === 'news') {
            $res = ['news'];
        } elseif ($parent === my_category()) {
            $res = ['news'];
            $res = ['opportunity'];
        } elseif ($parent === 'search') {
            $res = ['news', 'opportunity'];
        }
        return $res;
    }
}


// $args = [
//     'post_type'      => $post_type, // Или свой CPT
//     's'              => $search_term,
//     // 'fields'         => 'ids', // важно!
//     // 'no_found_rows'  => false, // нужно для pagination
//     'orderby'        => $sortby,
//     'order'          => $sort,
//     'posts_per_page' => $per_page,
//     'paged'          => $page,
//     // 'tax_query'      => [
//     // [
//     // 'taxonomy' => $category, // Или твоя кастомная taxonomy
//     // 'field'    => 'slug',
//     // 'terms'    => $category_slug,
//     // ]
//     // ]
// ];

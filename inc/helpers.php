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


// возвращает название категории slug 
if (!function_exists('my_category')) {
    function my_category()
    {
        return 'category-oportunities';
    }
}

if (!function_exists('my_category_by_slug')) {
    function my_category_by_slug($slug)
    {
        $category = my_category();
        $term = get_term_by('slug', $slug, $category);
        if ($term && !is_wp_error($term)) {
            return $term;
        }
        return null;
    }
}

// возвращает массив с цветами для категории, если категория не найдена, возвращает дефолтные цвета
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

// добавляет к URL пагинации параметры page_num и per_page
if (!function_exists('pagination_url')) {
    function pagination_url($base_url, $page, $per_page, $search='')
    {
        $args = [
            'page_num' => $page,
            'per_page' => $per_page,
        ];
        if ($search) {
            $args['search'] = $search;
        }

        return esc_url(add_query_arg($args, $base_url));
    }
}

// Функция для запроса постов 
// нужно еще добавить обработку таксономии и категорий, если нужно
if (!function_exists('my_query_search')) {
    function my_query_search($args)
    {
        // print_r($args);
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            foreach ($query->posts as $post_item) {
                $post_id = $post_item->ID;
                $terms = get_the_terms($post_id, my_category());

                // echo 'post_id: ' . $post_id . '<br>';
                // echo 'terms: ';
                // print_r($terms);
                // echo '<br>';

                if (!empty($terms) && !is_wp_error($terms)) {
                    $term_id = $terms[0]->term_id;
                    $term_name = $terms[0]->name;
                } else {
                    $term_id = null;
                    $term_name = null;
                }
                $post_item->term_id = $term_id;
                $post_item->term_name = $term_name;
                // echo '<pre>';
                // print_r($post_item);
                // echo '</pre>';
            }
        }
        wp_reset_postdata();
        return $query;
    }
}

// получает значение query параметра из URL, если параметр не найден, возвращает null 
// например, для URL /news/category-slug/?page_num=2&per_page=8
// get_url_params('page_num') вернет 2, get_url_params('per_page') вернет 8, get_url_params('nonexistent') вернет null
function get_url_params($name)
{
    if (isset($_GET[$name])) {
        return sanitize_text_field($_GET[$name]);
    }
    return null;
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
if (!function_exists('my_query_args_prepare')) {

    function my_query_args_prepare($args)
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
        $new_args['post_type'] = $args['post_type'] ?? my_post_type();

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
        } elseif (isset($_GET['per_page'])) {
            $new_args['posts_per_page'] = (int) sanitize_text_field($_GET['per_page']);
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

        $tax_query = my_taxonomy();
        if ($tax_query) {
            $new_args['tax_query'] = [$tax_query];
        }

        // echo '<pre>';
        // print_r($new_args);
        // echo '</pre>';

        return $new_args;
    }
}

// получает из блоков страницы значения параметров для поиска, например sortby, order, default_per_page
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





// проверяет нужно ли вернуть taxonomy для WP_Query, 
// например, для страницы news/news-by-category/kultura-ta-khobi/ нужно вернкуть tax_query 
// [
//     'taxonomy' => 'category-oportunities',
//     'field'    => 'slug',    
//     'terms'    => 'kultura-ta-khobi',
// ]
if (!function_exists('my_taxonomy')) {
    function my_taxonomy()
    {
        $category = my_category();
        $slug = get_query_var('news_cat');
        if ($slug) {
            return [
                'taxonomy' => $category,
                'field'    => 'slug',
                'terms'    => $slug,
            ];
        }   
        return null;
    }
}


if (!function_exists('my_post_type')) {
    function my_post_type($url = null)
    {
        if ($url === null) {
            $request_path = trim($_SERVER['REQUEST_URI'], '/');
        }else {
            $request_path = trim($url, '/');
        }
        $request_path = $request_path ? $request_path : $_GET('current_path', '');
        
        // echo 'request_path=' . esc_url($request_path) . '<br>';
        // print_r($request_path);
        // echo '<br>';

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
        // return $request_path;
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

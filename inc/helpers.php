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

function my_parent_category($term_id)
{
    $term = get_term($term_id);
    if ($term && !is_wp_error($term)) {
        // echo 'term_id: ' . $term_id . '<br>';
        if ($term->parent == 0) {
            return $term_id;
        } else {
            return my_parent_category($term->parent);
        }
    }
    return null;
}

// возвращает массив с цветами для категории, если категория не найдена, возвращает дефолтные цвета
if (!function_exists('my_category_color')) {
    function my_category_colors($category_id)
    {
        $categories_colors = get_field('categories_labels_color', 'options');
        $category_id = my_parent_category($category_id);
        // echo 'category_id: ' . $category_id . '<br>';
        // echo '<pre>';
        // print_r($new_args);
        // echo '</pre>';
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

function my_add_category_post($post_item)
{
    $category = my_category();
    $post_id = $post_item->ID;
    $terms = get_the_terms($post_id, $category);
    // echo '<pre>';
    // print_r($terms);
    // echo '</pre>';

    if (!empty($terms) && !is_wp_error($terms)) {
        $term_id = $terms[0]->term_id;
        $term_name = $terms[0]->name;
        $term_slug = $terms[0]->slug;
    } else {
        $term_id = null;
        $term_name = null;
        $term_slug = null;
    }
    $post_item->term_id = $term_id;
    $post_item->term_name = $term_name;
    // template-parts/news-card.php keys its label colour off the slug
    $post_item->term_slug = $term_slug;

    return $post_item;
}

function my_iter_posts_add_category($query)
{
    $result  = [];
    if (isset($query->posts)) {
        // echo 'have posts: ' . count($query->posts) . '<br>';
        foreach ($query->posts as $post_item) {
            $post_type = $post_item->post_type;
            $post_type_obj = get_post_type_object($post_type);
            $post_item->post_type_name = __($post_type_obj->labels->singular_name, 'starwishx');

            $new_post = my_add_category_post($post_item);
            array_push($result, $new_post);
        }
        $query->posts = $result;
    }
    return $query;
}

// добавляет к URL пагинации параметры page_num и per_page
// sortby/order пробрасываются из текущего запроса, иначе сортировка
// сбрасывалась при каждом переходе по страницам
if (!function_exists('pagination_url')) {
    function pagination_url($base_url, $page, $per_page, $search = '')
    {
        $args = [
            'page_num' => $page,
            'per_page' => $per_page,
        ];
        if ($search) {
            $args['search'] = $search;
        }

        $sort = sw_get_sort_params();
        if ($sort['orderby'] !== null) {
            $args['sortby'] = $sort['orderby'];
        }
        if ($sort['order'] !== null) {
            $args['order'] = $sort['order'];
        }

        return esc_url(add_query_arg($args, $base_url));
    }
}

/**
 * Допустимые значения posts_per_page.
 *
 * Единственный источник правды: и слой запроса, и блок пагинации берут
 * список отсюда, иначе счётчик страниц расходится с выдачей.
 */
if (!function_exists('sw_get_allowed_per_page')) {
    function sw_get_allowed_per_page(): array
    {
        $allowed = apply_filters('sw_allowed_per_page', [4, 8, 12]);
        $allowed = array_values(array_unique(array_filter(array_map('intval', (array) $allowed))));

        return $allowed ?: [12];
    }
}

/**
 * Значение per_page из запроса, приведённое к белому списку.
 */
if (!function_exists('sw_get_per_page')) {
    function sw_get_per_page(): int
    {
        $allowed = sw_get_allowed_per_page();
        $default = in_array(12, $allowed, true) ? 12 : end($allowed);

        if (!isset($_GET['per_page'])) {
            return $default;
        }

        $per_page = (int) sanitize_text_field(wp_unslash($_GET['per_page']));

        return in_array($per_page, $allowed, true) ? $per_page : $default;
    }
}

/**
 * sortby/order из запроса, приведённые к белому списку.
 *
 * WP_Query ждёт orderby/order — старый код писал в args ключ `sortby`,
 * поэтому сортировка не работала вообще.
 *
 * @return array{orderby: ?string, order: ?string}
 */
if (!function_exists('sw_get_sort_params')) {
    function sw_get_sort_params(): array
    {
        $allowed_orderby = ['date', 'title', 'modified', 'relevance'];

        $orderby = null;
        if (isset($_GET['sortby'])) {
            $candidate = strtolower(sanitize_text_field(wp_unslash($_GET['sortby'])));
            if (in_array($candidate, $allowed_orderby, true)) {
                $orderby = $candidate;
            }
        }

        $order = null;
        if (isset($_GET['order'])) {
            $candidate = strtoupper(sanitize_text_field(wp_unslash($_GET['order'])));
            if (in_array($candidate, ['ASC', 'DESC'], true)) {
                $order = $candidate;
            }
        }

        return ['orderby' => $orderby, 'order' => $order];
    }
}

/**
 * Мост между блоком, который отрисовал список, и блоком пагинации.
 *
 * Блок пагинации раньше выполнял собственный WP_Query с другими
 * аргументами, из-за чего счётчик страниц не совпадал с выдачей.
 * Теперь список публикует свой запрос, а пагинация его читает.
 *
 * @internal Временное решение: уходит вместе с блоком пагинации.
 */
if (!function_exists('sw_set_pagination_context')) {
    function sw_set_pagination_context(WP_Query $query, int $per_page): void
    {
        $GLOBALS['sw_pagination_context'] = [
            'query'    => $query,
            'per_page' => $per_page,
        ];
    }
}

if (!function_exists('sw_get_pagination_context')) {
    function sw_get_pagination_context(): ?array
    {
        return $GLOBALS['sw_pagination_context'] ?? null;
    }
}

// Функция для запроса постов 
// нужно еще добавить обработку таксономии и категорий, если нужно
if (!function_exists('my_query_search')) {
    function my_query_search($args)
    {
        if (isset($args['s'])) {
            $search_term = $args['s'];
            $sainitized_search_term = sanitize_text_field($search_term);

            if (strlen($sainitized_search_term) === 0) {
                $args['post__in'] = [0]; // если строка поиска пустая, возвращаем пустой результат
                // unset($args->s);
            }
        } else {
            // $args['post__in'] = [0]; // если строка поиска пустая, возвращаем пустой результат

        }
        // print_r($args);
        $query = new WP_Query($args);
        
        $query = my_iter_posts_add_category($query);
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
        $args     = is_array($args) ? $args : [];
        $new_args = [];

        $block_params = seach_params_from_blocks(
            sw_get_current_content_blocks(),
            ['sortby', 'order', 'default_per_page']
        );

        $new_args['post_status']   = 'publish';
        $new_args['no_found_rows'] = false; // нужно для pagination

        // post_type
        $new_args['post_type'] = $args['post_type'] ?? my_post_type();

        // search
        if (isset($args['s'])) {
            $new_args['s'] = sanitize_text_field((string) $args['s']);
        } elseif (isset($_GET['search'])) {
            $new_args['s'] = sanitize_text_field(wp_unslash($_GET['search']));
        }

        // orderby / order — WP_Query ждёт именно эти ключи
        $sort = sw_get_sort_params();

        $orderby = $args['orderby'] ?? $sort['orderby'] ?? $block_params['sortby'] ?? null;
        if ($orderby !== null && in_array($orderby, ['date', 'title', 'modified', 'relevance'], true)) {
            $new_args['orderby'] = $orderby;
        }

        $order = $args['order'] ?? $sort['order'] ?? $block_params['order'] ?? null;
        if ($order !== null && in_array(strtoupper((string) $order), ['ASC', 'DESC'], true)) {
            $new_args['order'] = strtoupper((string) $order);
        }

        // posts_per_page
        $allowed_per_page = sw_get_allowed_per_page();

        if (isset($args['posts_per_page'])) {
            $per_page = (int) $args['posts_per_page'];
        } elseif (isset($_GET['per_page'])) {
            $per_page = sw_get_per_page();
        } elseif (isset($block_params['default_per_page'])) {
            $per_page = (int) $block_params['default_per_page'];
        } else {
            $per_page = sw_get_per_page();
        }

        $new_args['posts_per_page'] = in_array($per_page, $allowed_per_page, true)
            ? $per_page
            : sw_get_per_page();

        // paged
        if (isset($args['paged'])) {
            $new_args['paged'] = max(1, (int) $args['paged']);
        } elseif (isset($_GET['page_num'])) {
            $new_args['paged'] = max(1, (int) sanitize_text_field(wp_unslash($_GET['page_num'])));
        } else {
            $new_args['paged'] = 1;
        }

        $tax_query = my_taxonomy();
        if ($tax_query) {
            $new_args['tax_query'] = [$tax_query];
        }

        return $new_args;
    }
}

/**
 * Блоки текущей записи, разобранные один раз за запрос.
 *
 * my_query_args_prepare() вызывается и блоком списка, и блоком пагинации,
 * поэтому parse_blocks() без кеша выполнялся дважды на страницу.
 */
if (!function_exists('sw_get_current_content_blocks')) {
    function sw_get_current_content_blocks(): array
    {
        static $cache = [];

        $post_id = get_the_ID();
        $key     = $post_id ? (int) $post_id : 0;

        if (!array_key_exists($key, $cache)) {
            $content      = $key ? (string) get_post_field('post_content', $key) : '';
            $cache[$key]  = $content !== '' ? parse_blocks($content) : [];
        }

        return $cache[$key];
    }
}

// получает из блоков страницы значения параметров для поиска, например sortby, order, default_per_page
if (!function_exists('seach_params_from_blocks')) {
    function seach_params_from_blocks($blocks, $search_params = [])
    {
        $result = [];

        foreach ((array) $blocks as $block) {
            foreach ($search_params as $param) {
                // Первое совпадение выигрывает: раньше цикл шёл до конца,
                // и значение перетирал последний блок на странице
                if (!isset($result[$param]) && isset($block['attrs']['data'][$param])) {
                    $result[$param] = $block['attrs']['data'][$param];
                }
            }

            if (!empty($block['innerBlocks'])) {
                $result += seach_params_from_blocks($block['innerBlocks'], $search_params);
            }
        }

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


// типы записей, участвующие в поиске по сайту
if (!function_exists('my_searchable_post_types')) {
    function my_searchable_post_types(): array
    {
        return ['news', 'opportunity', 'project'];
    }
}

/**
 * Определяет post_type по первому сегменту пути.
 *
 * Всегда возвращает массив: раньше при неизвестном сегменте возвращалась
 * неинициализированная переменная, а при пустом пути вызывался $_GET(...)
 * — обращение к массиву как к функции, то есть фатальная ошибка.
 */
if (!function_exists('my_post_type')) {
    function my_post_type($url = null): array
    {
        $raw = $url === null
            ? ($_SERVER['REQUEST_URI'] ?? '')
            : $url;

        $path         = (string) (wp_parse_url((string) $raw, PHP_URL_PATH) ?? '');
        $request_path = trim($path, '/');
        $parent       = $request_path === '' ? '' : explode('/', $request_path)[0];

        if ($parent === 'news') {
            return ['news'];
        }

        if ($parent === my_category()) {
            return ['opportunity'];
        }

        return my_searchable_post_types();
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

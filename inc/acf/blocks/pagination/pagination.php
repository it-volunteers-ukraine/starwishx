<?php

/**
 * Pagination for news by category / search results.
 *
 * URL format: ?page_num=2&per_page=8 (плюс sortby/order, если заданы)
 *
 * Счётчик страниц берётся из запроса, который отрисовал список
 * (sw_get_pagination_context()). Раньше блок выполнял собственный WP_Query
 * с другими аргументами: на страницах без ?search= он всегда получал
 * post__in => [0], то есть 0 результатов, и вся пагинация была мертва.
 */

$default_classes = [
    'section' => 'section',
    'pagination' => 'pagination',
    'pagination-section' => 'pagination-section',
    'pages' => 'pages',
    'form-select-perpage' => 'form-select-perpage',
    'select-perpage' => 'select-perpage',

    'selected' => 'selected',
    'nav-arrow' => 'nav-arrow',
    'nav-arrow-rotate' => 'nav-arrow-rotate',
    'nav-icon' => 'nav-icon',
    'link' => 'link',
    'link-disabled' => 'link-disabled',
    'page-num' => 'page-num',

    'arrow-icon' => 'arrow-icon',
    'load-more' => 'load-more'

];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['pagination'] ?? []);
}

// -----------------------------
// 1. Current URL
// -----------------------------
global $wp;

$base_url      = home_url($wp->request);
$category      = my_category();
$category_slug = get_query_var('news_cat');

// -----------------------------
// 2. Params
// -----------------------------
$page = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;

$btn_loadmore = (string) get_field('text_button_loadmore');
$btn_loading  = (string) get_field('text_button_loading');

$per_page_array_str  = (string) get_field('per_page_array_data');
$show_per_page_form  = $per_page_array_str !== '';
$allowed_per_page    = sw_get_allowed_per_page();

$search_term = isset($_GET['search'])
    ? sanitize_text_field(wp_unslash($_GET['search']))
    : '';

$no_desc      = false;
$card_version = 1;

if ($wp->request === 'search') {
    $no_desc      = true;
    $card_version = 2;
}

// -----------------------------
// 3. Counts — из запроса, отрисовавшего список
// -----------------------------
$context = sw_get_pagination_context();

if ($context) {
    $query    = $context['query'];
    $per_page = max(1, (int) $context['per_page']);
} else {
    // Фолбэк: блок списка ничего не опубликовал (например, блок пагинации
    // поставили на страницу в одиночку). Считаем по тем же аргументам.
    $count_args = my_query_args_prepare([]);
    $count_args['fields'] = 'ids';
    $per_page = max(1, (int) ($count_args['posts_per_page'] ?? sw_get_per_page()));
    $query = my_query_search($count_args);
}

$total_posts = (int) $query->found_posts;
$total_pages = (int) $query->max_num_pages;

wp_reset_postdata();

// -----------------------------
// 4. Page-number window (3 slots, clamped to the real range)
// -----------------------------
$window      = 3;
$window_last = max(1, $total_pages - $window + 1);
$window_from = $total_pages > 0 ? max(1, min($page - 1, $window_last)) : 1;

if ($page === 1) {
    $window_from = 1;
}

$prev_disabled = $page <= 1;
$next_disabled = $total_pages === 0 || $page >= $total_pages;

$load_next_page     = $page < $total_pages ? $page + 1 : $total_pages;
$load_more_disabled = $total_pages > 0 && $page >= $total_pages ? $classes['link-disabled'] : '';
$load_more_hidden   = $page >= $total_pages ? 'display: none' : '';

?>

<section class="section breadcumbs-section <?php echo esc_attr($classes["section"]); ?> ">
    <div class="container">
        <nav class="<?php echo esc_attr($classes["pagination"]); ?> ">

            <div class="btn-text-medium <?php echo esc_attr($classes['pages']); ?>">
                <!-- Prev -->
                <a id='pagination-prev'
                    href="<?= pagination_url($base_url, max(1, $page - 1), $per_page, $search_term); ?>"
                    class="<?php echo esc_attr($classes['nav-arrow']); ?> <?php echo esc_attr($classes['nav-arrow-rotate']); ?>"
                    data-link-disabled="<?php echo $prev_disabled ? '1' : '0'; ?>"
                    rel="prev">
                    <svg class="<?php echo esc_attr($classes["nav-icon"]); ?>">
                        <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow"></use>
                    </svg>
                </a>

                <!-- Numbers -->
                <?php for ($slot = 1; $slot <= $window; $slot++) :
                    $i             = $window_from + $slot - 1;
                    $link_disabled = $total_pages === 0 || $i > $total_pages;
                    $is_active     = $page === $i;
                ?>
                    <a id='pagination-<?php echo $slot; ?>'
                        href="<?= pagination_url($base_url, $i, $per_page, $search_term); ?>"
                        data-is-active="<?php echo $is_active ? '1' : '0'; ?>"
                        data-link-disabled="<?php echo $link_disabled ? '1' : '0'; ?>"
                        class="<?php echo esc_attr($classes['page-num']); ?>">
                        <?= (int) $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next -->
                <a id='pagination-next'
                    href="<?= pagination_url($base_url, $page + 1, $per_page, $search_term); ?>"
                    class="<?php echo esc_attr($classes['nav-arrow']); ?>"
                    data-link-disabled="<?php echo $next_disabled ? '1' : '0'; ?>"
                    rel="next">
                    <svg class="<?php echo esc_attr($classes["nav-icon"]); ?>">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow"></use>
                    </svg>
                </a>

            </div>

            <button
                id="load-more"
                type="button"
                data-page="<?php echo esc_attr((string) $page); ?>"
                data-category="<?php echo esc_attr($category); ?>"
                data-category-slug="<?php echo esc_attr((string) $category_slug); ?>"
                data-per-page="<?php echo esc_attr((string) $per_page); ?>"
                data-text-loadmore="<?php echo esc_attr($btn_loadmore); ?>"
                data-text-loading="<?php echo esc_attr($btn_loading); ?>"
                data-search="<?php echo esc_attr($search_term); ?>"
                data-nodesc="<?php echo $no_desc ? '1' : '0'; ?>"
                data-card-version="<?php echo esc_attr((string) $card_version); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('sw_load_news')); ?>"
                style="<?php echo esc_attr($load_more_hidden); ?>"
                class="btn <?php echo esc_attr($classes["load-more"]); ?>  <?php echo esc_attr($load_more_disabled); ?>">
                <?php echo esc_html($btn_loadmore); ?>
            </button>

            <?php if ($show_per_page_form) : ?>
                <form method="get" class="<?php echo esc_attr($classes['form-select-perpage']); ?>">

                    <input type="hidden" name="page_num" value="1">
                    <?php if ($search_term !== '') : ?>
                        <input type="hidden" name="search" value="<?php echo esc_attr($search_term); ?>">
                    <?php endif; ?>
                    <?php $sort = sw_get_sort_params(); ?>
                    <?php if ($sort['orderby'] !== null) : ?>
                        <input type="hidden" name="sortby" value="<?php echo esc_attr($sort['orderby']); ?>">
                    <?php endif; ?>
                    <?php if ($sort['order'] !== null) : ?>
                        <input type="hidden" name="order" value="<?php echo esc_attr($sort['order']); ?>">
                    <?php endif; ?>

                    <label class="screen-reader-text" for="per-page-select">
                        <?php esc_html_e('Items per page', 'starwishx'); ?>
                    </label>
                    <select id="per-page-select" name="per_page" class="btn-text-medium <?php echo esc_attr($classes["select-perpage"]); ?>" onchange="this.form.submit()">
                        <?php foreach ($allowed_per_page as $value) : ?>
                            <option value="<?= (int) $value; ?>" <?= selected($per_page, $value, false); ?>>
                                <?= (int) $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</section>

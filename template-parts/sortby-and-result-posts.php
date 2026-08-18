<?php

/**
 * Result count + sort control.
 *
 * `sortby_list` is an ACF repeater on the options page; row 0 carries the
 * defaults. The old markup also emitted a hardcoded Ukrainian third option
 * with value "0", which WP_Query silently ignored.
 *
 * @var array $args {
 *     @type int $total_posts Number of results found.
 * }
 */

declare(strict_types=1);

$total_posts = (int) ($args['total_posts'] ?? 0);

$found_name   = (string) get_field('found_posts_name', 'options');
$select_title = (string) get_field('sorting_select_title', 'options');

$sortby_list = get_field('sortby_list', 'options');
$defaults    = is_array($sortby_list) && isset($sortby_list[0]) ? $sortby_list[0] : [];

$desc_name = $defaults['desc_name'] ?? __('Newest first', 'starwishx');
$asc_name  = $defaults['asc_name']  ?? __('Oldest first', 'starwishx');

$sort    = sw_get_sort_params();
$sortby  = $sort['orderby'] ?? ($defaults['sortby'] ?? 'date');
$order   = $sort['order']   ?? ($defaults['order']  ?? 'DESC');

?>

<div class="text-r block-filter">
    <p>
        <?php echo esc_html($found_name ?: __('Found', 'starwishx')); ?>:
        <?php echo (int) $total_posts; ?>
    </p>

    <div class="sortby filter">
        <div class="sortby-title filter-title">
            <?php echo esc_html($select_title ?: __('Sorting', 'starwishx')); ?>:
        </div>

        <div class="custom-select btn-text-medium sortby-select">
            <form method="get">
                <?php if (is_search()) : ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr(get_search_query()); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['per_page'])) : ?>
                    <input type="hidden" name="per_page" value="<?php echo esc_attr((string) sw_get_per_page()); ?>">
                <?php endif; ?>
                <input type="hidden" name="sortby" value="<?php echo esc_attr($sortby); ?>">

                <label class="screen-reader-text" for="sort">
                    <?php esc_html_e('Sort order', 'starwishx'); ?>
                </label>
                <select name="order" id="sort" onchange="this.form.submit()">
                    <option value="DESC" <?php selected($order, 'DESC'); ?>><?php echo esc_html($desc_name); ?></option>
                    <option value="ASC" <?php selected($order, 'ASC'); ?>><?php echo esc_html($asc_name); ?></option>
                </select>
            </form>
            <svg class="sort-icon">
                <use href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-arrow_down'); ?>"></use>
            </svg>
        </div>
    </div>
</div>

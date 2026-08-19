<?php

/**
 * Result count + sort control.
 *
 * `sortby_list` is an ACF repeater on the options page; row 0 carries the
 * defaults. The old markup also emitted a hardcoded Ukrainian third option
 * with value "0", which WP_Query silently ignored.
 *
 * The control is a SelectDropdown of links rather than a <select> in a form:
 * it only changes a URL, and links keep working with JavaScript disabled.
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

$sort   = sw_get_sort_params();
$sortby = $sort['orderby'] ?? ($defaults['sortby'] ?? 'date');
$order  = $sort['order']   ?? ($defaults['order']  ?? 'DESC');

$order_options = [
    [
        'label'      => $desc_name,
        'url'        => sw_archive_url(['sortby' => $sortby, 'order' => 'DESC']),
        'is_current' => $order === 'DESC',
    ],
    [
        'label'      => $asc_name,
        'url'        => sw_archive_url(['sortby' => $sortby, 'order' => 'ASC']),
        'is_current' => $order === 'ASC',
    ],
];

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

        <?php
        echo \Shared\View\SelectDropdown::render($order_options, [
            'label' => $select_title ?: __('Sorting', 'starwishx'),
            'class' => 'sw-select--sort',
        ]);
        ?>
    </div>
</div>

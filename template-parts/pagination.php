<?php

/**
 * Pagination — shared across news, news-by-category and search.
 *
 * Reads the query it is paginating instead of running its own: the ACF
 * pagination block it replaces ran a second, differently-built query, which
 * is why its counts disagreed with the list on screen.
 *
 * Pretty URLs (/page/2/) come from paginate_links(); per_page, sortby and
 * order ride along as query args so the choice survives a page change.
 * "Load more" is progressive enhancement on top — the numbered links work
 * with JavaScript disabled.
 *
 * @var array $args {
 *     @type WP_Query $query          Query to paginate. Defaults to the main query.
 *     @type bool     $show_per_page  Render the items-per-page select. Default true.
 *     @type bool     $show_load_more Render the "Load more" button. Default true.
 * }
 */

declare(strict_types=1);

$args  = $args ?? [];
$query = $args['query'] ?? $GLOBALS['wp_query'];

$show_per_page  = $args['show_per_page'] ?? true;
$show_load_more = $args['show_load_more'] ?? true;

$total_pages = (int) $query->max_num_pages;
$current     = max(1, (int) ($query->get('paged') ?: 1));

$allowed_per_page = sw_get_allowed_per_page();
$per_page         = sw_get_per_page();
$sort             = sw_get_sort_params();

// Query args that must survive a page change.
// Only what the reader actually chose: the default per_page would otherwise
// show up in every URL, and on pretty permalinks the search term is already
// in the path (/search/a/page/2/).
$add_args = [];

if (isset($_GET['per_page'])) {
    $add_args['per_page'] = $per_page;
}
if ($sort['orderby'] !== null) {
    $add_args['sortby'] = $sort['orderby'];
}
if ($sort['order'] !== null) {
    $add_args['order'] = $sort['order'];
}
if (is_search() && !$GLOBALS['wp_rewrite']->using_permalinks()) {
    $add_args['s'] = get_search_query();
}

$sprite = get_template_directory_uri() . '/assets/img/sprites.svg';

$arrow = static fn(string $extra): string => sprintf(
    '<svg class="sw-pagination__icon %s" aria-hidden="true" focusable="false"><use href="%s#icon-arrow"></use></svg>',
    esc_attr($extra),
    esc_url($sprite)
);

$links = $total_pages > 1
    ? paginate_links([
        'current'   => $current,
        'total'     => $total_pages,
        'mid_size'  => 1,
        'end_size'  => 1,
        'add_args'  => $add_args,
        'type'      => 'plain',
        'prev_text' => $arrow('sw-pagination__icon--prev'),
        'next_text' => $arrow(''),
    ])
    : '';

if (!$links && !$show_per_page) {
    return;
}

// Application state for the load-more store. Infrastructure state
// (restUrl, context, i18n) is hydrated by NewsCore::hydrateState().
$load_more = $show_load_more && $total_pages > 1 && function_exists('wp_interactivity_state');

if ($load_more) {
    wp_interactivity_state('news', [
        'page'       => $current,
        'maxPages'   => $total_pages,
        'perPage'    => $per_page,
        'slug'       => (string) get_query_var(\News\Core\NewsCore::QUERY_VAR),
        'searchTerm' => is_search() ? get_search_query() : '',
        'orderby'    => $sort['orderby'] ?? '',
        'order'      => $sort['order'] ?? '',
    ]);
}
?>

<section class="section sw-pagination-section" <?php echo $load_more ? 'data-wp-interactive="news"' : ''; ?>>
    <div class="container">

        <?php if ($load_more) : ?>
            <div class="sw-pagination__alert" role="alert" data-wp-bind--hidden="!state.error">
                <span data-wp-text="state.error"></span>
                <button type="button" class="sw-pagination__alert-close" data-wp-on--click="actions.dismissError">
                    <?php esc_html_e('Dismiss', 'starwishx'); ?>
                </button>
            </div>
        <?php endif; ?>

        <nav class="sw-pagination" aria-label="<?php esc_attr_e('Pagination', 'starwishx'); ?>">

            <?php if ($links) : ?>
                <div class="btn-text-medium sw-pagination__pages">
                    <?php
                    // paginate_links() escapes its own URLs and page numbers;
                    // prev/next markup is built above from theme constants.
                    echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($load_more) : ?>
                <button
                    type="button"
                    class="btn sw-pagination__load-more"
                    data-wp-on--click="actions.loadMore"
                    data-wp-bind--disabled="state.loading"
                    data-wp-bind--hidden="!state.hasMore"
                    data-wp-text="state.buttonLabel">
                    <?php esc_html_e('Show more', 'starwishx'); ?>
                </button>
            <?php endif; ?>

            <?php
            if ($show_per_page) {
                $per_page_options = array_map(
                    static fn(int $value): array => [
                        'label'      => (string) $value,
                        'url'        => sw_archive_url(['per_page' => $value]),
                        'is_current' => $value === $per_page,
                    ],
                    $allowed_per_page
                );

                echo \Shared\View\SelectDropdown::render($per_page_options, [
                    'label' => __('Items per page', 'starwishx'),
                    'class' => 'sw-select--perpage sw-pagination__perpage',
                ]);
            }
            ?>

        </nav>
    </div>
</section>

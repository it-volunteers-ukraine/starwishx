<?php

/**
 * The template for displaying Opportunities archive
 *
 * File: archive-opportunity.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * BOOTSTRAP
 * Access the Listing singleton via our helper.
 */
if (!function_exists('listing')) {
    require_once get_template_directory() . '/inc/listing/helpers.php';
}

$listing = \listing();

/**
 * STATE HYDRATION (SSR)
 * Seed the 'listing' namespace using the authoritative Core aggregator.
 * When a pretty category URL is active (/opportunities/{slug}/), inject
 * the slug into the filter pipeline so SSR matches the URL context.
 */
$filterOverrides = [];
$listingCat = get_query_var('listing_cat');
if ($listingCat) {
    $filterOverrides['category'] = sanitize_title($listingCat);
}
wp_interactivity_state('listing', $listing->getState($filterOverrides));

/**
 * ASSET FOUC PROTECTION
 * We use the same pattern as Launchpad/Gateway for immediate CSS hiding.
 */
add_action('wp_head', function () {
?>
    <style id="listing-fouc-shield">
        .listing-filter-group[hidden],
        .listing-grid[hidden] {
            display: none !important;
        }

        .popup[hidden],
        .listing-backdrop[hidden] {
            display: none !important;
        }
    </style>
<?php
}, 1);

$css = sw_get_taxonomy_top_level_colors_styles('category-oportunities');
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles', false);
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

get_header();
?>
<?php
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [
            'data'        => [
                'show_last_item' => true,
                'nowrap'         => true,
                'nav_class'      => 'container',
            ],
        ],
        'innerHTML'   => '',
        'innerBlocks' => [],
    ]);
}
?>
<main id="primary" class="site-main listing-app container" data-wp-interactive="listing">

    <header class="listing-app__header">
        <div class="listing-app__header--text">
            <h1 class="page-title"><?= esc_html__('Opportunities', 'starwishx'); ?></h1>
            <span class="page-subtitle" data-wp-bind--hidden="!state.hasSelectedCategory" hidden>
                <?= esc_html__('Category', 'starwishx'); ?>:&nbsp;
                <span class="page-subtitle--text" data-wp-text="state.selectedCategoryName"></span>
            </span>
        </div>
        <figure class="listing-app__header--figure" data-wp-bind--hidden="!state.hasSelectedCategory">
            <img class="listing-app__header--image" width="100" height="70" src="https://starwish.local/wp-content/uploads/2025/04/sp11-300x200.jpg" data-wp-bind--alt="state.selectedCategoryName">
        </figure>
    </header>

    <div class="listing-controls">
        <button class="btn-show-filters btn" data-wp-on--click="actions.toggleSidebar">
            <?= sw_svg('icon-filter', 16); ?>
            <?php esc_html_e('Filters', 'starwishx'); ?>
            <span
                class="filter-count-badge"
                data-wp-text="state.activeFiltersCountLabel"
                data-wp-bind--hidden="!state.hasActiveFilters"
                hidden></span>
        </button>
    </div>

    <div class="listing-layout">
        <!-- SIDEBAR: Managed by the Filter Registry -->
        <div
            class="listing-backdrop"
            data-wp-bind--hidden="!state.isSidebarOpen"
            data-wp-on--click="actions.closeSidebar"
            hidden></div>
        <aside class="listing-sidebar" data-wp-class--is-open="state.isSidebarOpen">
            <div class="listing-sidebar__header">
                <h2 class="sidebar-title"><?php esc_html_e('Refine Search', 'starwishx'); ?></h2>
                <!-- <button
                    type="button"
                    class="listing-sidebar__close"
                    data-wp-on--click="actions.closeSidebar"
                    aria-label="< ?php esc_attr_e('Close filters', 'starwishx'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </button> -->
            </div>
            <div class="listing-status">
                <span class="listing-status-label">
                    <!-- < ?= sw_svg('icon-opportunities'); ?> -->
                    <img width="20" height="20" src="<?= get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="Icon star">

                    <span class="status-label__title">
                        <?= esc_html__('Found', 'starwishx'); ?>:
                    </span>
                    <span class="status-label__info" data-wp-text="state.resultsFoundLabel"></span>
                </span>
                <button class="btn-tertiary" data-wp-on--click="actions.filters.clearAll">
                    <?php esc_html_e('Clear all', 'starwishx'); ?>
                </button>
            </div>


            <div class="listing-filters">
                <details
                    class="listing-filter-group"
                    id="filter-group-<?php echo esc_attr($id); ?>"
                    data-wp-interactive="listing"
                    data-wp-context='{ "filterId": "<?php echo esc_attr($id); ?>" }'
                    data-wp-class--has-selection="callbacks.hasActiveFilter"
                    open>

                    <summary class="listing-filter-group__summary">
                        <span class="listing-filter-group__title">
                            <?php esc_html_e('Keyword search', 'starwishx'); ?>
                            <span class="filter-count-badge" data-wp-text="callbacks.getActiveFilterCount"></span>
                        </span>
                        <svg class="icon-chevron">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow-down"></use>
                        </svg>
                    </summary>

                    <div class="listing-filter-group__content">
                        <div class="listing-search-wrapper">
                            <input
                                type="search"
                                class="listing-search-input"
                                placeholder="<?php esc_attr_e('Search by keyword...', 'starwishx'); ?>"
                                data-wp-bind--value="state.query.s"
                                data-wp-on--input="actions.filters.updateSearch">
                        </div>
                    </div>
                </details>
                <?php
                // Registry pattern: Iterate through registered filters
                foreach ($listing->registry()->getAll() as $filter) {
                    echo $filter->render();
                }
                ?>
            </div>

            <button
                type="button"
                class="sidebar-btn__close btn-secondary__small"
                data-wp-on--click="actions.closeSidebar"
                aria-label="<?php esc_attr_e('Close filters', 'starwishx'); ?>">
                <?= esc_html__('Close', 'starwishx'); ?>
            </button>
        </aside>

        <!-- CONTENT: Search Bar and Results Grid -->
        <div class="listing-content">
            <?php echo $listing->renderGrid(); ?>
        </div>

    </div>
</main>

<?php
get_template_part('template-parts/element-popup', null, [
    'title' => __('Hi!', 'starwishx'),
    'text'  => __('Add to favorites is only available for registered users.', 'starwishx'),
    'id'    => 'listing-auth-popup',
]);

get_footer();

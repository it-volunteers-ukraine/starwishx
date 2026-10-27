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
sw_breadcrumbs();

/**
 * SERVER RENDER
 * <main> goes through the Interactivity API on the server too: its
 * directives are evaluated against the state hydrated above, including the
 * PHP twins of the store's getters (Listing\Core\DerivedState), so the first
 * paint already has the results, counts, chips and category header. Should
 * the markup ever be unbalanced, core returns it unprocessed and the client
 * renders it as before.
 */
ob_start();
?>
<main id="primary" class="site-main listing-app container" data-wp-interactive="listing">

    <header class="listing-app__header">
        <div class="listing-app__header--text">
            <h1 class="page-title"><?= esc_html__('Opportunities', 'starwishx'); ?></h1>
            <span class="page-subtitle" data-wp-bind--hidden="!state.hasSelectedCategory" hidden>
                <?= esc_html__('Category', 'starwishx'); ?>:&nbsp;
                <span class="page-subtitle--text" data-wp-text="state.selectedCategoryName"></span>
            </span>
            <p class="listing-app__header--description"
                data-wp-bind--hidden="!state.selectedCategoryDescription"
                data-wp-text="state.selectedCategoryDescription"
                hidden></p>
        </div>
        <figure class="listing-app__header--figure"
            data-wp-bind--hidden="!state.selectedCategoryImageUrl"
            hidden>
            <img class="listing-app__header--image"
                width="100" height="70"
                alt=""
                data-wp-bind--src="state.selectedCategoryImageUrl">
        </figure>
    </header>

    <div class="listing-controls">
        <button
            type="button"
            class="btn-show-filters btn"
            aria-controls="listing-sidebar"
            aria-expanded="false"
            data-wp-bind--aria-expanded="state.isSidebarOpen"
            data-wp-on--click="actions.toggleSidebar">
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
        <?php
        // Below the laptop breakpoint this is an off-canvas drawer opened by
        // .btn-show-filters (aria-controls). While it is open, Tab stays
        // inside it, and focus moves to the close button on open and back to
        // the toggle on close (actions.trapSidebarFocus,
        // callbacks.syncSidebarFocus). The close button only shows there. It
        // sits outside .listing-sidebar__body, the drawer's scroll container,
        // so it can hang over the panel's edge and stays put while the rest
        // scrolls.
        ?>
        <aside
            id="listing-sidebar"
            class="listing-sidebar"
            aria-labelledby="listing-sidebar-title"
            data-wp-class--is-open="state.isSidebarOpen"
            data-wp-on--keydown="actions.trapSidebarFocus"
            data-wp-watch="callbacks.syncSidebarFocus">
            <button
                type="button"
                class="listing-sidebar__close"
                data-wp-on--click="actions.closeSidebar"
                aria-label="<?php esc_attr_e('Close filters', 'starwishx'); ?>">
                <?= sw_svg('icon-close', 20); ?>
            </button>
            <div class="listing-sidebar__body">
                <div class="listing-sidebar__header">
                    <h2 id="listing-sidebar-title" class="sidebar-title"><?php esc_html_e('Refine Search', 'starwishx'); ?></h2>
                </div>
                <div class="listing-status">
                    <span class="listing-status-label">
                        <!-- < ?= sw_svg('icon-opportunities'); ?> -->
                        <img width="20" height="20" src="<?= get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="">

                        <span class="status-label__title">
                            <?= esc_html__('Found', 'starwishx'); ?>:
                        </span>
                        <span class="status-label__info" data-wp-text="state.resultsFoundLabel"></span>
                    </span>
                    <button type="button" class="btn-tertiary" data-wp-on--click="actions.filters.clearAll">
                        <?php esc_html_e('Clear all', 'starwishx'); ?>
                    </button>
                </div>


                <div class="listing-filters">
                    <?php
                    // Keyword search is the one filter group written by hand rather
                    // than rendered from the registry, so it has no filter object to
                    // take an id from. It used to interpolate $id, which is not a
                    // template variable at all - load_template() globalises $id, so
                    // it silently picked up WordPress's current-post id and the two
                    // callbacks below never resolved. 's' is the key the input binds
                    // to on state.query.
                    ?>
                    <details
                        class="listing-filter-group"
                        id="filter-group-s"
                        data-wp-interactive="listing"
                        data-wp-context='{ "filterId": "s" }'
                        data-wp-class--has-selection="state.filterHasSelection"
                        open>

                        <summary class="listing-filter-group__summary">
                            <span class="listing-filter-group__title">
                                <span id="filter-title-s"><?php esc_html_e('Keyword search', 'starwishx'); ?></span>
                                <span class="filter-count-badge" data-wp-text="state.filterCountLabel"></span>
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
                                    aria-labelledby="filter-title-s"
                                    maxlength="<?php echo (int) \Listing\Services\ListingService::SEARCH_MAX_LENGTH; ?>"
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
            </div>
        </aside>

        <!-- CONTENT: Search Bar and Results Grid -->
        <div class="listing-content">
            <?php echo $listing->renderGrid(); ?>
        </div>

    </div>
</main>

<?php
echo wp_interactivity_process_directives(ob_get_clean());

get_template_part('template-parts/element-popup', null, [
    'title' => __('Hi!', 'starwishx'),
    'text'  => __('Add to favorites is only available for registered users.', 'starwishx'),
    'id'    => 'listing-auth-popup',
]);

get_footer();

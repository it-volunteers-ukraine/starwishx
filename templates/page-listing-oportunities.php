<?php

/**
 * Template Name: Listing Page
 * File: templates/page-listing-oportunities.php
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
 */
wp_interactivity_state('listing', $listing->getState());

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

<main id="primary" class="site-main listing-app container" data-wp-interactive="listing">

    <header class="listing-app__header">
        <h1 class="page-title"><?php the_title(); ?></h1>
    </header>

    <div class="listing-layout">

        <!-- SIDEBAR: Managed by the Filter Registry -->
        <aside class="listing-sidebar">
            <h2 class="sidebar-title"><?php esc_html_e('Refine Search', 'starwishx'); ?></h2>
            <div class="listing-status-info">
                <span data-wp-text="state.resultsFoundLabel"></span>
            </div>
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

            <div class="listing-filters">
                <?php
                // Registry pattern: Iterate through registered filters
                foreach ($listing->registry()->getAll() as $filter) {
                    echo $filter->render();
                }
                ?>
            </div>
        </aside>

        <!-- CONTENT: Search Bar and Results Grid -->
        <div class="listing-content">
            <?php echo $listing->renderGrid(); ?>
        </div>

    </div>
</main>

<?php

get_footer();

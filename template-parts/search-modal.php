<?php

/**
 * Site search modal.
 *
 * Submits `s` to the home URL so WordPress handles the request natively and
 * canonicalises it to /search/{term}/. It used to post `search` to a WP page,
 * which meant is_search() was never true.
 */

$sort = sw_get_sort_params();
?>
<div id="searchModal" class="modal" tabindex="-1">
    <div class="modal-content modal-main">
        <form id="form-search" role="search" class="search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="search-input">
                <?php esc_html_e('Search the site', 'starwishx'); ?>
            </label>
            <input
                type="search"
                id="search-input"
                name="s"
                class="search-input"
                value="<?php echo esc_attr(get_search_query()); ?>"
                placeholder="<?php esc_attr_e('Enter a search term', 'starwishx'); ?>">

            <?php if ($sort['orderby'] !== null) : ?>
                <input type="hidden" name="sortby" value="<?php echo esc_attr($sort['orderby']); ?>">
            <?php endif; ?>
            <?php if ($sort['order'] !== null) : ?>
                <input type="hidden" name="order" value="<?php echo esc_attr($sort['order']); ?>">
            <?php endif; ?>

            <div id="clear-form" class="form-clear-btn">
                <svg class="form-clear-icon">
                    <use href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-close'); ?>"></use>
                </svg>
            </div>
            <button type="submit" class="search-submit-bth">
                <span class="screen-reader-text"><?php esc_html_e('Search', 'starwishx'); ?></span>
                <svg class="search-submit-icon">
                    <use href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-find'); ?>"></use>
                </svg>
            </button>
        </form>
    </div>
</div>

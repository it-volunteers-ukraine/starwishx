<?php

/**
 * Site search modal.
 *
 * Submits `s` to the home URL so WordPress handles the request natively and
 * canonicalises it to /search/{term}/. It used to post `search` to a WP page,
 * which meant is_search() was never true.
 *
 * A native <dialog>, opened with showModal() by the header buttons that carry
 * aria-controls="searchModal" (src/js/_search-dialog.js): focus containment,
 * Escape, the inert page behind it and focus return come from the browser.
 */

$sort = sw_get_sort_params();
?>
<dialog id="searchModal" class="modal" aria-label="<?php esc_attr_e('Search the site', 'starwishx'); ?>">
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

            <button type="button" class="form-clear-btn" aria-label="<?php esc_attr_e('Clear', 'starwishx'); ?>">
                <?php sw_svg_e('icon-close', 20, null, 'search-clear-icon'); ?>
            </button>
            <button type="submit" class="search-submit-bth">
                <span class="screen-reader-text"><?php esc_html_e('Search', 'starwishx'); ?></span>
                <?php sw_svg_e('icon-find', 20, null, 'search-submit-icon'); ?>
            </button>
        </form>
    </div>
</dialog>
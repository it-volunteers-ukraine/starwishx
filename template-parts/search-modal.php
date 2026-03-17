<?php
$sortby_list = get_field('sortby_list', 'options');
$sortby      = isset($_GET['sortby']) ? $_GET['sortby'] : '';
$order       = isset($_GET['order']) ? $_GET['order'] : '';

// $sortby = isset($sortby_list['sortby']) ? $sortby_list['sortby'] : 'date';
?>
<div id="searchModal" class="modal" tabindex="-1">
    <div class="modal-content modal-main">
        <form id="form-search" role="search" class="search-form" method="get" action="<?= home_url('/search'); ?>">
            <input type="text" name="search" class="search-input" placeholder="<?= __('Enter a search term', 'starwishx'); ?>">
            <?php if ($sortby) : ?>
                <input type="hidden" name="sortby" value="<?= esc_attr($sortby); ?>">
            <?php endif; ?>
            <?php if ($order) : ?>
                <input type="hidden" name="order" value="<?= esc_attr($order); ?>">
            <?php endif; ?>
            <!-- <span id="clear-form" class="clear-form-icon">&times;</span> -->
            <div id="clear-form" class="form-clear-btn">
                <svg class="form-clear-icon">
                    <use href="<?= esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-close'); ?>"></use>
                </svg>
            </div>
            <button type="submit" class="search-submit-bth">
                <svg class="search-submit-icon">
                    <use href="<?= esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-find'); ?>"></use>
                </svg>
            </button>
        </form>
    </div>
</div>
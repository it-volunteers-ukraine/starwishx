<?php

/**
 * Favorites Control (Frontend Version)
 * 
 * File: template-parts/control-favorites.php
 */

declare(strict_types=1);

$post_id = $args['post_id'] ?? get_the_ID();
?>

<div
    class="control-favorites-wrapper"
    data-wp-interactive="starwishx/opportunities"
    data-wp-context='{ "id": <?= (int)$post_id ?> }'>
    <label
        for="favorite-<?= esc_attr($post_id) ?>"
        class="heart-label"
        data-wp-class--is-active="state.isFavorite">
        <span class="heart-label__text">
            <span class="heart-label__text--inactive"><?php esc_html_e('Add to favorites', 'starwishx'); ?></span>
            <span class="heart-label__text--active"><?php esc_html_e('In favorites', 'starwishx'); ?></span>
        </span>
    </label>
    <div class="heart">

        <input
            type="checkbox"
            class="heart__checkbox"
            id="favorite-<?= esc_attr($post_id) ?>"
            data-id="<?= esc_attr($post_id) ?>"
            data-wp-bind--checked="state.isFavorite"
            data-wp-on--change="actions.toggle"
            aria-label="<?php esc_attr_e('Toggle Favorite', 'starwishx'); ?>">
        <div class="heart__icon"></div>
        <div class="heart__lines" aria-hidden="true">
            <span class="heart__line"></span>
            <span class="heart__line"></span>
            <span class="heart__line"></span>
            <span class="heart__line"></span>
            <span class="heart__line"></span>
            <span class="heart__line"></span>
        </div>
    </div>
</div>
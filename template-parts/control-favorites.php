<?php

/**
 * Favorites Control (Frontend Version)
 * Namespace: starwishx/opportunities
 */

declare(strict_types=1);
$post_id = $args['post_id'] ?? get_the_ID();
?>

<div
    class="control-favorites-wrapper"
    data-wp-interactive="starwishx/opportunities"
    data-wp-context='{ "id": <?= (int)$post_id ?> }'>

    <input
        type="checkbox"
        class="btn-favorite__checkbox"
        id="favorite-<?= esc_attr($post_id) ?>"
        data-id="<?= esc_attr($post_id) ?>"
        data-wp-bind--checked="state.isFavorite"
        data-wp-on--change="actions.toggle"
        aria-label="<?php esc_attr_e('Toggle Favorite', 'starwishx'); ?>">

    <label
        for="favorite-<?= esc_attr($post_id) ?>"
        class="btn-favorite"
        data-wp-class--is-active="state.isFavorite">
        <!-- Heart Icon (emoji-based, changes with state) -->
        <span class="btn-favorite__icon" aria-hidden="true">
            <span class="btn-favorite__icon--inactive">🤍</span>
            <span class="btn-favorite__icon--active">💛</span>
        </span>
        <!-- Label Text (changes with state) -->
        <span class="btn-favorite__text">
            <span class="btn-favorite__text--inactive"><?php esc_html_e('Add to favorites', 'starwishx'); ?></span>
            <span class="btn-favorite__text--active"><?php esc_html_e('In favorites', 'starwishx'); ?></span>
        </span>
    </label>
</div>
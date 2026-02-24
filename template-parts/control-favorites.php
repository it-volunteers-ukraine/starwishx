<?php

/**
 * Favorites Control (Frontend Version)
 *
 * File: template-parts/control-favorites.php
 *
 * Arguments:
 * @var int  $args['post_id']    Optional. Post ID. Defaults to current global ID.
 * @var bool $args['show_label'] Optional. Whether to display the text label. Defaults to true.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

// Data Normalization
$post_id    = isset($args['post_id']) ? (int) $args['post_id'] : get_the_ID();
$show_label = isset($args['show_label']) ? (bool) $args['show_label'] : true;

// Dynamic modifier class so CSS can adjust margins/gaps when the label is missing
$wrapper_classes = ['control-favorites-wrapper'];
if (! $show_label) {
    $wrapper_classes[] = 'control-favorites-wrapper--icon-only';
}
?>

<div
    class="<?php esc_attr_e(implode(' ', $wrapper_classes)) ?>"
    data-wp-interactive="starwishx/opportunities"
    data-wp-context='{ "id": <?= (int)$post_id ?> }'>
    <?php if ($show_label) : ?>
        <label
            for="favorite-<?= esc_attr($post_id) ?>"
            class="heart-label"
            data-wp-class--is-active="state.isFavorite">
            <span class="heart-label__text">
                <span class="heart-label__text--inactive"><?php esc_html_e('Додати до обраних', 'starwishx'); ?></span>
                <span class="heart-label__text--active"><?php esc_html_e('Обране', 'starwishx'); ?></span>
            </span>
        </label>
    <?php endif; ?>

    <div class="heart" title="<?php esc_attr_e('Обране', 'starwishx'); ?>">
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
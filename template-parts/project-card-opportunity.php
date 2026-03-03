<?php

/**
 * Template Part: Compact Opportunity Card (for project tabs)
 *
 * Used inside data-wp-each loop - context.item provides card data.
 * Fields: item.id, item.title, item.url, item.excerpt, item.thumbnail, item.date
 *
 * File: template-parts/project-card-opportunity.php
 */

defined('ABSPATH') || exit;
?>

<div class="project-card project-card--opportunity">
    <a data-wp-bind--href="context.item.url" class="project-card__link">
        <div class="project-card__image-wrapper">
            <img
                class="project-card__image"
                data-wp-bind--src="context.item.thumbnail"
                data-wp-bind--alt="context.item.title"
                data-wp-bind--hidden="!context.item.thumbnail" 
                loading="lazy" />
            <div class="control-favorites-wrapper control-favorites-wrapper--icon-only">
                <div class="heart"
                    title="<?php esc_attr_e('Favorites', 'starwishx'); ?>">
                    <input
                        type="checkbox"
                        class="heart__checkbox"
                        data-wp-bind--checked="state.isFavorited"
                        data-wp-on--change="actions.toggleFavorite"
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
            <div class="project-card__image-placeholder" data-wp-bind--hidden="context.item.thumbnail">
                <img class="project-card__fallback-icon" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="">
            </div>
        </div>
        <div class="project-card__body">
            <span class="project-card__date" data-wp-text="context.item.date"></span>
            <h3 class="project-card__title" data-wp-text="context.item.title"></h3>
            <p class="project-card__excerpt" data-wp-text="context.item.excerpt"></p>
        </div>
    </a>
</div>
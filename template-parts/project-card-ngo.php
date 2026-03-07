<?php

/**
 * Template Part: Compact NGO Card (for project tabs)
 *
 * Used inside data-wp-each loop — context.item provides card data.
 * Fields: item.id, item.title, item.url, item.excerpt, item.thumbnail, item.date
 *
 * Separate file for future divergence (NGOs may show org type, etc.)
 *
 * File: template-parts/project-card-ngo.php
 */

defined('ABSPATH') || exit;
?>

<article class="project-card project-card--ngo">
    <div class="project-card__link">
        <figure class="project-card__image-wrapper">
            <img
                class="project-card__image"
                data-wp-bind--src="context.item.thumbnail"
                data-wp-bind--alt="context.item.title"
                loading="lazy" />
            <div class="project-card__image-placeholder" data-wp-bind--hidden="context.item.thumbnail">
                <img class="project-card__fallback-icon" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="context.item.title">
            </div>
        </figure>
    </div>
    <div class="project-card__body">
        <h3 class="project-card__title" data-wp-text="context.item.title"></h3>
        <p class="project-card__excerpt" data-wp-text="context.item.excerpt"></p>
        <div class="project-card__meta">
            <span class="project-card__mail" data-wp-text="context.item.ngoEmail"></span>
            <a class="project-card__site"
                data-wp-bind--href="context.item.ngoSite"
                data-wp-text="context.item.ngoSite"
                target="_blank" rel="noopener">
            </a>
        </div>
    </div>
</article>
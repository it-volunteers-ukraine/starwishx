<?php
//file: inc/listing/Core/ResultsGrid.php

declare(strict_types=1);

namespace Listing\Core;

use Shared\Contracts\RenderableInterface;
use Shared\Core\Traits\BufferedRenderTrait;

class ResultsGrid implements RenderableInterface
{
    use BufferedRenderTrait;

    public function render(): string
    {
        $this->startBuffer();
?>
        <section class="listing-results" data-wp-interactive="listing">
            <!-- Loading Overlay -->
            <div class="listing-results__loading" data-wp-bind--hidden="!state.isLoading">
                <span class="spinner is-active"></span>
            </div>

            <!-- Active Filter Chips -->
            <div class="listing-chips" data-wp-bind--hidden="!state.hasActiveFilters">
                <template data-wp-each--item="state.activeFilterChips" data-wp-each-key="context.item.key">
                    <span class="btn-chip" data-wp-bind--data-slug="context.item.slug" data-wp-bind--data-child="context.item.isChild">
                        <span data-wp-text="context.item.label"></span>
                        <button class="btn-chip__icon"
                            data-wp-on--click="actions.filters.removeChip"
                            type="button"
                            aria-label="<?php esc_attr_e('Remove filter', 'starwishx'); ?>">
                            <svg width="14" height="14" aria-hidden="true">
                                <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus-small"></use>
                            </svg>
                        </button>
                    </span>
                </template>
                <button class="btn-tertiary listing-chips__clear" data-wp-on--click="actions.filters.clearAll">
                    <?php esc_html_e('Clear all', 'starwishx'); ?>
                </button>
            </div>

            <!-- Empty State -->
            <div class="listing-results__empty" data-wp-bind--hidden="state.hasResults">
                <p><?php esc_html_e('Nothing found matching your criteria', 'starwishx'); ?></p>
                <button class="btn-tertiary" data-wp-on--click="actions.filters.clearAll">
                    <?php esc_html_e('Clear all filters', 'starwishx'); ?>
                </button>
            </div>
            <!-- The Grid -->
            <!-- data-wp-bind--class="state.layoutClass" opportunities-grid -->
            <div
                class="listing-grid"
                data-wp-bind--hidden="!state.hasResults"
                data-layout="compact">
                <template data-wp-each--item="state.results" data-wp-key="context.item.id">
                    <?php echo $this->renderCard(); ?>
                </template>
            </div>

            <!-- Pagination / Load More -->
            <footer class="listing-results__footer" data-wp-bind--hidden="!state.hasMore">
                <button
                    class="btn-tertiary btn-load-more"
                    data-wp-on--click="actions.loadMore"
                    data-wp-bind--disabled="state.isLoading">
                    <?php esc_html_e('Load More', 'starwishx'); ?>
                </button>
            </footer>
        </section>
    <?php
        return $this->endBuffer();
    }

    /**
     * Renders the HTML structure of a single card.
     * Note: Uses iAPI directives to bind to the 'item' context.
     */
    private function renderCard(): string
    {
        $this->startBuffer();
    ?>
        <article class="listing-card">

            <div class="listing-card__content">

                <div class="listing-card__meta">
                    <div class="listing-card__suptitle">
                        <span class="listing-card__categories">
                            <template data-wp-each--cat="context.item.categories">
                                <span data-wp-text="context.cat.name"
                                    data-wp-bind--class="state.categoryClass">
                                </span>
                            </template>
                        </span>
                        <div class="control-favorites-wrapper">
                            <div
                                for="context.item.id"
                                class="heart-label"
                                data-wp-class--is-active="state.isFavorited"
                                data-wp-on--click="actions.toggleFavorite"
                                role="button"
                                tabindex="0">
                                <span class="heart-label__text">
                                    <span class="heart-label__text--inactive">
                                        <?php esc_html_e('Add to favorites', 'starwishx'); ?>
                                    </span>
                                    <span class="heart-label__text--active">
                                        <?php esc_html_e('In favorites', 'starwishx'); ?>
                                    </span>
                                </span>
                            </div>
                            <? if (is_user_logged_in()) : ?>
                                <div class="heart"
                                    title="<?php esc_attr_e('Favorites', 'starwishx'); ?>">
                                    <input
                                        id="context.item.id"
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
                            <? else : ?>
                                <div class="heart--img"
                                    title="<?php esc_attr_e('Favorites', 'starwishx'); ?>">
                                    <input
                                        id="context.item.id"
                                        type="checkbox"
                                        class="heart__checkbox--img"
                                        data-wp-bind--checked="state.isFavorited"
                                        data-wp-on--change="actions.toggleFavorite"
                                        aria-label="<?php esc_attr_e('Toggle Favorite', 'starwishx'); ?>">
                                    <img class="heart__image" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-heart-gradient.svg" alt="heart icon">
                                </div>
                            <? endif ?>
                        </div>
                    </div>
                    <a class="listing-card__title--link" data-wp-bind--href="context.item.url">
                        <h3 class="listing-card__title" data-wp-text="context.item.title"></h3>
                        <!-- </a> -->
                        <div class="listing-card__dates">
                            <span data-wp-bind--hidden="!context.item.date_starts">
                                <?php esc_html_e('From:', 'starwishx'); ?>
                                <span data-wp-text="context.item.date_starts"></span>
                            </span>
                            <span data-wp-bind--hidden="!context.item.date_ends">
                                <?php esc_html_e('To:', 'starwishx'); ?>
                                <span data-wp-text="context.item.date_ends"></span>
                            </span>
                        </div>
                        <!-- <a class="listing-card__excerpt--link" data-wp-bind--href="context.item.url"> -->
                        <p class="listing-card__excerpt card-text" data-wp-text="context.item.excerpt"></p>
                    </a>
                    <!-- <span>
                        < ?php esc_html_e('Donator', 'starwishx'); ?>:&nbsp;
                        <span class="company" data-wp-text="context.item.company"></span>
                    </span>
                    <span>
                        < ?php esc_html_e('Country', 'starwishx'); ?>:&nbsp;
                        <span class="country" data-wp-bind--hidden="!context.item.country" data-wp-text="context.item.country"></span>
                    </span> -->
                    <!-- <span data-wp-bind--hidden="!state.seekersList">
                        < ?php esc_html_e('Recievers', 'starwishx'); ?>:&nbsp;
                        <span data-wp-text="state.seekersList"></span>
                    </span> -->
                    <div class="listing-card__engage">
                        <span class="listing-card__rating rating-badge" data-wp-bind--hidden="!context.item.ratingCount">
                            <span class="stars-display"
                                data-wp-bind--data-rating="context.item.ratingRounded"
                                aria-hidden="true">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <svg class="icon-star star-<?php echo $s; ?>" width="14" height="14" aria-hidden="true">
                                        <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star"></use>
                                    </svg>
                                <?php endfor; ?>
                            </span>
                            <span class="listing-card__rating-avg" data-wp-text="context.item.ratingAvg"></span>
                        </span>
                        <a class="listing-card__comments-link"
                            data-wp-bind--hidden="!context.item.commentCount"
                            data-wp-bind--href="context.item.commentsUrl">
                            <?= sw_svg('icon-write', 14); ?>
                            <?= __("Comments", 'starwishx') ?>:
                            <!-- <svg width="14" height="14" aria-hidden="true">
                                <use href="< ?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-write"></use>
                            </svg> -->
                            <span class="listing-card__comments--count" data-wp-text="context.item.commentCount"></span>
                        </a>
                    </div>
                </div> <!-- Meta -->

                <!-- <div class="seekers" data-wp-bind--hidden="!context.item.seekers.length">
                    <span class="seekers__label">Seekers:</span>
                    <template data-wp-each--seeker="context.item.seekers">
                        <span class="seeker-chip" data-wp-text="context.seeker.name"></span>
                    </template>
                </div> -->
            </div>
            <!-- <div class="listing-card__footer" data-wp-bind--hidden="!context.item.locations.length">
                <span class="location locations-chips locations-chips--listing">
                    <template data-wp-each--loc="context.item.locations">
                        <span class="location-chip1 btn-chip1" data-wp-text="context.loc.name"></span>&sol;
                    </template>
                </span>
            </div> -->
        </article>
<?php
        return $this->endBuffer();
    }
}

/* 

            <!-- Header: Summary & Layout Switcher -->
            <!-- <header class="listing-results__header"> -->
            <!-- <div class="listing-results__info">
                    < ?php esc_html_e('Showing', 'starwishx'); ? >
                    <span data-wp-text="state.results.length"></span>
                    < ?php esc_html_e('opportunities', 'starwishx'); ? >
                </div> -->

            <!-- <div class="listing-results__actions">
                    <select data-wp-on--change="actions.listing.setLayout">
                        <option value="grid">< ?php esc_html_e('Grid', 'starwishx'); ? ></option>
                        <option value="list">< ?php esc_html_e('List', 'starwishx'); ? ></option>
                    </select>
                </div> -->
            <!-- </header> -->




                <figure class="listing-card__figure listing-card__figure">
                    <a class="listing-card__figure--link" data-wp-bind--href="context.item.url" data-wp-class--placeholder="!context.item.thumbnail">
                        <img class="listing-card__image"
                            data-wp-bind--src="context.item.thumbnail" data-wp-bind--hidden="!context.item.thumbnail" data-wp-bind--alt="context.item.title">
                        <!-- Fallback Placeholder (shown if no image) -->
                        <div class="listing-card__placeholder" data-wp-bind--hidden="context.item.thumbnail">
                            <!-- <svg width="40" height="40" class="icon-heart">
                                <use xlink:href="< ?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-opportunities"></use>
                            </svg> -->
                            <img class="card-image__fallback--icon" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="fallback image">
                        </div>
                    </a>
                    <div class="control-favorites-wrapper control-favorites-wrapper--icon-only">
                        <div
                            class="heart-label"
                            data-wp-class--is-active="state.isFavorited"
                            data-wp-on--click="actions.toggleFavorite"
                            role="button"
                            tabindex="0">
                            <span class="heart-label__text">
                                <span class="heart-label__text--inactive">
                                    <?php esc_html_e('Add to favorites', 'starwishx'); ?>
                                </span>
                                <span class="heart-label__text--active">
                                    <?php esc_html_e('In favorites', 'starwishx'); ?>
                                </span>
                            </span>
                        </div>

                        <? if (is_user_logged_in()) : ?>
                            <div class="heart">
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
                        <? else: ?>
                            <div class="heart--img">
                                <input
                                    type="checkbox"
                                    class="heart__checkbox--img"
                                    data-wp-bind--checked="state.isFavorited"
                                    data-wp-on--change="actions.toggleFavorite"
                                    aria-label="<?php esc_attr_e('Toggle Favorite', 'starwishx'); ?>">
                                <img class="heart__image" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-heart-gradient.svg" alt="heart icon">
                            </div>
                        <? endif; ?>
                    </div>

                </figure>


                <!-- <div class="control-favorites-wrapper">
                    <div
                        class="heart-label"
                        data-wp-class--is-active="state.isFavorited"
                        data-wp-on--click="actions.toggleFavorite"
                        role="button"
                        tabindex="0">
                        <span class="heart-label__text">
                            <span class="heart-label__text--inactive">< ?php esc_html_e('Add to favorites', 'starwishx'); ? ></span>
                            <span class="heart-label__text--active">< ?php esc_html_e('In favorites', 'starwishx'); ?></span>
                        </span>
                    </div>
                    <div class="heart">
                        <input
                            type="checkbox"
                            class="heart__checkbox"
                            data-wp-bind--checked="state.isFavorited"
                            data-wp-on--change="actions.toggleFavorite"
                            aria-label="< ?php esc_attr_e('Toggle Favorite', 'starwishx'); ?>">
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
                </div> -->

*/
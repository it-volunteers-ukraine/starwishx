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
            <!-- Header: Summary & Layout Switcher -->
            <header class="listing-results__header">
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
            </header>

            <!-- Loading Overlay -->
            <div class="listing-results__loading" data-wp-bind--hidden="!state.isLoading">
                <span class="spinner is-active"></span>
            </div>

            <!-- Empty State -->
            <div class="listing-results__empty" data-wp-bind--hidden="state.hasResults">
                <p><?php esc_html_e('Nothing found matching your criteria', 'starwishx'); ?></p>
                <button class="btn-tertiary" data-wp-on--click="actions.filters.clearAll">
                    <?php esc_html_e('Clear all filters', 'starwishx'); ?>
                </button>
            </div>
            <!-- The Grid -->
            <!-- data-wp-bind--class="state.layoutClass" -->
            <div
                class="listing-grid opportunities-grid results-grid"
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
        <article class="opportunity-card">

            <div class="opportunity-card__content">
                <figure class="opportunity-card__figure listing-card__figure">
                    <a class="listing-card__figure--link" data-wp-bind--href="context.item.url" data-wp-class--placeholder="!context.item.thumbnail">
                        <img class="listing-card__image"
                            data-wp-bind--src="context.item.thumbnail" data-wp-bind--hidden="!context.item.thumbnail" data-wp-bind--alt="context.item.title">
                        <!-- Fallback Placeholder (shown if no image) -->
                        <div class="opportunity-card__placeholder" data-wp-bind--hidden="context.item.thumbnail">
                            <svg width="40" height="40" class="icon-heart">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-opportunities"></use>
                            </svg>
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
                <div class="opportunity-card__meta">
                    <span class="categories">
                        <template data-wp-each--cat="context.item.categories">
                            <span data-wp-text="context.cat.name"
                                data-wp-bind--class="state.categoryClass">
                            </span>
                        </template>
                    </span>
                    <a data-wp-bind--href="context.item.url">
                        <h3 class="opportunity-card__title card-title" data-wp-text="context.item.title"></h3>
                    </a>
                    <div class="opportunity-card__dates">
                        <span data-wp-bind--hidden="!context.item.date_starts">
                            <?php esc_html_e('From:', 'starwishx'); ?>
                            <span data-wp-text="context.item.date_starts"></span>
                        </span>
                        <span data-wp-bind--hidden="!context.item.date_ends">
                            <?php esc_html_e('To:', 'starwishx'); ?>
                            <span data-wp-text="context.item.date_ends"></span>
                        </span>
                    </div>
                    <span>
                        Donator:&nbsp;
                        <span class="company" data-wp-text="context.item.company"></span>
                    </span>
                    <span>
                        Country:&nbsp;
                        <span class="country" data-wp-bind--hidden="!context.item.country" data-wp-text="context.item.country"></span>
                    </span>
                    <p class="opportunity-card__excerpt card-text" data-wp-text="context.item.excerpt"></p>
                    <span data-wp-bind--hidden="!state.seekersList">
                        Recievers:&nbsp;
                        <span data-wp-text="state.seekersList"></span>
                    </span>
                </div> <!-- Meta -->

                <!-- <div class="seekers" data-wp-bind--hidden="!context.item.seekers.length">
                    <span class="seekers__label">Seekers:</span>
                    <template data-wp-each--seeker="context.item.seekers">
                        <span class="seeker-chip" data-wp-text="context.seeker.name"></span>
                    </template>
                </div> -->
            </div>
            <div class="opportunity-card__footer" data-wp-bind--hidden="!context.item.locations.length">
                <span class="location locations-chips locations-chips--listing">
                    <template data-wp-each--loc="context.item.locations">
                        <span class="location-chip1 btn-chip1" data-wp-text="context.loc.name"></span>&sol;
                    </template>
                </span>
            </div>
        </article>
<?php
        return $this->endBuffer();
    }
}

<?php
// File: inc/launchpad/Panels/FavoritesPanel.php
declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\FavoritesService;

class FavoritesPanel extends AbstractPanel
{
    private FavoritesService $service;

    /**
     * Dependency Injection via Constructor
     */
    public function __construct(FavoritesService $service)
    {
        $this->service = $service;
    }

    public function getId(): string
    {
        return 'favorites';
    }

    public function getLabel(): string
    {
        return __('Favorites', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'icon-heart';
    }

    public function getInitialState(?int $userId = null): array
    {
        // We'r injected service here instead of 'new FavoritesService()'
        $favorites = $this->service->getUserFavorites($userId, 20, 0);
        $total     = $this->service->countUserFavorites($userId);

        return [
            'items'       => $favorites,
            'total'       => $total,
            'page'        => 1,
            'totalPages'  => ceil($total / 20),
            'hasMore'     => $total > 20,
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <!-- Outer wrapper uses main launchpad store -->
        <div class="launchpad-panel launchpad-panel--favorites" data-wp-interactive="launchpad">
            <h2 class="panel-title"><?php esc_html_e('My Favorites', 'starwishx'); ?></h2>
            <div
                class="launchpad-alert launchpad-alert--error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>
            <div
                class="launchpad-loading"
                data-wp-bind--hidden="!<?= $this->statePath('isLoading') ?>">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading...', 'starwishx'); ?>
            </div>
            <div
                class="favorites-empty"
                data-wp-bind--hidden="state.shouldHideFavoritesEmpty">
                <span class="dashicons dashicons-heart"></span>
                <p><?php esc_html_e('No favorites yet.', 'starwishx'); ?></p>
            </div>
            <!-- GRID -->
            <div class="favorites-grid" data-wp-bind--hidden="<?= $this->statePath('items') ?>.length === 0">
                <template data-wp-each--item="<?= $this->statePath('items') ?>">
                    <article
                        class="favorite-card"
                        data-wp-bind--hidden="state.isCurrentItemFavorite">
                        <!-- Thumbnail Figure -->
                        <div class="favorite-card__figure">
                            <a data-wp-bind--href="context.item.url" target="_blank">
                                <!-- Actual Image -->
                                <img
                                    class="favorite-card__image"
                                    data-wp-bind--src="context.item.thumbnail"
                                    data-wp-bind--alt="context.item.title"
                                    data-wp-bind--hidden="!context.item.thumbnail" />

                                <!-- Fallback Placeholder -->
                                <div class="favorite-card__placeholder" data-wp-bind--hidden="context.item.thumbnail">
                                    <span class="dashicons dashicons-heart"></span>
                                </div>
                            </a>
                        </div>
                        <div class="favorite-content">
                            <a data-wp-bind--href="context.item.url" target="_blank">
                                <h3 class="favorite-title" data-wp-text="context.item.title"></h3>
                            </a>
                            <!-- Meta (could include date added, etc.) -->
                            <div class="favorite-meta">
                                <span class="favorite-type"><?php esc_html_e('Opportunity', 'starwishx'); ?></span>
                            </div>
                            <!-- Excerpt -->
                            <p class="favorite-excerpt"
                                data-wp-text="context.item.excerpt"
                                data-wp-bind--hidden="!context.item.excerpt"></p>
                        </div>
                        <div class="favorite-actions">
                            <a class="btn-secondary__small" data-wp-bind--href="context.item.url" target="_blank">
                                <?php esc_html_e('View', 'starwishx'); ?>
                            </a>
                            <!-- No wrapper div changing namespace. Passed ID via data-id attribute (bridge across namespaces). Used 'launchpad/favorites::' syntax. -->
                            <button
                                class="btn-secondary__small button-link-delete"
                                data-wp-bind--data-id="context.item.id"
                                data-wp-class--is-active="state.isCurrentItemFavorite"
                                data-wp-on--click="launchpad/favorites::actions.toggle">
                                <?php esc_html_e('Remove', 'starwishx'); ?>
                            </button>
                        </div>
                    </article>
                </template>
            </div>
            <div
                class="favorites-pagination"
                data-wp-bind--hidden="!<?= $this->statePath('hasMore') ?>">
                <button
                    class="button"
                    data-wp-on--click="actions.favorites.loadMore"
                    data-wp-bind--disabled="<?= $this->statePath('isLoading') ?>">
                    <?php esc_html_e('Load More', 'starwishx'); ?>
                </button>
            </div>
        </div>
<?php
        return $this->endBuffer();
    }
}

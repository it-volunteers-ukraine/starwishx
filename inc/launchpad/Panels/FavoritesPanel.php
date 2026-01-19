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
        return 'heart';
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
        <div class="launchpad-panel launchpad-panel--favorites">
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
                data-wp-bind--hidden="<?= $this->statePath('items') ?>.length > 0 || <?= $this->statePath('isLoading') ?>">
                <span class="dashicons dashicons-heart"></span>
                <p><?php esc_html_e('No favorites yet.', 'starwishx'); ?></p>
            </div>

            <div class="favorites-grid" data-wp-bind--hidden="<?= $this->statePath('items') ?>.length === 0">
                <template data-wp-each="<?= $this->statePath('items') ?>">
                    <article class="favorite-card">
                        <img
                            class="favorite-thumbnail"
                            data-wp-bind--src="context.item.thumbnail"
                            data-wp-bind--alt="context.item.title" />
                        <div class="favorite-content">
                            <h3 class="favorite-title" data-wp-text="context.item.title"></h3>
                        </div>
                        <div class="favorite-actions">
                            <a class="button" data-wp-bind--href="context.item.url">
                                <?php esc_html_e('View', 'starwishx'); ?>
                            </a>
                            <button
                                class="button button-link-delete"
                                data-wp-bind--data-post-id="context.item.id"
                                data-wp-on--click="actions.favorites.remove">
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

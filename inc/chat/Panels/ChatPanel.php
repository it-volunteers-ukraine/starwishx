<?php
// File: inc/chat/Panels/ChatPanel.php

declare(strict_types=1);

namespace Chat\Panels;

use Launchpad\Panels\AbstractPanel;
use Chat\Services\ActivityService;

class ChatPanel extends AbstractPanel
{
    private ActivityService $service;

    public function __construct(ActivityService $service)
    {
        $this->service = $service;
    }

    public function getId(): string
    {
        return 'chat';
    }

    public function getLabel(): string
    {
        return __('Chat', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'icon-email';
    }

    public function getInitialState(?int $userId = null): array
    {
        if (! $userId) {
            return [
                'items'       => [],
                'total'       => 0,
                'totalPages'  => 0,
                'page'        => 1,
                'hasMore'     => false,
            ];
        }

        return $this->service->getActivity($userId, 1, 15);
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <div class="launchpad-panel launchpad-panel--chat" data-wp-interactive="launchpad">

            <div class="chat-panel-header">
                <h2 class="panel-title"><?php esc_html_e('Activity', 'starwishx'); ?></h2>
                <div class="chat-panel-actions" data-wp-interactive="chat">
                    <button class="btn-secondary__small"
                        data-wp-on--click="actions.refresh"
                        data-wp-bind--disabled="state.isRefreshing">
                        <?php esc_html_e('Refresh', 'starwishx'); ?>
                    </button>
                    <button class="btn-secondary__small"
                        data-wp-on--click="actions.markAllRead"
                        data-wp-bind--hidden="!state.hasUnread"
                        hidden>
                        <?php esc_html_e('Mark all read', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <div class="launchpad-alert launchpad-alert--error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>

            <div class="launchpad-loading"
                data-wp-bind--hidden="!<?= $this->statePath('isLoading') ?>">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading...', 'starwishx'); ?>
            </div>

            <!-- Empty state -->
            <div class="chat-empty" data-wp-interactive="chat"
                data-wp-bind--hidden="state.hasItems">
                <svg width="24" height="24">
                    <use xlink:href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-email"></use>
                </svg>
                <p data-wp-text="state.config.messages.noActivity"></p>
            </div>

            <!-- Activity list -->
            <div class="chat-activity-list" data-wp-interactive="chat"
                data-wp-bind--hidden="!state.hasItems">
                <template data-wp-each--item="state.panelItems">
                    <div class="activity-item"
                        data-wp-class--activity-item--unread="!context.item.isRead"
                        data-wp-on--click="actions.markItemRead">

                        <div class="activity-item__avatar">
                            <img data-wp-bind--src="context.item.actorAvatar"
                                data-wp-bind--alt="context.item.actorName"
                                width="40" height="40" loading="lazy" />
                        </div>

                        <div class="activity-item__content">
                            <p class="activity-item__text">
                                <strong data-wp-text="context.item.actorName"></strong>
                                <span data-wp-text="state.currentItemAction"></span>
                                <a data-wp-bind--href="context.item.postUrl"
                                    data-wp-text="context.item.postTitle"
                                    target="_blank"></a>
                            </p>
                            <p class="activity-item__excerpt"
                                data-wp-text="context.item.commentExcerpt"
                                data-wp-bind--hidden="!context.item.commentExcerpt"
                                hidden></p>
                            <span class="activity-item__time"
                                data-wp-text="state.currentItemTimeAgo"></span>
                        </div>

                        <span class="activity-item__dot"
                            data-wp-bind--hidden="context.item.isRead"
                            hidden></span>
                    </div>
                </template>
            </div>

            <!-- Load more -->
            <div class="chat-pagination" data-wp-interactive="chat"
                data-wp-bind--hidden="!state.hasMoreItems"
                hidden>
                <button class="button"
                    data-wp-on--click="actions.loadMore"
                    data-wp-bind--disabled="state.isLoadingMore">
                    <?php esc_html_e('Load More', 'starwishx'); ?>
                </button>
            </div>

        </div>
<?php
        return $this->endBuffer();
    }
}

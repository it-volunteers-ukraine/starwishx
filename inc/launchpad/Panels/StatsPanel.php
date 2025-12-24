<?php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\StatsService;

class StatsPanel extends AbstractPanel
{

    public function getId(): string
    {
        return 'stats';
    }

    public function getLabel(): string
    {
        return __('Stats', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'chart-bar';
    }

    public function getInitialState(int $userId): array
    {
        $service = new StatsService();

        return [
            'totalViews'     => $service->getTotalViews($userId),
            'totalFavorites' => $service->getTotalFavorites($userId),
            'totalComments'  => $service->getTotalComments($userId),
            'recentActivity' => $service->getRecentActivity($userId, 10),
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <div class="launchpad-panel launchpad-panel--stats">
            <h2 class="panel-title"><?php esc_html_e('Your Statistics', 'starwishx'); ?></h2>

            <div class="stats-grid placeholder-box">
                <div class="stat-card">
                    <span class="stat-icon dashicons dashicons-visibility"></span>
                    <span class="stat-value" data-wp-text="<?= $this->statePath('totalViews') ?>">0</span>
                    <span class="stat-label"><?php esc_html_e('Views', 'starwishx'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon dashicons dashicons-heart"></span>
                    <span class="stat-value" data-wp-text="<?= $this->statePath('totalFavorites') ?>">0</span>
                    <span class="stat-label"><?php esc_html_e('Favorites', 'starwishx'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon dashicons dashicons-admin-comments"></span>
                    <span class="stat-value" data-wp-text="<?= $this->statePath('totalComments') ?>">0</span>
                    <span class="stat-label"><?php esc_html_e('Comments', 'starwishx'); ?></span>
                </div>
            </div>

            <div class="stats-activity">
                <h3><?php esc_html_e('Recent Activity', 'starwishx'); ?></h3>
                <ul class="activity-list">
                    <template data-wp-each="<?= $this->statePath('recentActivity') ?>">
                        <li class="activity-item">
                            <span class="activity-icon dashicons" data-wp-bind--class="'dashicons-' + context.item.icon"></span>
                            <span class="activity-text" data-wp-text="context.item.description"></span>
                            <time class="activity-time" data-wp-text="context.item.time"></time>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
<?php
        return $this->endBuffer();
    }
}

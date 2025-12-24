<?php
declare(strict_types=1);

namespace Launchpad\Services;

class StatsService {

    public function getTotalViews(int $userId): int {
        return (int) get_user_meta($userId, 'launchpad_total_views', true) ?: 0;
    }

    public function getTotalFavorites(int $userId): int {
        $service = new FavoritesService();
        return $service->countUserFavorites($userId);
    }

    public function getTotalComments(int $userId): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
            $userId
        ));
    }

    public function getRecentActivity(int $userId, int $limit = 10): array {
        // Implement based on your analytics needs
        return [];
    }
}
<?php

declare(strict_types=1);

namespace Launchpad\Data\Repositories;

class FavoritesRepository
{

    private function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'launchpad_favorites';
    }

    public function getFavoriteIds(
        int $userId,
        string $objectType = 'post',
        int $limit = 50,
        int $offset = 0
    ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT object_id FROM {$this->getTable()}
             WHERE user_id = %d AND object_type = %s
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $userId,
            $objectType,
            $limit,
            $offset
        );

        return array_map('intval', $wpdb->get_col($sql));
    }

    public function getFavoritesWithPosts(int $userId, int $limit = 50, int $offset = 0): array
    {
        $ids = $this->getFavoriteIds($userId, 'post', $limit, $offset);

        if (empty($ids)) {
            return [];
        }

        $query = new \WP_Query([
            'post__in'       => $ids,
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'orderby'        => 'post__in',
            'posts_per_page' => -1,
        ]);

        return $query->posts;
    }

    public function addFavorite(int $userId, int $objectId, string $objectType = 'post'): bool
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->getTable(),
            [
                'user_id'     => $userId,
                'object_id'   => $objectId,
                'object_type' => $objectType,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    public function removeFavorite(int $userId, int $objectId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getTable(),
            [
                'user_id'   => $userId,
                'object_id' => $objectId,
            ],
            ['%d', '%d']
        );

        return $result !== false;
    }

    public function isFavorite(int $userId, int $objectId): bool
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT 1 FROM {$this->getTable()} WHERE user_id = %d AND object_id = %d LIMIT 1",
            $userId,
            $objectId
        );

        return (bool) $wpdb->get_var($sql);
    }

    public function countFavorites(int $userId): int
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->getTable()} WHERE user_id = %d",
            $userId
        );

        return (int) $wpdb->get_var($sql);
    }
}

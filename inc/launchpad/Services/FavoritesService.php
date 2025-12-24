<?php
declare(strict_types=1);

namespace Launchpad\Services;

use Launchpad\Data\Repositories\FavoritesRepository;

class FavoritesService {
    private FavoritesRepository $repository;

    public function __construct(?FavoritesRepository $repository = null) {
        $this->repository = $repository ?? new FavoritesRepository();
    }

    public function getUserFavorites(int $userId, int $limit = 20, int $offset = 0): array {
        $posts = $this->repository->getFavoritesWithPosts($userId, $limit, $offset);

        return array_map(function($post) {
            return [
                'id'        => $post->ID,
                'title'     => get_the_title($post),
                'excerpt'   => get_the_excerpt($post),
                'url'       => get_permalink($post),
                'thumbnail' => get_the_post_thumbnail_url($post, 'medium') ?: '',
            ];
        }, $posts);
    }

    public function countUserFavorites(int $userId): int {
        return $this->repository->countFavorites($userId);
    }

    public function addFavorite(int $userId, int $postId): bool {
        if ($this->repository->isFavorite($userId, $postId)) {
            return true;
        }
        return $this->repository->addFavorite($userId, $postId);
    }

    public function removeFavorite(int $userId, int $postId): bool {
        return $this->repository->removeFavorite($userId, $postId);
    }

    public function isFavorite(int $userId, int $postId): bool {
        return $this->repository->isFavorite($userId, $postId);
    }
}
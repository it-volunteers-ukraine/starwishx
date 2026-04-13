<?php

/**
 * Projects — Data Service
 *
 * Handles data fetching for single project pages.
 * File: inc/projects/Services/ProjectsService.php
 */

declare(strict_types=1);

namespace Projects\Services;

use Favorites\Data\FavoritesRepository;

class ProjectsService
{
    private ?FavoritesRepository $favoritesRepo;

    public function __construct(?FavoritesRepository $favoritesRepo = null)
    {
        $this->favoritesRepo = $favoritesRepo;
    }

    /**
     * Get view data for a single project post.
     *
     * @param int $postId
     * @return array{title: string, description: string, excerpt: string}
     */
    public function getViewData(int $postId): array
    {
        $post = get_post($postId);

        return [
            'title'              => get_the_title($postId),
            // 'description'        => wpautop($post->post_content ?? ''),
            'description'        => apply_filters('the_content', $post->post_content ?? ''),
            'excerpt'            => get_the_excerpt($postId),
            'opportunities_info' => sw_get_field('opportunities_info', $postId),
            'ngo_info'           => sw_get_field('ngo_info', $postId),
        ];
    }

    /**
     * Get related opportunities for a project.
     *
     * @param int $projectId
     * @param int $userId Current user ID for favorite status (0 = guest)
     * @return array
     */
    public function getRelatedOpportunities(int $projectId, int $userId = 0): array
    {
        $ids = sw_get_field('opportunities', $projectId);

        if (empty($ids) || !is_array($ids)) {
            return [];
        }

        // ACF relationship fields return post objects or IDs depending on config
        $ids = array_map(function ($item) {
            return is_object($item) ? $item->ID : (int) $item;
        }, $ids);

        return $this->fetchCompactCards($ids, 'opportunity', $userId);
    }

    /**
     * Get related NGOs for a project.
     *
     * @param int $projectId
     * @param int $userId Current user ID for favorite status (0 = guest)
     * @return array
     */
    public function getRelatedNgos(int $projectId, int $userId = 0): array
    {
        $ids = sw_get_field('ngo', $projectId);

        if (empty($ids) || !is_array($ids)) {
            return [];
        }

        // ACF relationship fields return post objects or IDs depending on config
        $ids = array_map(function ($item) {
            return is_object($item) ? $item->ID : (int) $item;
        }, $ids);

        return $this->fetchCompactCards($ids, 'ngo', $userId);
    }

    /**
     * Fetch compact card data for a list of post IDs.
     *
     * @param int[]  $ids
     * @param string $postType
     * @param int    $userId Current user ID for favorite status (0 = guest)
     * @return array<array{id: int, title: string, url: string, excerpt: string, thumbnail: string, date: string, isFavorite: bool}>
     */
    private function fetchCompactCards(array $ids, string $postType, int $userId = 0): array
    {
        if (empty($ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $postType,
            'include'        => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($ids),
            'post_status'    => 'publish',
        ]);

        if (empty($posts)) {
            return [];
        }

        // Prime thumbnail cache in a single query
        update_post_thumbnail_cache(
            new \WP_Query(['posts' => $posts, 'update_post_meta_cache' => false])
        );

        $cards = [];

        foreach ($posts as $post) {
            $thumb_id  = get_post_thumbnail_id($post->ID);
            $thumbnail = $thumb_id
                ? wp_get_attachment_image_url($thumb_id, 'medium')
                : '';

            $cards[] = [
                'id'         => $post->ID,
                'title'      => html_entity_decode(get_the_title($post->ID)),
                'url'        => get_permalink($post->ID),
                'excerpt'    => get_the_excerpt($post->ID),
                'thumbnail'  => $thumbnail ?: '',
                'date'       => get_the_date('', $post->ID),
                'isFavorite' => $userId > 0 && $this->favoritesRepo
                    ? $this->favoritesRepo->isFavorite($userId, $post->ID)
                    : false,
                'ngoEmail'  => sw_get_field('ngo_email', $post->ID),
                'ngoSite'   => sw_get_field('ngo_site', $post->ID),
            ];
        }

        return $cards;
    }
}

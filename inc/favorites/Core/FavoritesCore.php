<?php

/**
 * Favorites - Independent Module Core
 *
 * Manages the favorites feature lifecycle: REST API, state hydration,
 * cleanup hooks, and asset enqueueing.
 * Consumed by: Launchpad (dashboard panel), Listing (grid), Projects (cards),
 *              single-opportunity & single-project templates.
 * 
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/favorites/Core/FavoritesCore.php
 */

declare(strict_types=1);

namespace Favorites\Core;

use Favorites\Data\FavoritesRepository;
use Favorites\Services\FavoritesService;
use Favorites\Api\FavoritesController;

final class FavoritesCore
{
    private static ?self $instance = null;

    private FavoritesRepository $repository;
    private FavoritesService $service;

    /** @var int[]|null Cached favorite IDs for the current user (populated during enqueueAssets) */
    private ?array $cachedIds = null;

    public static function instance(
        ?FavoritesRepository $repository = null,
        ?FavoritesService $service = null
    ): self {
        if (!self::$instance) {
            $repository = $repository ?? new FavoritesRepository();
            $service    = $service    ?? new FavoritesService($repository);

            self::$instance = new self($repository, $service);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct(
        FavoritesRepository $repository,
        FavoritesService $service
    ) {
        $this->repository = $repository;
        $this->service    = $service;

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Data cleanup hooks
        add_action('delete_post', [$this, 'cleanupPostFavorites']);
        add_action('delete_user', [$this, 'cleanupUserFavorites']);

        // Asset enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerRestRoutes(): void
    {
        (new FavoritesController($this->service))->registerRoutes();
    }

    /**
     * Enqueue the favorites store JS and hydrate state.
     *
     * Centralizes what was previously duplicated across
     * LaunchpadCore, ProjectsCore, and ListingCore.
     */
    public function enqueueAssets(): void
    {
        $needsFavorites = is_page('launchpad')
            // || is_page('listing') // if listing in page Listing
            // || is_page('opportunities') // if listing in page Opportunities
            || is_archive('opportunities') // if listing is archive Opportunities
            || is_singular('opportunity')
            // || is_singular('news')
            || is_singular('project');

        if (!$needsFavorites) {
            return;
        }

        // Register the script module
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/favorites',
                get_template_directory_uri() . '/assets/js/favorites-store.module.js',
                ['@wordpress/interactivity']
            );
            wp_enqueue_script_module('@starwishx/favorites');
        }

        // Hydrate state for logged-in users
        $userId = get_current_user_id();

        if ($userId > 0) {
            $ids = $this->repository->getFavoriteIds($userId, 'post', 9999, 0);
            $this->cachedIds = $ids;

            wp_interactivity_state('favorites', [
                'myFavoriteIds' => $ids,
                'config'        => [
                    'nonce'   => wp_create_nonce('wp_rest'),
                    'restUrl' => rest_url('favorites/v1/'),
                ],
            ]);
        }
    }

    // --- Public Accessors ---

    public function repository(): FavoritesRepository
    {
        return $this->repository;
    }

    public function service(): FavoritesService
    {
        return $this->service;
    }

    /**
     * Check if the current user has favorited a post.
     * Uses the cached IDs from enqueueAssets() to avoid extra DB queries.
     */
    public function isUserFavorite(int $postId): bool
    {
        if ($this->cachedIds !== null) {
            return in_array($postId, $this->cachedIds, true);
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        return $this->repository->isFavorite($userId, $postId);
    }

    // --- Cleanup ---

    public function cleanupPostFavorites(int $postId): void
    {
        $this->repository->deleteByPost($postId);
    }

    public function cleanupUserFavorites(int $userId): void
    {
        $this->repository->deleteByUser($userId);
    }
}

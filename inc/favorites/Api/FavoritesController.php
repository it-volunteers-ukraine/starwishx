<?php
// File: inc/favorites/Api/FavoritesController.php

declare(strict_types=1);

namespace Favorites\Api;

use Favorites\Services\FavoritesService;
use Shared\Core\AbstractApiController;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class FavoritesController extends AbstractApiController
{
    protected $namespace = 'favorites/v1';

    private const PER_PAGE_MAX = 50;
    private const PER_PAGE_DEFAULT = 4;

    // Shared per-user bucket across add/remove/toggle — a single angry user
    // can page-thrash favorites UI buttons faster than a human would, but
    // 256/hour still covers any realistic browsing session.
    private const WRITE_RATE_LIMIT_MAX    = 256;
    private const WRITE_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    private FavoritesService $service;

    public function __construct(FavoritesService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        $idArg = [
            'validate_callback' => RestArg::intRange(
                1,
                PHP_INT_MAX,
                __('Post ID', 'starwishx')
            ),
        ];

        // GET List
        register_rest_route($this->namespace, '/favorites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFavorites'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'page'     => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'validate_callback' => RestArg::intRange(
                        1,
                        PHP_INT_MAX,
                        __('Page', 'starwishx')
                    ),
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'default'           => self::PER_PAGE_DEFAULT,
                    'validate_callback' => RestArg::intRange(
                        1,
                        self::PER_PAGE_MAX,
                        __('Per page', 'starwishx')
                    ),
                ],
            ],
        ]);

        // POST (Add) / DELETE (Remove)
        register_rest_route($this->namespace, '/favorites/(?P<id>\d+)', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'addFavorite'],
                'permission_callback' => [$this, 'checkLoggedInWithNonce'],
                'args'                => ['id' => $idArg],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'removeFavorite'],
                'permission_callback' => [$this, 'checkLoggedInWithNonce'],
                'args'                => ['id' => $idArg],
            ],
        ]);

        register_rest_route($this->namespace, '/favorites/toggle/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'toggleFavorite'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => ['id' => $idArg],
        ]);
    }

    public function getFavorites(WP_REST_Request $request): WP_REST_Response
    {
        $page = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');
        $userId = get_current_user_id();

        $items = $this->service->getUserFavorites(
            $userId,
            $perPage,
            ($page - 1) * $perPage
        );

        $total = $this->service->countUserFavorites($userId);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function addFavorite(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $postId = (int) $request->get_param('id');
        $postCheck = $this->ensureFavoritablePost($postId);
        if ($postCheck !== null) {
            return $postCheck;
        }

        $this->service->addFavorite($userId, $postId);

        return $this->success([
            'success'    => true,
            'isFavorite' => true,
        ]);
    }

    public function removeFavorite(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $postId = (int) $request->get_param('id');
        $postCheck = $this->ensureFavoritablePost($postId);
        if ($postCheck !== null) {
            return $postCheck;
        }

        $this->service->removeFavorite($userId, $postId);

        return $this->success([
            'success'    => true,
            'isFavorite' => false,
        ]);
    }

    public function toggleFavorite(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $postId = (int) $request->get_param('id');
        $postCheck = $this->ensureFavoritablePost($postId);
        if ($postCheck !== null) {
            return $postCheck;
        }

        $result = $this->service->toggleFavorite($userId, $postId);

        return $this->success([
            'success'    => true,
            'isFavorite' => (bool) ($result['isFavorite'] ?? false),
        ]);
    }

    /**
     * Guard: post must exist and be published before a user can favorite it.
     *
     * Mirrors the pre-existing check in toggleFavorite and extends it to
     * add/remove so all three write paths share the same contract — no way
     * to favorite a draft/trash/private post by hitting POST directly.
     *
     * Returns null when the post is favoritable, or a localized WP_Error
     * otherwise. Callers short-circuit on non-null return.
     */
    private function ensureFavoritablePost(int $postId): ?WP_Error
    {
        $post = get_post($postId);
        if (!$post) {
            return $this->error(
                __('Post not found.', 'starwishx'),
                404,
                'not_found'
            );
        }

        if ($post->post_status !== 'publish') {
            return $this->error(
                __('Favorites are only allowed on published posts.', 'starwishx'),
                403,
                'forbidden'
            );
        }

        return null;
    }

    /**
     * Per-user rate limit — single shared bucket for all favorites writes.
     *
     * Toggle/add/remove share one counter so an attacker can't triple their
     * effective budget by rotating across endpoints. `mapServiceError()`
     * translates the policy's `rate_limited` code into 429.
     */
    private function applyWriteRateLimit(int $userId): ?WP_Error
    {
        $key = RateLimitPolicy::key('favorites_write', (string) $userId);

        $message = sprintf(
            /* translators: 1: human-readable wait duration */
            __('Too many favorites changes. Please wait %1$s before trying again.', 'starwishx'),
            human_time_diff(time(), time() + self::WRITE_RATE_LIMIT_WINDOW)
        );

        $check = RateLimitPolicy::check(
            $key,
            self::WRITE_RATE_LIMIT_MAX,
            self::WRITE_RATE_LIMIT_WINDOW,
            $message
        );
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        RateLimitPolicy::hit($key, self::WRITE_RATE_LIMIT_WINDOW);

        return null;
    }
}

<?php
// File: inc/launchpad/Api/FavoritesController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\FavoritesService;
use WP_REST_Request;
use WP_REST_Response;

class FavoritesController extends AbstractApiController
{
    private FavoritesService $service;

    public function __construct(?FavoritesService $service = null)
    {
        // Dependency Injection allows for mocking in tests
        $this->service = $service ?? new FavoritesService();
    }

    public function registerRoutes(): void
    {
        // GET List
        register_rest_route($this->namespace, '/favorites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFavorites'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        // POST (Add) / DELETE (Remove)
        register_rest_route($this->namespace, '/favorites/(?P<id>\d+)', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'addFavorite'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'removeFavorite'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    public function getFavorites(WP_REST_Request $request): WP_REST_Response
    {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
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
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    public function addFavorite(WP_REST_Request $request): WP_REST_Response
    {
        $success = $this->service->addFavorite(
            get_current_user_id(),
            (int) $request->get_param('id')
        );

        return $this->success(['success' => $success]);
    }

    public function removeFavorite(WP_REST_Request $request): WP_REST_Response
    {
        $success = $this->service->removeFavorite(
            get_current_user_id(),
            (int) $request->get_param('id')
        );

        return $this->success(['success' => $success]);
    }
}

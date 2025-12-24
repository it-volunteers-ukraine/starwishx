<?php
// File: inc/launchpad/Api/MainController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Core\PanelRegistry;
use WP_REST_Request;
use WP_REST_Response;

class MainController extends AbstractApiController
{
    private PanelRegistry $registry;

    public function __construct(PanelRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function registerRoutes(): void
    {
        // Endpoint: GET /wp-json/launchpad/v1/panel/{id}/state
        register_rest_route($this->namespace, '/panel/(?P<id>[a-z0-9-]+)/state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPanelState'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    // Validation happens here, preventing invalid IDs from reaching logic
                    'validate_callback' => fn($id) => $this->registry->has($id),
                ],
            ],
        ]);
    }

    public function getPanelState(WP_REST_Request $request): WP_REST_Response
    {
        $panelId = $request->get_param('id');
        $panel = $this->registry->get($panelId);

        // This should technically be caught by validate_callback, but safety first
        if (!$panel) {
            return new WP_REST_Response(['error' => 'Panel not found'], 404);
        }

        // Reuse the EXACT same state logic as the initial SSR load
        $state = $panel->getInitialState(get_current_user_id());

        // Add hydration flags so the JS store knows this is fresh data
        $state['_loaded'] = true;
        $state['isLoading'] = false;

        return $this->success($state);
    }
}

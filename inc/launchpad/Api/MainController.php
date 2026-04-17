<?php
// File: inc/launchpad/Api/MainController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Core\PanelRegistry;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MainController extends AbstractLaunchpadController
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
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    // Reject unknown panel IDs at the route boundary with a
                    // localized 404 — same code/message as the in-handler
                    // safety net so the client sees one consistent error shape.
                    'validate_callback' => fn($id) => $this->registry->has($id)
                        ? true
                        : new WP_Error(
                            'panel_missing',
                            __('Panel not found.', 'starwishx'),
                            ['status' => 404]
                        ),
                ],
            ],
        ]);
    }

    public function getPanelState(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $panelId = $request->get_param('id');
        $panel = $this->registry->get($panelId);

        // This should technically be caught by validate_callback, but safety first
        if (!$panel) {
            // Here we use the standard helper. 
            // JS will find the message in 'data.message'
            return $this->error(__('Panel not found.', 'starwishx'), 404, 'panel_missing');
        }

        // Reuse the EXACT same state logic as the initial SSR load
        $state = $panel->getInitialState(get_current_user_id());

        // Add hydration flags so the JS store knows this is fresh data
        $state['_loaded'] = true;
        $state['isLoading'] = false;

        return $this->success($state);
    }
}

<?php
// File: inc/launchpad/Api/SecurityController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\SecurityService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SecurityController extends AbstractLaunchpadController
{
    private SecurityService $service;

    public function __construct(SecurityService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/security/password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'changePassword'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'currentPassword' => ['required' => true, 'type' => 'string'],
                'newPassword'     => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function changePassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->changePassword(
            get_current_user_id(),
            $request->get_param('currentPassword'),
            $request->get_param('newPassword')
        );

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 400, $result->get_error_code());
        }

        // Note: wp_set_password invalidates auth cookies.
        // The frontend launchpad-store.js must handle the redirect to login immediately.
        return $this->success([
            'success' => true,
            'message' => __('Password changed. Please log in again.', 'starwishx'),
        ]);
    }
}

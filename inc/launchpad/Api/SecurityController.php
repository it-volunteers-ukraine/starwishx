<?php
// File: inc/launchpad/Api/SecurityController.php

declare(strict_types=1);

namespace Launchpad\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SecurityController extends AbstractApiController
{
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
        $user = wp_get_current_user();
        $current = $request->get_param('currentPassword');
        $newPass = $request->get_param('newPassword');

        // Verify current password
        if (!wp_check_password($current, $user->user_pass, $user->ID)) {
            return $this->error(__('Current password is incorrect.', 'starwishx'), 400, 'invalid_password');
        }

        // Update password
        wp_set_password($newPass, $user->ID);

        // Note: wp_set_password invalidates auth cookies.
        // The frontend store.js must handle the redirect to login immediately.
        return $this->success([
            'success' => true,
            'message' => __('Password changed. Please log in again.', 'starwishx'),
        ]);
    }
}

<?php
// File: inc/launchpad/Api/ProfileController.php

declare(strict_types=1);

namespace Launchpad\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ProfileController extends AbstractApiController
{
    public function registerRoutes(): void
    {
        // Endpoint: POST /wp-json/launchpad/v1/profile
        register_rest_route($this->namespace, '/profile', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateProfile'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'firstName' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'lastName'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'email'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        $data = ['ID' => $userId];

        // Only update fields that were actually sent
        if ($request->get_param('firstName') !== null) {
            $data['first_name'] = $request->get_param('firstName');
        }
        if ($request->get_param('lastName') !== null) {
            $data['last_name'] = $request->get_param('lastName');
        }
        if ($request->get_param('email') !== null) {
            $data['user_email'] = $request->get_param('email');
        }

        $result = wp_update_user($data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Return the updated data to keep the JS store in sync
        return $this->success([
            'success'   => true,
            'message'   => __('Profile updated.', 'starwishx'),
            'firstName' => $data['first_name'] ?? '',
            'lastName'  => $data['last_name'] ?? '',
            'email'     => $data['user_email'] ?? '',
        ]);
    }
}

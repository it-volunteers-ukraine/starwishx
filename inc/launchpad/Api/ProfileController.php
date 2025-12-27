<?php
// File: inc/launchpad/Api/ProfileController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\ProfileService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ProfileController extends AbstractApiController
{
    private ProfileService $service;

    public function __construct()
    {
        $this->service = new ProfileService();
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/profile', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateProfile'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'firstName' => ['sanitize_callback' => 'sanitize_text_field'],
                'lastName'  => ['sanitize_callback' => 'sanitize_text_field'],
                'email'     => ['sanitize_callback' => 'sanitize_email'],
                // New Fields
                'phone'     => ['sanitize_callback' => 'sanitize_text_field'],
                'telegram'  => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Get all params (sanitized by registerRoutes args)
        $params = $request->get_params();
        error_log('REST updateProfile params: ' . print_r($params, true));
        
        // Also log raw body for extra confidence
        $raw_body = $request->get_body();
        error_log('REST raw body: ' . $raw_body);

        // Pass to Service
        $result = $this->service->updateProfile(get_current_user_id(), $params);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success([
            'success' => true,
            'message' => __('Profile updated.', 'starwishx'),
            // Spread the updated data back to frontend
            ...$result
        ]);
    }
}

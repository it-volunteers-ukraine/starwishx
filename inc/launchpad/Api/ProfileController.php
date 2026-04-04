<?php
// File: inc/launchpad/Api/ProfileController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\ProfileService;
use Shared\Sanitize\InputSanitizer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ProfileController extends AbstractLaunchpadController
{
    private ProfileService $service;

    public function __construct(ProfileService $service)
    {
        $this->service = $service;
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
                'email'     => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        return !empty($value) && is_email($value);
                    },
                ],
                // Core WP fields
                'nickname'    => ['sanitize_callback' => 'sanitize_text_field'],
                'displayName' => ['sanitize_callback' => 'sanitize_text_field'],
                'userUrl'     => ['sanitize_callback' => [InputSanitizer::class, 'sanitizeUrl']],
                'description' => ['sanitize_callback' => 'sanitize_textarea_field'],
                // Additional ACF Fields
                'phone'        => ['sanitize_callback' => 'sanitize_text_field'],
                'phoneCountry' => ['sanitize_callback' => 'sanitize_text_field'],
                'telegram'     => ['sanitize_callback' => 'sanitize_text_field'],
                'organization' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route($this->namespace, '/profile/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deleteAccount'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Get all params (sanitized by registerRoutes args)
        $params = $request->get_params();

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

    public function deleteAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->deleteAccount(
            get_current_user_id(),
            $request->get_param('password')
        );

        if (is_wp_error($result)) {
            return $this->error(
                $result->get_error_message(),
                $result->get_error_code() === 'forbidden' ? 403 : 400,
                $result->get_error_code()
            );
        }

        return $this->success([
            'success' => true,
            'message' => __('Your account has been deleted.', 'starwishx'),
        ]);
    }
}

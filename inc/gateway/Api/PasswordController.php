<?php

/**
 * REST controller for password reset endpoints.
 * 
 * File: inc/gateway/Services/PasswordController.php
 */

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Gateway\Services\PasswordResetService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class PasswordController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';
    private PasswordResetService $service;
    public function __construct(?PasswordResetService $service = null)
    {
        $this->service = $service ?? new PasswordResetService();
    }
    public function registerRoutes(): void
    {
        // Corresponds to action=lostpassword
        register_rest_route($this->namespace, '/password/lost', [
            'methods'             => 'POST',
            'callback'            => [$this, 'lostPassword'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'user_login' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        // Corresponds to action=rp
        register_rest_route($this->namespace, '/password/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resetPassword'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'login'    => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
                'key'      => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'password' => ['required' => true],
            ],
        ]);
    }
    public function lostPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->handleLostPassword($request->get_param('user_login'));
        if (is_wp_error($result)) {
            return $this->mapServiceError($result);
        }
        return $this->success([
            'success' => true,
            'message' => __('If an account exists, a reset link has been sent.', 'starwishx'),
        ]);
    }
    public function resetPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->resetPassword(
            $request->get_param('key'),
            $request->get_param('login'),
            $request->get_param('password')
        );
        if (is_wp_error($result)) {
            return $this->mapServiceError($result);
        }
        return $this->success([
            'success' => true,
            'message' => __('Password reset successfully!', 'starwishx'),
        ]);
    }
    /**
     * Convert service WP_Error to proper REST error with HTTP status.
     * Maps error codes to appropriate HTTP status codes.
     *
     * @param WP_Error $error Error from service layer
     * @return WP_Error Error with proper HTTP status code in data
     */
    private function mapServiceError(WP_Error $error): WP_Error
    {
        $code = $error->get_error_code();
        // $message = $error->get_error_message();

        // Map error codes to HTTP status codes
        $status_map = [
            // Validation errors -> 400 Bad Request
            'password_too_short'         => 400,
            'password_too_weak'          => 400,
            'password_contains_userdata' => 400,
            'empty_username'             => 400,
            // Authentication/Authorization errors -> 401 Unauthorized
            'invalid_key'                => 401,
            // Rate limiting -> 429 Too Many Requests
            'too_many_attempts'          => 429,
        ];

        // Default to 400 for unknown validation errors
        $status = $status_map[$code] ?? 400;

        // return new WP_Error($code, $message, ['status' => $status]);

        // Or like this: allows the WP REST Server to see the 'status' and send the right header
        $error->add_data(['status' => $status]);

        return $error;
    }
}

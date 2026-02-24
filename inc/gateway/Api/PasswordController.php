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
        // GET /gateway/v1/password/generate
        register_rest_route($this->namespace, '/password/generate', [
            'methods'             => 'GET',
            'callback'            => [$this, 'generatePassword'],
            'permission_callback' => '__return_true', // Public endpoint (just returns random string)
        ]);
    }
    public function lostPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->handleLostPassword($request->get_param('user_login'));
        if (is_wp_error($result)) {
            // Only codes that handleLostPassword can actually return:
            // - empty_username (PasswordResetService.php:33)
            // - too_many_attempts (via checkRateLimit, PasswordResetService.php:39-40)
            return $this->mapServiceError($result, [
                'empty_username'    => 400,
                'too_many_attempts' => 429,
            ]);
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
            // Only codes that resetPassword can actually return:
            // - invalid_key (via validateKey, PasswordResetService.php:119-121)
            // - password_too_short, password_too_weak, password_contains_userdata (via validatePasswordStrength, PasswordResetService.php:125-128)
            // NOTE: resetPassword does NOT call checkRateLimit, so too_many_attempts is NOT returned here
            return $this->mapServiceError($result, [
                'password_too_short'         => 400,
                'password_too_weak'          => 400,
                'password_contains_userdata' => 400,
                'invalid_key'                => 401,
            ]);
        }
        return $this->success([
            'success' => true,
            'message' => __('Password reset successfully!', 'starwishx'),
        ]);
    }

    /**
     * Generate a strong password via REST.
     */
    public function generatePassword(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->success([
            'success'  => true,
            'password' => $this->service->generatePassword(),
        ]);
    }
}

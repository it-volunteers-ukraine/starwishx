<?php

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Gateway\Services\PasswordResetService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for password reset endpoints.
 */
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
        register_rest_route($this->namespace, '/password/forgot', [
            'methods'             => 'POST',
            'callback'            => [$this, 'forgotPassword'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'email' => ['required' => true, 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

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

    public function forgotPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->requestReset($request->get_param('email'));

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->success([
            'success' => true,
            'message' => __('If an account exists, a reset link has been sent.', 'starwishx'),
        ]);
    }

    public function resetPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->resetPassword(
            $request->get_param('login'),
            $request->get_param('key'),
            $request->get_param('password')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->success([
            'success' => true,
            'message' => __('Password reset successfully!', 'starwishx'),
        ]);
    }
}
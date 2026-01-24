<?php

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Gateway\Services\RegisterService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for registration endpoints.
 */
class RegisterController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';
    private RegisterService $service;

    public function __construct(?RegisterService $service = null)
    {
        $this->service = $service ?? new RegisterService();
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'register'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'username' => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
                'email'    => ['required' => true, 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);
    }

    public function register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->handleRegistration(
            (string) $request->get_param('username'),
            (string) $request->get_param('email')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->success([
            'success' => true,
            'message' => __('Registration successful! Please check your email to set your password.', 'starwishx'),
        ]);
    }
}

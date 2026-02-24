<?php

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Gateway\Services\AuthService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for authentication endpoints.
 */
class AuthController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';
    private AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        $this->service = $service ?? new AuthService();
    }

    public function registerRoutes(): void
    {
        // POST /gateway/v1/login
        register_rest_route($this->namespace, '/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'login'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'remember' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // POST /gateway/v1/logout
        register_rest_route($this->namespace, '/logout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'logout'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);
    }

    /**
     * Handle login request.
     */
    public function login(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->authenticate(
            (string) $request->get_param('username'),
            (string) $request->get_param('password'),
            (bool) $request->get_param('remember')
        );

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                // AuthService wraps WP errors and returns these codes:
                'auth_failed' => 401,  // Invalid credentials (AuthService.php:54)
                // Note: rate_limited is in default_map, not repeated here
            ]);
        }

        return $this->success([
            'success'    => true,
            'redirectTo' => $result['redirect_to'],
            'user'       => [
                'id'          => $result['user']->ID,
                'displayName' => $result['user']->display_name,
            ],
        ]);
    }

    /**
     * Handle logout request.
     */
    public function logout(WP_REST_Request $request): WP_REST_Response
    {
        $this->service->logout();

        return $this->success([
            'success'    => true,
            'redirectTo' => home_url('/gateway/'),
        ]);
    }
}
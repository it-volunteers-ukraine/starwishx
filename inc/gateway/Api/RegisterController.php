<?php

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for registration endpoints.
 */
class RegisterController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'register'],
            'permission_callback' => [$this, 'checkLoggedOut'],
            'args'                => [
                'username' => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
                'email'    => ['required' => true, 'sanitize_callback' => 'sanitize_email'],
                'password' => ['required' => true],
            ],
        ]);
    }

    public function register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $password = $request->get_param('password');

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return $this->error($user_id->get_error_message());
        }

        return $this->success([
            'success' => true,
            'message' => __('Registration successful!', 'starwishx'),
        ]);
    }
}
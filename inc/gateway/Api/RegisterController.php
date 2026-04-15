<?php

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Shared\Policy\EmailPolicy;
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
            'permission_callback' => function (WP_REST_Request $request) {
                $loggedOut = $this->checkLoggedOut($request);
                if (is_wp_error($loggedOut)) {
                    return $loggedOut;
                }
                return $this->checkRestNonce($request);
            },
            'args'                => [
                'username' => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
                'email'    => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        if (empty($value)) {
                            return false;
                        }
                        return EmailPolicy::validate($value);
                    },
                ],
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
            return $this->mapServiceError($result, [
                // Note: invalid_data is in default_map, not repeated here
                'invalid_username_format' => 422,
                'username_exists'        => 409,
                'email_exists'           => 409,
            ]);
        }

        return $this->success([
            'success' => true,
            'message' => __('Registration successful! Please check your email to set your password.', 'starwishx'),
        ]);
    }
}

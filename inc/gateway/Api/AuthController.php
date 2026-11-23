<?php

declare(strict_types=1);

namespace Gateway\Api;

use Gateway\Enums\GatewayView;
use Gateway\Services\AuthService;
use Shared\Core\AbstractApiController;
use Shared\Validation\RestArg;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for authentication endpoints.
 */
final class AuthController extends AbstractApiController
{
    #[\Override]
    protected $namespace = 'gateway/v1';

    public function __construct(
        private readonly AuthService $service = new AuthService()
    ) {}

    #[\Override]
    public function registerRoutes(): void
    {
        // POST /gateway/v1/login
        register_rest_route($this->namespace, '/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'login'],
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::string(__('Username', 'starwishx')),
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
                'auth_failed'           => 401, // invalid credentials (rate_limited: default map, 429)
                'account_not_activated' => 403, // right password, no session until activated
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
            'redirectTo' => GatewayView::LOGIN->url(),
        ]);
    }
}

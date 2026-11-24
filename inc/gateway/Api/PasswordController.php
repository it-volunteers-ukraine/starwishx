<?php

/**
 * REST controller for password reset endpoints.
 *
 * File: inc/gateway/Api/PasswordController.php
 */

declare(strict_types=1);

namespace Gateway\Api;

use Gateway\Services\PasswordResetService;
use Shared\Core\AbstractApiController;
use Shared\Http\ClientIp;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PasswordController extends AbstractApiController
{
    #[\Override]
    protected $namespace = 'gateway/v1';

    // Per-IP budget — caps SMTP-flooding / cross-user email bombing.
    private const int LOST_IP_MAX    = 5;
    private const int LOST_IP_WINDOW = HOUR_IN_SECONDS;

    // Per (IP, user_login) — protects an individual user's inbox from a
    // targeted flood while keeping the per-IP cap as an overall ceiling.
    private const int LOST_TARGET_MAX    = 3;
    private const int LOST_TARGET_WINDOW = 15 * MINUTE_IN_SECONDS;

    public function __construct(
        private readonly PasswordResetService $service = new PasswordResetService()
    ) {}

    #[\Override]
    public function registerRoutes(): void
    {
        // Corresponds to action=lostpassword
        register_rest_route($this->namespace, '/password/lost', [
            'methods'             => 'POST',
            'callback'            => [$this, 'lostPassword'],
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'user_login' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::string(__('Username or email', 'starwishx')),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        // Corresponds to action=rp. Every value is validated as a string
        // before the service (typed `string`) sees it.
        register_rest_route($this->namespace, '/password/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resetPassword'],
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'login'    => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::string(__('Username', 'starwishx')),
                    'sanitize_callback' => 'sanitize_user',
                ],
                'key'      => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::string(__('Key', 'starwishx')),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // No sanitize_callback: a password is used as typed. `type`
                // alone makes WordPress check it (rest_parse_request_arg).
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);
        // GET /gateway/v1/password/generate
        // Bound to a page-load nonce so this can't be used as a public
        // random-string service from outside the gateway UI. Also used by
        // the Launchpad's change-password form (logged in).
        register_rest_route($this->namespace, '/password/generate', [
            'methods'             => 'GET',
            'callback'            => [$this, 'generatePassword'],
            'permission_callback' => [$this, 'checkRestNonce'],
        ]);
    }

    public function lostPassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_login = (string) $request->get_param('user_login');
        $ip = ClientIp::resolve();

        // Dual rate limit, evaluated before the user lookup.
        $ipKey = RateLimitPolicy::key('gateway.password_lost', $ip);
        $ipCheck = RateLimitPolicy::check(
            $ipKey,
            self::LOST_IP_MAX,
            self::LOST_IP_WINDOW,
            __('Too many password reset attempts. Please try again later.', 'starwishx')
        );
        if (is_wp_error($ipCheck)) {
            return $this->mapServiceError($ipCheck);
        }

        $targetKey = RateLimitPolicy::key('gateway.password_lost_target', $ip, $user_login);
        $targetCheck = RateLimitPolicy::check(
            $targetKey,
            self::LOST_TARGET_MAX,
            self::LOST_TARGET_WINDOW,
            __('Too many password reset attempts. Please try again later.', 'starwishx')
        );
        if (is_wp_error($targetCheck)) {
            return $this->mapServiceError($targetCheck);
        }

        RateLimitPolicy::hit($ipKey, self::LOST_IP_WINDOW);
        RateLimitPolicy::hit($targetKey, self::LOST_TARGET_WINDOW);

        $result = $this->service->handleLostPassword($user_login);
        if (is_wp_error($result)) {
            // Only code handleLostPassword can actually return now that
            // rate limiting lives at the controller edge: empty_username.
            return $this->mapServiceError($result, [
                'empty_username' => 400,
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
            (string) $request->get_param('key'),
            (string) $request->get_param('login'),
            (string) $request->get_param('password')
        );
        if (is_wp_error($result)) {
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
    public function generatePassword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->success([
            'success'  => true,
            'password' => $this->service->generatePassword(),
        ]);
    }
}

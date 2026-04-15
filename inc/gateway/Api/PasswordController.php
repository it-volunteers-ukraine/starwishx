<?php

/**
 * REST controller for password reset endpoints.
 * 
 * File: inc/gateway/Services/PasswordController.php
 */

declare(strict_types=1);

namespace Gateway\Api;

use Shared\Core\AbstractApiController;
use Shared\Http\ClientIp;
use Shared\Policy\RateLimitPolicy;
use Gateway\Services\PasswordResetService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class PasswordController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';

    // Per-IP budget — caps SMTP-flooding / cross-user email bombing.
    private const LOST_IP_MAX    = 5;
    private const LOST_IP_WINDOW = HOUR_IN_SECONDS;

    // Per (IP, user_login) — protects an individual user's inbox from a
    // targeted flood while keeping the per-IP cap as an overall ceiling.
    private const LOST_TARGET_MAX    = 3;
    private const LOST_TARGET_WINDOW = 15 * MINUTE_IN_SECONDS;

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
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'user_login' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        // Corresponds to action=rp
        register_rest_route($this->namespace, '/password/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resetPassword'],
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'login'    => ['required' => true, 'sanitize_callback' => 'sanitize_user'],
                'key'      => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'password' => ['required' => true],
            ],
        ]);
        // GET /gateway/v1/password/generate
        // Bound to a page-load nonce so this can't be used as a public
        // random-string service from outside the gateway UI.
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

        // Dual rate limit — evaluated BEFORE user lookup so timing cannot
        // reveal whether an account exists (the service applies the same
        // pre-lookup discipline for enumeration resistance).
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
            $request->get_param('key'),
            $request->get_param('login'),
            $request->get_param('password')
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
    public function generatePassword(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->success([
            'success'  => true,
            'password' => $this->service->generatePassword(),
        ]);
    }
}

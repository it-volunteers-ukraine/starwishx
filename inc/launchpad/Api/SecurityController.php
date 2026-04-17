<?php
// File: inc/launchpad/Api/SecurityController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\SecurityService;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SecurityController extends AbstractLaunchpadController
{
    private const PASSWORD_RATE_LIMIT_MAX    = 5;
    private const PASSWORD_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    private const PASSWORD_MAX = 256;

    private SecurityService $service;

    public function __construct(SecurityService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/security/password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'changePassword'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'currentPassword' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PASSWORD_MAX,
                        __('Current password', 'starwishx')
                    ),
                ],
                'newPassword' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PASSWORD_MAX,
                        __('New password', 'starwishx')
                    ),
                ],
            ],
        ]);
    }

    public function changePassword(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        // Conditional rate limit (auth pattern): only failed attempts count
        // toward the bucket; a successful change clears it. This avoids
        // penalizing a legitimate user who simply rotates their password
        // while still throttling brute-force on `currentPassword`.
        $rlKey = $this->checkAuthRateLimit(
            'security_password',
            $userId,
            self::PASSWORD_RATE_LIMIT_MAX,
            self::PASSWORD_RATE_LIMIT_WINDOW,
            __('Password change', 'starwishx')
        );
        if (is_wp_error($rlKey)) {
            return $rlKey;
        }

        $result = $this->service->changePassword(
            $userId,
            $request->get_param('currentPassword'),
            $request->get_param('newPassword')
        );

        if (is_wp_error($result)) {
            RateLimitPolicy::hit($rlKey, self::PASSWORD_RATE_LIMIT_WINDOW);

            return $this->mapServiceError($result, [
                'invalid_password'           => 422,
                'password_too_short'         => 422,
                'password_too_weak'          => 422,
                'password_contains_userdata' => 422,
            ]);
        }

        RateLimitPolicy::clear($rlKey);

        // Note: wp_set_password invalidates auth cookies.
        // The frontend launchpad-store.js must handle the redirect to login immediately.
        return $this->success([
            'success' => true,
            'message' => __('Password changed. Please log in again.', 'starwishx'),
        ]);
    }

    /**
     * Conditional rate-limit guard for auth flows.
     *
     * Returns the transient key on pass — the caller MUST subsequently call
     * `RateLimitPolicy::hit($key, $window)` on auth failure or
     * `RateLimitPolicy::clear($key)` on success. Returns a mapped WP_Error
     * (HTTP 429) when the limit is already exceeded.
     *
     * Mirrors the "Conditional (auth)" usage documented in RateLimitPolicy.
     * The 429 message names the action and shows a single-unit, localized
     * wait duration via human_time_diff() (e.g., "1 hour", "30 mins").
     */
    private function checkAuthRateLimit(
        string $action,
        int $userId,
        int $max,
        int $window,
        string $actionLabel
    ): string|WP_Error {
        $key = RateLimitPolicy::key($action, (string) $userId);

        $message = sprintf(
            /* translators: 1: action name (e.g., "Password change"), 2: human-readable wait duration */
            __('%1$s limit reached. Please wait %2$s before trying again.', 'starwishx'),
            $actionLabel,
            human_time_diff(time(), time() + $window)
        );

        $check = RateLimitPolicy::check($key, $max, $window, $message);
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        return $key;
    }
}

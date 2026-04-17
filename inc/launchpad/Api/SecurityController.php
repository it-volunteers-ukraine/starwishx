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

        $rateLimited = $this->applyPasswordRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $result = $this->service->changePassword(
            $userId,
            $request->get_param('currentPassword'),
            $request->get_param('newPassword')
        );

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                'invalid_password'           => 422,
                'password_too_short'         => 422,
                'password_too_weak'          => 422,
                'password_contains_userdata' => 422,
            ]);
        }

        // Note: wp_set_password invalidates auth cookies.
        // The frontend launchpad-store.js must handle the redirect to login immediately.
        return $this->success([
            'success' => true,
            'message' => __('Password changed. Please log in again.', 'starwishx'),
        ]);
    }

    /**
     * Per-user rate limit — password change bucket.
     *
     * Slows brute-force on `currentPassword` (the highest-blast-radius
     * endpoint in the dashboard — success rotates the credential and
     * locks out the legitimate owner).
     */
    private function applyPasswordRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'security_password',
            $userId,
            self::PASSWORD_RATE_LIMIT_MAX,
            self::PASSWORD_RATE_LIMIT_WINDOW,
            __('Password change', 'starwishx')
        );
    }

    /**
     * Generic per-user rate-limit guard with an action-named, friendly message.
     *
     * Mirrors OpportunitiesController / ProfileController — the user-facing
     * message names the action and shows a single-unit, localized wait
     * duration via human_time_diff() (e.g., "1 hour", "30 mins").
     *
     * `mapServiceError()` translates the policy's `rate_limited` code into 429.
     */
    private function applyRateLimit(
        string $action,
        int $userId,
        int $max,
        int $window,
        string $actionLabel
    ): ?WP_Error {
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

        RateLimitPolicy::hit($key, $window);

        return null;
    }
}

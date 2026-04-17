<?php
// File: inc/launchpad/Api/ProfileController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\ProfileService;
use Shared\Policy\EmailPolicy;
use Shared\Policy\RateLimitPolicy;
use Shared\Sanitize\InputSanitizer;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ProfileController extends AbstractLaunchpadController
{
    private const WRITE_RATE_LIMIT_MAX     = 30;
    private const WRITE_RATE_LIMIT_WINDOW  = HOUR_IN_SECONDS;
    private const EMAIL_RATE_LIMIT_MAX     = 5;
    private const EMAIL_RATE_LIMIT_WINDOW  = HOUR_IN_SECONDS;
    private const DELETE_RATE_LIMIT_MAX    = 3;
    private const DELETE_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    private const FIRST_NAME_MAX    = 80;
    private const LAST_NAME_MAX     = 80;
    private const NICKNAME_MAX      = 50;
    private const DISPLAY_NAME_MAX  = 100;
    private const USER_URL_MAX      = 500;
    private const DESCRIPTION_MAX   = 1000;
    private const PHONE_MAX         = 32;
    private const PHONE_COUNTRY_MAX = 8;
    private const TELEGRAM_MAX      = 64;
    private const ORGANIZATION_MAX  = 200;
    private const PASSWORD_MAX      = 256;

    /**
     * Allowlist of fields the /profile endpoint forwards to the service.
     * Any other inbound key is dropped at the controller boundary — the
     * service still uses isset() per field, but the explicit list makes the
     * contract obvious and defends against future service-side mass-assignment.
     */
    private const PROFILE_FIELDS = [
        'firstName',
        'lastName',
        'nickname',
        'displayName',
        'userUrl',
        'description',
        'phone',
        'phoneCountry',
        'telegram',
        'organization',
        'receiveMailNotifications',
    ];

    private ProfileService $service;

    public function __construct(ProfileService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/profile', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateProfile'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'firstName' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::FIRST_NAME_MAX,
                        __('First name', 'starwishx')
                    ),
                ],
                'lastName'  => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::LAST_NAME_MAX,
                        __('Last name', 'starwishx')
                    ),
                ],
                // Email is handled by the dedicated /profile/email endpoint.
                // Core WP fields
                'nickname'    => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::NICKNAME_MAX,
                        __('Nickname', 'starwishx')
                    ),
                ],
                'displayName' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::DISPLAY_NAME_MAX,
                        __('Display name', 'starwishx')
                    ),
                ],
                'userUrl'     => [
                    'sanitize_callback' => [InputSanitizer::class, 'sanitizeUrl'],
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::USER_URL_MAX,
                        __('Website URL', 'starwishx')
                    ),
                ],
                'description' => [
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::DESCRIPTION_MAX,
                        __('Bio', 'starwishx')
                    ),
                ],
                // Additional ACF Fields
                'phone'        => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PHONE_MAX,
                        __('Phone', 'starwishx')
                    ),
                ],
                'phoneCountry' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PHONE_COUNTRY_MAX,
                        __('Phone country', 'starwishx')
                    ),
                ],
                'telegram'     => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::TELEGRAM_MAX,
                        __('Telegram', 'starwishx')
                    ),
                ],
                'organization' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::ORGANIZATION_MAX,
                        __('Organization', 'starwishx')
                    ),
                ],
                'receiveMailNotifications' => [
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/profile/email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'changeEmail'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        // EmailPolicy treats '' as valid (its callers handle
                        // optionality), so guard explicitly here — the email
                        // change endpoint requires a real address.
                        if ($value === '' || $value === null) {
                            return new WP_Error(
                                'email_required',
                                __('Please enter a valid email address.', 'starwishx'),
                                ['status' => 422]
                            );
                        }
                        return EmailPolicy::validate((string) $value);
                    },
                ],
                'password' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PASSWORD_MAX,
                        __('Password', 'starwishx')
                    ),
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/profile/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deleteAccount'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'password' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::PASSWORD_MAX,
                        __('Password', 'starwishx')
                    ),
                ],
            ],
        ]);
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        // Explicit allowlist — only forward declared profile fields to the
        // service, regardless of what else the client may have submitted.
        $params = array_intersect_key(
            $request->get_params(),
            array_flip(self::PROFILE_FIELDS)
        );

        $result = $this->service->updateProfile($userId, $params);

        if (is_wp_error($result)) {
            return $this->mapServiceError($result);
        }

        return $this->success([
            'success' => true,
            'message' => __('Profile updated.', 'starwishx'),
            ...$result
        ]);
    }

    public function changeEmail(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyEmailRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $result = $this->service->changeEmail(
            $userId,
            $request->get_param('email'),
            $request->get_param('password')
        );

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                'invalid_password'  => 422,
                'email_exists'      => 422,
                'email_invalid'     => 422,
                'email_dns_failed'  => 422,
            ]);
        }

        return $this->success([
            'success' => true,
            'message' => __('Email updated.', 'starwishx'),
            ...$result
        ]);
    }

    public function deleteAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyDeleteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $result = $this->service->deleteAccount(
            $userId,
            $request->get_param('password')
        );

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                'forbidden'        => 403,
                'invalid_password' => 422,
                'not_found'        => 404,
                'delete_failed'    => 500,
            ]);
        }

        return $this->success([
            'success' => true,
            'message' => __('Your account has been deleted.', 'starwishx'),
        ]);
    }

    /** Per-user rate limit — profile-update bucket. */
    private function applyWriteRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'profile_write',
            $userId,
            self::WRITE_RATE_LIMIT_MAX,
            self::WRITE_RATE_LIMIT_WINDOW,
            __('Profile changes', 'starwishx')
        );
    }

    /** Per-user rate limit — email change bucket (also slows password brute-force). */
    private function applyEmailRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'profile_email',
            $userId,
            self::EMAIL_RATE_LIMIT_MAX,
            self::EMAIL_RATE_LIMIT_WINDOW,
            __('Email change', 'starwishx')
        );
    }

    /** Per-user rate limit — account-deletion bucket (also slows password brute-force). */
    private function applyDeleteRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'profile_delete',
            $userId,
            self::DELETE_RATE_LIMIT_MAX,
            self::DELETE_RATE_LIMIT_WINDOW,
            __('Account deletion', 'starwishx')
        );
    }

    /**
     * Generic per-user rate-limit guard with an action-named, friendly message.
     *
     * Mirrors OpportunitiesController::applyRateLimit — the user-facing message
     * names the action and shows a single-unit, localized wait duration via
     * human_time_diff() (e.g., "1 hour", "30 mins"), derived from the window
     * so the wording tracks any future window changes.
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
            /* translators: 1: action name (e.g., "Profile changes"), 2: human-readable wait duration */
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

<?php

declare(strict_types=1);

namespace Gateway\Api;

use Gateway\Services\RegisterService;
use Shared\Core\AbstractApiController;
use Shared\Http\ClientIp;
use Shared\Policy\EmailPolicy;
use Shared\Policy\RateLimitPolicy;
use Shared\Policy\UsernamePolicy;
use Shared\Validation\RestArg;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for registration endpoints.
 */
final class RegisterController extends AbstractApiController
{
    protected $namespace = 'gateway/v1';

    private const int RATE_LIMIT_MAX    = 5;
    private const int RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    public function __construct(
        private readonly RegisterService $service = new RegisterService()
    ) {}

    #[\Override]
    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'register'],
            'permission_callback' => [$this, 'checkGuestWithNonce'],
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => RestArg::string(
                        __('Username', 'starwishx'),
                        UsernamePolicy::validate(...)
                    ),
                    'sanitize_callback' => 'sanitize_user',
                ],
                'email'    => [
                    'required'          => true,
                    'type'              => 'string',
                    // EmailPolicy accepts '' (optional fields elsewhere):
                    // required here, so empty fails first.
                    'validate_callback' => RestArg::string(
                        __('Email', 'starwishx'),
                        static fn(string $email): bool|WP_Error
                            => trim($email) === '' ? false : EmailPolicy::validate($email)
                    ),
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);
    }

    public function register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Rate limit: every attempt past this point counts against the IP's
        // quota, including ones that fail validation downstream. Abuse
        // prevention beats typo leniency for registration.
        $rlKey = RateLimitPolicy::key('gateway.register', ClientIp::resolve());
        $rlCheck = RateLimitPolicy::check(
            $rlKey,
            self::RATE_LIMIT_MAX,
            self::RATE_LIMIT_WINDOW,
            __('Too many registration attempts. Please try again later.', 'starwishx')
        );
        if (is_wp_error($rlCheck)) {
            return $this->mapServiceError($rlCheck);
        }
        RateLimitPolicy::hit($rlKey, self::RATE_LIMIT_WINDOW);

        $result = $this->service->handleRegistration(
            (string) $request->get_param('username'),
            (string) $request->get_param('email')
        );

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                // invalid_data: default map
                'invalid_username_format' => 422,
                'username_exists'         => 409,
                'email_exists'            => 409,
            ]);
        }

        return $this->success([
            'success' => true,
            'message' => __('Registration successful! Please check your email to set your password.', 'starwishx'),
        ]);
    }
}

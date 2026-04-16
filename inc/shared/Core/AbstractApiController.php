<?php
// File: inc/shared/Core/AbstractApiController.php
declare(strict_types=1);

namespace Shared\Core;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

/**
 * Base class for REST API controllers.
 * Provides common functionality for authentication, response formatting, and namespace management.
 */
abstract class AbstractApiController extends WP_REST_Controller
{
    /**
     * Every child must define its own namespace (e.g., 'launchpad/v1')
     */
    protected $namespace;

    /**
     * Register REST API routes.
     * Must be implemented by subclasses.
     */
    abstract public function registerRoutes(): void;

    /**
     * Shared Guard: Is Logged In
     *
     * Returns WP_Error (not bare false) for unambiguous HTTP response.
     */
    public function checkLoggedIn(WP_REST_Request $request): bool|WP_Error
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'not_authenticated',
                __('You must be logged in to access this resource.', 'starwishx'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Shared Guard: Is Logged Out
     *
     * Returns WP_Error (not bare false) for unambiguous HTTP response.
     *
     * NOTE: WordPress requires BOTH auth cookie AND valid nonce to report a
     * user as logged-in in REST context. A request with cookies but no nonce
     * (e.g., Postman) is seen as logged-out here — this is a WordPress
     * constraint, not fixed in this guard. The correct fix for that gap is
     * to compose this guard with checkRestNonce() in the permission callback.
     */
    public function checkLoggedOut(WP_REST_Request $request): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return new WP_Error(
                'already_authenticated',
                __('You are already logged in.', 'starwishx'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Shared Guard: Require a valid wp_rest nonce
     *
     * WordPress core only verifies the nonce for requests that already carry
     * auth cookies (see rest_cookie_check_errors). Anonymous requests bypass
     * nonce validation entirely, which is why Postman can hit a public
     * endpoint without any token. This guard closes that gap by verifying
     * the wp_rest nonce regardless of auth state, binding the request to a
     * prior page load where the nonce was hydrated into the client.
     *
     * Uses the same error code as WP core (rest_cookie_invalid_nonce) so the
     * client retry flow in gateway utils.js recovers automatically when a
     * legitimate user's nonce has rolled over.
     */
    public function checkRestNonce(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_cookie_invalid_nonce',
                __('Cookie check failed.', 'starwishx'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Shared Guard: Guest + valid wp_rest nonce
     *
     * Convenience composite for public endpoints that must only accept
     * anonymous requests originating from a page that was issued a nonce
     * (login, register, password recovery). Short-circuits on the first
     * failing guard so the caller gets a precise error code.
     */
    public function checkGuestWithNonce(WP_REST_Request $request): bool|WP_Error
    {
        $loggedOut = $this->checkLoggedOut($request);
        if (is_wp_error($loggedOut)) {
            return $loggedOut;
        }

        return $this->checkRestNonce($request);
    }

    /**
     * Shared Guard: Logged-in user + valid wp_rest nonce
     *
     * Binds authenticated requests to a page-load origin. WP core already
     * enforces the nonce for cookie auth (via rest_cookie_check_errors), so
     * this guard is a no-op for the browser path. Its value is closing the
     * non-cookie auth bypass: Application Passwords / JWT / OAuth accept
     * credentials without a nonce, which would let a script with a leaked
     * token reach write endpoints from Postman. Requiring the nonce on top
     * means the attacker must also compromise an interactive browser
     * session — a meaningfully higher bar.
     */
    public function checkLoggedInWithNonce(WP_REST_Request $request): bool|WP_Error
    {
        $loggedIn = $this->checkLoggedIn($request);
        if (is_wp_error($loggedIn)) {
            return $loggedIn;
        }

        return $this->checkRestNonce($request);
    }

    /**
     * Unified success response
     *
     * @param array $data Response data
     * @param int $status HTTP status code
     */
    protected function success(array $data = [], int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    /**
     * Convert service WP_Error to proper REST error with HTTP status.
     *
     * Unmapped error codes fall back to 400 (Bad Request). This is intentional:
     * WordPress core functions like wp_insert_user() may return their own error
     * codes (e.g., existing_user_login) which we don't explicitly map. The 400
     * fallback provides reasonable semantics for unexpected validation failures.
     *
     * @param WP_Error $error Error from service layer
     * @param array $custom_map Override/add specific error code mappings
     * @return WP_Error Error with proper HTTP status code in data
     */
    protected function mapServiceError(WP_Error $error, array $custom_map = []): WP_Error
    {
        $code = $error->get_error_code();

        // Default map: only codes that multiple services actually return
        $default_map = [
            'invalid_data'      => 422,
            'not_found'         => 404,
            'rate_limited'      => 429,
            'phone_invalid'     => 422,
            'phone_parse_error' => 422,
            'email_invalid'     => 422,
            'email_dns_failed'  => 422,
        ];

        $status_map = array_merge($default_map, $custom_map);
        $status = $status_map[$code] ?? 400;

        // Merge status into existing data (preserves field_errors, etc.)
        $existing = $error->get_error_data($code);
        $data = is_array($existing) ? $existing : [];
        $data['status'] = $status;
        $error->add_data($data, $code);

        return $error;
    }

    /**
     * Unified authoritative error response
     *
     * WordPress automatically converts WP_Error into a JSON object:
     * { "code": "...", "message": "...", "data": { "status": ... } }
     */
    protected function error(
        string $message,
        int $status = 400,
        string $code = 'api_error'
    ): WP_Error {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}

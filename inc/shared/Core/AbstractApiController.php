<?php

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
     * NOTE: This fixes the case where browser requests via Interactivity API
     * (with X-WP-Nonce) correctly identify authenticated users. For requests
     * with cookies but no nonce (e.g., Postman), is_user_logged_in() returns
     * false in REST context - this is a WordPress constraint, not fixed here.
     * The WP_Error return ensures unambiguous handling when authentication
     * state IS correctly detected.
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
            'invalid_data'   => 422,
            'not_found'      => 404,
            'rate_limited'   => 429,
        ];

        $status_map = array_merge($default_map, $custom_map);
        $status = $status_map[$code] ?? 400;

        // Pin status to the specific error code for deterministic behavior
        $error->add_data(['status' => $status], $code);

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

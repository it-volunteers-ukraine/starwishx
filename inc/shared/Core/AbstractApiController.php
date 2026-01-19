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
     */
    public function checkLoggedIn(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    /**
     * Shared Guard: Is Logged Out
     */
    public function checkLoggedOut(WP_REST_Request $request): bool
    {
        return !is_user_logged_in();
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

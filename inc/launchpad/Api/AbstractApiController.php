<?php
// File: inc/launchpad/Api/AbstractApiController.php

declare(strict_types=1);

namespace Launchpad\Api;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

abstract class AbstractApiController extends WP_REST_Controller
{
    // The shared namespace for all endpoints (launchpad/v1)
    protected $namespace = 'launchpad/v1';

    /**
     * Enforce that every controller must register its own routes.
     */
    abstract public function registerRoutes(): void;

    /**
     * Standard permission callback.
     * Centralizes the logic for "Who can access this API?".
     */
    public function checkLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Helper to return standard JSON success responses.
     */
    protected function success(array $data = []): WP_REST_Response
    {
        return new WP_REST_Response($data, 200);
    }

    /**
     * Helper to return standard error responses.
     */
    protected function error(string $message, int $status = 400, string $code = 'launchpad_error'): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}

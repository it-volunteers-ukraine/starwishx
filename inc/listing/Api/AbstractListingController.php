<?php
// file: inc/listing/Api/AbstractListingController.php
declare(strict_types=1);

namespace Listing\Api;

use Shared\Core\AbstractApiController;
use WP_REST_Request;

/**
 * Base controller for Public Listing endpoints.
 */
abstract class AbstractListingController extends AbstractApiController
{
    /**
     * The versioned namespace for the Listing API.
     */
    protected $namespace = 'listing/v1';

    /**
     * Permission Callback: Allows public access to search.
     * Even if public, WordPress handles nonces via 'X-WP-Nonce' to prevent CSRF
     * if the user happens to be logged in.
     */
    public function publicAccess(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Helper to get and sanitize the 'query' object from the request.
     */
    protected function getFilterParams(WP_REST_Request $request): array
    {
        $params = $request->get_params();
        // Remove standard REST params to leave only our filter keys
        unset($params['_locale'], $params['_wpnonce']);
        return $params;
    }
}

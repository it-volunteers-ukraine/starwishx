<?php
// file: inc/listing/Api/AbstractListingController.php
declare(strict_types=1);

namespace Listing\Api;

use Shared\Core\AbstractApiController;
use Shared\Http\QueryStringParser;
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
     *
     * The listing client sends no nonce (fetchJson() in
     * inc/listing/Assets/utils.js), so every request runs as a guest's: no
     * per-user data in the response, and no 403 from core's nonce check when
     * the page that made the request is a day old or came from a cache.
     */
    public function publicAccess(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * The request's raw query parameters, repeated keys kept as lists:
     *   ?category=1&category=2  →  ['category' => ['1', '2']]
     *
     * Read from the raw QUERY_STRING because WP_REST_Request::get_params()
     * keeps only the last of repeated keys without [] notation. Nothing is
     * cast or capped here: ListingQuery::fromRaw() does that, the same way
     * for the REST search and the page load.
     */
    protected function getFilterParams(WP_REST_Request $request): array
    {
        $params = QueryStringParser::fromServer();

        // Strip framework internals that are not filter keys
        unset($params['_locale'], $params['_wpnonce']);

        return $params;
    }
}

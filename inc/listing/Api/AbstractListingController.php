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
     * Defensive cap on repeated-key array params after normalization in
     * getFilterParams(). Intentionally set high enough that no realistic UI
     * selection is truncated (facet trees may legitimately push into the
     * low hundreds when users expand multiple categories), while still
     * blocking pathological payloads like ?category=1&category=2&…×10k.
     *
     * This is a DoS cap, not a UX constraint.
     */
    protected const ARRAY_PARAM_MAX = 100;

    /**
     * Declare which query params should always resolve as arrays,
     * regardless of how many times they appear in the query string.
     *
     * Override in concrete controllers to match their route schema:
     *
     *   protected function arrayParamKeys(): array
     *   {
     *       return ['seekers', 'category', 'country'];
     *   }
     *
     * @return string[]
     */
    protected function arrayParamKeys(): array
    {
        return [];
    }

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
     * Helper to get and sanitize filter params from the request.
     *
     * Uses the raw QUERY_STRING instead of WP_REST_Request::get_params() so that
     * repeated keys without [] notation are preserved as arrays:
     *   ?category=1&category=2  →  ['category' => ['1', '2']]
     *
     * Params declared in arrayParamKeys() are always cast to arrays, even when
     * only a single value is present, giving the service layer a consistent type.
     * Each declared array is also truncated to ARRAY_PARAM_MAX items as a DoS
     * defense — WP's args schema cannot validate repeated-key params, so the
     * cap has to live here.
     */
    protected function getFilterParams(WP_REST_Request $request): array
    {
        $params = QueryStringParser::fromServer();

        // Strip framework internals that are not filter keys
        unset($params['_locale'], $params['_wpnonce']);

        // Normalize declared array params: scalar → single-element array,
        // then cap length. The cap sits here because these keys bypass WP's
        // schema layer (see class docblock on arrayParamKeys).
        foreach ($this->arrayParamKeys() as $key) {
            if (array_key_exists($key, $params)) {
                $params[$key] = array_slice((array) $params[$key], 0, static::ARRAY_PARAM_MAX);
            }
        }

        return $params;
    }
}

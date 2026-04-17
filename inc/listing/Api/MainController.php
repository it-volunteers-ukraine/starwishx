<?php
// file: inc/listing/Api/MainController.php
declare(strict_types=1);

namespace Listing\Api;

use Listing\Services\ListingService;
use Shared\Http\ClientIp;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MainController extends AbstractListingController
{
    private const Q_MIN_LENGTH = 2;
    private const Q_MAX_LENGTH = 100;
    private const LOCATION_MAX_LENGTH = 100;
    private const S_MAX_LENGTH = 200;
    private const PAGE_MAX = 1000;

    // Per-IP rate limits — anonymous endpoints must bucket by IP, not user.
    // Both /search and /sub-filter share the same ceiling; /search is more
    // expensive (≈15 queries/call via getFacets), but /sub-filter is faster
    // to spam, so the effective floor matches.
    private const SEARCH_RATE_LIMIT_MAX    = 60;
    private const SEARCH_RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

    private const SUB_FILTER_RATE_LIMIT_MAX    = 60;
    private const SUB_FILTER_RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

    private ListingService $service;

    public function __construct(ListingService $service)
    {
        $this->service = $service;
    }

    /**
     * Declare which params must always be arrays for the search route.
     * Consumed by AbstractListingController::getFilterParams().
     *
     * @return string[]
     */
    protected function arrayParamKeys(): array
    {
        return ['seekers', 'category', 'country'];
    }

    public function registerRoutes(): void
    {
        // GET /wp-json/listing/v1/search
        register_rest_route($this->namespace, '/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'publicAccess'],
            'args'                => [
                // Scalar params validated at the WP layer.
                // Array params (category, country, seekers) are handled by
                // QueryStringParser — they bypass WP's schema because WP
                // cannot represent repeated keys without [] notation. Their
                // length cap lives in AbstractListingController::getFilterParams.
                'page'     => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'validate_callback' => RestArg::intRange(
                        1,
                        self::PAGE_MAX,
                        __('Page', 'starwishx')
                    ),
                ],
                'location' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::LOCATION_MAX_LENGTH,
                        __('Location', 'starwishx')
                    ),
                ],
                // The `s` param is consumed by QueryBuilder via getFilterParams();
                // declaring it here also runs sanitize+validate at the WP layer
                // before it reaches the service. Without this entry the param
                // would still be accepted (via raw QUERY_STRING) but unbounded.
                's' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        self::S_MAX_LENGTH,
                        __('Search', 'starwishx')
                    ),
                ],
            ],
        ]);

        // GET /wp-json/listing/v1/sub-filter/{filter_id}
        // Used for the "Search inside City" functionality
        register_rest_route($this->namespace, '/sub-filter/(?P<filter_id>[a-zA-Z0-9-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchSubFilter'],
            'permission_callback' => [$this, 'publicAccess'],
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        self::Q_MIN_LENGTH,
                        self::Q_MAX_LENGTH,
                        __('Search query', 'starwishx')
                    ),
                ],
                // Reject unsupported filter IDs before they reach the service.
                // The whitelist is owned by ListingService::SUPPORTED_SUB_FILTERS
                // so adding a new sub-filter is a one-line change.
                'filter_id' => [
                    'required'          => true,
                    'validate_callback' => function ($value) {
                        if (in_array($value, ListingService::SUPPORTED_SUB_FILTERS, true)) {
                            return true;
                        }
                        return new WP_Error(
                            'unsupported_filter',
                            sprintf(
                                /* translators: %s: filter identifier supplied by client */
                                __('Filter "%s" is not supported.', 'starwishx'),
                                (string) $value
                            ),
                            ['status' => 400]
                        );
                    },
                ],
            ],
        ]);
    }

    /**
     * The primary search endpoint.
     * Returns: { items: [], facets: {}, total: 0, total_pages: 0, page: 1 }
     */
    public function search(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimited = $this->applySearchRateLimit();
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $filters = $this->getFilterParams($request);

        $results = $this->service->search($filters);
        $facets  = $this->service->getFacets($filters);

        return $this->success([
            'items'       => $results['items'],
            'facets'      => $facets,
            'total'       => $results['total'],
            'total_pages' => $results['total_pages'],
            'page'        => $results['page'],
        ]);
    }

    /**
     * Specialized endpoint for high-cardinality meta fields (e.g. searching Cities).
     * Invalid filter_id values are rejected by the route's validate_callback,
     * so by the time this runs the value is always in the supported set.
     */
    public function searchSubFilter(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimited = $this->applySubFilterRateLimit();
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $filterId   = $request->get_param('filter_id');
        $searchTerm = $request->get_param('q');

        $options = $this->service->searchFilterOptions($filterId, $searchTerm);

        if (is_wp_error($options)) {
            return $this->mapServiceError($options);
        }

        return $this->success($options);
    }

    /**
     * Per-IP rate limit — /search bucket.
     * Each /search call runs ~15 DB queries (main WP_Query + getFacets
     * re-runs the user's filter context once per taxonomy), so the ceiling
     * is tuned to 1 req/s sustained.
     */
    private function applySearchRateLimit(): ?WP_Error
    {
        return $this->applyIpRateLimit(
            'listing_search',
            self::SEARCH_RATE_LIMIT_MAX,
            self::SEARCH_RATE_LIMIT_WINDOW,
            __('Search', 'starwishx')
        );
    }

    /**
     * Per-IP rate limit — /sub-filter bucket.
     * Typeahead is burstier than /search but each call is a single LIKE
     * query, so the ceiling matches. Separate bucket from /search so users
     * browsing the catalog don't exhaust their autocomplete budget.
     */
    private function applySubFilterRateLimit(): ?WP_Error
    {
        return $this->applyIpRateLimit(
            'listing_sub_filter',
            self::SUB_FILTER_RATE_LIMIT_MAX,
            self::SUB_FILTER_RATE_LIMIT_WINDOW,
            __('Location search', 'starwishx')
        );
    }

    /**
     * Generic per-IP rate-limit guard.
     *
     * Uses ClientIp::resolve() so the operator can opt in to trusted-proxy
     * headers (Cloudflare, ALB, etc.) — on direct-origin deployments it
     * falls back to REMOTE_ADDR. Wait duration is derived from the window
     * via human_time_diff() so wording tracks future tuning.
     *
     * `mapServiceError()` translates the policy's `rate_limited` code into 429.
     */
    private function applyIpRateLimit(
        string $action,
        int $max,
        int $window,
        string $actionLabel
    ): ?WP_Error {
        $key = RateLimitPolicy::key($action, ClientIp::resolve());

        $message = sprintf(
            /* translators: 1: action name, 2: human-readable wait duration */
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

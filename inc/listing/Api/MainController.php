<?php
// file: inc/listing/Api/MainController.php
declare(strict_types=1);

namespace Listing\Api;

use Listing\Services\ListingService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MainController extends AbstractListingController
{
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
                // Scalar params are sanitized at the WP layer.
                // Array params (category, country, seekers) are handled by
                // QueryStringParser — they bypass WP's schema because WP
                // cannot represent repeated keys without [] notation.
                'page'     => ['default' => 1,  'sanitize_callback' => 'absint'],
                'location' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // GET /wp-json/listing/v1/sub-filter/{filter_id}
        // Used for the "Search inside City" functionality
        register_rest_route($this->namespace, '/sub-filter/(?P<filter_id>[a-zA-Z0-9-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchSubFilter'],
            'permission_callback' => [$this, 'publicAccess'],
            'args'                => [
                'q'         => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                // Reject unsupported filter IDs before they reach the service layer
                'filter_id' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => in_array($v, ['location'], true),
                ],
            ],
        ]);
    }

    /**
     * The primary search endpoint.
     * Returns: { items: [], facets: {}, total: 0, total_pages: 0, page: 1 }
     */
    public function search(WP_REST_Request $request): WP_REST_Response
    {
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
        $filterId   = $request->get_param('filter_id');
        $searchTerm = $request->get_param('q');

        $options = $this->service->searchFilterOptions($filterId, $searchTerm);

        if (is_wp_error($options)) {
            return $this->mapServiceError($options);
        }

        return $this->success($options);
    }
}

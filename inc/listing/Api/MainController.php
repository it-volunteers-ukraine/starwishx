<?php
// file: inc/listing/Api/MainController.php
declare(strict_types=1);

namespace Listing\Api;

use Launchpad\Data\Repositories\CountriesRepository;
use Listing\Core\ListingQuery;
use Listing\Services\ListingService;
use Shared\Http\ClientIp;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MainController extends AbstractListingController
{
    private const PAGE_MAX = 1000;

    // Per-IP rate limit — an anonymous endpoint must bucket by IP, not user.
    private const SEARCH_RATE_LIMIT_MAX    = 60;
    private const SEARCH_RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

    private ListingService $service;
    private CountriesRepository $countries;

    public function __construct(ListingService $service, CountriesRepository $countries)
    {
        $this->service   = $service;
        $this->countries = $countries;
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
                // Array params (category, country, beneficiaries) are handled by
                // QueryStringParser — they bypass WP's schema because WP
                // cannot represent repeated keys without [] notation.
                // ListingQuery normalises and caps them.
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
                        ListingService::LOCATION_MAX_LENGTH,
                        __('Location', 'starwishx')
                    ),
                ],
                // search() reads `s` (like `location` and `page`) back from these
                // validated params, so the limits here hold for what is
                // searched - not only for the last of a repeated key.
                's' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => RestArg::stringLength(
                        0,
                        ListingService::SEARCH_MAX_LENGTH,
                        __('Search', 'starwishx')
                    ),
                ],
            ],
        ]);
    }

    /**
     * The primary search endpoint.
     * Returns: { items: [], facets: {}, total: 0, total_pages: 0, page: 1 }
     *
     * `facets` only comes with page 1: Load more keeps the filters, so its
     * pages can't change them, and the client keeps the facets it has.
     */
    public function search(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimited = $this->applySearchRateLimit();
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        // Repeated keys (?category=1&category=2) only survive in the raw
        // query string; the scalars come from WordPress' validated params,
        // so what was validated is what gets searched.
        $raw = $this->getFilterParams($request);
        unset($raw['paged']);
        $query = ListingQuery::fromRaw(
            array_merge($raw, [
                's'        => (string) $request->get_param('s'),
                'location' => (string) $request->get_param('location'),
                'page'     => (int) $request->get_param('page'),
            ]),
            $this->countries
        );

        $results = $this->service->search($query);
        $data    = [
            'items'       => $results['items'],
            'total'       => $results['total'],
            'total_pages' => $results['total_pages'],
            'page'        => $results['page'],
        ];
        if ($query->page === 1) {
            $data['facets'] = $this->service->getFacets($query);
        }

        return $this->success($data);
    }

    /**
     * Per-IP rate limit — /search bucket.
     * A /search call runs a page of results plus, on page 1, one SQL per
     * facet over the shared filtered-ID subquery (TermCountingService), so the
     * ceiling is tuned to 1 req/s sustained.
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
     * Generic per-IP rate-limit guard.
     *
     * Uses ClientIp::resolve() so the operator can opt in to trusted-proxy
     * headers (Cloudflare, ALB, etc.) — on direct-origin deployments it
     * falls back to REMOTE_ADDR. Wait duration is derived from the window
     * via human_time_diff() so wording tracks future tuning.
     *
     * Fixed window: the key carries the window's number, so the count starts
     * over every $window seconds. RateLimitPolicy::hit() pushes the expiry
     * out on every hit - right for failure counters, but on a key of its own
     * it would block a visitor who simply keeps browsing without a
     * $window-long pause.
     *
     * `mapServiceError()` translates the policy's `rate_limited` code into 429.
     */
    private function applyIpRateLimit(
        string $action,
        int $max,
        int $window,
        string $actionLabel
    ): ?WP_Error {
        $key = RateLimitPolicy::key(
            $action,
            ClientIp::resolve(),
            (string) intdiv(time(), $window)
        );

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

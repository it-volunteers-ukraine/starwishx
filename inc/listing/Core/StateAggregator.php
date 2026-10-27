<?php
// file: inc/listing/Core/StateAggregator.php
declare(strict_types=1);

namespace Listing\Core;

use Launchpad\Data\Repositories\CountriesRepository;
use Listing\Services\ListingService;

/**
 * Assembles the initial Interactivity API state for SSR hydration.
 *
 * Raw filter values are accepted as a parameter rather than read from $_GET,
 * keeping this class free of superglobal access and fully unit-testable.
 * Parsing of the raw query string is the caller's responsibility
 * (see QueryStringParser and ListingCore::getState()); normalising it is
 * ListingQuery's, the same as for the REST search.
 */
class StateAggregator
{
    /**
     * Most result pages one page load renders for ?page=N. N multiplies a
     * single query's LIMIT, so it is capped; Load more carries on from there.
     */
    private const MAX_SSR_PAGES = 5;

    private ListingService $service;
    private CountriesRepository $countriesRepository;

    public function __construct(
        ListingService $service,
        ?CountriesRepository $countriesRepository = null
    ) {
        $this->service             = $service;
        $this->countriesRepository = $countriesRepository ?? new CountriesRepository();
    }

    /**
     * Build the complete initial state object for the listing page.
     *
     * @param FilterRegistry $registry   Populated filter registry (reserved for future
     *                                   use, e.g. dynamic facet discovery per filter).
     * @param array          $rawFilters Raw filter values from the current request.
     */
    public function aggregate(FilterRegistry $registry, array $rawFilters = []): array
    {
        $query = ListingQuery::fromRaw($rawFilters, $this->countriesRepository);

        // ?page=N is what Load more leaves in the URL (grid/actions.js).
        // Render pages 1..N in one query, so a reload or a shared link shows
        // the list the visitor had, and Load more continues after it.
        $through = min($query->page, self::MAX_SSR_PAGES);
        $results = $this->service->search(
            $query->withPage(1),
            $through * QueryBuilder::ITEMS_PER_PAGE
        );
        $facets  = $this->service->getFacets($query);

        $totalPages = (int) ceil($results['total'] / QueryBuilder::ITEMS_PER_PAGE);

        return [
            'query'      => $query->withPage(min($through, max(1, $totalPages)))->toArray(),
            'results'    => $results['items'],
            'facets'     => $facets,
            'totalFound' => $results['total'],
            'totalPages' => $totalPages,
            'isLoading'  => false,
            'layout'     => 'grid',
            // Client-only UI state, given here for the server render: a
            // directive bound to a key the server doesn't know evaluates to
            // null, and core drops the attribute (the toggle's aria-expanded).
            'isSidebarOpen' => false,
            'error'         => '',
            'announcement'  => '',
        ];
    }
}

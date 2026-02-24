<?php
// file: inc/listing/Core/StateAggregator.php
declare(strict_types=1);

namespace Listing\Core;

use Listing\Services\ListingService;

/**
 * Assembles the initial Interactivity API state for SSR hydration.
 *
 * Raw filter values are accepted as a parameter rather than read from $_GET,
 * keeping this class free of superglobal access and fully unit-testable.
 * Parsing of the raw query string is the caller's responsibility
 * (see QueryStringParser and ListingCore::getState()).
 */
class StateAggregator
{
    private ListingService $service;

    public function __construct(ListingService $service)
    {
        $this->service = $service;
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
        $filters = $this->sanitizeFilters($rawFilters);

        $results = $this->service->search($filters);
        $facets  = $this->service->getFacets($filters);

        return [
            'query'      => $filters,
            'results'    => $results['items'],
            'facets'     => $facets,
            'totalFound' => $results['total'],
            'totalPages' => $results['total_pages'],
            'isLoading'  => false,
            'layout'     => 'grid',
        ];
    }

    /**
     * Normalise and sanitize raw filter values from the query string.
     *
     * Centralises type coercion so callers and the service layer both receive a
     * consistent, predictable shape regardless of input source.
     *
     * Note on 'page' vs 'paged': WordPress uses 'paged' as its native pagination
     * query var on front-end pages; the REST layer uses 'page'. Both are accepted
     * here and normalised to 'page' so the service layer has a single key.
     */
    private function sanitizeFilters(array $raw): array
    {
        return [
            's'        => sanitize_text_field($raw['s'] ?? ''),
            'category' => array_map('absint', (array) ($raw['category'] ?? [])),
            'country'  => array_map('absint', (array) ($raw['country']  ?? [])),
            'location' => sanitize_text_field($raw['location'] ?? ''),
            'seekers'  => array_map('absint', (array) ($raw['seekers']  ?? [])),
            'page'     => absint($raw['paged'] ?? $raw['page'] ?? 1),
        ];
    }
}

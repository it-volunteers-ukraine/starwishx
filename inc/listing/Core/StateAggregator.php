<?php
// file: inc\listing\Core\StateAggregator.php
declare(strict_types=1);

namespace Listing\Core;

use Listing\Services\ListingService;

class StateAggregator
{
    private ListingService $service;
    private QueryBuilder $queryBuilder;

    public function __construct(ListingService $service, QueryBuilder $queryBuilder)
    {
        $this->service = $service;
        $this->queryBuilder = $queryBuilder;
    }

    public function aggregate(FilterRegistry $registry): array
    {
        // Set the registry on queryBuilder for filter application
        $this->queryBuilder->setFilterRegistry($registry);

        $initialFilters = [
            's'        => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'category' => isset($_GET['category']) ? array_map('absint', (array) $_GET['category']) : [],
            'country'  => isset($_GET['country']) ? array_map('absint', (array) $_GET['country']) : [],
            'location' => isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '',
            'seekers'  => isset($_GET['seekers']) ? array_map('absint', (array) $_GET['seekers']) : [],
            'page'     => isset($_GET['paged']) ? absint($_GET['paged']) : 1,
        ];

        $results = $this->service->search($initialFilters);
        $facets  = $this->service->getFacets($initialFilters);

        return [
            'query'      => $initialFilters,
            'results'    => $results['items'],
            'facets'     => $facets,
            'totalFound' => $results['total'],
            'totalPages' => $results['total_pages'],
            'isLoading'  => false,
            'layout'     => 'grid'
        ];
    }
}

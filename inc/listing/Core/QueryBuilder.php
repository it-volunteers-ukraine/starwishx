<?php
// file: inc\listing\Core\QueryBuilder.php
declare(strict_types=1);

namespace Listing\Core;

use Listing\Enums\Taxonomy;

/**
 * Translates iAPI query state into WP_Query arguments.
 */
class QueryBuilder
{
    public const ITEMS_PER_PAGE = 10;

    private ?FilterRegistry $filterRegistry = null;

    public function __construct(?FilterRegistry $filterRegistry = null)
    {
        $this->filterRegistry = $filterRegistry;
    }

    public function setFilterRegistry(FilterRegistry $registry): void
    {
        $this->filterRegistry = $registry;
    }

    public function build(array $filters, int $perPage = self::ITEMS_PER_PAGE): array
    {
        $page = isset($filters['page']) ? absint($filters['page']) : 1;

        $args = [
            'post_type'      => 'opportunity',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
        ];

        // 1. Global Text Search
        if (!empty($filters['s'])) {
            $args['s'] = sanitize_text_field($filters['s']);
        }

        // 2. Filters from Registry (handles taxonomies + location + future filters)
        if ($this->filterRegistry) {
            foreach ($this->filterRegistry->getAll() as $filter) {
                $filterId = $filter->getId();
                if (method_exists($filter, 'applyQuery') && isset($filters[$filterId]) && !empty($filters[$filterId])) {
                    $args = $filter->applyQuery($args, $filters[$filterId]);
                }
            }
        }

        // foreach ($taxonomies as $stateKey => $taxName) {
        //     if (!empty($filters[$stateKey])) {
        //         $args['tax_query'][] = [
        //             'taxonomy' => $taxName,
        //             'field'    => 'term_id',
        //             'terms'    => (array) $filters[$stateKey],
        //             'operator' => 'IN',
        //         ];
        //     }
        // }

        // 3. Meta Filters (City, Company)
        // if (!empty($filters['city'])) {
        //     $args['meta_query'][] = [
        //         'key'     => 'city',
        //         'value'   => sanitize_text_field($filters['city']),
        //         'compare' => '=',
        //     ];
        // }

        //todo check opportunity_date_ends format in database
        // 4. Date Logic: Hide Expired Opportunities
        // ACF Date Picker stores Ymd (e.g., 20240131)
        // $args['meta_query'][] = [
        //     'key'     => 'opportunity_date_ends',
        //     'value'   => current_time('Ymd'),
        //     'compare' => '>=',
        //     'type'    => 'NUMERIC',
        // ];
        // 4. Date Logic: Hide Expired Opportunities (if end date is set)
        // Shows posts without end date, with empty end date, or with future end date
        // $args['meta_query'][] = [
        //     'relation' => 'OR',
        //     [
        //         'key'     => 'opportunity_date_ends',
        //         'compare' => 'NOT EXISTS',
        //     ],
        //     [
        //         'key'     => 'opportunity_date_ends',
        //         'value'   => '',
        //         'compare' => '=',
        //     ],
        //     [
        //         'key'     => 'opportunity_date_ends',
        //         'value'   => current_time('Ymd'),
        //         'compare' => '>=',
        //         'type'    => 'NUMERIC',
        //     ],
        // ];

        if ($this->filterRegistry) {
            foreach ($this->filterRegistry->getAll() as $filter) {
                $filterId = $filter->getId();
                if (method_exists($filter, 'applyQuery') && isset($filters[$filterId]) && !empty($filters[$filterId])) {
                    $args = $filter->applyQuery($args, $filters[$filterId]);
                }
            }
        }

        return $args;
    }
}

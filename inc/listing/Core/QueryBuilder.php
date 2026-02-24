<?php
// file: inc/listing/Core/QueryBuilder.php
declare(strict_types=1);

namespace Listing\Core;

/**
 * Translates iAPI query state into WP_Query arguments.
 *
 * Receives a FilterRegistry reference at construction time. Because PHP passes
 * objects by reference, the registry may still be empty at that point — filters
 * are registered via the 'listing_register_filters' action later in the 'init'
 * lifecycle. By the time build() is actually called (REST request or SSR
 * hydration), the registry is fully populated. No setter is needed.
 */
class QueryBuilder
{
    public const ITEMS_PER_PAGE = 10;

    private FilterRegistry $filterRegistry;

    public function __construct(FilterRegistry $filterRegistry)
    {
        $this->filterRegistry = $filterRegistry;
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

        // 2. Registered Filters (taxonomies, location, and any future additions).
        // FilterInterface::applyQuery() is enforced by FilterRegistry, so no
        // method_exists() guard is needed here.
        foreach ($this->filterRegistry->getAll() as $filter) {
            $filterId = $filter->getId();

            if (isset($filters[$filterId]) && !empty($filters[$filterId])) {
                $args = $filter->applyQuery($args, $filters[$filterId]);
            }
        }

        return $args;
    }
}

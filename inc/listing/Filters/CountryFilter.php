<?php
// file: inc/listing/Filters/CountryFilter.php
declare(strict_types=1);

namespace Listing\Filters;

/**
 * Country filter, backed by wp_opportunity_countries (junction) and
 * wp_sw_countries (curated dictionary) — not a WP taxonomy.
 *
 * applyQuery pre-resolves selected country_ids to a post__in list
 * because tax_query can't reach a custom table. Facet counts come
 * from ListingService::buildCountryFacets which scopes the join
 * to the current filter context.
 */
class CountryFilter extends AbstractFilter
{
    /**
     * Facet key in iAPI state (state.facets.country) and the registered
     * filter id. Was named TAXONOMY when country was a WP taxonomy —
     * renamed after the wp_opportunity_countries migration since the
     * filter no longer maps to anything in WP's taxonomy system.
     */
    private const FACET_KEY = 'country';

    public function getId(): string
    {
        return 'country';
    }

    public function getLabel(): string
    {
        return __('Country', 'starwishx');
    }

    /**
     * Applies the country selection to the WP_Query via post__in.
     *
     * Country is no longer a WP taxonomy, so tax_query can't reach it.
     * Strategy: pre-resolve the selected country_ids to a post_id set
     * via wp_opportunity_countries, then inject into post__in. Stays
     * inside the standard WP_Query path — no posts_clauses gymnastics.
     *
     * Composes with any existing post__in (from another filter) by
     * intersecting; an empty intersect or zero matches collapses to
     * [0], a sentinel that forces zero rows without raising an error.
     */
    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) {
            return $args;
        }

        $countryIds = array_filter(
            array_map('intval', (array) $value),
            static fn(int $id): bool => $id > 0
        );
        if (empty($countryIds)) {
            return $args;
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($countryIds), '%d'));

        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id
             FROM {$wpdb->prefix}opportunity_countries
             WHERE country_id IN ($placeholders)",
            ...$countryIds
        ));

        $postIds = array_map('intval', $postIds ?: []);

        if (empty($postIds)) {
            $args['post__in'] = [0];
            return $args;
        }

        if (!empty($args['post__in'])) {
            $existing    = array_map('intval', $args['post__in']);
            $intersected = array_values(array_intersect($existing, $postIds));
            $args['post__in'] = !empty($intersected) ? $intersected : [0];
        } else {
            $args['post__in'] = $postIds;
        }

        return $args;
    }

    /**
     * Static facet list: every country that has at least one opportunity.
     *
     * Note: this method is not on the listing's hot path — ListingService::
     * getFacets() builds context-aware counts via buildCountryFacets() and
     * does not call here. Kept for FilterInterface conformance and for any
     * future caller that wants the unfiltered list.
     */
    public function getFacetData(array $current_query_results): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT c.id, c.name AS label, COUNT(DISTINCT oc.post_id) AS count
             FROM {$wpdb->prefix}sw_countries c
             INNER JOIN {$wpdb->prefix}opportunity_countries oc ON oc.country_id = c.id
             GROUP BY c.id
             ORDER BY c.priority ASC, c.name ASC",
            ARRAY_A
        );

        return array_map(static fn(array $r): array => [
            'id'    => (int) $r['id'],
            'label' => (string) $r['label'],
            'count' => (int) $r['count'],
        ], $rows ?: []);
    }

    /**
     * Renders the checkbox list for countries.
     */
    protected function renderContent(): string
    {
        $this->startBuffer();
        $storePath = $this->getStorePath(); // state.query.country
?>
        <div class="filter-list">
            <template
                data-wp-each--item="state.facets.<?php echo self::FACET_KEY; ?>"
                data-wp-key="context.item.id"
                data-wp-context='{"filterField": "country"}'>
                <label class="filter-checkbox" data-wp-context='{"filterField": "country"}'>
                    <input
                        type="checkbox"
                        data-wp-on--change="actions.filters.toggle"
                        data-wp-bind--value="context.item.id"
                        data-wp-bind--checked="state.isFilterChecked"
                        data-field="country">
                    <span class="label-text" data-wp-text="context.item.label"></span>
                    <data class="count" data-wp-bind--value="context.item.count" data-wp-text="context.item.count" aria-hidden="true"></data>
                </label>
            </template>
        </div>
<?php
        return $this->endBuffer();
    }
}

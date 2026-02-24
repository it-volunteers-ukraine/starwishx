<?php
// file: inc/listing/Filters/AbstractTaxonomyFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Listing\Enums\Taxonomy;

/**
 * Base class for all Taxonomy-based filters.
 * Eliminates duplication of query logic and standard checkbox rendering.
 */
abstract class AbstractTaxonomyFilter extends AbstractFilter
{
    /**
     * Child classes must specify which taxonomy they handle via the Enum.
     */
    abstract protected function getTaxonomy(): Taxonomy;

    /**
     * Standard implementation: Adds a tax_query for the selected terms.
     */
    public function applyQuery(array $args, $value): array
    {
        if (empty($value)) {
            return $args;
        }

        $args['tax_query'][] = [
            'taxonomy' => $this->getTaxonomy()->value,
            'field'    => 'term_id',
            'terms'    => (array) $value,
            'operator' => 'IN',
        ];

        return $args;
    }

    /**
     * Standard implementation: Fetches terms for facets.
     * Note: In a future refactor, ListingService should call this method.
     */
    public function getFacetData(array $current_query_results): array
    {
        $terms = get_terms([
            'taxonomy'   => $this->getTaxonomy()->value,
            'hide_empty' => true,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(fn($t) => [
            'id'    => $t->term_id,
            'label' => $t->name,
            'count' => $t->count,
        ], (array) $terms);
    }

    /**
     * Allow children to add classes (e.g. 'filter-list--hierarchical').
     */
    protected function getListClass(): string
    {
        return 'filter-list';
    }

    /**
     * Standard Checkbox Renderer.
     * Uses the Taxonomy Enum to bind dynamically to state.facets.{taxonomy}
     */
    protected function renderContent(): string
    {
        $taxKey = $this->getTaxonomy()->value;
        // e.g. "category" or "country" - used for the Interactivity Context
        $contextField = $this->getId();

        $this->startBuffer();
?>
        <div class="<?php echo esc_attr($this->getListClass()); ?>">
            <template
                data-wp-each--item="state.facets.<?php echo esc_attr($taxKey); ?>"
                data-wp-key="context.item.id"
                data-wp-context='{"filterField": "<?php echo esc_attr($contextField); ?>"}'>

                <label class="filter-checkbox">
                    <input
                        type="checkbox"
                        data-wp-on--change="actions.filters.toggle"
                        data-wp-bind--value="context.item.id"
                        data-wp-bind--checked="state.isFilterChecked"
                        data-field="<?php echo esc_attr($contextField); ?>">

                    <span class="label-text" data-wp-text="context.item.label"></span>
                    <data class="count" data-wp-bind--value="context.item.count" data-wp-text="context.item.count" aria-hidden="true"></data>
                </label>

            </template>
        </div>
<?php
        return $this->endBuffer();
    }
}

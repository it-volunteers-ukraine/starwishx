<?php
// file: inc/listing/Filters/CategoryFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Listing\Enums\Taxonomy;
use Listing\Services\TermCountingService;

class CategoryFilter extends AbstractTaxonomyFilter
{
    private ?TermCountingService $termCounter = null;

    public function setTermCounter(TermCountingService $termCounter): void
    {
        $this->termCounter = $termCounter;
    }

    protected function getTaxonomy(): Taxonomy
    {
        return Taxonomy::CATEGORY;
    }

    public function getId(): string
    {
        return 'category';
    }

    public function getLabel(): string
    {
        return __('Category', 'starwishx');
    }

    /**
     * OVERRIDE: Get faceted tree with dynamic counts.
     * Shows only categories with posts matching current filters.
     */
    public function getFacetData(array $currentFilters): array
    {
        if (!$this->termCounter) {
            throw new \RuntimeException('TermCountingService not injected into CategoryFilter');
        }

        $contextFilters = $currentFilters;
        unset($contextFilters['category']);

        return $this->termCounter->buildFacetedTree($this->getTaxonomy(), $contextFilters);
    }

    /**
     * OVERRIDE: Render nested HTML with "Progressive Disclosure" logic.
     */
    protected function renderContent(): string
    {
        $taxKey = $this->getTaxonomy()->value;

        $this->startBuffer();
?>
        <div class="filter-list filter-list--hierarchical">
            <template
                data-wp-each--item="state.facets.<?php echo esc_attr($taxKey); ?>"
                data-wp-key="context.item.id"
                data-wp-context='{"filterField": "category"}'>
                <div class="filter-group-item">
                    <label class="filter-checkbox parent-checkbox">
                        <!-- 
                                data-wp-watch: Handles the visual "Dash"
                                toggleParent: Handles Select All / None
                             -->
                        <!-- data-wp-effect="callbacks.setIndeterminate" -->
                        <!-- data-wp-watch="callbacks.setIndeterminate" -->
                        <input
                            type="checkbox"
                            data-wp-class--is-indeterminate="callbacks.isIndeterminate"
                            data-wp-on--change="actions.filters.toggleParent"
                            data-wp-bind--value="context.item.id"
                            data-wp-bind--checked="state.isFilterChecked"
                            data-field="category">
                        <span class="label-text" data-wp-text="context.item.label"></span>
                        <data class="count" data-wp-text="context.item.count"></data>
                    </label>
                    <!-- Children Container -->
                    <div
                        class="filter-sub-level"
                        data-wp-bind--hidden="!callbacks.isParentExpanded">
                        <template
                            data-wp-each--item="context.item.children"
                            data-wp-key="context.item.id"
                            data-wp-context='{"filterField": "category"}'>
                            <label class="filter-checkbox child-checkbox">
                                <input
                                    type="checkbox"
                                    data-wp-on--change="actions.filters.toggleChild"
                                    data-wp-bind--value="context.item.id"
                                    data-wp-bind--checked="state.isFilterChecked"
                                    data-field="category">
                                <span>
                                    <span class="label-text" data-wp-text="context.item.label"></span>
                                    <data class="count" data-wp-text="context.item.count"></data>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>
<?php
        return $this->endBuffer();
    }
}

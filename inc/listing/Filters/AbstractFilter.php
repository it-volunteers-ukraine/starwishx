<?php
// file: inc/listing/Filters/AbstractFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Listing\Contracts\FilterInterface;
use Shared\Core\Traits\BufferedRenderTrait;

/**
 * Base class for Sidebar Filters.
 * Handles the "Collapsible Details" wrapper logic.
 */
abstract class AbstractFilter implements FilterInterface
{
    use BufferedRenderTrait;

    /**
     * Child classes implement this to render the specific 
     * checkboxes/inputs for that filter.
     */
    abstract protected function renderContent(): string;

    /**
     * Implementation of RenderableInterface.
     * Wraps the content in a summary/details block with iAPI directives.
     */
    public function render(): string
    {
        $id = $this->getId();
        $label = $this->getLabel();

        $this->startBuffer();
?>
        <details
            class="listing-filter-group"
            id="filter-group-<?php echo esc_attr($id); ?>"
            data-wp-interactive="listing"
            data-wp-context='{ "filterId": "<?php echo esc_attr($id); ?>" }'
            data-wp-class--has-selection="callbacks.hasActiveFilter"
            open>

            <summary class="listing-filter-group__summary">
                <span class="listing-filter-group__title"><?php echo esc_html($label); ?>
                    <!-- The Count Badge -->
                    <span
                        class="filter-count-badge"
                        data-wp-text="callbacks.getActiveFilterCount">
                    </span></span>
                <svg class="icon-chevron">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow-down"></use>
                </svg>
            </summary>

            <div class="listing-filter-group__content">
                <?php echo $this->renderContent(); ?>
            </div>
        </details>
<?php
        return $this->endBuffer();
    }

    /**
     * Default implementation: most filters don't have dependencies.
     */
    public function dependsOn(): ?string
    {
        return null;
    }

    /**
     * Standard path for Interactivity API bindings for this specific filter.
     */
    protected function getStorePath(string $subKey = ''): string
    {
        $path = 'state.query.' . $this->getId();
        return $subKey ? $path . '.' . $subKey : $path;
    }
}

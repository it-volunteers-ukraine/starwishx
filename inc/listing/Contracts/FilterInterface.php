<?php
// file: inc/listing/Contracts/FilterInterface.php
declare(strict_types=1);

namespace Listing\Contracts;

use Shared\Contracts\RenderableInterface;

interface FilterInterface extends RenderableInterface
{
    public function getId(): string;
    public function getLabel(): string;

    /**
     * Modifies the WP_Query args based on user selection.
     */
    public function applyQuery(array $args, $value): array;

    /**
     * Provides the options and facet counts for the UI.
     */
    public function getFacetData(array $current_query_results): array;
}

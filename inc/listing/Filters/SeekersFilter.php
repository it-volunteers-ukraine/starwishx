<?php
// file: inc/listing/Filters/SeekersFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Listing\Enums\Taxonomy;

class SeekersFilter extends AbstractTaxonomyFilter
{
    protected function getTaxonomy(): Taxonomy
    {
        return Taxonomy::SEEKERS;
    }

    public function getId(): string
    {
        return 'seekers';
    }

    public function getLabel(): string
    {
        return __('Recievers', 'starwishx');
    }
}

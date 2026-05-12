<?php
// file: inc/listing/Filters/BeneficiariesFilter.php
declare(strict_types=1);

namespace Listing\Filters;

use Listing\Enums\Taxonomy;

class BeneficiariesFilter extends AbstractTaxonomyFilter
{
    protected function getTaxonomy(): Taxonomy
    {
        return Taxonomy::BENEFICIARIES;
    }

    public function getId(): string
    {
        return 'beneficiaries';
    }

    public function getLabel(): string
    {
        return __('Beneficiaries', 'starwishx');
    }
}

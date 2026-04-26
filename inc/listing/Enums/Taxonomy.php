<?php
// file: inc/listing/Enums/Taxonomy.php
declare(strict_types=1);

namespace Listing\Enums;

/**
 * Enumeration of WP taxonomies used by the listing's facet pipeline.
 *
 * Country was removed when the `country` taxonomy was retired in favor
 * of wp_opportunity_countries (typed junction) + wp_sw_countries
 * (curated dictionary). The listing's country filter lives in
 * CountryFilter and reads from those tables directly — country is
 * intentionally absent from this enum so iterating Taxonomy::cases()
 * only visits actual WP taxonomies.
 */
enum Taxonomy: string
{
    case CATEGORY = 'category-oportunities';
    case SEEKERS  = 'category-seekers';

    /**
     * Returns the key used in the URL/State for this taxonomy.
     * e.g., ?category=123 maps to 'category-oportunities'
     */
    public function getQueryVar(): string
    {
        return match ($this) {
            self::CATEGORY => 'category',
            self::SEEKERS  => 'seekers',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CATEGORY => __('Category', 'starwishx'),
            self::SEEKERS  => __('Seekers', 'starwishx'),
        };
    }
}

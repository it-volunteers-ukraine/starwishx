<?php
// file: inc/listing/Enums/Taxonomy.php
declare(strict_types=1);

namespace Listing\Enums;

enum Taxonomy: string
{
    case CATEGORY = 'category-oportunities';
    case COUNTRY  = 'country';
    case SEEKERS  = 'category-seekers';

    /**
     * Returns the key used in the URL/State for this taxonomy.
     * e.g., ?category=123 maps to 'category-oportunities'
     */
    public function getQueryVar(): string
    {
        return match ($this) {
            self::CATEGORY => 'category',
            self::COUNTRY  => 'country',
            self::SEEKERS  => 'seekers',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CATEGORY => __('Category', 'starwishx'),
            self::COUNTRY  => __('Country', 'starwishx'),
            self::SEEKERS  => __('Seekers', 'starwishx'),
        };
    }
}

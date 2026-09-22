<?php

declare(strict_types=1);

/**
 * Global helper function to access Launchpad instance
 *
 * @return \Listing\Core\ListingCore
 */
function listing(): \Listing\Core\ListingCore
{
   return \Listing\Core\ListingCore::instance();
}

if (! function_exists('sw_listing_category_url')) {
    /**
     * Public URL of an opportunity category listing.
     *
     * One place for the URL scheme the Listing module owns, so templates and
     * blocks stop hard-coding home_url('opportunities/…'). Mirrors buildUrl()
     * in inc/listing/Assets/utils.js: with LISTING_PRETTY_CATEGORY_URLS the
     * category lives at {archive}/{slug}/ (see ListingCore::registerCategoryRewrites),
     * otherwise it is the archive filtered by ?category={slug}.
     */
    function sw_listing_category_url(string $slug): string
    {
        $archive = get_post_type_archive_link('opportunity') ?: home_url('/opportunities/');

        if (defined('LISTING_PRETTY_CATEGORY_URLS') && LISTING_PRETTY_CATEGORY_URLS) {
            return trailingslashit($archive) . rawurlencode($slug) . '/';
        }

        return add_query_arg('category', $slug, $archive);
    }
}

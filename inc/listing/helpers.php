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

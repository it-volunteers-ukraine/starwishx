<?php

declare(strict_types=1);

/**
 * Global helper function to access Tour instance
 *
 * @return \Tour\Core\TourCore
 */
function tour(): \Tour\Core\TourCore
{
    return \Tour\Core\TourCore::instance();
}

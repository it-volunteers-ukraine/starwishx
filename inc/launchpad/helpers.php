<?php

declare(strict_types=1);

/**
 * Global helper function to access Launchpad instance
 *
 * @return \Launchpad\Core\LaunchpadCore
 */
function launchpad(): \Launchpad\Core\LaunchpadCore
{
   return \Launchpad\Core\LaunchpadCore::instance();
}

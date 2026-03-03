<?php

declare(strict_types=1);

/**
 * Global helper function to access Projects instance
 *
 * @return \Projects\Core\ProjectsCore
 */
function projects(): \Projects\Core\ProjectsCore
{
   return \Projects\Core\ProjectsCore::instance();
}

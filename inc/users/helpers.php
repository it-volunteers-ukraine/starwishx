<?php

declare(strict_types=1);

/**
 * Global helper function to access Users instance.
 *
 * @return \Users\Core\UsersCore
 */
function users(): \Users\Core\UsersCore
{
    return \Users\Core\UsersCore::instance();
}

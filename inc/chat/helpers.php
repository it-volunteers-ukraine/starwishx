<?php

declare(strict_types=1);

/**
 * Global helper function to access Chat instance
 *
 * @return \Chat\Core\ChatCore
 */
function chat(): \Chat\Core\ChatCore
{
    return \Chat\Core\ChatCore::instance();
}

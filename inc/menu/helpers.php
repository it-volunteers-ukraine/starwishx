<?php

declare(strict_types=1);

/**
 * Global helper function to access Menu instance
 *
 * @return \Menu\Core\MenuCore
 */
function menu(): \Menu\Core\MenuCore
{
    return \Menu\Core\MenuCore::instance();
}

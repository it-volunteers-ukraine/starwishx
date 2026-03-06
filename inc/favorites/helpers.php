<?php

declare(strict_types=1);

/**
 * Global helper function to access Favorites instance
 *
 * @return \Favorites\Core\FavoritesCore
 */
function favorites(): \Favorites\Core\FavoritesCore
{
    return \Favorites\Core\FavoritesCore::instance();
}

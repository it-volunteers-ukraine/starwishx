<?php

declare(strict_types=1);

/**
 * Global helper function to access Notifications instance
 *
 * @return \Notifications\Core\NotificationsCore
 */
function notifications(): \Notifications\Core\NotificationsCore
{
    return \Notifications\Core\NotificationsCore::instance();
}

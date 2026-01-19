<?php

declare(strict_types=1);

/**
 * Global helper function to access Gateway instance
 *
 * @return \Gateway\Core\GatewayCore
 */
function gateway(): \Gateway\Core\GatewayCore
{
    return \Gateway\Core\GatewayCore::instance();
}
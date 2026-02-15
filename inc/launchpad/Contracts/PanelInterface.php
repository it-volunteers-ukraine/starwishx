<?php
// File: inc/launchpad/Contracts/PanelInterface.php

declare(strict_types=1);

namespace Launchpad\Contracts;

use Shared\Contracts\StateProviderInterface;

/**
 * PanelInterface includes everything in StateProviderInterface 
 * PLUS the icon and render methods.
 */
interface PanelInterface extends StateProviderInterface
{
    /**
     * Icon Id from SVG sprite 
     */
    public function getIcon(): string;

    /**
     * Render panel HTML with data-wp-* directives.
     * @return string HTML content
     */
    public function render(): string;
}

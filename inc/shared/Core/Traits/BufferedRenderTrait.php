<?php

declare(strict_types=1);

namespace Shared\Core\Traits;

/**
 * Trait for buffered rendering of HTML content.
 * Uses output buffering to capture rendered content.
 */
trait BufferedRenderTrait
{
    /**
     * Start output buffering.
     */
    protected function startBuffer(): void
    {
        ob_start();
    }

    /**
     * End output buffering and return captured content.
     */
    protected function endBuffer(): string
    {
        return ob_get_clean();
    }
}

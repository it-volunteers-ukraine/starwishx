<?php

declare(strict_types=1);

namespace Shared\Contracts;

/**
 * Interface for objects that can render themselves as HTML.
 */
interface RenderableInterface
{
    /**
     * Render the object as HTML string.
     */
    public function render(): string;
}

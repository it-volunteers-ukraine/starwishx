<?php
// file: inc/listing/Core/FilterRegistry.php
declare(strict_types=1);

namespace Listing\Core;

use Shared\Core\AbstractRegistry;
use Listing\Contracts\FilterInterface;

class FilterRegistry extends AbstractRegistry
{
    public function register(string $id, object $instance, int $priority = 10): void
    {
        if (!$instance instanceof FilterInterface) {
            throw new \InvalidArgumentException(
                sprintf('Filter must implement FilterInterface, got %s', get_class($instance))
            );
        }
        parent::register($id, $instance, $priority);
    }

    /** @return FilterInterface[] */
    public function getAll(): array
    {
        return parent::getAll();
    }
}

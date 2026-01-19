<?php
// File: inc/launchpad/Core/PanelRegistry.php

declare(strict_types=1);

namespace Launchpad\Core;

use Shared\Core\AbstractRegistry;
use Launchpad\Contracts\PanelInterface;

class PanelRegistry extends AbstractRegistry
{
    public function register(string $id, object $panel, int $priority = 10): void
    {
        if (!$panel instanceof PanelInterface) {
            throw new \InvalidArgumentException('Panel must implement PanelInterface');
        }
        parent::register($id, $panel, $priority);
    }

    /**
     * @return array<string, PanelInterface>
     */
    public function getAll(): array
    {
        return parent::getAll();
    }

    public function get(string $id): ?PanelInterface
    {
        return parent::get($id);
    }

    public function has(string $id): bool
    {
        return parent::has($id);
    }

    public function getIds(): array
    {
        return parent::getIds();
    }
}

<?php

declare(strict_types=1);

namespace Shared\Core;

use Shared\Contracts\RegistryInterface;

/**
 * Base class for registries that manage collections of items with priorities.
 */
abstract class AbstractRegistry implements RegistryInterface
{
    /** @var array<string, array{instance: object, priority: int}> */
    private array $items = [];

    public function register(string $id, object $instance, int $priority = 10): void
    {
        $this->items[$id] = [
            'instance' => $instance,
            'priority' => $priority,
        ];
    }

    public function get(string $id): ?object
    {
        return $this->items[$id]['instance'] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    /**
     * @return array<string, object>
     */
    public function getAll(): array
    {
        uasort($this->items, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return array_map(fn($item) => $item['instance'], $this->items);
    }

    /**
     * Get all item IDs.
     *
     * @return array<string>
     */
    public function getIds(): array
    {
        return array_keys($this->items);
    }
}

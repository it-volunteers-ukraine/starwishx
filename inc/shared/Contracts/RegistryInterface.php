<?php

declare(strict_types=1);

namespace Shared\Contracts;

/**
 * Interface for registry classes that manage collections of items.
 */
interface RegistryInterface
{
    /**
     * Register an item with a priority.
     */
    public function register(string $id, object $instance, int $priority = 10): void;

    /**
     * Get an item by ID.
     */
    public function get(string $id): ?object;

    /**
     * Check if an item exists.
     */
    public function has(string $id): bool;

    /**
     * Get all registered items, sorted by priority.
     *
     * @return array<string, object>
     */
    public function getAll(): array;
}

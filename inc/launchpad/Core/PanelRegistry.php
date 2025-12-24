<?php

declare(strict_types=1);

namespace Launchpad\Core;

use Launchpad\Contracts\PanelInterface;

class PanelRegistry
{
    /** @var array<string, array{instance: PanelInterface, priority: int}> */
    private array $panels = [];

    public function register(string $id, PanelInterface $panel, int $priority = 10): void
    {
        $this->panels[$id] = [
            'instance' => $panel,
            'priority' => $priority,
        ];
    }

    /**
     * @return array<string, PanelInterface>
     */
    public function getAll(): array
    {
        uasort($this->panels, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return array_map(fn($item) => $item['instance'], $this->panels);
    }

    public function get(string $id): ?PanelInterface
    {
        return $this->panels[$id]['instance'] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->panels[$id]);
    }

    public function getIds(): array
    {
        return array_keys($this->panels);
    }
}

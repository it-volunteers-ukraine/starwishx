<?php

declare(strict_types=1);

namespace Shared\Contracts;

/**
 * Interface for aggregating state from multiple state providers.
 */
interface StateAggregatorInterface
{
    /**
     * Aggregate initial state from multiple state providers.
     *
     * @param array<string, StateProviderInterface> $providers Array of state providers keyed by ID
     * @param string $activeProviderId The currently active provider ID
     * @param int|null $userId Current user ID, null for unauthenticated
     * @return array Aggregated state ready for wp_interactivity_state()
     */
    public function aggregate(array $providers, string $activeProviderId, ?int $userId = null): array;
}

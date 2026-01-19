<?php
// File: inc/shared/Contracts/StateProviderInterface.php
declare(strict_types=1);

namespace Shared\Contracts;

/**
 * Interface for objects that provide initial state for Interactivity API.
 */
interface StateProviderInterface
{
    /**
     * Get the unique identifier for this state provider.
     */
    public function getId(): string;

    /**
     * Get the human-readable label for this state provider.
     */
    public function getLabel(): string;

    /**
     * Get the initial state array for SSR hydration.
     * 
     * Auto-injected keys by StateAggregator:
     * - `_loaded`: bool
     * - `isLoading`: bool
     * - `isSaving`: bool
     * - `error`: ?string
     * 
     * @param int|null $userId Current user ID, null for unauthenticated
     * @return array Initial state data
     */
    public function getInitialState(?int $userId = null): array;

    /**
     * The specific key name used in the Interactivity API state.
     * Example: 'isProfileActive'
     */
    public function getStateKey(): string;
}

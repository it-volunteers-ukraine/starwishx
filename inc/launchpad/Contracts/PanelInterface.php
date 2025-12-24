<?php

declare(strict_types=1);

namespace Launchpad\Contracts;

interface PanelInterface
{
    /**
     * Unique panel identifier (lowercase, alphanumeric, dashes).
     */
    public function getId(): string;

    /**
     * Display label for sidebar navigation.
     */
    public function getLabel(): string;

    /**
     * Dashicon name without 'dashicons-' prefix.
     */
    public function getIcon(): string;

    /**
     * Initial state data for this panel.
     *
     * Auto-injected keys by StateAggregator:
     * - `_loaded`: bool
     * - `isLoading`: bool
     * - `isSaving`: bool
     * - `error`: ?string
     */
    public function getInitialState(int $userId): array;

    /**
     * Render panel HTML with data-wp-* directives.
     * @return string HTML content
     */
    public function render(): string;
}

<?php
// File: inc/tour/Contracts/ScenarioInterface.php

declare(strict_types=1);

namespace Tour\Contracts;

interface ScenarioInterface
{
    public function getId(): string;

    public function getLabel(): string;

    /**
     * Context where this tour applies (e.g., 'launchpad', 'listing').
     */
    public function getContext(): string;

    /**
     * Return step definitions with i18n text.
     *
     * Each step: [
     *   'id'              => string,
     *   'title'           => string (translated),
     *   'text'            => string (translated),
     *   'attachTo'        => ['element' => CSS selector, 'on' => position] | null,
     *   'panel'           => string|null (panel to switch to before showing),
     *   'view'            => string|null (view to switch to within panel),
     *   'condition'       => string|null (state path to evaluate),
     *   'conditionNegate' => bool,
     * ]
     *
     * @return array<int, array>
     */
    public function getSteps(int $userId): array;

    /**
     * Whether this tour is available for the given user.
     */
    public function isAvailable(int $userId): bool;
}

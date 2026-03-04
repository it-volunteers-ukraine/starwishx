<?php

/**
 * Projects — State Aggregator
 *
 * Builds the Interactivity API state for a single project page.
 * File: inc/projects/Core/StateAggregator.php
 */

declare(strict_types=1);

namespace Projects\Core;

use Projects\Services\ProjectsService;

class StateAggregator
{
    private ProjectsService $service;

    public function __construct(ProjectsService $service)
    {
        $this->service = $service;
    }

    /**
     * Aggregate state for SSR hydration.
     *
     * @param int $postId
     * @param int $userId Current user ID (0 = guest)
     * @return array
     */
    public function aggregate(int $postId, int $userId = 0): array
    {
        $opportunities = $this->service->getRelatedOpportunities($postId, $userId);
        $ngos          = $this->service->getRelatedNgos($postId, $userId);

        return [
            'activeTab'      => 'about',
            'isUserLoggedIn' => $userId > 0,
            'opportunities'  => $opportunities,
            'ngos'           => $ngos,
            'counts'         => [
                'opportunities' => count($opportunities),
                'ngos'          => count($ngos),
            ],
        ];
    }
}

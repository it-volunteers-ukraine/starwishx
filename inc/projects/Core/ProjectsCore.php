<?php

/**
 * Projects - Core Singleton
 * Orchestrates the single project page functionality.
 * 
 * Version: 0.7.5
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/projects/Core/ProjectsCore.php
 */

declare(strict_types=1);

namespace Projects\Core;

use Projects\Services\ProjectsService;
use Shared\Build\AssetFile;

final class ProjectsCore
{
    private static ?self $instance = null;

    private ProjectsService $service;
    private StateAggregator $stateAggregator;

    /**
     * Reset singleton (for testing only).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get singleton instance with optional DI for testing.
     */
    public static function instance(
        ?ProjectsService $service = null,
        ?StateAggregator $stateAggregator = null
    ): self {
        if (!self::$instance) {
            // Reuse repository from the shared Favorites module
            $favoritesRepo   = \favorites()->repository();
            $service         = $service         ?? new ProjectsService($favoritesRepo);
            $stateAggregator = $stateAggregator ?? new StateAggregator($service);

            self::$instance = new self($service, $stateAggregator);
        }

        return self::$instance;
    }

    private function __construct(
        ProjectsService $service,
        StateAggregator $stateAggregator
    ) {
        $this->service         = $service;
        $this->stateAggregator = $stateAggregator;

        $this->bootstrap();
    }

    /**
     * Register WordPress hooks.
     */
    private function bootstrap(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue Interactivity API assets only on single project pages.
     */
    public function enqueueAssets(): void
    {
        if (!is_singular('project')) {
            return;
        }

        $asset = AssetFile::read('assets/js/projects-store.module.js', ['@wordpress/interactivity']);
        $deps  = $asset['dependencies'];

        if (is_user_logged_in()) {
            // Favorites store is enqueued by the independent FavoritesCore module
            $deps[] = '@starwishx/favorites';
        }

        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/projects',
                $asset['url'],
                $deps,
                $asset['version']
            );
            wp_enqueue_script_module('@starwishx/projects');
        }
    }

    /**
     * Get aggregated state for SSR hydration.
     *
     * @param int $postId
     * @return array
     */
    public function getState(int $postId): array
    {
        $userId = get_current_user_id();
        return $this->stateAggregator->aggregate($postId, $userId);
    }

    /**
     * Access the projects service.
     */
    public function service(): ProjectsService
    {
        return $this->service;
    }
}

<?php

/**
 * Tour module
 * Guided onboarding tours for Launchpad using shepherd.js.
 *
 * Version: 0.1.0
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/tour/Core/TourCore.php
 */

declare(strict_types=1);

namespace Tour\Core;

use Tour\Registry\ScenarioRegistry;
use Tour\Api\TourController;

final class TourCore
{
    private static ?self $instance = null;
    private ScenarioRegistry $registry;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
        $this->registry = new ScenarioRegistry();
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Fire scenario registration (same init priority as launchpad panels)
        add_action('init', fn() => do_action('tour_register_scenarios', $this->registry), 22);

        // Register built-in scenarios
        add_action('tour_register_scenarios', [$this, 'registerDefaultScenarios'], 5);
    }

    public function registerDefaultScenarios(ScenarioRegistry $registry): void
    {
        $registry->register('launchpad-intro', new \Tour\Scenarios\LaunchpadIntroScenario(), 10);
        $registry->register('opportunity-form', new \Tour\Scenarios\OpportunityFormScenario(), 20);
    }

    public function registerRestRoutes(): void
    {
        $controller = new TourController();
        $controller->registerRoutes();
    }

    public function enqueueAssets(): void
    {
        if (! is_page('launchpad') || ! is_user_logged_in()) {
            return;
        }
        // Shepherd.js CSS from CDN
        wp_enqueue_style(
            'shepherd',
            'https://cdn.jsdelivr.net/npm/shepherd.js@15.2.2/dist/css/shepherd.css',
            [],
            '15.2.2'
        );

        // Shepherd.js ESM from CDN — registered as WP script module
        wp_enqueue_script_module(
            'shepherd',
            'https://cdn.jsdelivr.net/npm/shepherd.js@15.2.2/dist/js/shepherd.mjs',
            array(),
            '15.2.2'
        );

        // Tour store module
        if (function_exists('wp_register_script_module')) {
            wp_register_script_module(
                '@starwishx/tour',
                get_template_directory_uri() . '/assets/js/tour-store.module.js',
                ['@wordpress/interactivity', 'shepherd'],
                '1.0.0'
            );
            wp_enqueue_script_module('@starwishx/tour');
        }

        $this->hydrateState();
    }

    /**
     * Whether the given tour ID corresponds to a registered scenario.
     * Used by TourController to allowlist tourId at the REST boundary so
     * arbitrary strings can't accumulate in user_meta.
     */
    public function hasScenario(string $id): bool
    {
        return $this->registry->has($id);
    }

    /**
     * Build scenario data for a user (role-aware, i18n in PHP).
     * Used by both SSR hydration and the REST scenarios endpoint.
     */
    public function buildScenarioData(int $userId): array
    {
        $completedTours = get_user_meta($userId, 'sw_completed_tours', true) ?: [];
        $dismissedTours = get_user_meta($userId, 'sw_dismissed_tours', true) ?: [];

        if (!is_array($completedTours)) {
            $completedTours = [];
        }
        if (!is_array($dismissedTours)) {
            $dismissedTours = [];
        }

        $scenarios = [];
        foreach ($this->registry->getAll() as $id => $scenario) {
            if (!$scenario->isAvailable($userId)) {
                continue;
            }
            $scenarios[$id] = [
                'id'        => $id,
                'label'     => $scenario->getLabel(),
                'context'   => $scenario->getContext(),
                'steps'     => $scenario->getSteps($userId),
                'completed' => in_array($id, $completedTours, true),
                'dismissed' => in_array($id, $dismissedTours, true),
            ];
        }

        return $scenarios;
    }

    private function hydrateState(): void
    {
        $userId = get_current_user_id();
        $scenarios = $this->buildScenarioData($userId);

        wp_interactivity_state('tour', [
            'config' => [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('tour/v1/'),
                'messages' => [
                    'next'       => __('Next', 'starwishx'),
                    'prev'       => __('Back', 'starwishx'),
                    'finish'     => __('Done', 'starwishx'),
                    'skip'       => __('Skip tour', 'starwishx'),
                    'startTour'  => __('Take a Tour', 'starwishx'),
                    'retakeTour' => __('Retake Tour', 'starwishx'),
                ],
            ],
            'scenarios'      => $scenarios,
            'activeTour'     => null,
            'activeStepIndex' => 0,
            'isRunning'      => false,
            'showTrigger'    => true,
        ]);
    }

    public function registry(): ScenarioRegistry
    {
        return $this->registry;
    }
}

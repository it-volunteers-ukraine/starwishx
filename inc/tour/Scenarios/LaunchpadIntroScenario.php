<?php
// File: inc/tour/Scenarios/LaunchpadIntroScenario.php

declare(strict_types=1);

namespace Tour\Scenarios;

use Tour\Contracts\ScenarioInterface;

/**
 * "Welcome to StarWishX" tour for Subscribers (newcomers).
 *
 * Guides new users through the dashboard, explains available features,
 * and directs them to complete their profile to unlock contributor role.
 *
 * Available only for subscribers — contributors get OpportunityFormScenario.
 */
class LaunchpadIntroScenario implements ScenarioInterface
{
    public function getId(): string
    {
        return 'launchpad-intro';
    }

    public function getLabel(): string
    {
        return __('Welcome Tour', 'starwishx');
    }

    public function getContext(): string
    {
        return 'launchpad';
    }

    public function isAvailable(int $userId): bool
    {
        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        // Subscriber-only — contributors have their own tour
        return in_array('subscriber', $user->roles, true);
    }

    public function getSteps(int $userId): array
    {
        return [
            // Step 1 — Centered welcome modal
            [
                'id'       => 'welcome',
                'title'    => __('Welcome to StarWishX!', 'starwishx'),
                'text'     => __('StarWishX is a platform where people and organizations around the world share free humanitarian opportunities to support Ukrainian people. Let us show you around — it will only take a moment.', 'starwishx'),
                'attachTo' => null,
                'panel'    => null,
                'view'     => null,
            ],
            // Step 2 — Sidebar navigation
            [
                'id'       => 'sidebar',
                'title'    => __('Your Launchpad', 'starwishx'),
                'text'     => __('This is your personal space on StarWishX. Use these tabs to navigate between sections of your dashboard.', 'starwishx'),
                'attachTo' => ['element' => '.launchpad-tabs', 'on' => 'right'],
                'panel'    => null,
                'view'     => null,
            ],
            // Step 3 — Opportunities panel (locked state for subscribers)
            [
                'id'       => 'opportunities',
                'title'    => __('Opportunities', 'starwishx'),
                'text'     => __('Browse free opportunities published by organizations and individuals. You can comment, rate, and save the ones that interest you. Want to share an opportunity yourself? We just need a bit more information about you first.', 'starwishx'),
                'attachTo' => ['element' => '.launchpad-panel--onboarding', 'on' => 'bottom'],
                'panel'    => 'opportunities',
                'view'     => null,
            ],
            // Step 4 — Favorites tab
            [
                'id'       => 'favorites',
                'title'    => __('Favorites', 'starwishx'),
                'text'     => __('Found something helpful? Save opportunities to your Favorites so you can find them quickly later.', 'starwishx'),
                'attachTo' => ['element' => '.launchpad-tab[data-panel-id="favorites"]', 'on' => 'right'],
                'panel'    => null,
                'view'     => null,
            ],
            // Step 5 — Notifications tab
            [
                'id'       => 'notifications',
                'title'    => __('Notifications', 'starwishx'),
                'text'     => __('Your activity feed — you\'ll get updates here when someone responds to your comments.', 'starwishx'),
                'attachTo' => ['element' => '.launchpad-tab[data-panel-id="chat"]', 'on' => 'right'],
                'panel'    => null,
                'view'     => null,
            ],
            // Step 6 — Profile card overview (switches to profile panel)
            [
                'id'       => 'profile-overview',
                'title'    => __('Your Profile', 'starwishx'),
                'text'     => __('Here is your profile overview. To unlock all features — including the ability to share opportunities — you\'ll need to complete a few details.', 'starwishx'),
                'attachTo' => ['element' => '.profile-card', 'on' => 'bottom'],
                'panel'    => 'profile',
                'view'     => null,
            ],
            // Step 7 — Required fields (switches to edit view, highlights Name + Phone)
            [
                'id'       => 'required-fields',
                'title'    => __('Name & Contact', 'starwishx'),
                'text'     => __('We need your name and phone number — the fields marked with an asterisk. This information stays private with the StarWishX team for verification purposes. Other visitors only see your chosen display name.', 'starwishx'),
                'attachTo'        => ['element' => '#lp-first-name', 'on' => 'bottom'],
                'extraHighlights' => ['.form-field--phone'],
                'panel'           => 'profile',
                'view'            => 'profile',
            ],
            // Step 8 — Completion
            [
                'id'       => 'complete',
                'title'    => __('You\'re Almost There!', 'starwishx'),
                'text'     => __('Once you fill in your name and phone, you\'ll be able to share free opportunities with people who need them. Start by completing your profile — and you can retake this tour anytime.', 'starwishx'),
                'attachTo' => null,
                'panel'    => null,
                'view'     => null,
            ],
        ];
    }
}

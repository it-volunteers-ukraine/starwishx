<?php
// File: inc/tour/Scenarios/OpportunityFormScenario.php

declare(strict_types=1);

namespace Tour\Scenarios;

use Tour\Contracts\ScenarioInterface;

/**
 * "How to Create an Opportunity" tour for Contributors.
 *
 * Walks contributors through the opportunity creation form,
 * explaining each required field and the submission process.
 *
 * Available only for contributors (profile complete, not locked).
 */
class OpportunityFormScenario implements ScenarioInterface
{
    public function getId(): string
    {
        return 'opportunity-form';
    }

    public function getLabel(): string
    {
        return __('Opportunity Form Guide', 'starwishx');
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

        return in_array('contributor', $user->roles, true);
    }

    public function getSteps(int $userId): array
    {
        return [
            // Step 1 — Add button (on list view)
            [
                'id'       => 'add-button',
                'title'    => __('Share an Opportunity', 'starwishx'),
                'text'     => __('Ready to help? Click this button to create a new opportunity and share it with people who may benefit.', 'starwishx'),
                'attachTo' => ['element' => '.btn-opportunity__add', 'on' => 'bottom'],
                'panel'    => 'opportunities',
                'view'     => null,
            ],
            // Step 2 — Title field (opens form view)
            [
                'id'       => 'title-field',
                'title'    => __('Title', 'starwishx'),
                'text'     => __('Give your opportunity a clear, descriptive title. This is the first thing people will see when browsing.', 'starwishx'),
                'attachTo' => ['element' => '#opportunity-title', 'on' => 'bottom'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 3 — Organization
            [
                'id'       => 'company-field',
                'title'    => __('Organization', 'starwishx'),
                'text'     => __('Enter the name of the organization providing this opportunity — whether it\'s an NGO, foundation, government body, or another entity.', 'starwishx'),
                'attachTo' => ['element' => '#opportunity-company', 'on' => 'bottom'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 4 — Source link
            [
                'id'       => 'source-link',
                'title'    => __('Source Link', 'starwishx'),
                'text'     => __('Add a link to the original source — the organization\'s website or the page with official details about this opportunity. This helps verify its authenticity.', 'starwishx'),
                'attachTo' => ['element' => '#opportunity-sourcelink', 'on' => 'bottom'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 5 — Categories
            [
                'id'       => 'categories',
                'title'    => __('Categories', 'starwishx'),
                'text'     => __('Select one or more categories that describe this opportunity. Good categorization helps people find what they need faster.', 'starwishx'),
                'attachTo' => ['element' => '.category-group-container', 'on' => 'top'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 6 — Seekers
            [
                'id'       => 'seekers',
                'title'    => __('Seekers', 'starwishx'),
                'text'     => __('Choose who this opportunity is intended for — the groups of people who can benefit from it.', 'starwishx'),
                'attachTo' => ['element' => '.checkbox-group.launchpad-grid-3-col', 'on' => 'top'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 7 — Description
            [
                'id'       => 'description',
                'title'    => __('Description', 'starwishx'),
                'text'     => __('Describe the opportunity in detail — what it offers, who can apply, and how to participate. Be clear and specific to help people understand if it\'s right for them.', 'starwishx'),
                'attachTo' => ['element' => '#opportunity-description', 'on' => 'top'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 8 — Form actions
            [
                'id'       => 'form-actions',
                'title'    => __('Save or Submit', 'starwishx'),
                'text'     => __('Save a draft to continue later, or submit for review when you\'re ready. Our team reviews each opportunity within 1–2 business days to ensure accuracy before publishing.', 'starwishx'),
                'attachTo' => ['element' => '.form-actions', 'on' => 'top'],
                'panel'    => 'opportunities',
                'view'     => 'add',
            ],
            // Step 9 — Completion
            [
                'id'       => 'complete',
                'title'    => __('You\'re Ready!', 'starwishx'),
                'text'     => __('That\'s everything you need to know. Go ahead and create your first opportunity — every submission helps people find the support they need.', 'starwishx'),
                'attachTo' => null,
                'panel'    => null,
                'view'     => null,
            ],
        ];
    }
}

<?php
// File: inc/launchpad/Panels/OpportunitiesPanel.php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\OpportunitiesService;
use Launchpad\Services\ProfileService;

class OpportunitiesPanel extends AbstractPanel
{
    private OpportunitiesService $service;
    private ProfileService $profileService;

    public function __construct(OpportunitiesService $service, ProfileService $profileService)
    {
        $this->service = $service;
        $this->profileService = $profileService;
    }

    public function getId(): string
    {
        return 'opportunities';
    }

    public function getLabel(): string
    {
        return __('Opportunities', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'icon-opportunities';
    }

    public function getInitialState(?int $userId = null): array
    {
        // Check profile completeness regardless of role
        $isLocked = !$userId || !$this->profileService->isProfileComplete($userId);

        // Fetch Taxonomy Options for Dropdowns
        // $options = $this->service->getFormOptions();

        // 1. Determine if the user is locked (Subscriber role)
        // $user = get_userdata($userId);
        // $roles = $user ? $user->roles : [];
        // $isLocked = in_array('subscriber', $roles) && !in_array('contributor', $roles) && !in_array('administrator', $roles);

        // 2. Define Empty Form Structure (Prevents "undefined" errors in JS)
        // Note: applicant_name, applicant_mail, applicant_phone are auto-filled from user profile
        $emptyForm = [
            'id' => null,
            'title' => '',
            'company' => '',
            'date_starts' => '',
            'date_ends' => '',
            'category' => [], // Array for multiple selection
            'country' => '',
            'locations' => [], // Must be initialized as array
            'city' => '',
            'sourcelink' => '',
            'application_form' => '',
            'seekers' => [],
            'subcategory' => [],
            'description' => '',
            'requirements' => '',
            'details' => '',
            'document' => null,
            'document_id' => null,
        ];

        // Early return: skip all expensive queries for locked users
        if ($isLocked) {
            return [
                'isLocked'    => true,
                'currentView' => 'list',
                'items'       => [],
                'total'       => 0,
                'options'     => [],
                'formData'    => $emptyForm,
                'emptyForm'   => $emptyForm,
            ];
        }

        $options = $this->service->getFormOptions();

        // 3. Routing Logic (List vs Edit vs Add)
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $currentFormData = $emptyForm;
        $error = null;

        if ($view === 'edit' && $editId > 0) {
            $fetchedData = $this->service->getSingleOpportunity($userId, $editId);
            if ($fetchedData) {
                $currentFormData = $fetchedData;
            } else {
                $error = __('Opportunity not found or access denied.', 'starwishx');
                $view = 'list'; // Fallback
            }
        }

        // 4. Fetch List Items
        $opportunities = $this->service->getUserOpportunities($userId);
        $total = $this->service->countUserOpportunities($userId);
        $currentLayout = 'compact';

        return [
            'isLocked'    => $isLocked, // Check user capabilities
            'currentView' => $view,
            'options'     => $options,     // Dropdown lists
            'formData'    => $currentFormData, // Actual form values
            'emptyForm'   => $emptyForm,
            'items'       => $opportunities,
            'total'       => $total,
            'page'        => 1,
            'totalPages'  => ceil($total / OpportunitiesService::ITEMS_PER_PAGE),
            'hasMore'     => $total > OpportunitiesService::ITEMS_PER_PAGE,
            'error'       => $error,
            'fieldErrors' => (object) [],
            'titleLimits' => [
                'min' => OpportunitiesService::TITLE_MIN_LENGTH,
                'max' => OpportunitiesService::TITLE_MAX_LENGTH,
            ],
            'textareaLimits' => [
                'description'  => OpportunitiesService::DESCRIPTION_MAX_LENGTH,
                'requirements' => OpportunitiesService::REQUIREMENTS_MAX_LENGTH,
                'details'      => OpportunitiesService::DETAILS_MAX_LENGTH,
            ],
            'validationMessages' => [
                'title'          => __('Title is required.', 'starwishx'),
                'titleMinLength' => sprintf(
                    __('Title must be at least %d characters.', 'starwishx'),
                    OpportunitiesService::TITLE_MIN_LENGTH
                ),
                'company'     => __('Company is required.', 'starwishx'),
                'sourcelink'  => __('Source link is required.', 'starwishx'),
                'description' => __('Description is required.', 'starwishx'),
                'category'    => __('At least one category is required.', 'starwishx'),
                'seekers'     => __('At least one seeker type is required.', 'starwishx'),
                'dateRange'   => __('End date cannot be before start date.', 'starwishx'),
            ],
            'confirmPopup' => [
                'isOpen'  => false,
                'itemId'  => null,
                'source'  => null, // 'form' or 'list'
            ],
            'isLoading'   => false,
            'isSaving'    => false,
            '_loaded'     => true,
            'formHeaders' => [
                'newOpportunity'  => esc_html__('New Opportunity', 'starwishx'),
                'editOpportunity' => esc_html__('Edit Opportunity', 'starwishx'),
            ],
            'layout'      => 'compact',
            'isLayoutCompact'  => ($currentLayout === 'compact'),
            'isLayoutCard'     => ($currentLayout === 'card'),
            'isLayoutGrid'     => ($currentLayout === 'grid'),
        ];
    }

    public function render(): string
    {
        // 1. Calculate lock state for initial server-side render
        // $current_user = wp_get_current_user();
        // $roles = $current_user->roles;
        // $isLocked = in_array('subscriber', $roles) && !in_array('contributor', $roles) && !in_array('administrator', $roles);

        // Determine the view on the server so we can set 'hidden' attributes
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $isListView = ($view === 'list');
        $isFormView = ($view === 'add' || $view === 'edit');
        // 2. Adjust visibility logic so list and form are hidden immediately if locked
        // $isListView = !$isLocked && ($view === 'list');
        // $isFormView = !$isLocked && ($view === 'add' || $view === 'edit');
        $opportunity_fields = acf_get_fields('group_69373c79e9c9a');

        // a lookup map for labels to ensure SSOT (Single Source of Truth)
        // Maps 'opportunity_company' => 'Company' (or whatever is set in ACF)
        $labels = [];
        $placeholders = [];
        $instructions = [];
        $required = [];
        if ($opportunity_fields) {
            foreach ($opportunity_fields as $field) {
                $labels[$field['name']] = $field['label'];
                $placeholders[$field['name']] = $field['placeholder'] ?? '';
                $instructions[$field['name']] = $field['instructions'] ?? '';
                $required[$field['name']] = (bool) ($field['required'] ?? 0);
            }
        }

        // Compute lock state for SSR — prevents flash of content before hydration
        $userId = get_current_user_id();
        $isLocked = !$userId || !$this->profileService->isProfileComplete($userId);

        $this->startBuffer();

        // Simplify paths for cleaner template
        $formPath = "state.panels.opportunities.formData";
        $optPath  = "state.panels.opportunities.options";
        $errPath  = "state.panels.opportunities.fieldErrors";
        $isLoadingPath = $this->statePath('isLoading');
?>
        <div class="launchpad-panel launchpad-panel--opportunities">
            <!-- NEW VIEW: ONBOARDING (Locked State) -->
            <!-- <div class="launchpad-onboarding placeholder-box"
                data-wp-bind--hidden="!state.isOppOnboardingVisible">
                <h2 class="panel-title">< ?php esc_html_e('Welcome to Opportunities!', 'starwishx'); ?></h2>
                <p>< ?php esc_html_e('To start publishing opportunities, we need a little more information about you to verify your account.', 'starwishx'); ?></p>

                <button class="btn button-primary"
                    data-wp-on--click="actions.opportunities.goToProfile">
                    < ?php esc_html_e('Complete Profile', 'starwishx'); ?>
                </button>
            </div> -->
            <div class="launchpad-panel--onboarding"
                <?php echo !$isLocked ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isOppOnboardingVisible">
                <hgroup>
                    <h2 class="panel-title"><?php esc_html_e('Post an Opportunity', 'starwishx'); ?></h2>
                    <p><?php esc_html_e('To publish opportunities, please complete your profile first — we need your name and phone number.', 'starwishx'); ?></p>
                </hgroup>
                <button class="btn"
                    data-wp-on--click="actions.opportunities.goToProfile">
                    <?php esc_html_e('Fill Profile', 'starwishx'); ?>
                </button>
                <div class="tour-wrapper" data-wp-interactive="tour">
                    <button class="btn-tertiary sw-tour-trigger sw-tour-subscriber-trigger"
                        data-tour-id="launchpad-intro"
                        data-wp-on--click="actions.startTour"
                        data-wp-bind--hidden="!state.showStartButton"
                        hidden>
                        <svg width="16" height="16" aria-hidden="true">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-lightning"></use>
                        </svg>
                        <span data-wp-text="state.triggerLabel"></span>
                    </button>
                </div>
            </div>
            <!-- VIEW: LIST -->
            <div class="launchpad-grid__container"
                <?php echo (!$isListView || $isLocked) ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isOppListVisible">

                <div class="launchpad-header">
                    <hgroup>
                        <h2 class="panel-title"><?php esc_html_e('Opportunities List', 'starwishx'); ?></h2>
                        <p class="panel-description"><?php esc_html_e('You can add new opportunities, edit existing ones, and submit them for review. All opportunity data is reviewed and moderated by the site administration team before being approved for publication or rejected. Please contact the site staff if you have any questions.', 'starwishx'); ?></p>
                        <div class="launchpad-sidebar-footer" data-wp-interactive="tour">
                            <button class="btn-tertiary sw-tour-trigger sw-tour-contributor-trigger"
                                data-tour-id="opportunity-form"
                                data-wp-on--click="actions.startTour"
                                data-wp-bind--hidden="!state.showStartButton"
                                hidden>
                                <svg width="16" height="16">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-lightning"></use>
                                </svg>
                                <span data-wp-text="state.triggerLabel"></span>
                            </button>
                        </div>
                    </hgroup>

                    <div class="launchpad-controls">
                        <!-- Filter Group -->
                        <div class="launchpad-filters" data-wp-init="actions.opportunities.initFilters">
                            <span class="filter-label"><?php esc_html_e('Filter by status:', 'starwishx'); ?></span>
                            <div class="filter-checkboxes">
                                <?php
                                $statuses = [
                                    'draft'   => __('Drafts', 'starwishx'),
                                    'pending' => __('Pending', 'starwishx'),
                                    'publish' => __('Published', 'starwishx'),
                                ];
                                foreach ($statuses as $slug => $label) : ?>
                                    <label class="filter-checkbox">
                                        <input type="checkbox"
                                            value="<?php echo $slug; ?>"
                                            checked
                                            data-wp-on--change="actions.opportunities.toggleFilter">
                                        <span><?php echo $label; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Layout Switcher Radio Group -->
                        <div class="layout-switcher">
                            <div class="btn-group-toggle" role="group">
                                <?php
                                $layouts = [
                                    'compact' => 'icon-view-rows',
                                    'card'    => 'icon-view-blocks',
                                    'grid'    => 'icon-view-columns',
                                ];

                                foreach ($layouts as $slug => $icon) :
                                    $camelSlug = ucfirst($slug);
                                    $stateKey = "isLayout{$camelSlug}"; // e.g. isLayoutCompact
                                ?>
                                    <input type="radio"
                                        class="layout-check"
                                        name="grid_layout"
                                        id="layout-<?php echo $slug; ?>"
                                        value="<?php echo $slug; ?>"
                                        data-wp-on--change="actions.opportunities.setLayout"
                                        data-wp-bind--checked="state.panels.opportunities.<?php echo $stateKey; ?>">
                                    <label
                                        class="layout-btn"
                                        for="layout-<?php echo $slug; ?>"
                                        data-wp-class--active="state.panels.opportunities.<?php echo $stateKey; ?>">

                                        <svg width="18" height="18" aria-hidden="true" class="layout-btn__icon">
                                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#<?php echo $icon; ?>"></use>
                                        </svg>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button class="btn-secondary btn-opportunity__add" data-wp-on--click="actions.opportunities.openAdd">
                        <svg class="btn-secondary__icon cross">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus"></use>
                        </svg>
                        <span><?php esc_html_e('Add New', 'starwishx'); ?></span>
                    </button>
                </div>

                <div id="launchpad-alert" class="launchpad-alert launchpad-alert--error label-info exclamation-circle__error"
                    data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                    data-wp-text="<?= $this->statePath('error') ?>"></div>

                <div class="launchpad-loading" data-wp-bind--hidden="!<?= $isLoadingPath ?>">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading...', 'starwishx'); ?>
                </div>

                <div class="opportunities-empty" data-wp-bind--hidden="!state.showOppEmpty">
                    <span class="dashicons dashicons-megaphone"></span>
                    <p><?php esc_html_e('No opportunities yet.', 'starwishx'); ?></p>
                </div>

                <!-- Grid -->
                <div class="opportunities-grid placeholder-box" data-wp-bind--hidden="!state.showOppGrid" data-wp-bind--data-layout="state.panels.opportunities.layout">
                    <template data-wp-each="<?= $this->statePath('items') ?>">
                        <article class="opportunity-card"
                            data-wp-bind--data-status="context.item.status"
                            data-wp-bind--data-expired="context.item.isExpired"
                            data-wp-bind--data-layout="state.panels.opportunities.layout">
                            <div class="opportunity-card--inner">
                                <div class="opportunity-card__content-wrapper">
                                    <figure class="opportunity-card__figure">
                                        <a class="opportunity-card__figure--link" data-wp-bind--href="context.item.viewUrl" target="_blank">
                                            <img
                                                class="opportunity-card__image"
                                                data-wp-bind--src="context.item.thumbnailUrl"
                                                data-wp-bind--alt="context.item.title"
                                                data-wp-bind--hidden="!context.item.thumbnailUrl" />
                                            <!-- Fallback Placeholder -->
                                            <div class="opportunity-card__placeholder" data-wp-bind--hidden="context.item.thumbnailUrl">
                                                <!-- <svg width="20" height="20" class="icon-opportunity">
                                                    <use xlink:href="< ?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-opportunities"></use>
                                                </svg> -->
                                                <img class="card-image__fallback--icon" src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg" alt="fallback image">
                                            </div>
                                        </a>
                                    </figure>

                                    <div class="opportunity-content">
                                        <a data-wp-bind--href="context.item.viewUrl" target="_blank">
                                            <h3 class="opportunity-title" data-wp-text="context.item.title"></h3>
                                        </a>
                                        <div class="opportunity-meta">
                                            <div class="opportunity-meta__container-date">
                                                <span class="opportunity-date" data-wp-text="context.item.date"></span>
                                                <!-- Expired Label -->
                                                <span
                                                    class="status-badge status-badge--expired"
                                                    data-wp-bind--hidden="!context.item.isExpired">
                                                    <?php esc_html_e('Finished', 'starwishx'); ?>
                                                </span>
                                                <!-- Rating & Comments -->
                                                <div class="opportunity-engage">
                                                    <span class="opportunity-engage__rating rating-badge"
                                                        data-wp-bind--hidden="!context.item.ratingCount">
                                                        <span class="stars-display"
                                                            data-wp-bind--data-rating="context.item.ratingRounded"
                                                            aria-hidden="true">
                                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                                <svg class="icon-star star-<?php echo $s; ?>" width="14" height="14" aria-hidden="true">
                                                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star"></use>
                                                                </svg>
                                                            <?php endfor; ?>
                                                        </span>
                                                        <span class="opportunity-engage__rating-avg"
                                                            data-wp-text="context.item.ratingAvg"></span>
                                                    </span>
                                                    <a class="opportunity-engage__comments-link"
                                                        data-wp-bind--hidden="!context.item.commentsCount"
                                                        data-wp-bind--href="context.item.commentsUrl">
                                                        <?= sw_svg('icon-write', 14); ?>
                                                        <?= __("Comments", 'starwishx') ?>:
                                                        <span data-wp-text="context.item.commentsCount"></span>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="opportunity-meta__container-statuses">
                                                <!-- Visual Label -->
                                                <span
                                                    class="status-badge"
                                                    data-wp-bind--data-status="context.item.status"
                                                    data-wp-text="context.item.statusLabel">
                                                </span>
                                            </div>
                                        </div>
                                        <!-- <div class="opportunity-taxonomy"
                                    data-wp-text="context.item.categoryName"
                                    data-wp-bind--hidden="!context.item.categoryName">
                                </div> -->
                                        <div class="opportunity-info">
                                            <div class="opportunity-range">
                                                <span class="opportunity-range__title">Lasts</span>
                                                <span data-wp-text="context.item.dateStarts"></span>
                                                <span class="opportunity-range__icon dashicons dashicons-calendar-alt"></span>
                                                <span class="range-sep">—</span>
                                                <span data-wp-text="context.item.dateEnds"></span>
                                            </div>
                                        </div>
                                        <!-- <p class="opportunity-excerpt"
                                    data-wp-text="context.item.excerpt"
                                    data-wp-bind--hidden="!context.item.excerpt"></p> -->
                                    </div>
                                </div>
                                <div class="opportunity-actions">
                                    <!-- <a class="btn-secondary__small btn-opportunity__view" data-wp-bind--href="context.item.viewUrl" target="_blank">
                                    < ?php esc_html_e('View', 'starwishx'); ? >
                                </a> -->
                                    <!-- Submit -->
                                    <button class="btn-secondary__small btn-opportunity__review"
                                        data-wp-on--click="actions.opportunities.quickSubmit"
                                        data-wp-bind--disabled="!state.canEdit"
                                        data-wp-bind--hidden="state.isPublish"
                                        data-wp-bind--data-status="context.item.status">
                                        <span><?php esc_html_e('Submit for Review', 'starwishx'); ?></span>
                                    </button>
                                    <button class="btn-tertiary btn-opportunity__edit"
                                        data-wp-on--click="actions.opportunities.openEdit"
                                        data-wp-bind--data-id="context.item.id"
                                        data-wp-bind--disabled="!state.canEdit"
                                        data-wp-bind--hidden="state.isPublish"
                                        data-wp-bind--data-status="context.item.status">
                                        <span><?php esc_html_e('Edit', 'starwishx'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="opportunities-pagination" data-wp-bind--hidden="!<?= $this->statePath('hasMore') ?>">
                    <button class="btn-tertiary opportunities-pagination__button"
                        data-wp-on--click="actions.opportunities.loadMore"
                        data-wp-bind--disabled="<?= $isLoadingPath ?>">
                        <?php esc_html_e('Load More', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <!-- VIEW: FORM (Shared for Add & Edit) -->
            <div class="launchpad-form__container"
                <?php echo (!$isFormView || $isLocked) ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isOppFormVisible"
                data-wp-bind--data-is-loading="<?= $isLoadingPath ?>">
                <!-- Bind loading state ☝🏻 to a data attribute for CSS targeting -->

                <div class="launchpad-form-header" data-wp-bind--data-status="state.panels.opportunities.formData.status">
                    <h2 data-wp-text="state.opportunityFormHeaders"></h2>
                    <button class="btn-secondary__small" type="button" data-wp-on--click="actions.opportunities.cancel">
                        <svg class="btn-secondary__small--icon arrow-left">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                        </svg>
                        <?php esc_html_e('Back', 'starwishx'); ?>
                    </button>
                </div>

                <form class="launchpad-form" data-wp-on--submit="actions.opportunities.save">

                    <!-- Main Title -->
                    <div class="form-field form-field__title">
                        <label for="opportunity-title" class="label-required"><?php echo esc_html($labels['title'] ?? __('Opportunity Title', 'starwishx')); ?></label>
                        <input id="opportunity-title" type="text" required class="large-text"
                            maxlength="<?= OpportunitiesService::TITLE_MAX_LENGTH ?>"
                            placeholder="<?= esc_attr(sprintf(__('Add title min %1$d, max %2$d characters', 'starwishx'), OpportunitiesService::TITLE_MIN_LENGTH, OpportunitiesService::TITLE_MAX_LENGTH)); ?>"
                            data-wp-bind--value="<?= $formPath ?>.title"
                            data-wp-on--input="actions.opportunities.updateForm"
                            data-wp-bind--disabled="<?= $isLoadingPath ?>"
                            data-field="title">
                        <div class="form-field__footer">
                            <label class="exclamation-circle__error" hidden
                                data-wp-bind--hidden="!<?= $errPath ?>.title"
                                data-wp-text="<?= $errPath ?>.title"></label>
                            <span class="char-counter"
                                data-wp-class--char-counter--warn="state.isTitleTooShort"
                                data-wp-text="state.titleCounterText"></span>
                        </div>
                    </div>

                    <!-- 3 Column Layout -->
                    <div class="">

                        <!-- GROUP 1: Info -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php esc_html_e('Opportunity Info', 'starwishx'); ?></h3>
                            <div class="form-card-data ">
                                <div class="launchpad-grid-auto">
                                    <div class="form-field">
                                        <label for="opportunity-company" class="<?php echo $required['opportunity_company'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_company'] ?? __('Company', 'starwishx')); ?></label>
                                        <input id="opportunity-company" type="text"
                                            <?php echo $required['opportunity_company'] ? 'required' : ''; ?>
                                            placeholder="<?php echo esc_attr($placeholders['opportunity_company'] ?? ''); ?>"
                                            data-wp-bind--value="<?= $formPath ?>.company" data-wp-on--input="actions.opportunities.updateForm" data-field="company">
                                        <label class="exclamation-circle__error" hidden
                                            data-wp-bind--hidden="!<?= $errPath ?>.company"
                                            data-wp-text="<?= $errPath ?>.company"></label>
                                    </div>

                                    <!-- <div class="form-row"> -->
                                    <div class="form-field">
                                        <label class="<?php echo $required['opportunity_date_starts'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_date_starts'] ?? __('Start Date', 'starwishx')); ?></label>
                                        <div class="input-date-iconed">
                                            <input type="date"
                                                <?php echo $required['opportunity_date_starts'] ? 'required' : ''; ?>
                                                data-wp-bind--value="<?= $formPath ?>.date_starts" data-wp-on--input="actions.opportunities.updateForm" data-field="date_starts">
                                            <button type="button" class="input-date-iconed__btn" data-wp-on--click="actions.opportunities.openDatePicker" aria-label="<?php esc_attr_e('Open calendar', 'starwishx'); ?>">
                                                <svg width="18" height="18" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-calendar"></use>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-field">
                                        <label class="<?php echo $required['opportunity_date_ends'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_date_ends'] ?? __('End Date', 'starwishx')); ?></label>
                                        <div class="input-date-iconed">
                                            <input type="date"
                                                <?php echo $required['opportunity_date_ends'] ? 'required' : ''; ?>
                                                data-wp-bind--value="<?= $formPath ?>.date_ends" data-wp-on--input="actions.opportunities.updateForm" data-field="date_ends">
                                            <button type="button" class="input-date-iconed__btn" data-wp-on--click="actions.opportunities.openDatePicker" aria-label="<?php esc_attr_e('Open calendar', 'starwishx'); ?>">
                                                <svg width="18" height="18" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-calendar"></use>
                                                </svg>
                                            </button>
                                        </div>
                                        <label class="exclamation-circle__error" hidden
                                            data-wp-bind--hidden="!<?= $errPath ?>.date_ends"
                                            data-wp-text="<?= $errPath ?>.date_ends"></label>
                                    </div>
                                </div>

                                <div class="launchpad-grid-auto">
                                    <div class="form-field">
                                        <label for="opportunity-sourcelink" class="<?php echo $required['opportunity_sourcelink'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_sourcelink'] ?? __('Link', 'starwishx')); ?></label>
                                        <input id="opportunity-sourcelink" type="url"
                                            <?php echo $required['opportunity_sourcelink'] ? 'required' : ''; ?>
                                            placeholder="<?php echo esc_attr($placeholders['opportunity_sourcelink'] ?? ''); ?>"
                                            data-wp-bind--value="<?= $formPath ?>.sourcelink" data-wp-on--input="actions.opportunities.updateForm" data-field="sourcelink">
                                        <label class="exclamation-circle__error" hidden
                                            data-wp-bind--hidden="!<?= $errPath ?>.sourcelink"
                                            data-wp-text="<?= $errPath ?>.sourcelink"></label>
                                    </div>
                                    <div class="form-field">
                                        <label class="<?php echo $required['opportunity_application_form'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_application_form'] ?? __('Application Form URL', 'starwishx')); ?></label>
                                        <input type="url"
                                            <?php echo $required['opportunity_application_form'] ? 'required' : ''; ?>
                                            placeholder="<?php echo esc_attr($placeholders['opportunity_application_form'] ?? ''); ?>"
                                            data-wp-bind--value="<?= $formPath ?>.application_form" data-wp-on--input="actions.opportunities.updateForm" data-field="application_form">
                                    </div>
                                    <!-- Country Select -->
                                    <div class="form-field">
                                        <label class="<?php echo $required['country'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['country'] ?? __('Country', 'starwishx')); ?></label>
                                        <select
                                            <?php echo $required['country'] ? 'required' : ''; ?>
                                            data-wp-bind--value="<?= $formPath ?>.country"
                                            data-wp-on--change="actions.opportunities.updateForm"
                                            data-field="country">
                                            <option value=""><?php esc_html_e('Select Country', 'starwishx'); ?></option>
                                            <template data-wp-each="<?= $optPath ?>.countries">
                                                <option data-wp-bind--value="context.item.id" data-wp-text="context.item.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-field form-field-locations" data-wp-bind--hidden="!state.isUkraineSelected">
                                    <label class="label-info">
                                        <!-- < ?php esc_html_e('Locations (not necessary)', 'starwishx'); ? ><br> -->
                                        <!-- < ?php sw_svg_e() ?> -->

                                        <span class="exclamation-circle">
                                            <?php esc_html_e('Not necessary. Only if you want to point that your opportunity is available for particular locations: City, District, Hromada, etc.', 'starwishx'); ?>
                                        </span>
                                    </label>

                                    <!-- 1. The Selected Chips (Shared for all 3 inputs) -->
                                    <!-- Showing this AT THE TOP is better UX so user sees what they added immediately -->
                                    <div class="locations-chips"
                                        data-wp-bind--hidden="!state.panels.opportunities.formData.locations.length">
                                        <template data-wp-each="state.panels.opportunities.formData.locations">
                                            <span class="location-chip btn-chip">
                                                <!-- Using the name from the View (name_category_oblast) -->
                                                <span data-wp-text="context.item.name"></span>
                                                <!-- &times; Button-->
                                                <button class="location-remove btn-chip__icon" data-wp-on--click="actions.opportunities.removeLocation">
                                                    <svg class="">
                                                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus-small"></use>
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>
                                    </div>

                                    <!-- 2. The 3-Column Search Grid -->
                                    <div class="launchpad-grid-3-col">

                                        <!-- BOX 1: OBLAST -->
                                        <div class="location-input-wrapper location-search-wrapper">
                                            <label><?php esc_html_e('if opportynity for Oblast', 'starwishx'); ?></label>
                                            <div class="input-iconed">
                                                <input type="text"
                                                    placeholder="<?php esc_attr_e('Kyivska, Lvivska...', 'starwishx'); ?>"
                                                    data-wp-bind--value="state.panels.opportunities.formData.searchOblast"
                                                    data-wp-on--input="actions.opportunities.searchKatottgOblast"
                                                    autocomplete="off">
                                                <svg class="">
                                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search-small"></use>
                                                </svg>
                                            </div>

                                            <ul class="location-results location-dropdown" data-wp-bind--hidden="!state.panels.opportunities.formData.resultsOblast.length">
                                                <template data-wp-each="state.panels.opportunities.formData.resultsOblast">
                                                    <li class="location-result-item" data-wp-on--click="actions.opportunities.addLocation"
                                                        tabindex="0"
                                                        role="button">
                                                        <span data-wp-text="context.item.name"></span>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>

                                        <!-- BOX 2: RAION -->
                                        <div class="location-input-wrapper location-search-wrapper">
                                            <label><?php esc_html_e('if for Raion, District, Hromada', 'starwishx'); ?></label>
                                            <div class="input-iconed">
                                                <input type="text"
                                                    placeholder="<?php esc_attr_e('Buchanskyi r-n, Dolynska tg..', 'starwishx'); ?>"
                                                    data-wp-bind--value="state.panels.opportunities.formData.searchRaion"
                                                    data-wp-on--input="actions.opportunities.searchKatottgRaion"
                                                    autocomplete="off">
                                                <svg class="">
                                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search-small"></use>
                                                </svg>
                                            </div>
                                            <ul class="location-results location-dropdown" data-wp-bind--hidden="!state.panels.opportunities.formData.resultsRaion.length">
                                                <template data-wp-each="state.panels.opportunities.formData.resultsRaion">
                                                    <li class="location-result-item" data-wp-on--click="actions.opportunities.addLocation"
                                                        tabindex="0"
                                                        role="button">
                                                        <span data-wp-text="context.item.name"></span>
                                                        <!-- <small data-wp-text="context.item.category"></small> -->
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>

                                        <!-- BOX 3: CITY/HROMADA -->
                                        <div class="location-input-wrapper location-search-wrapper">
                                            <label><?php esc_html_e('if for City, Settlement, City district', 'starwishx'); ?></label>
                                            <div class="input-iconed">
                                                <input type="text"
                                                    placeholder="<?php esc_attr_e('Bucha c., Vesele v., Obolon...', 'starwishx'); ?>"
                                                    data-wp-bind--value="state.panels.opportunities.formData.searchCity"
                                                    data-wp-on--input="actions.opportunities.searchKatottgCity"
                                                    autocomplete="off">
                                                <svg class="">
                                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search-small"></use>
                                                </svg>
                                            </div>
                                            <ul class="location-results location-dropdown" data-wp-bind--hidden="!state.panels.opportunities.formData.resultsCity.length">
                                                <template data-wp-each="state.panels.opportunities.formData.resultsCity">
                                                    <li class="location-result-item" data-wp-on--click="actions.opportunities.addLocation"
                                                        tabindex="0"
                                                        role="button">
                                                        <span data-wp-text="context.item.name"></span>
                                                        <!-- <small data-wp-text="context.item.category"></small> -->
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>
                                    <label class="label-info">
                                        <?php esc_html_e('If you want you coud to select the required administrative level. If the opportunity is provided throughout the Oblast - select from (1) Oblast, if the opportunity is for a district or community - select from (2) District, if the opportunity concerns a city or village select the required City or Village from (3).', 'starwishx'); ?>
                                    </label>
                                </div>
                                <div class="form-field">
                                    <label class="<?php echo $required['opportunity_category'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_category'] ?? __('Opportunity Category', 'starwishx')); ?></label>
                                    <!-- Hierarchical Checkboxes -->
                                    <div class="category-group-container ">
                                        <template data-wp-each="<?= $optPath ?>.categories">
                                            <div class="category-group">
                                                <!-- Parent Category Name (Header) -->
                                                <h4 class="category-group-title"
                                                    data-wp-text="context.item.name"></h4>

                                                <!-- Checkboxes for Children -->
                                                <div class="category-group-items launchpad-grid-3-col--sm">
                                                    <template data-wp-each--child="context.item.children">
                                                        <label class="launchpad-form__checkbox">
                                                            <input type="checkbox"
                                                                data-wp-bind--value="context.child.id"
                                                                data-wp-bind--checked="state.isCategoryChecked"
                                                                data-wp-on--change="actions.opportunities.toggleCategory">
                                                            <span data-wp-text="context.child.name"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <label class="exclamation-circle__error" hidden
                                        data-wp-bind--hidden="!<?= $errPath ?>.category"
                                        data-wp-text="<?= $errPath ?>.category"></label>
                                </div>

                                <div class="form-field">
                                    <label class="<?php echo $required['opportunity_seekers'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_seekers'] ?? __('Seekers', 'starwishx')); ?></label>
                                    <div class="checkbox-group launchpad-grid-3-col">
                                        <template data-wp-each="<?= $optPath ?>.seekers">
                                            <label class="launchpad-form__checkbox">
                                                <input type="checkbox"
                                                    data-wp-bind--value="context.item.id"
                                                    data-wp-bind--checked="state.isSeekerChecked"
                                                    data-wp-on--change="actions.opportunities.toggleSeeker">
                                                <span data-wp-text="context.item.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <label class="exclamation-circle__error" hidden
                                        data-wp-bind--hidden="!<?= $errPath ?>.seekers"
                                        data-wp-text="<?= $errPath ?>.seekers"></label>
                                </div>
                            </div>
                        </div>

                        <!-- GROUP 3: Description -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php esc_html_e('Description', 'starwishx'); ?></h3>
                            <div class="form-card-data">
                                <div for="opportunity-description" class="form-field form-field-description">
                                    <label class="label-required"><?php esc_html_e('Describe the opportunity', 'starwishx'); ?></label>
                                    <textarea id="opportunity-description" rows="6" required class="widefat"
                                        maxlength="<?= OpportunitiesService::DESCRIPTION_MAX_LENGTH ?>"
                                        placeholder="<?php esc_html_e('Add information about the opportunity. Describe the essence.', 'starwishx'); ?>"
                                        data-wp-bind--value="<?= $formPath ?>.description" data-wp-on--input="actions.opportunities.updateForm" data-field="description"></textarea>
                                    <div class="form-field__footer">
                                        <label class="exclamation-circle__error" hidden
                                            data-wp-bind--hidden="!<?= $errPath ?>.description"
                                            data-wp-text="<?= $errPath ?>.description"></label>
                                        <span class="char-counter"
                                            data-wp-class--char-counter--warn="state.isDescriptionNearLimit"
                                            data-wp-text="state.descriptionCounterText"></span>
                                    </div>
                                </div>
                                <div class="form-field form-field-description">
                                    <label class="<?php echo $required['opportunity_requirements'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_requirements'] ?? __('Requirements', 'starwishx')); ?></label>
                                    <textarea rows="6" class="widefat"
                                        maxlength="<?= OpportunitiesService::REQUIREMENTS_MAX_LENGTH ?>"
                                        <?php echo $required['opportunity_requirements'] ? 'required' : ''; ?>
                                        placeholder="<?php echo esc_attr($placeholders['opportunity_requirements'] ?? ''); ?>"
                                        data-wp-bind--value="<?= $formPath ?>.requirements" data-wp-on--input="actions.opportunities.updateForm" data-field="requirements"></textarea>
                                    <div class="form-field__footer">
                                        <span class="char-counter"
                                            data-wp-class--char-counter--warn="state.isRequirementsNearLimit"
                                            data-wp-text="state.requirementsCounterText"></span>
                                    </div>
                                </div>
                                <div class="form-field form-field-description">
                                    <label class="<?php echo $required['opportunity_details'] ? 'label-required' : ''; ?>"><?php echo esc_html($labels['opportunity_details'] ?? __('Details', 'starwishx')); ?></label>
                                    <textarea rows="4" class="widefat"
                                        maxlength="<?= OpportunitiesService::DETAILS_MAX_LENGTH ?>"
                                        <?php echo $required['opportunity_details'] ? 'required' : ''; ?>
                                        placeholder="<?php echo esc_attr($placeholders['opportunity_details'] ?? ''); ?>"
                                        data-wp-bind--value="<?= $formPath ?>.details" data-wp-on--input="actions.opportunities.updateForm" data-field="details"></textarea>
                                    <div class="form-field__footer">
                                        <span class="char-counter"
                                            data-wp-class--char-counter--warn="state.isDetailsNearLimit"
                                            data-wp-text="state.detailsCounterText"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GROUP 4: Document -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php echo esc_html($labels['opportunity_document'] ?? __('Document', 'starwishx')); ?></h3>
                            <div class="form-card-data">
                                <div class="form-field">
                                    <input type="file" id="opp-doc-upload"
                                        accept=".pdf,.doc,.docx"
                                        style="display:none;"
                                        data-wp-on--change="actions.opportunities.handleFileSelect">

                                    <!-- Empty State -->
                                    <div data-wp-bind--hidden="state.panels.opportunities.formData.document" class="documents-chips">
                                        <button type="button" class="document-btn btn-chip"
                                            data-wp-on--click="actions.opportunities.triggerFileSelect"
                                            data-wp-bind--disabled="state.panels.opportunities.isSaving">
                                            <!-- <span class="dashicons dashicons-paperclip"></span> -->
                                            <svg width="18" height="18" aria-hidden="true" class="document-btn__icon">
                                                <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-clip"></use>
                                            </svg>

                                            <?php echo esc_html($instructions['opportunity_document'] ?? __('Attach File', 'starwishx')); ?>
                                            <!-- < ?php esc_html_e('Attach File', 'starwishx'); ?> -->
                                        </button>
                                    </div>

                                    <!-- Preview State -->
                                    <div class="locations-chips" data-wp-bind--hidden="!state.panels.opportunities.formData.document">
                                        <span class="location-chip btn-chip btn-chip--document">
                                            <span class="btn-chip--document__content">
                                                <!-- <span class="dashicons dashicons-media-document"></span> -->
                                                <svg width="18" height="18" aria-hidden="true" class="btn-chip--document__icon">
                                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-doc"></use>
                                                </svg>

                                                <!-- Link to file if it exists and is not pending -->
                                                <a class="btn-chip--document__link"
                                                    data-wp-bind--href="state.panels.opportunities.formData.document.url"
                                                    target="_blank"
                                                    data-wp-bind--hidden="state.panels.opportunities.formData.document.isPending">
                                                    <span data-wp-text="state.panels.opportunities.formData.document.name"></span>
                                                </a>

                                                <!-- Just Text if Pending -->
                                                <span data-wp-bind--hidden="!state.panels.opportunities.formData.document.isPending"
                                                    data-wp-text="state.panels.opportunities.formData.document.name"></span>

                                                <small style="opacity: 0.7;" data-wp-text="state.panels.opportunities.formData.document.size"></small>
                                            </span>

                                            <span style="display:flex; align-items:center; gap:8px;">
                                                <span class="status-badge" style="font-size:10px;"
                                                    data-wp-bind--hidden="!state.panels.opportunities.formData.document.isPending">
                                                    <?php esc_html_e('Pending Save', 'starwishx'); ?>
                                                </span>

                                                <!-- Spinner instead of Progress Bar -->
                                                <span class="spinner is-active"
                                                    style="margin:0;"
                                                    data-wp-bind--hidden="!state.panels.opportunities.isUploading"></span>

                                                <button type="button" class="location-remove btn-chip__icon"
                                                    data-wp-on--click="actions.opportunities.removeDocument"
                                                    data-wp-bind--disabled="state.panels.opportunities.isSaving">
                                                    <svg>
                                                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus-small"></use>
                                                    </svg>
                                                </button>
                                            </span>
                                        </span>
                                    </div>
                                    <label>
                                        <?php esc_html_e('Format (PDF, DOCX). Max size 5Mb.', 'starwishx'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div> <!-- End Grid -->

                    <div class="form-actions">
                        <!-- data-wp-bind--disabled="< ?= $this->statePath('isSaving') ?>" -->
                        <!-- data-wp-bind--hidden="!state.canEdit" -->
                        <!-- Save Draft -->
                        <button type="submit" class="btn__small"
                            data-wp-on--click="actions.opportunities.validateRequired"
                            data-wp-bind--disabled="state.panels.opportunities.isSaving">
                            <?php esc_html_e('Save Draft', 'starwishx'); ?>
                        </button>

                        <!-- WORKFLOW ACTION: Submit for Review -->
                        <!-- Only show if status is not already 'pending' or 'publish' -->
                        <!-- data-wp-bind--disabled="< ?= $this->statePath('isSaving') ?>" -->
                        <!-- data-wp-bind--hidden="!state.canEdit" -->
                        <button
                            type="button"
                            class="btn-secondary__small launchpad-form__btn--review"
                            data-wp-on--click="actions.opportunities.submitForReview"
                            data-wp-bind--disabled="state.panels.opportunities.isSaving">
                            <?php esc_html_e('Submit for Review', 'starwishx'); ?>
                        </button>
                        <!-- Back to list -->
                        <button class="btn-secondary__small" type="button" data-wp-on--click="actions.opportunities.cancel">
                            <svg class="btn-secondary__small--icon arrow-left">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                            </svg>
                            <?php esc_html_e('Back', 'starwishx'); ?>
                        </button>
                        <!-- indicator for non-editable items -->
                        <!-- <div class="status-alert" data-wp-bind--hidden="state.canEdit">
                            <span class="dashicons dashicons-lock"></span>
                            < ?php esc_html_e('This item is currently under review and cannot be modified.', 'starwishx'); ? >
                        </div> -->
                    </div>
                </form>
            </div>

            <!-- Confirm Submit Popup -->
            <div class="popup"
                hidden
                data-wp-bind--hidden="!<?= $this->statePath('confirmPopup') ?>.isOpen">

                <div class="popup__backdrop" data-wp-on--click="actions.opportunities.closeConfirm"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-submit-title">
                    <button type="button" class="popup__close"
                        data-wp-on--click="actions.opportunities.closeConfirm"
                        aria-label="<?php esc_attr_e('Close', 'starwishx'); ?>">
                        <svg class="popup__close-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </button>

                    <div class="popup__body">
                        <h2 id="confirm-submit-title" class="popup__title">
                            <?php esc_html_e('Submit for Review', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?php esc_html_e('Submit your changes and request a review from the moderation team?', 'starwishx'); ?>
                        </p>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn popup__footer--button"
                            data-wp-on--click="actions.opportunities.confirmSubmit"
                            data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Submit', 'starwishx'); ?>
                        </button>
                        <button type="button" class="btn-secondary popup__footer--button"
                            data-wp-on--click="actions.opportunities.closeConfirm">
                            <?php esc_html_e('Cancel', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
<?php
        return $this->endBuffer();
    }
}

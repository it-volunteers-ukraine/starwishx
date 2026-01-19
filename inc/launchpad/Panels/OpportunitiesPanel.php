<?php
// File: inc/launchpad/Panels/OpportunitiesPanel.php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\OpportunitiesService;

class OpportunitiesPanel extends AbstractPanel
{
    private OpportunitiesService $service;

    public function __construct(OpportunitiesService $service)
    {
        $this->service = $service;
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
        return 'megaphone';
    }

    public function getInitialState(?int $userId = null): array
    {
        // 1. Fetch Taxonomy Options for Dropdowns
        $options = $this->service->getFormOptions();

        // 2. Define Empty Form Structure (Prevents "undefined" errors in JS)
        $emptyForm = [
            'id' => null,
            'title' => '',
            'applicant_name' => '',
            'applicant_mail' => '',
            'applicant_phone' => '',
            'company' => '',
            'date_starts' => '',
            'date_ends' => '',
            'category' => '',
            'country' => '',
            'city' => '',
            'sourcelink' => '',
            'seekers' => [],
            'subcategory' => [],
            'description' => '',
            'requirements' => '',
            'details' => ''
        ];

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
        // Determine the view on the server so we can set 'hidden' attributes
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $isListView = ($view === 'list');
        $isFormView = ($view === 'add' || $view === 'edit');

        $this->startBuffer();

        // Simplify paths for cleaner template
        $formPath = "state.panels.opportunities.formData";
        $optPath  = "state.panels.opportunities.options";
        $isLoadingPath = $this->statePath('isLoading');
?>
        <div class="launchpad-panel launchpad-panel--opportunities">

            <!-- VIEW: LIST -->
            <div class="launchpad-grid__container"
                <?php echo !$isListView ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isOppListVisible">

                <div class="launchpad-header">
                    <hgroup>
                        <h2 class="panel-title"><?php esc_html_e('Opportunities List', 'starwishx'); ?></h2>
                        <p class="panel-description"><?php esc_html_e('You can add new opportunities, edit existing ones, and submit them for review. All opportunity data is reviewed and moderated by the site administration team before being approved for publication or rejected. Please contact the site staff if you have any questions.', 'starwishx'); ?></p>
                    </hgroup>

                    <div class="launchpad-controls">
                        <!-- Layout Switcher Radio Group -->
                        <div class="layout-switcher">
                            <div class="btn-group-toggle" role="group">
                                <?php
                                $layouts = [
                                    'compact' => 'dashicons-list-view',
                                    'card'    => 'dashicons-grid-view',
                                    'grid'    => 'dashicons-columns',
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
                                        <span class="dashicons <?php echo $icon; ?>"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

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
                    </div>

                    <button class="btn-secondary btn-opportunity__add" data-wp-on--click="actions.opportunities.openAdd">
                        <svg class="btn-secondary__icon cross">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus"></use>
                        </svg>
                        <span><?php esc_html_e('Add New', 'starwishx'); ?></span>
                    </button>
                </div>

                <div class="launchpad-alert launchpad-alert--error"
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
                            <!-- Image Container -->
                            <div class="opportunity-card__figure">
                                <a data-wp-bind--href="context.item.viewUrl" target="_blank">
                                    <!-- Actual Image -->
                                    <img
                                        class="opportunity-card__image"
                                        data-wp-bind--src="context.item.thumbnailUrl"
                                        data-wp-bind--alt="context.item.title"
                                        data-wp-bind--hidden="!context.item.thumbnailUrl" />

                                    <!-- Fallback Placeholder (shown if no image) -->
                                    <div class="opportunity-card__placeholder" data-wp-bind--hidden="context.item.thumbnailUrl">
                                        <span class="dashicons dashicons-format-image"></span>
                                    </div>
                                </a>
                            </div>

                            <div class="opportunity-content">
                                <a data-wp-bind--href="context.item.viewUrl" target="_blank">
                                    <h3 class="opportunity-title" data-wp-text="context.item.title"></h3>
                                </a>
                                <div class="opportunity-meta">
                                    <span class="opportunity-date" data-wp-text="context.item.date"></span>
                                    <!-- Visual Label -->
                                    <span
                                        class="status-badge"
                                        data-wp-bind--data-status="context.item.status"
                                        data-wp-text="context.item.status">
                                    </span>
                                    <!-- Comments Count Bubble -->
                                    <div class="opportunity-comments"
                                        data-wp-bind--hidden="!context.item.commentsCount">
                                        <span class="dashicons1 dashicons-admin-comments1">Comments:</span>
                                        <span class="comments-number" data-wp-text="context.item.commentsCount"></span>
                                    </div>
                                </div>
                                <!-- Category Label -->
                                <div class="opportunity-taxonomy"
                                    data-wp-text="context.item.categoryName"
                                    data-wp-bind--hidden="!context.item.categoryName">
                                </div>
                                <!-- Opportunity Info -->
                                <div class="opportunity-info">
                                    <div class="opportunity-range">
                                        <span class="opportunity-range__title">Lasts</span>
                                        <span data-wp-text="context.item.dateStarts"></span>
                                        <span class="opportunity-range__icon dashicons dashicons-calendar-alt"></span>
                                        <!-- <span class="range-sep">—</span> -->
                                        <span data-wp-text="context.item.dateEnds"></span>

                                        <!-- Expired Label -->
                                        <span
                                            class="status-badge status-badge--expired"
                                            data-wp-bind--hidden="!context.item.isExpired">
                                            <?php esc_html_e('Finished', 'starwishx'); ?>
                                        </span>
                                    </div>
                                </div>
                                <!-- The Excerpt Text -->
                                <p class="opportunity-excerpt"
                                    data-wp-text="context.item.excerpt"
                                    data-wp-bind--hidden="!context.item.excerpt"></p>
                            </div>
                            <div class="opportunity-actions">
                                <!-- <a class="btn-secondary__small btn-opportunity__view" data-wp-bind--href="context.item.viewUrl" target="_blank">
                                    < ?php esc_html_e('View', 'starwishx'); ? >
                                </a> -->
                                <!-- QUICK SUBMIT BUTTON -->
                                <button class="btn-secondary__small btn-opportunity__review"
                                    data-wp-on--click="actions.opportunities.quickSubmit"
                                    data-wp-bind--disabled="!state.canEdit"
                                    data-wp-bind--hidden="state.isPublish"
                                    data-wp-bind--data-status="context.item.status">
                                    <span><?php esc_html_e('Submit for Review', 'starwishx'); ?></span>
                                </button>
                                <!-- data-wp-bind--hidden="!state.canEdit" -->
                                <button class="btn-secondary__small btn-opportunity__edit"
                                    data-wp-on--click="actions.opportunities.openEdit"
                                    data-wp-bind--data-id="context.item.id"
                                    data-wp-bind--disabled="!state.canEdit"
                                    data-wp-bind--hidden="state.isPublish"
                                    data-wp-bind--data-status="context.item.status">
                                    <span><?php esc_html_e('Edit', 'starwishx'); ?></span>
                                </button>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="opportunities-pagination" data-wp-bind--hidden="!<?= $this->statePath('hasMore') ?>">
                    <button class="btn-tertiary"
                        data-wp-on--click="actions.opportunities.loadMore"
                        data-wp-bind--disabled="<?= $isLoadingPath ?>">
                        <?php esc_html_e('Load More', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <!-- VIEW: FORM (Shared for Add & Edit) -->
            <div class="launchpad-form__container"
                <?php echo !$isFormView ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isOppFormVisible"
                data-wp-bind--data-is-loading="<?= $isLoadingPath ?>">
                <!-- Bind loading state ☝🏻 to a data attribute for CSS targeting -->

                <div class="launchpad-form-header" data-wp-bind--data-status="state.panels.opportunities.formData.status">
                    <h2 data-wp-text="state.opportunityFormHeaders"></h2>
                    <button class="btn-secondary__small" type="button" data-wp-on--click="actions.opportunities.cancel">
                        <svg class="btn-secondary__small--icon arrow-left">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                        </svg>
                        <?php esc_html_e('Back to list', 'starwishx'); ?>
                    </button>
                </div>

                <form class="launchpad-form" data-wp-on--submit="actions.opportunities.save">

                    <!-- Main Title -->
                    <div class="form-field form-field__title">
                        <label><?php esc_html_e('Opportunity Title', 'starwishx'); ?></label>
                        <input type="text" required class="large-text"
                            data-wp-bind--value="<?= $formPath ?>.title"
                            data-wp-on--input="actions.opportunities.updateForm"
                            data-wp-bind--disabled="<?= $isLoadingPath ?>"
                            data-field="title">
                    </div>

                    <!-- 3 Column Layout -->
                    <div class="">

                        <!-- GROUP 1: Applicant -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php esc_html_e('Applicant Info', 'starwishx'); ?></h3>
                            <div class="form-card-data launchpad-grid-3-col">
                                <div class="form-field">
                                    <label><?php esc_html_e('Name', 'starwishx'); ?></label>
                                    <input type="text" required data-wp-bind--value="<?= $formPath ?>.applicant_name" data-wp-on--input="actions.opportunities.updateForm" data-field="applicant_name">
                                </div>
                                <div class="form-field">
                                    <label><?php esc_html_e('Email', 'starwishx'); ?></label>
                                    <input type="email" required data-wp-bind--value="<?= $formPath ?>.applicant_mail" data-wp-on--input="actions.opportunities.updateForm" data-field="applicant_mail">
                                </div>
                                <div class="form-field">
                                    <label><?php esc_html_e('Phone', 'starwishx'); ?></label>
                                    <input type="text" required data-wp-bind--value="<?= $formPath ?>.applicant_phone" data-wp-on--input="actions.opportunities.updateForm" data-field="applicant_phone">
                                </div>
                            </div>
                        </div>

                        <!-- GROUP 2: Info -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php esc_html_e('Opportunity Info', 'starwishx'); ?></h3>
                            <div class="form-card-data ">
                                <div class="launchpad-grid-auto">
                                    <div class="form-field">
                                        <label><?php esc_html_e('Company', 'starwishx'); ?></label>
                                        <input type="text" required data-wp-bind--value="<?= $formPath ?>.company" data-wp-on--input="actions.opportunities.updateForm" data-field="company">
                                    </div>

                                    <div class="form-row">
                                        <div class="form-field">
                                            <label><?php esc_html_e('Start Date', 'starwishx'); ?></label>
                                            <input type="date" required data-wp-bind--value="<?= $formPath ?>.date_starts" data-wp-on--input="actions.opportunities.updateForm" data-field="date_starts">
                                        </div>
                                        <div class="form-field">
                                            <label><?php esc_html_e('End Date', 'starwishx'); ?></label>
                                            <input type="date" required data-wp-bind--value="<?= $formPath ?>.date_ends" data-wp-on--input="actions.opportunities.updateForm" data-field="date_ends">
                                        </div>
                                    </div>

                                    <div class="form-field">
                                        <label><?php esc_html_e('Country', 'starwishx'); ?></label>
                                        <select required data-wp-bind--value="<?= $formPath ?>.country" data-wp-on--change="actions.opportunities.updateForm" data-field="country">
                                            <option value=""><?php esc_html_e('Select Country', 'starwishx'); ?></option>
                                            <template data-wp-each="<?= $optPath ?>.countries">
                                                <option data-wp-bind--value="context.item.id" data-wp-text="context.item.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                <div class="launchpad-grid-auto">
                                    <div class="form-field">
                                        <label><?php esc_html_e('City', 'starwishx'); ?></label>
                                        <input type="text" required data-wp-bind--value="<?= $formPath ?>.city" data-wp-on--input="actions.opportunities.updateForm" data-field="city">
                                    </div>

                                    <div class="form-field">
                                        <label><?php esc_html_e('Category', 'starwishx'); ?></label>
                                        <select required data-wp-bind--value="<?= $formPath ?>.category" data-wp-on--change="actions.opportunities.updateForm" data-field="category"
                                            class="form-select-cetegory">
                                            <option value=""><?php esc_html_e('Select Category', 'starwishx'); ?></option>
                                            <template data-wp-each="<?= $optPath ?>.categories">
                                                <option data-wp-bind--value="context.item.id" data-wp-text="context.item.name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div class="form-field">
                                        <label><?php esc_html_e('Link', 'starwishx'); ?></label>
                                        <input type="url" required data-wp-bind--value="<?= $formPath ?>.sourcelink" data-wp-on--input="actions.opportunities.updateForm" data-field="sourcelink">
                                    </div>
                                </div>

                                <!-- Seekers (Checkboxes)
                                 style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;"
                                  -->
                                <div class="form-field">
                                    <label><?php esc_html_e('Seekers', 'starwishx'); ?></label>
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
                                </div>
                            </div>
                        </div>

                        <!-- GROUP 3: Description -->
                        <div class="form-group-card placeholder-box">
                            <h3 class="group-card-title"><?php esc_html_e('Description', 'starwishx'); ?></h3>
                            <div class="form-card-data">
                                <div class="form-field form-field-description">
                                    <label><?php esc_html_e('Main Description', 'starwishx'); ?></label>
                                    <textarea rows="6" required class="widefat" data-wp-bind--value="<?= $formPath ?>.description" data-wp-on--input="actions.opportunities.updateForm" data-field="description"></textarea>
                                </div>
                                <div class="form-field form-field-description">
                                    <label><?php esc_html_e('Requirements', 'starwishx'); ?></label>
                                    <textarea rows="6" required class="widefat" data-wp-bind--value="<?= $formPath ?>.requirements" data-wp-on--input="actions.opportunities.updateForm" data-field="requirements"></textarea>
                                </div>
                                <div class="form-field form-field-description">
                                    <label><?php esc_html_e('Details', 'starwishx'); ?></label>
                                    <textarea rows="4" class="widefat" data-wp-bind--value="<?= $formPath ?>.details" data-wp-on--input="actions.opportunities.updateForm" data-field="details"></textarea>
                                </div>
                            </div>
                        </div>

                    </div> <!-- End Grid -->

                    <div class="form-actions">
                        <!-- STANDARD SAVE (Preserves current status or Draft) -->
                        <!-- data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>" -->
                        <button type="submit" class="btn__small"
                            data-wp-bind--disabled="state.panels.opportunities.isSaving"
                            data-wp-bind--hidden="!state.canEdit">
                            <?php esc_html_e('Save Draft', 'starwishx'); ?>
                        </button>

                        <!-- WORKFLOW ACTION: SUBMIT FOR REVIEW -->
                        <!-- Only show if status is not already 'pending' or 'publish' -->
                        <!-- data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>" -->
                        <button
                            type="button"
                            class="btn-secondary__small launchpad-form__btn--review"
                            data-wp-on--click="actions.opportunities.submitForReview"
                            data-wp-bind--disabled="state.panels.opportunities.isSaving"
                            data-wp-bind--hidden="!state.canEdit">
                            <?php esc_html_e('Submit for Review', 'starwishx'); ?>
                        </button>

                        <button class="btn-secondary__small" type="button" data-wp-on--click="actions.opportunities.cancel">
                            <svg class="btn-secondary__small--icon arrow-left">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                            </svg>
                            <?php esc_html_e('Back to list', 'starwishx'); ?>
                        </button>
                        <!-- indicator for non-editable items -->
                        <!-- <div class="status-alert" data-wp-bind--hidden="state.canEdit">
                            <span class="dashicons dashicons-lock"></span>
                            < ?php esc_html_e('This item is currently under review and cannot be modified.', 'starwishx'); ? >
                        </div> -->
                    </div>
                </form>
            </div>
        </div>
<?php
        return $this->endBuffer();
    }
}

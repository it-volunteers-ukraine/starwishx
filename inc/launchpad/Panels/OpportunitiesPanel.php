<?php
// File: inc/launchpad/Panels/OpportunitiesPanel.php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\OpportunitiesService;

class OpportunitiesPanel extends AbstractPanel
{

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

    public function getInitialState(int $userId): array
    {
        $service = new OpportunitiesService();

        // 1. Fetch Taxonomy Options for Dropdowns
        $options = $service->getFormOptions();

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
            $fetchedData = $service->getSingleOpportunity($userId, $editId);
            if ($fetchedData) {
                $currentFormData = $fetchedData;
            } else {
                $error = __('Opportunity not found or access denied.', 'starwishx');
                $view = 'list'; // Fallback
            }
        }

        // 4. Fetch List Items
        $opportunities = $service->getUserOpportunities($userId);
        $total = $service->countUserOpportunities($userId);

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
        ];
    }

    public function render(): string
    {
        $this->startBuffer();

        // Simplify paths for cleaner template
        $formPath = "state.panels.opportunities.formData";
        $optPath  = "state.panels.opportunities.options";
        $isLoadingPath = $this->statePath('isLoading');
?>
        <div class="launchpad-panel launchpad-panel--opportunities">

            <!-- VIEW: LIST -->
            <div class="launchpad-grid__container" data-wp-bind--hidden="!state.isOppListVisible">

                <div class="launchpad-header-actions" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h2 class="panel-title" style="margin:0;"><?php esc_html_e('Opportunities List', 'starwishx'); ?></h2>
                    <button class="btn-secondary" data-wp-on--click="actions.opportunities.openAdd">
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
                <div class="opportunities-grid placeholder-box" data-wp-bind--hidden="!state.showOppGrid">
                    <template data-wp-each="<?= $this->statePath('items') ?>">
                        <article class="opportunity-card">
                            <div class="opportunity-content">

                                <h3 class="opportunity-title" data-wp-text="context.item.title"></h3>
                                <div class="opportunity-meta">
                                    <span class="opportunity-date" data-wp-text="context.item.date"></span>
                                    <span data-wp-bind--class="context.item.status" data-wp-text="context.item.status"></span>
                                </div>
                            </div>
                            <div class="opportunity-actions">
                                <button class="btn-secondary__small"
                                    data-wp-on--click="actions.opportunities.openEdit"
                                    data-wp-bind--data-id="context.item.id"
                                    data-wp-bind--hidden="!context.item.editUrl">
                                    <svg class="btn-secondary__small--icon write">
                                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-write"></use>
                                    </svg>
                                    <span><?php esc_html_e('Edit', 'starwishx'); ?></span>

                                </button>
                                <a class="btn-secondary__small" data-wp-bind--href="context.item.viewUrl" target="_blank">
                                    <?php esc_html_e('View', 'starwishx'); ?>
                                </a>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="opportunities-pagination" data-wp-bind--hidden="!<?= $this->statePath('hasMore') ?>">
                    <button class="btn button"
                        data-wp-on--click="actions.opportunities.loadMore"
                        data-wp-bind--disabled="<?= $isLoadingPath ?>">
                        <?php esc_html_e('Load More', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <!-- VIEW: FORM (Shared for Add & Edit) -->
            <div class="launchpad-form__container" data-wp-bind--hidden="!state.isOppFormVisible">
                <div class="launchpad-form-header">
                    <button class="btn-secondary__small" type="button" data-wp-on--click="actions.opportunities.cancel">
                        <svg class="btn-secondary__small--icon arrow-left">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                        </svg>
                        <?php esc_html_e('Back to list', 'starwishx'); ?>
                    </button>
                    <h2 data-wp-text="state.panels.opportunities.currentView === 'add' ? '<?php esc_html_e('New Opportunity', 'starwishx'); ?>' : '<?php esc_html_e('Edit Opportunity', 'starwishx'); ?>'"></h2>
                </div>

                <form class="launchpad-form" data-wp-on--submit="actions.opportunities.save">

                    <!-- Main Title -->
                    <div class="form-field form-field__title">
                        <label><?php esc_html_e('Opportunity Title', 'starwishx'); ?></label>
                        <input type="text" required class="large-text"
                            data-wp-bind--value="<?= $formPath ?>.title"
                            data-wp-on--input="actions.opportunities.updateForm"
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
                                            <label style="display:block; margin-bottom:5px;">
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

                    <div class="form-actions" style="margin-top: 2rem;">
                        <button type="submit" class="btn button button-primary" data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                            <span data-wp-bind--hidden="<?= $this->statePath('isSaving') ?>">
                                <?php esc_html_e('Save Opportunity', 'starwishx'); ?>
                            </span>
                            <span data-wp-bind--hidden="!<?= $this->statePath('isSaving') ?>">
                                <?php esc_html_e('Saving...', 'starwishx'); ?>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
<?php
        return $this->endBuffer();
    }
}

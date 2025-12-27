<?php
// File: inc\launchpad\Panels\ProfilePanel.php

declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\ProfileService;

class ProfilePanel extends AbstractPanel
{

    public function getId(): string
    {
        return 'profile';
    }

    public function getLabel(): string
    {
        return __('Profile', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'admin-users';
    }

    public function getInitialState(int $userId): array
    {
        $service = new ProfileService();
        $data = $service->getProfileData($userId);

        // Merge service data with UI state
        return array_merge($data, [
            'isEditing' => false,
            // 'error' handled by defaults
        ]);
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <div class="launchpad-panel launchpad-panel--profile">
            <h2 class="panel-title"><?php esc_html_e('Profile Information', 'starwishx'); ?></h2>

            <div
                class="launchpad-alert launchpad-alert--error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>

            <!-- View Mode -->
            <div class="profile-card placeholder-box"
                data-wp-bind--hidden="<?= $this->statePath('isEditing') ?>">
                <img
                    class="profile-avatar"
                    data-wp-bind--src="<?= $this->statePath('avatarUrl') ?>"
                    alt="" />

                <h2 class="profile-name">
                    <span data-wp-text="<?= $this->statePath('firstName') ?>"></span>
                    <span data-wp-text="<?= $this->statePath('lastName') ?>"></span>
                </h2>

                <p class="profile-email" data-wp-text="<?= $this->statePath('email') ?>"></p>

                <!-- Profile Meta -->
                <div class="profile-meta">
                    <div data-wp-bind--hidden="!<?= $this->statePath('phone') ?>">
                        <strong>Phone:</strong> <span data-wp-text="<?= $this->statePath('phone') ?>"></span>
                    </div>
                    <div data-wp-bind--hidden="!<?= $this->statePath('telegram') ?>">
                        <strong>Telegram:</strong> <span data-wp-text="<?= $this->statePath('telegram') ?>"></span>
                    </div>
                </div>

                <button
                    class="btn button button-primary"
                    data-wp-on--click="actions.profile.startEdit">
                    <?php esc_html_e('Edit Profile', 'starwishx'); ?>
                </button>
            </div>

            <!-- Edit Mode -->
            <form
                class="profile-form placeholder-box"
                data-wp-bind--hidden="!<?= $this->statePath('isEditing') ?>"
                data-wp-on--submit="actions.profile.save">

                <div class="form-field">
                    <label for="lp-first-name"><?php esc_html_e('First Name', 'starwishx'); ?></label>
                    <input type="text" id="lp-first-name" data-field="firstName"
                        data-wp-bind--value="<?= $this->statePath('firstName') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-last-name"><?php esc_html_e('Last Name', 'starwishx'); ?></label>
                    <input type="text" id="lp-last-name" data-field="lastName"
                        data-wp-bind--value="<?= $this->statePath('lastName') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-email"><?php esc_html_e('Email', 'starwishx'); ?></label>
                    <input type="email" id="lp-email" data-field="email"
                        data-wp-bind--value="<?= $this->statePath('email') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <!-- NEW FIELDS -->
                <div class="form-field">
                    <label for="lp-phone">
                        <?php esc_html_e('Phone', 'starwishx'); ?>
                        <span class="description" style="color:#666; font-size:0.85em; font-weight:normal;">
                            (e.g., +38 044 555 5555)
                        </span>
                    </label>
                    <input type="text" id="lp-phone" data-field="phone"
                        data-wp-bind--value="<?= $this->statePath('phone') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-telegram"><?php esc_html_e('Telegram', 'starwishx'); ?></label>
                    <input type="text" id="lp-telegram" data-field="telegram"
                        data-wp-bind--value="<?= $this->statePath('telegram') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn button button-primary"
                        data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                        <span data-wp-bind--hidden="<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Save', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Saving...', 'starwishx'); ?>
                        </span>
                    </button>
                    <button type="button" class="btn button"
                        data-wp-on--click="actions.profile.cancelEdit">
                        <?php esc_html_e('Cancel', 'starwishx'); ?>
                    </button>
                </div>
            </form>
        </div>
<?php
        return $this->endBuffer();
    }
}

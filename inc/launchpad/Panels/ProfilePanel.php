<?php

declare(strict_types=1);

namespace Launchpad\Panels;

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
        $user = get_userdata($userId);

        return [
            'firstName'  => $user->first_name ?? '',
            'lastName'   => $user->last_name ?? '',
            'email'      => $user->user_email ?? '',
            'avatarUrl'  => get_avatar_url($userId, ['size' => 150]),
            'isEditing'  => false,
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <div class="launchpad-panel launchpad-panel--profile">
            <!-- Error Alert -->
            <div
                class="launchpad-alert launchpad-alert--error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>

            <!-- View Mode -->
            <div
                class="profile-card placeholder-box"
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
                    <input
                        type="text"
                        id="lp-first-name"
                        name="first_name"
                        data-wp-bind--value="<?= $this->statePath('firstName') ?>"
                        data-wp-on--input="actions.profile.updateField"
                        data-field="firstName" />
                </div>

                <div class="form-field">
                    <label for="lp-last-name"><?php esc_html_e('Last Name', 'starwishx'); ?></label>
                    <input
                        type="text"
                        id="lp-last-name"
                        name="last_name"
                        data-wp-bind--value="<?= $this->statePath('lastName') ?>"
                        data-wp-on--input="actions.profile.updateField"
                        data-field="lastName" />
                </div>

                <div class="form-field">
                    <label for="lp-email"><?php esc_html_e('Email', 'starwishx'); ?></label>
                    <input
                        type="email"
                        id="lp-email"
                        name="email"
                        data-wp-bind--value="<?= $this->statePath('email') ?>"
                        data-wp-on--input="actions.profile.updateField"
                        data-field="email" />
                </div>

                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn button button-primary"
                        data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                        <span data-wp-bind--hidden="<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Save', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Saving...', 'starwishx'); ?>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="btn button"
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

<?php
// File: inc\launchpad\Panels\ProfilePanel.php
declare(strict_types=1);

namespace Launchpad\Panels;

use Launchpad\Services\ProfileService;
use Shared\Policy\PasswordPolicy;

class ProfilePanel extends AbstractPanel
{
    private ProfileService $service;

    public function __construct(ProfileService $service)
    {
        $this->service = $service;
    }

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
        return 'icon-settings';
    }

    public function getInitialState(?int $userId = null): array
    {

        // Safety check: Launchpad panels should always have a user, 
        // but we must handle the nullable parameter to satisfy PHP.
        if (!$userId) {
            return [];
        }

        $data = $this->service->getProfileData($userId);

        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';

        return array_merge($data, [
            'isEditing'          => ($view === 'profile'),
            'isChangingPassword' => ($view === 'password'),
            'passwordData'       => [
                'current' => '',
                'new'     => '',
            ],
            'isCurrentPasswordVisible' => false,
            'isNewPasswordVisible'     => false,
            'isGenerating'             => false,
            'passwordSuccessPopup'     => ['isOpen' => false],
            'deletePopup'              => [
                'isOpen'            => false,
                'password'          => '',
                'isPasswordVisible' => false,
                'isDeleting'        => false,
                'error'             => null,
            ],
            'deleteSuccessPopup'       => ['isOpen' => false],
            'isFormExpanded'           => false,
        ]);
    }

    public function render(): string
    {
        // Determine the view on the server so we can set 'hidden' attributes
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';
        $isCardVisible  = ($view !== 'profile' && $view !== 'password');
        $isProfileForm  = ($view === 'profile');
        $isPasswordForm = ($view === 'password');

        $this->startBuffer();

        $statePath = "state.panels.profile";
?>
        <div class="launchpad-panel launchpad-panel--profile">
            <hgroup>
                <h2 class="panel-title"><?= esc_html__('Profile Information', 'starwishx'); ?></h2>
                <p class="panel-description"><?= esc_html__('View/edit personal information, change password', 'starwishx'); ?></p>
            </hgroup>
            <div
                class="launchpad-alert launchpad-alert--error label-info exclamation-circle__error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>
            <!-- View Mode: Visible by default, so no 'hidden' attribute needed here -->
            <div class="profile-card placeholder-box"
                <?= !$isCardVisible ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isProfileCardVisible">

                <img
                    class="profile-avatar"
                    data-wp-bind--src="<?= $this->statePath('avatarUrl') ?>"
                    alt="Your avatar" />
                <div class="profile-info">
                    <h2 class="profile-name">
                        <!-- <span data-wp-text="< ?= $this->statePath('firstName') ?>"></span>
                        <span data-wp-text="< ?= $this->statePath('firstName') ?>"></span> -->
                        <span data-wp-text="<?= $this->statePath('displayName') ?>"></span>
                    </h2>


                    <!-- Profile Meta -->
                    <div class="profile-meta">
                        <p class="profile-email" data-wp-text="<?= $this->statePath('email') ?>"></p>
                        <p class="profile-login" data-wp-text="<?= $this->statePath('userLogin') ?>"></p>
                        <div class="profile-role">
                            <span class="status-badge"
                                data-wp-bind--data-role="<?= $this->statePath('role') ?>"
                                data-wp-text="<?= $this->statePath('roleLabel') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('phone') ?>">
                            <strong><?= esc_html__('Phone', 'starwishx'); ?>:</strong> <span data-wp-text="<?= $this->statePath('phone') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('telegram') ?>">
                            <strong><?= esc_html__('Telegram', 'starwishx'); ?>:</strong> <span data-wp-text="<?= $this->statePath('telegram') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('organization') ?>">
                            <strong><?= esc_html__('Organization', 'starwishx'); ?>:</strong> <span data-wp-text="<?= $this->statePath('organization') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('userUrl') ?>">
                            <strong><?= esc_html__('Website', 'starwishx'); ?>:</strong> <span data-wp-text="<?= $this->statePath('userUrl') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('description') ?>">
                            <strong><?= esc_html__('Bio', 'starwishx'); ?>:</strong> <span data-wp-text="<?= $this->statePath('description') ?>"></span>
                        </div>
                    </div>
                </div>
                <div class="profile-card__controls">
                    <button
                        class="btn__small"
                        data-wp-on--click="actions.profile.startEdit">
                        <?= esc_html__('Edit Profile', 'starwishx'); ?>
                    </button>
                    <button class="btn-secondary__small"
                        data-wp-on--click="actions.profile.startChangePassword">
                        <?= esc_html__('Change Password', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <!-- VIEW 2: EDIT PROFILE MODE -->
            <form
                class="profile-form placeholder-box launchpad-form__container"
                <?= !$isProfileForm ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!<?= $this->statePath('isEditing') ?>"
                data-wp-on--submit="actions.profile.save"
                data-wp-init="actions.profile.initPhoneWidget">
                <div class="form-group-card1 launchpad-grid-auto">

                    <div class="form-field">
                        <label class="label-required" for="lp-first-name"><?= esc_html__('First Name', 'starwishx'); ?></label>
                        <input type="text" id="lp-first-name" data-field="firstName"
                            data-wp-bind--value="<?= $this->statePath('firstName') ?>"
                            data-wp-on--input="actions.profile.updateField" />
                    </div>
                    <!-- Phone field: intlTelInput widget owns this input -->
                    <div class="form-field form-field--phone">
                        <label for="lp-phone" class="label-required">
                            <?= esc_html__('Phone', 'starwishx'); ?>
                        </label>
                        <input type="tel" id="lp-phone" />
                    </div>
                    <div class="form-field">
                        <label class="label-required" for="lp-email"><?= esc_html__('Email', 'starwishx'); ?></label>
                        <input type="email" id="lp-email" required data-field="email"
                            data-wp-bind--value="<?= $this->statePath('email') ?>"
                            data-wp-on--input="actions.profile.updateField" />
                    </div>
                </div>
                <div class="show-more form-group-card">
                    <button type="button" class="btn-tertiary"
                        data-wp-on--click="actions.profile.toggleFormExpanded"
                        data-wp-bind--aria-expanded="<?= $this->statePath('isFormExpanded') ?>">
                        <span data-wp-bind--hidden="<?= $this->statePath('isFormExpanded') ?>">
                            <?= esc_html__('Show More', 'starwishx'); ?>
                        </span>
                        <span hidden data-wp-bind--hidden="!<?= $this->statePath('isFormExpanded') ?>">
                            <?= esc_html__('Show Less', 'starwishx'); ?>
                        </span>
                    </button>
                </div>
                <div class="reveal-container"
                    hidden
                    data-wp-bind--hidden="!<?= $this->statePath('isFormExpanded') ?>">
                    <div class="form-group-card1 launchpad-grid-auto">
                        <div class="form-field">
                            <label for="lp-display-name"><?= esc_html__('Display name publicly as', 'starwishx'); ?></label>
                            <select id="lp-display-name" data-field="displayName"
                                data-wp-bind--value="<?= $this->statePath('displayName') ?>"
                                data-wp-on--change="actions.profile.updateField">
                                <template data-wp-each="<?= $this->statePath('displayNameOptions') ?>">
                                    <option data-wp-bind--value="context.item" data-wp-text="context.item"></option>
                                </template>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="lp-last-name"><?= esc_html__('Last Name', 'starwishx'); ?></label>
                            <input type="text" id="lp-last-name" data-field="lastName"
                                data-wp-bind--value="<?= $this->statePath('lastName') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>
                        <!-- // temporary hidden -->
                        <!-- <div class="form-field">
                            <label for="lp-nickname">< ?= esc_html__('Nickname', 'starwishx'); ?></label>
                            <input type="text" id="lp-nickname" data-field="nickname"
                                data-wp-bind--value="< ?= $this->statePath('nickname') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div> -->
                    </div>

                    <div class="launchpad-grid-auto">
                        <div class="form-field">
                            <label for="lp-telegram"><?= esc_html__('Telegram', 'starwishx'); ?></label>
                            <input type="text" id="lp-telegram" data-field="telegram"
                                data-wp-bind--value="<?= $this->statePath('telegram') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>

                        <div class="form-field">
                            <label for="lp-organization"><?= esc_html__('Organization', 'starwishx'); ?></label>
                            <input type="text" id="lp-organization" data-field="organization"
                                data-wp-bind--value="<?= $this->statePath('organization') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>
                        <div class="form-field">
                            <label for="lp-user-url"><?= esc_html__('Website', 'starwishx'); ?></label>
                            <input type="url" id="lp-user-url" data-field="userUrl"
                                data-wp-bind--value="<?= $this->statePath('userUrl') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>

                    </div>
                    <!-- <div class="launchpad-grid-auto"> -->
                    <div class="form-field">
                        <label for="lp-description"><?= esc_html__('Biographical Info', 'starwishx'); ?></label>
                        <textarea id="lp-description" data-field="description" rows="4"
                            data-wp-bind--value="<?= $this->statePath('description') ?>"
                            data-wp-on--input="actions.profile.updateField"></textarea>
                    </div>
                    <!-- </div>
                    <div class="launchpad-grid-auto"> -->
                    <div class="form-actions form-actions--delete">
                        <button type="button" class="btn-tertiary btn-profile-delete btn-secondary__small" data-wp-on--click="actions.profile.deleteProfile">
                            <!-- < ?= sw_svg_e('icon-close') ?> -->
                            <?= esc_html__('Delete profile', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
                <!-- </div> -->

                <div class="form-field form-field--checkbox field-notifications">
                    <label for="lp-receive-notifications">
                        <input type="checkbox" id="lp-receive-notifications"
                            data-field="receiveMailNotifications"
                            data-wp-bind--checked="<?= $this->statePath('receiveMailNotifications') ?>"
                            data-wp-on--change="actions.profile.updateField" />
                        <?= esc_html__('Receive email notifications', 'starwishx'); ?>
                    </label>
                    <p class="form-field__hint">
                        <?= esc_html__('Get notified by email when someone comments on your content.', 'starwishx'); ?>
                    </p>
                </div>

                <div class="form-field">
                    <label for="lp-nickname"><?= esc_html__('Nickname', 'starwishx'); ?></label>
                    <input type="text" id="lp-nickname" data-field="nickname"
                        data-wp-bind--value="<?= $this->statePath('nickname') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-user-url"><?= esc_html__('Website', 'starwishx'); ?></label>
                    <input type="url" id="lp-user-url" data-field="userUrl"
                        data-wp-bind--value="<?= $this->statePath('userUrl') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-description"><?= esc_html__('Biographical Info', 'starwishx'); ?></label>
                    <textarea id="lp-description" data-field="description" rows="4"
                        data-wp-bind--value="<?= $this->statePath('description') ?>"
                        data-wp-on--input="actions.profile.updateField"></textarea>
                </div>

                <div class="form-field form-field--checkbox">
                    <label for="lp-receive-notifications">
                        <input type="checkbox" id="lp-receive-notifications"
                            data-field="receiveMailNotifications"
                            data-wp-bind--checked="<?= $this->statePath('receiveMailNotifications') ?>"
                            data-wp-on--change="actions.profile.updateField" />
                        <?= esc_html__('Receive email notifications', 'starwishx'); ?>
                    </label>
                    <p class="form-field__hint">
                        <?= esc_html__('Get notified by email when someone comments on your content.', 'starwishx'); ?>
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn__small"
                        data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                        <span data-wp-bind--hidden="<?= $this->statePath('isSaving') ?>">
                            <?= esc_html__('Save', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $this->statePath('isSaving') ?>">
                            <?= esc_html__('Saving...', 'starwishx'); ?>
                        </span>
                    </button>
                    <button type="button" class="btn-secondary__small"
                        data-wp-on--click="actions.profile.cancelEdit">
                        <?= esc_html__('Cancel', 'starwishx'); ?>
                    </button>
                </div>
            </form>
            <!-- 
                < ?php echo !$isEditingInitial ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!< ?= $this->statePath('isEditing') ?>"
                data-wp-on--submit="actions.profile.save"> -->
            <!-- VIEW 3: CHANGE PASSWORD MODE -->
            <form class="password-form placeholder-box"
                <?= !$isPasswordForm ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!<?= $this->statePath('isChangingPassword') ?>"
                data-wp-on--submit="actions.profile.submitPasswordChange">

                <h3><?= esc_html__('Update Security Credentials', 'starwishx'); ?></h3>

                <div class="form-field">
                    <label><?= esc_html__('Current Password', 'starwishx'); ?></label>
                    <div class="gateway-password-group">
                        <input type="password" required
                            data-wp-bind--type="state.currentPasswordInputType"
                            data-wp-bind--value="<?= $statePath ?>.passwordData.current"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="current" />
                        <button type="button" class="btn-hide-pw"
                            data-wp-on--click="actions.profile.toggleCurrentPasswordVisibility"
                            aria-label="<?= esc_attr__('Toggle password visibility', 'starwishx'); ?>">
                            <span data-wp-bind--hidden="<?= $statePath ?>.isCurrentPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                </svg>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.isCurrentPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="form-field">
                    <label><?= esc_html__('New Password', 'starwishx'); ?></label>
                    <div class="gateway-password-group">
                        <input type="password" required
                            minlength="<?= PasswordPolicy::MIN_LENGTH ?>"
                            data-wp-bind--type="state.newPasswordInputType"
                            data-wp-bind--value="<?= $statePath ?>.passwordData.new"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="new" />
                        <button type="button" class="btn-hide-pw"
                            data-wp-on--click="actions.profile.toggleNewPasswordVisibility"
                            aria-label="<?= esc_attr__('Toggle password visibility', 'starwishx'); ?>">
                            <span data-wp-bind--hidden="<?= $statePath ?>.isNewPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                </svg>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.isNewPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                </svg>
                            </span>
                        </button>
                    </div>
                    <button type="button" class="btn-secondary__small"
                        data-wp-on--click="actions.profile.generatePassword"
                        data-wp-bind--disabled="<?= $statePath ?>.isGenerating">
                        <span data-wp-bind--hidden="<?= $statePath ?>.isGenerating">
                            <?= esc_html__('Generate Strong Password', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $statePath ?>.isGenerating">
                            <?= esc_html__('Generating...', 'starwishx'); ?>
                        </span>
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn__small">
                        <?= esc_html__('Update Password', 'starwishx'); ?>
                    </button>
                    <button type="button" class="btn-secondary__small" data-wp-on--click="actions.profile.cancelPasswordChange">
                        <?= esc_html__('Cancel', 'starwishx'); ?>
                    </button>
                </div>
            </form>

            <!-- Password Changed Success Popup -->
            <div class="popup"
                hidden
                data-wp-bind--hidden="!<?= $statePath ?>.passwordSuccessPopup.isOpen">

                <div class="popup__backdrop"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="password-success-title">
                    <div class="popup__body">
                        <h2 id="password-success-title" class="popup__title">
                            <?= esc_html__('Password Changed', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?= esc_html__('Your password has been changed successfully. You will need to log in again with your new password.', 'starwishx'); ?>
                        </p>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn popup__footer--button"
                            data-wp-on--click="actions.profile.confirmPasswordSuccess">
                            <?= esc_html__('Log In', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete Account Confirmation Popup -->
            <div class="popup"
                hidden
                data-wp-bind--hidden="!<?= $statePath ?>.deletePopup.isOpen">

                <div class="popup__backdrop" data-wp-on--click="actions.profile.cancelDelete"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="delete-account-title">
                    <div class="popup__body gateway-form">
                        <h2 id="delete-account-title" class="popup__title">
                            <?= esc_html__('Delete Account', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?= esc_html__('This action is permanent and cannot be undone. All your personal data will be removed. Your published content will be preserved.', 'starwishx'); ?>
                        </p>

                        <div class="launchpad-alert launchpad-alert--error label-info exclamation-circle__error"
                            data-wp-bind--hidden="!<?= $statePath ?>.deletePopup.error"
                            data-wp-text="<?= $statePath ?>.deletePopup.error"></div>

                        <div class="form-field">
                            <label for="lp-delete-password">
                                <?= esc_html__('Enter your password to confirm', 'starwishx'); ?>
                            </label>
                            <div class="gateway-password-group">
                                <input type="password" id="lp-delete-password" required
                                    data-wp-bind--type="state.deletePasswordInputType"
                                    data-wp-bind--value="<?= $statePath ?>.deletePopup.password"
                                    data-wp-on--input="actions.profile.updateDeletePassword"
                                    data-field="password" />
                                <button type="button" class="btn-hide-pw"
                                    data-wp-on--click="actions.profile.toggleDeletePasswordVisibility"
                                    aria-label="<?= esc_attr__('Toggle password visibility', 'starwishx'); ?>">
                                    <span data-wp-bind--hidden="<?= $statePath ?>.deletePopup.isPasswordVisible">
                                        <svg width="23" height="23" class="btn-hide-pw__icon">
                                            <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                        </svg>
                                    </span>
                                    <span data-wp-bind--hidden="!<?= $statePath ?>.deletePopup.isPasswordVisible">
                                        <svg width="23" height="23" class="btn-hide-pw__icon">
                                            <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn btn--danger popup__footer--button"
                            data-wp-on--click="actions.profile.confirmDelete"
                            data-wp-bind--disabled="<?= $statePath ?>.deletePopup.isDeleting">
                            <span data-wp-bind--hidden="<?= $statePath ?>.deletePopup.isDeleting">
                                <?= esc_html__('Delete my account', 'starwishx'); ?>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.deletePopup.isDeleting">
                                <?= esc_html__('Deleting...', 'starwishx'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn-secondary popup__footer--button"
                            data-wp-on--click="actions.profile.cancelDelete">
                            <?= esc_html__('Cancel', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete Success Popup -->
            <div class="popup"
                hidden
                data-wp-bind--hidden="!<?= $statePath ?>.deleteSuccessPopup.isOpen">

                <div class="popup__backdrop"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="delete-success-title">
                    <div class="popup__body">
                        <h2 id="delete-success-title" class="popup__title">
                            <?= esc_html__('Account Deleted', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?= esc_html__('Your account has been successfully deleted. You will be redirected to the home page.', 'starwishx'); ?>
                        </p>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn popup__footer--button"
                            data-wp-on--click="actions.profile.confirmDeleteSuccess">
                            <?= esc_html__('OK', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
<?php
        return $this->endBuffer();
    }
}

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
                <h2 class="panel-title"><?php esc_html_e('Profile Information', 'starwishx'); ?></h2>
                <p class="panel-description"><?php esc_html_e('View/edit personal information, change password', 'starwishx'); ?></p>
            </hgroup>

            <div
                class="launchpad-alert launchpad-alert--error label-info exclamation-circle__error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>
            <!-- View Mode: Visible by default, so no 'hidden' attribute needed here -->
            <div class="profile-card placeholder-box"
                <?php echo !$isCardVisible ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!state.isProfileCardVisible">

                <img
                    class="profile-avatar"
                    data-wp-bind--src="<?= $this->statePath('avatarUrl') ?>"
                    alt="Your avatar" />
                <div class="profile-info">
                    <h2 class="profile-name">
                        <span data-wp-text="<?= $this->statePath('firstName') ?>"></span>
                        <span data-wp-text="<?= $this->statePath('lastName') ?>"></span>
                    </h2>


                    <!-- Profile Meta -->
                    <div class="profile-meta">
                        <p class="profile-email" data-wp-text="<?= $this->statePath('email') ?>"></p>
                        <div class="profile-role">
                            <span class="status-badge"
                                data-wp-bind--data-role="<?= $this->statePath('role') ?>"
                                data-wp-text="<?= $this->statePath('roleLabel') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('phone') ?>">
                            <strong>Phone:</strong> <span data-wp-text="<?= $this->statePath('phone') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('telegram') ?>">
                            <strong>Telegram:</strong> <span data-wp-text="<?= $this->statePath('telegram') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('organization') ?>">
                            <strong>Organization:</strong> <span data-wp-text="<?= $this->statePath('organization') ?>"></span>
                        </div>
                    </div>
                </div>
                <div class="profile-card__controls">
                    <button
                        class="btn__small"
                        data-wp-on--click="actions.profile.startEdit">
                        <?php esc_html_e('Edit Profile', 'starwishx'); ?>
                    </button>
                    <button class="btn-secondary__small"
                        data-wp-on--click="actions.profile.startChangePassword">
                        <?php esc_html_e('Change Password', 'starwishx'); ?>
                    </button>
                </div>
            </div>

            <!-- VIEW 2: EDIT PROFILE MODE -->
            <form
                class="profile-form placeholder-box"
                <?php echo !$isProfileForm ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!<?= $this->statePath('isEditing') ?>"
                data-wp-on--submit="actions.profile.save"
                data-wp-init="actions.profile.initPhoneWidget">

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
                    <label class="label-required" for="lp-email"><?php esc_html_e('Email', 'starwishx'); ?></label>
                    <input type="email" id="lp-email" required data-field="email"
                        data-wp-bind--value="<?= $this->statePath('email') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <!-- Phone field: intlTelInput widget owns this input -->
                <div class="form-field form-field--phone">
                    <label for="lp-phone" class="label-required">
                        <?php esc_html_e('Phone', 'starwishx'); ?>
                    </label>
                    <input type="tel" id="lp-phone" />
                </div>

                <div class="form-field">
                    <label for="lp-telegram"><?php esc_html_e('Telegram', 'starwishx'); ?></label>
                    <input type="text" id="lp-telegram" data-field="telegram"
                        data-wp-bind--value="<?= $this->statePath('telegram') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-field">
                    <label for="lp-organization"><?php esc_html_e('Organization', 'starwishx'); ?></label>
                    <input type="text" id="lp-organization" data-field="organization"
                        data-wp-bind--value="<?= $this->statePath('organization') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn__small"
                        data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                        <span data-wp-bind--hidden="<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Save', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $this->statePath('isSaving') ?>">
                            <?php esc_html_e('Saving...', 'starwishx'); ?>
                        </span>
                    </button>
                    <button type="button" class="btn-secondary__small"
                        data-wp-on--click="actions.profile.cancelEdit">
                        <?php esc_html_e('Cancel', 'starwishx'); ?>
                    </button>
                </div>
            </form>
            <!-- 
                < ?php echo !$isEditingInitial ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!< ?= $this->statePath('isEditing') ?>"
                data-wp-on--submit="actions.profile.save"> -->
            <!-- VIEW 3: CHANGE PASSWORD MODE -->
            <form class="password-form placeholder-box"
                <?php echo !$isPasswordForm ? 'hidden' : ''; ?>
                data-wp-bind--hidden="!<?= $this->statePath('isChangingPassword') ?>"
                data-wp-on--submit="actions.profile.submitPasswordChange">

                <h3><?php esc_html_e('Update Security Credentials', 'starwishx'); ?></h3>

                <div class="form-field">
                    <label><?php esc_html_e('Current Password', 'starwishx'); ?></label>
                    <div class="gateway-password-group">
                        <input type="password" required
                            data-wp-bind--type="state.currentPasswordInputType"
                            data-wp-bind--value="<?= $statePath ?>.passwordData.current"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="current" />
                        <button type="button" class="btn-hide-pw"
                            data-wp-on--click="actions.profile.toggleCurrentPasswordVisibility"
                            aria-label="<?php esc_attr_e('Toggle password visibility', 'starwishx'); ?>">
                            <span data-wp-bind--hidden="<?= $statePath ?>.isCurrentPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                </svg>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.isCurrentPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="form-field">
                    <label><?php esc_html_e('New Password', 'starwishx'); ?></label>
                    <div class="gateway-password-group">
                        <input type="password" required
                            minlength="<?php echo PasswordPolicy::MIN_LENGTH ?>"
                            data-wp-bind--type="state.newPasswordInputType"
                            data-wp-bind--value="<?= $statePath ?>.passwordData.new"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="new" />
                        <button type="button" class="btn-hide-pw"
                            data-wp-on--click="actions.profile.toggleNewPasswordVisibility"
                            aria-label="<?php esc_attr_e('Toggle password visibility', 'starwishx'); ?>">
                            <span data-wp-bind--hidden="<?= $statePath ?>.isNewPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                </svg>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.isNewPasswordVisible">
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                </svg>
                            </span>
                        </button>
                    </div>
                    <button type="button" class="btn-secondary__small"
                        data-wp-on--click="actions.profile.generatePassword"
                        data-wp-bind--disabled="<?= $statePath ?>.isGenerating">
                        <span data-wp-bind--hidden="<?= $statePath ?>.isGenerating">
                            <?php esc_html_e('Generate Strong Password', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!<?= $statePath ?>.isGenerating">
                            <?php esc_html_e('Generating...', 'starwishx'); ?>
                        </span>
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn__small">
                        <?php esc_html_e('Update Password', 'starwishx'); ?>
                    </button>
                    <button type="button" class="btn-secondary__small" data-wp-on--click="actions.profile.cancelPasswordChange">
                        <?php esc_html_e('Cancel', 'starwishx'); ?>
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
                            <?php esc_html_e('Password Changed', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?php esc_html_e('Your password has been changed successfully. You will need to log in again with your new password.', 'starwishx'); ?>
                        </p>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn popup__footer--button"
                            data-wp-on--click="actions.profile.confirmPasswordSuccess">
                            <?php esc_html_e('Log In', 'starwishx'); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
<?php
        return $this->endBuffer();
    }
}

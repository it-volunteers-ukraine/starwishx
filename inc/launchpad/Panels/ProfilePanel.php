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
            'isDisplayNameDropdownOpen' => false,
            'fieldErrors'        => (object) [],
            'nameLimits'         => [
                'min' => ProfileService::NAME_MIN_LENGTH,
                'max' => ProfileService::NAME_MAX_LENGTH,
            ],
            'validationMessages' => [
                'nameInvalid'   => __('Only letters, spaces, hyphens, and apostrophes allowed.', 'starwishx'),
                'nameMinLength' => sprintf(
                    __('Must be at least %d characters.', 'starwishx'),
                    ProfileService::NAME_MIN_LENGTH
                ),
                'nameMaxLength' => sprintf(
                    __('Must not exceed %d characters.', 'starwishx'),
                    ProfileService::NAME_MAX_LENGTH
                ),
            ],
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
            'emailPopup'               => [
                'isOpen'            => false,
                'newEmail'          => '',
                'password'          => '',
                'isPasswordVisible' => false,
                'isChanging'        => false,
                'error'             => null,
            ],
            'deleteSuccessPopup'       => ['isOpen' => false],
            'isFormExpanded'           => false,
            'revealedFields'           => [],
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

                    <!-- Profile Auth -->
                    <div class="profile-auth">
                        <div class="profile-email">
                            <strong><?= esc_html__('Email', 'starwishx'); ?>:</strong>
                            <span class="profile-email__info"
                                data-wp-context='{"field":"email"}'
                                data-wp-class--blurred="state.isFieldBlurred"
                                data-wp-on--click="actions.profile.toggleReveal"
                                data-wp-on--keydown="actions.profile.revealKeydown"
                                data-field="email"
                                role="button"
                                tabindex="0"
                                data-wp-text="<?= $this->statePath('email') ?>"></span>
                        </div>
                        <div class="profile-login">
                            <strong><?= esc_html__('Login name', 'starwishx'); ?>:</strong>
                            <span class="profile-login__info"
                                data-wp-context='{"field":"userLogin"}'
                                data-wp-class--blurred="state.isFieldBlurred"
                                data-wp-on--click="actions.profile.toggleReveal"
                                data-wp-on--keydown="actions.profile.revealKeydown"
                                data-field="userLogin"
                                role="button"
                                tabindex="0"
                                data-wp-text="<?= $this->statePath('userLogin') ?>"></span>
                        </div>
                    </div>
                    <!-- Profile Meta -->
                    <div class="profile-meta">
                        <div class="profile-role">
                            <span class="status-badge"
                                data-wp-bind--data-role="<?= $this->statePath('role') ?>"
                                data-wp-text="<?= $this->statePath('roleLabel') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('phone') ?>">
                            <strong><?= esc_html__('Phone', 'starwishx'); ?>:</strong>
                            <span class="profile-phone__info"
                                data-wp-context='{"field":"phone"}'
                                data-wp-class--blurred="state.isFieldBlurred"
                                data-wp-on--click="actions.profile.toggleReveal"
                                data-wp-on--keydown="actions.profile.revealKeydown"
                                data-field="phone"
                                role="button"
                                tabindex="0"
                                data-wp-text="<?= $this->statePath('phone') ?>"></span>
                        </div>
                        <div data-wp-bind--hidden="!<?= $this->statePath('telegram') ?>">
                            <strong><?= esc_html__('Telegram', 'starwishx'); ?>:</strong>
                            <span class="profile-messenger__info"
                                data-wp-context='{"field":"telegram"}'
                                data-wp-class--blurred="state.isFieldBlurred"
                                data-wp-on--click="actions.profile.toggleReveal"
                                data-wp-on--keydown="actions.profile.revealKeydown"
                                data-field="telegram"
                                role="button"
                                tabindex="0"
                                data-wp-text="<?= $this->statePath('telegram') ?>"></span>
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
                            placeholder="<?= esc_attr__('e.g. Jane', 'starwishx'); ?>"
                            maxlength="<?= ProfileService::NAME_MAX_LENGTH ?>"
                            data-wp-bind--value="<?= $this->statePath('firstName') ?>"
                            data-wp-on--input="actions.profile.updateField" />
                        <label class="exclamation-circle__error" hidden
                            data-wp-bind--hidden="!<?= $this->statePath('fieldErrors') ?>.firstName"
                            data-wp-text="<?= $this->statePath('fieldErrors') ?>.firstName"></label>
                    </div>
                    <!-- Phone field: intlTelInput widget owns this input -->
                    <div class="form-field form-field--phone">
                        <label for="lp-phone" class="label-required">
                            <?= esc_html__('Phone', 'starwishx'); ?>
                        </label>
                        <input type="tel" id="lp-phone" />
                    </div>
                    <div class="form-field form-field--with-action">
                        <label for="lp-email"><?= esc_html__('Email', 'starwishx'); ?></label>
                        <div class="form-field__group">
                            <input class="form-field--disabled" type="email" id="lp-email" readonly tabindex="-1"
                                data-wp-bind--value="<?= $this->statePath('email') ?>" />
                            <button type="button" class="btn btn-changemail"
                                title="<?= esc_attr__('Change Email', 'starwishx') ?>"
                                data-wp-on--click="actions.profile.openEmailChange">
                                <?= sw_svg('icon-write', 14); ?>
                                <!-- < ?= esc_html__('Change', 'starwishx'); ? > -->
                            </button>
                        </div>
                    </div>
                </div>
                <div class="explanation-block">
                    <div class="label-info">
                        <span class="exclamation-circle">
                            <?= esc_html__('If you want to be an active part of the StarwishX community, sharing opportunities with others and receive Contributor status, the First Name and Phone fields in your profile must be filled in. We want real people to post and receive real opportunities.', 'starwishx'); ?>
                            </br>
                            <?= esc_html__('Note: The information in your profile is not publicly available.', 'starwishx'); ?>
                        </span>
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
                            <label for="lp-last-name"><?= esc_html__('Last Name', 'starwishx'); ?></label>
                            <input type="text" id="lp-last-name" data-field="lastName"
                                placeholder="<?= esc_attr__('e.g. Dow', 'starwishx'); ?>"
                                maxlength="<?= ProfileService::NAME_MAX_LENGTH ?>"
                                data-wp-bind--value="<?= $this->statePath('lastName') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                            <label class="exclamation-circle__error" hidden
                                data-wp-bind--hidden="!<?= $this->statePath('fieldErrors') ?>.lastName"
                                data-wp-text="<?= $this->statePath('fieldErrors') ?>.lastName"></label>
                        </div>
                        <!-- // could be temporary hidden -->
                        <div class="form-field">
                            <label for="lp-nickname">
                                <?= esc_html__('Nickname', 'starwishx'); ?>
                            </label>
                            <input type="text" id="lp-nickname" data-field="nickname"
                                placeholder="<?= esc_attr__('e.g. Janie, kat25', 'starwishx'); ?>"
                                data-wp-bind--value="< ?= $this->statePath('nickname') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>
                        <div class="form-field">
                            <label for="lp-organization"><?= esc_html__('Organization', 'starwishx'); ?></label>
                            <input type="text" id="lp-organization" data-field="organization"
                                placeholder="<?= esc_attr__('If you represent any organization', 'starwishx'); ?>"
                                data-wp-bind--value="<?= $this->statePath('organization') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>

                    </div>

                    <div class="launchpad-grid-auto">
                        <div class="form-field">
                            <label><?= esc_html__('Display name publicly as', 'starwishx'); ?></label>
                            <div class="lp-dropdown"
                                data-wp-class--lp-dropdown--open="<?= $this->statePath('isDisplayNameDropdownOpen') ?>"
                                data-wp-on--focusout="actions.profile.displayNameFocusout"
                                data-wp-on--keydown="actions.profile.displayNameKeydown">
                                <button type="button" class="lp-dropdown__trigger"
                                    data-wp-on--click="actions.profile.toggleDisplayNameDropdown"
                                    aria-haspopup="listbox"
                                    data-wp-bind--aria-expanded="<?= $this->statePath('isDisplayNameDropdownOpen') ?>">
                                    <span data-wp-text="<?= $this->statePath('displayName') ?>"></span>
                                    <svg class="lp-dropdown__chevron" viewBox="0 0 11 7">
                                        <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow-down"></use>
                                    </svg>
                                </button>
                                <ul class="lp-dropdown__list" role="listbox" hidden
                                    data-wp-bind--hidden="!<?= $this->statePath('isDisplayNameDropdownOpen') ?>">
                                    <template data-wp-each="<?= $this->statePath('displayNameOptions') ?>">
                                        <li class="lp-dropdown__item"
                                            data-wp-on--click="actions.profile.selectDisplayName"
                                            data-wp-on--keydown="actions.profile.displayNameItemKeydown"
                                            data-wp-class--lp-dropdown__item--selected="state.isDisplayNameItemSelected"
                                            tabindex="0"
                                            role="option">
                                            <span data-wp-text="context.item"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="lp-telegram"><?= esc_html__('Telegram', 'starwishx'); ?></label>
                            <input type="text" id="lp-telegram" data-field="telegram"
                                placeholder="<?= esc_attr('@jane_smith'); ?>"
                                data-wp-bind--value="<?= $this->statePath('telegram') ?>"
                                data-wp-on--input="actions.profile.updateField" />
                        </div>

                        <div class="form-field">
                            <label for="lp-user-url"><?= esc_html__('Website', 'starwishx'); ?></label>
                            <input type="url" id="lp-user-url" data-field="userUrl"
                                placeholder="<?= esc_attr__('dou.ua, https://me.ua', 'starwishx'); ?>"
                                data-wp-bind--value="<?= $this->statePath('userUrl') ?>"
                                data-wp-on--input="actions.profile.updateField"
                                data-wp-on--blur="actions.profile.normalizeUrlField" />
                        </div>

                    </div>
                    <div class="form-field">
                        <label for="lp-description"><?= esc_html__('Description', 'starwishx'); ?></label>
                        <textarea id="lp-description" data-field="description" rows="4"
                            placeholder="<?= esc_attr__('Biographical Info', 'starwishx'); ?>"
                            data-wp-bind--value="<?= $this->statePath('description') ?>"
                            data-wp-on--input="actions.profile.updateField"></textarea>
                    </div>
                    <div class="form-actions form-actions--delete">
                        <button type="button" class="btn-tertiary btn-profile-delete btn-secondary__small" data-wp-on--click="actions.profile.deleteProfile">
                            <!-- < ?= sw_svg_e('icon-close') ?> -->
                            <?= esc_html__('Delete profile', 'starwishx'); ?>
                        </button>
                    </div>
                </div>

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

            <!-- Change Email Confirmation Popup -->
            <div class="popup"
                hidden
                data-wp-bind--hidden="!<?= $statePath ?>.emailPopup.isOpen">

                <div class="popup__backdrop" data-wp-on--click="actions.profile.cancelEmailChange"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="email-change-title">
                    <div class="popup__body gateway-form">
                        <h2 id="email-change-title" class="popup__title">
                            <?= esc_html__('Change Email', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?= esc_html__('Enter your new email address and current password to confirm the change.', 'starwishx'); ?>
                        </p>

                        <div class="launchpad-alert launchpad-alert--error label-info exclamation-circle__error"
                            data-wp-bind--hidden="!<?= $statePath ?>.emailPopup.error"
                            data-wp-text="<?= $statePath ?>.emailPopup.error"></div>

                        <div class="form-field">
                            <label for="lp-email-new">
                                <?= esc_html__('New Email', 'starwishx'); ?>
                            </label>
                            <input type="email" id="lp-email-new" required
                                data-wp-bind--value="<?= $statePath ?>.emailPopup.newEmail"
                                data-wp-on--input="actions.profile.updateEmailPopupField"
                                data-field="newEmail" />
                        </div>

                        <div class="form-field">
                            <label for="lp-email-password">
                                <?= esc_html__('Current Password', 'starwishx'); ?>
                            </label>
                            <div class="gateway-password-group">
                                <input type="password" id="lp-email-password" required
                                    data-wp-bind--type="state.emailPasswordInputType"
                                    data-wp-bind--value="<?= $statePath ?>.emailPopup.password"
                                    data-wp-on--input="actions.profile.updateEmailPopupField"
                                    data-field="password" />
                                <button type="button" class="btn-hide-pw"
                                    data-wp-on--click="actions.profile.toggleEmailPasswordVisibility"
                                    aria-label="<?= esc_attr__('Toggle password visibility', 'starwishx'); ?>">
                                    <span data-wp-bind--hidden="<?= $statePath ?>.emailPopup.isPasswordVisible">
                                        <svg width="23" height="23" class="btn-hide-pw__icon">
                                            <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-opened"></use>
                                        </svg>
                                    </span>
                                    <span data-wp-bind--hidden="!<?= $statePath ?>.emailPopup.isPasswordVisible">
                                        <svg width="23" height="23" class="btn-hide-pw__icon">
                                            <use href="<?= get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-eye-closed"></use>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="popup__footer">
                        <button type="button" class="btn__small popup__footer--button"
                            data-wp-on--click="actions.profile.confirmEmailChange"
                            data-wp-bind--disabled="<?= $statePath ?>.emailPopup.isChanging">
                            <span data-wp-bind--hidden="<?= $statePath ?>.emailPopup.isChanging">
                                <?= esc_html__('Change Email', 'starwishx'); ?>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.emailPopup.isChanging">
                                <?= esc_html__('Changing...', 'starwishx'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn-secondary__small popup__footer--button"
                            data-wp-on--click="actions.profile.cancelEmailChange">
                            <?= esc_html__('Cancel', 'starwishx'); ?>
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
                        <button type="button" class="btn-secondary__small btn--danger popup__footer--button"
                            data-wp-on--click="actions.profile.confirmDelete"
                            data-wp-bind--disabled="<?= $statePath ?>.deletePopup.isDeleting">
                            <span class="exclamation-circle__error1" data-wp-bind--hidden="<?= $statePath ?>.deletePopup.isDeleting">
                                <?= esc_html__('Delete my account', 'starwishx'); ?>
                            </span>
                            <span data-wp-bind--hidden="!<?= $statePath ?>.deletePopup.isDeleting">
                                <?= esc_html__('Deleting...', 'starwishx'); ?>
                            </span>
                        </button>
                        <button type="button" class="btn__small popup__footer--button"
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

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
        // $service = new ProfileService();
        // $data = $service->getProfileData($userId);
        // // Merge service data with UI state
        // return array_merge($data, [
        //     'isEditing' => false,
        //     // 'error' handled by defaults
        // ]);

        // Safety check: Launchpad panels should always have a user, 
        // but we must handle the nullable parameter to satisfy PHP.
        if (!$userId) {
            return [];
        }

        // return $this->service->getProfileData($userId);
        $data = $this->service->getProfileData($userId);

        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';

        return array_merge($data, [
            'isEditing'          => ($view === 'profile'),
            'isChangingPassword' => ($view === 'password'),
            'passwordData'       => [
                'current' => '',
                'new'     => '',
                'confirm' => '',
            ]
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
                class="launchpad-alert launchpad-alert--error"
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
                                data-wp-text="<?= $this->statePath('role') ?>"></span>
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
                    <input type="email" id="lp-email" required data-field="email"
                        data-wp-bind--value="<?= $this->statePath('email') ?>"
                        data-wp-on--input="actions.profile.updateField" />
                </div>

                <!-- Additional ACF fields -->
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
                    <input type="password" required data-wp-bind--value="<?= $statePath ?>.passwordData.current"
                        data-wp-on--input="actions.profile.updatePasswordField" data-field="current" />
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label><?php esc_html_e('New Password', 'starwishx'); ?></label>
                        <input type="password" required minlength="<?php echo PasswordPolicy::MIN_LENGTH ?>" data-wp-bind--value="<?= $statePath ?>.passwordData.new"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="new" />
                    </div>
                    <div class="form-field">
                        <label><?php esc_html_e('Confirm New Password', 'starwishx'); ?></label>
                        <input type="password" required data-wp-bind--value="<?= $statePath ?>.passwordData.confirm"
                            data-wp-on--input="actions.profile.updatePasswordField" data-field="confirm" />
                    </div>
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

        </div>
<?php
        return $this->endBuffer();
    }
}

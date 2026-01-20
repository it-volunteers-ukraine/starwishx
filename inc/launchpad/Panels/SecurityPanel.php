<?php

declare(strict_types=1);

namespace Launchpad\Panels;

class SecurityPanel extends AbstractPanel
{

    public function getId(): string
    {
        return 'security';
    }

    public function getLabel(): string
    {
        return __('Security', 'starwishx');
    }

    public function getIcon(): string
    {
        return 'shield';
    }

    public function getInitialState(?int $userId = null): array
    {
        return [
            'currentPassword'  => '',
            'newPassword'      => '',
            'confirmPassword'  => '',
            'passwordChanged'  => false,
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <div class="launchpad-panel launchpad-panel--security">
            <h2 class="panel-title"><?php esc_html_e('Security Settings', 'starwishx'); ?></h2>

            <div
                class="launchpad-alert launchpad-alert--error"
                data-wp-bind--hidden="!<?= $this->statePath('error') ?>"
                data-wp-text="<?= $this->statePath('error') ?>"></div>

            <div
                class="launchpad-alert launchpad-alert--success"
                data-wp-bind--hidden="!<?= $this->statePath('passwordChanged') ?>">
                <?php esc_html_e('Password changed successfully.', 'starwishx'); ?>
            </div>

            <form
                class="security-form placeholder-box"
                data-wp-on--submit="actions.security.changePassword">
                <h3><?php esc_html_e('Change Password', 'starwishx'); ?></h3>

                <div class="form-field">
                    <label for="lp-current-password"><?php esc_html_e('Current Password', 'starwishx'); ?></label>
                    <input
                        type="password"
                        id="lp-current-password"
                        data-wp-bind--value="<?= $this->statePath('currentPassword') ?>"
                        data-wp-on--input="actions.security.updateField"
                        data-field="currentPassword"
                        required />
                </div>

                <div class="form-field">
                    <label for="lp-new-password"><?php esc_html_e('New Password', 'starwishx'); ?></label>
                    <input
                        type="password"
                        id="lp-new-password"
                        data-wp-bind--value="<?= $this->statePath('newPassword') ?>"
                        data-wp-on--input="actions.security.updateField"
                        data-field="newPassword"
                        required
                        minlength="8" />
                </div>

                <div class="form-field">
                    <label for="lp-confirm-password"><?php esc_html_e('Confirm Password', 'starwishx'); ?></label>
                    <input
                        type="password"
                        id="lp-confirm-password"
                        data-wp-bind--value="<?= $this->statePath('confirmPassword') ?>"
                        data-wp-on--input="actions.security.updateField"
                        data-field="confirmPassword"
                        required />
                </div>

                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn button button-primary"
                        data-wp-bind--disabled="<?= $this->statePath('isSaving') ?>">
                        <?php esc_html_e('Change Password', 'starwishx'); ?>
                    </button>
                </div>
            </form>
        </div>
<?php
        return $this->endBuffer();
    }
}

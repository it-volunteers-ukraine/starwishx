<?php

/**
 * Reset password form.
 * 
 * File: inc/gateway/Forms/ResetPasswordForm.php
 */

declare(strict_types=1);

namespace Gateway\Forms;

class ResetPasswordForm extends AbstractForm
{
    public function getId(): string
    {
        return 'reset-password';
    }
    public function getLabel(): string
    {
        return _x('Reset Password', 'gateway', 'starwishx');
    }
    public function getInitialState(?int $userId = null): array
    {
        return [
            'newPassword'     => '',
            'confirmPassword' => '',
            'isSubmitting'    => false,
            'error'           => null,
            'success'         => false,
            'successMessage'  => null,
        ];
    }
    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--reset-password"
            data-wp-on--submit="actions.<?php echo esc_attr($this->getJsId()); ?>.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Set New Password', 'starwishx'); ?></h2>
            <div data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.success">
                <p class="gateway-form__intro">
                    <?php esc_html_e('Your password must be at least 12 characters and include uppercase, numbers, and symbols.', 'starwishx'); ?>
                </p>
                <div class="form-field">
                    <label for="gw-new-password"><?php esc_html_e('New Password', 'starwishx'); ?></label>
                    <input type="password" id="gw-new-password" name="newPassword" required
                        data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.newPassword"
                        data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField"
                        data-field="newPassword"
                        autocomplete="new-password">
                </div>
                <div class="form-field">
                    <label for="gw-confirm-password"><?php esc_html_e('Confirm Password', 'starwishx'); ?></label>
                    <input type="password" id="gw-confirm-password" name="confirmPassword" required
                        data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.confirmPassword"
                        data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField"
                        data-field="confirmPassword"
                        autocomplete="new-password">
                </div>
                <!-- Error message display -->
                <div class="gateway-alert gateway-alert--error"
                    data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.error"
                    data-wp-text="state.forms.<?php echo esc_attr($this->getJsId()); ?>.error">
                </div>
                <!-- Submit Button with Loading State -->
                <button type="submit" class="btn btn-primary btn-block"
                    data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                    <span data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                        <?php esc_html_e('Reset Password', 'starwishx'); ?>
                    </span>
                    <span data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                        <?php esc_html_e('Resetting...', 'starwishx'); ?>
                    </span>
                </button>
            </div>
            <!-- Success message display -->
            <div class="gateway-alert gateway-alert--success"
                data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.success">
                <strong><?php esc_html_e('Password Reset Successful!', 'starwishx'); ?></strong>
                <p data-wp-text="state.forms.<?php echo esc_attr($this->getJsId()); ?>.successMessage"></p>
            </div>
            <div class="gateway-links">
                <a href="?view=login" data-wp-on--click="actions.switchView">
                    <?php esc_html_e('Back to Login', 'starwishx'); ?>
                </a>
            </div>
        </form>
<?php
        return $this->endBuffer();
    }
}

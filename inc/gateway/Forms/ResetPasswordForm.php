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
                <div class="form-field">
                    <label for="gw-new-password"><?php esc_html_e('New Password', 'starwishx'); ?></label>
                    <input type="password" id="gw-new-password" name="newPassword" required
                        data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.newPassword"
                        data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="newPassword">
                </div>

                <div class="form-field">
                    <label for="gw-confirm-password"><?php esc_html_e('Confirm Password', 'starwishx'); ?></label>
                    <input type="password" id="gw-confirm-password" name="confirmPassword" required
                        data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.confirmPassword"
                        data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="confirmPassword">
                </div>

                <button type="submit" class="btn btn-primary btn-block"
                    data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                    <?php esc_html_e('Reset Password', 'starwishx'); ?>
                </button>
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

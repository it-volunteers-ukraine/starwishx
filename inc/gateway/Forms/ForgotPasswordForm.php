<?php

declare(strict_types=1);

namespace Gateway\Forms;

/**
 * Forgot password form.
 */
class ForgotPasswordForm extends AbstractForm
{
    public function getId(): string
    {
        return 'forgot-password';
    }

    public function getLabel(): string
    {
        return __('Forgot Password', 'starwishx');
    }

    public function getInitialState(?int $userId = null): array
    {
        return [
            'email'          => '',
            'isSubmitting'   => false,
            'error'          => null,
            'success'        => false,
            'successMessage' => null,
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--forgot-password" data-wp-on--submit="actions.forgotPassword.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Reset Password', 'starwishx'); ?></h2>
            <p><?php esc_html_e('Enter your email and we\'ll send you a reset link.', 'starwishx'); ?></p>

            <div data-wp-bind--hidden="state.forms.forgotPassword.success">
                <div class="form-field">
                    <label for="gw-email"><?php esc_html_e('Email Address', 'starwishx'); ?></label>
                    <input type="email" id="gw-email" name="email" required
                        data-wp-bind--value="state.forms.forgotPassword.email"
                        data-wp-on--input="actions.forgotPassword.updateField" data-field="email">
                </div>

                <button type="submit" class="btn btn-primary btn-block"
                    data-wp-bind--disabled="state.forms.forgotPassword.isSubmitting">
                    <?php esc_html_e('Send Reset Link', 'starwishx'); ?>
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

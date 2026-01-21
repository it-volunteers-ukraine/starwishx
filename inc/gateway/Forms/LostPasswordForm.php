<?php

/**
 * Lost Password form 
 * 
 * File: inc/gateway/Forms/LostPasswordForm.php
 */

declare(strict_types=1);

namespace Gateway\Forms;

class LostPasswordForm extends AbstractForm
{
    public function getId(): string
    {
        return 'lost-password';
    }
    public function getLabel(): string
    {
        return __('Lost Password', 'starwishx');
    }

    public function getInitialState(?int $userId = null): array
    {
        return [
            'userLogin'      => '',
            'isSubmitting'   => false,
            'error'          => null,
            'success'        => false,
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--lost-password" data-wp-on--submit="actions.lostPassword.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Lost Password', 'starwishx'); ?></h2>
            <p><?php esc_html_e('Enter your username or email address to receive a reset link.', 'starwishx'); ?></p>

            <div data-wp-bind--hidden="state.forms.lostPassword.success">
                <div class="form-field">
                    <label for="gw-lost-user"><?php esc_html_e('Username or Email', 'starwishx'); ?></label>
                    <input type="text" id="gw-lost-user" name="user_login" required
                        data-wp-bind--value="state.forms.lostPassword.userLogin"
                        data-wp-on--input="actions.lostPassword.updateField" data-field="userLogin">
                </div>

                <button type="submit" class="btn btn-primary btn-block"
                    data-wp-bind--disabled="state.forms.lostPassword.isSubmitting">
                    <span data-wp-bind--hidden="state.forms.lostPassword.isSubmitting"><?php esc_html_e('Get New Password', 'starwishx'); ?></span>
                    <span data-wp-bind--hidden="!state.forms.lostPassword.isSubmitting"><?php esc_html_e('Processing...', 'starwishx'); ?></span>
                </button>
            </div>

            <div class="gateway-alert gateway-alert--error" data-wp-bind--hidden="!state.forms.lostPassword.error" data-wp-text="state.forms.lostPassword.error"></div>
            <div class="gateway-alert gateway-alert--success" data-wp-bind--hidden="!state.forms.lostPassword.success">
                <?php esc_html_e('Check your email for the confirmation link.', 'starwishx'); ?>
            </div>

            <div class="gateway-links">
                <a href="?view=login" data-wp-on--click="actions.switchView"><?php esc_html_e('Back to Login', 'starwishx'); ?></a>
            </div>
        </form>
<?php
        return $this->endBuffer();
    }
}

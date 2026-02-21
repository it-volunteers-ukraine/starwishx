<?php

/**
 * Reset password form.
 * 
 * File: inc/gateway/Forms/ResetPasswordForm.php
 */


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
            // Pre-fill the first password via PHP to save an AJAX call on load
            'newPassword'       => wp_generate_password(24, true, true),
            'isPasswordVisible' => true, // Native WP defaults to showing the generated password
            'isGenerating'      => false,
            'isSubmitting'      => false,
            'error'             => null,
            'success'           => false,
            'successMessage'    => null,
        ];
    }
    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--reset-password"
            data-wp-on--submit="actions.<?php echo esc_attr($this->getJsId()); ?>.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Set New Password', 'starwishx'); ?></h2>
            <p class="gateway-form__intro">
                <?php esc_html_e('Your new password has been pre-generated. You can modify it or generate a new one.', 'starwishx'); ?>
            </p>

            <div class="gateway-fields__container" data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.success">
                <div class="form-field">
                    <label for="gw-new-password"><?php esc_html_e('New Password', 'starwishx'); ?></label>

                    <div class="gateway-password-group">
                        <input
                            id="gw-new-password"
                            name="newPassword"
                            required
                            type="text"
                            data-wp-bind--type="state.resetPasswordInputType"
                            data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.newPassword"
                            data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField"
                            data-field="newPassword"
                            autocomplete="new-password">

                        <button type="button" class="btn-hide-pw"
                            data-wp-on--click="actions.<?php echo esc_attr($this->getJsId()); ?>.toggleVisibility">
                            <span data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isPasswordVisible">
                                <!-- < ?php esc_html_e('Show', 'starwishx'); ?> -->
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use xlink:href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-eye-opened"></use>
                                </svg>
                            </span>
                            <span data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.isPasswordVisible">
                                <!-- < ?php esc_html_e('Hide', 'starwishx'); ?> -->
                                <svg width="23" height="23" class="btn-hide-pw__icon">
                                    <use xlink:href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-eye-closed"></use>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Error message display -->
                <div class="gateway-alert gateway-alert--error"
                    data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.error"
                    data-wp-text="state.forms.<?php echo esc_attr($this->getJsId()); ?>.error">
                </div>

                <div class="buttons-group">

                    <button type="button" class="btn btn-secondary btn-block"
                        data-wp-on--click="actions.<?php echo esc_attr($this->getJsId()); ?>.generate"
                        data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isGenerating">
                        <span data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isGenerating">
                            <?php esc_html_e('Generate New Password', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.isGenerating">
                            <?php esc_html_e('Generating...', 'starwishx'); ?>
                        </span>
                    </button>

                    <!-- Submit Button with Loading State -->
                    <button type="submit" class="btn btn-primary btn-block"
                        data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                        <span data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                            <?php esc_html_e('Save Password', 'starwishx'); ?>
                        </span>
                        <span data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                            <?php esc_html_e('Saving...', 'starwishx'); ?>
                        </span>
                    </button>
                </div>
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

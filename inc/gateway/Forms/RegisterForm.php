<?php

declare(strict_types=1);

namespace Gateway\Forms;

/**
 * Registration form.
 */
class RegisterForm extends AbstractForm
{
    public function getId(): string
    {
        return 'register';
    }

    public function getLabel(): string
    {
        return __('Register', 'starwishx');
    }

    public function getInitialState(?int $userId = null): array
    {
        return [
            'username'       => '',
            'email'          => '',
            'password'       => '',
            'confirmPassword' => '',
            'isSubmitting'   => false,
            'error'          => null,
            'fieldErrors'    => [
                'username' => null,
                'email'    => null,
                'password' => null,
            ],
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--register" data-wp-on--submit="actions.register.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Create Account', 'starwishx'); ?></h2>

            <div class="form-field">
                <label for="gw-reg-username"><?php esc_html_e('Username', 'starwishx'); ?></label>
                <input type="text" id="gw-reg-username" name="username" required
                    data-wp-bind--value="state.forms.register.username"
                    data-wp-on--input="actions.register.updateField" data-field="username">
            </div>

            <div class="form-field">
                <label for="gw-reg-email"><?php esc_html_e('Email', 'starwishx'); ?></label>
                <input type="email" id="gw-reg-email" name="email" required
                    data-wp-bind--value="state.forms.register.email"
                    data-wp-on--input="actions.register.updateField" data-field="email">
            </div>

            <div class="form-field">
                <label for="gw-reg-password"><?php esc_html_e('Password', 'starwishx'); ?></label>
                <input type="password" id="gw-reg-password" name="password" required
                    data-wp-bind--value="state.forms.register.password"
                    data-wp-on--input="actions.register.updateField" data-field="password">
            </div>

            <button type="submit" class="btn btn-primary btn-block"
                data-wp-bind--disabled="state.forms.register.isSubmitting">
                <?php esc_html_e('Register', 'starwishx'); ?>
            </button>

            <div class="gateway-links">
                <a href="?view=login" data-wp-on--click="actions.switchView">
                    <?php esc_html_e('Already have an account?', 'starwishx'); ?>
                </a>
            </div>
        </form>
<?php
        return $this->endBuffer();
    }
}

<?php

/**
 * Registration form.
 * 
 * File: inc/gateway/Forms/RegisterForm.php
 */

declare(strict_types=1);

namespace Gateway\Forms;

class RegisterForm extends AbstractForm
{
    public function getId(): string
    {
        return 'register';
    }

    public function getLabel(): string
    {
        return _x('Register', 'gateway', 'starwishx');
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
        <form class="gateway-form gateway-form--register"
            data-wp-on--submit="actions.<?php echo esc_attr($this->getJsId()); ?>.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Create Account', 'starwishx'); ?></h2>

            <div class="form-field">
                <label for="gw-reg-username"><?php _ex('Username', 'gateway', 'starwishx'); ?></label>
                <input type="text" id="gw-reg-username" name="username" required
                    data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.username"
                    data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="username">
            </div>

            <div class="form-field">
                <label for="gw-reg-email"><?php _ex('Email', 'gateway', 'starwishx'); ?></label>
                <input type="email" id="gw-reg-email" name="email" required
                    data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.email"
                    data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="email">
            </div>

            <div class="form-field">
                <label for="gw-reg-password"><?php _ex('Password', 'gateway', 'starwishx'); ?></label>
                <input type="password" id="gw-reg-password" name="password" required
                    data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.password"
                    data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="password">
            </div>

            <button type="submit" class="btn btn-primary btn-block"
                data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
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

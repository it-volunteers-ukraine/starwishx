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
            'username'     => '',
            'email'        => '',
            'isSubmitting' => false,
            'success'      => false,
            'error'        => null,
            'fieldErrors'  => ['username' => null, 'email' => null],
        ];
    }

    public function render(): string
    {
        $jsId = $this->getJsId();
        $this->startBuffer();
?>
        <form class="gateway-form" data-wp-on--submit="actions.<?php echo $jsId; ?>.submit">
            <h2 class="gateway-form__title"><?php esc_html_e('Create Account', 'starwishx'); ?></h2>
            <p class="gateway-form__intro"><?php esc_html_e('Enter your details to receive an activation link.', 'starwishx'); ?></p>

            <div class="gateway-fields__container" data-wp-bind--hidden="state.forms.<?php echo $jsId; ?>.success">

                <div class="form-field">
                    <label><?php _ex('Username', 'gateway', 'starwishx'); ?></label>
                    <input type="text" required data-field="username"
                        data-wp-bind--value="state.forms.<?php echo $jsId; ?>.username"
                        data-wp-on--input="actions.<?php echo $jsId; ?>.updateField">
                </div>

                <div class="form-field">
                    <label><?php _ex('Email', 'gateway', 'starwishx'); ?></label>
                    <input type="email" required data-field="email"
                        data-wp-bind--value="state.forms.<?php echo $jsId; ?>.email"
                        data-wp-on--input="actions.<?php echo $jsId; ?>.updateField">
                </div>

                <button type="submit" class="btn btn-primary btn-block" data-wp-bind--disabled="state.forms.<?php echo $jsId; ?>.isSubmitting">
                    <?php esc_html_e('Register', 'starwishx'); ?>
                </button>
            </div>

            <div class="gateway-alert gateway-alert--success" data-wp-bind--hidden="!state.forms.<?php echo $jsId; ?>.success">
                <?php esc_html_e('Registration successful! Please check your email for the activation link.', 'starwishx'); ?>
            </div>

            <div class="gateway-alert gateway-alert--error" data-wp-bind--hidden="!state.forms.<?php echo $jsId; ?>.error" data-wp-text="state.forms.<?php echo $jsId; ?>.error"></div>

            <div class="gateway-links">
                <a href="?view=login" data-wp-on--click="actions.switchView"><?php esc_html_e('Back to Login', 'starwishx'); ?></a>
            </div>
        </form>
<?php
        return $this->endBuffer();
    }
}

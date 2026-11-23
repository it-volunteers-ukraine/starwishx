<?php

/**
 * Lost Password form 
 * 
 * File: inc/gateway/Forms/LostPasswordForm.php
 */

declare(strict_types=1);

namespace Gateway\Forms;

use Gateway\Enums\GatewayView;

final class LostPasswordForm extends AbstractForm
{
    #[\Override]
    public function getId(): string
    {
        return GatewayView::LOST_PASSWORD->value;
    }

    #[\Override]
    public function getLabel(): string
    {
        return _x('Lost Password', 'gateway', 'starwishx');
    }

    #[\Override]
    public function getInitialState(?int $userId = null): array
    {
        return [
            'userLogin'      => '',
            'isSubmitting'   => false,
            'error'          => null,
            'success'        => false,
            'fieldErrors'    => ['userLogin' => null],
        ];
    }

    #[\Override]
    public function render(): string
    {
        $this->startBuffer();
?>
        <form class="gateway-form gateway-form--lost-password"
            data-wp-on--submit="actions.<?php echo esc_attr($this->getJsId()); ?>.submit"
            novalidate>
            <h2 class="gateway-form__title"><?php esc_html_e('Lost Password', 'starwishx'); ?></h2>

            <div class="gateway-fields__container" data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.success">
                <p class="gateway-form__intro"><?php esc_html_e('Enter your login or email to receive a reset link.', 'starwishx'); ?></p>
                <div class="form-field">
                    <label for="gw-lost-user"><?php echo esc_html_x('Login name or Email', 'gateway', 'starwishx'); ?></label>
                    <input type="text" id="gw-lost-user" name="user_login" required
                        placeholder="<?php echo esc_attr_x('Latin letters, digits, . - _ @', 'gateway', 'starwishx'); ?>"
                        data-wp-bind--value="state.forms.<?php echo esc_attr($this->getJsId()); ?>.userLogin"
                        data-wp-on--input="actions.<?php echo esc_attr($this->getJsId()); ?>.updateField" data-field="userLogin">
                    <span
                        class="exclamation-circle__error"
                        data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.fieldErrors.userLogin"
                        data-wp-text="state.forms.<?php echo esc_attr($this->getJsId()); ?>.fieldErrors.userLogin">
                    </span>
                </div>

                <button type="submit" class="btn btn-submit"
                    data-wp-bind--disabled="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                    <span data-wp-bind--hidden="state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                        <?php echo esc_html_x('Get New Password', 'gateway', 'starwishx'); ?>
                    </span>
                    <span data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.isSubmitting">
                        <?php esc_html_e('Processing...', 'starwishx'); ?>
                    </span>
                </button>
            </div>

            <div class="gateway-alert gateway-alert--error" data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.error" data-wp-text="state.forms.<?php echo esc_attr($this->getJsId()); ?>.error"></div>
            <div class="gateway-alert gateway-alert--success" data-wp-bind--hidden="!state.forms.<?php echo esc_attr($this->getJsId()); ?>.success">
                <?php echo esc_html_x('Check your email for the confirmation link.', 'gateway', 'starwishx'); ?>
            </div>

            <div class="gateway-links">
                <a href="?view=login" data-wp-on--click="actions.switchView">
                    <?php echo esc_html_x('Back to Login', 'gateway', 'starwishx'); ?>
                </a>
            </div>
        </form>
<?php
        return $this->endBuffer();
    }
}

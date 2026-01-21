<?php

declare(strict_types=1);

namespace Gateway\Forms;

/**
 * Login form with SSR state and Interactivity API directives.
 */
class LoginForm extends AbstractForm
{
    public function getId(): string
    {
        return 'login';
    }

    public function getLabel(): string
    {
        return __('Login', 'starwishx');
    }

    public function getInitialState(?int $userId = null): array
    {
        return [
            'username'     => '',
            'password'     => '',
            'rememberMe'   => false,
            'isSubmitting' => false,
            'error'        => null,
            'fieldErrors'  => [
                'username' => null,
                'password' => null,
            ],
        ];
    }

    public function render(): string
    {
        $this->startBuffer();
?>
        <form
            class="gateway-form gateway-form--login"
            data-wp-on--submit="actions.login.submit"
            autocomplete="on">

            <h2 class="gateway-form__title">
                <?php esc_html_e('Welcome Back', 'starwishx'); ?>
            </h2>

            <!-- Username/Email Field -->
            <div class="form-field">
                <label for="gw-username">
                    <?php esc_html_e('Username or Email', 'starwishx'); ?>
                </label>
                <input
                    type="text"
                    id="gw-username"
                    name="username"
                    autocomplete="username"
                    required
                    data-wp-bind--value="state.forms.login.username"
                    data-wp-on--input="actions.login.updateField"
                    data-field="username">
                <span
                    class="field-error"
                    data-wp-bind--hidden="!state.forms.login.fieldErrors.username"
                    data-wp-text="state.forms.login.fieldErrors.username">
                </span>
            </div>

            <!-- Password Field -->
            <div class="form-field">
                <label for="gw-password">
                    <?php esc_html_e('Password', 'starwishx'); ?>
                </label>
                <input
                    type="password"
                    id="gw-password"
                    name="password"
                    autocomplete="current-password"
                    required
                    data-wp-bind--value="state.forms.login.password"
                    data-wp-on--input="actions.login.updateField"
                    data-field="password">
                <span
                    class="field-error"
                    data-wp-bind--hidden="!state.forms.login.fieldErrors.password"
                    data-wp-text="state.forms.login.fieldErrors.password">
                </span>
            </div>

            <!-- Error Message -->
            <div
                class="gateway-alert gateway-alert--error"
                data-wp-bind--hidden="!state.forms.login.error"
                data-wp-text="state.forms.login.error">
            </div>

            <!-- Submit Button -->
            <div class="buttons-group">
                <button
                    type="submit"
                    class="btn btn-primary btn-block"
                    data-wp-bind--disabled="state.forms.login.isSubmitting">
                    <span data-wp-bind--hidden="state.forms.login.isSubmitting">
                        <?php esc_html_e('Log In', 'starwishx'); ?>
                    </span>
                    <span data-wp-bind--hidden="!state.forms.login.isSubmitting">
                        <?php esc_html_e('Logging in...', 'starwishx'); ?>
                    </span>
                </button>

                <a
                    class="btn btn-secondary btn-block"
                    href="?view=register"
                    data-wp-on--click="actions.switchView">
                    <?php esc_html_e('Create account', 'starwishx'); ?>
                </a>
            </div>

            <!-- Links -->
            <div class="gateway-links">
                <!-- Remember Me -->
                <label class="form-field form-field--checkbox">
                    <input
                        type="checkbox"
                        name="remember"
                        data-wp-bind--checked="state.forms.login.rememberMe"
                        data-wp-on--change="actions.login.toggleRemember">
                    <?php esc_html_e('Remember me', 'starwishx'); ?>
                </label>

                <!-- Forgot Password -->
                <a
                    class="gateway-link__password"
                    href="?view=lost-password"
                    data-wp-on--click="actions.switchView">
                    <?php esc_html_e('Forgot password?', 'starwishx'); ?>
                </a>
            </div>

        </form>
<?php
        return $this->endBuffer();
    }
}

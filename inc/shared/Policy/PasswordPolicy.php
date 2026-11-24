<?php
// File: inc/shared/Policy/PasswordPolicy.php
declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;
use WP_User;

final class PasswordPolicy
{
    /** In characters (code points), as the client counts them. */
    public const int MIN_LENGTH = 12;

    public static function getMinLength(): int
    {
        return self::MIN_LENGTH;
    }

    /**
     * Single source of truth for password validation.
     * Used by both Gateway (reset) and Launchpad (change password).
     *
     * The length is in characters: in bytes, a Cyrillic password of
     * 9 characters would pass a 12 minimum. The client's validatePassword()
     * (inc/gateway/Assets/utils.ts) counts the same way.
     *
     * @param string       $password The new password to validate.
     * @param WP_User|null $user     Optional user — enables "no personal data" rule.
     * @return true|WP_Error
     */
    #[\NoDiscard('a password is only checked when the result is acted on')]
    public static function validate(
        #[\SensitiveParameter] string $password,
        ?WP_User $user = null
    ): bool|WP_Error {
        // 1. Minimum length
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return new WP_Error(
                'password_too_short',
                sprintf(
                    __('Password must be at least %d characters long.', 'starwishx'),
                    self::MIN_LENGTH
                )
            );
        }

        // 2. Character mix (uppercase + number + special)
        $has_uppercase = preg_match('/[A-Z]/', $password);
        $has_number    = preg_match('/[0-9]/', $password);
        $has_special   = preg_match('/[^A-Za-z0-9]/', $password);

        if (!$has_uppercase || !$has_number || !$has_special) {
            return new WP_Error(
                'password_too_weak',
                __('Please use a mix of uppercase letters, numbers, and symbols.', 'starwishx')
            );
        }

        // 3. No personal data (only when a user is provided)
        if ($user) {
            $personal = array_filter([
                $user->user_login,
                $user->user_email,
                $user->display_name,
            ]);

            if (array_any($personal, static fn(string $data): bool => stripos($password, $data) !== false)) {
                return new WP_Error(
                    'password_contains_userdata',
                    __('Password cannot contain your username, email, or name.', 'starwishx')
                );
            }
        }

        return true;
    }

    /**
     * Provide policy rules + i18n strings for client-side JS validation.
     * Hydrated into state via wp_interactivity_state() — no hardcoded
     * English strings in JS files. TS: PasswordPolicy in
     * inc/gateway/Assets/types.ts.
     *
     * @return array{minLength: int, messages: array{tooShort: string, tooWeak: string, hasUserData: string}}
     */
    public static function getClientRules(): array
    {
        return [
            'minLength' => self::MIN_LENGTH,
            'messages'  => [
                'tooShort'    => sprintf(
                    __('Password must be at least %d characters long.', 'starwishx'),
                    self::MIN_LENGTH
                ),
                'tooWeak'    => __('Please use a mix of uppercase letters, numbers, and symbols.', 'starwishx'),
                'hasUserData' => __('Password cannot contain your username, email, or name.', 'starwishx'),
            ],
        ];
    }

    /**
     * Provide generic client-side validation strings (non-password).
     * Covers "required field" messages used across Gateway forms.
     * TS: ValidationStrings in inc/gateway/Assets/types.ts.
     *
     * @return array{usernameRequired: string, passwordRequired: string, emailRequired: string, emailInvalid: string, userLoginRequired: string}
     */
    public static function getClientValidationStrings(): array
    {
        return [
            'usernameRequired'   => __('Username is required', 'starwishx'),
            'passwordRequired'   => __('Password is required', 'starwishx'),
            'emailRequired'      => __('Email is required', 'starwishx'),
            'emailInvalid'       => __('Please enter a valid email address', 'starwishx'),
            'userLoginRequired'  => __('Please enter your username or email', 'starwishx'),
        ];
    }
}

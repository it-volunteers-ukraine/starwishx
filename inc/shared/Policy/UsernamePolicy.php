<?php
// File: inc/shared/Policy/UsernamePolicy.php
declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;

/**
 * Shared character-set policy for human-readable identifiers.
 *
 * Used by Gateway (register: user_login) and Launchpad (profile: nickname).
 * The PATTERN is a strict subset of what WordPress's sanitize_user() accepts,
 * so values that pass this policy survive sanitize_user() unchanged — giving
 * callers defense-in-depth without surprise mutation.
 */
final class UsernamePolicy
{
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 60;

    /** Latin letters, digits, dot, underscore, hyphen. No spaces, no '@'. */
    public const PATTERN = '/^[a-zA-Z0-9._-]+$/';

    public static function getMinLength(): int
    {
        return self::MIN_LENGTH;
    }

    public static function getMaxLength(): int
    {
        return self::MAX_LENGTH;
    }

    /**
     * Validate a username/nickname value. Returns true on success, or a
     * WP_Error with code `invalid_username_format` on failure.
     *
     * Not called on empty input — callers decide whether the field is required.
     */
    public static function validate(string $value): bool|WP_Error
    {
        $len = strlen($value);

        if ($len < self::MIN_LENGTH) {
            return new WP_Error('invalid_username_format', self::messageTooShort());
        }
        if ($len > self::MAX_LENGTH) {
            return new WP_Error('invalid_username_format', self::messageTooLong());
        }
        if (!preg_match(self::PATTERN, $value)) {
            return new WP_Error('invalid_username_format', self::messageInvalid());
        }

        return true;
    }

    /**
     * Rules + i18n strings for client-side JS pre-validation.
     * Hydrated into Interactivity state via wp_interactivity_state().
     */
    public static function getClientRules(): array
    {
        return [
            'pattern'   => '^[a-zA-Z0-9._-]+$', // no delimiters — JS RegExp takes the bare source
            'minLength' => self::MIN_LENGTH,
            'maxLength' => self::MAX_LENGTH,
            'messages'  => [
                'tooShort' => self::messageTooShort(),
                'tooLong'  => self::messageTooLong(),
                'invalid'  => self::messageInvalid(),
            ],
        ];
    }

    private static function messageTooShort(): string
    {
        return sprintf(
            __('Must be at least %d characters.', 'starwishx'),
            self::MIN_LENGTH
        );
    }

    private static function messageTooLong(): string
    {
        return sprintf(
            __('Must not exceed %d characters.', 'starwishx'),
            self::MAX_LENGTH
        );
    }

    private static function messageInvalid(): string
    {
        return __('Only latin letters, digits, and the characters . _ - are allowed.', 'starwishx');
    }
}

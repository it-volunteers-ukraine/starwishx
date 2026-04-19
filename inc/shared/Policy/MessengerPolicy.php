<?php
// File: inc/shared/Policy/MessengerPolicy.php
declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;

/**
 * Shared character-set policy for messenger handles (Telegram-style).
 *
 * Rules (mirror Telegram's public username policy — the canonical format):
 *   - 5 to 32 characters (handle length, excluding an optional leading "@")
 *   - Must start with a latin letter
 *   - Only latin letters, digits, and underscores
 *
 * The optional "@" prefix is accepted for UX (users often type it) but
 * is not counted toward the length. Callers decide whether the field is
 * required; this only validates format when a value is present.
 */
final class MessengerPolicy
{
    public const MIN_LENGTH = 5;
    public const MAX_LENGTH = 32;

    /** Optional "@" + handle (letter-led, letters/digits/underscore, 5-32). */
    public const PATTERN = '/^@?[a-zA-Z][a-zA-Z0-9_]{4,31}$/';

    public static function getMinLength(): int
    {
        return self::MIN_LENGTH;
    }

    public static function getMaxLength(): int
    {
        return self::MAX_LENGTH;
    }

    /**
     * Validate a messenger handle. Returns true on success, or a WP_Error
     * with code `invalid_messenger_format` on failure.
     *
     * Not called on empty input — callers decide whether the field is required.
     */
    public static function validate(string $value): bool|WP_Error
    {
        if (!preg_match(self::PATTERN, $value)) {
            return new WP_Error('invalid_messenger_format', self::messageInvalid());
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
            'pattern'   => '^@?[a-zA-Z][a-zA-Z0-9_]{4,31}$', // no delimiters — JS RegExp takes bare source
            'minLength' => self::MIN_LENGTH,
            'maxLength' => self::MAX_LENGTH,
            'messages'  => [
                'invalid' => self::messageInvalid(),
            ],
        ];
    }

    private static function messageInvalid(): string
    {
        return sprintf(
            /* translators: %1$d = minimum length, %2$d = maximum length. */
            __('Must be %1$d–%2$d characters, start with a letter, and use only latin letters, digits, and underscores.', 'starwishx'),
            self::MIN_LENGTH,
            self::MAX_LENGTH
        );
    }
}

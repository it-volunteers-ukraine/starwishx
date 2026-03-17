<?php
// File: inc/shared/Policy/PhonePolicy.php
declare(strict_types=1);

namespace Shared\Policy;

use WP_Error;

/**
 * Phone number validation policy.
 *
 * Uses libphonenumber (via ACF Phone Number plugin) for precise validation
 * when available, falls back to E.164 format check otherwise.
 */
final class PhonePolicy
{
    /**
     * Validate a phone number.
     *
     * Empty string is considered valid (field optionality is the caller's concern).
     *
     * @param string $number  E.164 phone number (e.g. "+380441231231")
     * @param string $country ISO 3166-1 alpha-2 country code (e.g. "ua")
     * @return true|WP_Error
     */
    public static function validate(string $number, string $country = 'ua'): bool|WP_Error
    {
        if ($number === '') {
            return true;
        }

        if (class_exists(\libphonenumber\PhoneNumberUtil::class)) {
            return self::validateWithLibphone($number, $country);
        }

        return self::validateBasic($number);
    }

    /**
     * Full validation via Google's libphonenumber.
     */
    private static function validateWithLibphone(string $number, string $country): bool|WP_Error
    {
        $util = \libphonenumber\PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($number, strtoupper($country));
        } catch (\libphonenumber\NumberParseException $e) {
            return new WP_Error(
                'phone_parse_error',
                __('The phone number could not be recognized. Please check the format.', 'starwishx')
            );
        }

        if (!$util->isValidNumber($parsed)) {
            return new WP_Error(
                'phone_invalid',
                __('The phone number is not valid for the selected country.', 'starwishx')
            );
        }

        return true;
    }

    /**
     * Fallback: E.164 format + length check.
     * Accepts + followed by 7–15 digits (ITU-T E.164 spec).
     */
    private static function validateBasic(string $number): bool|WP_Error
    {
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $number)) {
            return new WP_Error(
                'phone_invalid',
                __('Please enter a valid phone number in international format (e.g. +380441231231).', 'starwishx')
            );
        }

        return true;
    }
}

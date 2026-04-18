<?php

/**
 * REST argument validators with i18n, field-named error messages.
 *
 * Stateless static factories returning Closures suitable for use as
 * `validate_callback` in register_rest_route() args. Returning WP_Error
 * (instead of bare false) lets the response carry the specific reason for
 * failure — without WordPress's generic "Invalid parameter(s): X" wrapper
 * hiding which constraint actually tripped.
 *
 * Multi-byte safe: string length is measured with mb_strlen, so Cyrillic /
 * Ukrainian content is counted by characters, not bytes.
 *
 * Usage:
 *   'seekers' => [
 *       'type'              => 'array',
 *       'validate_callback' => RestArg::arrayMaxItems(10, __('Seekers', 'starwishx')),
 *   ],
 *
 * File: inc/shared/Validation/RestArg.php
 */

declare(strict_types=1);

namespace Shared\Validation;

use Closure;
use WP_Error;

final class RestArg
{
    /**
     * Validate value is an array and does not exceed $max items.
     *
     * Type vs. size failures use distinct codes (`invalid_type` / `too_many_items`)
     * so clients can react differently if needed.
     */
    public static function arrayMaxItems(int $max, string $fieldLabel): Closure
    {
        return static function ($value) use ($max, $fieldLabel) {
            if (!is_array($value)) {
                return new WP_Error(
                    'invalid_type',
                    sprintf(
                        /* translators: %s: name of the field that received an invalid value */
                        __('%s: expected an array.', 'starwishx'),
                        $fieldLabel
                    ),
                    ['status' => 400]
                );
            }

            if (count($value) > $max) {
                return new WP_Error(
                    'too_many_items',
                    sprintf(
                        /* translators: 1: field name, 2: maximum allowed item count */
                        __('%1$s: maximum %2$d items allowed.', 'starwishx'),
                        $fieldLabel,
                        $max
                    ),
                    ['status' => 422]
                );
            }

            return true;
        };
    }

    /**
     * Validate value is numeric and within an inclusive integer range.
     */
    public static function intRange(int $min, int $max, string $fieldLabel): Closure
    {
        return static function ($value) use ($min, $max, $fieldLabel) {
            if (!is_numeric($value)) {
                return new WP_Error(
                    'invalid_type',
                    sprintf(
                        /* translators: %s: name of the field that received an invalid value */
                        __('%s: expected a number.', 'starwishx'),
                        $fieldLabel
                    ),
                    ['status' => 400]
                );
            }

            $int = (int) $value;
            if ($int < $min || $int > $max) {
                return new WP_Error(
                    'out_of_range',
                    sprintf(
                        /* translators: 1: field name, 2: minimum value, 3: maximum value */
                        __('%1$s: must be between %2$d and %3$d.', 'starwishx'),
                        $fieldLabel,
                        $min,
                        $max
                    ),
                    ['status' => 422]
                );
            }

            return true;
        };
    }

    /**
     * Validate value is a Y-m-d calendar date.
     *
     * Two-stage: regex for shape, then checkdate() for real-calendar validity
     * (so 2024-02-30 and 2024-13-01 are rejected, not just parsed-as-garbage
     * and tolerated downstream).
     *
     * Empty strings are allowed when $nullable is true — useful for optional
     * date fields where "unset" is a legitimate value (e.g., open-ended
     * opportunities). Required dates should pair this with required=>true at
     * the arg level and pass $nullable=false here.
     */
    public static function datePattern(string $fieldLabel, bool $nullable = true): Closure
    {
        return static function ($value) use ($fieldLabel, $nullable) {
            if ($nullable && ($value === null || $value === '')) {
                return true;
            }

            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
                return new WP_Error(
                    'invalid_date_format',
                    sprintf(
                        /* translators: %s: name of the date field */
                        __('%s: expected YYYY-MM-DD format.', 'starwishx'),
                        $fieldLabel
                    ),
                    ['status' => 422]
                );
            }

            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return new WP_Error(
                    'invalid_date',
                    sprintf(
                        /* translators: %s: name of the date field */
                        __('%s: not a valid calendar date.', 'starwishx'),
                        $fieldLabel
                    ),
                    ['status' => 422]
                );
            }

            return true;
        };
    }

    /**
     * Validate string length is within bounds (inclusive).
     *
     * Trim is applied before the min check so whitespace-only strings fail.
     * Length uses mb_strlen — counts characters, not bytes.
     */
    public static function stringLength(int $min, int $max, string $fieldLabel): Closure
    {
        return static function ($value) use ($min, $max, $fieldLabel) {
            if (!is_string($value)) {
                return new WP_Error(
                    'invalid_type',
                    sprintf(
                        /* translators: %s: name of the field that received an invalid value */
                        __('%s: expected a string.', 'starwishx'),
                        $fieldLabel
                    ),
                    ['status' => 400]
                );
            }

            if (mb_strlen(trim($value)) < $min) {
                return new WP_Error(
                    'too_short',
                    sprintf(
                        /* translators: 1: field name, 2: minimum character count */
                        __('%1$s: at least %2$d characters required.', 'starwishx'),
                        $fieldLabel,
                        $min
                    ),
                    ['status' => 422]
                );
            }

            if (mb_strlen($value) > $max) {
                return new WP_Error(
                    'too_long',
                    sprintf(
                        /* translators: 1: field name, 2: maximum character count */
                        __('%1$s: maximum %2$d characters allowed.', 'starwishx'),
                        $fieldLabel,
                        $max
                    ),
                    ['status' => 422]
                );
            }

            return true;
        };
    }
}

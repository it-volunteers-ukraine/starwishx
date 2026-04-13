<?php

/**
 * Email validation policy using egulias/email-validator (RFC 5321/5322).
 *
 * Strict format check by default; optional DNS probe behind feature flag.
 *
 * File: inc/shared/Policy/EmailPolicy.php
 */

declare(strict_types=1);

namespace Shared\Policy;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use WP_Error;

final class EmailPolicy
{
    /** Enable MX/A/AAAA DNS lookup to verify the domain accepts mail. */
    public const DNS_CHECK_ENABLED = true;

    /**
     * Validate an email address.
     *
     * Empty string is valid (field optionality is the caller's concern).
     *
     * @return true|WP_Error
     */
    public static function validate(string $email): bool|WP_Error
    {
        $email = trim($email);
        if ($email === '') {
            return true;
        }

        $validator = new EmailValidator();

        // Strict RFC 5321/5322 — rejects deprecated forms (IP literals, comments, etc.)
        if (!$validator->isValid($email, new NoRFCWarningsValidation())) {
            return new WP_Error(
                'email_invalid',
                __('Please enter a valid email address.', 'starwishx')
            );
        }

        // Optional DNS probe — verifies the domain has MX/A/AAAA records
        if (self::DNS_CHECK_ENABLED) {
            $dnsValidator = new EmailValidator();
            if (!$dnsValidator->isValid($email, new DNSCheckValidation())) {
                return new WP_Error(
                    'email_dns_failed',
                    __("This email domain doesn't appear to accept mail.", 'starwishx')
                );
            }
        }

        return true;
    }
}

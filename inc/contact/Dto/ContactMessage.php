<?php

/**
 * ContactMessage — immutable form payload passed to delivery channels.
 *
 * Values are expected to be already validated and sanitized by the
 * controller before construction; channels treat them as trusted strings.
 *
 * File: inc/contact/Dto/ContactMessage.php
 */

declare(strict_types=1);

namespace Contact\Dto;

final class ContactMessage
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $message,
    ) {}
}

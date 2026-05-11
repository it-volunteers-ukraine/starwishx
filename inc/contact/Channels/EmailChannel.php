<?php

/**
 * Email delivery channel for contact form submissions.
 *
 * Recipient resolution: ACF option `email_link`, falling back to the WP
 * `admin_email` site option. Composition mirrors the previous inline
 * wp_mail() call that lived in ContactController prior to multi-channel
 * extraction.
 *
 * File: inc/contact/Channels/EmailChannel.php
 */

declare(strict_types=1);

namespace Contact\Channels;

use Contact\Dto\ContactMessage;
use Shared\Log\Logger;
use WP_Error;

final class EmailChannel implements ChannelInterface
{
    public function getId(): string
    {
        return 'email';
    }

    public function isEnabled(): bool
    {
        return (bool) get_field('use_email_channel', 'option');
    }

    public function isConfigured(): bool
    {
        return $this->resolveRecipient() !== '';
    }

    public function send(ContactMessage $message): bool|WP_Error
    {
        $to = $this->resolveRecipient();
        if ($to === '') {
            return new WP_Error('email_no_recipient', __('No email recipient configured.', 'starwishx'));
        }

        $subject = sprintf(
            /* translators: %s: sender name */
            __('Message from website by %s', 'starwishx'),
            $message->name
        );

        $body = sprintf(
            "%s: %s\n%s: %s\n%s: %s\n\n%s:\n%s",
            __('Name', 'starwishx'),
            $message->name,
            __('Phone', 'starwishx'),
            $message->phone !== '' ? $message->phone : __('Not specified', 'starwishx'),
            __('Email', 'starwishx'),
            $message->email,
            __('Message', 'starwishx'),
            $message->message
        );

        $headers = [sprintf('Reply-To: %s', $message->email)];

        if (wp_mail($to, $subject, $body, $headers)) {
            return true;
        }

        global $phpmailer;
        Logger::error('Contact', 'Email send failed', [
            'channel'   => $this->getId(),
            'smtpError' => $phpmailer->ErrorInfo ?? 'unknown',
        ]);

        return new WP_Error(
            'email_send_failed',
            __('Failed to deliver email.', 'starwishx')
        );
    }

    private function resolveRecipient(): string
    {
        $configured = (string) (get_field('email_link', 'option') ?: '');
        if ($configured !== '') {
            return $configured;
        }

        return (string) get_option('admin_email', '');
    }
}

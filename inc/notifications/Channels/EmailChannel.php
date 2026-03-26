<?php
// File: inc/notifications/Channels/EmailChannel.php

declare(strict_types=1);

namespace Notifications\Channels;

class EmailChannel implements NotificationChannelInterface
{
    public function supports(string $channelType): bool
    {
        return $channelType === 'email';
    }

    public function send(object $notification): bool
    {
        $recipient = get_userdata((int) $notification->recipient_id);
        if (! $recipient || ! $recipient->user_email) {
            error_log("[Notifications] No valid email for recipient ID {$notification->recipient_id}");
            return false;
        }

        $context  = json_decode($notification->context, true) ?: [];
        $siteName = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $postTitle = $context['post_title'] ?? __('an opportunity', 'starwishx');
        $postUrl   = $context['post_url'] ?? home_url();
        $actor     = $context['actor_display_name'] ?? __('Someone', 'starwishx');
        $excerpt   = $context['comment_excerpt'] ?? '';

        switch ($notification->type) {
            case 'new_comment':
                $subject = sprintf(
                    /* translators: 1: site name, 2: post title */
                    __('[%1$s] New review on "%2$s"', 'starwishx'),
                    $siteName,
                    $postTitle
                );
                $message = sprintf(
                    /* translators: %s: recipient display name */
                    __('Hi %s,', 'starwishx'),
                    $recipient->display_name
                ) . "\r\n\r\n";
                $message .= sprintf(
                    /* translators: 1: actor name, 2: post title */
                    __('%1$s left a new review on your opportunity "%2$s":', 'starwishx'),
                    $actor,
                    $postTitle
                ) . "\r\n\r\n";
                if ($excerpt) {
                    $message .= '> ' . $excerpt . "\r\n\r\n";
                }
                $message .= __('View the review:', 'starwishx') . "\r\n";
                $message .= $postUrl . '#comments' . "\r\n";
                break;

            case 'comment_reply':
                $subject = sprintf(
                    /* translators: %s: site name */
                    __('[%s] New reply to your review', 'starwishx'),
                    $siteName
                );
                $message = sprintf(
                    /* translators: %s: recipient display name */
                    __('Hi %s,', 'starwishx'),
                    $recipient->display_name
                ) . "\r\n\r\n";
                $message .= sprintf(
                    /* translators: 1: actor name, 2: post title */
                    __('%1$s replied to your review on "%2$s":', 'starwishx'),
                    $actor,
                    $postTitle
                ) . "\r\n\r\n";
                if ($excerpt) {
                    $message .= '> ' . $excerpt . "\r\n\r\n";
                }
                $message .= __('View the reply:', 'starwishx') . "\r\n";
                $message .= $postUrl . '#comments' . "\r\n";
                break;

            default:
                error_log("[Notifications] Unknown notification type: {$notification->type}");
                return false;
        }

        $sent = wp_mail($recipient->user_email, $subject, $message);

        if (! $sent) {
            error_log("[Notifications] wp_mail failed for notification ID {$notification->id}");
        }

        return $sent;
    }
}

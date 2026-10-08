<?php

/**
 * Contact module — the site's public contact links
 *
 * File: inc/contact/Support/ContactLinks.php
 */

declare(strict_types=1);

namespace Contact\Support;

/**
 * The email / Telegram / LinkedIn links from Theme Settings → Common Info,
 * normalised once for every surface that lists them: the footer, the contact
 * section (template-parts/contact-section.php) and the starwishx/contact-card
 * block.
 *
 * Editors type these fields loosely ("@handle" or "https://t.me/handle", a
 * LinkedIn slug or a full URL), so the cleanup lives here instead of being
 * copied into each template. Channels that aren't configured are left out.
 * Not to be confused with Contact\Channels, which deliver form messages.
 *
 * Per entry: `label` is the translated row label ("Email:"), `text` the
 * address or handle as people should see it, `title` the network name from
 * the *_title field ("Telegram"; '' when empty). Which of them a surface
 * shows is that surface's choice.
 *
 * @phpstan-type ContactLink array{key: string, label: string, url: string, text: string, title: string, icon: string, external: bool}
 */
final class ContactLinks
{
    /** ACF option field names this class reads. */
    private const FIELDS = [
        'email_link',
        'email_name',
        'email_title',
        'telegram_link',
        'telegram_name',
        'telegram_title',
        'linkedin_link',
        'linkedin_name',
        'linkedin_title',
    ];

    /** @var list<ContactLink>|null */
    private static ?array $links = null;

    /**
     * @return list<ContactLink>
     */
    public static function all(): array
    {
        if (self::$links !== null) {
            return self::$links;
        }

        // ACF keeps option-page values out of autoload and reads two rows per
        // field (the value and its `_` field-key reference), one query each.
        // Prime them in a single query; get_field() then hits the cache.
        if (function_exists('wp_prime_option_caches')) {
            $names = [];
            foreach (self::FIELDS as $field) {
                $names[] = 'options_' . $field;
                $names[] = '_options_' . $field;
            }
            wp_prime_option_caches($names);
        }

        $links = [];

        $email = sanitize_email(self::option('email_link'));
        if ($email !== '') {
            $links[] = [
                'key'      => 'email',
                'label'    => __('Email:', 'starwishx'),
                'url'      => 'mailto:' . $email,
                'text'     => self::option('email_name') ?: $email,
                'title'    => self::option('email_title'),
                'icon'     => 'icon-email',
                'external' => false,
            ];
        }

        $telegram = self::option('telegram_link');
        if ($telegram !== '') {
            $handle  = ltrim(str_replace('https://t.me/', '', $telegram), '@');
            $links[] = [
                'key'      => 'telegram',
                'label'    => __('Telegram:', 'starwishx'),
                'url'      => 'https://t.me/' . $handle,
                'text'     => '@' . (self::option('telegram_name') ?: $handle),
                'title'    => self::option('telegram_title'),
                'icon'     => 'icon-telegram',
                'external' => true,
            ];
        }

        $linkedin = self::option('linkedin_link');
        if ($linkedin !== '') {
            $links[] = [
                'key'      => 'linkedin',
                'label'    => __('LinkedIn:', 'starwishx'),
                'url'      => str_starts_with($linkedin, 'http') ? $linkedin : 'https://linkedin.com/in/' . $linkedin,
                'text'     => self::option('linkedin_name') ?: $linkedin,
                'title'    => self::option('linkedin_title'),
                'icon'     => 'icon-linkedin',
                'external' => true,
            ];
        }

        return self::$links = $links;
    }

    private static function option(string $name): string
    {
        $value = function_exists('get_field') ? get_field($name, 'option') : '';

        return is_string($value) ? trim($value) : '';
    }
}

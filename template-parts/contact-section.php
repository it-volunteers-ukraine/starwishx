<?php

/**
 * Contact Section — template part
 *
 * Renders: section header (titles), contact info, form, success/error popups.
 * Reactive form powered by Interactivity API store "contact".
 *
 * Accepts $args (via get_template_part 3rd param):
 *   title_small, title_medium, title_big, subtitle
 *
 * File: template-parts/contact-section.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use Contact\Core\ContactCore;

/* ===========================
   Template args (headings)
   =========================== */

$args = wp_parse_args($args ?? [], [
    'title_small'  => __('Contact', 'starwishx'),
    'title_medium' => __('Open to dialogue', 'starwishx'),
    'title_big'    => __('Do you have any questions or suggestions?<br />Leave a request and we will respond within 2 business days.', 'starwishx'),
    'subtitle'     => '',
]);
$title_small  = $args['title_small'];
$title_medium = $args['title_medium'];
$title_big = wp_kses($args['title_big'], ['br' => []]);
$subtitle     = $args['subtitle'];
/* ===========================
   Contact info (ACF options)
   =========================== */
$email_link    = get_field('email_link', 'option');
$email_name    = get_field('email_name', 'option');
$telegram_link = get_field('telegram_link', 'option');
$telegram_name = get_field('telegram_name', 'option');
$linkedin_link = get_field('linkedin_link', 'option');
$linkedin_name = get_field('linkedin_name', 'option');
$avatars       = get_field('avatars', 'option');

/* Clean telegram URL */
$telegram_full_url = '';
$clean_telegram    = '';
if ($telegram_link) {
    $clean_telegram    = trim($telegram_link);
    $clean_telegram    = str_replace('https://t.me/', '', $clean_telegram);
    $clean_telegram    = ltrim($clean_telegram, '@');
    $telegram_full_url = 'https://t.me/' . $clean_telegram;
}

/* Clean linkedin URL */
$linkedin_full_url = '';
$clean_linkedin    = '';
if ($linkedin_link) {
    $clean_linkedin = trim($linkedin_link);
    if (strpos($clean_linkedin, 'http') === 0) {
        $linkedin_full_url = $clean_linkedin;
    } else {
        $linkedin_full_url = 'https://linkedin.com/in/' . $clean_linkedin;
    }
}

/* ===========================
   Form config
   =========================== */
$char_limit  = ContactCore::MESSAGE_MAX_LENGTH;
$sprite_path = get_template_directory_uri() . '/assets/img/sprites.svg';

$labels = [
    'name'    => __('Your name', 'starwishx'),
    'phone'   => __('Phone', 'starwishx'),
    'email'   => __('Email', 'starwishx'),
    'message' => __('Your message', 'starwishx'),
];

$placeholders = [
    'name'    => __('Enter your name', 'starwishx'),
    'phone'   => __('Enter your phone number', 'starwishx'),
    'email'   => __('Enter your email', 'starwishx'),
    'message' => __('Enter your message', 'starwishx'),
];

$required = [
    'name'    => true,
    'phone'   => false,
    'email'   => true,
    'message' => true,
];

/* Privacy policy link */
$privacy_page_id = (int) get_option('wp_page_for_privacy_policy');
$privacy_url     = $privacy_page_id ? get_permalink($privacy_page_id) : '';

$data_policy_page = get_page_by_path('data-collection-policy');
$data_policy_url   = $data_policy_page ? get_permalink($data_policy_page) : '';

$privacy_html    = '';
if ($privacy_url || $data_policy_url) {
    $parts = [];

    if ($privacy_url) {
        $parts[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url($privacy_url),
            esc_html__('Privacy Policy', 'starwishx')
        );
    } else {
        $parts[] = esc_html__('Privacy Policy', 'starwishx');
    }

    if ($data_policy_url) {
        $parts[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url($data_policy_url),
            esc_html__('Data Collection Policy', 'starwishx')
        );
    } else {
        $parts[] = esc_html__('Data Collection Policy', 'starwishx');
    }

    $policy_html = sprintf(
        /* translators: %s: policy links */
        __('By clicking the “Submit” button, you automatically agree to the %s.', 'starwishx'),
        implode(__(' and ', 'starwishx'), $parts)
    );
}
/* ===========================
   Helper: render label
   =========================== */
$render_label = static function (string $text, bool $is_required): string {
    $html = '<span class="label-text">' . esc_html($text) . '</span>';
    if ($is_required) {
        $html .= '<span class="label-required">*</span>';
    }
    return $html;
};
?>

<section class="contact-section"
    data-wp-interactive="contact"
    aria-labelledby="contact-heading">

    <div class="container contact-container">

        <!-- LEFT COLUMN: titles + contact info -->
        <div class="contact-block">

            <?php if ($title_small || $title_medium || $subtitle): ?>
                <header id="contact-heading" class="contact-titles">
                    <?php if ($title_small): ?>
                        <span class="contact-title-small"><?= esc_html($title_small) ?></span>
                    <?php endif; ?>

                    <?php if ($title_medium): ?>
                        <h2 class="contact-title-medium"><?= esc_html($title_medium) ?></h2>
                    <?php endif; ?>

                    <?php if ($subtitle): ?>
                        <div class="contact-subtitle"><?= esc_html($subtitle) ?></div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <div class="contact-list">
                <?php if ($title_big): ?>
                    <div class="contact-title-big"><?= wp_kses_post($title_big) ?></div>
                <?php endif; ?>

                <div class="contact-group">
                    <?php if ($email_link): ?>
                        <div class="contact-item">
                            <?= sw_svg('icon-email', 24, null, 'icon') ?>
                            <span class="contact-label"><?= esc_html__('Email:', 'starwishx') ?></span>
                            <a href="mailto:<?= esc_attr($email_link) ?>" class="contact-value">
                                <?= esc_html($email_name ?: $email_link) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($telegram_link): ?>
                        <div class="contact-item">
                            <?= sw_svg('icon-telegram', 24, null, 'icon') ?>
                            <span class="contact-label"><?= esc_html__('Telegram:', 'starwishx') ?></span>
                            <a href="<?= esc_url($telegram_full_url) ?>" target="_blank" rel="noopener" class="contact-value">
                                @<?= esc_html($telegram_name ?: $clean_telegram) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedin_link): ?>
                        <div class="contact-item">
                            <?= sw_svg('icon-linkedin', 24, null, 'icon') ?>
                            <span class="contact-label"><?= esc_html__('LinkedIn:', 'starwishx') ?></span>
                            <a href="<?= esc_url($linkedin_full_url) ?>" target="_blank" rel="noopener" class="contact-value">
                                <?= esc_html($linkedin_name ?: $clean_linkedin) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (! empty($avatars) && is_array($avatars)): ?>
                    <div class="contact-avatars">
                        <?php foreach ($avatars as $avatar):
                            $img_id = $avatar['avatar_image']['ID'] ?? $avatar['avatar_image'] ?? false;
                            if (! $img_id) {
                                continue;
                            }
                        ?>
                            <div class="contact-avatar-item">
                                <?= wp_get_attachment_image($img_id, [48, 48], false, ['alt' => 'avatar']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: form -->
        <form class="contact-form" data-wp-on--submit="actions.submit" novalidate>

            <!-- Name -->
            <label class="contact-form-field">
                <span class="label-wrapper">
                    <?= $render_label($labels['name'], $required['name']) ?>
                </span>
                <input type="text"
                    name="name"
                    <?= $required['name'] ? 'required' : '' ?>
                    placeholder="<?= esc_attr($placeholders['name']) ?>"
                    data-wp-on--input="actions.updateField"
                    data-wp-bind--value="state.fields.name"
                    data-wp-class--input-error="state.errors.name">
                <span class="contact-error" hidden
                    data-wp-bind--hidden="!state.errors.name">
                    <svg class="icon" aria-hidden="true">
                        <use href="<?= esc_url($sprite_path) ?>#icon-error"></use>
                    </svg>
                    <span data-wp-text="state.errors.name"></span>
                </span>
            </label>

            <!-- Phone -->
            <label class="contact-form-field">
                <span class="label-wrapper">
                    <?= $render_label($labels['phone'], $required['phone']) ?>
                </span>
                <input type="tel"
                    name="phone"
                    class="contact-phone-input"
                    <?= $required['phone'] ? 'required' : '' ?>
                    placeholder="<?= esc_attr($placeholders['phone']) ?>"
                    data-wp-init="callbacks.initPhone"
                    data-wp-on--input="actions.updateField"
                    data-wp-bind--value="state.fields.phone"
                    data-wp-class--input-error="state.errors.phone">
                <span class="contact-error" hidden
                    data-wp-bind--hidden="!state.errors.phone">
                    <svg class="icon" aria-hidden="true">
                        <use href="<?= esc_url($sprite_path) ?>#icon-error"></use>
                    </svg>
                    <span data-wp-text="state.errors.phone"></span>
                </span>
            </label>

            <!-- Email -->
            <label class="contact-form-field">
                <span class="label-wrapper">
                    <?= $render_label($labels['email'], $required['email']) ?>
                </span>
                <input type="email"
                    name="email"
                    <?= $required['email'] ? 'required' : '' ?>
                    placeholder="<?= esc_attr($placeholders['email']) ?>"
                    data-wp-on--input="actions.updateField"
                    data-wp-bind--value="state.fields.email"
                    data-wp-class--input-error="state.errors.email">
                <span class="contact-error" hidden
                    data-wp-bind--hidden="!state.errors.email">
                    <svg class="icon" aria-hidden="true">
                        <use href="<?= esc_url($sprite_path) ?>#icon-error"></use>
                    </svg>
                    <span data-wp-text="state.errors.email"></span>
                </span>
            </label>

            <!-- Message -->
            <label class="contact-form-textarea">
                <span class="label-wrapper">
                    <?= $render_label($labels['message'], $required['message']) ?>
                </span>
                <span class="textarea-wrapper">
                    <textarea name="message"
                        maxlength="<?= esc_attr((string) $char_limit) ?>"
                        <?= $required['message'] ? 'required' : '' ?>
                        placeholder="<?= esc_attr($placeholders['message']) ?>"
                        data-wp-on--input="actions.updateField"
                        data-wp-bind--value="state.fields.message"
                        data-wp-class--input-error="state.errors.message"></textarea>
                    <span class="contact-counter"
                        data-wp-text="state.counterText">0/<?= esc_html((string) $char_limit) ?></span>
                </span>
                <span class="contact-error" hidden
                    data-wp-bind--hidden="!state.errors.message">
                    <svg class="icon" aria-hidden="true">
                        <use href="<?= esc_url($sprite_path) ?>#icon-error"></use>
                    </svg>
                    <span data-wp-text="state.errors.message"></span>
                </span>
            </label>

            <!-- Privacy -->
            <?php if ($policy_html): ?>
                <div class="contact-privacy">
                    <?= wp_kses($policy_html, [
                        'a' => ['href' => true, 'target' => true, 'rel' => true],
                    ]) ?>
                </div>
            <?php endif; ?>

            <!-- Honeypot -->
            <div style="display:none !important;">
                <input type="text" name="honeypot" tabindex="-1" autocomplete="off">
            </div>

            <!-- Server error (inline, auto-dismiss) -->
            <div class="contact-error contact-error--server" hidden
                role="alert"
                data-wp-bind--hidden="!state.serverError">
                <svg class="icon" aria-hidden="true">
                    <use href="<?= esc_url($sprite_path) ?>#icon-error"></use>
                </svg>
                <span data-wp-text="state.serverError"></span>
            </div>

            <!-- Submit -->
            <button type="submit"
                class="contact-submit"
                data-wp-bind--disabled="state.isSubmitting">
                <span data-wp-bind--hidden="state.isSubmitting">
                    <?= esc_html__('Send', 'starwishx') ?>
                </span>
                <span hidden data-wp-bind--hidden="!state.isSubmitting">
                    <?= esc_html__('Sending...', 'starwishx') ?>
                </span>
            </button>
        </form>

    </div>

    <!-- POPUP: Success -->
    <div class="contact-popup contact-popup-success"
        data-wp-class--is-visible="state.showSuccess"
        data-wp-on--click="actions.dismissPopup"
        role="dialog"
        aria-label="<?= esc_attr__('Success', 'starwishx') ?>">
        <div class="contact-popup-inner">
            <svg class="contact-popup-icon" aria-hidden="true">
                <use href="<?= esc_url($sprite_path) ?>#icon-success"></use>
            </svg>
            <div class="contact-popup-title"
                data-wp-text="state.config.messages.successTitle">
                <?= esc_html__('Thank you!', 'starwishx') ?>
            </div>
            <div class="contact-popup-text"
                data-wp-text="state.config.messages.successText">
                <?= esc_html__('Your message has been sent successfully.', 'starwishx') ?>
            </div>
        </div>
    </div>

    <!-- POPUP: Error (dead — errors shown inline, kept for future redesign) -->
    <!--
    <div class="contact-popup contact-popup-error"
        data-wp-class--is-visible="state.showError"
        data-wp-on--click="actions.dismissPopup"
        role="dialog"
        aria-label="< ?= esc_attr__('Error', 'starwishx') ?>">
        <div class="contact-popup-inner">
            <svg class="contact-popup-icon" aria-hidden="true">
                <use href="< ? = esc_url($sprite_path) ?>#icon-error"></use>
            </svg>
            <div class="contact-popup-title"
                data-wp-text="state.config.messages.errorTitle">
                < ?= esc_html__('Error', 'starwishx') ?>
            </div>
            <div class="contact-popup-text"
                data-wp-text="state.serverError">
                < ?= esc_html__('Something went wrong. Please try again later.', 'starwishx') ?>
            </div>
        </div>
    </div>
    -->

</section>
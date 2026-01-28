<?php

/**
 * Handler for Contact form
 * *Now depends on helpers functions from file: inc/theme-helpers.php
 * 
 * File: inc/acf/blocks/contact_form/contact-handler.php
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_send_contact_form', 'theme_send_contact_form');
add_action('wp_ajax_nopriv_send_contact_form', 'theme_send_contact_form');

function theme_send_contact_form()
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_send_json_error(['message' => 'Invalid request method'], 405);
    }

    $nonce = $_POST['_ajax_nonce'] ?? '';
    if (! wp_verify_nonce($nonce, 'contact_form_nonce')) {
        wp_send_json_error(['message' => 'Security error (nonce). Refresh the page.'], 403);
    }

    $raw_name    = $_POST['name']    ?? '';
    $raw_message = $_POST['message'] ?? '';
    $raw_email   = $_POST['email']   ?? '';
    $raw_phone   = $_POST['phone']   ?? '';

    if (sw_contains_url($raw_name) || sw_contains_url($raw_message)) {
        wp_send_json_error(['message' => 'Посилання у полях заборонені'], 422);
    }
    // Sanitization with our helpers from File: inc/theme-helpers.php
    $name    = sw_sanitize_text_field($raw_name);
    $message = sw_sanitize_textarea_field($raw_message);
    $email   = sanitize_email($raw_email);
    // Optional for phone. "+" is processed Ok. Keep sanitize_text_field() instead in other case
    $phone   = sw_sanitize_text_field($raw_phone);

    if (empty($name) || empty($message)) {
        wp_send_json_error(['message' => 'Заповніть необхідні поля'], 422);
    }

    // Email validation: optional — only validate if provided
    if (!empty($email)) {
        if (!is_email($email) || strpos(explode('@', $email)[1] ?? '', '.') === false) {
            wp_send_json_error(['message' => 'Вказано невірний формат Email'], 422);
        }
    }
    $email_to = get_field('email_link', 'option') ?: get_option('admin_email');
    $subject  = sprintf('Повідомлення з сайту від %s', $name);

    // Future production honeypot (add hidden field in form: <input type="text" name="honeypot" style="display:none">)
    // if ( ! empty( $_POST['honeypot'] ?? '' ) ) {
    //     error_log( 'Contact Form Spam Attempt (honeypot filled)' );
    //     wp_send_json_error( [ 'message' => 'No spam allowed' ], 422 );
    // }

    $body = sprintf(
        "Ім'я: %s\n" .
            "Телефон: %s\n" .
            "Email: %s\n\n" .
            "Повідомлення:\n%s",
        $name,
        $phone ?: 'Не вказано',
        $email ?: 'Не вказано',
        $message
    );

    $headers = [];
    if (!empty($email)) {
        $headers[] = sprintf('Reply-To: %s', $email);
    }

    if (wp_mail($email_to, $subject, $body, $headers)) {
        wp_send_json_success(['message' => 'Повідомлення успішно надіслано!']);
    } else {
        global $phpmailer;
        if (!empty($phpmailer->ErrorInfo)) {
            error_log('Contact Form SMTP Error: ' . $phpmailer->ErrorInfo);
        }
        wp_send_json_error(['message' => 'Помилка сервера. Спробуйте пізніше.'], 500);
    }
}

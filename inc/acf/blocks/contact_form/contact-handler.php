<?php
// inc/acf/blocks/contact_form/contact-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_send_contact_form', 'theme_send_contact_form');
add_action('wp_ajax_nopriv_send_contact_form', 'theme_send_contact_form');

function theme_send_contact_form() {
    if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_send_json_error(['message' => 'Invalid request method'], 405);
    }

    $nonce = $_POST['_ajax_nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'contact_form_nonce' ) ) {
        wp_send_json_error(['message' => 'Security error (nonce). Refresh the page.'], 403);
    }

    $email_to = get_field('email_link', 'option');
    if ( ! $email_to || ! is_email( $email_to ) ) {
        $email_to = get_option('admin_email');
    }

    $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? wp_strip_all_tags($_POST['message']) : '';

    if ( empty($name) || empty($message) ) {
        wp_send_json_error(['message' => 'Заповніть необхідні поля'], 422);
    }

    if ( ! is_email($email) ) {
        wp_send_json_error(['message' => 'Вказано невірний формат Email'], 422);
    }

    $email_parts = explode('@', $email);
    $domain = array_pop($email_parts);
    if ( strpos($domain, '.') === false ) {
        wp_send_json_error(['message' => 'Email повинен містити доменну зону (наприклад .com)'], 422);
    }

    $subject = sprintf('Повідомлення з сайту від %s', $name);
    $body = "Ім'я: $name\nТелефон: $phone\nEmail: $email\n\nПовідомлення:\n$message\n";
    
    $headers = [];
    if ( is_email( $email ) ) {
        $headers[] = 'Reply-To: ' . $email;
    }


    global $phpmailer;

    if ( ! ( $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) ) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }
    
    try {
        $sent = wp_mail( $email_to, $subject, $body, $headers );
    } catch (Exception $e) {
        $sent = false;
        $debug_info = $e->getMessage();
    }

    if ( $sent ) {
        wp_send_json_success(['message' => 'Повідомлення успішно надіслано!']);
    } else {
        $error_msg = 'Помилка надсилання.';
        
        if ( is_object($phpmailer) && !empty($phpmailer->ErrorInfo) ) {
            $error_msg .= ' Детали: ' . $phpmailer->ErrorInfo;
        }
        
        error_log('Contact Form Error: ' . $error_msg);

        wp_send_json_error(['message' => $error_msg], 500);
    }
}
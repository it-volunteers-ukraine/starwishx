<?php
// inc/acf/blocks/contact_form/contact-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_send_contact_form', 'theme_send_contact_form');
add_action('wp_ajax_nopriv_send_contact_form', 'theme_send_contact_form');

function theme_send_contact_form() {
    // Проверка метода
    if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_send_json_error(['message' => 'Invalid request method'], 405);
    }

    // nonce
    $nonce = $_POST['_ajax_nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'contact_form_nonce' ) ) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    // Получаем адрес получателя из ACF options
    $email_to = get_field('email_link', 'option');
    if ( ! $email_to || ! is_email( $email_to ) ) {
        wp_send_json_error(['message' => 'Recipient email not configured'], 500);
    }

    // Сбор данных и санитизация
    $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? wp_strip_all_tags($_POST['message']) : '';

    // Honeypot (если используется)
    if ( ! empty( $_POST['antispam'] ?? '' ) ) {
        wp_send_json_error(['message' => 'Spam detected'], 422);
    }

    // Минимальная валидация
    if ( empty($name) || empty($message) ) {
        wp_send_json_error(['message' => 'Please fill required fields (name and message)'], 422);
    }

    // Формируем тему и тело
    $subject = sprintf('Сообщение с сайта от %s', $name);
    $body = "Имя: $name\n";
    $body .= "Телефон: $phone\n";
    $body .= "Email: $email\n\n";
    $body .= "Сообщение:\n$message\n";

    // Заголовки: From задаст WP Mail SMTP; Reply-To — пользователь, если валиден
    $headers = [];
    if ( is_email( $email ) ) {
        $headers[] = 'Reply-To: ' . $email;
    }

    // Отправляем
    $sent = wp_mail( $email_to, $subject, $body, $headers );

    if ( $sent ) {
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    } else {
        // можно логировать для отладки
        wp_send_json_error(['message' => 'Ошибка отправки письма'], 500);
    }
}

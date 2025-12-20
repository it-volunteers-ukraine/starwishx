<?php
// inc/acf/blocks/contact_form/contact-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_send_contact_form', 'theme_send_contact_form');
add_action('wp_ajax_nopriv_send_contact_form', 'theme_send_contact_form');

function theme_send_contact_form() {
    // 1. Проверки безопасности
    if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_send_json_error(['message' => 'Invalid request method'], 405);
    }

    $nonce = $_POST['_ajax_nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'contact_form_nonce' ) ) {
        wp_send_json_error(['message' => 'Ошибка безопасности (nonce). Обновите страницу.'], 403);
    }

    // 2. Сбор данных
    $email_to = get_field('email_link', 'option');
    if ( ! $email_to || ! is_email( $email_to ) ) {
        // Если email в админке не задан, шлем на админский email сайта для теста
        $email_to = get_option('admin_email');
    }

    $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? wp_strip_all_tags($_POST['message']) : '';

    if ( empty($name) || empty($message) ) {
        wp_send_json_error(['message' => 'Заполните обязательные поля'], 422);
    }

    // 3. Подготовка письма
    $subject = sprintf('Сообщение с сайта от %s', $name);
    $body = "Имя: $name\nТелефон: $phone\nEmail: $email\n\nСообщение:\n$message\n";
    
    $headers = [];
    if ( is_email( $email ) ) {
        $headers[] = 'Reply-To: ' . $email;
    }

    // --- МАГИЯ ОТЛАДКИ НАЧИНАЕТСЯ ЗДЕСЬ ---

    // Глобальная переменная для отлова ошибок phpmailer
    global $phpmailer;

    // Сбрасываем phpmailer, если он был инициализирован ранее
    if ( ! ( $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) ) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }
    
    // Попытка отправки
    try {
        $sent = wp_mail( $email_to, $subject, $body, $headers );
    } catch (Exception $e) {
        $sent = false;
        $debug_info = $e->getMessage();
    }

    // 4. Проверка результата
    if ( $sent ) {
        wp_send_json_success(['message' => 'Сообщение успешно отправлено!']);
    } else {
        // Если wp_mail вернул false, пытаемся достать причину
        $error_msg = 'Ошибка отправки.';
        
        // Проверяем глобальный объект phpmailer на наличие текста ошибки
        if ( is_object($phpmailer) && !empty($phpmailer->ErrorInfo) ) {
            $error_msg .= ' Детали: ' . $phpmailer->ErrorInfo;
        }
        
        // Логируем ошибку в файл debug.log (если включен на сервере)
        error_log('Contact Form Error: ' . $error_msg);

        // Возвращаем ошибку в JS (чтобы попал в красный попап)
        wp_send_json_error(['message' => $error_msg], 500);
    }
}
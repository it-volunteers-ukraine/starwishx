<?php

/**
 * Template Part: Generic Popup
 *
 * Usage:
 *   get_template_part('template-parts/element-popup', null, [
 *       'title' => __('Title', 'starwishx'),
 *       'text'  => __('Body text.', 'starwishx'),
 *       'id'    => 'my-popup',   // optional, default: 'site-popup'
 *       'class' => 'my-class',   // optional extra class
 *   ]);
 *
 * File: template-parts/element-popup.php
 */

defined('ABSPATH') || exit;

$title = $args['title'] ?? '';
$text  = $args['text']  ?? '';
$class = isset($args['class']) ? ' ' . esc_attr($args['class']) : '';
$id    = $args['id'] ?? 'site-popup';

$login_url    = home_url('/gateway/?view=login');
$register_url = home_url('/gateway/?view=register');
?>
<div
    id="<?php echo esc_attr($id); ?>"
    class="popup<?php echo $class; ?>"
    data-wp-interactive="popup"
    data-wp-bind--hidden="!state.isOpen"
    hidden>

    <div class="popup__backdrop" data-wp-on--click="actions.close"></div>

    <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($id); ?>-title">

        <button
            type="button"
            class="popup__close"
            data-wp-on--click="actions.close"
            aria-label="<?php esc_attr_e('Close', 'starwishx'); ?>">
            <svg class="popup__close-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
        </button>

        <div class="popup__body">
            <?php if ($title) : ?>
                <h2 id="<?php echo esc_attr($id); ?>-title" class="popup__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="popup__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
        </div>

        <div class="popup__footer">
            <a href="<?php echo esc_url($login_url); ?>" class="btn popup__footer--button">
                <?php esc_html_e('Login', 'starwishx'); ?>
            </a>
            <a href="<?php echo esc_url($register_url); ?>" class="btn-secondary popup__footer--button">
                <?php esc_html_e('Register', 'starwishx'); ?>
            </a>
        </div>

    </div>
</div>
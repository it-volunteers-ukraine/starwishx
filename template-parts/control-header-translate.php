<?php

/**
 * Template Part: Control Header Translate
 *
 * Purpose: Flags that translate the page with Google's website translator.
 * Google's script loads only when a visitor picks a language, or already has
 * one active (src/js/_translate.js), so no page waits for Google.
 * Location: Header (Desktop & Mobile variants supported via $args['variant']).
 *
 * File: template-parts/control-header-translate.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$variant = ($args['variant'] ?? 'desktop') === 'mobile' ? 'mobile' : 'desktop';

// Google's code for the page language: 'uk' for the uk locale.
$source = strtolower((string) strtok(get_locale(), '_'));

// Each language named in itself. The order is the flags' order on screen.
$languages = [
    'sv' => 'Svenska',
    'en' => 'English',
    'uk' => 'Українська',
];
?>
<div class="translate-switcher translate-switcher--<?= esc_attr($variant) ?> notranslate"
    role="group"
    aria-label="<?= esc_attr__('Language', 'starwishx') ?>"
    translate="no"
    data-translate-source="<?= esc_attr($source) ?>">
    <?php foreach ($languages as $code => $name) : ?>
        <button type="button"
            class="translate-switcher__button"
            lang="<?= esc_attr($code) ?>"
            title="<?= esc_attr($name) ?>"
            aria-label="<?= esc_attr($name) ?>"
            aria-pressed="<?= $code === $source ? 'true' : 'false' ?>"
            data-translate-lang="<?= esc_attr($code) ?>">
            <?= sw_svg('icon-flag-' . $code, 22, 16) ?>
        </button>
    <?php endforeach; ?>
</div>

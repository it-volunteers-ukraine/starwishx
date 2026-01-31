<?php

/**
 * Template Part: Control Header Auth
 * 
 * Purpose: Context-aware authentication toggle (Login / Cabinet / Logout).
 * Location: Header (Desktop & Mobile variants supported via $args['variant']).
 * 
 * File: template-parts/control-header-auth.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * EXTRACT ARGS & CONTEXT
 */
$variant      = $args['variant'] ?? 'desktop';
$is_mobile    = ($variant === 'mobile');
$is_logged_in = is_user_logged_in();
$is_launchpad = is_page_template('templates/page-launchpad.php');

/**
 * DEFINE STATE DATA
 * initialize defaults to avoid "undefined variable" notices.
 */
$btn = [
    'text' => '',
    'url'  => '',
    'slug' => '' // used for class modifiers
];


if (!$is_logged_in) {
    // STATE: GUEST
    $btn['text'] = __('Login', 'starwishx');
    $btn['url']  = home_url('/gateway/');
    $btn['slug'] = 'login';
} elseif ($is_launchpad) {
    // STATE: LOGGED IN & CURRENTLY IN CABINET
    $btn['text'] = __('Logout', 'starwishx');
    $btn['url']  = wp_logout_url(home_url());
    $btn['slug'] = 'logout';
} else {
    // STATE: LOGGED IN & ON WEBSITE
    $can_use_lp = false;

    // Architectural Safety: Check module and eligibility
    if (function_exists('launchpad')) {
        $access = launchpad()->accessController();
        if ($access && $access->shouldUseLaunchpad()) {
            $can_use_lp = true;
        }
    }

    if ($can_use_lp) {
        $btn['text'] = __('Cabinet', 'starwishx');
        $btn['url']  = home_url('/launchpad/');
        $btn['slug'] = 'cabinet';
    } else {
        // Fallback for Admins/Editors: They don't use Launchpad, so show Logout
        $btn['text'] = __('Logout', 'starwishx');
        $btn['url']  = wp_logout_url(home_url());
        $btn['slug'] = 'logout';
    }
}

/**
 * DYNAMIC CLASS GENERATION
 * Pattern: {base}-{variant}-btn {base}--{slug}
 * Example: header-desktop-btn header--cabinet
 */
$base_class = $is_mobile ? 'header-mobile-btn' : 'header-desktop-btn';
$final_class = sprintf('%1$s %1$s--%2$s', $base_class, $btn['slug']);

/**
 * RENDER
 */
?>
<a href="<?php echo esc_url($btn['url']); ?>"
    class="<?php echo esc_attr($final_class); ?>"
    <?php echo ($btn['slug'] === 'logout') ? 'role="button"' : ''; ?>>
    <span><?php echo esc_html($btn['text']); ?></span>
</a>
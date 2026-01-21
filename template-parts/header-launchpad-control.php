<?php

/**
 * Template Part: Header Launchpad Control
 *
 * Displays authentication control button with three states:
 * 1. Login (not authenticated) → /gateway
 * 2. Cabinet (authenticated, outside Launchpad) → /launchpad
 * 3. Logout (authenticated, inside Launchpad) → logout action
 *
 * This version uses modernized $args passing and safe dependency checks.
 * 
 * File: template-parts/header-launchpad-control.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 1. CONFIGURATION & VARIANTS
 * Modernized: We use the $args array passed via get_template_part()
 */
$variant      = $args['variant'] ?? 'desktop';
$is_mobile    = ($variant === 'mobile');
$is_logged_in = is_user_logged_in();
$is_launchpad = is_page_template('templates/page-launchpad.php');

/**
 * 2. STATE LOGIC
 */

// STATE 1: GUEST (NOT LOGGED IN)
if (!$is_logged_in) {
    $button_text  = get_field('header_button', 'option') ?: __('Login', 'starwishx');
    $button_url   = home_url('/gateway/');
    $button_class = $is_mobile ? 'login-button-mobile' : 'header-login-btn';
}

// STATE 2: INSIDE CABINET (LOGGED IN + ON LAUNCHPAD PAGE)
elseif ($is_launchpad) {
    $button_text  = __('Logout', 'starwishx');
    $button_url   = wp_logout_url(home_url());
    $button_class = $is_mobile ? 'logout-button-mobile' : 'header-logout-btn';
}

// STATE 3: OUTSIDE CABINET (LOGGED IN + ON REGULAR SITE PAGE)
else {
    /**
     * DEPENDENCY SAFETY (Graceful Degradation)
     * Check if the Launchpad module is active and the user is eligible (Subscriber/Contributor).
     * If module is missing or user is Admin/Editor, we fall back to a "Logout" button.
     */
    $can_use_launchpad = false;

    if (function_exists('launchpad')) {
        $launchpad_instance = launchpad();
        $access_controller  = $launchpad_instance->accessController();

        if ($access_controller && $access_controller->shouldUseLaunchpad()) {
            $can_use_launchpad = true;
        }
    }

    if ($can_use_launchpad) {
        $button_text  = __('Cabinet', 'starwishx');
        $button_url   = home_url('/launchpad/');
        $button_class = $is_mobile ? 'cabinet-button-mobile' : 'header-cabinet-btn';
    } else {
        // Fallback for Admins or if module is disabled
        $button_text  = __('Logout', 'starwishx');
        $button_url   = wp_logout_url(home_url());
        $button_class = $is_mobile ? 'logout-button-mobile' : 'header-logout-btn';
    }
}

/**
 * 3. RENDER
 */
?>
<a href="<?php echo esc_url($button_url); ?>" class="<?php echo esc_attr($button_class); ?>">
    <?php echo esc_html($button_text); ?>
</a>
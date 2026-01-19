<?php

/**
 * Template Name: Gateway Page
 *
 * Authentication interface using the WordPress Interactivity API.
 * 
 * This page serves as the entry point for Login, Registration, 
 * and Password Recovery. It is designed to be highly reactive, 
 * providing a Single Page Application (SPA) feel while maintaining 
 * full SEO and SSR (Server Side Rendering) compatibility.
 * 
 * File: templates/page-gateway.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 1. ENVIRONMENT & BOOTSTRAP
 * 
 * We ensure the Gateway helper is available. This allows us to access
 * the singleton GatewayCore instance which manages the Form Registry.
 */
if (!function_exists('gateway')) {
    require_once get_template_directory() . '/inc/gateway/helpers.php';
}

/**
 * 2. AUTHENTICATION SHIELD
 * 
 * If the user is already logged in, they have no business being on the 
 * Gateway page. We redirect them based on their authority level.
 */
if (is_user_logged_in()) {
    $user = wp_get_current_user();

    /**
     * Cross-Module Integration: Launchpad
     * 
     * If the Launchpad module is active, we check if this specific user
     * should be sent to the Launchpad Admin instead of the standard Home/Admin.
     */
    if (function_exists('launchpad')) {
        try {
            $launchpad = \launchpad();
            $access    = $launchpad->accessController();

            if ($access && $access->shouldUseLaunchpad($user->ID)) {
                wp_safe_redirect(home_url('/launchpad/'));
                exit;
            }
        } catch (\Throwable $e) {
            // Silently fail if Launchpad logic is unavailable
        }
    }

    // Default Redirects
    if (current_user_can('manage_options')) {
        wp_safe_redirect(admin_url());
    } else {
        wp_safe_redirect(home_url('/'));
    }
    exit;
}

/**
 * 3. ROUTING & REGISTRY
 * 
 * We identify which form should be 'active' based on the ?view= parameter.
 */
$gateway      = \gateway();
$registry     = $gateway->registry();
$all_forms    = $registry->getAll();
$current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'login';

// Fallback to login if a requested view doesn't exist in our registry
if (!$registry->has($current_view)) {
    $current_view = 'login';
}

/**
 * 4. STATE HYDRATION (SSR)
 * 
 * We aggregate the initial state for all registered forms. This data
 * is injected into the HTML as a JSON object, allowing the Interactivity 
 * API to 'hydrate' the UI without requiring an initial API call.
 */
$state = $gateway->getState($current_view);

// Runtime settings used by gateway-store.js
$state['gatewaySettings'] = [
    'nonce'   => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url('gateway/v1/'),
];

// Seed the 'gateway' namespace with our state
wp_interactivity_state('gateway', $state);

/**
 * 5. HEAD INJECTION (FOUC PROTECTION)
 * 
 * Prevents the 'Flash of Unhydrated Content'. We hide non-active forms
 * using CSS immediately, before the JavaScript engine takes over.
 */
add_action('wp_head', function () {
?>
    <style id="gateway-fouc-shield">
        /* Hide any wrapper with the 'hidden' attribute immediately */
        .gateway-form-wrapper[hidden] {
            display: none !important;
        }

        /* Subtle entry animation for the gateway card */
        .gateway-container {
            animation: gateway-appear 0.25s ease-out;
        }

        @keyframes gateway-appear {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
<?php
}, 1);

// get_header('gateway');
locate_template('templates/header-gateway.php', true, true);

?>

<main
    id="primary"
    class="site-main gateway-container"
    data-wp-interactive="gateway">

    <div class="gateway-layout">
        <div class="gateway-card">

            <!-- BRANDING SECTION -->
            <header class="gateway-header">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="gateway-logo" rel="home">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <h1 class="gateway-title"><?php bloginfo('name'); ?></h1>
                    <?php endif; ?>
                </a>
            </header>

            <!-- REACTIVE FORM CONTAINER -->
            <div class="gateway-forms" role="region" aria-live="assertive">
                <?php
                foreach ($all_forms as $id => $form) :
                    /** @var \Gateway\Forms\AbstractForm $form */

                    // The Authoritative Key mapping (e.g., 'isLoginActive')
                    $stateKey = $form->getStateKey();

                    // Determine server-side visibility
                    $isHidden = ($id !== $current_view);
                ?>
                    <div
                        class="gateway-form-wrapper"
                        id="gateway-view-<?php echo esc_attr($id); ?>"
                        <?php echo $isHidden ? 'hidden' : ''; ?>
                        data-wp-bind--hidden="!state.<?php echo $stateKey; ?>">

                        <?php
                        /**
                         * Renders the specific form (Login, Register, etc.)
                         * including its internal data-wp-* directives.
                         */
                        echo $form->render();
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

</main>

<?php
// get_footer('gateway');
locate_template('templates/footer-gateway.php', true, true);

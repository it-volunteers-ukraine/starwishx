<?php

/**
 * Template Name: Launchpad Page
 *
 * Core User Workspace & Management Interface.
 * 
 * This template acts as the entry point for the Launchpad module.
 * It handles SSR hydration for the Interactivity API and ensures
 * a flicker-free initial load (FOUC prevention).
 * 
 * File: templates\page-launchpad.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// 1. ENVIRONMENT CHECK
if (!function_exists('launchpad')) {
    require_once get_template_directory() . '/inc/launchpad/helpers.php';
}

// 2. AUTHENTICATION GUARD
if (!is_user_logged_in()) {
    // Safety: Redirect unauthorized users back to login with a return-to parameter
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

// 3. INITIALIZATION & REGISTRY
$launchpad     = \launchpad();
$registry      = $launchpad->registry();
$all_panels    = $registry->getAll();

// Get active panel from URL, fallback to default 'profile'
$current_panel_id = isset($_GET['panel']) ? sanitize_key($_GET['panel']) : 'opportunities';

// Validate requested panel exists, fallback if necessary
if (!$registry->has($current_panel_id)) {
    $current_panel_id = 'profile';
}

// 4. STATE HYDRATION (SSR)
$user_id = get_current_user_id();

/**
 * Build the authoritative state.
 * getState() handles:
 * - Current User data
 * - Panel definitions and initial data
 * - Visibility mapping (panelMap)
 */
$state = $launchpad->getState($user_id, $current_panel_id);

// Inject runtime settings required by store.js
$state['launchpadSettings'] = [
    'nonce'    => wp_create_nonce('wp_rest'),
    'restUrl'  => rest_url('launchpad/v1/'),
    'userId'   => $user_id,
    'loginUrl' => wp_login_url(home_url('/launchpad/')),
];

// Initialize Interactivity API state for the 'launchpad' namespace
wp_interactivity_state('launchpad', $state);

// 5. HEAD INJECTION (FOUC SHIELD)
/**
 * To prevent 'Flash of Unhydrated Content', we inject an immediate style.
 * This ensures panels marked as 'hidden' via PHP are hidden by the browser 
 * BEFORE the JavaScript store initializes.
 */
add_action('wp_head', function () {
?>
    <style id="launchpad-fouc-shield">
        /* Authoritative Hiding */
        .launchpad-panel-wrapper[hidden],
        .launchpad-sidebar [hidden] {
            display: none !important;
        }

        /* Smooth reveal of the workspace */
        .launchpad-container {
            animation: launchpad-fade-in 0.2s ease-in;
        }

        @keyframes launchpad-fade-in {
            from {
                opacity: 0.95;
                transform: translateY(2px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
<?php
}, 1);

get_header();
?>

<main
    id="primary"
    class="site-main launchpad-container"
    data-wp-interactive="launchpad">

    <div class="container launchpad-layout">

        <!-- Sidebar Navigation -->
        <aside class="launchpad-sidebar">
            <nav class="launchpad-tabs" role="tablist">
                <?php foreach ($all_panels as $id => $panel) :
                    /** @var \Launchpad\Contracts\PanelInterface $panel */
                    $stateKey = $panel->getStateKey();
                ?>

                    <button
                        class="launchpad-tab"
                        role="tab"
                        aria-selected="<?php echo ($id === $current_panel_id) ? 'true' : 'false'; ?>"
                        data-panel-id="<?php echo esc_attr($id); ?>"
                        data-wp-on--click="actions.switchPanel"
                        data-wp-class--active="state.<?php echo $stateKey; ?>">
                        <span class="launchpad-tab__icon">
                            <svg width="16" height="16">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#<?php echo esc_attr($panel->getIcon()); ?>"></use>
                            </svg>
                        </span>
                        <span class="tab-text"><?php echo esc_html($panel->getLabel()); ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <!-- <div class="launchpad-sidebar-footer"> -->
            <!-- <a href="< ?php echo esc_url(home_url('/')); ?>" class="launchpad-back">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    < ?php esc_html_e('Back to Site', 'starwishx'); ?>
                </a> -->
            <!-- 
                <a class="btn-secondary__small" href="< ?php echo esc_url(wp_logout_url(home_url())); ? >" class="launchpad-logout">
                    <span class="dashicons dashicons-exit"></span>
                    < ?php esc_html_e('Logout', 'starwishx'); ? >
                </a> -->
            <!-- </div> -->
        </aside>

        <!-- Main Workspace Area -->
        <div class="launchpad-main" role="region" aria-live="polite">
            <div class="launchpad-panels panel-content">

                <?php foreach ($all_panels as $id => $panel) :
                    /** @var \Launchpad\Contracts\PanelInterface $panel */
                    $stateKey = $panel->getStateKey();
                    $isHidden = ($id !== $current_panel_id);
                ?>
                    <div
                        class="launchpad-panel-wrapper"
                        id="panel-<?php echo esc_attr($id); ?>"
                        <?php echo $isHidden ? 'hidden' : ''; ?>
                        data-wp-bind--hidden="!state.<?php echo $stateKey; ?>">

                        <?php
                        /**
                         * Logic Check: 
                         * The render() method should handle its own internal 
                         * sub-view visibility (e.g., list vs edit).
                         */
                        echo $panel->render();
                        ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

    </div>
</main>

<?php
get_footer();

<?php

/**
 * Template Name: Launchpad Page
 *
 * Core User Workspace & Management Interface
 */

defined('ABSPATH') || exit;

// Ensure Launchpad helpers are loaded
if (!function_exists('launchpad')) {
    require_once get_template_directory() . '/inc/launchpad/helpers.php';
}

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

$user_id = get_current_user_id();
$current_panel = isset($_GET['panel']) ? sanitize_key($_GET['panel']) : 'profile';

// Get Launchpad instance
$launchpad = \launchpad();
$registry = $launchpad->registry();
$all_panels = $registry->getAll();

// Build and inject state
$state = $launchpad->getState($user_id, $current_panel);
// We ensure launchpadSettings is merged into the state properly
$state['launchpadSettings'] = [
    'nonce' => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url('launchpad/v1/'),
    'userId' => get_current_user_id(),
    'loginUrl' => wp_login_url(home_url('/launchpad/')),
];
wp_interactivity_state('launchpad', $state);

get_header();
?>

<main
    id="primary"
    class="site-main launchpad-container"
    data-wp-interactive="launchpad">
    <div class="container launchpad-layout">

        <!-- Sidebar Navigation -->
        <aside class="launchpad-sidebar">
            <nav class="launchpad-tabs">
                <?php foreach ($all_panels as $id => $panel) :
                    // Create the CamelCase key for the reactive boolean flag (e.g. 'opportunities' -> 'Opportunities')
                    $camelId = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id)));
                ?>
                    <button
                        class="launchpad-tab"
                        <?php // FIX: Use the boolean flag instead of string comparison for reliable sync 
                        ?>
                        data-wp-class--active="state.is<?php echo $camelId; ?>Active"
                        data-wp-on--click="actions.switchPanel"
                        data-panel-id="<?php echo esc_attr($id); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($panel->getIcon()); ?>"></span>
                        <span class="tab-text"><?php echo esc_html($panel->getLabel()); ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div class="launchpad-sidebar-footer">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="launchpad-back">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Back to Site', 'starwishx'); ?>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="launchpad-logout">
                    <span class="dashicons dashicons-exit"></span>
                    <?php esc_html_e('Logout', 'starwishx'); ?>
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="launchpad-main">
            <div class="launchpad-panels panel-content">
                <?php foreach ($all_panels as $id => $panel) :
                    $camelId = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $id)));
                ?>
                    <div
                        class="launchpad-panel-wrapper"
                        id="<?php echo esc_attr($id); ?>"
                        data-wp-bind--hidden="!state.is<?php echo $camelId; ?>Active">
                        <?php echo $panel->render(); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<?php
get_footer();

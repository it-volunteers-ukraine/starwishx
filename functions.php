<?php

function _themeprefix_theme_setup()
{
  /**
   * ! does not need for WP 6.7+
   * see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
   **/
  // load_theme_textdomain(wp_get_theme()->get('TextDomain'), get_template_directory() . '/languages');

  add_theme_support('automatic-feed-links');
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');

  register_nav_menus(
    array(
      'menu-1' => esc_html__('Primary', '_themedomain'),
      'menu-header' => esc_html__('Header', '_themedomain'),
      'menu-footer' => esc_html__('Footer', '_themedomain'),
    )
  );

  add_theme_support(
    'html5',
    array(
      'search-form',
      'comment-form',
      'comment-list',
      'gallery',
      'caption',
      'style',
      'script',
    )
  );


  add_theme_support('customize-selective-refresh-widgets');

  add_theme_support(
    'custom-logo',
    array(
      'height'      => 250,
      'width'       => 250,
      'flex-width'  => true,
      'flex-height' => true,
    )
  );
}
add_action('after_setup_theme', '_themeprefix_theme_setup');

/** add fonts */
function add_google_fonts()
{
  wp_enqueue_style(
    'google_web_fonts',
    'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Urbanist:wght@900&display=swap',
    array(),
    null
  );
}
add_action('wp_enqueue_scripts', 'add_google_fonts');


/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';


function _themeprefix_theme_scripts()
{
  $version = wp_get_theme()->get('Version');


  wp_register_style('_themeprefix-style', get_stylesheet_uri(), [], $version);
  // wp_register_style('normalize-css', get_stylesheet_directory_uri() . '/assets/css/normalize.css');
  // wp_register_style('swiper-css', get_stylesheet_directory_uri() . '/assets/css/swiper.min.css');
  wp_register_style('swiper', get_stylesheet_directory_uri() . '/assets/css/swiper-bundle.min.css');
  // wp_register_style('lightbox', get_stylesheet_directory_uri() . '/assets/css/lightbox.min.css');

  wp_register_style('app', get_stylesheet_directory_uri() . '/assets/css/app.css', [], $version, 'all');
  wp_register_style('app-logged-in', get_stylesheet_directory_uri() . '/assets/css/app-logged-in.css', [], $version, 'all');

  wp_enqueue_style('_themeprefix-style');
  // wp_enqueue_style('normalize-css');
  wp_enqueue_style('swiper');
  // wp_enqueue_style('lightbox');
  wp_enqueue_style('app');
  if (is_user_logged_in()) {
    wp_enqueue_style('app-logged-in');
  }

  // wp_register_script('app', get_stylesheet_directory_uri() . '/assets/js/app.js', array('jquery'), $version, true);
  wp_register_script('app', get_stylesheet_directory_uri() . '/assets/js/app.js', [], $version, true);
  // wp_register_script('swiper-js', get_stylesheet_directory_uri() . '/assets/js/swiper.min.js', array('jquery'), $version, true);
  wp_register_script('swiper', get_stylesheet_directory_uri() . '/assets/js/vendor/swiper-bundle.min.js', [], $version, [
    'in_footer' => true,
    'strategy'   => 'defer',
  ]);
  // wp_register_script('lightbox-js', get_stylesheet_directory_uri() . '/assets/js/lightboxmmc.js', array('jquery'), $version, true);

  // wp_enqueue_script('lightbox-js');
  wp_enqueue_script('swiper');
  wp_enqueue_script('app');
}
add_action('wp_enqueue_scripts', '_themeprefix_theme_scripts');


require_once get_template_directory() . '/inc/acf/blocks/blocks-init.php';


add_action('init', '_themeprefix_acf_options_page', 20);
function _themeprefix_acf_options_page()
{
  if (!function_exists('acf_add_options_page')) return;

  acf_add_options_page(array(
    'page_title'    => 'Theme General Settings',
    'menu_title'    => 'Theme Settings',
    'menu_slug'     => 'theme-general-settings',
    'capability'    => 'edit_posts',
    'redirect'      => false
  ));

  acf_add_options_sub_page(array(
    'page_title'    => 'Theme Header Settings',
    'menu_title'    => 'Header',
    'parent_slug'   => 'theme-general-settings',
  ));

  acf_add_options_sub_page(array(
    'page_title'    => 'Theme Footer Settings',
    'menu_title'    => 'Footer',
    'parent_slug'   => 'theme-general-settings',
  ));
  acf_add_options_sub_page(array(
    'page_title' => 'Theme Common Info Settings',
    'menu_title' => 'Common Info',
    'parent_slug' => 'theme-general-settings',
  ));
}


/**
 * UI helpers for header template.
 * These render search trigger, language switcher, and mobile variants.
 * Phase 2 will migrate these into the Menu module's Interactivity API layer.
 */
if (! function_exists('yourtheme_search_trigger')) {
  function yourtheme_search_trigger()
  {
?>
    <div class="menu-item menu-item-search" role="button" tabindex="0" aria-label="<?php esc_attr_e('Пошук', '_themedomain'); ?>">
      <svg class="search-icon" width="16" height="16" aria-hidden="true">
        <use xlink:href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-find'); ?>"></use>
      </svg>
    </div>
  <?php
  }
}

if (! function_exists('yourtheme_language_switcher')) {
  function yourtheme_language_switcher()
  {
  ?>
    <div class="menu-item menu-item-lang" role="group" aria-label="<?= esc_attr__('Language', 'starwishx'); ?>">
      <div class="lang">
        <button class="lang-btn">УКР</button>
        <span class="lang-separator">|</span>
        <button class="lang-btn">ENG</button>
      </div>
    </div>
  <?php
  }
}

if (! function_exists('yourtheme_mobile_search_lang')) {
  function yourtheme_mobile_search_lang()
  {
  ?>
    <div class="search-language-container">
      <div class="search-icon">
        <svg width="16" height="16" aria-hidden="true">
          <use xlink:href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-find'); ?>"></use>
        </svg>
      </div>
      <!-- <div class="language-switch">
        <button class="lang-btn">УКР</button>
        <span class="lang-separator">|</span>
        <button class="lang-btn">ENG</button>
      </div> -->
      <?php echo do_shortcode('[prisna-google-website-translator]'); ?>
    </div>
<?php
  }
}

// disable dark mode
// add_action('wp_head', function () {
//   echo '<meta name="color-scheme" content="light">';
// });

// Composer autoload (league/uri, etc.)
require_once get_template_directory() . '/vendor/autoload.php';

// init for Shared infrastructure
require_once get_template_directory() . '/inc/shared/setup.php';

// init for Users - user-lifecycle state (is_activated, moderation_status)
// Loads before Gateway so user_register / after_password_reset / login hooks
// are registered before Gateway starts firing them.
require_once get_template_directory() . '/inc/users/setup.php';

// init for Menu - header navigation module
require_once get_template_directory() . '/inc/menu/setup.php';

// init for Gateway - auth module
require_once get_template_directory() . '/inc/gateway/setup.php';

// init for Favorites - shared favorites module
require_once get_template_directory() . '/inc/favorites/setup.php';

// init for Comments - interactive comments/reviews module
require_once get_template_directory() . '/inc/comments/setup.php';

// init for Notifications - comment notification queue & delivery
require_once get_template_directory() . '/inc/notifications/setup.php';

// init for Chat - notification center & support messaging
require_once get_template_directory() . '/inc/chat/setup.php';

// init for Launchpad - user's dashboard
require_once get_template_directory() . '/inc/launchpad/setup.php';

// Feature flag: SEO-friendly category URLs for Listing (/opportunities/{slug}/)s
// After toggling, flush rewrite rules via Settings → Permalinks.
define('LISTING_PRETTY_CATEGORY_URLS', true);

// init for Tour — guided onboarding with shepherd.js
require_once get_template_directory() . '/inc/tour/setup.php';

// init for Listing - search & filter opportunities
require_once get_template_directory() . '/inc/listing/setup.php';

// init for Projects - single project page
require_once get_template_directory() . '/inc/projects/setup.php';

// init for Contact - contact form module
require_once get_template_directory() . '/inc/contact/setup.php';

require_once get_template_directory() . '/inc/news-taxonomy-metabox.php';


/**
 * Place CPT Opportunity Single Template along with others
 */
add_filter('single_template', function ($template) {
  if (is_singular('opportunity')) {
    $alt = locate_template('templates/single-opportunity.php');
    if ($alt) {
      return $alt;
    }
  }
  return $template;
});

/**
 * Place CPT Project Single Template
 */
add_filter('single_template', function ($template) {
  if (is_singular('project')) {
    $alt = locate_template('templates/single-project.php');
    if ($alt) {
      return $alt;
    }
  }
  return $template;
});

/**
 * Place CPT News Single Template
 */
add_filter('single_template', function ($template) {
  if (is_singular('news')) {
    $alt = locate_template('templates/single-news.php');
    if ($alt) {
      return $alt;
    }
  }
  return $template;
});

// Для пагинации и фильтров
require_once get_template_directory() . '/inc/rewrites.php';

// для загрузки AJAX обработчиков
require get_template_directory() . '/inc/ajax.php';

require_once get_template_directory() . '/inc/helpers.php';

require_once get_template_directory() . '/inc/theme-helpers.php';

/**
 * Disable default WordPress search query on SPA-style pages and CPT archives
 * to prevent interference with client-side search using `?s=`.
 *
 * Each entry in $target_slugs defines how WordPress should resolve the URL
 * after the search flag is stripped:
 *
 *   'type' => 'page'      — standard WP page; resolved via `pagename`
 *   'type' => 'post_type' — CPT archive; resolved via `post_type` (the registered
 *                           post type ID, which may differ from the archive slug)
 *
 * Example: the `opportunity` CPT uses `opportunities` as its archive slug
 * (`has_archive_slug`), so the array key is the URL segment (`opportunities`)
 * while `post_type` holds the actual registered ID (`opportunity`).
 *
 * @param array $vars The array of parsed query variables.
 * @return array The modified array of query variables.
 */
function sw_clear_search_flags_on_spa($vars)
{
  $target_slugs = [
    'listing'       => ['type' => 'page'],
    'launchpad'     => ['type' => 'page'],
    'gateway'       => ['type' => 'page'],

    // Archive slug `opportunities` maps to CPT ID `opportunity`.
    // These differ because `has_archive_slug` is set independently
    // from the post type name in the CPT registration.
    'opportunities' => ['type' => 'post_type', 'post_type' => 'opportunity'],
  ];

  if (!isset($vars['s']) && !isset($vars['country'])) {
    return $vars;
  }

  $request_uri = $_SERVER['REQUEST_URI'] ?? '';
  $path        = parse_url($request_uri, PHP_URL_PATH);

  // Strip the subdirectory prefix for non-root WP installs.
  // e.g. WP lives at /app/, request is /app/opportunities → we want /opportunities.
  $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
  $install_dir = dirname($script_name);

  if ($install_dir !== '/' && strpos($path, $install_dir) === 0) {
    $path = substr($path, strlen($install_dir));
  }

  $path          = trim($path, '/');
  $segments      = explode('/', $path);
  $first_segment = $segments[0] ?? '';

  // Only act on explicitly registered slugs.
  if (!array_key_exists($first_segment, $target_slugs)) {
    return $vars;
  }

  // Remove the search flag so WP doesn't enter search mode.
  unset($vars['s']);
  unset($vars['country']);

  $config = $target_slugs[$first_segment];

  if ($config['type'] === 'post_type') {
    // Point WP to the CPT archive using the registered post type ID,
    // not the archive slug. Without this, WP has no `post_type` var
    // to resolve after `s` is removed and will return a 404.
    $vars['post_type']         = $config['post_type'];
    $vars['post_type_archive'] = $config['post_type'];
  } elseif ($config['type'] === 'page' && !isset($vars['pagename'])) {
    // For regular pages, restore `pagename` so WP can resolve the page.
    // WP drops `pagename` in favour of `s` when a search query is present,
    // so we need to put it back after unsetting `s`.
    $vars['pagename'] = $first_segment;
  }

  return $vars;
}
add_filter('request', 'sw_clear_search_flags_on_spa', 10, 1);

/**
 * Should partially disable XML-RPC & remove trace from <head>
 */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', '__return_empty_array');
remove_action('wp_head', 'rsd_link');

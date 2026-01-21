<?php

function _themeprefix_theme_setup()
{

  load_theme_textdomain(wp_get_theme()->get('TextDomain'), get_template_directory() . '/languages');


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
  wp_register_style('normalize-css', get_stylesheet_directory_uri() . '/assets/css/normalize.css');
  // wp_register_style('swiper-css', get_stylesheet_directory_uri() . '/assets/css/swiper.min.css');
  wp_register_style('swiper-css', get_stylesheet_directory_uri() . '/assets/css/swiper-bundle.min.css');
  // wp_register_style('lightbox-css', get_stylesheet_directory_uri() . '/assets/css/lightbox.min.css');

  wp_register_style('app-css', get_stylesheet_directory_uri() . '/assets/css/app.css', [], $version, 'all');

  wp_enqueue_style('_themeprefix-style');
  wp_enqueue_style('normalize-css');
  wp_enqueue_style('swiper-css');
  wp_enqueue_style('lightbox-css');
  wp_enqueue_style('app-css');


  wp_register_script('app-js', get_stylesheet_directory_uri() . '/assets/js/app.js', array('jquery'), $version, true);
  // wp_register_script('swiper-js', get_stylesheet_directory_uri() . '/assets/js/swiper.min.js', array('jquery'), $version, true);
  wp_register_script('swiper-js', get_stylesheet_directory_uri() . '/assets/js/vendor/swiper-bundle.min.js', [], $version, true);
  // wp_register_script('lightbox-js', get_stylesheet_directory_uri() . '/assets/js/lightbox.js', array('jquery'), $version, true);

  // wp_enqueue_script('lightbox-js');
  wp_enqueue_script('swiper-js');
  wp_enqueue_script('app-js');
}
add_action('wp_enqueue_scripts', '_themeprefix_theme_scripts');


require_once get_template_directory() . '/inc/acf/blocks/blocks-init.php';
// Contact form AJAX handler
require get_template_directory() . '/inc/acf/blocks/contact_form/contact-handler.php';



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


add_filter('wp_nav_menu_objects', 'add_acf_images_to_submenu_items_only', 10, 2);
function add_acf_images_to_submenu_items_only($items, $args)
{
  // if (defined('WP_DEBUG') && WP_DEBUG) {
  //   error_log('=== ACF Images Filter Debug ===');
  // }
  foreach ($items as &$item) {

    if (empty($item->menu_item_parent)) {
      continue;
    }


    $image_id = get_field('images', 'nav_menu_item_' . $item->ID);
    // if (defined('WP_DEBUG') && WP_DEBUG) {
    //   error_log("Десктоп: Пункт {$item->title} (ID: {$item->ID}) | Image ID: " . ($image_id ?: 'пусто'));
    // }
    if ($image_id) {
      $image_url = wp_get_attachment_image_url($image_id, 'full');

      $item->title = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->title) . '" class="submenu-image" />' . $item->title;
    }
  }
  // if (defined('WP_DEBUG') && WP_DEBUG) {
  //   error_log('=== End ACF Images Filter ===');
  // }
  return $items;
}



add_action('acf/init', 'acf_add_menu_item_mobile_field');
function acf_add_menu_item_mobile_field()
{
  if (!function_exists('acf_add_local_field_group')) return;

  acf_add_local_field_group([
    'key' => 'group_menu_item_mobile',
    'title' => 'Мобильное меню',
    'fields' => [
      [
        'key' => 'field_mobile_submenu_type',
        'label' => 'Тип подменю в мобильной версии',
        'name' => 'mobile_submenu_type',
        'type' => 'select',
        'choices' => [
          '' => 'Обычный пункт (подменю не отображается)',
          'custom_grid' => 'Кастомная сетка (2 ряда, как "Можливості")',
        ],
        'default_value' => false,
        'allow_null' => true,
        'ui' => true,
      ],
    ],
    'location' => [
      [
        [
          'param' => 'nav_menu_item',
          'operator' => '==',
          'value' => 'all',
        ],
      ],
    ],
  ]);
}


require get_template_directory() . '/inc/walker-header.php';
require get_template_directory() . '/inc/walker-mobile.php';


add_filter('wp_nav_menu_objects', 'header_set_menu_flags', 10, 2);
function header_set_menu_flags($items, $args)
{
  // дети
  $children = [];
  foreach ($items as $i) if ($i->menu_item_parent) $children[$i->menu_item_parent][] = $i;

  foreach ($items as &$item) {
    if (!empty($children[$item->ID])) $item->classes[] = 'has-children';
    $item->mobile_submenu_type = get_field('mobile_submenu_type', $item);
  }
  return $items;
}

add_filter('wp_nav_menu_items', function ($items, $args) {
  if ($args->theme_location === 'menu-header') {
    $items = str_replace('<ul class="sub-menu"></ul>', '', $items);
  }
  return $items;
}, 10, 2);


add_filter('wp_kses_allowed_html', function ($tags) {
  $tags['img'] = ['src' => 1, 'alt' => 1, 'class' => 1, 'width' => 1, 'height' => 1];
  return $tags;
}, 10, 1);
/**
 * Заглушка: триггер поиска (десктоп и мобильная версия)
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

/**
 * Заглушка: переключатель языка (десктоп)
 */
if (! function_exists('yourtheme_language_switcher')) {
  function yourtheme_language_switcher()
  {
  ?>
    <div class="menu-item menu-item-lang" aria-label="<?php esc_attr_e('Мова', '_themedomain'); ?>">
      <div class="lang">
        <button class="lang-btn">УКР</button>
        <span class="lang-separator">|</span>
        <button class="lang-btn">ENG</button>
      </div>
    </div>
  <?php
  }
}

/**
 * Заглушка: поиск + язык для мобильной версии
 */
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
      <div class="language-switch">
        <button class="lang-btn">УКР</button>
        <span class="lang-separator">|</span>
        <button class="lang-btn">ENG</button>
      </div>
    </div>
<?php
  }
}
// Регистрация и локализация скрипта контактной формы
add_action('wp_enqueue_scripts', function () {

  // Путь к файлу contact.js
  $script_path = '/inc/acf/blocks/contact_form/contact.js';
  $script_file = get_template_directory() . $script_path;

  // Регистрируем скрипт
  wp_register_script(
    'theme-contact-form',
    get_template_directory_uri() . $script_path,
    [], // при необходимости добавь зависимости (например ['jquery'])
    file_exists($script_file) ? filemtime($script_file) : false,
    true
  );

  // Пробрасываем AJAX-настройки
  wp_localize_script('theme-contact-form', 'ContactFormAjax', [
    'url'   => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('contact_form_nonce')
  ]);

  // Подключаем скрипт
  wp_enqueue_script('theme-contact-form');
});


// init for Shared infrastructure
require_once get_template_directory() . '/inc/shared/setup.php';

// init for Gateway - auth module
require_once get_template_directory() . '/inc/gateway/setup.php';

// init for Launchpad - user's dashboard
require_once get_template_directory() . '/inc/launchpad/setup.php';
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

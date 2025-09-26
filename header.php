<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$button = get_field('header_button', 'option' );
?>
<header class="header">
    <div class="container">
        <div class="header-inner">
 
  <div class="header-left">
    <div class="header-logo">
      <?php 
        if ( has_custom_logo() ) {
          echo get_custom_logo();
        }
      ?>
    </div>
  </div>
  <div class="header-center">
    <div class="header-menu">
      <input class="side-menu" type="checkbox" id="side-menu"/>
      <label class="hamb" for="side-menu"><span class="hamb-line"></span></label>
      <nav class="nav">
        <ul class="menu">
          <?php
          wp_nav_menu([
            'theme_location' => 'menu-header',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'echo'           => true,
          ]);
          ?>
          <li class="menu-item menu-item-search">
  <div class="div-search">
    <svg class="search-icon">
      <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-good"></use>
    </svg>
  </div>
</li>
<li class="menu-item menu-item-lang">
  <div class="lang">
    <button class="lang-btn">УКР</button>
    <span class="lang-separator">|</span>
    <button class="lang-btn">ENG</button>
  </div>
</li>
        </ul>
      </nav>
    </div>
  </div>
  <div class="header-right">
    <a href="#" class="header-login-btn">Увійти</a>
  </div>
</div>
    </div>
</header>

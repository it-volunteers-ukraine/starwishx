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
        <?php wp_nav_menu( [
          'theme_location'       => 'menu-header',                          
          'container'            => false,                           
          'menu_class'           => 'menu',
          'menu_id'              => false,    
          'echo'                 => true,                            
          'items_wrap'           => '<ul id="%1$s" class="header_list %2$s">%3$s</ul>',  
        ]); ?>
      </nav>
    </div>
  </div>
  <div class="header-right">
    <a href="#" class="header-login-btn">Увійти</a>
  </div>
</div>
    </div>
</header>

<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$login_button_text = get_field('header_button', 'option') ?: 'Увійти';
?>

<header class="header">
    <div class="container">
        <div class="header-inner">

            
            <div class="header-left">
                <div class="header-logo">
                    <?php if (has_custom_logo()) echo get_custom_logo(); ?>
                </div>
            </div>

            
            <input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-toggle" />

            
            <label for="mobile-menu-toggle" class="burger-menu-button">
                <span>Меню</span>
                <svg class="burger-icon" width="16" height="16" aria-hidden="true">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-burger"></use>
                </svg>
            </label>

            
            <div class="mobile-header-buttons">
                <div class="search-language-container">
                    <div class="search-icon">
                        <svg width="16" height="16" aria-hidden="true">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search"></use>
                        </svg>
                    </div>
                    <div class="language-switch">
                        <button class="lang-btn">УКР</button>
                        <span class="lang-separator">|</span>
                        <button class="lang-btn">ENG</button>
                    </div>
                </div>
                <label for="mobile-menu-toggle" class="close-menu-button">
                    <svg class="close-icon" width="16" height="16" aria-hidden="true">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-close"></use>
                    </svg>
                </label>
            </div>

            
            <div class="header-center">
                <nav class="site-head-nav">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'menu-header',
                        'container'      => false,
                        'menu_class'     => 'menu',
                        'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                        'walker'         => new Header_Menu_Walker(),
                        'fallback_cb'    => false,
                    ]);
                    ?>
                </nav>
            </div>

            
            <div class="header-right">
                <a href="#" class="header-login-btn"><?php echo esc_html($login_button_text); ?></a>
            </div>

            
            <div class="burger-menu">
                <?php
                wp_nav_menu([
                    'theme_location' => 'menu-header',
                    'container'      => false,
                    'menu_class'     => 'mobile-menu-list',
                    'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                    'walker'         => new Mobile_Menu_Walker(),
                    'fallback_cb'    => false,
                    'depth'          => 2,
                ]);
                ?>
                <a href="#" class="login-button-mobile"><?php echo esc_html($login_button_text); ?></a>
            </div>

        </div>
    </div>
</header>
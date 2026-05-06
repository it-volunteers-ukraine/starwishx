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
    <header class="header" data-wp-interactive="menu">
        <div class="container">
            <div class="header-inner">
                <!-- Logo -->
                <div class="header-left">
                    <div class="header-logo">
                        <?php if (has_custom_logo()) echo get_custom_logo(); ?>
                    </div>
                </div>
                <!-- Checkbox for mobile menu (hidden) -->
                <input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-toggle" />
                <!-- Menu button (burger) -->
                <label for="mobile-menu-toggle" class="burger-menu-button">
                    <span>Меню</span>
                    <svg class="burger-icon" width="16" height="16" aria-hidden="true">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-burger"></use>
                    </svg>
                </label>
                <!-- Mobile buttons (search, arrow, cross) -->
                <div class="mobile-header-buttons">
                    <?php yourtheme_mobile_search_lang(); ?>
                    <label for="mobile-menu-toggle" class="close-menu-button">
                        <svg class="close-icon" width="16" height="16" aria-hidden="true">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-x"></use>
                        </svg>
                    </label>
                </div>
                <!-- Center: menu + add. elements (search, language) -->
                <div class="header-center">
                    <div class="menu-container">
                        <nav class="site-head-nav">
                            <?php wp_nav_menu([
                                'theme_location' => 'menu-header',
                                'container'      => false,
                                'menu_class'     => 'menu',
                                'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                                'walker'         => new \Menu\Walkers\DesktopWalker(),
                                'fallback_cb'    => false,
                            ]); ?>
                        </nav>
                        <div class="header-addons">
                            <?php yourtheme_search_trigger(); ?>
                            <!-- < ?php yourtheme_language_switcher(); ?> -->
                            <?php echo do_shortcode('[prisna-google-website-translator]'); ?>
                        </div>
                    </div>
                </div>
                <!-- 3-state Button Login / Cabinet / Logout -->
                <div class="header-right">
                    <?php get_template_part('template-parts/control-header-auth', null, ['variant' => 'desktop']); ?>
                </div>
                <!-- Mobile menu -->
                <div class="burger-menu">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'menu-header',
                        'container'      => false,
                        'menu_class'     => 'mobile-menu-list',
                        'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                        'walker'         => new \Menu\Walkers\MobileWalker(),
                        'fallback_cb'    => false,
                        'depth'          => 2,
                    ]);
                    ?>
                    <?php get_template_part('template-parts/control-header-auth', null, ['variant' => 'mobile']); ?>
                </div>
            </div>
        </div>
        <?php get_template_part('template-parts/search-modal'); ?>

        <?php if (!is_user_logged_in()) : ?>
            <?php
            $login_url    = home_url('/gateway/?view=login');
            $register_url = home_url('/gateway/?view=register');
            ?>
            <div
                class="popup menu-auth-popup"
                data-wp-bind--hidden="!state.showAuthPopup"
                hidden>

                <div class="popup__backdrop" data-wp-on--click="actions.closeAuthPopup"></div>

                <div class="popup__dialog" role="dialog" aria-modal="true" aria-labelledby="menu-auth-popup-title">

                    <button
                        type="button"
                        class="popup__close"
                        data-wp-on--click="actions.closeAuthPopup"
                        aria-label="<?php esc_attr_e('Close', 'starwishx'); ?>">
                        <svg class="popup__close-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </button>

                    <div class="popup__body">
                        <h2 id="menu-auth-popup-title" class="popup__title">
                            <?php esc_html_e('Hi!', 'starwishx'); ?>
                        </h2>
                        <p class="popup__text">
                            <?php esc_html_e('Register or log in to your account to add an opportunity.', 'starwishx'); ?>
                        </p>
                    </div>

                    <div class="popup__footer">
                        <a href="<?php echo esc_url($login_url); ?>" class="btn popup__footer--button">
                            <?php esc_html_e('Login', 'starwishx'); ?>
                        </a>
                        <a href="<?php echo esc_url($register_url); ?>" class="btn-secondary popup__footer--button">
                            <?php esc_html_e('Register', 'starwishx'); ?>
                        </a>
                    </div>

                </div>
            </div>
        <?php endif; ?>
    </header>
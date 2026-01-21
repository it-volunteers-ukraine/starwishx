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

                <!-- Логотип -->
                <div class="header-left">
                    <div class="header-logo">
                        <?php if (has_custom_logo()) echo get_custom_logo(); ?>
                    </div>
                </div>

                <!-- Чекбокс для мобильного меню (скрытый) -->
                <input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-toggle" />

                <!-- Кнопка "Меню" (бургер) -->
                <label for="mobile-menu-toggle" class="burger-menu-button">
                    <span>Меню</span>
                    <svg class="burger-icon" width="16" height="16" aria-hidden="true">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-burger"></use>
                    </svg>
                </label>

                <!-- Мобильные кнопки (поиск, язык, крестик) -->
                <div class="mobile-header-buttons">
                    <?php yourtheme_mobile_search_lang(); ?>
                    <label for="mobile-menu-toggle" class="close-menu-button">
                        <svg class="close-icon" width="16" height="16" aria-hidden="true">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-x"></use>
                        </svg>
                    </label>
                </div>

                <!-- Центр: меню + доп. элементы (поиск, язык) -->
                <div class="header-center">
                    <div class="menu-container">
                        <nav class="site-head-nav">
                            <?php wp_nav_menu([
                                'theme_location' => 'menu-header',
                                'container'      => false,
                                'menu_class'     => 'menu',
                                'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                                'walker'         => new Header_Menu_Walker(),
                                'fallback_cb'    => false,
                            ]); ?>
                        </nav>
                        <div class="header-addons">
                            <?php yourtheme_search_trigger(); ?>
                            <?php yourtheme_language_switcher(); ?>
                        </div>
                    </div>
                </div>
                <!-- 3-state Button Login / Cabinet / Logout -->
                <div class="header-right">
                    <?php get_template_part('template-parts/header-launchpad-control', null, ['variant' => 'desktop']); ?>
                </div>
                <!-- Mobile menu -->
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
                    <?php get_template_part('template-parts/header-launchpad-control', null, ['variant' => 'desktop']); ?>
                </div>
            </div>
        </div>
    </header>
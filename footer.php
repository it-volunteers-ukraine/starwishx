<?php
$footer_title = esc_html(get_field('title', 'options'));
$email_title = esc_html(get_field('email_title', 'options'));
$email_text = esc_html(get_field('email_name', 'options'));
$email_link = esc_html(get_field('email_link', 'options'));
$telegram_title = esc_html(get_field('telegram_title', 'options'));
$telegram_text = esc_html(get_field('telegram_name', 'options'));
$telegram_link = esc_html(get_field('telegram_link', 'options'));
$linkedin_title = esc_html(get_field('linkedin_title', 'options'));
$linkedin_text = esc_html(get_field('linkedin_name', 'options'));
$linkedin_link = esc_html(get_field('linkedin_link', 'options'));

// print_r("assadas");
// print_r($email_text);
// print_r($email_link);
// print_r($telegram_text);
// print_r($telegram_link);

?>

<footer class="footer">
    <div class="container">
        <h4 class="footer-title"><?php echo $footer_title; ?></h4>
        <div class="footer-inner">
            <div class="footer-socwraper">
                <h4 class="footer-title title-socblock"><?php echo $footer_title; ?></h4>
                <ul class="socblock">
                    <?php if ($email_text && $email_link): ?>
                        <li class="socblock-item">
                            <a href="mailto:<?php echo $email_link; ?>" class="socblock-link socblock-link-email" target="_blank" rel="noopener noreferrer">
                                <svg class="socblock-icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-email"></use>
                                </svg>
                                <span>
                                    <?php echo $email_text; ?></a>
                            </span>
                        </li>
                    <?php endif; ?>
                    <?php if ($telegram_text && $telegram_link): ?>
                        <li class="socblock-item">
                            <a href="<?php echo $telegram_link; ?>" class="socblock-link socblock-link-telegram" target="_blank" rel="noopener noreferrer">
                                <svg class="socblock-icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-telegram"></use>
                                </svg>
                                <span>
                                    <?php echo $telegram_title; ?></a>
                            </span>
                        </li>

                    <?php endif; ?>
                    <?php if ($linkedin_text && $linkedin_link): ?>
                        <li class="socblock-item">
                            <a href="<?php echo $linkedin_link; ?>" class="socblock-link socblock-link-linkedin" target="_blank" rel="noopener noreferrer">
                                <svg class="socblock-icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-linkedin"></use>
                                </svg>
                                <span>
                                    <?php echo $linkedin_title; ?></a>
                            </span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <nav class="nav">
                <?php wp_nav_menu([
                    'theme_location'       => 'menu-footer',
                    'container'            => false,
                    'menu_class'           => 'menu',
                    'menu_id'              => false,
                    'echo'                 => true,
                    'items_wrap'           => '<ul id="%1$s" class="footer_list %2$s">%3$s</ul>',
                ]);
                ?>
            </nav>
        </div>

        <div class="footer-logo-wrapper">
            <!-- <div class="hologram"></div> -->
            <div class="footer-logo-container">
                <span class="footer-logo">STAR WISH X</span>
                <!-- <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star_wish_x-1920.png" alt="" class="footer-logo-img"> -->
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star-satellite.svg" class="footer-logo-satellite" alt="">
            </div>
        </div>

        <div class="footer-copyright">
            <div class="footer-copyright1">
                <p class="copyright-text"><?php echo esc_html(get_field('parts_1', 'options')); ?> <span> </span></p>
                <div class="copyright-text1">
                    <p class="copyright-text">
                        <?php echo esc_html(get_field('parts_2', 'options')); ?>
                        <a href="<?php echo esc_html(get_field('parts_2_link', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('parts_2_text_link', 'options')); ?></a>
                    </p>
                </div>
            </div>
            <div class="footer-copyright2">
                <a href="<?php echo esc_html(get_field('privacy_policy_page', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('privacy_policy_text', 'options')); ?></a>
                <!-- <div class="copyright-text"> -->
                <!-- </div> -->
                <a href="<?php echo esc_html(get_field('privacy_data_protection_page', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('privacy_data_protection_text', 'options')); ?></a>
                <!-- <div class="copyright-text"> -->
                <!-- </div> -->
            </div>
            <?php echo esc_html(get_field('copyright', 'options')); ?>
        </div>

    </div>
</footer>

<?php wp_footer(); ?>

</body>

</html>
<?php
$footer_title = esc_html(get_field('title', 'options'));

/*
 * Contact rows. On the options page *_title is the network name ("Telegram"),
 * *_name is the handle or address, and *_link is the URL.
 *
 * Each label falls back to the other field, and the guard below tests the
 * resolved label rather than a field that is never displayed. Telegram and
 * LinkedIn used to guard on *_name while printing *_title, so clearing the
 * title left a link holding an icon and no text at all.
 *
 * Email leads with the address rather than the word "Email" - the address is
 * the useful label there. The other two lead with the network name.
 *
 * Values are kept raw here and escaped at the point of output: esc_html() on a
 * URL cannot break out of an href, but it does not validate the protocol
 * either, so a javascript: value saved on the options page rendered live.
 * The (string) casts keep esc_url() and esc_html() off ACF's null for an
 * empty field, which PHP 8.1 deprecates.
 */
$email_link     = (string) get_field('email_link', 'options');
$email_label    = (string) get_field('email_name', 'options')
    ?: (string) get_field('email_title', 'options');

$telegram_link  = (string) get_field('telegram_link', 'options');
$telegram_label = (string) get_field('telegram_title', 'options')
    ?: (string) get_field('telegram_name', 'options');

$linkedin_link  = (string) get_field('linkedin_link', 'options');
$linkedin_label = (string) get_field('linkedin_title', 'options')
    ?: (string) get_field('linkedin_name', 'options');

?>

<footer class="footer site-footer">
    <div class="container">
        <h2 class="footer-title"><?php echo $footer_title; ?></h2>
        <div class="footer-inner">
            <div class="footer-socwraper">
                <h3 class="footer-title title-socblock"><?php echo $footer_title; ?></h3>
                <ul class="socblock">
                    <?php if ($email_label && $email_link): ?>
                        <li class="socblock-item">
                            <a href="<?php echo esc_url('mailto:' . $email_link); ?>" class="socblock-link socblock-link-email" target="_blank" rel="noopener noreferrer">
                                <?php sw_svg_e('icon-email', 24, null, 'socblock-icon'); ?>
                                <span>
                                    <?php echo esc_html($email_label); ?>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($telegram_label && $telegram_link): ?>
                        <li class="socblock-item">
                            <a href="<?php echo esc_url($telegram_link); ?>" class="socblock-link socblock-link-telegram" target="_blank" rel="noopener noreferrer">
                                <?php sw_svg_e('icon-telegram', 24, null, 'socblock-icon'); ?>
                                <span>
                                    <?php echo esc_html($telegram_label); ?>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($linkedin_label && $linkedin_link): ?>
                        <li class="socblock-item">
                            <a href="<?php echo esc_url($linkedin_link); ?>" class="socblock-link socblock-link-linkedin" target="_blank" rel="noopener noreferrer">
                                <?php sw_svg_e('icon-linkedin', 24, null, 'socblock-icon'); ?>
                                <span>
                                    <?php echo esc_html($linkedin_label); ?>
                                </span>
                            </a>
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
            <div class="footer-logo-container">
                <span class="footer-logo notranslate">STAR WISH X</span>
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star-satellite.svg" class="footer-logo-satellite" alt="satellite icon" loading="lazy">
            </div>
        </div>

        <div class="footer-copyright">
            <div class="footer-copyright1">
                <p class="copyright-text"><?php echo esc_html(get_field('parts_1', 'options')); ?> <span> </span></p>
                <div class="copyright-text1">
                    <p class="copyright-text">
                        <?php echo esc_html(get_field('parts_2', 'options')); ?>
                        <a href="<?php echo esc_url((string) get_field('parts_2_link', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('parts_2_text_link', 'options')); ?></a>
                    </p>
                </div>
            </div>
            <div class="footer-copyright2">
                <a href="<?php echo esc_url((string) get_field('privacy_policy_page', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('privacy_policy_text', 'options')); ?></a>
                <a href="<?php echo esc_url((string) get_field('privacy_data_protection_page', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('privacy_data_protection_text', 'options')); ?></a>
            </div>
            <?php echo esc_html(get_field('copyright', 'options')); ?>
        </div>

    </div>
</footer>

<button
    type="button"
    id="scroll-top"
    class="scroll-top"
    aria-label="<?php esc_attr_e('Back to top', 'starwishx'); ?>">
    <?php sw_svg_e('icon-arrow_down', 24, null, 'scroll-top__icon'); ?>
</button>

<?php wp_footer(); ?>

</body>

</html>
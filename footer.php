<?php
$footer_title = esc_html(get_field('title', 'options'));

/*
 * Contact rows from Theme Settings → Common Info, normalised by the Contact
 * module (sw_contact_links(), shared with the contact section and the
 * contact-card block); unconfigured channels are already left out. URLs are
 * still escaped at the point of output.
 *
 * The label is the footer's own choice: email leads with the address - the
 * useful label there - and the others with the network name ("Telegram"),
 * falling back to the handle, so a row never holds an icon and no text.
 */
$contact_links = sw_contact_links();

?>

<footer class="footer site-footer">
    <div class="container">
        <h2 class="footer-title"><?php echo $footer_title; ?></h2>
        <div class="footer-inner">
            <div class="footer-socwraper">
                <h3 class="footer-title title-socblock"><?php echo $footer_title; ?></h3>
                <?php if ($contact_links): ?>
                    <ul class="socblock">
                        <?php foreach ($contact_links as $link):
                            $label = $link['key'] === 'email' ? $link['text'] : ($link['title'] ?: $link['text']);
                        ?>
                            <li class="socblock-item">
                                <a href="<?php echo esc_url($link['url']); ?>" class="socblock-link socblock-link-<?php echo esc_attr($link['key']); ?>"<?php echo $link['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php sw_svg_e($link['icon'], 24, null, 'socblock-icon'); ?>
                                    <span>
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
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
                <?php 
                /*
                    <a href="<?php echo esc_url((string) get_field('privacy_policy_page', 'options')); ?>" class="copyright-link" target="_blank"><?php echo esc_html(get_field('privacy_policy_text', 'options')); ?></a>
                    */
                ?>
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
    <?php sw_svg_e('icon-arrow-down', 24, null, 'scroll-top__icon'); ?>
</button>

<?php wp_footer(); ?>

</body>

</html>
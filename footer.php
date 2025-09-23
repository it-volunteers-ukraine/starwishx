<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <h2 class="footer-title"><?php echo esc_html(get_field('title', 'options')) ?></h2>

            <nav class="nav">
                <?php wp_nav_menu([
                    'theme_location'       => 'menu-footer',
                    'container'            => false,
                    'menu_class'           => 'menu',
                    'menu_id'              => false,
                    'echo'                 => true,
                    'items_wrap'           => '<ul id="%1$s" class="header_list %2$s">%3$s</ul>',
                ]);
                ?>
            </nav>
            <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star_wish_x-1920.png" alt="" class="footer-logo">
            <div class="footer-copyright">
                <div class="copyright-text">
                    <p class="copyright-text"><?php echo esc_html(get_field('parts_1', 'options')) ?></p>
                </div>
                <div class="copyright-text">
                    <?php echo esc_html(get_field('parts_2', 'options')) ?>
                    <a href="<?php echo esc_html(get_field('parts_2_link', 'options')) ?>" class="copyright-link"><?php echo esc_html(get_field('parts_2_text_link', 'options')) ?></a>
                </div>
                <div class="copyright-text">
                    <a href="<?php echo esc_html(get_field('privacy_policy_page', 'options')) ?>" class="copyright-link"><?php echo esc_html(get_field('privacy_policy_text', 'options')) ?></a>
                </div>
                <div class="copyright-text">
                    <a href="<?php echo esc_html(get_field('privacy_data_protection_page', 'options')) ?>" class="copyright-link"><?php echo esc_html(get_field('privacy_data_protection_text', 'options')) ?></a>
                </div>
                <?php echo esc_html(get_field('copyright', 'options')) ?>
            </div>

        </div>
</footer>

<?php wp_footer(); ?>

</body>

</html>
<?php

get_header();
?>

<main id="primary" class="site-main">
    <section class="section-404">
        <div class="container">
            <div class="info-404">
                <p class="h4"><?= esc_html__('It seems the page you are looking for does not exist.', 'starwishx'); ?></p>
                <!-- <svg class="icon-satellite">
                    <use xlink:href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-satellite-blue"></use>
                </svg> -->
                <!-- <svg class="heading-404__logo">
                    <title>0</title>
                    <use href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-element_planet_3-circle"></use>
                </svg> -->
                <?php sw_svg_e('icon-satellite-blue', class: "icon-satellite"); ?>
            </div>
            <header class="page-header">
                <h1 class="heading-404" aria-label="404">
                    4
                    <figure class="figure-404__logo">
                        <img src="<?= get_template_directory_uri(); ?>/assets/img/planet-bg-radial-gradient.svg" class="icon-bg" alt="Icon star">
                        <img src="<?= get_template_directory_uri(); ?>/assets/img/planet-mask-gradient.svg" class="icon-mask" alt="Mask for icon star">
                    </figure>
                    4
                </h1>
            </header>
            <a class="btn" href="/home"><?= esc_html__('To the main page', 'starwishx'); ?></a>
        </div>
    </section>
</main>

<?php
get_footer();

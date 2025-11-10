<?php

get_header();
?>

<main id="primary" class="site-main">
    <section class="section-404">
        <div class="container">
            <div class="info-404">
                <p class="h4"><?php esc_html_e('Схоже сторінка, яку Ви шукаєте, не існує.', '_themedomain'); ?></p>
                <svg class="icon-satellite">
                    <use xlink:href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-satellite-blue"></use>
                </svg>
            </div>
            <header class="page-header">
                <h1 class="heading-404" aria-label="404">4
                    <svg class="heading-404__logo">
                        <title>0</title>
                        <use xlink:href="/wp-content/themes/starwishx/assets/img/sprites.svg#icon-element_planet_3-circle"></use>
                    </svg>4
                </h1>
            </header>
            <a class="btn" href="/home"><?php esc_html_e('На головну сторінку', '_themedomain'); ?></a>
        </div>
    </section>
</main>

<?php
get_footer();

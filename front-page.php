<?php

/**
 * Template Name: Home Page
 *
 * File: front-page.php
 */

get_header();
?>

<main id="primary" class="site-main">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>

    <?php get_template_part('template-parts/contact-section', null, [
        'title_small'  => __('Contacts', 'starwishx'),
        'title_medium' => __('Get in Touch', 'starwishx'),
        'title_big'    => __('Do you have any questions or suggestions?<br />Leave a request and we will respond within 2 business days.', 'starwishx'),
    ]); ?>
</main>

<?php get_footer(); ?>
<?php

/**
 * Template Name: Contact Page
 *
 * File: page-contact.php
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
        'title_medium' => __('Contact Us', 'starwishx'),
    ]); ?>
</main>

<?php get_footer(); ?>

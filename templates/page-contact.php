<?php

/**
 * Template Name: Contact Page
 *
 * Breadcrumbs from the template (as on every other template - not a block in
 * the page content), then the page content (contact card etc.) and the
 * contact form section.
 *
 * File: templates/page-contact.php
 */

get_header();
sw_breadcrumbs();
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

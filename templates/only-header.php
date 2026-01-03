<?php
/*
Template Name: Only Header
Template Post Type: post, page
*/

get_header(); // Подключаем header
?>

<main id="primary" class="site-main">
    <?php
    // Контент страницы или поста
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
    endif;
    ?>
</main><!-- #primary -->

<?php
// Обратите внимание: get_footer() не вызываем!
wp_footer(); // но wp_footer() желательно оставить, чтобы скрипты работали
?>

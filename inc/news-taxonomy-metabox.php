<?php
// inc/news-taxonomy-metabox.php

add_action('save_post_news', function ($post_id, $post, $update) {

    // защита
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if ($post->post_type !== 'news') return;

    // получаем все выбранные термины
    $terms = wp_get_post_terms($post_id, 'category-oportunities', [
        'fields' => 'ids',
    ]);

    // если больше одного — оставляем только один
    if (count($terms) > 1) {
        // оставляем последний выбранный
        $last_term_id = end($terms);

        wp_set_post_terms(
            $post_id,
            [$last_term_id],
            'category-oportunities',
            false
        );
    }

}, 10, 3);

add_action('add_meta_boxes', function () {

    remove_meta_box('category-oportunitiesdiv', 'news', 'side');

    add_meta_box(
        'category-oportunitiesdiv',
        'Category Oportunities',
        function ($post) {
            echo '<ul class="categorychecklist">';
            
            wp_terms_checklist($post->ID, [
                'taxonomy'      => 'category-oportunities',
                'checked_ontop' => false,
                'parent'        => 0,
            ]);

            echo '</ul>';
        },
        'news',
        'side',
        'default'
    );
});
add_filter('get_terms_args', function ($args, $taxonomies) {

    // только в админке
    if (!is_admin()) {
        return $args;
    }

    // только для нужной таксономии
    if (
        empty($taxonomies) ||
        !in_array('category-oportunities', (array) $taxonomies, true)
    ) {
        return $args;
    }

    // только для post type news
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'news') {
        return $args;
    }

    // ТОЛЬКО корневые категории
    $args['parent'] = 0;

    return $args;
}, 10, 2);

add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'news') {
        return;
    }
    ?>
    <script>
        jQuery(function ($) {
            $('#category-oportunitiesdiv input[type="checkbox"]').each(function () {
                this.type = 'radio';
                this.name = 'tax_input[category-oportunities][]';
            });
        });
    </script>
    <?php
});

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'news') {
        return;
    }
    ?>
    <style>
        #category-oportunitiesdiv ul {
            list-style: none;
            margin-left: 0;
            padding-left: 0;
        }
    </style>
    <?php
});
?>
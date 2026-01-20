<?php
acf_register_block_type([
    'name'  => 'news-page',
    'title' => __('Block News Page', '_themedomain'),

    'enqueue_assets' => function () {
        wp_enqueue_script('swiper-js');

        wp_enqueue_script(
            'news-page-js',
            get_template_directory_uri() . '/inc/acf/blocks/news-page/news-page.js',
            ['swiper-js'],
            null,
            true
        );
    },

    'render_template' => __DIR__ . '/news-page.php',
]);
?>
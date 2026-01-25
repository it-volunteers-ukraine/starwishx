<?php
acf_register_block_type([
    'name'            => 'news-page-category',
    'title'           => __('Block News Page by Categoty', '_themedomain'),
    'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) {

        // Подключаем CSS/JS для блока
        wp_enqueue_style('new-card-css', get_template_directory_uri() . '/assets/css/template-parts/new-card.css');
        // wp_enqueue_style('last-news-aside-css', get_template_directory_uri() . '/assets/css/template-parts/last-news-aside.css');
        wp_enqueue_style('news-page-css', get_template_directory_uri() . '/assets/css/blocks/news-page-category/news-page-category.module.css');
        // wp_enqueue_script('news-page-js', get_template_directory_uri() . '/assets/js/news-page-category.js', ['jquery'], null, true);

        // Рендерим шаблон блока (относительно этого register.php)
        include __DIR__ . '/news-page-category.php';
    },
    'icon'     => 'format-image',
    'category' => 'custom-blocks',
]);

?>
<?php
acf_register_block_type([
    'name'            => 'new-page',
    'title'           => __('Block One New Page', '_themedomain'),
    'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) {

        // Подключаем CSS/JS для блока
        // wp_enqueue_style('new-card-css', get_template_directory_uri() . '/assets/css/template-parts/new-card.css');
        // wp_enqueue_style('last-news-aside-css', get_template_directory_uri() . '/assets/css/template-parts/last-news-aside.css');
        wp_enqueue_style('new-page-css', get_template_directory_uri() . '/assets/css/blocks/new-page/new-page.module.css');
        wp_enqueue_script('new-page-js', get_template_directory_uri() . '/assets/js/new-page.js', ['jquery'], null, true);

        // Рендерим шаблон блока (относительно этого register.php)
        include __DIR__ . '/new-page.php';
    },
    'icon'     => 'format-image',
    'category' => 'custom-blocks',
]);


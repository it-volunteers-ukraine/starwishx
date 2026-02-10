<?php
acf_register_block_type([
    'name'            => 'search-page',
    'title'           => __('Search Page', '_themedomain'),
    'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) {

        // Подключаем CSS/JS для блока
        wp_enqueue_style('new-card-css', get_template_directory_uri() . '/assets/css/template-parts/new-card.css');
        wp_enqueue_style('custom-select-css', get_template_directory_uri() . '/assets/css/custom-select.css');
        wp_enqueue_style('search-page-css', get_template_directory_uri() . '/assets/css/blocks/search-page/search-page.module.css');
        wp_enqueue_script('search-page-js', get_template_directory_uri() . '/assets/js/search-page.js', ['jquery'], null, true);

        // Рендерим шаблон блока (относительно этого register.php)
        include __DIR__ . '/search-page.php';
    },
    'icon'     => 'format-image',
    'category' => 'custom-blocks',
]);


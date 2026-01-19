<?php
acf_register_block_type([
    'name'            => 'news-page',
    'title'           => __('Block News Page', '_themedomain'),
    'render_callback' => function($block, $content = '', $is_preview = false, $post_id = 0) {

        // Подключаем CSS/JS для блока
        wp_enqueue_style('new-card-css', get_template_directory_uri() . '/assets/css/template-parts/new-card.css');
        wp_enqueue_style('news-page-css', get_template_directory_uri() . '/assets/css/blocks/news-page/news-page.module.css');
        wp_enqueue_script('news-page-js', get_template_directory_uri() . '/assets/js/news-page.js', ['jquery'], null, true);

        // Рендерим шаблон блока (относительно этого register.php)
        include __DIR__ . '/news-page.php';
    },
    'icon'     => 'format-image',
    'category' => 'custom-blocks',
]);

?>
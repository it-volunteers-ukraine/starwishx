<?php
acf_register_block_type(array(
    'name' => 'news-page',
    'title' => __('Block News Page', '_themedomain'),
    'description' => __('Block News Page', '_themedomain'),
    'render_template' => acf_theme_blocks_path('news-page/news-page.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/news-page/news-page.module.css',
    'enqueue_script' => get_template_directory_uri() . '/assets/js/news-page.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));
?>
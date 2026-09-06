<?php
acf_register_block_type(array(
    'name' => 'news',
    'title' => __('Block News', 'starwishx'),
    'description' => __('Block News', 'starwishx'),
    'render_template' => acf_theme_blocks_path('news/news.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/news/news.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/news/news.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

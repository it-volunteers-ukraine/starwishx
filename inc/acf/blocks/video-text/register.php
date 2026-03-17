<?php
acf_register_block_type(array(
    'name' => 'video-text',
    'title' => __('Video & Text', 'starwishx'),
    'description' => __('Block with title, video & text', 'starwishx'),
    'render_template' => acf_theme_blocks_path('video-text/video-text.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/video-text/video-text.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/video-text/photo-text.js',
    'icon'  =>  'format-video',
    'category' => 'custom-blocks',
));

<?php
acf_register_block_type(array(
    'name' => 'christmas-tree',
    'title' => __('ChristmasTree', '_themedomain'),
    'description' => __('ChristmasTree', '_themedomain'),
    'render_template' => acf_theme_blocks_path('christmas-tree/christmas-tree.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/christmas-tree/christmas-tree.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/christmas-tree.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

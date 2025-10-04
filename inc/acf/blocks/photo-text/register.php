<?php
acf_register_block_type(array(
    'name' => 'photo-text',
    'title' => __('Block1 Photo & Text & Contacts', '_themedomain'),
    'description' => __('Block with title, photo, text and contact', '_themedomain'),
    'render_template' => acf_theme_blocks_path('photo-text/photo-text.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/photo-text/photo-text.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/photo-text/photo-text.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

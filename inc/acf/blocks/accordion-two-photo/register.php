<?php
acf_register_block_type(array(
    'name' => 'accordion-two-photo',
    'title' => __('Block accordion two photo', '_themedomain'),
    'description' => __('Block accordion with two photo', '_themedomain'),
    'render_template' => acf_theme_blocks_path('accordion-two-photo/accordion-two-photo.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/accordion-two-photo/accordion-two-photo.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/accordion-two-photo/accordion-two-photo.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

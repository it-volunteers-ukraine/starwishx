<?php
acf_register_block_type(array(
    'name' => 'accordion-one-photo',
    'title' => __('Block accordion one photo', '_themedomain'),
    'description' => __('Block accordion with one photo', '_themedomain'),
    'render_template' => acf_theme_blocks_path('accordion-one-photo/accordion-one-photo.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/accordion-one-photo/accordion-one-photo.module.css',
    'enqueue_script' => get_template_directory_uri() . '/assets/js/accordion-one-photo.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

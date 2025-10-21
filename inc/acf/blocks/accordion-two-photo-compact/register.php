<?php
acf_register_block_type(array(
    'name' => 'accordion-two-photo-compact',
    'title' => __('Block accordion two photo compact', '_themedomain'),
    'description' => __('Block accordion with two photo compact', '_themedomain'),
    'render_template' => acf_theme_blocks_path('accordion-two-photo-compact/accordion-two-photo-compact.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/accordion-two-photo-compact/accordion-two-photo-compact.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/accordion-two-photo-compact/accordion-two-photo-compact.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

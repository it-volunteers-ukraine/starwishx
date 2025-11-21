<?php
acf_register_block_type(array(
    'name' => 'breadcrumbs',
    'title' => __('Breadcrumbs', '_themedomain'),
    'description' => __('Block breadcrumbs', '_themedomain'),
    'render_template' => acf_theme_blocks_path('breadcrumbs/breadcrumbs.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/breadcrumbs/breadcrumbs.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/breadcrumbs.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

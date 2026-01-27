<?php
acf_register_block_type(array(
    'name' => 'pagination',
    'title' => __('Pagination', '_themedomain'),
    'description' => __('Block pagination', '_themedomain'),
    'render_template' => acf_theme_blocks_path('pagination/pagination.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/pagination/pagination.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/pagination.js',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

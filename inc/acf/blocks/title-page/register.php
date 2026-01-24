<?php
acf_register_block_type(array(
    'name' => 'title-page',
    'title' => __('Title Page', '_themedomain'),
    'description' => __('Block Title Page', '_themedomain'),
    'render_template' => acf_theme_blocks_path('title-page/title-page.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/title-page/title-pages.module.css',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));
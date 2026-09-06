<?php
acf_register_block_type(array(
    'name'            => 'hero',
    'title'           => __('Hero', 'starwishx'),
    'description'     => __('Block Hero', 'starwishx'),
    'render_template' => acf_theme_blocks_path('hero/hero.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/hero/hero.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/hero.js',
    'icon'            =>  'format-image',
    'category'        => 'custom-blocks',
));

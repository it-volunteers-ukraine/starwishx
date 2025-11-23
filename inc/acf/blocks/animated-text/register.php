<?php
acf_register_block_type([
    'name'              => 'animated-text',
    'title'             => __('Animated Text', '_themedomain'),
    'description'       => __('Block for displaying animated text on the homepage', '_themedomain'),
    'render_template'   => acf_theme_blocks_path('animated-text/animated-text.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/animated-text/animated-text.module.css',
    'icon'              => 'editor-textcolor',
    'category'          => 'custom-blocks',
]);
<?php
acf_register_block_type(array(
    'name'              => 'policy',
    'title'             => __('Policy Block', '_themedomain'),
    'description'       => __('Privacy / Policy content with PDF link.', '_themedomain'),
    'render_template'   => acf_theme_blocks_path('policy/policy.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/policy/policy.module.css',
    'enqueue_script'    => '',
    'icon'              => 'media-document',
    'category'          => 'custom-blocks'
));

<?php
acf_register_block_type([
    'name'              => 'projects',
    'title'             => __('Projects Banner (Static)', '_themedomain'),
    'description'       => __('Static banner with 4 manually filled project cards', '_themedomain'),
    'render_template'   => acf_theme_blocks_path('projects/projects.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/projects/projects.module.css',
    'icon'              => 'portfolio',
    'category'          => 'custom-blocks',
]);
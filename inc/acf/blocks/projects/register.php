<?php
acf_register_block_type([
    'name'              => 'projects',
    'title'             => __('Projects Carousel', 'starwishx'),
    'description'       => __('Carousel of project cards', 'starwishx'),
    'render_template'   => acf_theme_blocks_path('projects/projects.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/projects/projects.module.css',
    'icon'              => 'portfolio',
    'category'          => 'custom-blocks',
]);
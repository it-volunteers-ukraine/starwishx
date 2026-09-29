<?php
acf_register_block_type([
    'name'            => 'projects',
    'title'           => __('Projects Carousel (legacy)', 'starwishx'), // replaced by starwishx/projects (inc/blocks/projects)
    'description'     => __('Carousel of project cards', 'starwishx'),
    'render_template' => acf_theme_blocks_path('projects/projects.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/projects/projects.module.css',
    'icon'            => 'portfolio',
    'category'        => 'custom-blocks',
    'enqueue_assets'  => function () {
        // wp_enqueue_script('swiper'); 
        // This block demands earlier registration then in finctions.php (?)
        // Kinda ACF block's enqueue_assets callback can run before this hook fires (e.g., in the block editor, AJAX requests, or other contexts where wp_enqueue_scripts doesn't execute
        // So we register script here too
        wp_register_script(
            'swiper',
            get_template_directory_uri() . '/assets/js/vendor/swiper-bundle.min.js',
            [],  // No dependencies
            null,
            ['in_footer' => true, 'strategy' => 'defer']
        );
        wp_enqueue_script(
            'projects-block-script',
            get_template_directory_uri() . '/assets/js/projects.js',
            ['swiper'],
            null,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }
]);

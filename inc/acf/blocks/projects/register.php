<?php
acf_register_block_type([
    'name'            => 'projects',
    'title'           => __('Projects Carousel (legacy)', 'starwishx'), // replaced by starwishx/projects (inc/blocks/projects)
    'description'     => __('Carousel of project cards', 'starwishx'),
    'render_template' => acf_theme_blocks_path('projects/projects.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/projects/projects.module.css',
    'icon'            => 'portfolio',
    'category'        => 'custom-blocks',
    'supports'        => ['inserter' => false], // replaced: still renders and edits where it exists, not offered for new content
    'enqueue_assets'  => function () {
        // Swiper is registered on `init` (functions.php) and enqueued only by
        // its consumers; this block needs its CSS as well as its JS.
        wp_enqueue_style('swiper');
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

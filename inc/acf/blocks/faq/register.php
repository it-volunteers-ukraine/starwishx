<?php
acf_register_block_type(array(
    'name'            => 'faq',
    'title'           => __('FAQ Block', 'starwishx'),
    'description'     => __('Frequently Asked Questions Block', 'starwishx'),
    'render_template' => acf_theme_blocks_path('faq/faq.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/faq/faq.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/faq.js',
    'icon'            =>  'editor-help',
    'category'        => 'custom-blocks',
    'enqueue_assets'  => function () {
        wp_enqueue_script(
            'block-acf-faq',
            get_template_directory_uri() . '/assets/js/faq.js',
            [],
            null,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }
));

<?php
acf_register_block_type(array(
    'name'            => 'faq',
    'title'           => __('FAQ Block (legacy)', 'starwishx'), // replaced by starwishx/faq (inc/blocks/faq)
    'description'     => __('Frequently Asked Questions Block', 'starwishx'),
    'render_template' => acf_theme_blocks_path('faq/faq.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/faq/faq.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/faq.js',
    'icon'            =>  'editor-help',
    'category'        => 'custom-blocks',
    'supports'        => ['inserter' => false], // replaced: still renders and edits where it exists, not offered for new content
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

<?php
acf_register_block_type(array(
    'name'            => 'pagination',
    'title'           => __('Pagination', '_themedomain'),
    'description'     => __('Block pagination', '_themedomain'),
    'render_template' => acf_theme_blocks_path('pagination/pagination.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/pagination/pagination.module.css',
    // 'enqueue_script' => get_template_directory_uri() . '/assets/js/pagination.js',
    'icon'            => 'format-image',
    'category'        => 'custom-blocks',
    'enqueue_assets'  => function () {
        wp_enqueue_script(
            'pagination',
            get_template_directory_uri() . '/assets/js/pagination.js',
            [],
            null,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
        wp_localize_script('pagination', 'THEME_AJAX', [
            'url' => admin_url('admin-ajax.php'),
        ]);
    }
));


/** 
 * ! Whats wrong with this: enqueing on every page across all the theme.
 * Obviously is better to use callback `enqueue_assets`
 */
// add_action('enqueue_block_assets', function () {
//     wp_register_script(
//         'pagination-block',
//         get_template_directory_uri() . '/assets/js/pagination.js',
//         [],
//         null,
//         true
//     );

//     wp_localize_script('pagination-block', 'THEME_AJAX', [
//         'url' => admin_url('admin-ajax.php'),
//     ]);

//     wp_enqueue_script('pagination-block');
// });

// add_action('acf/init', function () {

//     acf_register_block_type([
//         'name'            => 'pagination',
//         'title'           => __('Pagination', '_themedomain'),
//         'description'     => __('Block pagination', '_themedomain'),
//         'render_template' => acf_theme_blocks_path('pagination/pagination.php'),
//         'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/pagination/pagination.module.css',
//         'icon'            => 'format-image',
//         'category'        => 'custom-blocks',
//     ]);
// });

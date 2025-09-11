<?php
acf_register_block_type(array(
    'name' => 'example-block',
    'title' => __('Example Block'),
    'description' => __('example block.'),
    'render_template' => acf_theme_blocks_path('example-block/example-block.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/blocks/styles/example-block/example-block.css',
    'enqueue_script' => get_template_directory_uri() . '/assets/blocks/scripts/example-block/example-block.js',
    'category' => 'custom-blocks',
));


// acf_register_block_type(array(
//     'name'  =>  'hero3',
//     'title' =>  __('Hero3', '_themedomain'),
//     'description'   =>  __('Block Hero3', '_themedomain'),
//     'render_template'   =>  acf_theme_blocks_path('hero3/hero3.php'),
//     'category'  =>  'ccc-blocks',
//     'icon'  =>  'format-image',
//     'supports'  =>  ['jsx'  =>  true],
// ));

// acf_register_block_type(array(
//     'name'  =>  'counter1',
//     'title' =>  __('counter1', '_themedomain'),
//     'description'   =>  __('Block counter1', '_themedomain'),
//     'render_template'   =>  acf_theme_blocks_path('counter1/counter1.php'),
//     'category'  =>  'ccc-blocks',
//     'icon'  =>  'format-image',
//     'supports'  =>  ['jsx'  =>  true],
//     'enqueue_script' => get_template_directory_uri() . '/assets/js/counter1.js',
// ));
<?php
add_action('acf/init', function () {
    acf_register_block_type([
        'name'            => 'projects',
        'title'           => __('Блок «Проєкти»', '_themedomain'),
        'description'     => __('Виводить випадкові записи-проєкти', '_themedomain'),
        'render_template' => acf_theme_blocks_path('projects/projects.php'),
        'enqueue_style'   => get_template_directory_uri() .
                             '/assets/css/blocks/projects/projects.module.css',
        'icon'            => 'portfolio',
        'category'        => 'custom-blocks',
        'supports'        => ['align' => false],
    ]);
});
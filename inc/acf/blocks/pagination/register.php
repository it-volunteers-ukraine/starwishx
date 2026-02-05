<?php
acf_register_block_type(array(
    'name' => 'pagination',
    'title' => __('Pagination', '_themedomain'),
    'description' => __('Block pagination', '_themedomain'),
    'render_template' => acf_theme_blocks_path('pagination/pagination.php'),
    'enqueue_style' => get_template_directory_uri() . '/assets/css/blocks/pagination/pagination.module.css',
    'icon'  =>  'format-image',
    'category' => 'custom-blocks',
));

// register.php — твой файл для подключения ACF блоков

add_action('enqueue_block_assets', function () {

    // регистрируем скрипт
    wp_register_script(
        'pagination-block',
        get_template_directory_uri() . '/assets/js/pagination.js',
        [],
        null,
        true
    );

    // локализуем переменные
    wp_localize_script('pagination-block', 'THEME_AJAX', [
        'url' => admin_url('admin-ajax.php'),
    ]);

    // подключаем скрипт (вместо enqueue_script в блоке)
    wp_enqueue_script('pagination-block');
});

add_action('acf/init', function () {

    acf_register_block_type([
        'name'            => 'pagination',
        'title'           => __('Pagination', '_themedomain'),
        'description'     => __('Block pagination', '_themedomain'),
        'render_template' => acf_theme_blocks_path('pagination/pagination.php'),
        'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/pagination/pagination.module.css',
        'icon'            => 'format-image',
        'category'        => 'custom-blocks',
    ]);
});


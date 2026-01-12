<?php

acf_register_block_type([
    'name'              => 'contact_promo',
    'title'             => __('Contact Promo Card', '_themedomain'),
    'description'       => __('Картка з фото, текстом та контактами', '_themedomain'),
    'render_template'   => acf_theme_blocks_path('contact_page/contact_promo.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/contact_promo/contact_promo.module.css',
    'category'          => 'custom-blocks',
    'icon'              => 'id-alt',
]);
<?php

acf_register_block_type([
    'name'              => 'contact_form',
    'title'             => __('Contact Form', '_themedomain'),
    'description'       => __('Контактний блок з формою і контактною інформацією', '_themedomain'),
    'render_template'   => acf_theme_blocks_path('contact_form/contact.php'),
    'enqueue_style'     => get_template_directory_uri() . '/assets/css/blocks/contact_form/contact.module.css',
    'category'          => 'custom-blocks',
    'icon'              => 'email',
]);

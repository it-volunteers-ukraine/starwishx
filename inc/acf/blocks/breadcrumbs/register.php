<?php
acf_register_block_type(array(
    'name'            => 'breadcrumbs',
    'title'           => __('Breadcrumbs (legacy)', 'starwishx'), // replaced by sw_breadcrumbs() in templates (template-parts/breadcrumbs.php)
    'description'     => __('Block breadcrumbs', 'starwishx'),
    'render_template' => acf_theme_blocks_path('breadcrumbs/breadcrumbs.php'),
    // No enqueue_style: the trail's styles live in app.css (components/_breadcrumbs.scss).
    'icon'            => 'format-image',
    'category'        => 'custom-blocks',
    'supports'        => ['inserter' => false], // replaced: still renders where it exists, not offered for new content
));

<?php
acf_register_block_type(array(
    'name'            => 'accordion-opportunities',
    'title'           => __('Block accordion opportunities', 'starwishx'),
    'description'     => __('Block accordion opportunities', 'starwishx'),
    'render_template' => acf_theme_blocks_path('accordion-opportunities/accordion-opportunities.php'),
    'enqueue_style'   => get_template_directory_uri() . '/assets/css/blocks/accordion-opportunities/accordion-opportunities.module.css',
    'enqueue_script'  => get_template_directory_uri() . '/assets/js/accordion-opportunities.js',
    'icon'            =>  'format-image',
    'category'        => 'custom-blocks',
));

// Show only top-level (parent) terms in the category selector for this block.
// Uses field key (globally unique) instead of _name to avoid collisions with
// other taxonomy fields across field groups.
add_filter('acf/fields/taxonomy/query', function ($args, $field) {
    if ('field_69b9d8ccddfd5' === $field['key']) {
        $args['parent'] = 0;
    }
    return $args;
}, 10, 2);

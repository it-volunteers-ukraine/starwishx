<?php

/**
 * Attribute labels for the editor sidebar, merged into block.json by
 * Blocks\Core\BlocksCore::applyAttributeLabels(). Kept in PHP so they are
 * translatable; the gettext context keeps short generic words from inheriting
 * unrelated translations elsewhere in the theme.
 *
 * File: inc/blocks/projects/labels.php
 */

return [
    'suptitle'    => _x('Label', 'block attribute label', 'starwishx'),
    'title'       => _x('Title', 'block attribute label', 'starwishx'),
    'slidesCount' => _x('Number of projects', 'block attribute label', 'starwishx'),
];

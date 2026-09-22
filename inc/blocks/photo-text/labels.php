<?php

/**
 * Attribute labels for the editor sidebar, merged into block.json by
 * Blocks\Core\BlocksCore::applyAttributeLabels(). Kept in PHP so they are
 * translatable (`npm run i18n` extracts them; block.json labels are not).
 * The gettext context keeps short generic words ("Title") from inheriting
 * unrelated translations elsewhere in the theme.
 *
 * File: inc/blocks/photo-text/labels.php
 */

return [
    'title'   => _x('Title', 'block attribute label', 'starwishx'),
    'photoId' => _x('Photo', 'block attribute label', 'starwishx'),
    'text'    => _x('Text', 'block attribute label', 'starwishx'),
];

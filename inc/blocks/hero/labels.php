<?php

/**
 * Attribute labels for the editor sidebar, merged into block.json by
 * Blocks\Core\BlocksCore::applyAttributeLabels(). Kept in PHP so they are
 * translatable (`npm run i18n` extracts them; block.json labels are not).
 * The gettext context keeps short generic words ("Title") from inheriting
 * unrelated translations elsewhere in the theme.
 *
 * File: inc/blocks/hero/labels.php
 */

return [
    'title'         => _x('Title', 'block attribute label', 'starwishx'),
    'subtitle'      => _x('Subtitle', 'block attribute label', 'starwishx'),
    'browseText'    => _x('Link: browse opportunities', 'block attribute label', 'starwishx'),
    'addText'       => _x('Link: add an opportunity', 'block attribute label', 'starwishx'),
    'textBottom'    => _x('Bottom text', 'block attribute label', 'starwishx'),
    'imageId'       => _x('Photo (fallback: JPEG or PNG)', 'block attribute label', 'starwishx'),
    // Modern-format versions of the same photo, in browser preference order.
    'sourceOneId'   => _x('Format source 1 (AVIF)', 'block attribute label', 'starwishx'),
    'sourceTwoId'   => _x('Format source 2 (WebP)', 'block attribute label', 'starwishx'),
];

<?php

/**
 * Attribute labels for the editor sidebar, merged into block.json by
 * Blocks\Core\BlocksCore::applyAttributeLabels(). Kept in PHP so they are
 * translatable (`npm run i18n` extracts them; block.json labels are not).
 * The gettext context keeps short generic words ("Title") from inheriting
 * unrelated translations elsewhere in the theme.
 *
 * File: inc/blocks/news/labels.php
 */

return [
    'label'      => _x('Label', 'block attribute label', 'starwishx'),
    'title'      => _x('Title', 'block attribute label', 'starwishx'),
    'buttonText' => _x('Button text', 'block attribute label', 'starwishx'),
    'buttonUrl'  => _x('Button URL (empty = news archive)', 'block attribute label', 'starwishx'),
];

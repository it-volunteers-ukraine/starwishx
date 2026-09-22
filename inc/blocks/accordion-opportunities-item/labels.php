<?php

/**
 * Attribute labels for the shared sidebar panels ("Category" select, "Media"
 * picker), merged into block.json by Blocks\Core\BlocksCore::applyAttributeLabels().
 * The description is edited inline (RichText) and needs no label. Kept in PHP
 * so they are translatable; the gettext context keeps short words from
 * inheriting unrelated translations.
 *
 * File: inc/blocks/accordion-opportunities-item/labels.php
 */

return [
    'termId'  => _x('Category', 'block attribute label', 'starwishx'),
    'photoId' => _x('Photo', 'block attribute label', 'starwishx'),
];

<?php

/**
 * Attribute labels for the editor sidebar, merged into block.json by
 * Blocks\Core\BlocksCore::applyAttributeLabels(). Kept in PHP so they are
 * translatable (`npm run i18n` extracts them; block.json labels are not).
 *
 * File: inc/blocks/video-text/labels.php
 */

return [
    'title'    => __('Title', 'starwishx'),
    'videoUrl' => __('Video URL (YouTube or Vimeo)', 'starwishx'),
    'text'     => __('Text', 'starwishx'),
    'coverId'  => __('Cover image', 'starwishx'),
];

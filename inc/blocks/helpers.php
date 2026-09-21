<?php

/**
 * Blocks module — global helper
 *
 * Prefixed (sw_blocks, not blocks) because a bare `blocks()` is a name any
 * plugin could claim.
 *
 * File: inc/blocks/helpers.php
 */

if (! function_exists('sw_blocks')) {
    function sw_blocks(): \Blocks\Core\BlocksCore
    {
        return \Blocks\Core\BlocksCore::instance();
    }
}

<?php

/**
 * News module — global helper
 *
 * Prefixed `sw_news()` rather than the bare `news()` used by the other
 * modules: `news` is a name a plugin is quite likely to claim.
 *
 * File: inc/news/helpers.php
 */

if (! function_exists('sw_news')) {
    function sw_news(): \News\Core\NewsCore
    {
        return \News\Core\NewsCore::instance();
    }
}

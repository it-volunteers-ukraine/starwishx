<?php

/**
 * Social Share module — global helper
 *
 * File: inc/social-share/helpers.php
 */

if (! function_exists('social_share')) {
    function social_share(): \SocialShare\Core\SocialShareCore
    {
        return \SocialShare\Core\SocialShareCore::instance();
    }
}

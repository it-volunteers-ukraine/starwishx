<?php

/**
 * Contact module — global helper
 *
 * File: inc/contact/helpers.php
 */

if (! function_exists('contact')) {
    function contact(): \Contact\Core\ContactCore
    {
        return \Contact\Core\ContactCore::instance();
    }
}

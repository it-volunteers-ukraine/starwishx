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

if (! function_exists('sw_contact_links')) {
    /**
     * The site's public contact links (Theme Settings → Common Info), normalised.
     *
     * @return list<array{key: string, label: string, url: string, text: string, title: string, icon: string, external: bool}>
     */
    function sw_contact_links(): array
    {
        return \Contact\Support\ContactLinks::all();
    }
}

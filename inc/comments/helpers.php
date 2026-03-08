<?php

declare(strict_types=1);

/**
 * Global helper function to access Comments instance
 *
 * @return \Comments\Core\CommentsCore
 */
function comments(): \Comments\Core\CommentsCore
{
    return \Comments\Core\CommentsCore::instance();
}

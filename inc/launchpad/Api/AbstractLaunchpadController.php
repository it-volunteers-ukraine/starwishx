<?php
// File: inc/launchpad/Api/AbstractLaunchpadController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Shared\Core\AbstractApiController as BaseController;
use WP_REST_Request;

/**
 * The specific base for all Launchpad module endpoints.
 */
abstract class AbstractLaunchpadController extends BaseController
{
    /**
     * Authoritative namespace for this specific module.
     */
    protected $namespace = 'launchpad/v1';

    /**
     * Helper for REST permission callbacks.
     * 
     * This must be public. WordPress REST Server calls this 
     * from the global scope to verify permissions.
     */
    public function checkPostOwner(WP_REST_Request $request): bool
    {
        $id = $request->get_param('id');

        if (!$id) {
            return false;
        }

        return $this->checkOwner((int) $id);
    }

    /**
     * Core logic: Check if a user owns a specific post ID.
     * 
     * This must be public to be accessible as a valid callback.
     */
    public function checkOwner(?int $postId): bool
    {
        if (!$postId || !is_user_logged_in()) {
            return false;
        }

        $post = get_post($postId);

        // Ensure post exists and author matches current user
        return $post && (int) $post->post_author === get_current_user_id();
    }
}

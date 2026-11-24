<?php

/**
 * Who is looking at a post's comments: what each comment's flags (isMine,
 * canReply, isEditable) depend on. Worked out once per request, not once per
 * comment.
 *
 * File: inc/comments/Services/CommentViewer.php
 */

declare(strict_types=1);

namespace Comments\Services;

use WP_Post;

final class CommentViewer
{
    /** Logged in: a guest (0) owns no comment, not even a user_id 0 one. */
    public bool $isLoggedIn {
        get => $this->userId > 0;
    }

    /** The post's author, who may reply to every thread. */
    public bool $isPostAuthor {
        get => $this->isLoggedIn && $this->userId === $this->postAuthorId;
    }

    public function __construct(
        public readonly int $userId,
        public readonly int $postAuthorId,
        public readonly bool $canModerate,
    ) {}

    public static function of(int $userId, ?WP_Post $post): self
    {
        return new self(
            $userId,
            $post ? (int) $post->post_author : 0,
            $userId > 0 && user_can($userId, 'moderate_comments'),
        );
    }
}

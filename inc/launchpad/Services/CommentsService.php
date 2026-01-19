<?php
// inc/launchpad/Services/CommentsService.php
declare(strict_types=1);

namespace Launchpad\Services;

use WP_Error;
use WP_Comment;

class CommentsService
{
    public const ITEMS_PER_PAGE = 2;
    public const EDIT_TIMEOUT_SECONDS = 15 * 60;

    /**
     * Get comments with rating data (Optimized to avoid N+1 queries)
     */
    public function getPostComments(int $postId, int $limit = self::ITEMS_PER_PAGE, int $offset = 0): array
    {
        $currentUserId = get_current_user_id();

        // Context: Is the logged-in user the author of the Post (Opportunity)?
        $post = get_post($postId);
        $postAuthorId = $post ? (int) $post->post_author : 0;
        $currentUserIsPostAuthor = $currentUserId > 0 && $currentUserId === $postAuthorId;

        // 1. Fetch TOP LEVEL comments (Parents)
        $parents = get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'parent'  => 0,
            'order'   => 'DESC',
            'number'  => $limit,
            'offset'  => $offset,
        ]);

        if (empty($parents)) {
            return [];
        }

        // 2. Extract Parent IDs to fetch all children in ONE query
        $parentIds = array_column($parents, 'comment_ID');

        // 3. Fetch ALL replies for these specific parents
        // This replaces the loop of get_comments() inside the formatter
        $replies = get_comments([
            'post_id'    => $postId,
            'status'     => 'approve',
            'parent__in' => $parentIds,
            'order'      => 'ASC',
            'orderby'    => 'comment_date_gmt'
        ]);

        // 4. Group replies by their Parent ID in memory
        $groupedReplies = [];
        foreach ($replies as $reply) {
            $parentId = (int) $reply->comment_parent;
            if (!isset($groupedReplies[$parentId])) {
                $groupedReplies[$parentId] = [];
            }
            $groupedReplies[$parentId][] = $reply;
        }

        // 5. Map Parents and inject their specific children
        return array_map(function ($parent) use ($groupedReplies, $currentUserId, $postAuthorId, $currentUserIsPostAuthor) {

            $childrenObjects = $groupedReplies[$parent->comment_ID] ?? [];

            // Format the children first
            $formattedChildren = array_map(function ($child) use ($currentUserId, $postAuthorId, $currentUserIsPostAuthor) {
                return $this->formatSingleComment($child, $currentUserId, $postAuthorId, $currentUserIsPostAuthor);
            }, $childrenObjects);

            // Format the parent and attach children
            $formattedParent = $this->formatSingleComment($parent, $currentUserId, $postAuthorId, $currentUserIsPostAuthor);

            $formattedParent['replies']    = $formattedChildren;
            $formattedParent['hasReplies'] = count($formattedChildren) > 0;

            return $formattedParent;
        }, $parents);
    }

    /**
     * New Helper: Formats a single WP_Comment object into an array.
     * Purely data mapping, NO database queries here.
     * 
     * @param WP_Comment $comment The comment object
     * @param int $currentUserId ID of currently logged in user
     * @param int $postAuthorId ID of the author of the generic POST (Opportunity)
     * @param bool $currentUserIsPostAuthor Is current user the owner of the Opportunity?
     */
    private function formatSingleComment(WP_Comment $comment, int $currentUserId, int $postAuthorId, bool $currentUserIsPostAuthor): array
    {
        $commentId     = (int) $comment->comment_ID;
        $commentUserId = (int) $comment->user_id;

        // Metadata (Ratings are usually pre-cached by WP, but simple get_metadata is fast)
        $rating = (int) get_comment_meta($commentId, '_star_rating', true);

        // Logic: Author Display Name
        $display_author = $comment->comment_author;
        if ($commentUserId > 0) {
            $user = get_userdata($commentUserId);
            if ($user && !empty($user->first_name)) {
                $display_author = $user->first_name;
            }
        }

        // Permissions
        $isMine = ($commentUserId === $currentUserId);
        $canReply = $currentUserId > 0 && ($currentUserIsPostAuthor || $isMine);

        // --- Time-based Editing Restrictions ---
        $isEditable = false;
        if ($isMine) {
            // Use GMT dates to avoid server timezone offsets issues
            $postedTime = strtotime($comment->comment_date_gmt);
            $now = time(); // Returns UTC timestamp

            // Allow edit if within timeout OR if user is a moderator (can bypass limits)
            if (($now - $postedTime) <= self::EDIT_TIMEOUT_SECONDS) {
                $isEditable = true;
            } elseif (user_can($currentUserId, 'moderate_comments')) {
                // Admins/Moderators should always be able to edit
                $isEditable = true;
            }
        }
        // ---------------------------------------

        // Logic: CPT author Badges
        $isCommentByPostAuthor = ($commentUserId === $postAuthorId);

        return [
            'id'           => $commentId,
            'author'       => $display_author,
            'content'      => $comment->comment_content,
            'date'         => get_comment_date('d.m.y H:i', $comment),
            'avatar'       => get_avatar_url($comment, ['size' => 96]),
            'rating'       => $rating > 0 ? $rating : null,
            'isMine'       => $commentUserId === $currentUserId,
            'isPostAuthor' => $isCommentByPostAuthor,
            'canReply'     => $canReply,
            'isEditable'   => $isEditable, // Edit State Property
            // Default empty for structure consistency, populated by getPostComments if needed
            'replies'      => [],
            'hasReplies'   => false,
        ];
    }

    public function addComment(int $userId, int $postId, string $content, int $rating = 0, int $parentId = 0): array|WP_Error
    {
        $user = get_userdata($userId);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found.', 'starwishx'));
        }
        $author_name = !empty($user->first_name) ? $user->first_name : $user->user_login;

        // Validation
        if ($parentId > 0) {
            $parentComm = get_comment($parentId);
            if (!$parentComm) return new WP_Error('invalid_parent', __('Parent comment not found.', 'starwishx'));

            // Server-side canReply validation
            $post                = get_post($postId);
            $postAuthorId        = $post ? (int) $post->post_author : 0;
            $parentCommentUserId = (int) $parentComm->user_id;

            $canReply = (
                $userId === $postAuthorId ||            // Post author can reply to anyone
                $userId === $parentCommentUserId        // Thread starter can reply
            );

            if (!$canReply) {
                return new WP_Error('forbidden', __('You cannot reply to this thread.', 'starwishx'));
            }
        }

        $data = [
            'comment_post_ID'  => $postId,
            'comment_content'  => sanitize_textarea_field($content),
            'user_id'          => $userId,
            'comment_author'   => $author_name,
            'comment_approved' => 1,
            'comment_parent'   => $parentId,
        ];

        $commentId = wp_insert_comment($data);

        if (!$commentId) {
            return new WP_Error('comment_failed', __('Failed to post comment.', 'starwishx'));
        }

        // Handle Rating
        if ($parentId === 0 && $rating > 0 && $rating <= 5) {
            update_comment_meta($commentId, '_star_rating', $rating);
            $this->updatePostAggregateRating($postId);
        }

        // Prepare return data using new formatter
        $newCommentObj = get_comment($commentId);
        $post = get_post($postId);
        $postAuthorId = (int) $post->post_author;
        $currentUserIsPostAuthor = ($userId === $postAuthorId);

        // Note: New comments have no replies yet
        $formatted = $this->formatSingleComment($newCommentObj, $userId, $postAuthorId, $currentUserIsPostAuthor);

        return [
            'comment'    => $formatted,
            'aggregates' => $this->getAggregates($postId)
        ];
    }

    /**
     * Update an existing comment
     */
    public function updateComment(int $userId, int $commentId, string $content, int $rating = 0): array|WP_Error
    {
        $comment = get_comment($commentId);

        if (!$comment) {
            return new WP_Error('not_found', __('Comment not found.', 'starwishx'));
        }
        if ((int) $comment->user_id !== $userId) {
            return new WP_Error('forbidden', __('You cannot edit this comment.', 'starwishx'));
        }

        // --- Edit Timeout Server-Side Validation ---
        if (!user_can($userId, 'moderate_comments')) {
            $postedTime = strtotime($comment->comment_date_gmt);
            $now = time();
            if (($now - $postedTime) > self::EDIT_TIMEOUT_SECONDS) {
                return new WP_Error('timeout', __('Edit time limit expired (15 min).', 'starwishx'));
            }
        }
        // -------------------------------------------

        $args = [
            'comment_ID'      => $commentId,
            'comment_content' => sanitize_textarea_field($content),
        ];

        if ($comment->comment_content !== $args['comment_content']) {
            wp_update_comment($args);
        }

        if ($rating > 0 && $rating <= 5) {
            update_comment_meta($commentId, '_star_rating', $rating);
            $this->updatePostAggregateRating((int) $comment->comment_post_ID);
        }

        // Fetch fresh object
        $updatedComment          = get_comment($commentId);
        $post                    = get_post($comment->comment_post_ID);
        $postAuthorId            = (int) $post->post_author;
        $currentUserIsPostAuthor = ($userId === $postAuthorId);

        // Note: When updating, we just return the item data. 
        // We preserve existing 'replies' in the store via JS, so we return empty array here 
        // or the Frontend needs to be smart enough not to overwrite replies with empty array.
        // For safety, this service just returns the node data.
        $formatted = $this->formatSingleComment($updatedComment, $userId, $postAuthorId, $currentUserIsPostAuthor);

        return [
            'comment'    => $formatted,
            'aggregates' => $this->getAggregates((int) $comment->comment_post_ID)
        ];
    }

    /**
     * Helper: Recalculate Post Average Rating
     */
    private function updatePostAggregateRating(int $postId): void
    {
        global $wpdb;

        $sql = "SELECT count(meta_value) as cnt, avg(meta_value) as avg 
                FROM {$wpdb->commentmeta} 
                WHERE meta_key = '_star_rating' 
                AND comment_id IN (
                    SELECT comment_ID FROM {$wpdb->comments} 
                    WHERE comment_post_ID = %d AND comment_approved = '1'
                )";

        $result = $wpdb->get_row($wpdb->prepare($sql, $postId));

        $count  = (int) $result->cnt;
        $avg    = round((float) $result->avg, 1);

        update_post_meta($postId, '_opportunity_rating_count', $count);
        update_post_meta($postId, '_opportunity_rating_avg', $avg);
    }

    /**
     * Get Aggregates for Header
     */
    public function getAggregates(int $postId): array
    {
        return [
            'count' => (int) get_post_meta($postId, '_opportunity_rating_count', true),
            'avg'   => (float) get_post_meta($postId, '_opportunity_rating_avg', true),
        ];
    }

    /**
     * Count approved comments for pagination calculation.
     */
    public function countPostComments(int $postId): int
    {
        return get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'count'   => true,
            'parent'  => 0 // Count only threads for pagination matching getPostComments limit
        ]);
    }
}

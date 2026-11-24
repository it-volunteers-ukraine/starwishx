<?php
// File: inc/comments/Services/CommentsService.php
declare(strict_types=1);

namespace Comments\Services;

use DateTimeImmutable;
use DateTimeZone;
use Shared\Http\ClientIp;
use WP_Comment;
use WP_Error;
use WP_Post;

/**
 * Reviews (top-level comments, optionally rated) and their replies.
 *
 * Text: WordPress stores comment text entity-encoded (sanitize_textarea_field,
 * kses on update) and wp_insert_comment() / wp_update_comment() unslash what
 * they're given. So writes go in wp_slash()ed and the formatter decodes what
 * it sends: the store prints it as text (data-wp-text).
 *
 * Ratings: only top-level comments carry one. The post's cached average
 * (refreshAggregates()) counts each author's latest rating once.
 */
final class CommentsService
{
    public const int ITEMS_PER_PAGE = 2;
    public const int EDIT_TIMEOUT_SECONDS = 15 * 60;
    public const int CONTENT_MAX_LENGTH = 2000;
    public const array ALLOWED_POST_TYPES = ['opportunity', 'project', 'news'];

    /**
     * The counting rules of refreshAggregates(). Bump it when they change:
     * CommentsCore recounts every rated post once.
     */
    public const int AGGREGATES_VERSION = 2;

    private const string RATING_KEY = '_star_rating';
    private const string COUNT_KEY  = '_opportunity_rating_count';
    private const string AVG_KEY    = '_opportunity_rating_avg';

    /**
     * The post-specific state template-parts/comments-interactive.php
     * hydrates for the comments store (CommentsCore::getConfig() adds
     * `config`). Its shape is the PHP side of the contract in
     * tests/fixtures/comments-state.json; the TS side is
     * inc/comments/Assets/types.ts (CommentsServerState).
     *
     * hasComments, hasRatings and roundedAvg are server-render mirrors of
     * the store's getters of the same names, which replace them on the client.
     *
     * @return array{
     *   canComment: bool, isPostAuthor: bool, list: list<array<string, mixed>>,
     *   aggregates: array{count: int, avg: float}, page: int, hasMore: bool,
     *   hasComments: bool, newContent: string, newRating: int, error: null,
     *   successMessage: null, isSubmitting: bool, isLoading: bool,
     *   showForm: bool, hasRatings: bool, roundedAvg: int
     * }
     */
    public function getInitialState(int $postId): array
    {
        $total      = $this->countPostComments($postId);
        $aggregates = $this->getAggregates($postId);

        return [
            'canComment'     => get_post_status($postId) === 'publish',
            'isPostAuthor'   => $this->viewer($postId)->isPostAuthor,
            'list'           => $this->getPostComments($postId, self::ITEMS_PER_PAGE, 0),
            'aggregates'     => $aggregates,
            'page'           => 1,
            'hasMore'        => $total > self::ITEMS_PER_PAGE,
            'hasComments'    => $total > 0,
            'newContent'     => '',
            'newRating'      => 5,
            'error'          => null,
            'successMessage' => null,
            'isSubmitting'   => false,
            'isLoading'      => false,
            'showForm'       => false,
            'hasRatings'     => $aggregates['count'] > 0,
            'roundedAvg'     => (int) round($aggregates['avg']),
        ];
    }

    /**
     * May the current user read the post's comments? As far as they may
     * read the post: a public one of an allowed type, or one of theirs (a
     * draft's author loading more comments in the preview).
     */
    public function canRead(int $postId): bool
    {
        $post = get_post($postId);

        return $post instanceof WP_Post
            && in_array($post->post_type, self::ALLOWED_POST_TYPES, true)
            && (is_post_publicly_viewable($post) || current_user_can('read_post', $post->ID));
    }

    /**
     * A page of threads, newest first, each with all its replies (oldest
     * first). Three queries whatever the page size: the threads, their
     * replies, and their authors.
     *
     * @return list<array<string, mixed>>
     */
    public function getPostComments(int $postId, int $limit = self::ITEMS_PER_PAGE, int $offset = 0): array
    {
        $parents = get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'parent'  => 0,
            'order'   => 'DESC',
            'number'  => $limit,
            'offset'  => $offset,
        ]);

        if ($parents === []) {
            return [];
        }

        $replies = get_comments([
            'post_id'    => $postId,
            'status'     => 'approve',
            'parent__in' => array_column($parents, 'comment_ID'),
            'order'      => 'ASC',
            'orderby'    => 'comment_date_gmt',
        ]);

        // Every author's display name in one query, not one per comment.
        $authorIds = array_column([...$parents, ...$replies], 'user_id')
            |> (static fn(array $ids): array => array_map(intval(...), $ids))
            |> array_filter(...)
            |> array_unique(...);
        cache_users($authorIds);

        $repliesByParent = [];
        foreach ($replies as $reply) {
            $repliesByParent[(int) $reply->comment_parent][] = $reply;
        }

        $viewer = $this->viewer($postId);
        $format = fn(WP_Comment $comment): array => $this->formatSingleComment($comment, $viewer);

        return array_map(
            static function (WP_Comment $parent) use ($repliesByParent, $format): array {
                $thread = array_map($format, $repliesByParent[(int) $parent->comment_ID] ?? []);
                return [...$format($parent), 'replies' => $thread, 'hasReplies' => $thread !== []];
            },
            $parents
        );
    }

    /**
     * A comment as the store's CommentNode (inc/comments/Assets/types.ts).
     * No queries of its own: meta and users are primed by the caller.
     *
     * @return array<string, mixed>
     */
    private function formatSingleComment(WP_Comment $comment, CommentViewer $viewer): array
    {
        $commentId     = (int) $comment->comment_ID;
        $commentUserId = (int) $comment->user_id;
        $rating        = (int) get_comment_meta($commentId, self::RATING_KEY, true);
        // The name the user chose to show ("Display name publicly as").
        $author        = $commentUserId > 0 ? get_userdata($commentUserId) : false;
        $isMine        = $viewer->isLoggedIn && $commentUserId === $viewer->userId;

        return [
            'id'           => $commentId,
            'author'       => $author ? $author->display_name : $comment->comment_author,
            'content'      => wp_specialchars_decode($comment->comment_content, ENT_QUOTES),
            'date'         => get_comment_date('d.m.y H:i', $comment),
            'dateIso'      => get_comment_date('c', $comment),
            'avatar'       => get_avatar_url($comment, ['size' => 96]),
            'rating'       => $rating > 0 ? $rating : null,
            /* translators: %d: star rating number 1-5 */
            'ratingLabel'  => $rating > 0 ? sprintf(__('Rating: %d out of 5 stars', 'starwishx'), $rating) : '',
            'isMine'       => $isMine,
            'isPostAuthor' => $commentUserId > 0 && $commentUserId === $viewer->postAuthorId,
            'canReply'     => $viewer->isPostAuthor || $isMine,
            // Moderators may edit theirs after the window too.
            'isEditable'   => $isMine && ($viewer->canModerate || self::withinEditWindow($comment)),
            // getPostComments() fills these for threads.
            'replies'      => [],
            'hasReplies'   => false,
        ];
    }

    /**
     * Post a review (`$parentId` 0, optionally rated 1-5) or a reply to one.
     *
     * Honours the site's Discussion settings (duplicates, flooding, the
     * disallowed-words list) through wp_allow_comment(), but publishes
     * straight away: there's no "awaiting moderation" in this UI.
     *
     * @return array{comment: array<string, mixed>, aggregates: array{count: int, avg: float}}|WP_Error
     */
    #[\NoDiscard('a refused comment is only reported when the result is acted on')]
    public function addComment(int $userId, int $postId, string $content, int $rating = 0, int $parentId = 0): array|WP_Error
    {
        $user = get_userdata($userId);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found.', 'starwishx'));
        }

        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('not_published', __('Comments are only allowed on published posts.', 'starwishx'));
        }

        if (!in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return new WP_Error('not_commentable', __('Comments are not allowed on this content.', 'starwishx'));
        }

        // Authors reply to reviews of their post; they don't review it.
        if ($parentId === 0 && $userId === (int) $post->post_author) {
            return new WP_Error('self_review', __('You cannot review your own post.', 'starwishx'));
        }

        if ($parentId !== 0) {
            $parent = get_comment($parentId);

            // Replies go under an approved review: the list shows no deeper.
            if (!$parent || (int) $parent->comment_parent !== 0 || $parent->comment_approved !== '1') {
                return new WP_Error('invalid_parent', __('Parent comment not found.', 'starwishx'));
            }

            // Without this, post_id=A & parent_id=<a comment on B> would graft
            // a reply onto the wrong thread.
            if ((int) $parent->comment_post_ID !== $postId) {
                return new WP_Error('parent_post_mismatch', __('Invalid parent comment.', 'starwishx'));
            }

            // The post's author replies to anyone; others in their own thread.
            if ($userId !== (int) $post->post_author && $userId !== (int) $parent->user_id) {
                return new WP_Error('forbidden', __('You cannot reply to this thread.', 'starwishx'));
            }
        }

        // The author's email, IP and agent: what WP's flood, duplicate and
        // disallowed-words checks look at (an empty email would match every
        // comment without one), and what its privacy exporter finds them by.
        $data = [
            'comment_post_ID'      => $postId,
            'comment_parent'       => $parentId,
            'user_id'              => $userId,
            'comment_author'       => $user->display_name ?: $user->user_login,
            'comment_author_email' => $user->user_email,
            'comment_author_url'   => '',
            'comment_author_IP'    => ClientIp::resolve(),
            'comment_agent'        => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 254),
            'comment_content'      => sanitize_textarea_field($content),
            'comment_type'         => 'comment',
            'comment_date'         => current_time('mysql'),
            'comment_date_gmt'     => current_time('mysql', true),
        ];

        $verdict = wp_allow_comment(wp_slash($data), true);
        if (is_wp_error($verdict)) {
            return self::refused($verdict);
        }
        if ($verdict === 'spam' || $verdict === 'trash') {
            return new WP_Error('comment_rejected', __('Your comment could not be published.', 'starwishx'));
        }

        $commentId = wp_insert_comment(wp_slash([...$data, 'comment_approved' => 1]));
        if (!$commentId) {
            return new WP_Error('comment_failed', __('Failed to post comment.', 'starwishx'));
        }

        if ($parentId === 0 && $rating >= 1 && $rating <= 5) {
            update_comment_meta($commentId, self::RATING_KEY, $rating);
            $this->refreshAggregates($postId);
        }

        $formatted = $this->formatSingleComment(get_comment($commentId), CommentViewer::of($userId, $post));

        do_action('sw_comment_posted', $commentId, [
            'user_id'   => $userId,
            'post_id'   => $postId,
            'parent_id' => $parentId,
            'rating'    => $rating,
        ]);

        return [
            'comment'    => $formatted,
            'aggregates' => $this->getAggregates($postId),
        ];
    }

    /**
     * Edit one's own approved comment, within EDIT_TIMEOUT_SECONDS unless a
     * moderator. A rating (1-5) applies to reviews only; 0 keeps it.
     *
     * The node comes back with replies: [] - the store keeps the replies it
     * has.
     *
     * @return array{comment: array<string, mixed>, aggregates: array{count: int, avg: float}}|WP_Error
     */
    #[\NoDiscard('a refused edit is only reported when the result is acted on')]
    public function updateComment(int $userId, int $commentId, string $content, int $rating = 0): array|WP_Error
    {
        $comment = get_comment($commentId);
        $post    = $comment ? get_post((int) $comment->comment_post_ID) : null;

        if (
            !$comment || !$post
            || $comment->comment_approved !== '1'
            || !in_array($post->post_type, self::ALLOWED_POST_TYPES, true)
        ) {
            return new WP_Error('not_found', __('Comment not found.', 'starwishx'));
        }
        if ((int) $comment->user_id !== $userId) {
            return new WP_Error('forbidden', __('You cannot edit this comment.', 'starwishx'));
        }

        $viewer = CommentViewer::of($userId, $post);
        if (!$viewer->canModerate && !self::withinEditWindow($comment)) {
            return new WP_Error('timeout', __('Edit time limit expired (15 min).', 'starwishx'));
        }

        $text = sanitize_textarea_field($content);
        if ($text !== $comment->comment_content) {
            $updated = wp_update_comment(wp_slash(['comment_ID' => $commentId, 'comment_content' => $text]), true);
            if ($updated === false || is_wp_error($updated)) {
                return new WP_Error('comment_failed', __('Failed to save the comment.', 'starwishx'));
            }
        }

        // Replies carry no rating: a post's author could rate their own post
        // through one.
        if ((int) $comment->comment_parent === 0 && $rating >= 1 && $rating <= 5) {
            update_comment_meta($commentId, self::RATING_KEY, $rating);
            $this->refreshAggregates($post->ID);
        }

        return [
            'comment'    => $this->formatSingleComment(get_comment($commentId), $viewer),
            'aggregates' => $this->getAggregates($post->ID),
        ];
    }

    /**
     * Recount the post's cached rating (the header badge, the listing and
     * Launchpad cards): approved reviews only, each author's latest rating
     * once, so posting more reviews doesn't add weight. A guest review
     * (user_id 0) counts on its own.
     */
    public function refreshAggregates(int $postId): void
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.comment_ID AS id, c.user_id AS user_id, m.meta_value AS rating
               FROM {$wpdb->comments} c
               JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = %s
              WHERE c.comment_post_ID = %d AND c.comment_approved = '1' AND c.comment_parent = 0
              ORDER BY c.comment_ID ASC",
            self::RATING_KEY,
            $postId
        )) ?? [];

        $ratingsByAuthor = [];
        foreach ($rows as $row) {
            $rating = (int) $row->rating;
            if ($rating >= 1 && $rating <= 5) {
                $author = (int) $row->user_id > 0 ? "user:{$row->user_id}" : "comment:{$row->id}";
                $ratingsByAuthor[$author][] = $rating;
            }
        }

        // Oldest first, so the last is the one that counts.
        $latest = array_map(array_last(...), $ratingsByAuthor);
        $count  = count($latest);

        update_post_meta($postId, self::COUNT_KEY, $count);
        update_post_meta($postId, self::AVG_KEY, $count > 0 ? round(array_sum($latest) / $count, 1) : 0);
    }

    /**
     * Recount every post that has ratings or a cached count: the catch-up
     * when AGGREGATES_VERSION changes.
     *
     * @return int The posts recounted.
     */
    public function refreshAllAggregates(): int
    {
        global $wpdb;

        $postIds = array_map(intval(...), $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT c.comment_post_ID
               FROM {$wpdb->comments} c
               JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = %s
             UNION
             SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::RATING_KEY,
            self::COUNT_KEY
        )));

        foreach ($postIds as $postId) {
            $this->refreshAggregates($postId);
        }

        return count($postIds);
    }

    /**
     * The post's cached rating.
     *
     * @return array{count: int, avg: float}
     */
    public function getAggregates(int $postId): array
    {
        return [
            'count' => (int) get_post_meta($postId, self::COUNT_KEY, true),
            'avg'   => (float) get_post_meta($postId, self::AVG_KEY, true),
        ];
    }

    /** Approved threads (top-level comments): what the pages are counted in. */
    public function countPostComments(int $postId): int
    {
        return (int) get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'count'   => true,
            'parent'  => 0,
        ]);
    }

    private function viewer(int $postId): CommentViewer
    {
        return CommentViewer::of(get_current_user_id(), get_post($postId));
    }

    /** Posted less than EDIT_TIMEOUT_SECONDS ago? Read in GMT, whatever the default timezone. */
    private static function withinEditWindow(WP_Comment $comment): bool
    {
        $posted = new DateTimeImmutable($comment->comment_date_gmt, new DateTimeZone('UTC'))->getTimestamp();

        return time() - $posted <= self::EDIT_TIMEOUT_SECONDS;
    }

    /**
     * wp_allow_comment()'s refusal, in this theme's words: core's duplicate
     * message carries an HTML entity the store would print as is.
     */
    private static function refused(WP_Error $error): WP_Error
    {
        $code = (string) $error->get_error_code();

        return new WP_Error($code, match ($code) {
            'comment_duplicate' => __('You have already posted this.', 'starwishx'),
            'comment_flood'     => __('You are posting too quickly. Please wait a moment.', 'starwishx'),
            default             => __('Your comment could not be published.', 'starwishx'),
        });
    }
}

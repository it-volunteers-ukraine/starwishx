<?php
// File: inc/comments/Api/CommentsController.php
declare(strict_types=1);

namespace Comments\Api;

use Comments\Services\CommentsService;
use Shared\Core\AbstractApiController;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

final class CommentsController extends AbstractApiController
{
    #[\Override]
    protected $namespace = 'comments/v1';

    private const int RATE_LIMIT_MAX    = 10;
    private const int RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;
    private const int PER_PAGE_MAX      = 50;

    /**
     * The HTTP status of each CommentsService error code. Unlisted codes are
     * 400, and not_found 404 (mapServiceError()'s defaults). The store shows
     * the message either way; the status is for everything else.
     */
    private const array ERROR_STATUS = [
        'invalid_user'         => 401,
        'not_published'        => 403,
        'not_commentable'      => 403,
        'self_review'          => 403,
        'forbidden'            => 403,
        'timeout'              => 403,
        'comment_duplicate'    => 409,
        'invalid_parent'       => 422,
        'parent_post_mismatch' => 422,
        'comment_rejected'     => 422,
        'comment_flood'        => 429,
        'comment_failed'       => 500,
    ];

    public function __construct(
        private readonly CommentsService $service
    ) {}

    #[\Override]
    public function registerRoutes(): void
    {
        // Shared validator: required, non-empty, capped length.
        $contentArg = [
            'required'          => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'validate_callback' => RestArg::stringLength(
                1,
                CommentsService::CONTENT_MAX_LENGTH,
                __('Comment', 'starwishx')
            ),
        ];

        // Typed ids: WP validates them (422 field_errors through the shared
        // error-shape filter). A negative parent_id used to slip past both
        // the reply and the self-review checks.
        $postIdArg = ['required' => true, 'type' => 'integer', 'minimum' => 1];

        // GET /comments/v1/comments: readable as far as the post is.
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getComments'],
            'permission_callback' => [$this, 'checkPostReadable'],
            'args'                => [
                'post_id'  => $postIdArg,
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                'per_page' => [
                    'default'           => CommentsService::ITEMS_PER_PAGE,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => RestArg::intRange(
                        1,
                        self::PER_PAGE_MAX,
                        __('Items per page', 'starwishx')
                    ),
                ],
            ],
        ]);

        // POST /comments/v1/comments: a review, or a reply (parent_id).
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'createComment'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'post_id'   => $postIdArg,
                'content'   => $contentArg,
                'rating'    => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5],
                'parent_id' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
            ],
        ]);

        // PUT /comments/v1/comments/{id}: edit one's own.
        register_rest_route($this->namespace, '/comments/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'updateComment'],
            'permission_callback' => [$this, 'checkCommentOwnerWithNonce'],
            'args'                => [
                'id'      => ['required' => true, 'sanitize_callback' => 'absint'],
                'content' => $contentArg,
                'rating'  => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5],
            ],
        ]);
    }

    /**
     * GET: the comments of a post the user may read (CommentsService::canRead()).
     * Anything else is 404, as for a post that doesn't exist: a draft's or a
     * private post's comments don't leak, nor does whether it exists.
     */
    public function checkPostReadable(WP_REST_Request $request): true|WP_Error
    {
        return $this->service->canRead((int) $request->get_param('post_id'))
            ?: new WP_Error('not_found', __('Post not found.', 'starwishx'), ['status' => 404]);
    }

    /**
     * Permission helper: current user owns the targeted comment.
     */
    public function checkCommentOwner(WP_REST_Request $request): bool
    {
        $id = (int) $request->get_param('id');

        if (!$id || !is_user_logged_in()) {
            return false;
        }

        $comment = get_comment($id);

        return $comment && (int) $comment->user_id === get_current_user_id();
    }

    /**
     * Composite: comment ownership + wp_rest nonce.
     *
     * Mirrors AbstractLaunchpadController::checkPostOwnerWithNonce — binds the
     * write to a page-load origin so non-cookie auth paths (Application
     * Passwords, JWT) can't update another user's comment with a leaked token.
     */
    public function checkCommentOwnerWithNonce(WP_REST_Request $request): bool|WP_Error
    {
        if (!$this->checkCommentOwner($request)) {
            return false;
        }

        return $this->checkRestNonce($request);
    }

    public function getComments(WP_REST_Request $request): WP_REST_Response
    {
        $postId  = (int) $request->get_param('post_id');
        $page    = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');

        $items = $this->service->getPostComments($postId, $perPage, ($page - 1) * $perPage);
        $total = $this->service->countPostComments($postId);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function createComment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $result = $this->service->addComment(
            $userId,
            (int) $request->get_param('post_id'),
            (string) $request->get_param('content'),
            (int) $request->get_param('rating'),
            (int) $request->get_param('parent_id')
        );

        return is_wp_error($result)
            ? $this->mapServiceError($result, self::ERROR_STATUS)
            : $this->success($result);
    }

    public function updateComment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $result = $this->service->updateComment(
            $userId,
            (int) $request->get_param('id'),
            (string) $request->get_param('content'),
            (int) $request->get_param('rating')
        );

        return is_wp_error($result)
            ? $this->mapServiceError($result, self::ERROR_STATUS)
            : $this->success($result);
    }

    /**
     * Per-user write rate limit (shared bucket between create and update).
     *
     * Returns a mapped WP_Error (HTTP 429) when the limit is exceeded; null
     * otherwise. Every attempt counts — same pattern as ContactController.
     *
     * The 429 message names the action and shows a friendly, single-unit
     * wait duration via human_time_diff() (e.g., "1 hour", "30 mins"),
     * derived from the window so the wording tracks any future changes.
     */
    private function applyWriteRateLimit(int $userId): ?WP_Error
    {
        $key = RateLimitPolicy::key('comment_write', (string) $userId);

        $message = sprintf(
            /* translators: 1: action name, 2: human-readable wait duration */
            __('%1$s limit reached. Please wait %2$s before trying again.', 'starwishx'),
            __('Comment posting', 'starwishx'),
            human_time_diff(time(), time() + self::RATE_LIMIT_WINDOW)
        );

        $check = RateLimitPolicy::check(
            $key,
            self::RATE_LIMIT_MAX,
            self::RATE_LIMIT_WINDOW,
            $message
        );
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        RateLimitPolicy::hit($key, self::RATE_LIMIT_WINDOW);

        return null;
    }
}

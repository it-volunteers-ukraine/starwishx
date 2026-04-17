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

class CommentsController extends AbstractApiController
{
    protected $namespace = 'comments/v1';

    private const RATE_LIMIT_MAX    = 10;
    private const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;
    private const PER_PAGE_MAX      = 50;

    private CommentsService $service;

    public function __construct(CommentsService $service)
    {
        $this->service = $service;
    }

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

        // 1. Get List with Pagination
        // GET /comments/v1/comments
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getComments'],
            'permission_callback' => '__return_true', // Publicly readable
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
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

        // 2. POST (Create)
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'createComment'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'post_id'   => ['required' => true, 'sanitize_callback' => 'absint'],
                'content'   => $contentArg,
                'rating'    => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5],
                'parent_id' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        // 3. PUT (Update)
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

        $offset  = ($page - 1) * $perPage;

        $items = $this->service->getPostComments($postId, $perPage, $offset);
        $total = $this->service->countPostComments($postId);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    public function createComment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $postId   = (int) $request->get_param('post_id');
        $content  = $request->get_param('content');
        $rating   = (int) $request->get_param('rating');
        $parentId = (int) $request->get_param('parent_id');

        $result = $this->service->addComment($userId, $postId, $content, $rating, $parentId);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success($result);
    }

    public function updateComment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyWriteRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $commentId = (int) $request->get_param('id');
        $content   = $request->get_param('content');
        $rating    = (int) $request->get_param('rating');

        $result = $this->service->updateComment($userId, $commentId, $content, $rating);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success($result);
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

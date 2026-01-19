<?php
// inc/launchpad/Api/CommentsController.php
declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\CommentsService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class CommentsController extends AbstractLaunchpadController
{
    private CommentsService $service;

    public function __construct(CommentsService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        // 1. Get List with Pagination
        // GET /launchpad/v1/comments
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getComments'],
            'permission_callback' => '__return_true', // Publicly readable
            'args'                => [
                'post_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ],
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'default' => CommentsService::ITEMS_PER_PAGE,
                    'sanitize_callback' => 'absint'
                ]
            ],
        ]);

        // 2. POST (Create)
        register_rest_route($this->namespace, '/comments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'createComment'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'post_id'   => ['required' => true, 'sanitize_callback' => 'absint'],
                'content'   => ['required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
                // Check if child then rating 0 
                'rating'    => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5], 
                'parent_id' => ['type' => 'integer', 'default' => 0], // <--- NEW ARG
            ],
        ]);

        // 3. PUT (Update)
        register_rest_route($this->namespace, '/comments/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'updateComment'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'id'      => ['required' => true, 'sanitize_callback' => 'absint'],
                'content' => ['required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
                // Chech if child then rating 0 also???
                'rating'  => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5],
            ],
        ]);
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

    public function createComment(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $postId   = (int) $request->get_param('post_id');
        $content  = $request->get_param('content');
        $rating   = (int) $request->get_param('rating');
        $parentId = (int) $request->get_param('parent_id'); // <--- Fetch
        $userId   = get_current_user_id();

        $result = $this->service->addComment($userId, $postId, $content, $rating, $parentId);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success($result);
    }

    public function updateComment(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $commentId = (int) $request->get_param('id');
        $content   = $request->get_param('content');
        $rating    = (int) $request->get_param('rating');
        $userId    = get_current_user_id();

        $result = $this->service->updateComment($userId, $commentId, $content, $rating);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success($result);
    }
}

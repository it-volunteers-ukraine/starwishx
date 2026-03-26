<?php
// File: inc/chat/Api/ActivityController.php

declare(strict_types=1);

namespace Chat\Api;

use Shared\Core\AbstractApiController;
use Chat\Services\ActivityService;

class ActivityController extends AbstractApiController
{
    protected $namespace = 'chat/v1';
    private ActivityService $service;

    public function __construct(ActivityService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/activity', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getActivity'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 15, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route($this->namespace, '/activity/(?P<id>\d+)/read', [
            'methods'             => 'POST',
            'callback'            => [$this, 'markRead'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);

        register_rest_route($this->namespace, '/activity/read-all', [
            'methods'             => 'POST',
            'callback'            => [$this, 'markAllRead'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);
    }

    public function getActivity(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $userId  = get_current_user_id();
        $page    = (int) $request->get_param('page');
        $perPage = min((int) $request->get_param('per_page'), 50);

        $data = $this->service->getActivity($userId, $page, $perPage);
        $data['unreadCount'] = $this->service->getUnreadCount($userId);

        return $this->success($data);
    }

    public function markRead(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id     = (int) $request->get_param('id');
        $userId = get_current_user_id();

        $result = $this->service->markRead($id, $userId);

        if (! $result) {
            return $this->error(__('Notification not found.', 'starwishx'), 404, 'not_found');
        }

        return $this->success([
            'unreadCount' => $this->service->getUnreadCount($userId),
        ]);
    }

    public function markAllRead(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $userId = get_current_user_id();
        $count  = $this->service->markAllRead($userId);

        return $this->success([
            'marked'      => $count,
            'unreadCount' => 0,
        ]);
    }
}

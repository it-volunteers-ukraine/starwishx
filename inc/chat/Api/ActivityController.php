<?php
// File: inc/chat/Api/ActivityController.php

declare(strict_types=1);

namespace Chat\Api;

use Shared\Core\AbstractApiController;
use Shared\Policy\RateLimitPolicy;
use Shared\Validation\RestArg;
use Chat\Services\ActivityService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ActivityController extends AbstractApiController
{
    protected $namespace = 'chat/v1';

    private const PER_PAGE_MAX     = 50;
    private const PER_PAGE_DEFAULT = 15;

    // Mark-read and mark-all-read share one bucket — rotating endpoints
    // shouldn't multiply the budget. 200/hr covers a heavy manual session
    // (click-through of the feed) with headroom.
    private const MARK_RATE_LIMIT_MAX    = 200;
    private const MARK_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    // GET /activity has its own bucket so pagination / manual refresh
    // doesn't eat the mark budget. Each fetch runs two queries (list +
    // unread count), which is why this ceiling is deliberately below the
    // mark budget.
    private const FETCH_RATE_LIMIT_MAX    = 120;
    private const FETCH_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

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
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'page'     => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'validate_callback' => RestArg::intRange(
                        1,
                        PHP_INT_MAX,
                        __('Page', 'starwishx')
                    ),
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'default'           => self::PER_PAGE_DEFAULT,
                    'validate_callback' => RestArg::intRange(
                        1,
                        self::PER_PAGE_MAX,
                        __('Per page', 'starwishx')
                    ),
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/activity/(?P<id>\d+)/read', [
            'methods'             => 'POST',
            'callback'            => [$this, 'markRead'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'id' => [
                    'validate_callback' => RestArg::intRange(
                        1,
                        PHP_INT_MAX,
                        __('Notification ID', 'starwishx')
                    ),
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/activity/read-all', [
            'methods'             => 'POST',
            'callback'            => [$this, 'markAllRead'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
        ]);
    }

    public function getActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyFetchRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $page    = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');

        $data = $this->service->getActivity($userId, $page, $perPage);
        $data['unreadCount'] = $this->service->getUnreadCount($userId);

        return $this->success($data);
    }

    public function markRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyMarkRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $id     = (int) $request->get_param('id');
        $result = $this->service->markRead($id, $userId);

        if (!$result) {
            // 404 intentionally conflates "not found", "not yours", and
            // the already-read no-op UPDATE. The repository enforces
            // recipient scoping in the WHERE clause, so distinguishing
            // these cases would only leak existence.
            return $this->error(__('Notification not found.', 'starwishx'), 404, 'not_found');
        }

        return $this->success([
            'unreadCount' => $this->service->getUnreadCount($userId),
        ]);
    }

    public function markAllRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyMarkRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $count = $this->service->markAllRead($userId);

        return $this->success([
            'marked'      => $count,
            'unreadCount' => 0,
        ]);
    }

    /**
     * Per-user rate limit — mark-read + mark-all-read share one bucket.
     */
    private function applyMarkRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'chat_mark',
            $userId,
            self::MARK_RATE_LIMIT_MAX,
            self::MARK_RATE_LIMIT_WINDOW,
            __('Notification changes', 'starwishx')
        );
    }

    /**
     * Per-user rate limit — GET /activity fetches. Separate from the mark
     * bucket so pagination / manual refresh doesn't consume action budget.
     */
    private function applyFetchRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'chat_fetch',
            $userId,
            self::FETCH_RATE_LIMIT_MAX,
            self::FETCH_RATE_LIMIT_WINDOW,
            __('Activity feed', 'starwishx')
        );
    }

    /**
     * Generic per-user rate-limit guard with an action-named, friendly message.
     *
     * Mirrors ProfileController::applyRateLimit — the wait duration is
     * derived from the window via human_time_diff() so the wording tracks
     * any future window change.
     *
     * `mapServiceError()` translates the policy's `rate_limited` code into 429.
     */
    private function applyRateLimit(
        string $action,
        int $userId,
        int $max,
        int $window,
        string $actionLabel
    ): ?WP_Error {
        $key = RateLimitPolicy::key($action, (string) $userId);

        $message = sprintf(
            /* translators: 1: action name (e.g., "Notification changes"), 2: human-readable wait duration */
            __('%1$s limit reached. Please wait %2$s before trying again.', 'starwishx'),
            $actionLabel,
            human_time_diff(time(), time() + $window)
        );

        $check = RateLimitPolicy::check($key, $max, $window, $message);
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        RateLimitPolicy::hit($key, $window);

        return null;
    }
}

<?php
// File: inc/tour/Api/TourController.php

declare(strict_types=1);

namespace Tour\Api;

use Shared\Core\AbstractApiController;
use Shared\Policy\RateLimitPolicy;
use Tour\Core\TourCore;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class TourController extends AbstractApiController
{
    protected $namespace = 'tour/v1';

    // Shared bucket for complete/dismiss/reset — rotating endpoints
    // shouldn't multiply the budget. Tour state changes are once-per-user
    // events, so 60/hr is generous for legitimate use.
    private const STATE_RATE_LIMIT_MAX    = 60;
    private const STATE_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    // GET /scenarios has its own bucket — keeps repeated client-side
    // refresh from eating into the state-write budget.
    private const FETCH_RATE_LIMIT_MAX    = 120;
    private const FETCH_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    // Defense in depth: even if a rogue ID slips past the allowlist later,
    // the user_meta array can't grow unbounded.
    private const MAX_STORED_TOURS = 100;

    public function registerRoutes(): void
    {
        $tourIdArg = [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            // Authoritative allowlist: only registered scenario IDs accepted.
            // Stops arbitrary strings from accumulating in sw_completed_tours
            // / sw_dismissed_tours user_meta. Scenarios are registered on
            // init priority 22; REST callbacks run after that, so the
            // registry is always populated when this validator fires.
            'validate_callback' => function ($value) {
                if (!is_string($value) || $value === '') {
                    return new WP_Error(
                        'invalid_data',
                        __('Tour ID is required.', 'starwishx'),
                        ['status' => 422]
                    );
                }
                if (!TourCore::instance()->hasScenario($value)) {
                    return new WP_Error(
                        'invalid_data',
                        __('Unknown tour.', 'starwishx'),
                        ['status' => 422]
                    );
                }
                return true;
            },
        ];

        register_rest_route($this->namespace, '/scenarios', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getScenarios'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
        ]);

        register_rest_route($this->namespace, '/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'completeTour'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => ['tourId' => $tourIdArg],
        ]);

        register_rest_route($this->namespace, '/dismiss', [
            'methods'             => 'POST',
            'callback'            => [$this, 'dismissTour'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => ['tourId' => $tourIdArg],
        ]);

        register_rest_route($this->namespace, '/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resetTour'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => ['tourId' => $tourIdArg],
        ]);
    }

    public function getScenarios(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyFetchRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $scenarios = TourCore::instance()->buildScenarioData($userId);

        return $this->success(['scenarios' => $scenarios]);
    }

    public function completeTour(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyStateRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $tourId    = (string) $request->get_param('tourId');
        $completed = $this->readTourList($userId, 'sw_completed_tours');

        if (!in_array($tourId, $completed, true)) {
            $completed[] = $tourId;
            $completed   = $this->capList($completed);
            update_user_meta($userId, 'sw_completed_tours', $completed);
        }

        return $this->success(['completed' => $completed]);
    }

    public function dismissTour(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyStateRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $tourId    = (string) $request->get_param('tourId');
        $dismissed = $this->readTourList($userId, 'sw_dismissed_tours');

        if (!in_array($tourId, $dismissed, true)) {
            $dismissed[] = $tourId;
            $dismissed   = $this->capList($dismissed);
            update_user_meta($userId, 'sw_dismissed_tours', $dismissed);
        }

        return $this->success(['dismissed' => $dismissed]);
    }

    public function resetTour(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyStateRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $tourId = (string) $request->get_param('tourId');

        $completed = array_values(array_filter(
            $this->readTourList($userId, 'sw_completed_tours'),
            fn($id) => $id !== $tourId
        ));
        update_user_meta($userId, 'sw_completed_tours', $completed);

        $dismissed = array_values(array_filter(
            $this->readTourList($userId, 'sw_dismissed_tours'),
            fn($id) => $id !== $tourId
        ));
        update_user_meta($userId, 'sw_dismissed_tours', $dismissed);

        return $this->success([
            'completed' => $completed,
            'dismissed' => $dismissed,
        ]);
    }

    /**
     * Read a tour-list user_meta entry, normalising any non-array storage
     * (corrupted meta, legacy serialized scalar) to an empty array so
     * downstream array_* / in_array() calls are safe.
     */
    private function readTourList(int $userId, string $metaKey): array
    {
        $list = get_user_meta($userId, $metaKey, true);
        return is_array($list) ? array_values($list) : [];
    }

    /**
     * Cap the stored list at MAX_STORED_TOURS by trimming the oldest
     * entries. Defense-in-depth — the validate_callback allowlist already
     * prevents random strings from getting in, but this contains damage
     * if a future scenario churn produces a long history.
     */
    private function capList(array $list): array
    {
        if (count($list) <= self::MAX_STORED_TOURS) {
            return $list;
        }
        return array_slice($list, -self::MAX_STORED_TOURS);
    }

    /** Per-user rate limit — complete/dismiss/reset share one bucket. */
    private function applyStateRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'tour_state',
            $userId,
            self::STATE_RATE_LIMIT_MAX,
            self::STATE_RATE_LIMIT_WINDOW,
            __('Tour changes', 'starwishx')
        );
    }

    /** Per-user rate limit — GET /scenarios. Separate from state writes. */
    private function applyFetchRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'tour_fetch',
            $userId,
            self::FETCH_RATE_LIMIT_MAX,
            self::FETCH_RATE_LIMIT_WINDOW,
            __('Tour data', 'starwishx')
        );
    }

    /**
     * Generic per-user rate-limit guard with an action-named, friendly message.
     *
     * Mirrors ProfileController::applyRateLimit / ActivityController::applyRateLimit
     * — wait duration is derived from the window via human_time_diff() so the
     * wording tracks any future window change.
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
            /* translators: 1: action name (e.g., "Tour changes"), 2: human-readable wait duration */
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

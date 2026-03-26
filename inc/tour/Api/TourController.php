<?php
// File: inc/tour/Api/TourController.php

declare(strict_types=1);

namespace Tour\Api;

use Shared\Core\AbstractApiController;
use WP_REST_Request;
use WP_REST_Response;

class TourController extends AbstractApiController
{
    protected $namespace = 'tour/v1';

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'completeTour'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);

        register_rest_route($this->namespace, '/dismiss', [
            'methods'             => 'POST',
            'callback'            => [$this, 'dismissTour'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);

        register_rest_route($this->namespace, '/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resetTour'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);
    }

    public function completeTour(WP_REST_Request $request): WP_REST_Response
    {
        $tourId = sanitize_text_field($request->get_param('tourId') ?? '');
        if (empty($tourId)) {
            return new WP_REST_Response(['message' => 'Tour ID required'], 400);
        }

        $userId = get_current_user_id();
        $completed = get_user_meta($userId, 'sw_completed_tours', true) ?: [];
        if (!is_array($completed)) {
            $completed = [];
        }

        if (!in_array($tourId, $completed, true)) {
            $completed[] = $tourId;
            update_user_meta($userId, 'sw_completed_tours', $completed);
        }

        return $this->success(['completed' => $completed]);
    }

    public function dismissTour(WP_REST_Request $request): WP_REST_Response
    {
        $tourId = sanitize_text_field($request->get_param('tourId') ?? '');
        if (empty($tourId)) {
            return new WP_REST_Response(['message' => 'Tour ID required'], 400);
        }

        $userId = get_current_user_id();
        $dismissed = get_user_meta($userId, 'sw_dismissed_tours', true) ?: [];
        if (!is_array($dismissed)) {
            $dismissed = [];
        }

        if (!in_array($tourId, $dismissed, true)) {
            $dismissed[] = $tourId;
            update_user_meta($userId, 'sw_dismissed_tours', $dismissed);
        }

        return $this->success(['dismissed' => $dismissed]);
    }

    public function resetTour(WP_REST_Request $request): WP_REST_Response
    {
        $tourId = sanitize_text_field($request->get_param('tourId') ?? '');
        if (empty($tourId)) {
            return new WP_REST_Response(['message' => 'Tour ID required'], 400);
        }

        $userId = get_current_user_id();

        // Remove from completed
        $completed = get_user_meta($userId, 'sw_completed_tours', true) ?: [];
        if (is_array($completed)) {
            $completed = array_values(array_filter($completed, fn($id) => $id !== $tourId));
            update_user_meta($userId, 'sw_completed_tours', $completed);
        }

        // Remove from dismissed
        $dismissed = get_user_meta($userId, 'sw_dismissed_tours', true) ?: [];
        if (is_array($dismissed)) {
            $dismissed = array_values(array_filter($dismissed, fn($id) => $id !== $tourId));
            update_user_meta($userId, 'sw_dismissed_tours', $dismissed);
        }

        return $this->success(['completed' => $completed, 'dismissed' => $dismissed]);
    }
}

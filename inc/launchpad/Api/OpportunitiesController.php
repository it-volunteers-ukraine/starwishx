<?php
// File: inc/launchpad/Api/OpportunitiesController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\OpportunitiesService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class OpportunitiesController extends AbstractApiController
{
    private OpportunitiesService $service;

    public function __construct(?OpportunitiesService $service = null)
    {
        $this->service = $service ?? new OpportunitiesService();
    }

    public function registerRoutes(): void
    {
        // GET List
        register_rest_route($this->namespace, '/opportunities', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getOpportunities'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => OpportunitiesService::ITEMS_PER_PAGE],
            ],
        ]);

        // NEW: GET Single (Fetch full details for edit)
        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSingle'],
            'permission_callback' => [$this, 'checkOwner'], // Ensure user owns it
        ]);

        // POST & PUT Args definition
        $saveArgs = [
            'title'           => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            // Group 1
            'applicant_name'  => ['sanitize_callback' => 'sanitize_text_field'],
            'applicant_mail'  => ['sanitize_callback' => 'sanitize_email'],
            'applicant_phone' => ['sanitize_callback' => 'sanitize_text_field'],
            // Group 2
            'company'         => ['sanitize_callback' => 'sanitize_text_field'],
            'date_starts'     => ['sanitize_callback' => 'sanitize_text_field'],
            'date_ends'       => ['sanitize_callback' => 'sanitize_text_field'],
            'category'        => ['sanitize_callback' => 'absint'],
            'subcategory'     => ['type' => 'array', 'items' => ['type' => 'integer']],
            'country'         => ['sanitize_callback' => 'absint'],
            'city'            => ['sanitize_callback' => 'sanitize_text_field'],
            'sourcelink'      => ['sanitize_callback' => 'esc_url_raw'],
            'seekers'         => ['type' => 'array', 'items' => ['type' => 'integer']],
            // Group 3
            'description'     => ['sanitize_callback' => 'sanitize_textarea_field'],
            'requirements'    => ['sanitize_callback' => 'sanitize_textarea_field'],
            'details'         => ['sanitize_callback' => 'sanitize_textarea_field'],
        ];

        register_rest_route($this->namespace, '/opportunities', [
            'methods'  => 'POST',
            'callback' => [$this, 'saveOpportunity'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'     => $saveArgs,
        ]);

        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)', [
            'methods'  => 'PUT',
            'callback' => [$this, 'saveOpportunity'], // Reusing same callback logic
            'permission_callback' => [$this, 'checkOwner'],
            'args'     => $saveArgs,
        ]);
    }
    // Unified Callback
    public function saveOpportunity(WP_REST_Request $request)
    {
        $id = $request->get_param('id') ? (int) $request->get_param('id') : null;

        // FIX: Retrieve params individually to trigger REST API sanitizers
        $params = [
            'title'           => $request->get_param('title'),
            // Group 1
            'applicant_name'  => $request->get_param('applicant_name'),
            'applicant_mail'  => $request->get_param('applicant_mail'),
            'applicant_phone' => $request->get_param('applicant_phone'),
            // Group 2
            'company'         => $request->get_param('company'),
            'date_starts'     => $request->get_param('date_starts'),
            'date_ends'       => $request->get_param('date_ends'),
            'category'        => $request->get_param('category'),
            'country'         => $request->get_param('country'),
            'city'            => $request->get_param('city'),
            'sourcelink'      => $request->get_param('sourcelink'),
            // Arrays: explicitly get them
            'subcategory'     => $request->get_param('subcategory'),
            'seekers'         => $request->get_param('seekers'),
            // Group 3
            'description'     => $request->get_param('description'),
            'requirements'    => $request->get_param('requirements'),
            'details'         => $request->get_param('details'),
        ];

        // Defensive Coding: Ensure arrays are actually arrays of ints
        $params['seekers'] = is_array($params['seekers'])
            ? array_map('intval', $params['seekers'])
            : [];

        $params['subcategory'] = is_array($params['subcategory'])
            ? array_map('intval', $params['subcategory'])
            : [];

        // Pass clean params to service
        $result = $this->service->saveOpportunity($params, $id);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success(['id' => $result, 'message' => __('Saved successfully', 'starwishx')]);
    }

    public function checkOwner(WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) return false;

        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        return $post && (int) $post->post_author === get_current_user_id();
    }

    public function getOpportunities(WP_REST_Request $request): WP_REST_Response
    {
        // ... (Same as before) ...
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $userId = get_current_user_id();

        $items = $this->service->getUserOpportunities(
            $userId,
            $perPage,
            ($page - 1) * $perPage
        );

        $total = $this->service->countUserOpportunities($userId);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    public function createOpportunity(WP_REST_Request $request)
    {
        $title = $request->get_param('title');
        $content = $request->get_param('content');

        $id = $this->service->saveOpportunity([
            'title' => $title,
            'content' => $content,
            'author' => get_current_user_id()
        ]);

        if (is_wp_error($id)) return $this->error($id->get_error_message());

        return $this->success(['id' => $id, 'message' => __('Opportunity created.', 'starwishx')]);
    }

    public function updateOpportunity(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params(); // Get JSON body

        // Prepare data array
        $data = [];
        if (isset($params['title'])) $data['title'] = $params['title'];
        if (isset($params['content'])) $data['content'] = $params['content'];

        $result = $this->service->saveOpportunity($data, $id);

        if (is_wp_error($result)) return $this->error($result->get_error_message());

        return $this->success(['success' => true, 'message' => __('Opportunity updated.', 'starwishx')]);
    }
    /**
     * Fetch a single opportunity with full details.
     */
    public function getSingle(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $data = $this->service->getSingleOpportunity(get_current_user_id(), $id);

        if (!$data) {
            return $this->error(__('Opportunity not found.', 'starwishx'), 404);
        }

        return $this->success($data);
    }
}

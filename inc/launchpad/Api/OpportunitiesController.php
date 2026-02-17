<?php
// File: inc/launchpad/Api/OpportunitiesController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\OpportunitiesService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for Opportunity entities.
 * 
 * Architecture:
 * - Inherits from AbstractLaunchpadController for standard Namespace/Auth.
 * - Uses OpportunitiesService for business logic (Persistence/Queries).
 */
class OpportunitiesController extends AbstractLaunchpadController
{
    private OpportunitiesService $service;

    /**
     * Constructor with Dependency Injection.
     * Ensure this is injected in LaunchpadCore.php
     */
    public function __construct(OpportunitiesService $service)
    {
        $this->service = $service;
    }

    /**
     * Register REST API routes.
     */
    public function registerRoutes(): void
    {
        // Define shared validation/sanitization arguments for Create/Update
        $saveArgs = [
            'title'  => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'status' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function ($param) {
                    return in_array($param, ['draft', 'pending', 'publish']);
                }
            ],
            'applicant_name'  => ['sanitize_callback' => 'sanitize_text_field'],
            'applicant_mail'  => ['sanitize_callback' => 'sanitize_email'],
            'applicant_phone' => ['sanitize_callback' => 'sanitize_text_field'],
            'company'         => ['sanitize_callback' => 'sanitize_text_field'],
            'date_starts'     => ['sanitize_callback' => 'sanitize_text_field'],
            'date_ends'       => ['sanitize_callback' => 'sanitize_text_field'],
            'category'        => ['type' => 'array', 'items' => ['type' => 'integer']],
            'subcategory'     => ['type' => 'array', 'items' => ['type' => 'integer']],
            'country'         => ['sanitize_callback' => 'absint'],
            'locations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'string',
                            'required' => true,
                            'sanitize_callback' => 'sanitize_text_field'
                        ],
                        // We allow name/level to pass through for UI convenience, 
                        // but Service ignores them for DB insert.
                        'name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    ]
                ],
            ],
            'city'            => ['sanitize_callback' => 'sanitize_text_field'],
            'sourcelink'      => ['sanitize_callback' => 'esc_url_raw'],
            'seekers'         => ['type' => 'array', 'items' => ['type' => 'integer']],
            'description'     => ['sanitize_callback' => 'sanitize_textarea_field'],
            'requirements'    => ['sanitize_callback' => 'sanitize_textarea_field'],
            'details'         => ['sanitize_callback' => 'sanitize_textarea_field'],
            'document_id'    => [
                'required'          => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return is_numeric($param) || is_null($param);
                }
            ],
        ];

        // 1. COLLECTION: GET /opportunities (List)
        register_rest_route($this->namespace, '/opportunities', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getOpportunities'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => OpportunitiesService::ITEMS_PER_PAGE,
                    'sanitize_callback' => 'absint',
                ],
                // Accept statuses as an array
                'statuses' => [
                    'type'              => 'array',
                    'items'             => ['type' => 'string'],
                    'sanitize_callback' => function ($val) {
                        return array_map('sanitize_key', (array)$val);
                    },
                ],
            ],
        ]);

        // 2. CREATE: POST /opportunities
        register_rest_route($this->namespace, '/opportunities', [
            'methods'             => 'POST',
            'callback'            => [$this, 'saveOpportunity'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => $saveArgs,
        ]);

        // 3. RESOURCE: GET/PUT /opportunities/{id}
        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getSingle'],
                'permission_callback' => [$this, 'checkPostOwner'], // Inherited from Abstract
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveOpportunity'],
                // Inherited from Abstract
                'permission_callback' => [$this, 'checkPostOwner'],
                'args'                => $saveArgs,
            ],
        ]);

        // 4. ACTION: POST /opportunities/{id}/status
        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleStatusChange'],
            'permission_callback' => [$this, 'checkPostOwner'],
            'args'                => [
                'status' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => fn($param) => in_array($param, ['pending', 'draft']),
                ],
            ],
        ]);

        // Locations Search Endpoint
        register_rest_route($this->namespace, '/opportunities/locations', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchLocations'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args'                => [
                'search' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($p) {
                        return strlen($p) >= 2;
                    }
                ],
                'levels' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'default' => []
                ]
            ]
        ]);
    }

    public function searchLocations(WP_REST_Request $request): WP_REST_Response
    {
        // $query = $request->get_param('search');
        // $results = $this->service->searchKatottg($query);
        // return $this->success($results);

        $query = $request->get_param('search');
        $levels = $request->get_param('levels') ?: []; // Get the array

        $results = $this->service->searchKatottg($query, $levels);

        return $this->success($results);
    }

    /**
     * Handles specialized status changes.
     */
    public function handleStatusChange(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id     = (int) $request->get_param('id');
        $status = $request->get_param('status');

        $result = $this->service->updateStatus(get_current_user_id(), $id, $status);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message());
        }

        return $this->success([
            'success' => true,
            'message' => __('Status updated.', 'starwishx')
        ]);
    }

    /**
     * GET Handler: Retrieves a paginated list of opportunities for the current user.
     */
    public function getOpportunities(WP_REST_Request $request): WP_REST_Response
    {
        $page    = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');
        $userId  = get_current_user_id();
        $filters = [
            'statuses' => $request->get_param('statuses') ?: [],
        ];

        $items = $this->service->getUserOpportunities(
            $userId,
            $filters,
            $perPage,
            ($page - 1) * $perPage
        );

        $total = $this->service->countUserOpportunities($userId, $filters);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * GET Handler: Retrieves a single opportunity.
     * This is what is called when you click "Edit" on the frontend.
     */
    public function getSingle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request->get_param('id');
        $data = $this->service->getSingleOpportunity(get_current_user_id(), $id);

        if (!$data) {
            return $this->error(__('Opportunity not found.', 'starwishx'), 404);
        }

        return $this->success($data);
    }

    /**
     * POST/PUT Handler: Creates or updates an opportunity.
     * Unified callback logic but with strictly controlled ID source.
     */
    public function saveOpportunity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $method = $request->get_method(); // POST or PUT
        /**
         * SECURITY: Determine the ID.
         * 
         * If PUT: We ONLY take the ID from the URL route (?P<id>\d+).
         * If POST: We hard-force the ID to null, ignoring the body entirely.
         */
        $id = null;
        if ($method === 'PUT' || $method === 'PATCH') {
            // fetch directly from the URL attributes to avoid Body injection
            $id = (int) $request->get_url_params()['id'];
            // Now, even if the user sends {"id": 500} in a POST, 
            // our $id variable remains NULL.
        }

        $params = [
            'title'           => $request->get_param('title'),
            'status'          => $request->get_param('status'),
            'applicant_name'  => $request->get_param('applicant_name'),
            'applicant_mail'  => $request->get_param('applicant_mail'),
            'applicant_phone' => $request->get_param('applicant_phone'),
            'company'         => $request->get_param('company'),
            'date_starts'     => $request->get_param('date_starts'),
            'date_ends'       => $request->get_param('date_ends'),
            'category'        => (array) $request->get_param('category'),
            'country'         => $request->get_param('country'),
            'locations'       => (array) $request->get_param('locations'),
            'city'            => $request->get_param('city'),
            'sourcelink'      => $request->get_param('sourcelink'),
            'subcategory'     => (array) $request->get_param('subcategory'),
            'seekers'         => (array) $request->get_param('seekers'),
            'description'     => $request->get_param('description'),
            'requirements'    => $request->get_param('requirements'),
            'details'         => $request->get_param('details'),
            'document_id'     => $request->get_param('document_id'),
        ];

        // Pass the strictly controlled $id to the service
        $result = $this->service->saveOpportunity($params, $id);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 400);
        }

        return $this->success([
            'id'      => $result,
            'message' => $id ? __('Updated successfully', 'starwishx') : __('Created successfully', 'starwishx')
        ]);
    }
}

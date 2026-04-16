<?php
// File: inc/launchpad/Api/OpportunitiesController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\OpportunitiesService;
use Launchpad\Services\ProfileService;
use Shared\Policy\RateLimitPolicy;
use Shared\Sanitize\InputSanitizer;
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
    private const WRITE_RATE_LIMIT_MAX     = 30;
    private const WRITE_RATE_LIMIT_WINDOW  = HOUR_IN_SECONDS;
    private const SEARCH_RATE_LIMIT_MAX    = 60;
    private const SEARCH_RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

    private const PER_PAGE_MAX          = 50;
    private const SEARCH_MAX_LENGTH     = 100;
    private const CATEGORY_MAX_ITEMS    = 10;
    private const SUBCATEGORY_MAX_ITEMS = 30;
    private const SEEKERS_MAX_ITEMS     = 10;
    private const LOCATIONS_MAX_ITEMS   = 20;
    private const STATUSES_MAX_ITEMS    = 3;
    private const LEVELS_MAX_ITEMS      = 4;

    private OpportunitiesService $service;
    private ProfileService $profileService;

    /**
     * Constructor with Dependency Injection.
     * Ensure this is injected in LaunchpadCore.php
     */
    public function __construct(OpportunitiesService $service, ProfileService $profileService)
    {
        $this->service = $service;
        $this->profileService = $profileService;
    }

    /**
     * Register REST API routes.
     */
    public function registerRoutes(): void
    {
        // Define shared validation/sanitization arguments for Create/Update
        // Note: applicant_name, applicant_mail, applicant_phone are auto-filled from user profile
        $saveArgs = [
            'title'  => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($param) {
                    return mb_strlen(trim($param)) <= OpportunitiesService::TITLE_MAX_LENGTH;
                },
            ],
            'status' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function ($param) {
                    return in_array($param, ['draft', 'pending'], true);
                }
            ],
            'company'         => ['sanitize_callback' => 'sanitize_text_field'],
            'date_starts'     => ['sanitize_callback' => 'sanitize_text_field'],
            'date_ends'       => ['sanitize_callback' => 'sanitize_text_field'],
            'category'        => [
                'type'              => 'array',
                'items'             => ['type' => 'integer'],
                'validate_callback' => fn($v) => is_array($v) && count($v) <= self::CATEGORY_MAX_ITEMS,
            ],
            'subcategory'     => [
                'type'              => 'array',
                'items'             => ['type' => 'integer'],
                'validate_callback' => fn($v) => is_array($v) && count($v) <= self::SUBCATEGORY_MAX_ITEMS,
            ],
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
                'validate_callback' => fn($v) => is_array($v) && count($v) <= self::LOCATIONS_MAX_ITEMS,
            ],
            'city'            => ['sanitize_callback' => 'sanitize_text_field'],
            'sourcelink'      => ['sanitize_callback' => [InputSanitizer::class, 'sanitizeUrl']],
            'seekers'         => [
                'type'              => 'array',
                'items'             => ['type' => 'integer'],
                'validate_callback' => fn($v) => is_array($v) && count($v) <= self::SEEKERS_MAX_ITEMS,
            ],
            'description'     => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($param) {
                    return is_string($param) && trim($param) !== ''
                        && mb_strlen($param) <= OpportunitiesService::DESCRIPTION_MAX_LENGTH;
                },
            ],
            'requirements'    => [
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($param) {
                    return !is_string($param) || mb_strlen($param) <= OpportunitiesService::REQUIREMENTS_MAX_LENGTH;
                },
            ],
            'details'         => [
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($param) {
                    return !is_string($param) || mb_strlen($param) <= OpportunitiesService::DETAILS_MAX_LENGTH;
                },
            ],
            'document_id'    => [
                'required'          => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($param) {
                    return is_numeric($param) || is_null($param);
                }
            ],
            'application_form' => [
                'required'          => false,
                'sanitize_callback' => [InputSanitizer::class, 'sanitizeUrl'],
            ],
        ];

        // 1. COLLECTION: GET /opportunities (List)
        register_rest_route($this->namespace, '/opportunities', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getOpportunities'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => OpportunitiesService::ITEMS_PER_PAGE,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param)
                            && (int) $param >= 1
                            && (int) $param <= self::PER_PAGE_MAX;
                    },
                ],
                // Accept statuses as an array
                'statuses' => [
                    'type'              => 'array',
                    'items'             => ['type' => 'string'],
                    'sanitize_callback' => function ($val) {
                        return array_map('sanitize_key', (array)$val);
                    },
                    'validate_callback' => fn($v) => is_array($v) && count($v) <= self::STATUSES_MAX_ITEMS,
                ],
            ],
        ]);

        // 2. CREATE: POST /opportunities
        register_rest_route($this->namespace, '/opportunities', [
            'methods'             => 'POST',
            'callback'            => [$this, 'saveOpportunity'],
            'permission_callback' => [$this, 'checkCanPostWithNonce'],
            'args'                => $saveArgs,
        ]);

        // 3. RESOURCE: GET/PUT /opportunities/{id}
        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getSingle'],
                'permission_callback' => [$this, 'checkPostOwnerWithNonce'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveOpportunity'],
                'permission_callback' => [$this, 'checkOwnsAndCanPostWithNonce'],
                'args'                => $saveArgs,
            ],
        ]);

        // 4. ACTION: POST /opportunities/{id}/status
        register_rest_route($this->namespace, '/opportunities/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleStatusChange'],
            'permission_callback' => [$this, 'checkOwnsAndCanPostWithNonce'],
            'args'                => [
                'status' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => fn($param) => in_array($param, ['pending', 'draft'], true),
                ],
            ],
        ]);

        // Locations Search Endpoint
        register_rest_route($this->namespace, '/opportunities/locations', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchLocations'],
            'permission_callback' => [$this, 'checkLoggedInWithNonce'],
            'args'                => [
                'search' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($p) {
                        return is_string($p)
                            && strlen($p) >= 2
                            && strlen($p) <= self::SEARCH_MAX_LENGTH;
                    }
                ],
                'levels' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'default' => [],
                    'validate_callback' => fn($v) => is_array($v) && count($v) <= self::LEVELS_MAX_ITEMS,
                ]
            ]
        ]);
    }

    public function searchLocations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimited = $this->applySearchRateLimit(get_current_user_id());
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $query  = $request->get_param('search');
        $levels = $request->get_param('levels') ?: [];

        $results = $this->service->searchKatottg($query, $levels);

        return $this->success($results);
    }

    /**
     * Handles specialized status changes.
     */
    public function handleStatusChange(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rateLimited = $this->applyWriteRateLimit(get_current_user_id());
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $id     = (int) $request->get_param('id');
        $status = $request->get_param('status');

        $result = $this->service->updateStatus(get_current_user_id(), $id, $status);

        if (is_wp_error($result)) {
            return $result;
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
        $rateLimited = $this->applyWriteRateLimit(get_current_user_id());
        if ($rateLimited !== null) {
            return $rateLimited;
        }

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

        $current_user_id = get_current_user_id();

        // Defense-in-depth: route layer already verified ownership for PUT.
        if ($id) {
            $existing_post = get_post($id);
            if (
                !$existing_post
                || $existing_post->post_type !== 'opportunity'
                || (int) $existing_post->post_author !== $current_user_id
            ) {
                return new WP_Error('forbidden', 'You do not have permission to edit this item.');
            }
        }


        $params = [
            'title'           => $request->get_param('title'),
            'status'          => $request->get_param('status'),
            // applicant_name, applicant_mail, applicant_phone are auto-filled from user profile in Service
            'company'         => $request->get_param('company'),
            'date_starts'     => $request->get_param('date_starts'),
            'date_ends'       => $request->get_param('date_ends'),
            'category'        => (array) $request->get_param('category'),
            'country'         => $request->get_param('country'),
            'locations'       => (array) $request->get_param('locations'),
            'city'            => $request->get_param('city'),
            'sourcelink'      => $request->get_param('sourcelink'),
            'application_form' => $request->get_param('application_form'),
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
            return $result;
        }

        return $this->success([
            'id'      => $result,
            'message' => $id ? __('Updated successfully', 'starwishx') : __('Created successfully', 'starwishx')
        ]);
    }

    public function checkCanPost(): bool|WP_Error
    {
        if (!is_user_logged_in()) return false;

        if (!$this->profileService->isProfileComplete(get_current_user_id())) {
            return new WP_Error(
                'profile_incomplete',
                __('Please complete your profile (name and phone) before posting opportunities.', 'starwishx'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Composite: checkCanPost + wp_rest nonce.
     *
     * Applied to the create endpoint. See AbstractApiController::checkLoggedInWithNonce —
     * the nonce closes the non-cookie auth bypass that would otherwise let a
     * leaked Application Password flood this controller from a headless script.
     */
    public function checkCanPostWithNonce(WP_REST_Request $request): bool|WP_Error
    {
        $result = $this->checkCanPost();
        if ($result !== true) {
            return $result;
        }

        return $this->checkRestNonce($request);
    }

    /**
     * Composite: ownership of {id} + checkCanPost + wp_rest nonce.
     *
     * Applied to PUT and /status endpoints — both target a specific
     * opportunity. Closes the route-layer ownership gap that was previously
     * only enforced inside the service: the request now fails at the routing
     * layer for non-owners, before any service code or audit/log hooks run.
     */
    public function checkOwnsAndCanPostWithNonce(WP_REST_Request $request): bool|WP_Error
    {
        if (!$this->checkPostOwner($request)) {
            return false;
        }

        $canPost = $this->checkCanPost();
        if ($canPost !== true) {
            return $canPost;
        }

        return $this->checkRestNonce($request);
    }

    /**
     * Per-user write rate limit — shared bucket across create, update, status.
     *
     * Returns a mapped WP_Error (HTTP 429) when the limit is exceeded; null
     * otherwise. Every attempt counts — same pattern as ContactController.
     */
    private function applyWriteRateLimit(int $userId): ?WP_Error
    {
        $key = RateLimitPolicy::key('opportunity_write', (string) $userId);

        $check = RateLimitPolicy::check(
            $key,
            self::WRITE_RATE_LIMIT_MAX,
            self::WRITE_RATE_LIMIT_WINDOW
        );
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        RateLimitPolicy::hit($key, self::WRITE_RATE_LIMIT_WINDOW);

        return null;
    }

    /**
     * Per-user search rate limit — autocomplete bucket (separate from writes).
     *
     * Higher ceiling because typing-driven UI legitimately fires many requests
     * per minute, but capped to discourage scraping the KATOTTG dictionary.
     */
    private function applySearchRateLimit(int $userId): ?WP_Error
    {
        $key = RateLimitPolicy::key('opportunity_search', (string) $userId);

        $check = RateLimitPolicy::check(
            $key,
            self::SEARCH_RATE_LIMIT_MAX,
            self::SEARCH_RATE_LIMIT_WINDOW
        );
        if (is_wp_error($check)) {
            return $this->mapServiceError($check);
        }

        RateLimitPolicy::hit($key, self::SEARCH_RATE_LIMIT_WINDOW);

        return null;
    }
}

<?php
// File: inc/launchpad/Api/MediaController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\MediaService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MediaController extends AbstractLaunchpadController
{
    private MediaService $service;

    public function __construct(MediaService $service)
    {
        $this->service = $service;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleUpload'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);
    }

    public function handleUpload(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return $this->error(__('No file provided', 'starwishx'), 400);
        }

        $result = $this->service->uploadFile($files['file'], get_current_user_id());

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 400);
        }

        return $this->success($result);
    }
}

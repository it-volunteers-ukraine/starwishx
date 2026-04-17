<?php
// File: inc/launchpad/Api/MediaController.php

declare(strict_types=1);

namespace Launchpad\Api;

use Launchpad\Services\MediaService;
use Launchpad\Services\ProfileService;
use Shared\Policy\RateLimitPolicy;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MediaController extends AbstractLaunchpadController
{
    private const UPLOAD_RATE_LIMIT_MAX    = 20;
    private const UPLOAD_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

    private MediaService $service;
    private ProfileService $profileService;

    public function __construct(MediaService $service, ProfileService $profileService)
    {
        $this->service        = $service;
        $this->profileService = $profileService;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleUpload'],
            'permission_callback' => [$this, 'checkCanPostWithNonce'],
        ]);
    }

    public function handleUpload(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        $rateLimited = $this->applyUploadRateLimit($userId);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return $this->error(__('No file provided.', 'starwishx'), 400, 'no_file');
        }

        $result = $this->service->uploadFile($files['file'], $userId);

        if (is_wp_error($result)) {
            return $this->mapServiceError($result, [
                'file_too_large' => 413,
                'invalid_mime'   => 415,
                'upload_error'   => 400,
            ]);
        }

        return $this->success($result);
    }

    /**
     * Permission helper: logged-in user with a complete profile.
     *
     * Mirrors OpportunitiesController::checkCanPost — uploads are only ever
     * legitimate as part of opportunity creation, which itself requires the
     * subscriber → contributor promotion gate (name + phone). Restricting
     * here keeps the upload surface aligned with the only flow that uses it.
     */
    public function checkCanPost(): bool|WP_Error
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!$this->profileService->isProfileComplete(get_current_user_id())) {
            return new WP_Error(
                'profile_incomplete',
                __('Please complete your profile (name and phone) before uploading files.', 'starwishx'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Composite: checkCanPost + wp_rest nonce.
     *
     * The nonce closes the non-cookie auth bypass that would otherwise let a
     * leaked Application Password flood this controller from a headless
     * script — see AbstractApiController::checkLoggedInWithNonce.
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
     * Per-user upload rate limit.
     *
     * Caps disk + DB consumption: each successful upload writes a 5 MB-bound
     * file plus posts/postmeta rows that survive the 24h orphan window.
     * Without this ceiling, a leaked App Password (or a runaway client) could
     * exhaust storage faster than `cleanupOrphans` can drain it (50/run).
     */
    private function applyUploadRateLimit(int $userId): ?WP_Error
    {
        return $this->applyRateLimit(
            'media_upload',
            $userId,
            self::UPLOAD_RATE_LIMIT_MAX,
            self::UPLOAD_RATE_LIMIT_WINDOW,
            __('File upload', 'starwishx')
        );
    }

    /**
     * Generic per-user rate-limit guard with an action-named, friendly message.
     *
     * Mirrors OpportunitiesController / ProfileController — the user-facing
     * message names the action and shows a single-unit, localized wait
     * duration via human_time_diff() (e.g., "1 hour", "30 mins").
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
            /* translators: 1: action name (e.g., "File upload"), 2: human-readable wait duration */
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

<?php

/**
 * Base for the comments tests: users, posts and reviews, and comments/v1
 * calls as the store makes them (JSON body, wp_rest nonce).
 *
 * The post types come from SCF's acf-json on a real site, which the test
 * run doesn't load: they're registered here, public, when missing.
 *
 * WordPress's 15-second comment flood check is off (tests post several
 * comments in a row); the flood test turns it back on. Every test runs in
 * a transaction the WP test suite rolls back.
 *
 * File: tests/php/Comments/CommentsTestCase.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Comments;

use Comments\Services\CommentsService;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

abstract class CommentsTestCase extends WP_UnitTestCase
{
    protected CommentsService $service;

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();

        foreach (CommentsService::ALLOWED_POST_TYPES as $type) {
            if (!post_type_exists($type)) {
                register_post_type($type, ['public' => true]);
            }
        }

        wp_set_current_user(0);
        // Loopback: approving a comment mails the post's author, and that
        // mail reverse-resolves the comment's IP (seconds for a TEST-NET one).
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        add_filter('comment_flood_filter', '__return_false');
        $this->service = \comments()->service();

        // Routes register on rest_api_init: a fresh server per test.
        global $wp_rest_server;
        $wp_rest_server = null;
        rest_get_server();
    }

    #[\Override]
    public function tear_down(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tear_down();
    }

    protected function user(string $role = 'subscriber'): int
    {
        return self::factory()->user->create(['role' => $role]);
    }

    protected function post(int $author, string $status = 'publish', string $type = 'opportunity'): int
    {
        return self::factory()->post->create([
            'post_type'   => $type,
            'post_status' => $status,
            'post_author' => $author,
        ]);
    }

    /**
     * A comment posted through the service, as `$userId` (the current user,
     * as in a request). Fails the test if it's refused.
     *
     * @return array<string, mixed> The comment node.
     */
    protected function comment(int $userId, int $postId, string $content, int $rating = 0, int $parentId = 0): array
    {
        wp_set_current_user($userId);
        $result = $this->service->addComment($userId, $postId, $content, $rating, $parentId);
        $this->assertIsArray($result, is_wp_error($result) ? $result->get_error_code() : 'refused');
        return $result['comment'];
    }

    /** Move a comment's dates `$seconds` into the past. */
    protected function backdate(int $commentId, int $seconds): void
    {
        global $wpdb;
        $wpdb->update($wpdb->comments, [
            'comment_date'     => gmdate('Y-m-d H:i:s', time() - $seconds + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS)),
            'comment_date_gmt' => gmdate('Y-m-d H:i:s', time() - $seconds),
        ], ['comment_ID' => $commentId]);
        clean_comment_cache($commentId);
    }

    /**
     * A comments/v1 request as the store sends it: a JSON body (query for
     * GET) and the current user's nonce.
     *
     * @param array<string, mixed> $body
     */
    protected function rest(string $method, string $route, array $body = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, '/comments/v1/' . ltrim($route, '/'));
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        if ($method === 'GET') {
            $request->set_query_params($body);
        } else {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($body));
        }
        return rest_do_request($request);
    }

    /** The WP error code of a response ('' for a success). */
    protected static function code(WP_REST_Response $response): string
    {
        $data = $response->get_data();
        return is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
    }
}

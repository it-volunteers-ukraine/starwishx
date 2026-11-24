<?php

/**
 * comments/v1: who may read a post's comments, the argument checks, and the
 * status each refusal reaches the browser with (the store shows the
 * message; the status is for everything else).
 *
 * File: tests/php/Comments/CommentsRestTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Comments;

use WP_REST_Response;

final class CommentsRestTest extends CommentsTestCase
{
    private int $author;
    private int $reviewer;
    private int $postId;
    /** @var array<string, mixed> The reviewer's review on $postId. */
    private array $review;

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();
        $this->author   = $this->user();
        $this->reviewer = $this->user();
        $this->postId   = $this->post($this->author);
        $this->review   = $this->comment($this->reviewer, $this->postId, 'A fair review', 4);
        wp_set_current_user(0);
    }

    // --- Reading ---------------------------------------------------------------

    public function test_anyone_reads_a_published_posts_comments(): void
    {
        $response = $this->rest('GET', 'comments', ['post_id' => $this->postId]);

        $this->assertSame(200, $response->get_status());
        $this->assertSame([$this->review['id']], array_column($response->get_data()['items'], 'id'));
    }

    public function test_a_drafts_comments_are_its_authors_only(): void
    {
        $draft = $this->post($this->author, 'draft');

        $response = $this->rest('GET', 'comments', ['post_id' => $draft]);
        $this->assertSame(404, $response->get_status(), 'a guest');
        $this->assertSame('not_found', self::code($response));

        wp_set_current_user($this->reviewer);
        $this->assertSame(404, $this->rest('GET', 'comments', ['post_id' => $draft])->get_status(), 'another user');

        wp_set_current_user($this->author);
        $this->assertSame(200, $this->rest('GET', 'comments', ['post_id' => $draft])->get_status(), 'its author');
    }

    public function test_other_content_has_no_comments_here(): void
    {
        $page = $this->post($this->author, 'publish', 'page');

        $this->assertSame(404, $this->rest('GET', 'comments', ['post_id' => $page])->get_status());
    }

    // --- Arguments -------------------------------------------------------------

    /** @return array<string, array{string, string, array<string, mixed>, string}> */
    public static function badArguments(): array
    {
        return [
            'page 0'       => ['GET', 'comments', ['page' => 0], 'page'],
            'post_id 0'    => ['GET', 'comments', ['post_id' => 0], 'post_id'],
            'parent_id -1' => ['POST', 'comments', ['content' => 'Orphan', 'parent_id' => -1], 'parent_id'],
            'rating 6'     => ['POST', 'comments', ['content' => 'Too good', 'rating' => 6], 'rating'],
        ];
    }

    /**
     * @dataProvider badArguments
     * @param array<string, mixed> $body
     */
    public function test_a_bad_argument_is_a_field_error(string $method, string $route, array $body, string $field): void
    {
        wp_set_current_user($this->reviewer);

        $response = $this->rest($method, $route, $body + ['post_id' => $this->postId]);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('invalid_data', self::code($response));
        $this->assertIsString($response->get_data()['data']['field_errors'][$field] ?? null, "no field error for {$field}");
    }

    // --- Refusals --------------------------------------------------------------

    /** @return array<string, array{string, int}> */
    public static function refusals(): array
    {
        return [
            'reviewing one\'s own post' => ['self_review', 403],
            'replying in another\'s thread' => ['forbidden', 403],
            'replying across posts'     => ['parent_post_mismatch', 422],
            'commenting on a draft'     => ['not_published', 403],
            'posting the same twice'    => ['comment_duplicate', 409],
            'posting too fast'          => ['comment_flood', 429],
            'a disallowed word'         => ['comment_rejected', 422],
            'editing too late'          => ['timeout', 403],
            'editing a held comment'    => ['not_found', 404],
        ];
    }

    /** @dataProvider refusals */
    public function test_a_refusal_keeps_its_code_and_status(string $code, int $status): void
    {
        $response = $this->attempt($code);

        $this->assertSame($code, self::code($response));
        $this->assertSame($status, $response->get_status());
        $this->assertIsString($response->get_data()['message'] ?? null);
    }

    /** The request that `$code` refuses. */
    private function attempt(string $code): WP_REST_Response
    {
        $reviewId = $this->review['id'];
        $post     = fn(int $as, array $body): WP_REST_Response
            => $this->actingAs($as, fn(): WP_REST_Response => $this->rest('POST', 'comments', $body + ['post_id' => $this->postId]));

        return match ($code) {
            'self_review' => $post($this->author, ['content' => 'My own post is great', 'rating' => 5]),
            'forbidden'   => $post($this->user(), ['content' => 'Butting in', 'parent_id' => $reviewId]),
            'parent_post_mismatch' => $post($this->reviewer, [
                'content'   => 'Wrong thread',
                'parent_id' => $this->comment($this->reviewer, $this->post($this->author), 'Elsewhere', 3)['id'],
            ]),
            'not_published' => $post($this->reviewer, ['content' => 'Early', 'post_id' => $this->post($this->author, 'draft')]),
            'comment_duplicate' => $post($this->reviewer, ['content' => 'A fair review', 'rating' => 4]),
            'comment_flood' => (function () use ($post): WP_REST_Response {
                remove_filter('comment_flood_filter', '__return_false');
                return $post($this->reviewer, ['content' => 'And another thing', 'rating' => 4]);
            })(),
            'comment_rejected' => (function () use ($post): WP_REST_Response {
                update_option('disallowed_keys', 'casino');
                return $post($this->user(), ['content' => 'Visit my casino', 'rating' => 5]);
            })(),
            'timeout' => (function () use ($reviewId): WP_REST_Response {
                $this->backdate($reviewId, 16 * MINUTE_IN_SECONDS);
                return $this->actingAs($this->reviewer, fn(): WP_REST_Response => $this->rest('PUT', "comments/{$reviewId}", ['content' => 'Late']));
            })(),
            'not_found' => (function () use ($reviewId): WP_REST_Response {
                wp_set_comment_status($reviewId, 'hold');
                return $this->actingAs($this->reviewer, fn(): WP_REST_Response => $this->rest('PUT', "comments/{$reviewId}", ['content' => 'Held']));
            })(),
        };
    }

    /**
     * `$request` as user `$userId`.
     *
     * @param \Closure(): WP_REST_Response $request
     */
    private function actingAs(int $userId, \Closure $request): WP_REST_Response
    {
        wp_set_current_user($userId);
        return $request();
    }
}

<?php

/**
 * What PHP sends the comments store, as a shape (keys and value types),
 * pinned in tests/fixtures/comments-state.json:
 *
 * - `state`: the hydrated state, CommentsService::getInitialState() plus
 *   CommentsCore::getConfig(), for a post with a rated review and a reply;
 * - `write`: what POST/PUT comments answers ({comment, aggregates}).
 *
 * The TS side checks its own types against the same file
 * (tests/ts/comments/contract.test.ts), so a field added, renamed or
 * retyped on one side fails a test until the other side follows.
 *
 *   UPDATE_FIXTURES=1 pnpm test:php   rewrites the fixture after a deliberate change
 *
 * File: tests/php/Comments/StateContractTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Comments;

use WP_UnitTestCase;

final class StateContractTest extends WP_UnitTestCase
{
    private const string FIXTURE = STARWISHX_TESTS_THEME_DIR . '/tests/fixtures/comments-state.json';

    /** Keys and JSON value types: 'string', 'number', 'boolean', 'null', or a nested shape. */
    private static function shape(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(self::shape(...), $value),
            is_string($value) => 'string',
            is_int($value), is_float($value) => 'number',
            is_bool($value) => 'boolean',
            $value === null => 'null',
            default => get_debug_type($value),
        };
    }

    public function test_what_php_sends_matches_the_contract(): void
    {
        $author   = self::factory()->user->create();
        $reviewer = self::factory()->user->create();
        $postId   = self::factory()->post->create([
            'post_type'   => 'opportunity',
            'post_status' => 'publish',
            'post_author' => $author,
        ]);
        $service = \comments()->service();

        wp_set_current_user($reviewer);
        $write = $service->addComment($reviewer, $postId, 'A rated review', 4);
        $this->assertIsArray($write, 'the review was refused');

        // The post's author replies: replies carry no rating (null).
        wp_set_current_user($author);
        $reply = $service->addComment($author, $postId, 'A reply', 0, $write['comment']['id']);
        $this->assertIsArray($reply, 'the reply was refused');

        wp_set_current_user($reviewer);
        $state = $service->getInitialState($postId) + ['config' => \comments()->getConfig()];
        $this->assertCount(1, $state['list']);
        $this->assertCount(1, $state['list'][0]['replies']);

        $shape = self::shape(['state' => $state, 'write' => $write]);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents(self::FIXTURE, wp_json_encode($shape, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        $this->assertFileExists(self::FIXTURE, 'run once with UPDATE_FIXTURES=1');
        $this->assertSame(
            json_decode((string) file_get_contents(self::FIXTURE), true),
            $shape,
            'What PHP sends the comments store changed shape: update the TS types (inc/comments/Assets/types.ts), then UPDATE_FIXTURES=1.'
        );
    }
}

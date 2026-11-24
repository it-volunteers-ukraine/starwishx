<?php

/**
 * CommentsService: what counts toward a post's rating, text that comes back
 * as typed, who may edit, and the guard rails on its write methods.
 *
 * File: tests/php/Comments/CommentsServiceTest.php
 */

declare(strict_types=1);

namespace Starwishx\Tests\Comments;

use Comments\Services\CommentsService;
use ReflectionMethod;

final class CommentsServiceTest extends CommentsTestCase
{
    private int $author;
    private int $postId;

    #[\Override]
    public function set_up(): void
    {
        parent::set_up();
        $this->author = $this->user();
        $this->postId = $this->post($this->author);
    }

    /** @return array{count: int, avg: float} */
    private function aggregates(): array
    {
        return $this->service->getAggregates($this->postId);
    }

    // --- Ratings -------------------------------------------------------------

    public function test_a_post_author_cannot_rate_through_a_reply(): void
    {
        $review = $this->comment($this->user(), $this->postId, 'Solid', 4);
        $reply  = $this->comment($this->author, $this->postId, 'Thanks', 5, $review['id']);

        $edited = $this->service->updateComment($this->author, $reply['id'], 'Thanks!', 1);

        $this->assertIsArray($edited);
        $this->assertNull($edited['comment']['rating']);
        $this->assertSame('', get_comment_meta($reply['id'], '_star_rating', true));
        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates());
    }

    public function test_a_rating_already_stored_on_a_reply_does_not_count(): void
    {
        $review = $this->comment($this->user(), $this->postId, 'Solid', 4);
        $reply  = $this->comment($this->author, $this->postId, 'Thanks', 0, $review['id']);
        update_comment_meta($reply['id'], '_star_rating', 1); // as the old updateComment() could

        $this->service->refreshAggregates($this->postId);

        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates());
    }

    public function test_each_author_counts_once_with_their_latest_rating(): void
    {
        $anna = $this->user();
        $this->comment($anna, $this->postId, 'Meh', 2);
        $this->comment($anna, $this->postId, 'Changed my mind', 5);
        $this->comment($this->user(), $this->postId, 'Good', 4);

        $this->assertSame(['count' => 2, 'avg' => 4.5], $this->aggregates());
    }

    public function test_moderation_recounts_the_rating(): void
    {
        $this->comment($this->user(), $this->postId, 'Great', 4);
        $low = $this->comment($this->user(), $this->postId, 'Poor', 2)['id'];
        $this->assertSame(['count' => 2, 'avg' => 3.0], $this->aggregates());

        wp_set_comment_status($low, 'hold');
        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates(), 'unapproved');

        wp_set_comment_status($low, 'approve');
        $this->assertSame(['count' => 2, 'avg' => 3.0], $this->aggregates(), 'approved again');

        wp_spam_comment($low);
        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates(), 'spam');

        wp_unspam_comment($low);
        $this->assertSame(['count' => 2, 'avg' => 3.0], $this->aggregates(), 'not spam');

        wp_trash_comment($low);
        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates(), 'trashed');

        wp_untrash_comment($low);
        wp_delete_comment($low, true);
        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates(), 'deleted');
    }

    public function test_the_catch_up_recount_runs_once_per_version(): void
    {
        $this->comment($this->user(), $this->postId, 'Great', 4);
        $option = 'starwishx_comments_aggregates_version';

        update_post_meta($this->postId, '_opportunity_rating_count', 9);
        delete_option($option);
        \comments()->maybeRefreshAllAggregates();

        $this->assertSame(['count' => 1, 'avg' => 4.0], $this->aggregates());
        $this->assertSame(CommentsService::AGGREGATES_VERSION, (int) get_option($option));

        update_post_meta($this->postId, '_opportunity_rating_count', 9);
        \comments()->maybeRefreshAllAggregates();
        $this->assertSame(9, $this->aggregates()['count'], 'ran again');
    }

    // --- Text ------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function texts(): array
    {
        return [
            'less-than and ampersand' => ['2 < 3 & co'],
            'backslashes'             => ['C:\Users\anna\notes.txt'],
            'quotes'                  => ['She said "hi", it\'s fine'],
            'lines'                   => ["First line\nSecond line"],
        ];
    }

    /**
     * WordPress stores comment text entity-encoded and unslashes what it's
     * given: the store must get back what was typed, after an edit too.
     *
     * @dataProvider texts
     */
    public function test_text_comes_back_as_typed(string $text): void
    {
        $reviewer = $this->user();

        $posted = $this->comment($reviewer, $this->postId, $text, 4);
        $this->assertSame($text, $posted['content'], 'posted');
        $this->assertSame($text, $this->service->getPostComments($this->postId)[0]['content'], 'listed');

        $edited = $this->service->updateComment($reviewer, $posted['id'], "{$text} (edited)", 4);
        $this->assertIsArray($edited);
        $this->assertSame("{$text} (edited)", $edited['comment']['content'], 'edited');
        $this->assertSame("{$text} (edited)", $this->service->getPostComments($this->postId)[0]['content'], 'listed after the edit');
    }

    // --- Who may edit ----------------------------------------------------------

    public function test_a_guest_owns_no_comment(): void
    {
        self::factory()->comment->create([
            'comment_post_ID'  => $this->postId,
            'comment_approved' => '1',
            'user_id'          => 0,
        ]);
        wp_set_current_user(0);

        $node = $this->service->getPostComments($this->postId)[0];

        $this->assertFalse($node['isMine']);
        $this->assertFalse($node['isEditable']);
    }

    public function test_edits_close_after_the_window_except_for_moderators(): void
    {
        $reviewer = $this->user();
        $id       = $this->comment($reviewer, $this->postId, 'First take', 4)['id'];
        $window   = CommentsService::EDIT_TIMEOUT_SECONDS;

        $this->backdate($id, $window - 60);
        $this->assertIsArray($this->service->updateComment($reviewer, $id, 'Second take'), 'inside the window');

        $this->backdate($id, $window + 60);
        $late = $this->service->updateComment($reviewer, $id, 'Third take');
        $this->assertWPError($late);
        $this->assertSame('timeout', $late->get_error_code());
        wp_set_current_user($reviewer);
        $this->assertFalse($this->service->getPostComments($this->postId)[0]['isEditable'], 'the button hides too');

        $editor = $this->user('editor');
        $own    = $this->comment($editor, $this->postId, 'Editor view', 3)['id'];
        $this->backdate($own, $window + 60);
        $this->assertIsArray($this->service->updateComment($editor, $own, 'Editor view, revised'));
    }

    public function test_only_approved_comments_can_be_edited(): void
    {
        $reviewer = $this->user();
        $id       = $this->comment($reviewer, $this->postId, 'Pending', 4)['id'];
        wp_set_comment_status($id, 'hold');

        $result = $this->service->updateComment($reviewer, $id, 'Edited while held');

        $this->assertWPError($result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    // --- Replies ---------------------------------------------------------------

    public function test_replies_go_under_an_approved_review_only(): void
    {
        $reviewer = $this->user();
        $review   = $this->comment($reviewer, $this->postId, 'Review', 4);
        $reply    = $this->comment($this->author, $this->postId, 'Reply', 0, $review['id']);

        foreach (['a reply' => $reply['id'], 'no comment' => -1] as $label => $parentId) {
            $result = $this->service->addComment($this->author, $this->postId, "Under {$label}", 0, $parentId);
            $this->assertWPError($result, $label);
            $this->assertSame('invalid_parent', $result->get_error_code(), $label);
        }
    }

    // --- Guard rails -----------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function writeMethods(): array
    {
        return ['add' => ['addComment'], 'update' => ['updateComment']];
    }

    /**
     * An ignored WP_Error loses the refusal: PHP warns when the result of a
     * write is dropped.
     *
     * @dataProvider writeMethods
     */
    public function test_a_writes_result_must_be_used(string $method): void
    {
        $attributes = new ReflectionMethod(CommentsService::class, $method)->getAttributes(\NoDiscard::class);

        $this->assertCount(1, $attributes);
    }
}

<?php

/**
 * Blocks module — parent/child render context
 *
 * Gives inner blocks two facts they cannot know on their own: which parent
 * *instance* they belong to and their position in it. Core's
 * `render_block_context` filter runs for every inner block with the parent
 * WP_Block in hand; this injects
 *
 *   starwishx/parentId   — "sw1", "sw2", … unique per parent instance for the
 *                          request (WeakMap keyed by the WP_Block object),
 *   starwishx/childIndex — 0-based render order within that parent,
 *
 * into the context of any child whose block.json lists them in `usesContext`.
 * Nothing is persisted in post content, so duplicated or copied blocks never
 * share an id. The FAQ item uses the id for its exclusive <details name>
 * group and the index for "open the first question".
 *
 * A parent may be rendered more than once in a request (excerpt, schema, the
 * content itself); the counter restarts once it has handed out an index to
 * every inner block, so each render sees the same sequence.
 *
 * File: inc/blocks/Support/InnerBlockContext.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class InnerBlockContext
{
    public const PARENT_ID   = 'starwishx/parentId';
    public const CHILD_INDEX = 'starwishx/childIndex';

    /** @var \WeakMap<\WP_Block, array{id: int, count: int}> */
    private \WeakMap $parents;

    private int $next_id = 0;

    public function __construct()
    {
        $this->parents = new \WeakMap();
    }

    public function register(): void
    {
        add_filter('render_block_context', [$this, 'inject'], 10, 3);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $parsed_block
     * @param \WP_Block|null       $parent_block Null for top-level blocks.
     * @return array<string, mixed>
     */
    public function inject(array $context, array $parsed_block, $parent_block): array
    {
        if (! $parent_block instanceof \WP_Block) {
            return $context;
        }

        $type = \WP_Block_Type_Registry::get_instance()->get_registered((string) ($parsed_block['blockName'] ?? ''));
        if (! $type || ! array_intersect([self::PARENT_ID, self::CHILD_INDEX], (array) $type->uses_context)) {
            return $context;
        }

        if (! isset($this->parents[$parent_block])) {
            $this->parents[$parent_block] = ['id' => ++$this->next_id, 'count' => 0];
        }

        $entry = $this->parents[$parent_block];
        $total = count($parent_block->parsed_block['innerBlocks'] ?? []);

        if ($total > 0 && $entry['count'] >= $total) {
            $entry['count'] = 0; // the parent is being rendered again
        }

        $index = $entry['count'];
        $entry['count']++;
        $this->parents[$parent_block] = $entry;

        $context[self::PARENT_ID]   = 'sw' . $entry['id'];
        $context[self::CHILD_INDEX] = $index;

        return $context;
    }
}

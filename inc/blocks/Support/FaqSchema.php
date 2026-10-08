<?php

/**
 * Blocks module — FAQPage structured data for starwishx/faq
 *
 * One FAQPage entity per singular page (Google wants a single one), built from
 * the post's blocks rather than from the render: Rank Math assembles its graph
 * in wp_head, before the content is rendered. Every starwishx/faq block on the
 * page contributes its starwishx/faq-item children, wherever they are nested
 * (groups, columns…): question = the item's attribute, answer = its inner
 * blocks rendered and reduced to the tags Google accepts in an Answer.
 *
 * With Rank Math active the entity joins its @graph through `rank_math/json_ld`;
 * without it the theme prints a standalone JSON-LD script in <head>.
 * has_block() is a plain string search, so pages without the block pay
 * nothing beyond that.
 *
 * File: inc/blocks/Support/FaqSchema.php
 */

declare(strict_types=1);

namespace Blocks\Support;

final class FaqSchema
{
    private const BLOCK = 'starwishx/faq';
    private const ITEM  = 'starwishx/faq-item';

    /** Tags Google lists as accepted in an Answer's text. */
    private const ANSWER_TAGS = [
        'p'      => [],
        'br'     => [],
        'ol'     => [],
        'ul'     => [],
        'li'     => [],
        'a'      => ['href' => []],
        'strong' => [],
        'b'      => [],
        'em'     => [],
        'i'      => [],
        'h2'     => [],
        'h3'     => [],
        'h4'     => [],
        'h5'     => [],
        'h6'     => [],
    ];

    private bool $resolved = false;

    /** @var array<string, mixed>|null */
    private ?array $entity = null;

    public function register(): void
    {
        if (defined('RANK_MATH_VERSION')) {
            add_filter('rank_math/json_ld', [$this, 'addToGraph'], 99);
            return;
        }

        add_action('wp_head', [$this, 'printJsonLd'], 5);
    }

    /**
     * @param array<string, mixed> $data Rank Math's schema entities.
     * @return array<string, mixed>
     */
    public function addToGraph($data): array
    {
        $data   = is_array($data) ? $data : [];
        $entity = $this->entity();

        if ($entity) {
            $data['starwishxFaqPage'] = $entity;
        }

        return $data;
    }

    public function printJsonLd(): void
    {
        $entity = $this->entity();
        if (! $entity) {
            return;
        }

        echo '<script type="application/ld+json">'
            . wp_json_encode(['@context' => 'https://schema.org'] + $entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
            . '</script>' . "\n";
    }

    /**
     * The FAQPage entity for the current singular post, or null.
     *
     * @return array<string, mixed>|null
     */
    public function entity(): ?array
    {
        if ($this->resolved) {
            return $this->entity;
        }
        $this->resolved = true;

        if (! is_singular()) {
            return null;
        }

        $post = get_post();
        if (! $post instanceof \WP_Post || ! has_block(self::BLOCK, $post)) {
            return null;
        }

        $questions = [];
        $this->collect(PostBlocks::parsed($post), $questions);

        if (! $questions) {
            return null;
        }

        $this->entity = [
            '@type'      => 'FAQPage',
            'mainEntity' => $questions,
        ];

        return $this->entity;
    }

    /**
     * Walks parsed blocks depth-first, appending Question entities.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, array<string, mixed>> $questions
     */
    private function collect(array $blocks, array &$questions): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['blockName'] ?? '') === self::ITEM) {
                $question = trim(wp_strip_all_tags((string) ($block['attrs']['question'] ?? '')));

                $answer = '';
                foreach ((array) ($block['innerBlocks'] ?? []) as $inner) {
                    $answer .= render_block($inner);
                }
                $answer = trim(wp_kses($answer, self::ANSWER_TAGS));

                if ($question !== '' && $answer !== '') {
                    $questions[] = [
                        '@type'          => 'Question',
                        'name'           => $question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => $answer,
                        ],
                    ];
                }

                continue;
            }

            if (! empty($block['innerBlocks'])) {
                $this->collect($block['innerBlocks'], $questions);
            }
        }
    }
}

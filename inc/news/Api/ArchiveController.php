<?php

/**
 * REST route behind the "Load more" button.
 *
 * Replaces the admin-ajax `load_news` action, which took `post_type` from the
 * query string and handed it straight to WP_Query. Here the caller names a
 * *source* from a fixed list and the post types are derived server-side.
 *
 * The param is `source`, not `context`: `context` is reserved by the REST API
 * (view/edit/embed), and a route-level enum on that name is not enforced —
 * an unknown value slipped through to an unconstrained query.
 *
 * Search shares this route: its results are the same cards under the same
 * pagination contract.
 *
 * File: inc/news/Api/ArchiveController.php
 */

declare(strict_types=1);

namespace News\Api;

use News\Core\NewsCore;
use Shared\Core\AbstractApiController;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

final class ArchiveController extends AbstractApiController
{
    protected $namespace = 'news/v1';

    public const SOURCES = ['news_category', 'search'];

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/posts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getPosts'],
                'permission_callback' => '__return_true', // public archive data
                'args'                => $this->getCollectionArgs(),
            ],
        ]);
    }

    private function getCollectionArgs(): array
    {
        return [
            'source' => [
                'required'          => true,
                'type'              => 'string',
                'enum'              => self::SOURCES,
                'sanitize_callback' => 'sanitize_key',
            ],
            'slug' => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_title',
            ],
            's' => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'page' => [
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => 0, // 0 = site default
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'type'              => 'string',
                'default'           => '',
                'enum'              => ['', 'date', 'title', 'modified', 'relevance'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'order' => [
                'type'              => 'string',
                'default'           => '',
                'enum'              => ['', 'ASC', 'DESC', 'asc', 'desc'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function getPosts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $source = (string) $request->get_param('source');
        $args   = $this->buildQueryArgs($request, $source);

        if (is_wp_error($args)) {
            return $args;
        }

        $query = new WP_Query($args);

        update_post_thumbnail_cache($query);

        return $this->success([
            'html'      => $this->renderCards($query, $source),
            'page'      => (int) $args['paged'],
            'max_pages' => (int) $query->max_num_pages,
            'found'     => (int) $query->found_posts,
        ]);
    }

    /**
     * @return array|WP_Error
     */
    private function buildQueryArgs(WP_REST_Request $request, string $source)
    {
        $per_page = (int) $request->get_param('per_page');
        $allowed  = sw_get_allowed_per_page();

        if ($per_page === 0 || !in_array($per_page, $allowed, true)) {
            $per_page = in_array(12, $allowed, true) ? 12 : (int) end($allowed);
        }

        $args = [
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => max(1, (int) $request->get_param('page')),
            'no_found_rows'  => false,
        ];

        $orderby = (string) $request->get_param('orderby');
        $order   = strtoupper((string) $request->get_param('order'));

        if ($orderby !== '') {
            $args['orderby'] = $orderby;
        }

        if (in_array($order, ['ASC', 'DESC'], true)) {
            $args['order'] = $order;
        }

        switch ($source) {
            case 'news_category':
                $slug = (string) $request->get_param('slug');
                $term = $slug !== '' ? get_term_by('slug', $slug, NewsCore::TAXONOMY) : null;

                if (!$term || is_wp_error($term)) {
                    return $this->error(
                        __('Unknown category.', 'starwishx'),
                        404,
                        'invalid_category'
                    );
                }

                $args['post_type'] = NewsCore::POST_TYPE;
                $args['tax_query'] = [[
                    'taxonomy' => NewsCore::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $term->slug,
                ]];
                break;

            case 'search':
                $search = (string) $request->get_param('s');

                if ($search === '') {
                    return $this->error(
                        __('A search term is required.', 'starwishx'),
                        400,
                        'missing_search_term'
                    );
                }

                $args['post_type'] = sw_searchable_post_types();
                $args['s']         = $search;
                break;

            default:
                // Unreachable through the enum, but the query must never run
                // without a post_type restriction.
                return $this->error(
                    __('Unknown source.', 'starwishx'),
                    400,
                    'invalid_source'
                );
        }

        return $args;
    }

    /**
     * Render the same template part the server-side pages use, so appended
     * cards are byte-identical to the ones already on screen.
     */
    private function renderCards(WP_Query $query, string $source): string
    {
        $show_type = $source === 'search';

        ob_start();

        foreach ($query->posts as $post_item) {
            $terms = get_the_terms($post_item->ID, NewsCore::TAXONOMY);

            if (!empty($terms) && !is_wp_error($terms)) {
                $post_item->term_name = $terms[0]->name;
                $post_item->term_slug = $terms[0]->slug;
            }

            $label = '';

            if ($show_type) {
                $type_object = get_post_type_object($post_item->post_type);
                $label       = $type_object->labels->singular_name ?? '';
            }

            get_template_part('template-parts/news-card', null, [
                'post'            => $post_item,
                'show_excerpt'    => $source === 'news_category',
                'post_type_label' => $label,
                'card_version'    => $show_type ? 2 : 1,
            ]);
        }

        return (string) ob_get_clean();
    }
}

<?php

/**
 * News module — global helpers.
 *
 * These are the survivors of inc/helpers.php, which was deleted with the
 * legacy blocks: every my_* function in it (my_query_args_prepare,
 * my_query_search, my_post_type, my_category_colors, pagination_url,
 * seach_params_from_blocks, …) existed only to serve the ACF pagination,
 * search-page and news-page-category blocks.
 *
 * What is left is the display contract shared by the news archives and
 * search: how many items a page shows and how they are sorted. Both the
 * templates and the REST route read it from here, so a page of results and
 * its pagination can never disagree again.
 *
 * File: inc/news/helpers.php
 */

if (! function_exists('sw_news')) {
    /**
     * News module singleton.
     *
     * Prefixed `sw_news()` rather than the bare `news()` used by the other
     * modules: `news` is a name a plugin is quite likely to claim.
     */
    function sw_news(): \News\Core\NewsCore
    {
        return \News\Core\NewsCore::instance();
    }
}

if (! function_exists('sw_searchable_post_types')) {
    /**
     * Post types users can find through site search.
     */
    function sw_searchable_post_types(): array
    {
        return apply_filters('sw_searchable_post_types', ['news', 'opportunity', 'project']);
    }
}

if (! function_exists('sw_get_allowed_per_page')) {
    /**
     * Allowed posts_per_page values — the single source of truth.
     */
    function sw_get_allowed_per_page(): array
    {
        $allowed = apply_filters('sw_allowed_per_page', [4, 8, 12]);
        $allowed = array_values(array_unique(array_filter(array_map('intval', (array) $allowed))));

        return $allowed ?: [12];
    }
}

if (! function_exists('sw_get_per_page')) {
    /**
     * per_page from the request, clamped to the allowed list.
     */
    function sw_get_per_page(): int
    {
        $allowed = sw_get_allowed_per_page();
        $default = in_array(12, $allowed, true) ? 12 : (int) end($allowed);

        if (! isset($_GET['per_page'])) {
            return $default;
        }

        $per_page = (int) sanitize_text_field(wp_unslash($_GET['per_page']));

        return in_array($per_page, $allowed, true) ? $per_page : $default;
    }
}

if (! function_exists('sw_attach_card_terms')) {
    /**
     * Attach the *root* category term to each post, for news-card.php.
     *
     * Root, not the post's own term: sw_get_taxonomy_top_level_colors_styles()
     * only emits colour rules for top-level terms, so a card labelled with a
     * child term ("Коучинг") renders with no colour at all.
     *
     * Two queries for the whole page — the taxonomy's id=>parent map and one
     * bulk hydration of the roots actually used. sw_get_root_terms() in
     * inc/theme-helpers.php does the same walk but costs those two queries
     * *per post*, which is too much for a results grid.
     *
     * @param \WP_Post[] $posts Modified in place.
     */
    function sw_attach_card_terms(array $posts, string $taxonomy = 'category-oportunities'): void
    {
        if (! $posts) {
            return;
        }

        $parents = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'id=>parent',
        ]);

        if (is_wp_error($parents)) {
            return;
        }

        $root_id_by_post = [];
        $root_ids        = [];

        foreach ($posts as $post_item) {
            // Term cache is primed by WP_Query — no query per post here
            $terms = get_the_terms($post_item->ID, $taxonomy);

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            $term_id = (int) $terms[0]->term_id;

            while (! empty($parents[$term_id])) {
                $term_id = (int) $parents[$term_id];
            }

            $root_id_by_post[$post_item->ID] = $term_id;
            $root_ids[$term_id]              = true;
        }

        if (! $root_ids) {
            return;
        }

        $roots = get_terms([
            'taxonomy'   => $taxonomy,
            'include'    => array_keys($root_ids),
            'hide_empty' => false,
        ]);

        if (is_wp_error($roots)) {
            return;
        }

        $by_id = [];
        foreach ($roots as $term) {
            $by_id[(int) $term->term_id] = $term;
        }

        foreach ($posts as $post_item) {
            $root_id = $root_id_by_post[$post_item->ID] ?? null;

            if ($root_id !== null && isset($by_id[$root_id])) {
                $post_item->term_name = $by_id[$root_id]->name;
                $post_item->term_slug = $by_id[$root_id]->slug;
            }
        }
    }
}

if (! function_exists('sw_archive_url')) {
    /**
     * The current archive URL at page 1, with $args merged into the query.
     *
     * Always page 1 on purpose: a reader on page 3 who switches to 4 items per
     * page or flips the sort order does not want page 3 of a different list.
     * The old markup got this from a hidden `page_num=1` form field.
     *
     * Pass null as a value to drop that argument from the URL.
     */
    function sw_archive_url(array $args = []): string
    {
        $url = get_pagenum_link(1, false);

        $drop = array_keys(array_filter($args, static fn($value): bool => $value === null));
        $keep = array_filter($args, static fn($value): bool => $value !== null);

        if ($drop) {
            $url = remove_query_arg($drop, $url);
        }

        return $keep ? add_query_arg($keep, $url) : $url;
    }
}

if (! function_exists('sw_get_sort_params')) {
    /**
     * sortby/order from the request, clamped to a whitelist.
     *
     * Returns WP_Query's key names. The code this replaced set an `sortby`
     * argument, which WP_Query ignores — sorting had never worked.
     *
     * @return array{orderby: ?string, order: ?string}
     */
    function sw_get_sort_params(): array
    {
        $allowed_orderby = ['date', 'title', 'modified', 'relevance'];

        $orderby = null;
        if (isset($_GET['sortby'])) {
            $candidate = strtolower(sanitize_text_field(wp_unslash($_GET['sortby'])));
            if (in_array($candidate, $allowed_orderby, true)) {
                $orderby = $candidate;
            }
        }

        $order = null;
        if (isset($_GET['order'])) {
            $candidate = strtoupper(sanitize_text_field(wp_unslash($_GET['order'])));
            if (in_array($candidate, ['ASC', 'DESC'], true)) {
                $order = $candidate;
            }
        }

        return ['orderby' => $orderby, 'order' => $order];
    }
}

if (! function_exists('sw_news_latest_by_root_term')) {
    /**
     * The newest news post of every top-level term, in term (name) order.
     *
     * Backs the starwishx/news block on the home page. The ACF block it
     * replaced ran a full WP_Query per term (include_children resolving the
     * hierarchy again each time) and then paid two more queries per card for
     * the thumbnail — 52 queries for six categories on a cold cache. Here the
     * taxonomy is read once (hydrated, so the hierarchy and the
     * term_taxonomy_ids come for free), the per-term lookups are id-only with
     * every cache off, one WP_Query hydrates the winners and primes their meta,
     * and update_post_thumbnail_cache() primes the thumbnails — N+8 queries,
     * and every card render afterwards is cache-only. No transient on purpose:
     * invalidating it correctly would mean watching posts, terms and Polylang
     * languages to save a handful of tiny indexed queries.
     *
     * Root selection mirrors get_terms(parent => 0, hide_empty => true) for a
     * hierarchical taxonomy: a root stays when it or any descendant has posts.
     *
     * WP_Query rather than get_posts(): get_posts() defaults to
     * suppress_filters = true, which would bypass Polylang's language filter
     * and mix languages into the grid.
     *
     * Same post shape as sw_attach_card_terms() produces (->term_name and
     * ->term_slug of the root term), so template-parts/news-card.php can
     * consume the result as well. A post that is the newest in two roots is
     * listed once, under the first term.
     *
     * @return \WP_Post[] Posts with ->term_name / ->term_slug attached; thumbnail cache primed.
     */
    function sw_news_latest_by_root_term(string $taxonomy = 'category-oportunities'): array
    {
        $all_terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($all_terms) || ! $all_terms) {
            return [];
        }

        $by_id    = [];
        $children = [];

        foreach ($all_terms as $term) {
            $by_id[(int) $term->term_id]        = $term;
            $children[(int) $term->parent][]    = (int) $term->term_id;
        }

        $term_by_post = [];

        foreach ($all_terms as $term) {
            if ((int) $term->parent !== 0) {
                continue;
            }

            // The root and all of its descendants, breadth-first.
            $branch = [(int) $term->term_id];
            for ($i = 0; $i < count($branch); $i++) {
                foreach ($children[$branch[$i]] ?? [] as $child_id) {
                    if (! in_array($child_id, $branch, true)) {
                        $branch[] = $child_id;
                    }
                }
            }

            $tt_ids    = [];
            $has_posts = false;
            foreach ($branch as $branch_id) {
                $tt_ids[] = (int) $by_id[$branch_id]->term_taxonomy_id;
                if ((int) $by_id[$branch_id]->count > 0) {
                    $has_posts = true;
                }
            }

            if (! $has_posts) {
                continue; // hide_empty: the whole branch is empty
            }

            // term_taxonomy_id: WP_Tax_Query then needs no lookup query.
            $ids = (new \WP_Query([
                'post_type'              => 'news',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'tax_query'              => [[
                    'taxonomy'         => $taxonomy,
                    'field'            => 'term_taxonomy_id',
                    'terms'            => $tt_ids,
                    'include_children' => false,
                ]],
            ]))->posts;

            $post_id = (int) ($ids[0] ?? 0);

            if ($post_id > 0 && ! isset($term_by_post[$post_id])) {
                $term_by_post[$post_id] = $term;
            }
        }

        if (! $term_by_post) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'              => 'news',
            'post__in'               => array_keys($term_by_post),
            'orderby'                => 'post__in',
            'posts_per_page'         => count($term_by_post),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]);

        update_post_thumbnail_cache($query);

        foreach ($query->posts as $post_item) {
            $post_item->term_name = $term_by_post[$post_item->ID]->name;
            $post_item->term_slug = $term_by_post[$post_item->ID]->slug;
        }

        return $query->posts;
    }
}

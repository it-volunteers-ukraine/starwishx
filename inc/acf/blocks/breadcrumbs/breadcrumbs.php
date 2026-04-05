<?php

/**
 * Breadcrumbs block — universal, CPT-aware.
 *
 * Contexts supported:
 *   - CPT archive pages    → Home > Archive Label
 *   - CPT singles          → Home > Archive Label > Post Title
 *   - Pages with hierarchy → Home > Parent > … > Page Title
 *   - News-by-category     → Home > News > Category Name
 *
 * Last crumb is never a link (current page). Toggle its visibility
 * via $block['data']['show_last_item'] (default true).
 *
 * When $block['data']['nowrap'] is true the <nav> is rendered directly,
 * without the outer <section> / <div class="container"> wrappers.
 *
 * Schema.org BreadcrumbList structured data included.
 *
 * Query budget: 0–2 depending on context (term lookup, parent walk;
 * post_type_object and archive link are cached by WP).
 *
 * File: inc/acf/blocks/breadcrumbs/breadcrumbs.php
 */

declare(strict_types=1);

$default_classes = [
    'section'            => 'section',
    'breadcumbs-section' => 'breadcumbs-section',
    'list'               => 'list',
    'item'               => 'item',
    'selected'           => 'selected',
    'link'               => 'link',
    'arrow-icon'         => 'arrow-icon',
    'home-icon'          => 'home-icon',
];

$classes      = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['breadcrumbs'] ?? []);
}

// Block-level option: whether to display the last (current) breadcrumb item
$show_last_item = (bool) ($block['data']['show_last_item'] ?? true);

// Block-level option: when true, render <nav> directly without
// <section> / <div class="container"> wrappers (default false)
$nowrap = (bool) ($block['data']['nowrap'] ?? false);
// <nav> additional classes
$nav_class = (string) ($block['data']['nav_class'] ?? '');

// ── Home crumb (always first) ───────────────────────────────────────────────
$home_id    = (int) get_option('page_on_front');
$home_url   = $home_id ? get_permalink($home_id) : home_url('/');
$home_title = $home_id ? get_the_title($home_id) : __('Home', 'starwishx');

if (!$home_url) {
    return;
}

// ── Build crumbs ────────────────────────────────────────────────────────────
$breadcrumbs   = [];
$category_slug = get_query_var('news_cat');

global $post;

// A) Post-type archive page (archive-news.php, archive-opportunity.php)
//    The global $post here is the first post in the query — not what we want.
//    We only need the archive label from the post type object.
if (is_post_type_archive()) {
    $archive_pt = get_query_var('post_type');
    if (is_array($archive_pt)) {
        $archive_pt = reset($archive_pt);
    }
    $pt_object = get_post_type_object($archive_pt);
    // echo '<pre>';
    // var_dump($archive_pt, $pt_object);
    // echo '</pre>';

    if ($pt_object) {
        $breadcrumbs[] = [
            'title' => $pt_object->labels->name,
            'link'  => null, // current page
        ];
    }

    // B) Regular post / page / CPT single
} elseif ($post) {
    $post_type = get_post_type($post);
    $is_cpt    = !in_array($post_type, ['page', 'post'], true);

    // 1. CPT archive ancestor (linked) — e.g. "News", "Opportunities"
    if ($is_cpt) {
        $pt_object   = get_post_type_object($post_type);
        $archive_url = $pt_object ? get_post_type_archive_link($post_type) : '';
        // echo '<pre>';
        // var_dump($archive_pt, $pt_object);
        // echo '</pre>';
        if ($pt_object && $archive_url) {
            $breadcrumbs[] = [
                'title' => $pt_object->labels->name,
                'link'  => $archive_url,
            ];
        }
    }

    // 2. Page hierarchy ancestors (skip front page — it's already the home crumb)
    if ($post_type === 'page' && $post->post_parent) {
        $ancestors    = [];
        $current_post = get_post($post->post_parent);

        while ($current_post) {
            if ((int) $current_post->ID === $home_id) {
                break;
            }

            $ancestors[] = [
                'title' => get_the_title($current_post),
                'link'  => get_permalink($current_post),
            ];

            $current_post = $current_post->post_parent
                ? get_post($current_post->post_parent)
                : null;
        }

        // Collected child → parent; reverse to parent → child
        $breadcrumbs = array_merge($breadcrumbs, array_reverse($ancestors));
    }

    // 3. Last crumb: category name (news-by-category) OR current post title
    if ($category_slug) {
        $term = get_term_by('slug', $category_slug, 'category-oportunities');

        if ($term && !is_wp_error($term)) {
            $breadcrumbs[] = [
                'title' => $term->name,
                'link'  => null,
            ];
        }
    } else {
        $breadcrumbs[] = [
            'title' => get_the_title($post),
            'link'  => null,
        ];
    }
}

// When show_last_item is false, drop the final crumb
if (!$show_last_item && !empty($breadcrumbs)) {
    array_pop($breadcrumbs);
}

// Nothing besides home? Still render for visual consistency.
// Schema.org position counter (home = 1).
$schema_position = 1;

// ── Nav markup (shared between wrapped and nowrap modes) ────────────────────
$nav = static function () use ($classes, $home_url, $home_title, $breadcrumbs, &$schema_position, $nav_class): void { ?>
    <nav class="breadcumbs-nav <?= esc_attr($nav_class); ?>" aria-label="<?= esc_attr__('Breadcrumbs', 'starwishx'); ?>">
        <ol class="text-r <?= esc_attr($classes['list']); ?>"
            itemscope itemtype="https://schema.org/BreadcrumbList">

            <?php // ── Home ─────────────────────────────────────── 
            ?>
            <li class="<?= esc_attr($classes['item']); ?>"
                itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a class="link-bc" href="<?= esc_url($home_url); ?>"
                    itemprop="item" aria-label="<?= esc_attr($home_title); ?>">
                    <?php sw_svg_e('icon-house', 18, null, $classes['home-icon']); ?>
                    <meta itemprop="name" content="<?= esc_attr($home_title); ?>">
                </a>
                <meta itemprop="position" content="<?= $schema_position++; ?>">
            </li>

            <?php foreach ($breadcrumbs as $index => $crumb) :
                $is_last = ($index === count($breadcrumbs) - 1);
            ?>
                <li class="<?= esc_attr($classes['item']); ?><?= $is_last ? ' ' . esc_attr($classes['selected']) : ''; ?>"
                    itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <?php sw_svg_e('icon-arrow', 10, null, $classes['arrow-icon']); ?>
                    <?php if ($crumb['link']) : ?>
                        <a class="link-bc" href="<?= esc_url($crumb['link']); ?>" itemprop="item">
                            <span itemprop="name"><?= esc_html($crumb['title']); ?></span>
                        </a>
                    <?php else : ?>
                        <span class="link-bc" aria-current="page" itemprop="item">
                            <span itemprop="name"><?= esc_html($crumb['title']); ?></span>
                        </span>
                    <?php endif; ?>
                    <meta itemprop="position" content="<?= $schema_position++; ?>">
                </li>
            <?php endforeach; ?>

        </ol>
    </nav>
<?php };

// ── Render ──────────────────────────────────────────────────────────────────
if ($nowrap) :
    $nav();
else : ?>
    <section class="section breadcumbs-section <?= esc_attr($classes['section']); ?>">
        <div class="container">
            <?php $nav(); ?>
        </div>
    </section>
<?php endif;

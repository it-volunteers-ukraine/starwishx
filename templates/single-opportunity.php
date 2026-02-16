<?php

/**
 * Template Name: Single Opportunity View
 * Template Post Type: opportunity
 *
 * This template displays the full details of a Single Opportunity CPT.
 */

declare(strict_types=1);

$td = '_themedomain';
$post_id = get_the_ID();

// Used inc/theme-helpers.php function
$data = sw_get_opportunity_view_data($post_id);

$referer  = wp_get_referer();
$back_url = $referer ?: home_url('home');

// CSS category-oportunities injection using inc/theme-helpers.php function
$css = get_taxonomy_top_level_colors_styles('category-oportunities');
if (!empty($css)) {
    wp_register_style('cat-oportunities-color-styles', false);
    wp_enqueue_style('cat-oportunities-color-styles', false);
    wp_add_inline_style('cat-oportunities-color-styles', $css);
}

get_header();
?>
<nav class="opportunity-breadcrumbs container" aria-label="<?php esc_attr_e('Breadcrumb', $td); ?>">
    <a href="<?php echo esc_url($back_url); ?>" class="btn-back">
        <svg width="13" height="16" class="icon-arrow-left">
            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
        </svg>
        <span><?php esc_html_e('Opportunities', $td); ?></span>
    </a>
</nav>

<div class="single-opportunity__layout container">
    <main id="main" class="opportunity-main" role="main">

        <figure class="featured-figure">
            <!-- 1. Categories -->
            <?php if (!empty($data['root_categories'])) : ?>
                <div class="info-card info-card__categories">
                    <dt class="info-card__title d-none" aria-hidden="true">
                        <?php esc_html_e('Категорія', $td); ?>
                    </dt>
                    <dd class="tag-cloud tag-cloud__category">
                        <?php
                        echo sw_render_collapsible_tag_list(
                            $data['root_categories'],
                            'tag-category',
                            1991, // Forces full display
                            'tag-list',
                            true
                        );
                        ?>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', ['itemprop' => 'image', 'class' => 'featured-figure__image']); ?>
            <?php else : ?>
                <div class="featured-image__placeholder">
                    <svg width="40" height="40" class="icon-heart">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-heart"></use>
                    </svg>
                </div>
            <?php endif; ?>
            <figcaption class="d-none"><?php the_title(); ?></figcaption>

            <?php get_template_part('template-parts/control-favorites', null, [
                'post_id' => $post_id,
                'show_label' => false
            ]); ?>
        </figure>

        <article class="opportunity-article" itemscope itemtype="https://schema.org/Article">
            <header class="opportunity-header">
                <h1 class="opportunity-title h4" itemprop="headline">
                    <?php the_title(); ?>
                </h1>

                <div class="opportunity-article-meta__wrapper">
                    <dl class="opportunity-article-meta">
                        <!-- 2. Dates -->
                        <div class="info-card info-card--dates">
                            <dt class="info-card__title">
                                <svg width="18" height="18" class="info-card__icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-calendar"></use>
                                </svg>
                                <?php esc_html_e('Терміни', $td); ?>
                            </dt>
                            <div class="date-row">
                                <time class="btn-text-medium" datetime="<?php echo esc_attr($data['date_start']['iso']); ?>">
                                    <?php echo esc_html($data['date_start']['display']); ?>
                                </time>
                                —
                                <time class="btn-text-medium" datetime="<?php echo esc_attr($data['date_end']['iso']); ?>">
                                    <?php echo esc_html($data['date_end']['display']); ?>
                                </time>
                            </div>
                        </div>

                        <!-- 3. Location -->
                        <div class="info-card">
                            <dt class="info-card__title">
                                <svg width="16" height="21" class="info-card__icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-location-bold"></use>
                                </svg>
                                <?php esc_html_e('Локація', $td); ?>
                            </dt>
                            <?php if (!empty($data['locations'])) : ?>
                                <dd class="tag-cloud tag-cloud__locations">
                                    <!-- <ul class="tag-list">
                                        < ?php foreach ($data['locations'] as $loc) : ?>
                                            <li class="tag-locations">< ?php echo esc_html(trim($loc->name)); ?></li>
                                        < ?php endforeach; ?>
                                    </ul> -->
                                    <?= sw_render_collapsible_tag_list($data['locations'], 'tag-locations', 5); ?>
                                </dd>
                                <!-- < ?php $loc_names = array_column($data['locations'], 'name'); echo esc_html(implode(', ', $loc_names)); ? > -->
                            <?php elseif (!empty($data['country_name'])) : ?>
                                <dd class="tag-cloud tag-cloud__locations">
                                    <ul class="tag-list">
                                        <li class="tag-locations"><?php echo esc_html($data['country_name']); ?></li>
                                    </ul>
                                </dd>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Seekers -->
                        <?php if (!empty($data['seeker_terms'])) : ?>
                            <div class="info-card">
                                <dt class="info-card__title">
                                    <svg width="24" height="24" class="info-card__icon">
                                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-person"></use>
                                    </svg>
                                    <?php esc_html_e('Для кого', $td); ?>
                                </dt>
                                <!-- <dd class="tag-cloud tag-cloud__seekers">
                                    <ul class="tag-list">
                                        < ?php foreach ($data['seeker_ids'] as $s_id) : ?>
                                            <li class="tag-seekers">< ?php echo esc_html(get_term($s_id)->name); ?></li>
                                        < ?php endforeach; ?>
                                    </ul>
                                </dd> -->
                                <dd class="tag-cloud tag-cloud__seekers">
                                    <?= sw_render_collapsible_tag_list($data['seeker_terms'], 'tag-seekers', 5); ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <div class="opportunity-social">
                        <div class="opportunity-social__share">
                            <span>Social share</span>
                            <svg width="18" height="20" class="icon-share">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-share"></use>
                            </svg>
                        </div>
                        <div class="opportunity-badges">
                            <?php if (is_user_logged_in()) {
                                get_template_part('template-parts/control-favorites', null, ['post_id' => $post_id]);
                            } ?>
                        </div>
                    </div>
                </div>

                <!-- Document -->
                <?php if ($data['document']) : ?>
                    <div class="info-card info-card--file">
                        <a href="<?php echo esc_url($data['document']['url']); ?>" class="file-download" download>
                            <svg class="icon-file">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-document"></use>
                            </svg>
                            <div class="file-download__meta">
                                <span class="file-name"><?php echo esc_html($data['document']['title']); ?></span>
                                <span class="file-size"><?php echo size_format((int)$data['document']['filesize']); ?></span>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>
            </header>

            <section class="opportunity-content" itemprop="articleBody">
                <?php if ($data['description']) : ?>
                    <div class="opportunity-content__text">
                        <?php echo wp_kses_post(nl2br($data['description'])); ?>
                    </div>
                <?php endif; ?>

                <?php if ($data['requirements']) : ?>
                    <h2 class="opportunity-content__title"><?php esc_html_e('Вимоги', $td); ?></h2>
                    <div class="opportunity-content__text">
                        <?php echo wp_kses_post(nl2br($data['requirements'])); ?>
                    </div>
                <?php endif; ?>

                <h2 class="opportunity-content__title"><?php esc_html_e('Першоджерело', $td); ?></h2>
                <div class="opportunity-content__text">
                    <strong><?php echo esc_html($data['company']); ?>, </strong>

                    <?php if ($data['source_url']) : ?>
                        <span><?php esc_html_e('Посилання', $td); ?>: </span>
                        <a href="<?php echo esc_url($data['source_url']); ?>" class="btn-link" target="_blank" rel="nofollow">
                            <?php echo esc_url($data['source_url']); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($data['details']) : ?>
                    <section class="opportunity-content opportunity-content--details">
                        <h2 class="opportunity-content__title"><?php esc_html_e('Додаткова інформація', $td); ?></h2>
                        <div class="opportunity-content__text">
                            <?php echo wp_kses_post(nl2br($data['details'])); ?>
                        </div>
                    </section>
                <?php endif; ?>
            </section>

            <?php get_template_part('template-parts/comments', 'interactive'); ?>
        </article>
    </main>
    <aside class="opportunity-aside">
        <div class="news-container">
            <?php get_template_part('template-parts/last-news', 'aside', [
                'title' => 'News',
                'title_class' => 'h5',
                'count_news' => 10,
                'line_clamp' => 3
            ]); ?>
        </div>
    </aside>
</div>

<?php get_footer(); ?>
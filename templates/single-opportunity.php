<?php

/**
 * Template Name: Single Opportunity View
 * Template Post Type: opportunity
 *
 * This template displays the full details of a Single Opportunity CPT.
 */

declare(strict_types=1);

$td = '_themedomain'; // Text domain for translations
get_header();

/**
 * 1. DATA ACQUISITION & SANITIZATION
 * We fetch all ACF fields defined in the group group_69373c79e9c9a.
 */
$post_id = get_the_ID();

// Applicant Info
$applicant_name  = get_field('opportunity_applicant_name');
$applicant_mail  = get_field('opportunity_applicant_mail');
$applicant_phone = get_field('opportunity_applicant_phone');

// Company & Location
$company    = get_field('opportunity_company');
$city       = get_field('city');
$source_url = get_field('opportunity_sourcelink');

// Taxonomy fields (ACF returns IDs based on your JSON)
// $category_id    = get_field('opportunity_category');
// $subcategory_ids = get_field('opportunity_subcategory'); // Array of IDs
$country_id     = get_field('country');
$seeker_ids     = get_field('opportunity_seekers'); // Array of IDs

// Dates (ACF returns d/m/Y per config)
$date_start = get_field('opportunity_date_starts');
$date_end   = get_field('opportunity_date_ends');
$date_start_iso = '';
$date_end_iso = '';
if ($date_start) {
    $date_obj = DateTime::createFromFormat('d/m/Y', $date_start);
    if ($date_obj) {
        $date_start_iso = $date_obj->format('Y-m-d');
    }
}
if ($date_end) {
    $date_obj = DateTime::createFromFormat('d/m/Y', $date_end);
    if ($date_obj) {
        $date_end_iso = $date_obj->format('Y-m-d');
    }
}

// Content
$description  = get_field('opportunity_description');
$requirements = get_field('opportunity_requirements');
$details      = get_field('opportunity_details');
// Returns Array per config
$document = get_field('opportunity_document');

/**
 * Helper: breadcrumbs, back link
 */
$referer  = wp_get_referer();
$back_url = $referer ?: home_url('home');

?>
<!-- Breadcrumbs -->
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
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', ['itemprop' => 'image', 'class' => 'featured-figure__image']); ?>
            <?php else : ?>
                <div class="featured-image__placeholder">
                    <svg width="40" height="40" class="icon-heart">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-heart"></use>
                    </svg>
                </div>
            <?php endif; ?>
            <!-- Favorite/Bookmark Button: icon-only, accessible -->
            <!-- <button type="button" class="favorite-btn" aria-label="Add to favorites" aria-pressed="false">
                 Icon: hidden from AT, actual label is aria-label 
                <svg aria-hidden="true" focusable="false">
                    <use href="#icon-heart"></use>
                </svg>
            </button> -->
            <!-- Optional: visible caption for image context -->
            <figcaption class="d-none">
                Гуманітарна аптечка першої допомоги
            </figcaption>
        </figure>
        <article class="opportunity-article" itemscope itemtype="https://schema.org/Article">
            <header class="opportunity-header">
                <h1 class="opportunity-title h4" itemprop="headline">
                    <?php the_title(); ?>
                </h1>
                <div class="opportunity-article-meta__wrapper">
                    <dl class="opportunity-article-meta">
                        <!-- Dates -->
                        <div class="info-card info-card--dates">
                            <dt class="info-card__title">
                                <svg width="18" height="18" class="info-card__icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-calendar"></use>
                                </svg>
                                <?php esc_html_e('Терміни', $td); ?>
                            </dt>
                            <div class="date-row">
                                <time class="btn-text-medium" datetime="<?php echo esc_attr($date_start_iso); ?>">
                                    <?php echo esc_html($date_start); ?>
                                </time>
                                —
                                <time class="btn-text-medium" datetime="<?php echo esc_attr($date_end_iso); ?>">
                                    <?php echo esc_html($date_end); ?>
                                </time>
                            </div>
                        </div>
                        <!-- Place -->
                        <div class="info-card">
                            <dt class="info-card__title">
                                <svg width="16" height="21" class="info-card__icon">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-location-bold"></use>
                                </svg>
                                <?php esc_html_e('Локація', $td); ?>
                            </dt>
                            <dd class="btn-text-medium">
                                <?php
                                if ($country_id) echo esc_html(get_term($country_id)->name);
                                if ($city) echo ', ' . esc_html($city);
                                ?>
                            </dd>
                        </div>
                        <!-- Seekers -->
                        <?php if (!empty($seeker_ids)) : ?>
                            <div class="info-card">
                                <dt class="info-card__title">
                                    <svg width="24" height="24" class="info-card__icon">
                                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-person"></use>
                                    </svg>
                                    <?php esc_html_e('Для кого', $td); ?>
                                </dt>
                                <dd class="tag-cloud btn-text-medium">
                                    <?php foreach ($seeker_ids as $s_id) : ?>
                                        <span class="tag"><?php echo esc_html(get_term($s_id)->name); ?></span><br>
                                    <?php endforeach; ?>
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
                            <!-- Favorite Control -->
                            <?php
                            if (is_user_logged_in()) {
                                get_template_part('template-parts/control-favorites', null, [
                                    'post_id' => $post_id
                                ]);
                            }
                            ?>
                        </div>
                    </div>
                </div> <!-- article-meta-wrapper -->

                <!-- Document Download -->
                <?php if ($document) : ?>
                    <div class="info-card info-card--file">
                        <a href="<?php echo esc_url($document['url']); ?>" class="file-download" download>
                            <svg class="icon-file">
                                <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-document"></use>
                            </svg>
                            <div class="file-download__meta">
                                <span class="file-name"><?php echo esc_html($document['title']); ?></span>
                                <span class="file-size"><?php echo size_format((int)$document['filesize']); ?></span>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>
            </header>
            <section class="opportunity-content" aria-label="Post content" itemprop="articleBody">
                <?php if ($description) : ?>
                    <!-- <h2 class="opportunity-content__title"><?php esc_html_e('Опис можливості', $td); ?></h2> -->
                    <div class="opportunity-content__text">
                        <?php echo wp_kses_post(nl2br($description)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($requirements) : ?>
                    <h2 class="opportunity-content__title"><?php esc_html_e('Вимоги', $td); ?></h2>
                    <div class="opportunity-content__text">
                        <?php echo wp_kses_post(nl2br($requirements)); ?>
                    </div>
                <?php endif; ?>

                <h2 class="opportunity-content__title"><?php esc_html_e('Першоджерело', $td); ?></h2>
                <div class="opportunity-content__text">
                    <strong><?php echo esc_html($company); ?>, </strong>

                    <?php if ($source_url) : ?>
                        <span><?php esc_html_e('Посилання', $td); ?>: </span>
                        <a href="<?php echo esc_url($source_url); ?>" class="btn-link" target="_blank" rel="nofollow">
                            <?php echo esc_url($source_url); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($details) : ?>
                    <section class="opportunity-content opportunity-content--details">
                        <h2 class="opportunity-content__title"><?php esc_html_e('Додаткова інформація', $td); ?></h2>
                        <div class="opportunity-content__text">
                            <?php echo wp_kses_post(nl2br($details)); ?>
                        </div>
                    </section>
                <?php endif; ?>
            </section>
            <!-- Article footer: User reviews (comments) -->
            <?php
            get_template_part('template-parts/comments', 'interactive');
            ?>
        </article>
    </main>
    <aside class="opportunity-aside" aria-label="Latest news">
        <div class="news-container">
            <?php
            get_template_part(
                'template-parts/last-news',
                'aside',
                [
                    'title' => 'News',
                    'title_class' => 'h5',
                    'count_news' => 10,
                    'line_clamp' => 3
                ]
            );
            ?>
        </div>
    </aside>
</div>
<?php

get_footer();

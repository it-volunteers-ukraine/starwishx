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
$category_id    = get_field('opportunity_category');
$subcategory_ids = get_field('opportunity_subcategory'); // Array of IDs
$country_id     = get_field('country');
$seeker_ids     = get_field('opportunity_seekers'); // Array of IDs

// Dates (ACF returns d/m/Y per your config)
$date_start = get_field('opportunity_date_starts');
$date_end   = get_field('opportunity_date_ends');

// Content Areas
$description  = get_field('opportunity_description');
$requirements = get_field('opportunity_requirements');
$details      = get_field('opportunity_details');

// File Attachment
$document = get_field('opportunity_document'); // Returns Array per your config

/**
 * 2. HELPER: BREADCRUMBS / BACK LINK
 */
$referer  = wp_get_referer();
$back_url = $referer ?: home_url('/launchpad/?panel=opportunities');
?>

<main id="main" class="site-main opportunity-single">
    <div class="container">

        <!-- Breadcrumbs -->
        <nav class="opportunity-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', $td); ?>">
            <a href="<?php echo esc_url($back_url); ?>" class="btn-back">
                <svg width="13" height="16" class="icon-arrow-left">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-long_arrow_left"></use>
                </svg>
                <span><?php esc_html_e('Назад до списку', $td); ?></span>
            </a>
        </nav>

        <header class="opportunity-header">
            <h1 class="opportunity-title h2"><?php the_title(); ?></h1>

            <div class="opportunity-badges">
                <?php if ($category_id) :
                    $term = get_term($category_id); ?>
                    <span class="badge badge--primary"><?php echo esc_html($term->name); ?></span>
                <?php endif; ?>

                <?php if (!empty($subcategory_ids)) :
                    foreach ($subcategory_ids as $sub_id) :
                        $sub_term = get_term($sub_id); ?>
                        <span class="badge badge--secondary"><?php echo esc_html($sub_term->name); ?></span>
                <?php endforeach;
                endif; ?>
                <!-- Favorite Control -->
                <?php
                if (is_user_logged_in()) {
                    get_template_part('template-parts/control-favorites', null, [
                        'post_id' => $post_id
                    ]);
                }
                ?>
            </div>
        </header>

        <div class="opportunity-layout">

            <!-- SIDEBAR: Metadata & Contact -->
            <aside class="opportunity-sidebar">

                <!-- Date Card -->
                <div class="info-card info-card--dates">
                    <h3 class="info-card__title"><?php esc_html_e('Терміни', $td); ?></h3>
                    <div class="date-row">
                        <strong><?php esc_html_e('з:', $td); ?></strong>
                        <span><?php echo esc_html($date_start); ?></span>
                    </div>
                    <div class="date-row">
                        <strong><?php esc_html_e('по:', $td); ?></strong>
                        <span><?php echo esc_html($date_end); ?></span>
                    </div>
                </div>

                <!-- Contact Card -->
                <!-- <div class="info-card">
                    <h3 class="info-card__title"><?php esc_html_e('Контакти заявника', $td); ?></h3>
                    <ul class="contact-list">
                        <?php if ($applicant_name) : ?>
                            <li><strong><?php esc_html_e('Ім’я:', $td); ?></strong> <?php echo esc_html($applicant_name); ?></li>
                        <?php endif; ?>
                        <?php if ($applicant_mail) : ?>
                            <li><strong><?php esc_html_e('Email:', $td); ?></strong> <a href="mailto:<?php echo esc_attr($applicant_mail); ?>"><?php echo esc_html($applicant_mail); ?></a></li>
                        <?php endif; ?>
                        <?php if ($applicant_phone) : ?>
                            <li><strong><?php esc_html_e('Тел:', $td); ?></strong> <?php echo esc_html($applicant_phone); ?></li>
                        <?php endif; ?>
                    </ul>
                </div> -->

                <!-- Entity Card -->
                <div class="info-card">
                    <h3 class="info-card__title"><?php esc_html_e('Локація', $td); ?></h3>
                    <?php
                    if ($country_id) echo esc_html(get_term($country_id)->name);
                    if ($city) echo ', ' . esc_html($city);
                    ?>
                </div>

                <!-- Seekers Taxonomy -->
                <?php if (!empty($seeker_ids)) : ?>
                    <div class="info-card">
                        <h3 class="info-card__title"><?php esc_html_e('Для кого', $td); ?></h3>
                        <div class="tag-cloud">
                            <?php foreach ($seeker_ids as $s_id) : ?>
                                <span class="tag"><?php echo esc_html(get_term($s_id)->name); ?></span><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

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

            </aside>

            <!-- MAIN CONTENT: Description & Requirements -->
            <article class="opportunity-main">
                <?php if ($description) : ?>
                    <section class="content-block">
                        <h2 class="content-block__title"><?php esc_html_e('Опис можливості', $td); ?></h2>
                        <div class="content-block__text">
                            <?php echo wp_kses_post(nl2br($description)); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($requirements) : ?>
                    <section class="content-block">
                        <h2 class="content-block__title"><?php esc_html_e('Вимоги', $td); ?></h2>
                        <div class="content-block__text">
                            <?php echo wp_kses_post(nl2br($requirements)); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="content-block">
                    <h2 class="content-block__title"><?php esc_html_e('Першоджерело', $td); ?></h2>
                    <div class="content-block__text">
                        <strong><?php echo esc_html($company); ?></strong>

                        <?php if ($source_url) : ?>
                            <a href="<?php echo esc_url($source_url); ?>" class="btn-link" target="_blank" rel="nofollow">
                                <?php esc_html_e('Посилання', $td); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($details) : ?>
                    <section class="content-block content-block--details">
                        <h2 class="content-block__title"><?php esc_html_e('Додаткова інформація', $td); ?></h2>
                        <div class="content-block__text">
                            <?php echo wp_kses_post(nl2br($details)); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php
                get_template_part('template-parts/comments', 'interactive');
                ?>
            </article>
        </div>
    </div> <!-- container -->
</main>

<?php

get_footer();

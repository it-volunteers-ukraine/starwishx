<?php

/**
 * Template Name: Single Project View
 * Template Post Type: project
 *
 * Displays a single Project CPT with tabbed sections:
 * About, Opportunities, NGOs — all SSR, toggled via Interactivity API.
 *
 * File: templates/single-project.php
 */

declare(strict_types=1);

$post_id = get_the_ID();

$data  = \projects()->service()->getViewData($post_id);
$state = \projects()->getState($post_id);

// Hydrate Interactivity API state
wp_interactivity_state('starwishx/projects', $state);

get_header();
?>
<?php
if (function_exists('render_block')) {
    echo render_block([
        'blockName'   => 'acf/breadcrumbs',
        'attrs'       => [
            'data'        => [
                'show_last_item' => true,
                'nowrap'         => true,
                'nav_class'      => 'container',
            ],
        ],
        'innerHTML'   => '',
        'innerBlocks' => [],
    ]);
}
?>

<div class="single-project__layout container"
    data-wp-interactive="starwishx/projects">

    <main id="main" class="project-main" role="main">

        <!-- Sidebar: Featured Image + Tab Navigation -->
        <div class="project-sidebar">
            <figure class="project-sidebar__figure">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('large', [
                        'itemprop' => 'image',
                        'class'    => 'project-sidebar__image',
                    ]); ?>
                <?php else : ?>
                    <div class="project-sidebar__placeholder">
                        <img class="project-sidebar__fallback-icon"
                            src="<?php echo get_template_directory_uri(); ?>/assets/img/icon-opportunities-gradient.svg"
                            alt="">
                    </div>
                <?php endif; ?>

                <?php if (get_post_status() === 'publish') {
                    get_template_part('template-parts/control-favorites', null, [
                        'post_id'    => $post_id,
                        'show_label' => false,
                    ]);
                } ?>
            </figure>

            <nav class="project-tabs" aria-label="<?php esc_attr_e('Project sections', 'starwishx'); ?>">
                <button type="button"
                    class="project-tabs__btn"
                    data-tab="about"
                    data-wp-on--click="actions.switchTab"
                    data-wp-class--is-active="state.isAboutActive">
                    <?php esc_html_e('About project', 'starwishx'); ?>
                </button>

                <?php if ($state['counts']['opportunities'] > 0) : ?>
                    <button type="button"
                        class="project-tabs__btn"
                        data-tab="opportunities"
                        data-wp-on--click="actions.switchTab"
                        data-wp-class--is-active="state.isOpportunitiesActive">
                        <?php esc_html_e('Opportunities', 'starwishx'); ?>
                        <span class="project-tabs__count"
                            data-wp-text="state.opportunitiesCount"></span>
                    </button>
                <?php endif; ?>

                <?php if ($state['counts']['ngos'] > 0) : ?>
                    <button type="button"
                        class="project-tabs__btn"
                        data-tab="ngos"
                        data-wp-on--click="actions.switchTab"
                        data-wp-class--is-active="state.isNgosActive">
                        <?php esc_html_e('NGOs', 'starwishx'); ?>
                        <span class="project-tabs__count"
                            data-wp-text="state.ngosCount"></span>
                    </button>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Article: Tab Content Panels -->
        <article class="project-article" itemscope itemtype="https://schema.org/Article">

            <header class="project-header">
                <h1 class="project-title h4" itemprop="headline">
                    <?php the_title(); ?>
                </h1>
                <div class="project-social">
                    <div class="project-social__share">
                        <span><?php esc_html_e('Social share', 'starwishx') ?></span>
                        <?php sw_svg_e('icon-share', 18, 20, 'icon-share'); ?>
                    </div>
                </div>
            </header>

            <!-- Tab: About -->
            <section class="project-panel project-panel--about"
                data-wp-bind--hidden="!state.isAboutActive">
                <div class="project-content" itemprop="articleBody">
                    <!-- < ?php echo wp_kses_post($data['description']); ? > -->
                    <?php echo $data['description']; ?>
                </div>
            </section>

            <!-- Tab: Opportunities -->
            <?php if ($state['counts']['opportunities'] > 0) : ?>
                <section class="project-panel project-panel--opportunities"
                    data-wp-bind--hidden="!state.isOpportunitiesActive">
                    <?php if (!empty($data['opportunities_info'])) : ?>
                        <p class="opportunities-info"><?php echo wp_kses_post($data['opportunities_info']); ?></p>
                    <?php endif; ?>
                    <div class="project-cards-grid--opportunities">
                        <template data-wp-each--item="state.opportunities">
                            <?php get_template_part('template-parts/project-card-opportunity'); ?>
                        </template>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Tab: NGOs -->
            <?php if ($state['counts']['ngos'] > 0) : ?>
                <section class="project-panel project-panel--ngos"
                    data-wp-bind--hidden="!state.isNgosActive">
                    <?php if (!empty($data['ngo_info'])) : ?>
                        <p class="ngo-info"> <?php echo wp_kses_post($data['ngo_info']); ?></p>
                    <?php endif; ?>
                    <div class="project-cards-grid--ngo">
                        <template data-wp-each--item="state.ngos">
                            <?php get_template_part('template-parts/project-card-ngo'); ?>
                        </template>
                    </div>
                </section>
            <?php endif; ?>
            <?php get_template_part('template-parts/comments', 'interactive'); ?>
        </article>
    </main>

    <aside class="project-aside">
        <div class="news-container">
            <?php get_template_part('template-parts/news-aside', null, [
                'title'       => __('News', 'starwishx'),
                'title_class' => 'h5',
                'count_news'  => 4,
                'line_clamp'  => 3,
            ]); ?>
        </div>
    </aside>
</div>

<?php
get_template_part('template-parts/element-popup', null, [
    'title' => __('Hi!', 'starwishx'),
    'text'  => __('This feature is only available for registered users.', 'starwishx'),
    'id'    => 'project-auth-popup',
]);

get_footer();
?>
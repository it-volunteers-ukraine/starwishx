<?php
/**
 * Block: Projects Banner (Static)
 * -------------------------------------------------
 */
$default_classes = [
    'projects-section' => 'projects-section',
    'header-block'     => 'header-block',
    'sub-title'        => 'sub-title',
    'main-title'       => 'main-title',
    'projects-grid'    => 'projects-grid',
    'project-card'     => 'project-card',
    'img-wrapper'      => 'img-wrapper',
    'project-image'    => 'project-image',
    'project-category' => 'project-category',
    'project-date'     => 'project-date',
    'project-heading'  => 'project-heading',
];

/* ---------- подгружаем modules.json ---------- */
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;

if ( file_exists( $modules_file ) ) {
    $modules = json_decode( file_get_contents( $modules_file ), true );
    $classes = array_merge( $default_classes, $modules['projects'] ?? [] );
}

/* ---------- данные ACF ---------- */
$section_title = get_field( 'section_title' );
$cards         = get_field( 'project_cards' );
?>

<section class="section <?= esc_attr( $classes['projects-section'] ) ?>">
    <div class="container">
        <?php if ( $section_title ) : ?>
            <h2 class="<?= esc_attr( $classes['main-title'] ) ?>">
                <?= esc_html( $section_title ) ?>
            </h2>
        <?php endif; ?>

        <!-- блок 2 : «від серця до серця» + «ПРОЕКТИ» -->
        <div class="<?= esc_attr( $classes['header-block'] ) ?>">
            <span class="<?= esc_attr( $classes['sub-title'] ) ?>">
                ВІД СЕРЦЯ ДО СЕРЦЯ
            </span>
            <h3 class="<?= esc_attr( $classes['main-title'] ) ?>">
                ПРОЕКТИ
            </h3>
        </div>

        <!-- блок 1 : карточки -->
        <?php if ( $cards ) : ?>
            <div class="<?= esc_attr( $classes['projects-grid'] ) ?>">
                <?php foreach ( $cards as $card ) : ?>
                    <?php
                    $category    = $card['category'] ?? '';
                    $image_id    = $card['image'] ?? false;
                    $date        = $card['date'] ?? '';
                    $link        = $card['link'] ?? '#';
                    $heading     = $card['heading'] ?? '';
                    $bg_color    = $card['category_bg_color'] ?? '';
                    $text_color  = $card['category_text_color'] ?? '';
                    ?>
                    <a href="<?= esc_url( $link ) ?>"
                       class="<?= esc_attr( $classes['project-card'] ) ?>">

                        <?php if ( $image_id ) : ?>
                            <div class="<?= esc_attr( $classes['img-wrapper'] ) ?>">
                                <?= wp_get_attachment_image(
                                    $image_id,
                                    'large',
                                    false,
                                    [ 'class' => $classes['project-image'] ]
                                ) ?>

                                <?php if ( $category ) : ?>
                                    <span class="<?= esc_attr( $classes['project-category'] ) ?>"
                                          style="background-color: <?= esc_attr( $bg_color ) ?>;
                                                 color: <?= esc_attr( $text_color ) ?>;">
                                        <?= esc_html( $category ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $date ) : ?>
                            <time class="<?= esc_attr( $classes['project-date'] ) ?>">
                                <?= esc_html( $date ) ?>
                            </time>
                        <?php endif; ?>

                        <?php if ( $heading ) : ?>
                            <h4 class="<?= esc_attr( $classes['project-heading'] ) ?>">
                                <?= esc_html( $heading ) ?>
                            </h4>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

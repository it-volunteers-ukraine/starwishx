<?php

$block_css_classes = [
    'hero1'
];

$default_classes = [
    'faq' => 'faq',
    'container' => 'container',
    'inner' => 'faq-inner',
    'suptitle' => 'suptitle',
    'title' => 'title',
    'text' => 'text',
    'accordion' => 'accordion',
    'accordion-item' => 'accordion-item',
    'accordion-item-header' => 'accordion-item-header',
    'accordion-item-header-title' => 'accordion-item-header-title',
    'accordion-item-header-icon' => 'accordion-item-header-icon cross',
    'accordion-item-description-wrapper' => 'accordion-item-description-wrapper',
    'accordion-item-description' => 'accordion-item-description'
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['faq'] ?? []);
}

echo $is_preview ? '<div class="gt-block-preview"><p style="font-style: italic;">BLOCK: faq</p>' : '';


$title = get_field('title');
$suptitle = get_field('suptitle');
$text = get_field('text');
$questions = get_field('questions');


?>

<section class="section <?php echo esc_attr($classes['faq']); ?>">
    <div class="<?php echo esc_attr($classes['container']); ?>">
        <div class="<?php echo esc_attr($classes['inner']); ?>">
            <?php if ($suptitle) : ?>
                <p class="<?php echo esc_attr($classes['suptitle']); ?>"><?php echo $suptitle ?></p>
            <?php endif; ?>
            <?php if ($title) : ?>
                <h2 class="<?php echo esc_attr($classes['title']); ?>"><?php echo $title ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="<?php echo esc_attr($classes['text']); ?>"><?php echo $text ?></p>
            <?php endif; ?>

            <?php if ($questions) : ?>
                <ol class="<?php echo esc_attr($classes['accordion']); ?>">
                    <?php foreach ($questions as $index => $question) :
                        $is_first = ($index === 0);
                        $open_class = $is_first ? ' open' : '';
                        $aria_exp = $is_first ? 'true' : 'false';
                    ?>
                        <li class="<?php echo esc_attr($classes['accordion-item']); ?><?php echo $open_class; ?>">
                            <div class="<?php echo esc_attr($classes['accordion-item-header']); ?>"
                                role="button"
                                aria-expanded="<?php echo $aria_exp; ?>"
                                aria-controls="acc-desc-<?php echo $index; ?>">
                                <span class="<?php echo esc_attr($classes['accordion-item-header-title']); ?>">
                                    <?php echo esc_html($question['question']); ?>
                                </span>
                                <svg width="24" height="24" class="<?php echo esc_attr($classes['accordion-item-header-icon']); ?>">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus"></use>
                                </svg>
                            </div>

                            <div id="acc-desc-<?php echo $index; ?>" class="<?php echo esc_attr($classes['accordion-item-description-wrapper']); ?>">
                                <div class="<?php echo esc_attr($classes['accordion-item-description']); ?>">
                                    <p><?php echo wp_kses_post($question['answer']); ?></p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

        </div>
    </div>
</section>
<?php
echo $is_preview ? '</div>' : '';

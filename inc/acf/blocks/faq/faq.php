<?php

$default_classes = [
    'faq'       => 'faq',
    'container' => 'container',
    'inner'     => 'faq-inner',
    'suptitle'  => 'suptitle',
    'title'     => 'title',
    'text'      => 'text',
    'accordion' => 'accordion',
    'accordion-item' => 'accordion-item',
    'accordion-item-header' => 'accordion-item-header',
    'accordion-item-header-title' => 'accordion-item-header-title',
    'accordion-item-header-icon' => 'accordion-item-header-icon',
    'accordion-item-description-wrapper' => 'accordion-item-description-wrapper',
    'accordion-item-description' => 'accordion-item-description'
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_map('esc_attr', array_merge($default_classes, $modules['faq'] ?? []));
}

echo $is_preview ? '<div class="gt-block-preview"><p style="font-style: italic;">BLOCK: faq</p>' : '';

$title = wp_kses_post(get_field('title'));
$suptitle = wp_kses_post(get_field('suptitle'));
$text = wp_kses_post(get_field('text'));
$questions = get_field('questions');

?>

<section class="section <?= $classes['faq'] ?>">
    <div class="<?= $classes['container'] ?>">
        <div class="<?= $classes['inner'] ?>">
            <?php if ($suptitle) : ?>
                <p class="<?= $classes['suptitle'] ?>"><?php echo $suptitle ?></p>
            <?php endif; ?>
            <?php if ($title) : ?>
                <h2 class="h2-big <?= $classes['title'] ?>"><?php echo $title ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="<?= $classes['text'] ?>"><?php echo $text ?></p>
            <?php endif; ?>

            <?php if ($questions) : ?>
                <ol class="accordion <?= $classes['accordion'] ?>">
                    <?php foreach ($questions as $index => $question) :
                        $is_first = ($index === 0);
                        $aria_exp = $is_first ? 'true' : 'false';
                        $aria_hidden = $is_first ? 'false' : 'true';
                    ?>
                        <li class="accordion-item <?= $classes['accordion-item'] ?>">
                            <button class="accordion-item-header <?= $classes['accordion-item-header'] ?>"
                                aria-expanded="<?php echo $aria_exp; ?>"
                                aria-controls="acc-desc-<?php echo $index; ?>">
                                <span class="<?= $classes['accordion-item-header-title'] ?>">
                                    <?php echo esc_html($question['question']); ?>
                                </span>
                                <svg width="24" height="24" class="accordion-item-header-icon cross <?= $classes['accordion-item-header-icon'] ?>">
                                    <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-plus"></use>
                                </svg>
                            </button>

                            <div id="acc-desc-<?php echo $index; ?>"
                                class="accordion-item-description-wrapper <?= $classes['accordion-item-description-wrapper'] ?>"
                                aria-hidden="<?php echo $aria_hidden; ?>">
                                <div class="<?= $classes['accordion-item-description'] ?>">
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

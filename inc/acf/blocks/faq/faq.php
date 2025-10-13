<?php

$block_css_classes = [
    'hero1'
];

echo $is_preview ? '<div class="gt-block-preview"><p style="font-style: italic;">BLOCK: faq</p>' : '';


$title = get_field('title');
$suptitle = get_field('suptitle');
$text = get_field('text');
$questions = get_field('questions');


?>

<section class="faq section">
    <div class="container">
        <div class="faq-inner">
            <?php if ($suptitle) : ?>
                <p class="suptitle"><?php echo $suptitle ?></p>
            <?php endif; ?>
            <?php if ($title) : ?>
                <h2 class="title"><?php echo $title ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="text"><?php echo $text ?></p>
            <?php endif; ?>

            <?php if ($questions) : ?>
                <ol class="accordion">
                    <?php foreach ($questions as $index => $question) :
                        $is_first = ($index === 0);
                        $open_class = $is_first ? ' open' : '';
                        $aria_exp = $is_first ? 'true' : 'false';
                    ?>
                        <li class="accordion-item<?php echo $open_class; ?>">
                            <div class="accordion-item-header"
                                role="button"
                                aria-expanded="<?php echo $aria_exp; ?>"
                                aria-controls="acc-desc-<?php echo $index; ?>">
                                <span class="accordion-item-header-title">
                                    <?php echo esc_html($question['question']); ?>
                                </span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" xmlns="http://www.w3.org/2000/svg"
                                    class="cross accordion-item-header-icon">
                                    <path d="M0.00195313 12H12.002M12.002 12L24.002 12M12.002 12V0M12.002 12L12.002 24"
                                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>

                            <div id="acc-desc-<?php echo $index; ?>" class="accordion-item-description-wrapper">
                                <div class="accordion-item-description">
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

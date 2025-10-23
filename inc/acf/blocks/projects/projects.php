<?php
$default_classes = [
    'container'      => 'container',
    'title'          => 'title',
    'grid'           => 'projects-grid',
    'card'           => 'project-card',
    'image'          => 'project-image',
    'category'       => 'project-category',
    'date'           => 'project-date',
    'heading'        => 'project-heading',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['projects'] ?? []);
}

// Заголовок секции (опционально)
$section_title = get_field('section_title');

// Массив из 4 карточек (repeater)
$cards = get_field('project_cards');
?>

<section class="section">
    <div class="container <?php echo esc_attr($classes['container']); ?>">
        <?php if ($section_title): ?>
            <h2 class="<?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($section_title); ?></h2>
        <?php endif; ?>

        <?php if ($cards): ?>
            <div class="<?php echo esc_attr($classes['grid']); ?>">
                <?php foreach ($cards as $card): ?>
                    <?php
                    $category = $card['category'] ?? '';
                    $image = $card['image'] ?? false;
                    $date = $card['date'] ?? ''; // формат: 2025-10-23 или любой текст
                    $link = $card['link'] ?? '#';
                    $heading = $card['heading'] ?? '';
                    ?>
                    <a href="<?php echo esc_url($link); ?>" class="<?php echo esc_attr($classes['card']); ?>">
                        <?php if ($image): ?>
                            <div class="<?php echo esc_attr($classes['image']); ?>">
                                <?php echo wp_get_attachment_image($image, 'large'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($category): ?>
                            <span class="<?php echo esc_attr($classes['category']); ?>">
                                <?php echo esc_html($category); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($date): ?>
                            <time class="<?php echo esc_attr($classes['date']); ?>">
                                <?php echo esc_html($date); ?>
                            </time>
                        <?php endif; ?>

                        <?php if ($heading): ?>
                            <h3 class="<?php echo esc_attr($classes['heading']); ?>">
                                <?php echo esc_html($heading); ?>
                            </h3>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
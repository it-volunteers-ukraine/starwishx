<?php
$default_classes = [
    'section'     => 'section',
    'active'      => 'active',
    'container'   => 'container',
    'main-title'  => 'main-title',
    'title-wrap'  => 'title-wrap',
    'subtitle'    => 'subtitle',
    'list'        => 'list',
    'item'        => 'item',
    'content'     => 'content',
    'block-title' => 'block-title',
    'count'       => 'count',
    'title'       => 'title',
    'block-photo' => 'block-photo',
    'photo1'      => 'photo1',
    'photo1-img'  => 'photo1-img',
    'text'        => 'text',
    'icon'        => 'icon',
    'btn1'        => 'btn1',
    'btn2'        => 'btn2'
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes      = $default_classes;
$main_title   = get_field('main_title');
$items        = get_field('accordion') ?: [];
$subtitle     = get_field('subtitle');
$btn_text     = get_field('btn_text');
$btn_url      = get_field('btn_page');

$is_mode_click_for_touch = get_field('mode_click_for_touch');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['accordion-one-photo'] ?? []);
}
?>

<section class="section <?= esc_attr($classes['section']); ?> ">
    <div class="container ">
        <?php if ($items) : ?>
            <div class="<?= esc_attr($classes['subtitle']); ?>"><?= esc_html($subtitle); ?></div>
            <div class="<?= esc_attr($classes['title-wrap']); ?>">
                <h2 class="h2-big <?= esc_attr($classes['main-title']); ?>"><?= esc_html($main_title); ?></h2>
                <a href="<?= esc_url($btn_url) ?>" class="btn <?= esc_attr($classes['btn1']); ?>"><?= esc_html($btn_text); ?></a>
            </div>

            <div id="slider-one-photo" data-click-mode="<?= $is_mode_click_for_touch ? 'true' : 'false'; ?>" class="<?= esc_attr($classes['list']); ?>">
                <?php $count = 0; ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $count++;
                    $title      = $item['title'];
                    $text       = trim($item['text'] ?? '');
                    $photo1     = $item['photo1'] ?? null;
                    $photo1_url = $photo1['sizes']['large'] ?? '';
                    $photo1_alt = ($photo1['alt'] ?? '') ?: ($photo1['title'] ?? '');
                    ?>
                    <div class="<?= esc_attr($classes['item']); ?>">
                        <?php if ($title) : ?>
                            <div class="<?= esc_attr($classes['block-title']); ?>">
                                <div class="<?= esc_attr($classes['count']); ?>"><?= sprintf("%02d", $count); ?></div>
                                <h3 class="h5 <?= esc_attr($classes['title']); ?>"><?= esc_html($title); ?></h3>
                            </div>
                        <?php endif; ?>
                        <div class="<?= esc_attr($classes['content']); ?>">
                            <?php if ($text) : ?>
                                <div class="<?= esc_attr($classes['text']); ?>">
                                    <?= esc_html($text); ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?= esc_attr($classes['block-photo']); ?>">
                                <div class="<?= esc_attr($classes['photo1']); ?>">
                                    <img src="<?= esc_url($photo1_url); ?>" class="<?= esc_attr($classes['photo1-img']); ?>" alt="<?= esc_attr($photo1_alt) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= esc_url($btn_url) ?>" class="btn <?= esc_attr($classes['btn2']); ?>"><?= esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>
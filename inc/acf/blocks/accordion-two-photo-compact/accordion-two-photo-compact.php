<?php
$default_classes = [
    'container'   => 'container',
    'list'        => 'list',
    'item'        => 'item',
    'content'     => 'content',
    'block-title' => 'block-title',
    'count'       => 'count',
    'title'       => 'title',
    'block-photo' => 'block-photo',
    'photo1'      => 'photo1',
    'photo1-img'  => 'photo1-img',
    'photo2'      => 'photo2',
    'photo2-img'  => 'photo2-img',
    'text'        => 'text',
    'icon'        => 'icon'


];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$items = get_field('accordion') ?: [];

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['accordion-two-photo-compact'] ?? []);
}
?>

<section class="section">
    <div class="container <?= esc_attr($classes['container']); ?>">
        <?php if ($items) : ?>
            <div class="<?= esc_attr($classes['list']); ?>">

                <?php $count = 0; ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $count++;
                    $title      = $item['title'];
                    $text       = $item['text'];
                    $photo1_url = $item['photo1']["sizes"]['large'];
                    $photo1_alt = $item['photo1']['alt'] ?: $item['photo1']['title'];
                    $photo2_url = $item['photo2']["sizes"]['large'];
                    $photo2_alt = $item['photo2']['alt'] ?: $item['photo2']['title'];
                    ?>
                    <div class="<?= esc_attr($classes['item']); ?>">
                        <?php if ($title) : ?>
                            <div class="<?= esc_attr($classes['block-title']); ?>">
                                <div class="<?= esc_attr($classes['count']); ?>"><?= sprintf("%02d", $count); ?></div>
                                <h2 class="h2-big <?= esc_attr($classes['title']); ?>"><?= esc_html($title); ?></h2>
                            </div>
                        <?php endif; ?>
                        <div class="<?= esc_attr($classes['content']); ?>">
                            <?php if ($text) : ?>
                                <div class="<?= esc_attr($classes['text']); ?>">
                                    <?= $text; ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?= esc_attr($classes['block-photo']); ?>">
                                <div class="<?= esc_attr($classes['photo1']); ?>">
                                    <img src="<?= esc_url($photo1_url); ?>" class="<?= esc_attr($classes['photo1-img']); ?>" alt="<?= $photo1_alt; ?>">
                                </div>
                                <div class="<?= esc_attr($classes['photo2']); ?>">
                                    <img src="<?= esc_url($photo2_url); ?>" class="<?= esc_attr($classes['photo2-img']); ?>" alt="<?= $photo2_alt; ?>">
                                </div>
                                <img class="<?= esc_attr($classes['icon']); ?>" width="24" height="24" aria-hidden="true" src="<?= get_template_directory_uri(); ?>/assets/img/icon-stars-gradient.svg" alt="icon stars">
                            </div>

                        </div>

                    </div>
                <? endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
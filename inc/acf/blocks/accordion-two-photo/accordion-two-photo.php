<?php
$default_classes = [
    'container' => 'container',
    'item' => 'item',
    'content' => 'content',
    'block-title' => 'block-title',
    'count' => 'count',
    'title' => 'title',
    'block-photo' => 'block-photo',
    'photo1' => 'photo1',
    'photo1-img' => 'photo1-img',
    'photo2' => 'photo2',
    'photo2-img' => 'photo2-img',
    'text' => 'text',
    'icon' => 'icon'


];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$items = get_field('accordion') ?: [];

// $photo_url = esc_url(get_field('photo')["sizes"]['large']);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($items);
// echo '</pre>';
// print_r($photo_url);

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['accordion-two-photo'] ?? []);
}
?>

<!-- <section class="section <?php echo esc_attr($classes['photo-text']); ?>"> -->
<section class="section ">
    <div class="container <?php echo esc_attr($classes['container']); ?>">
        <?php if ($items) : ?>
            <div class="list">

                <?php $count = 0; ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $count++;
                    $title = $item['title'];
                    $text = $item['text'];
                    $photo1_url = $item['photo1']["sizes"]['large'];
                    // echo $photo1_url;
                    $photo1_alt = $item['photo1']['alt'] ?: $item['photo1']['title'];
                    $photo2_url = $item['photo2']["sizes"]['large'];
                    $photo2_alt = $item['photo2']['alt'] ?: $item['photo2']['title'];
                    ?>
                    <div class="<?php echo esc_attr($classes['item']); ?>">
                        <?php if ($title) : ?>
                            <div class="<?php echo esc_attr($classes['block-title']); ?>">
                                <div class="<?php echo esc_attr($classes['count']); ?>"><?php echo sprintf("%02d", $count);?></div>
                                <h2 class="h2-big <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h2>
                            </div>
                        <?php endif; ?>
                        <div class="<?php echo esc_attr($classes['content']); ?>">
                            <?php if ($text) : ?>
                                <div class="<?php echo esc_attr($classes['text']); ?>">
                                    <?php echo $text; ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?php echo esc_attr($classes['block-photo']); ?>">
                                <div class="<?php echo esc_attr($classes['photo1']); ?>">
                                    <img src="<?php echo esc_url($photo1_url); ?>" class="<?php echo  esc_attr($classes['photo1-img']); ?>" alt="<?php echo $photo1_alt; ?>">
                                </div>
                                <div class="<?php echo esc_attr($classes['photo2']); ?>">
                                    <img src="<?php echo esc_url($photo2_url); ?>" class="<?php echo  esc_attr($classes['photo2-img']); ?>" alt="<?php echo $photo2_alt; ?>">
                                </div>
                                <svg class="<?php echo esc_attr($classes['icon']); ?>">
                                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-stars"></use>
                                </svg>
                            </div>

                        </div>

                    </div>
                <? endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
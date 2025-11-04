<?php
$default_classes = [
    'section' => 'section',
    'active' => 'active',
    'container' => 'container',
    'main-title' => 'main-title',
    'title-wrap' => 'title-wrap',
    'subtitle' => 'subtitle',
    'list' => 'list',
    'item' => 'item',
    'content' => 'content',
    'block-title' => 'block-title',
    'count' => 'count',
    'title' => 'title',
    'block-photo' => 'block-photo',
    'photo1' => 'photo1',
    'photo1-img' => 'photo1-img',
    'text' => 'text',
    'icon' => 'icon',
    'btn1' => 'btn1',
    'btn2' => 'btn2'



];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$main_title = get_field('main_title');
$items = get_field('accordion') ?: [];
$subtitle = get_field('subtitle');
$btn_text = get_field('btn_text');
$btn_url = get_field('btn_page');

$is_mode_click_for_touch = get_field('mode_click_for_touch');

if ($is_mode_click_for_touch){
    print_r("Click mode: true");

} else{
    print_r("Click mode: false");
}

// $photo_url = esc_url(get_field('photo')["sizes"]['large']);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($items);
// echo '</pre>';
// print_r($photo_url);

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['accordion-one-photo'] ?? []);
}
?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <?php if ($items) : ?>
            <div class="label-subtitle <?php echo esc_attr($classes['subtitle']); ?>"><?php echo esc_html($subtitle); ?></div>
            <div class="<?php echo esc_attr($classes['title-wrap']); ?>">
                <h2 class="h2-big <?php echo esc_attr($classes['main-title']); ?>"><?php echo esc_html($main_title); ?></h2>
                <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn1']); ?>"><?php echo esc_html($btn_text); ?></a>
            </div>

            <div id="slider-one-photo" data-click-mode="<?php echo $is_mode_click_for_touch ? 'true' : 'false'; ?>" class="<?php echo esc_attr($classes['list']); ?>">
                <?php $count = 0; ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $count++;
                    $title = $item['title'];
                    $text = $item['text'];
                    $photo1_url = $item['photo1']["sizes"]['large'];
                    $photo1_alt = $item['photo1']['alt'] ?: $item['photo1']['title'];
                    ?>
                    <div class="<?php echo esc_attr($classes['item']); ?>">
                        <?php if ($title) : ?>
                            <div class="<?php echo esc_attr($classes['block-title']); ?>">
                                <div class="<?php echo esc_attr($classes['count']); ?>"><?php echo sprintf("%02d", $count); ?></div>
                                <h5 class="h5 <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($title); ?></h2>
                            </div>
                        <?php endif; ?>
                        <div class="<?php echo esc_attr($classes['content']); ?>">
                            <?php if ($text) : ?>
                                <div class="<?php echo esc_attr($classes['text']); ?>">
                                    <?php echo $text; ?>
                                    <?php echo $text; ?>
                                </div>
                            <?php endif; ?>
                            <div class="<?php echo esc_attr($classes['block-photo']); ?>">
                                <div class="<?php echo esc_attr($classes['photo1']); ?>">
                                    <img src="<?php echo esc_url($photo1_url); ?>" class="<?php echo  esc_attr($classes['photo1-img']); ?>" alt="<?php echo $photo1_alt; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                <? endforeach; ?>
            </div>
            <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn2']); ?>"><?php echo esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>
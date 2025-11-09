<?php
$default_classes = [
    'section' => 'section',
    'container' => 'container',
    'title' => 'title',
    'subtitle' => 'subtitle',
    'list' => 'list',
    'item' => 'item',
    'block-photo' => 'block-photo',
    'photo' => 'photo',
    'text' => 'text',
    'icon' => 'icon',
    'btn1' => 'btn1',
    'btn2' => 'btn2'

];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = get_field('title');
$subtitle = get_field('subtitle');
$btn_text = get_field('btn_text');
$btn_url = get_field('btn_page');

$is_mode_click_for_touch = get_field('mode_click_for_touch');

// if ($is_mode_click_for_touch){
//     print_r("Click mode: true");

// } else{
//     print_r("Click mode: false");
// }

// $photo_url = esc_url(get_field('photo')["sizes"]['large']);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($items);
// echo '</pre>';
// print_r($photo_url);

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['news'] ?? []);
}
?>

<section class="section <?php echo esc_attr($classes['section']); ?> ">
    <div class="container ">
        <?php if ($items) : ?>
            <div class="label-subtitle <?php echo esc_attr($classes['subtitle']); ?>"><?php echo esc_html($subtitle); ?></div>
            <!-- <div class="<?php echo esc_attr($classes['title-wrap']); ?>"> -->
            <h2 class="h2-big <?php echo esc_attr($classes['title']); ?>"><?php echo esc_html($main_title); ?></h2>
            <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn1']); ?>"><?php echo esc_html($btn_text); ?></a>
            <!-- </div> -->

            <div class="<?php echo esc_attr($classes['list']); ?>">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $text = $item['text'];
                    $label = $item['label'];
                    $photo_url = $item['photo']["sizes"]['large'];
                    $photo_alt = $item['photo']['alt'] ?: $item['photo']['title'];

                    ?>
                    <div class="<?php echo esc_attr($classes['item']); ?>">
                        <div class="<?php echo esc_attr($classes['content']); ?>">
                            <!-- <div class="<?php echo esc_attr($classes['block-photo']); ?>"> -->
                            <div class="<?php echo esc_attr($classes['photo-wrap']); ?>">
                                <img src="<?php echo esc_url($photo_url); ?>" class="<?php echo  esc_attr($classes['photo-img']); ?>" alt="<?php echo $photo_alt; ?>">
                            </div>
                            <!-- </div> -->
                            <?php if ($text) : ?>
                                <div class="<?php echo esc_attr($classes['text']); ?>">
                                    <?php echo $text; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <? endforeach; ?>
            </div>
            <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn2']); ?>"><?php echo esc_html($btn_text); ?></a>
            <a href="<?php esc_url($btn_url); ?>" class="btn <?php echo esc_attr($classes['btn']); ?>"><?php echo esc_html($btn_text); ?></a>
        <?php endif; ?>
    </div>
</section>
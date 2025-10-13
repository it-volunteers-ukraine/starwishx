<?php
$default_classes = [
    'container' => 'container',
    'title' => 'title',
    'photo' => 'photo',
    'content' => 'content',
    'text' => 'text',
    'content-text' => 'content-text',
    'contacts' => 'contacts',
    'icon' => 'icon',
    'icon2' => 'icon2',
    'icon3-wrap' => 'icon3-wrap',
    'icon3-wrap-in' => 'icon3-wrap-in',
    'icon3-bg' => 'icon3-bg',
    'icon3' => 'icon3',
    'icon4-glow-container1' => 'icon4-glow-container1',
    'icon4-mask' => 'icon4-mask',
    'icon4-light' => 'icon4-light',
    'icon4-bg' => 'icon4-bg',
    'icon5-bg1' => 'icon5-bg1',
    'icon5-bg2' => 'icon5-bg2',
    'icon5-mask' => 'icon5-mask',
    'icon6-bg' => 'icon6-bg',
    'icon6-mask' => 'icon6-mask',
    'icons-tmpl' => 'icons-tmpl'


];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title = esc_html(get_field('title'));
$photo_url = esc_url(get_field('photo')["sizes"]['large']);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($photo_url);
// echo '</pre>';
// print_r($photo_url);
$text = esc_html(get_field('text'));
// $contacts_list= get_field('contacts');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['photo-text'] ?? []);
    // $classes = array_merge($default_classes, $modules['photo-text'] ?? []);
}
?>

<!-- <section class="section <?php echo esc_attr($classes['photo-text']); ?>"> -->
<section class="section ">
    <div class="container <?php echo esc_attr($classes['container']); ?>">
        <?php if ($title) : ?>
            <h2 class="<?php echo esc_attr($classes['title']); ?>"><?php echo $title; ?></h2>
        <?php endif; ?>
        <div class="<?php echo esc_attr($classes['content']); ?>">
            <?php if ($photo_url) : ?>
                <div class="<?php echo esc_attr($classes['photo']); ?>">
                    <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($title); ?>">
                </div>
            <?php endif; ?>
            <div class="<?php echo esc_attr($classes['content-text']); ?>">

                <?php if ($text) : ?>
                    <p class="<?php echo esc_attr($classes['text']); ?>">
                        <?php echo esc_html($text); ?>
                    </p>
                <?php endif; ?>
                <!-- <?php if ($contacts_list) : ?> -->
                <!-- <div class="<?php echo esc_attr($classes['contacts']); ?>"> -->
                <!-- <ul> -->
                <!-- <?php foreach ($contacts_list as $contact) :
                                $contact_name = $contact['name'];
                                $contact_link = $contact['link'];
                        ?> -->
                <!-- <li> -->
                <!-- <?php if ($contact_link) : ?> -->
                <!-- <a href="<?php echo esc_url($contact_link); ?>"><?php echo esc_html($contact_name); ?></a> -->
                <!-- <?php else : ?> -->
                <!-- <?php echo esc_html($contact_name); ?> -->
                <!-- <?php endif; ?> -->
                <!-- </li> -->
                <!-- <?php endforeach; ?> -->
                <!-- </ul> -->
                <!-- </div> -->
                <!-- <?php endif; ?> -->
                <svg class="<?php echo esc_attr($classes['icon']); ?>">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-element_planet_3"></use>
                </svg>
                <svg class="<?php echo esc_attr($classes['icon2']); ?>">
                    <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-element_planet_3"></use>
                </svg>
                <div class="<?php echo esc_attr($classes['icon3-wrap']); ?>">
                    <!-- <svg class="<?php echo esc_attr($classes['icon3-bg']); ?>">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star1-bg"></use>
                    </svg> -->
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon3-bg']); ?>" alt="">
                    <div class="<?php echo esc_attr($classes['icon3-wrap-in']); ?>">
                        <svg class="<?php echo esc_attr($classes['icon3']); ?>">
                            <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-star1-circle"></use>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="<?php echo esc_attr($classes['icons-tmpl']); ?>">
            <div class="<?php echo esc_attr($classes['icon4-glow-container1']); ?>">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-icon.png" class="<?php echo esc_attr($classes['icon4-mask']); ?>">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon4-bg']); ?>" alt="">
                <div class="<?php echo esc_attr($classes['icon4-light']); ?>"></div>
            </div>
            <div class="<?php echo esc_attr($classes['icon4-glow-container1']); ?>">
                <!-- <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon5-bg1']); ?>" alt=""> -->
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon5-bg2']); ?>" alt="">
    
                <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-icon.png" class="<?php echo esc_attr($classes['icon5-mask']); ?>">
            </div>
            <!-- <div class="<?php echo esc_attr($classes['icon4-glow-container1']); ?>"> -->
                <!-- <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon5-bg1']); ?>" alt=""> -->
                <!-- <div class="<?php echo esc_attr($classes['icon6-bg']); ?>"> -->
                    
                <!-- </div> -->
                <!-- <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon6-bg']); ?>" alt=""> -->
    
                <!-- <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-icon.png" class="<?php echo esc_attr($classes['icon6-mask']); ?>"> -->
            <!-- </div> -->
        </div>
    </div>
</section>
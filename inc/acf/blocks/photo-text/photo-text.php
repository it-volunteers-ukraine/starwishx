<?php
$default_classes = [
    'title' => 'title',
    'photo' => 'photo',
    'text' => 'text',
    'contacts' => 'contacts',


];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
$title= esc_html(get_field('title'));
$photo_url= esc_url(get_field('photo')["sizes"]['large']);
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($photo_url);
// echo '</pre>';
// print_r($photo_url);
$text= esc_html(get_field('text'));
// $contacts_list= get_field('contacts');

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    // $classes = array_merge($default_classes, $modules['photo-text'] ?? []);
    $classes = array_merge($default_classes, $modules['photo-text'] ?? []);
}
?>

<!-- <section class="section <?php echo esc_attr($classes['photo-text']); ?>"> -->
<section class="section ">
    <div class="container">
        <?php if ($title) : ?>
            <h2 class="<?php echo esc_attr($classes['title']); ?>"><?php echo $title; ?></h2>
        <?php endif; ?>
        <!-- <div class="<?php echo esc_attr($classes['inner']); ?>"> -->
            <?php if ($photo_url) : ?>
                <!-- <div class="<?php echo esc_attr($classes['photo']); ?>"> -->
                    <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($title); ?>">
                <!-- </div> -->
            <?php endif; ?>
            <?php if ($text) : ?>
                <div class="<?php echo esc_attr($classes['text']); ?>">
                    <?php echo esc_html($text); ?>
                </div>
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
        <!-- </div> -->
    </div>
</section>
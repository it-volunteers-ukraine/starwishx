<?php
// Loading classes
$default_classes = [
    'image' => 'image',


];

$img = esc_url(get_field('image'));
$end_date = esc_html(get_field('end_date'));

$today = current_time('Ymd');


if ( $end_date  > $today ) {

        wp_enqueue_style(
            'snow-css',
            'https://cdn.jsdelivr.net/gh/Alaev-Co/snowflakes/dist/snow.min.css'
        );

        wp_enqueue_script(
            'snow-js',
            'https://cdn.jsdelivr.net/gh/Alaev-Co/snowflakes/dist/Snow.min.js',
            [],
            null,
            true
        );

        wp_add_inline_script( 'snow-js', 'new Snow();' );
    }

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['christmas-tree'] ?? []);
}


// Active item
$active_title = get_the_title();
?>

<?php if ($end_date > $today) : ?>
    <img class="<?php echo esc_attr($classes['image']); ?>" src="<?php echo $img; ?>" border="0" alt="rozhdestvenskaya-elka-animatsionnaya-kartinka-0335" />
<?php endif; ?>